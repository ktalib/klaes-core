<?php

namespace App\Models;

use App\Support\ParcelSizeSummary;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One property being merged, on a Master JSI.
 *
 * A merger takes two or more distinct properties; each selected property keeps its
 * own file number, plot number and measured size. The original references are
 * never rewritten — the merger records them, it does not change them.
 */
class MasterJsiMergerProperty extends Model
{
    protected $connection = 'sqlsrv';

    protected $table = 'master_jsi_merger_properties';

    protected $fillable = [
        'master_jsi_report_id',
        'property_file_number',
        'plot_number',
        'area_sqm',
        'unit',
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