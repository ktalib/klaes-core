<?php

namespace App\Models;

use App\Support\ParcelSizeSummary;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One proposed plot of a subdivision, on a Master JSI.
 *
 * The form draws one row per proposed plot once the officer says how many plots
 * come out of the site. Rows carry a plot number when available, a measured size
 * and optional remarks; totals and the unallocated remainder are derived at read.
 */
class MasterJsiSubdivisionPlot extends Model
{
    protected $connection = 'sqlsrv';

    protected $table = 'master_jsi_subdivision_plots';

    protected $fillable = [
        'master_jsi_report_id',
        'plot_number',
        'area_sqm',
        'unit',
        'remarks',
        'sequence',
    ];

    protected $casts = [
        'area_sqm' => 'decimal:2',
        'sequence' => 'integer',
    ];

    public function report(): BelongsTo
    {
        return $this->belongsTo(MasterJsiReport::class, 'master_jsi_report_id');
    }

    public function areaText(): string
    {
        if ($this->area_sqm === null || (float) $this->area_sqm <= 0) {
            return '';
        }

        return ParcelSizeSummary::number((float) $this->area_sqm) . ' m²';
    }
}