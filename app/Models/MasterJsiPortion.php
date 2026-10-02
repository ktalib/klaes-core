<?php

namespace App\Models;

use App\Support\ParcelSizeSummary;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One line of the S/N | PORTION | DIMENSION | MEASUREMENT table on a Master JSI.
 *
 * Which lines a report carries is decided by its parcel_update_type — an Extension
 * has Existing Title / Extension Portion / Recommended Site Portion, a Merger has
 * Portion A / Portion B / Recommended Merged Portion, and so on. The templates live
 * in App\Support\MasterJsiPortionTemplates; this row just holds what was measured.
 *
 * area_sqm is SQUARE METRES and is the authority. The sheets print "m²/ha", so the
 * hectare figure is derived here rather than stored — a second stored number is a
 * second thing to get wrong, and this codebase already carries hectare-shaped rows
 * in an older table because a form once labelled its total "Ha".
 */
class MasterJsiPortion extends Model
{
    protected $connection = 'sqlsrv';

    protected $table = 'master_jsi_portions';

    protected $fillable = [
        'master_jsi_report_id',
        'sn',
        'role',
        'label',
        'dimensions',
        'area_sqm',
        'land_use',
        'unit_count',
        'sequence',
    ];

    protected $casts = [
        'area_sqm'   => 'decimal:2',
        'sn'         => 'integer',
        'unit_count' => 'integer',
        'sequence'   => 'integer',
    ];

    public function report(): BelongsTo
    {
        return $this->belongsTo(MasterJsiReport::class, 'master_jsi_report_id');
    }

    /** "1,410 m²/0.141 ha" — the MEASUREMENT column exactly as the sheet prints it. */
    public function measurementText(): string
    {
        if ($this->area_sqm === null || (float) $this->area_sqm <= 0) {
            return '';
        }

        $m2 = (float) $this->area_sqm;

        return ParcelSizeSummary::number($m2) . ' m²/' . ParcelSizeSummary::hectares($m2) . ' ha';
    }
}
