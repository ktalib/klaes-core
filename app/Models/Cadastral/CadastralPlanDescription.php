<?php

namespace App\Models\Cadastral;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * The Plan and Description Unit's working record: area, pillars, fee and the
 * generated land description for one file.
 *
 * Area is stored ONCE, in square metres. Hectares, acres and plots are derived
 * here rather than stored — four representations of one number would drift.
 */
class CadastralPlanDescription extends CadastralModel
{
    protected $table = 'cadastral_plan_descriptions';

    protected $casts = [
        'cadastral_chart_id'       => 'integer',
        'cadastral_report_id'      => 'integer',
        'area_sqm'                 => 'float',
        'plot_size_sqm'            => 'float',
        'area_precision'           => 'integer',
        'description_generated_at' => 'datetime',
    ];

    public const LAND_USES = ['Residential', 'Commercial', 'Industrial', 'Agricultural', 'Institutional', 'Mixed'];

    public const ZONES = ['urban' => 'Urban', 'semi_urban' => 'Semi-urban', 'rural' => 'Rural'];

    public const COMPLEXITIES = ['simple' => 'Simple', 'standard' => 'Standard', 'complex' => 'Complex'];

    public function chart(): BelongsTo
    {
        return $this->belongsTo(CadastralChart::class, 'cadastral_chart_id');
    }

    public function report(): BelongsTo
    {
        return $this->belongsTo(CadastralReport::class, 'cadastral_report_id');
    }

    public function pillars(): HasMany
    {
        return $this->hasMany(CadastralPillar::class, 'cadastral_plan_description_id')
            ->orderBy('sort_order');
    }

    public function bills(): HasMany
    {
        return $this->hasMany(CadastralBill::class, 'cadastral_plan_description_id')
            ->orderByDesc('id');
    }

    /* ------------------------- derived area views ------------------------- */

    private function precision(): int
    {
        return $this->area_precision ?? (int) config('cadastral_module.area.precision', 2);
    }

    public function getAreaHaAttribute(): ?float
    {
        if ($this->area_sqm === null) return null;

        return round($this->area_sqm / (float) config('cadastral_module.area.sqm_per_hectare'), 4);
    }

    public function getAreaAcresAttribute(): ?float
    {
        if ($this->area_sqm === null) return null;

        return round($this->area_sqm / (float) config('cadastral_module.area.sqm_per_acre'), 4);
    }

    /**
     * Plot count against the standard plot. Uses the size snapshotted on this
     * row so an old bill reprints the same count if the standard later changes.
     */
    public function getAreaPlotsAttribute(): ?float
    {
        $plot = $this->plot_size_sqm ?: app(\App\Services\Cadastral\CadastralSettings::class)->plotSizeSqm();

        if ($this->area_sqm === null || $plot <= 0) return null;

        return round($this->area_sqm / $plot, 2);
    }

    public function getAreaSqmDisplayAttribute(): string
    {
        return $this->area_sqm === null ? '—' : number_format($this->area_sqm, $this->precision());
    }
}
