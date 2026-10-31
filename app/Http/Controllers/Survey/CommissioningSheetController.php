<?php

namespace App\Http\Controllers\Survey;

use App\Http\Controllers\Controller;
use App\Models\Survey\SurveyCoFile;
use App\Models\User;
use App\Support\AddressBuilder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Commissioning Sheet for Survey files (CR 30102026 item 2): CO files and the GKN,
 * Misc KN, LPKN, LPPL and RDKN application files. The sheet is a copy of the MLPP /
 * OSS one (commissioning_sheet/pdf), issued by the Survey Department. Read-only:
 * built from the file's own record on each print, nothing is stored.
 */
class CommissioningSheetController extends Controller
{
    /** A CO file, from the CO register. */
    public function coFile(SurveyCoFile $coFile)
    {
        $stamp = $coFile->commission_date ?? $coFile->created_at;
        // Several LGAs are stored "A | B"; they print "A & B", as the CO list shows them.
        $lga = AddressBuilder::resolve($coFile->prop_lga);

        return $this->render([
            'file_number'         => $coFile->co_file_no,
            'file_type'           => 'CO File',
            'related_file_number' => $coFile->related_file_no,
            'file_title'          => $coFile->title,
            'tp_number'           => implode(', ', $coFile->tpNumbers()),
            'plot_number'         => $coFile->prop_plot,
            'district'            => $coFile->resolvedDistrict('prop_'),
            'lga'                 => $lga,
            'location'            => $this->joinUnique([$coFile->resolvedStreet('prop_'), $lga, $coFile->prop_state]),
            'date'                => $stamp,
            // A commission date carries no time; the time is when the record was made.
            'time'                => $coFile->created_at,
            'by'                  => $this->userName($coFile->created_by),
        ]);
    }

    /**
     * A GKN, Misc KN, LPKN, LPPL or RDKN file, from its indexing row (the GKN >
     * Applications register). A number commissioned by the generator also has a
     * gkn_file_no row, which holds the commissioning date, time and officer.
     */
    public function applicationFile(int $id)
    {
        $db = DB::connection('sqlsrv');
        $file = $db->table('file_indexings')->where('id', $id)->first();
        $type = $file ? GknApplicationController::typeOf($file->file_number) : null;
        abort_unless($type, 404);

        $gkn = $db->table('gkn_file_no')
            ->where('full_file_number', $file->file_number)
            ->where(fn ($w) => $w->whereNull('is_deleted')->orWhere('is_deleted', 0))
            ->orderByDesc('id')
            ->first();

        return $this->render([
            'file_number'         => $file->file_number,
            'file_type'           => GknApplicationController::TYPES[$type]['label'],
            'related_file_number' => $this->relatedList($file->related_fileno),
            'file_title'          => $file->file_title,
            'tp_number'           => $file->tp_no ?: ($gkn->tp_no ?? null),
            'plot_number'         => $file->plot_number,
            'district'            => $file->district,
            'lga'                 => $file->lga,
            'location'            => $this->joinUnique([$file->street_name ?? null, $file->lga]),
            'date'                => $gkn->commissioning_date ?? $file->created_at,
            'time'                => $gkn->commissioning_time ?? $file->created_at,
            'by'                  => $this->userName($gkn->created_by ?? $file->created_by),
            'tracking_id'         => $gkn->tracking_id ?? $file->tracking_id,
        ]);
    }

    private function render(array $d)
    {
        $data = [
            'file_number'         => trim((string) $d['file_number']),
            'file_type'           => $d['file_type'],
            'related_file_number' => trim((string) ($d['related_file_number'] ?? '')),
            'file_title'          => trim((string) ($d['file_title'] ?? '')),
            'tp_number'           => trim((string) ($d['tp_number'] ?? '')),
            'plot_number'         => trim((string) ($d['plot_number'] ?? '')),
            'district'            => trim((string) ($d['district'] ?? '')),
            'location'            => $d['location'],
            'commissioning_date'  => $this->format($d['date'] ?? null, 'Y-m-d'),
            'commissioning_time'  => $this->format($d['time'] ?? null, 'h:i A'),
            'created_by'          => $d['by'],
            'tracking_id'         => trim((string) ($d['tracking_id'] ?? '')),
        ];

        // The LGA prints in caps in the Location line, as on the MLPP sheet.
        $lga = trim((string) ($d['lga'] ?? ''));
        if ($lga !== '' && $data['location'] !== '') {
            $data['location'] = preg_replace('/' . preg_quote($lga, '/') . '/i', mb_strtoupper($lga), $data['location']);
        }

        return view('survey_module.commissioning_sheet.pdf', compact('data'));
    }

    /** Joined once each, blanks dropped. The Location is Street, LGA, State: the plot number has its own line. */
    private function joinUnique(array $parts): string
    {
        $out = [];
        foreach ($parts as $p) {
            $p = trim((string) $p);
            if ($p !== '' && ! in_array(mb_strtolower($p), array_map('mb_strtolower', $out), true)) $out[] = $p;
        }

        return implode(', ', $out);
    }

    /** file_indexings.related_fileno is plain text on some rows and a JSON list on others. */
    private function relatedList($raw): string
    {
        $raw = trim((string) $raw);
        $list = str_starts_with($raw, '[') ? json_decode($raw, true) : null;

        return is_array($list) ? $this->joinUnique($list) : $raw;
    }

    private function format($value, string $format): string
    {
        if (blank($value)) return '';
        try {
            return Carbon::parse($value)->format($format);
        } catch (\Throwable $e) {
            return (string) $value;
        }
    }

    private function userName($id): string
    {
        if (blank($id) || ! is_numeric($id)) return (string) ($id ?? '');
        $u = User::find($id);

        return $u ? (trim((string) $u->name) ?: (string) $u->email) : '';
    }
}
