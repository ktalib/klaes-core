<?php

namespace App\Models\Survey;

/**
 * The entries of an upload's Application Plan (user, 2026-11-17), saved from the pop-up before printing.
 * One row per survey plan upload; `fields` holds the form's entries by the keys of FIELDS.
 */
class SurveyApplicationPlan extends SurveyModel
{
    protected $table = 'survey_application_plans';

    protected $casts = ['fields' => 'array'];

    /** Form entry => label, in the order they appear on the printed form. */
    public const FIELDS = [
        'serial_no'           => 'Serial No.',
        'purpose'             => 'Purpose (For ...)',
        'applicant'           => 'Name of Applicant',
        'address'             => 'Address',
        'plot_no'             => 'Plot No.',
        'location'            => 'Location',
        'map_sheet'           => 'Map Sheet',
        'scale'               => 'Scale',
        'plan_no'             => 'Plan No.',
        'ms_no'               => 'MS No.',
        'reference'           => 'Reference',
        'ground_open'         => 'The ground is open',
        'layout_plan_no'      => 'Layout Plan No.',
        'government_item_no'  => 'Government Item No.',
        'cofo_no'             => 'Certificate of Occupancy No.',
        'application_plan_no' => 'Application Plan No.',
        'surveyed_by'         => 'Surveyed by',
        'drawn_by'            => 'Drawn by',
        'checked_by'          => 'Checked by',
        'amount_paid'         => 'Amount Paid',
        'receipt_no'          => 'Receipt No.',
        'passed_by'           => 'Passed by',
        'rank'                => 'Rank',
        'date'                => 'Date',
    ];

    /** The scales a plan can be drawn at; blank = the largest at which the parcel fits the frame. */
    public const SCALES = [250, 500, 1000, 1250, 2000, 2500, 5000, 10000];

    /** Purpose (For ...): the four land uses (user, 2026-11-17), or Other with the purpose typed. */
    public const PURPOSES = \App\Services\Survey\ResettlementCalculator::LAND_USES;

    public const OTHER = 'Other';

    /**
     * Plan No. choices: the TP numbers of the layout register, tp_lookups (user, 2026-11-17: "Plan No. is the same as
     * the TP No"), or Other with the number typed. Same list as the File Movement card's Layout No.
     */
    public static function planNoChoices(): array
    {
        return \Illuminate\Support\Facades\Cache::remember('survey.application_plan.tp_nos', 600, fn () => \Illuminate\Support\Facades\DB::connection('sqlsrv')
            ->table('tp_lookups')->whereNotNull('tp_no')->orderBy('tp_no')->pluck('tp_no')
            ->map(fn ($t) => trim((string) $t))->filter()->unique(fn ($t) => mb_strtoupper($t))->values()->all());
    }

    /** Surveyed by / Checked by / Passed by (user, 2026-11-18: dropdowns, with Other): the active Survey staff list, as Drawn by on the upload. */
    public const PEOPLE_FIELDS = ['surveyed_by', 'checked_by', 'passed_by'];

    public static function peopleChoices(): array
    {
        return SurveyorListEntry::query()->where('is_active', true)->orderBy('first_name')->orderBy('surname')->get()
            ->map(fn ($s) => trim((string) $s->full_name))->filter()->unique(fn ($n) => mb_strtolower($n))->values()->all();
    }

    /** A typed "Other" value that is really one of the choices (any case / spacing) becomes that choice. */
    public static function matchChoice(string $typed, array $choices): string
    {
        $key = mb_strtolower(preg_replace('/\s+/u', ' ', trim($typed)));
        foreach ($choices as $c) {
            if (mb_strtolower(preg_replace('/\s+/u', ' ', trim($c))) === $key) return $c;
        }

        return $typed;
    }

    /** Asked only when the ground is not open. */
    public const LIES_ON = ['layout_plan_no', 'government_item_no', 'cofo_no', 'application_plan_no'];

    public static function installed(): bool
    {
        static $has;

        return $has ??= \Illuminate\Support\Facades\Schema::connection('sqlsrv')->hasTable('survey_application_plans');
    }

    /** What KLAES already holds for the form, from the upload (development standard 5). */
    public static function fromUpload(SurveyPlanUpload $plan): array
    {
        return [
            'plot_no'   => (string) $plan->plot_no,
            'location'  => (string) ($plan->location ?: collect([$plan->district, $plan->lga])->filter()->implode(', ')),
            'map_sheet' => collect([$plan->map_sheet_metric, $plan->map_sheet_imperial])->filter()->implode(' / '),
            'drawn_by'  => (string) $plan->drawn_by,
        ];
    }

    /**
     * The form's entries for an upload: what was saved, with the upload's own details filling anything not yet saved.
     * A saved entry wins, even when the user cleared it on purpose.
     */
    public static function entriesFor(SurveyPlanUpload $plan): array
    {
        $saved = (array) ($plan->relationLoaded('applicationPlan') ? $plan->applicationPlan?->fields : (self::installed() ? $plan->applicationPlan()->first()?->fields : null));
        $own = self::fromUpload($plan);
        $out = [];
        foreach (array_keys(self::FIELDS) as $k) {
            $out[$k] = array_key_exists($k, $saved) ? (string) $saved[$k] : (string) ($own[$k] ?? '');
        }

        return $out;
    }
}
