<?php

namespace App\Services\Survey;

use App\Models\Survey\SurveyBeaconRegister;
use App\Models\Survey\SurveyorListEntry;
use App\Models\Survey\SurveyPlanUpload as Plan;
use Illuminate\Support\Facades\DB;

/**
 * Beacons typed on a survey plan go into the Beacon Register (user, 2026-11-18, "yes"): one register line per
 * number not already in it, with the plan's X / Y, surveyor, TP No. and property. GKN plans are Public lines,
 * Examination/Verification plans Private. A number already in the register is left as it is. No teller or
 * payment: the line's remark names the plan it came from. Run inside the plan's save transaction.
 */
class PlanBeaconsToRegister
{
    public function record(Plan $plan, array $beacons, ?int $userId): int
    {
        $db = DB::connection('sqlsrv');
        $surveyor = $plan->surveyors_list_id ? SurveyorListEntry::find($plan->surveyors_list_id) : null;
        $added = 0;

        foreach ($beacons as $b) {
            if (($b['source'] ?? 'typed') !== 'typed') continue;
            $no = SurveyBeaconRegister::normalise($b['beacon_no'] ?? null);
            $parsed = SurveyBeaconRegister::parseBeacon($no);
            if (! $parsed) continue;
            if ($db->table('survey_beacon_numbers')->where('beacon_no', $no)->whereNull('deleted_at')->exists()) continue;

            $x = is_numeric($b['coord_x'] ?? null) ? $b['coord_x'] : null;
            $y = is_numeric($b['coord_y'] ?? null) ? $b['coord_y'] : null;
            $date = now();
            $line = SurveyBeaconRegister::create([
                'issue_date'        => $date->toDateString(),
                'quarter'           => $date->quarter, 'month' => $date->month, 'year' => $date->year,
                'beacon_from'       => $no, 'beacon_to' => $no,
                'beacon_series'     => $parsed['series'],
                'beacon_start_no'   => $parsed['n'], 'beacon_end_no' => $parsed['n'],
                'total_issued'      => 1,
                'plan_no'           => $plan->tp_no,
                'surveyor_name'     => $surveyor?->full_name ?: $plan->drawn_by,
                'surveyors_list_id' => $surveyor?->id,
                'surveyor_source'   => $surveyor ? 'staff' : ($plan->drawn_by ? 'other' : null),
                'layout_type'       => $plan->unit === Plan::UNIT_GKN ? 'Government' : 'Private',
                'prop_plot'         => $plan->plot_no,
                'prop_district'     => $plan->district,
                'prop_lga'          => $plan->lga,
                'prop_state'        => 'Kano',
                'location'          => $plan->location,
                'coord_x'           => $x, 'coord_y' => $y,
                'remark'            => 'Typed on survey plan ' . $plan->upload_ref,
                'created_by'        => $userId, 'updated_by' => $userId,
            ]);
            $db->table('survey_beacon_numbers')->insert([
                'survey_beacon_register_id' => $line->id,
                'beacon_no' => $no, 'beacon_series' => $parsed['series'], 'beacon_seq' => $parsed['n'],
                'coord_x' => $x, 'coord_y' => $y,
                'created_by' => $userId, 'updated_by' => $userId, 'created_at' => $date, 'updated_at' => $date,
            ]);
            BeaconPool::markIssued($line->id, $parsed['series'], $parsed['n'], $parsed['n']);
            $added++;
        }

        return $added;
    }
}
