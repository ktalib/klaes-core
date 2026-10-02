<?php

namespace App\Models;

use App\Support\ParcelSizeSummary;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One strip of land being added to the existing site, on a Master JSI.
 *
 * "Number of extensions" means the number of SEPARATE land portions being added —
 * not the number of years. Each portion is a repeatable row with its own number,
 * description and measurement.
 */
class MasterJsiExtensionPortion extends Model
{
    protected $connection = 'sqlsrv';

    protected $table = 'master_jsi_extension_portions';

    protected $fillable = [
        'master_jsi_report_id',
        'extension_number',
        'description',
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