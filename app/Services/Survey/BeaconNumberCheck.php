<?php

namespace App\Services\Survey;

use App\Models\Survey\SurveyBeaconRegister;
use App\Models\Survey\SurveyItsCoordinate;
use App\Services\SurveyItsAnchors;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Beacon Nos (change list p.2, 2026-10-29), for the GKN Surveyors Report and the
 * Surveyor's Report (Private): the beacons on a survey's List of Coordinates,
 * each checked against the Beacon Register (the numbers issued to surveyors):
 *
 *   registered      issued, to this survey's surveyor (or either side names none)
 *   other_surveyor  issued, but the register names a different surveyor
 *   unregistered    not in the Beacon Register
 *
 * plus any other survey whose coordinate list already uses the same number.
 */
class BeaconNumberCheck
{
    public const REGISTERED     = 'registered';
    public const OTHER_SURVEYOR = 'other_surveyor';
    public const UNREGISTERED   = 'unregistered';

    public const LABELS = [
        self::REGISTERED     => 'In Beacon Register',
        self::OTHER_SURVEYOR => 'Issued to another surveyor',
        self::UNREGISTERED   => 'Not in Beacon Register',
    ];

    /** One row per beacon on the anchor's list, in list order. */
    public function forAnchor(array $anchor): Collection
    {
        $coords = SurveyItsCoordinate::query()->tap(fn ($q) => SurveyItsAnchors::scope($q, $anchor))
            ->orderBy('sort_order')->orderBy('id')->get();
        if ($coords->isEmpty()) {
            return collect();
        }

        $codes = $coords->map(fn ($c) => SurveyBeaconRegister::normalise($c->beacon_id))->filter()->unique()->values()->all();

        $issued = collect(DB::connection('sqlsrv')->table('survey_beacon_numbers as n')
            ->join('survey_beacon_register as r', 'r.id', '=', 'n.survey_beacon_register_id')
            ->whereNull('n.deleted_at')->whereNull('r.deleted_at')
            ->whereIn('n.beacon_no', $codes)
            ->get(['n.beacon_no', 'r.id as register_id', 'r.register_no', 'r.surveyor_name', 'r.surveyor_registration', 'r.issue_date', 'r.plan_no', 'r.location', 'r.layout_type']))
            ->groupBy('beacon_no');

        // The same number on another survey's list.
        $elsewhere = SurveyItsCoordinate::query()->whereIn('beacon_id', $codes)
            ->where(function ($q) use ($anchor) {
                foreach (['survey_its_id', 'cadastral_survey_job_id', 'survey_change_report_id'] as $col) {
                    if (isset($anchor['keys'][$col])) {
                        $q->whereNull($col)->orWhere($col, '!=', $anchor['keys'][$col]);
                    }
                }
            })
            ->get(['beacon_id', 'its_number', 'file_number'])
            ->groupBy(fn ($c) => SurveyBeaconRegister::normalise($c->beacon_id));

        $surveyor = $this->norm($anchor['prefill']['surveyor_name'] ?? '');

        return $coords->map(function ($c) use ($issued, $elsewhere, $surveyor) {
            $code = SurveyBeaconRegister::normalise($c->beacon_id);
            $lines = $issued->get($code, collect());
            // A register line that names no surveyor (most of the book) cannot contradict the survey.
            $match = $lines->first(fn ($l) => $surveyor === '' || $this->norm($l->surveyor_name) === ''
                || $this->norm($l->surveyor_name) === $surveyor || $this->norm($l->surveyor_registration ?? '') === $surveyor);

            $status = $lines->isEmpty() ? self::UNREGISTERED : ($match ? self::REGISTERED : self::OTHER_SURVEYOR);

            return (object) [
                'beacon_id' => $c->beacon_id,
                'northing'  => $c->northing,
                'easting'   => $c->easting,
                'status'    => $status,
                'issue'     => $match ?? $lines->first(),
                'elsewhere' => $elsewhere->get($code, collect())->map(fn ($e) => trim($e->its_number . ' · ' . $e->file_number, ' ·'))->unique()->values(),
            ];
        });
    }

    /**
     * Beacon numbers read off the ground (Plot Confirmation, 2026-10-30), each against the
     * Beacon Register: [beacon, status, label, register_no, surveyor, issue_date, plan_no].
     * With no survey to compare surveyors against, an issued number is simply registered.
     *
     * @param array<int, string> $codes
     */
    public function forCodes(array $codes): array
    {
        $codes = collect($codes)->map(fn ($c) => SurveyBeaconRegister::normalise($c))->filter()->unique()->values();
        if ($codes->isEmpty()) {
            return [];
        }

        $issued = collect(DB::connection('sqlsrv')->table('survey_beacon_numbers as n')
            ->join('survey_beacon_register as r', 'r.id', '=', 'n.survey_beacon_register_id')
            ->whereNull('n.deleted_at')->whereNull('r.deleted_at')
            ->whereIn('n.beacon_no', $codes->all())
            ->get(['n.beacon_no', 'r.register_no', 'r.surveyor_name', 'r.issue_date', 'r.plan_no']))
            ->groupBy('beacon_no');

        return $codes->map(function ($code) use ($issued) {
            $line = $issued->get($code, collect())->first();
            $status = $line ? self::REGISTERED : self::UNREGISTERED;

            return [
                'beacon'      => $code,
                'status'      => $status,
                'label'       => self::LABELS[$status],
                'register_no' => $line->register_no ?? null,
                'surveyor'    => $line->surveyor_name ?? null,
                'issue_date'  => $line && $line->issue_date ? substr((string) $line->issue_date, 0, 10) : null,
                'plan_no'     => $line->plan_no ?? null,
            ];
        })->all();
    }

    /** Counts per status for a list of rows from forAnchor(). */
    public static function summary(Collection $rows): array
    {
        $out = array_fill_keys(array_keys(self::LABELS), 0);
        foreach ($rows as $r) $out[$r->status]++;

        return $out + ['total' => $rows->count(), 'reused' => $rows->filter(fn ($r) => $r->elsewhere->isNotEmpty())->count()];
    }

    private function norm(?string $name): string
    {
        return mb_strtolower(trim(preg_replace('/\s+/', ' ', (string) $name)));
    }
}
