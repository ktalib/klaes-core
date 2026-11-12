<?php

namespace App\Console\Commands;

use App\Services\Survey\SurveyCoordinates;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * CR 11112026-2 item 10: switch X and Y back on the beacons and control points captured the wrong way
 * round (X = Easting, Y = Northing). A dry run without --commit. Every changed row is saved to a CSV first;
 * --undo=<csv> puts those rows back.
 */
class SurveyFixSwappedCoordinates extends Command
{
    protected $signature = 'survey:fix-swapped-coordinates
        {--commit : Write the fix; without this nothing is written}
        {--undo= : CSV written by a --commit run: put those rows back}';

    protected $description = 'Switch X/Y back on Survey beacon and control point registers where they were captured reversed';

    private const TABLES = ['survey_beacon_register', 'survey_control_point_register'];

    public function handle(): int
    {
        $db = DB::connection('sqlsrv');

        if ($csv = $this->option('undo')) {
            $h = fopen($csv, 'r');
            fgetcsv($h);
            $n = 0;
            while ($row = fgetcsv($h)) {
                [$table, $id, $x, $y] = $row;
                if (! in_array($table, self::TABLES, true)) continue;
                $n += $db->table($table)->where('id', $id)->update(['coord_x' => $x === '' ? null : $x, 'coord_y' => $y === '' ? null : $y]);
            }
            fclose($h);
            $this->info("Put back $n rows.");

            return self::SUCCESS;
        }

        $fix = [];
        $odd = [];
        foreach (self::TABLES as $t) {
            foreach ($db->table($t)->whereNull('deleted_at')->whereNotNull('coord_x')->whereNotNull('coord_y')->orderBy('id')->get(['id', 'coord_x', 'coord_y']) as $r) {
                if (SurveyCoordinates::isReversed($r->coord_x, $r->coord_y)) {
                    $fix[] = [$t, $r->id, $r->coord_x, $r->coord_y];
                } elseif (! (SurveyCoordinates::isEasting((float) $r->coord_x) && SurveyCoordinates::isNorthing((float) $r->coord_y))) {
                    $odd[] = [$t, $r->id, $r->coord_x, $r->coord_y];
                }
            }
        }
        $by = fn ($rows) => collect($rows)->countBy(0)->map(fn ($n, $t) => [$t, $n])->values()->all();
        $this->table(['To switch', 'Rows'], $by($fix));
        $this->table(['Left as is: wrong digit count, check by hand', 'Rows'], $by($odd));

        $oddCsv = storage_path('app/survey/coordinate-fix/needs-checking-' . now()->format('Ymd_His') . '.csv');
        @mkdir(dirname($oddCsv), 0777, true);
        $h = fopen($oddCsv, 'w');
        fputcsv($h, ['table', 'id', 'coord_x', 'coord_y']);
        foreach ($odd as $o) fputcsv($h, $o);
        fclose($h);
        $this->line("Rows to check by hand: $oddCsv");

        if (! $this->option('commit')) {
            $this->warn('Dry run: nothing written. Add --commit to switch them.');

            return self::SUCCESS;
        }

        $backup = storage_path('app/survey/coordinate-fix/switched-before-' . now()->format('Ymd_His') . '.csv');
        $h = fopen($backup, 'w');
        fputcsv($h, ['table', 'id', 'coord_x_before', 'coord_y_before']);
        foreach ($fix as $f) fputcsv($h, $f);
        fclose($h);
        $this->line("Before values saved to $backup");

        $n = 0;
        foreach (array_chunk($fix, 200) as $chunk) {
            $db->transaction(function () use ($db, $chunk, &$n) {
                foreach ($chunk as [$t, $id, $x, $y]) {
                    $n += $db->table($t)->where('id', $id)->where('coord_x', $x)->where('coord_y', $y)->update(['coord_x' => $y, 'coord_y' => $x]);
                }
            });
        }
        $this->info("Switched $n rows. Undo: --undo=\"$backup\"");

        return self::SUCCESS;
    }
}
