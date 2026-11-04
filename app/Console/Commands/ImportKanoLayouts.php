<?php

namespace App\Console\Commands;

use App\Models\Survey\SurveyTpNumber as TP;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Public Layout Plans from the Ministry's register of layout drawings (user, 2026-11-03: "KANO LAYOUTS -
 * cleaned.docx"; "don't [use] the general tp no lookup table again"). Every row is loaded as written,
 * including reused drawing numbers, rows with no number or title, and partial dates; the register's
 * NOTES (CHECK) column comes with each row. Dry run by default; --apply loads; --undo removes the load.
 */
class ImportKanoLayouts extends Command
{
    protected $signature = 'survey:import-kano-layouts {file? : the .docx register} {--apply : load the rows} {--undo : remove the rows this import loaded}';

    protected $description = 'Load the KANO LAYOUTS register of layout drawings into Public Layout Plans (survey_tp_numbers, source kano_layouts).';

    public const SOURCE = 'kano_layouts';

    public function handle(): int
    {
        if ($this->option('undo')) {
            $n = TP::where('source', self::SOURCE)->count();
            if (! $this->confirm("Remove the {$n} rows loaded from the register?")) return self::SUCCESS;
            TP::where('source', self::SOURCE)->forceDelete();
            $this->info("Removed {$n} rows.");

            return self::SUCCESS;
        }

        $file = $this->argument('file');
        if (! $file || ! is_file($file)) {
            $this->error('Give the path of the KANO LAYOUTS .docx file.');

            return self::FAILURE;
        }
        $rows = $this->readRegister($file);
        if (! $rows) {
            $this->error('No register table found in the file.');

            return self::FAILURE;
        }

        $already = TP::where('source', self::SOURCE)->count();
        $this->line(count($rows) . ' register rows read; ' . $already . ' already loaded.');
        $this->line(collect($rows)->filter(fn ($r) => $r['tp_no'] === '')->count() . ' without a drawing number, '
            . collect($rows)->filter(fn ($r) => $r['layout_name'] === null)->count() . ' without a title, '
            . collect($rows)->filter(fn ($r) => $r['check_note'] !== null)->count() . ' with a note to check.');
        foreach (array_slice($rows, 0, 5) as $r) $this->line("  {$r['register_sn']}. {$r['tp_no']} | {$r['drawing_date']} | {$r['layout_name']}");

        if ($already) {
            $this->warn('The register is already loaded. Run --undo first to load it again.');

            return self::FAILURE;
        }
        if (! $this->option('apply')) {
            $this->info('Dry run: nothing written. Add --apply to load.');

            return self::SUCCESS;
        }

        $now = now();
        DB::connection('sqlsrv')->transaction(function () use ($rows, $now) {
            foreach (array_chunk($rows, 100) as $chunk) {   // 14 columns x 100 < 2,100 parameters
                DB::connection('sqlsrv')->table('survey_tp_numbers')->insert(array_map(fn ($r) => $r + [
                    'source' => self::SOURCE, 'created_at' => $now, 'updated_at' => $now,
                ], $chunk));
            }
        });
        $this->info(count($rows) . ' rows loaded.');

        return self::SUCCESS;
    }

    /** The register table of the .docx, one array per row, every row kept. */
    private function readRegister(string $file): array
    {
        $zip = new \ZipArchive();
        if ($zip->open($file) !== true) return [];
        $xml = $zip->getFromName('word/document.xml');
        $zip->close();
        if (! $xml) return [];

        $dom = new \DOMDocument();
        $dom->loadXML($xml, LIBXML_NONET);
        $xp = new \DOMXPath($dom);
        $xp->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');

        $cell = fn (\DOMNode $tc) => trim(preg_replace('/\s+/u', ' ', implode('', array_map(fn ($t) => $t->textContent, iterator_to_array($xp->query('.//w:t', $tc))))));
        $one = fn ($v) => $v === '' ? null : $v;

        $out = [];
        foreach ($xp->query('//w:tbl') as $tbl) {
            foreach ($xp->query('./w:tr', $tbl) as $i => $tr) {
                $c = array_map($cell, iterator_to_array($xp->query('./w:tc', $tr)));
                if (count($c) < 6 || ! ctype_digit($c[0])) continue;   // the header row
                // Drawing numbers keep their format: only spaces and stray slashes go.
                $tpNo = trim(preg_replace('/\s+/', '', $c[1]), '/');
                [$prefix, $serial] = str_contains($tpNo, '/') ? [substr($tpNo, 0, strrpos($tpNo, '/')), substr($tpNo, strrpos($tpNo, '/') + 1)] : ['', $tpNo];
                $out[] = [
                    'register_sn'  => (int) $c[0],
                    'tp_no'        => $tpNo,
                    'prefix'       => $prefix,
                    'serial_no'    => $serial,
                    'serial_sort'  => TP::serialSort($serial),
                    'layout_name'  => $one($c[3]),
                    'drawing_date' => $one(mb_substr($c[2], 0, 40)),
                    'remarks'      => $one($c[4]),
                    'check_note'   => $one(mb_substr($c[5], 0, 500)),
                ];
            }
            if ($out) break;
        }

        return $out;
    }
}
