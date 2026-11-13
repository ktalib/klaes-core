<?php

namespace App\Console\Commands;

use App\Services\Survey\SurveyCoordinates;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * CR 11112026-2 item 8 prototype: the beacons with coordinates, for the public Plan Coordinate Check page
 * (public/maps/survey-plan-check.html, no login; user, 2026-11-12). Writes public/maps/data/beacons.json with the
 * beacon number, X (Easting), Y (Northing), plan no. and district only. Pairs that are not a six-digit
 * Easting and a seven-digit Northing are left out (they are on the coordinate-fix "needs checking" list).
 */
class SurveyExportMapBeacons extends Command
{
    protected $signature = 'survey:export-map-beacons';

    protected $description = 'Write the beacon coordinates for the public Plan Coordinate Check prototype';

    public function handle(): int
    {
        $db = DB::connection('sqlsrv');
        $out = [];
        $skipped = 0;
        foreach (['survey_beacon_register' => 'B', 'survey_control_point_register' => 'C'] as $table => $kind) {
            $rows = $db->table($table)->whereNull('deleted_at')->whereNotNull('coord_x')->whereNotNull('coord_y')
                ->orderBy('beacon_from')->get(['beacon_from', 'coord_x', 'coord_y', 'plan_no', 'prop_district', 'prop_district_other']);
            foreach ($rows as $r) {
                $x = (float) $r->coord_x;
                $y = (float) $r->coord_y;
                if (! SurveyCoordinates::isEasting($x) || ! SurveyCoordinates::isNorthing($y)) {
                    $skipped++;
                    continue;
                }
                $district = trim((string) ($r->prop_district === 'Other' ? $r->prop_district_other : $r->prop_district));
                $out[] = [trim((string) $r->beacon_from), round($x, 3), round($y, 3), $kind, (string) $r->plan_no, $district];
            }
        }

        $path = public_path('maps/data/beacons.json');
        @mkdir(dirname($path), 0777, true);
        file_put_contents($path, json_encode([
            'generated' => now()->format('Y-m-d H:i'),
            'grid' => 'Minna / UTM zone 32N',
            'fields' => ['beacon', 'x', 'y', 'kind', 'plan_no', 'district'],
            'rows' => $out,
        ], JSON_UNESCAPED_UNICODE));
        $this->info(count($out) . " beacons written to $path ($skipped left out: not a valid Easting/Northing pair).");

        return self::SUCCESS;
    }
}
