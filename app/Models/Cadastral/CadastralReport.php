<?php

namespace App\Models\Cadastral;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A cadastral report — verification, customary or statutory.
 *
 * The three differ only in their stage chain: verification carries the extra
 * Field Inspection step, the other two do not. The chain lives in
 * config('cadastral_module.stage_chains') and is seeded into
 * cadastral_report_steps at creation, so a chain change never rewrites the
 * history of reports already in flight.
 */
class CadastralReport extends CadastralModel
{
    protected $table = 'cadastral_reports';

    protected $casts = [
        'cadastral_file_receipt_id' => 'integer',
        'cadastral_chart_id'        => 'integer',
        'cadastral_survey_job_id'   => 'integer',
        'due_date'      => 'date',
        'dispatched_at' => 'datetime',
        'current_step'  => 'integer',
        'assigned_user_id' => 'integer',
        // Report on Application (migration 2026_10_02_100000). Casting a column
        // the table does not have yet is harmless: the cast only applies when
        // the value is present.
        'area_applied_ha' => 'float',
    ];

    /**
     * The Report on Application questionnaire columns, in form order.
     * Added by 2026_10_02_100000; see applicationInstalled().
     */
    public const APPLICATION_COLUMNS = [
        'govt_item_no', 'sltr_no', 'sit_no',
        'q1_plan_sufficient',
        'q2_ground_open', 'q2_overlapping_title',
        'q3_beaconed', 'q3_tracing_no', 'q3_deposition_plan_no',
        'q3_unapproved_town_plan_no', 'q3_layout_no', 'q3_separate_survey',
        'q4_town_plan', 'q4_town_plan_no', 'q4_shape_agrees', 'q4_purpose', 'q4_area_for_purpose',
        'q5_previous_title', 'q5_details',
        'q6_railway', 'q7_trunk_road',
        'area_applied_ha',
    ];

    /** The seven headline questions; all must be answered before the Report step completes. */
    public const APPLICATION_QUESTIONS = [
        'q1_plan_sufficient' => "1. Is the applicant's Plan sufficient to identify the Plot?",
        'q2_ground_open'     => '2. Is the ground open correctly?',
        'q3_beaconed'        => '3. Is the Plot completely beaconed?',
        'q4_town_plan'       => '4. Does it lie on an area covered by a Town Plan?',
        'q5_previous_title'  => '5. Has the Plot been previously held or applied for under statutory title?',
        'q6_railway'         => '6. Does a Railway/Siding run alongside/through the Plot?',
        'q7_trunk_road'      => '7. Is the Plot alongside a Federal/State Trunk Road?',
    ];

    /** Q4(c). Anything else an officer types is "other purpose". */
    public const APPLICATION_PURPOSES = ['Residential', 'Commercial', 'Industrial', 'Agricultural'];

    /** The form prints "1 hectares = 2.47 acres"; the print uses the same factor. */
    public const ACRES_PER_HECTARE_AS_PRINTED = 2.47;

    /** hasColumn costs a round trip; one answer per request (per process). */
    private static ?bool $applicationInstalled = null;

    public const TYPE_VERIFICATION = 'verification';
    public const TYPE_CUSTOMARY    = 'customary';
    public const TYPE_STATUTORY    = 'statutory';

    public const TYPES = [
        self::TYPE_VERIFICATION => 'Verification',
        self::TYPE_CUSTOMARY    => 'Customary',
        self::TYPE_STATUTORY    => 'Statutory',
    ];

    public const STATUSES = ['Draft', 'In Progress', 'Checked', 'Approved', 'Dispatched', 'Returned', 'Rejected'];

    public function steps(): HasMany
    {
        return $this->hasMany(CadastralReportStep::class, 'cadastral_report_id')
            ->orderBy('step_no');
    }

    public function inspections(): HasMany
    {
        return $this->hasMany(CadastralSiteInspection::class, 'cadastral_report_id');
    }

    public function receipt(): BelongsTo
    {
        return $this->belongsTo(CadastralFileReceipt::class, 'cadastral_file_receipt_id');
    }

    public function chart(): BelongsTo
    {
        return $this->belongsTo(CadastralChart::class, 'cadastral_chart_id');
    }

    public function surveyJob(): BelongsTo
    {
        return $this->belongsTo(CadastralSurveyJob::class, 'cadastral_survey_job_id');
    }

    public function planDescriptions(): HasMany
    {
        return $this->hasMany(CadastralPlanDescription::class, 'cadastral_report_id');
    }

    /* ---------------------- Report on Application (§3a) ---------------------- */

    /**
     * Whether the questionnaire columns exist yet.
     *
     * Every column is checked, not one sentinel, so a part-applied migration
     * reads as not installed rather than failing on the first missing column.
     */
    public static function applicationInstalled(): bool
    {
        if (self::$applicationInstalled !== null) {
            return self::$applicationInstalled;
        }

        $have = array_map('strtolower', \Illuminate\Support\Facades\Schema::connection('sqlsrv')
            ->getColumnListing('cadastral_reports'));

        return self::$applicationInstalled =
            array_diff(array_map('strtolower', self::APPLICATION_COLUMNS), $have) === [];
    }

