<?php

namespace App\Models;

use App\Support\ParcelSizeSummary;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One current-to-proposed land use change, on a Master JSI.
 *
 * Both values come from the configured land_uses register — this row holds what was
 * picked, it does not invent a list of its own. One inspection may carry several
 * changes, each preserved as its own row.
 */
class MasterJsiPurposeChange extends Model
{
    protected $connection = 'sqlsrv';

    protected $table = 'master_jsi_purpose_changes';

    protected $fillable = [
        'master_jsi_report_id',
        'current_land_use',
        'proposed_land_use',
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