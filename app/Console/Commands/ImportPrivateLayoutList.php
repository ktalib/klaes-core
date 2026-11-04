<?php

namespace App\Console\Commands;

use App\Models\Survey\SurveyPrivateLayout as PL;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * Private Layout from the Ministry's "List of Private Layout and their Locations" (user, 2026-11-03:
 * LAYOUT PLANS-1.xlsx). Every row is loaded as written, including rows with only an S/N; what is missing
 * or odd goes in check_note. The LGA is set only where the location names one outright. All rows are
 * Approved (the list is of approved layouts; the year is the YEAR OF APPROVAL column). Dry run by
 * default; --apply loads; --undo removes the load.
 */
class ImportPrivateLayoutList extends Command
{
    protected $signature = 'survey:import-private-layouts {file? : the .xlsx list} {--apply : load the rows} {--undo : remove the rows this import loaded}';

    protected $description = 'Load the List of Private Layout and their Locations into Survey > Private Layout (source layout_plans_list).';

    public const SOURCE = 'layout_plans_list';

    /** LGA names as written in the list => the LGA list's spelling. Only explicit mentions count. */
    private const LGAS = [
        '/\bKANO\s+MUN(ICIPAL|CIPAL)?\b/' => 'Kano Municipal',
        '/\bMUN(ICIPAL|CIPAL)\b/'        => 'Kano Municipal',
        '/\bFAGGE\b/'                     => 'Fagge',
        '/\bKUMBOT(SO|OS)\b/'             => 'Kumbotso',
        '/\bGWALE\b/'                     => 'Gwale',
        '/\bNAS+AR+A+WA\b|\bNAS+RA+WA\b/' => 'Nasarawa',
        '/\bUNGOGO\b/'                    => 'Ungogo',
        '/\bTARAUNI\b/'                   => 'Tarauni',
        '/\bDALA\b/'                      => 'Dala',
    ];

    public function handle(): int
    {
        if ($this->option('undo')) {
            $n = PL::withTrashed()->where('source', self::SOURCE)->count();
            if (! $this->confirm("Remove the {$n} rows loaded from the list?")) return self::SUCCESS;
            PL::withTrashed()->where('source', self::SOURCE)->forceDelete();
            $this->info("Removed {$n} rows.");

            return self::SUCCESS;
        }

        $file = $this->argument('file');
        if (! $file || ! is_file($file)) {
            $this->error('Give the path of the LAYOUT PLANS .xlsx file.');

            return self::FAILURE;
        }
        $rows = $this->readList($file);
        $already = PL::withTrashed()->where('source', self::SOURCE)->count();
        $this->line(count($rows) . ' list rows read; ' . $already . ' already loaded.');
        $this->line(collect($rows)->whereNotNull('prop_lga')->count() . ' with an LGA named in the location, '
            . collect($rows)->whereNotNull('check_note')->count() . ' with a note to check.');
        foreach ($rows as $r) if ($r['check_note']) $this->line("  {$r['register_sn']}. " . ($r['layout_no'] ?: '—') . " | {$r['check_note']}");
        if ($this->getOutput()->isVerbose()) foreach ($rows as $r) if ($r['prop_lga']) $this->line("  LGA {$r['register_sn']}. {$r['location_text']} => {$r['prop_lga']}");

        if ($already) {
            $this->warn('The list is already loaded. Run --undo first to load it again.');

            return self::FAILURE;
        }
        if (! $this->option('apply')) {
            $this->info('Dry run: nothing written. Add --apply to load.');

            return self::SUCCESS;
        }

        DB::connection('sqlsrv')->transaction(function () use ($rows) {
            $now = now();
            foreach ($rows as $r) {
                // PVL-YYYY-nnnn, the page's internal reference (never shown), one by one as the page does.
                DB::connection('sqlsrv')->table('survey_private_layouts')->insert($r + [
                    'layout_ref' => PL::nextRef('layout_ref', 'PVL', 4),
                    'status'     => 'Approved',
                    'prop_state' => 'Kano',
                    'source'     => self::SOURCE,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        });
        $this->info(count($rows) . ' rows loaded.');

        return self::SUCCESS;
    }

    private function readList(string $file): array
    {
        $sheet = IOFactory::load($file)->getSheet(0)->toArray(null, true, false, false);
        $one = fn ($v) => ($v = trim(preg_replace('/\s+/u', ' ', (string) $v))) === '' ? null : $v;

        $rows = [];
        foreach ($sheet as $line) {
            $sn = $line[1] ?? null;
            if (! is_numeric($sn)) continue;                       // titles and the header row
            $name = $one($line[2] ?? null);
            $no = $one($line[3] ?? null);
            $no = $no ? preg_replace('/\s+/', '', $no) : null;      // an identifier: spaces only removed
            $place = $one($line[4] ?? null);
            $year = is_numeric($line[5] ?? null) ? (int) $line[5] : null;

            $lga = null;
            foreach (self::LGAS as $re => $canonical) {
                // A road named after a place ("ALONG KUMBOTSO ROAD") does not name the LGA.
                if ($place && preg_match($re, preg_replace("/\b[A-Z']+\s+(ROAD|RD|STREET|ST)\b/", '', mb_strtoupper($place)))) { $lga = $canonical; break; }
            }

            $rows[] = ['register_sn' => (int) $sn, 'layout_name' => $name, 'layout_no' => $no, 'location_text' => $place,
                       'approval_year' => $year, 'prop_lga' => $lga];
        }

        // What to check against the plan: missing details, an unfinished number, a year that cannot be right, repeats.
        $counts = collect($rows)->pluck('layout_no')->filter()->map(fn ($n) => mb_strtoupper($n))->countBy();
        foreach ($rows as &$r) {
            $notes = [];
            if (! $r['layout_name'] && ! $r['layout_no'] && ! $r['location_text']) {
                $notes[] = 'No details in the list (only the S/N' . ($r['approval_year'] ? ' and year' : '') . ')';
            } else {
                if (! $r['layout_no']) $notes[] = 'No layout number in the list';
                elseif (str_ends_with($r['layout_no'], '/')) $notes[] = 'Layout number incomplete';
                if (! $r['layout_name']) $notes[] = 'No name in the list';
                if (! $r['location_text']) $notes[] = 'No location in the list';
                if (! $r['approval_year']) $notes[] = 'No year of approval in the list';
            }
            if ($r['layout_no'] && preg_match('#/((?:19|20)\d\d)/#', $r['layout_no'], $m) && $r['approval_year'] && $r['approval_year'] < (int) $m[1]) {
                $notes[] = "Year of approval ({$r['approval_year']}) is before the year in the number ({$m[1]})";
            }
            if ($r['layout_no'] && ($counts[mb_strtoupper($r['layout_no'])] ?? 0) > 1) {
                $notes[] = 'Layout number used ' . $counts[mb_strtoupper($r['layout_no'])] . ' times in the list';
            }
            $r['check_note'] = $notes ? implode('; ', $notes) : null;
        }
        unset($r);

        return $rows;
    }
}