    /** Forget the cached answer — for a script that adds the columns mid-run. */
    public static function refreshApplicationInstalled(): void
    {
        self::$applicationInstalled = null;
    }

    /** A questionnaire value, or null before the migration (the attribute is absent). */
    public function applicationValue(string $column): mixed
    {
        return $this->attributes[$column] ?? null;
    }

    /** The headline questions not yet answered. */
    public function unansweredApplicationQuestions(): array
    {
        return array_values(array_filter(
            array_keys(self::APPLICATION_QUESTIONS),
            fn ($c) => ! in_array($this->applicationValue($c), ['Yes', 'No'], true)
        ));
    }

    /** The source registry the file arrived from, when the report came off a receipt. */
    private function sourceRegistry(): ?string
    {
        $receipt = $this->relationLoaded('receipt') ? $this->receipt : $this->receipt()->first();

        return $receipt?->source_registry ? strtoupper(trim($receipt->source_registry)) : null;
    }

    /**
     * Which line of the form the report's own file number belongs on:
     * 'sltr', 'sit' or 'lkn'. The receipt's source registry decides; without a
     * receipt the number's prefix does; otherwise it is a Land R. of O. number.
     */
    private function fileNumberLine(): string
    {
        $source = $this->sourceRegistry();

        if ($source === 'SLTR') return 'sltr';
        if ($source === 'ST')   return 'sit';
        if ($source !== null)   return 'lkn';

        $n = strtoupper(ltrim((string) $this->file_number));

        if (str_starts_with($n, 'SLTR')) return 'sltr';
        if (str_starts_with($n, 'SIT') || str_starts_with($n, 'ST-') || str_starts_with($n, 'ST/')) return 'sit';

        return 'lkn';
    }

    /** "LKN/123" on a form that already prints "LKN/" would read LKN/LKN/123. */
    private static function withoutPrefix(?string $value, string $prefix): ?string
    {
        $value = trim((string) $value);
        if ($value === '') return null;

        return preg_replace('#^' . preg_quote($prefix, '#') . '\s*[/\-]?\s*#i', '', $value) ?: $value;
    }

    /**
     * The four reference blanks at the head of the form, each without the
     * prefix the form already prints.
     *
     * @return array{lkn: ?string, sltr: ?string, gkn: ?string, sit: ?string}
     */
    public function applicationRefs(): array
    {
        $line = $this->fileNumberLine();

        return [
            'lkn'  => $line === 'lkn' ? self::withoutPrefix($this->file_number, 'LKN') : null,
            'sltr' => self::withoutPrefix($this->applicationValue('sltr_no') ?? ($line === 'sltr' ? $this->file_number : null), 'SLTR'),
            'gkn'  => self::withoutPrefix($this->applicationValue('govt_item_no'), 'GKN'),
            'sit'  => self::withoutPrefix($this->applicationValue('sit_no') ?? ($line === 'sit' ? $this->file_number : null), 'SIT'),
        ];
    }

    /**
     * Area applied for, in hectares, and where it came from.
     *
     * The officer's confirmed figure when there is one; otherwise the linked
     * plan description's area, then the chart's stored area, then the area
     * enclosed by the chart's beacon coordinates -- each through AreaCalculator,
     * so the conversion is the module's one conversion.
     *
     * @return array{ha: ?float, source: ?string}
     */
    public function appliedArea(): array
    {
        $stored = $this->applicationValue('area_applied_ha');
        if ($stored !== null && $stored !== '') {
            return ['ha' => round((float) $stored, 4), 'source' => 'entered'];
        }

        $calc = app(\App\Services\Cadastral\AreaCalculator::class);

        $pd = $this->planDescriptions()->whereNotNull('area_sqm')->orderByDesc('id')->first();
        if ($pd) {
            return ['ha' => $calc->convert((float) $pd->area_sqm)['hectares'], 'source' => 'plan description ' . $pd->pd_ref];
        }

        $chart = $this->relationLoaded('chart') ? $this->chart : $this->chart()->first();
        if ($chart) {
            if ($chart->area_sqm !== null) {
                return ['ha' => $calc->convert((float) $chart->area_sqm)['hectares'], 'source' => 'chart ' . $chart->chart_ref];
            }

            $sqm = $calc->areaSqmForChart($chart);
            if ($sqm !== null) {
                return ['ha' => $calc->convert($sqm)['hectares'], 'source' => 'chart ' . $chart->chart_ref . ' coordinates'];
            }
        }

        return ['ha' => null, 'source' => null];
    }

    public function getTypeLabelAttribute(): string
    {
        return self::TYPES[$this->report_type] ?? ucfirst((string) $this->report_type);
    }

    /** How many steps this report's chain has, from its own seeded rows. */
    public function totalSteps(): int
    {
        return $this->relationLoaded('steps')
            ? $this->steps->count()
            : $this->steps()->count();
    }

    public function isFinished(): bool
    {
        return in_array($this->status, ['Dispatched', 'Rejected'], true);
    }

    public function getStatusBadgeAttribute(): string
    {
        return match ($this->status) {
            'Approved', 'Dispatched' => 'active',
            'Checked'                => 'completed',
            'Rejected'               => 'rejected',
            'Returned'               => 'review',
            'In Progress'            => 'review',
            default                  => 'pending',
        };
    }
}
