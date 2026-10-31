<?php

namespace App\Models\Survey;

use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A ground check of a plot (Plot Confirmation app, 2026-10-30): the surveyor stands
 * on an OP's plot, or a plot of a layout plan, reads the beacon numbers there, and
 * records whether the plot is as recorded (Confirmed) or not (Discrepancy, with what
 * is wrong). The beacons are checked against the Beacon Register when it is saved.
 */
class SurveyPlotCheck extends SurveyModel
{
    protected $table = 'survey_plot_checks';

    protected $casts = [
        'op_source_id'      => 'integer',
        'survey_lpkn_id'    => 'integer',
        'surveyors_list_id' => 'integer',
        'checked_on'        => 'date',
        'latitude'          => 'float',
        'longitude'         => 'float',
        'accuracy_m'        => 'float',
        'beacon_check'      => 'array',
    ];

    public const TARGET_OP     = 'op';
    public const TARGET_LAYOUT = 'layout';

    public const CONFIRMED   = 'Confirmed';
    public const DISCREPANCY = 'Discrepancy';
    public const RESULTS     = [self::CONFIRMED, self::DISCREPANCY];

    public const OTHER = 'Other';

    /** What can be wrong on the ground; exactly one Other, with a required specify. */
    public const DISCREPANCIES = [
        'Beacon missing',
        'Beacon disturbed or moved',
        'Plot encroached',
        'Plot position differs from the layout',
        'Plot occupied by another person',
        self::OTHER,
    ];

    public function photos(): HasMany
    {
        return $this->hasMany(SurveyPlotCheckPhoto::class, 'survey_plot_check_id')->orderBy('id');
    }

    public function getDiscrepancyLabelAttribute(): ?string
    {
        if ($this->result !== self::DISCREPANCY) return null;

        return $this->discrepancy === self::OTHER ? ($this->discrepancy_other ?: self::OTHER) : $this->discrepancy;
    }

    /** "OP 4147" or "LPKN-2026-001 plot 12". */
    public function getTargetLabelAttribute(): string
    {
        if ($this->target_type === self::TARGET_OP) {
            return 'OP ' . ($this->op_serial ?: '—') . ($this->plot_no ? ' · Plot ' . $this->plot_no : '');
        }

        return trim(($this->layout_no ?: 'Layout') . ($this->plot_no ? ' · Plot ' . $this->plot_no : ''));
    }
}
