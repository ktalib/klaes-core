<?php

namespace App\Models\Cadastral;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One beacon on a chart's boundary ring, in survey order.
 *
 * Columns match survey_lpkn_coordinates exactly so the two read alike.
 */
class CadastralChartCoordinate extends CadastralModel
{
    protected $table = 'cadastral_chart_coordinates';

    protected $casts = [
        'cadastral_chart_id' => 'integer',
        'northing'  => 'float',
        'easting'   => 'float',
        'elevation' => 'float',
        'distance'  => 'float',
    ];

    public function chart(): BelongsTo
    {
        return $this->belongsTo(CadastralChart::class, 'cadastral_chart_id');
    }

    /** True when this point can take part in an area computation. */
    public function isPlottable(): bool
    {
        return $this->northing !== null && $this->easting !== null;
    }
}
