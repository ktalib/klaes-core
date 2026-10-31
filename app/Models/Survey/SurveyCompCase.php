<?php

namespace App\Models\Survey;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SurveyCompCase extends SurveyModel
{
    use Concerns\HasImportOrigin;

    protected $table = 'survey_comp_cases';

    protected $casts = [
        'case_date' => 'date',
        'area_ha'   => 'decimal:4',
        'portion_size_ha' => 'decimal:4',
        'survey_project_surveyor_id' => 'integer',
        'portion_no'      => 'integer',
        'captured_from_paper' => 'boolean',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(SurveyProject::class, 'survey_project_id');
    }

    /** The surveyor allocation this case surveys one portion of. */
    public function allocation(): BelongsTo
    {
        return $this->belongsTo(SurveyProjectSurveyor::class, 'survey_project_surveyor_id');
    }

    public function beneficiaries(): HasMany
    {
        return $this->hasMany(SurveyBeneficiary::class, 'survey_comp_case_id');
    }

    public function trees(): HasMany
    {
        return $this->hasMany(SurveyCaseTree::class, 'survey_comp_case_id');
    }

    public function plots(): HasMany
    {
        return $this->hasMany(SurveyPlotAllocation::class, 'survey_comp_case_id');
    }

    /** Geo-tagged field photos (SCF app). */
    public function photos(): HasMany
    {
        return $this->hasMany(SurveyCasePhoto::class, 'survey_comp_case_id')->orderBy('id');
    }

    public function opRecords(): HasMany
    {
        return $this->hasMany(SurveyOpRecord::class, 'survey_comp_case_id');
    }

    /**
     * An existing record: imported from the spreadsheets or keyed in from a paper list. Its OPs
     * already exist, so none is generated in KLAES (CR 30102026 item 32, user 2026-10-31).
     */
    public function isExistingRecord(): bool
    {
        return $this->isImported() || (bool) $this->captured_from_paper;
    }

    /** Lands created in KLAES itself (neither imported nor captured from paper). */
    public function scopeNewInKlaes($q)
    {
        return $q->whereNull($this->qualifyColumn('import_ref'))
            ->where(fn ($w) => $w->whereNull($this->qualifyColumn('captured_from_paper'))->orWhere($this->qualifyColumn('captured_from_paper'), false));
    }

    public function isMonetary(): bool
    {
        return $this->scheme_type === SurveyProject::SCHEME_MONETARY;
    }

    /** Total cash value of economic trees on this case. */
    public function getTreeTotalAttribute(): float
    {
        return (float) $this->trees()->sum('line_total');
    }

    /**
     * 50:50 split. Plot rows win; otherwise fall back to the declared count.
     * Odd plot goes to Government (ceil), farmer takes the floor.
     */
    public function plotSplit(): array
    {
        $total = $this->plots()->count() ?: (int) ($this->num_plots ?? 0);

        return [
            'total'  => $total,
            'govt'   => (int) ceil($total / 2),
            'farmer' => (int) floor($total / 2),
        ];
    }
}
