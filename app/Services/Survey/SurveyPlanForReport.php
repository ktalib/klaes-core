<?php

namespace App\Services\Survey;

use App\Models\Survey\SurveyPlanUpload as Plan;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The uploaded survey plan behind a Surveyor's Report (Private) row (user, 2026-11-18: "let's use the survey plan's
 * traverse"). A plan holds no file number of its own, so it is found by, in order: the file number on its
 * Application Plan, a beacon number both share, or the same TP No. and Plot No. Read only.
 */
class SurveyPlanForReport
{
    private ?Collection $plans = null;

    /** @param string[] $beacons the row's beacon numbers */
    public function find(?string $fileNumber, array $beacons, ?string $tpNo, ?string $plotNo): ?Plan
    {
        $plans = $this->plans();
        $up = fn ($v) => mb_strtoupper(trim((string) $v));

        if ($file = $up($fileNumber)) {
            if ($hit = $plans->first(fn ($p) => $p->_file_number === $file)) return $hit;
        }
        $wanted = array_filter(array_map($up, $beacons));
        if ($wanted) {
            if ($hit = $plans->first(fn ($p) => array_intersect($p->_beacons, $wanted))) return $hit;
        }
        if ($up($tpNo) !== '' && $up($plotNo) !== '') {
            return $plans->first(fn ($p) => $up($p->tp_no) === $up($tpNo) && $up($p->plot_no) === $up($plotNo));
        }

        return null;
    }

    /** What the row's Computation & Field Observations columns show from the plan's traverse. */
    public function observations(Plan $plan): array
    {
        $lines = app(PlanTraverseSheet::class)->lines($plan);
        $pd = (array) $plan->plot_data;
        $n = count($lines);

        return [
            'readings'   => $n,
            'closed'     => $n >= 3 && mb_strtoupper($lines[0]['from']) === mb_strtoupper($lines[$n - 1]['to']),
            // Only a plotting states its closing error; lines worked out from coordinates close by construction.
            'misclosure' => isset($pd['closing_error_m']) ? (float) $pd['closing_error_m'] : null,
        ];
    }

    /** Live plans, newest first, with their file number (from the Application Plan) and beacon numbers. */
    private function plans(): Collection
    {
        if ($this->plans !== null) return $this->plans;

        $db = DB::connection('sqlsrv');
        $files = $db->table('survey_application_plans')->whereNull('deleted_at')->orderByDesc('id')->get(['survey_plan_upload_id', 'fields'])
            ->groupBy('survey_plan_upload_id')
            ->map(fn ($g) => mb_strtoupper(trim((string) (json_decode((string) $g->first()->fields, true)['file_number'] ?? ''))));
        $beacons = $db->table('survey_plan_reference_beacons')->get(['survey_plan_upload_id', 'beacon_no'])
            ->groupBy('survey_plan_upload_id')
            ->map(fn ($g) => $g->map(fn ($b) => mb_strtoupper(trim((string) $b->beacon_no)))->filter()->values()->all());

        return $this->plans = Plan::query()->orderByDesc('id')->get()->each(function (Plan $p) use ($files, $beacons) {
            $p->_file_number = $files->get($p->id, '');
            $p->_beacons = $beacons->get($p->id, []);
        });
    }
}
