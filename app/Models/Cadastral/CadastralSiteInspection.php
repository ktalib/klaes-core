<?php

namespace App\Models\Cadastral;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A field inspection recorded against a verification report.
 *
 * GPS is plain lat/long: KLAES mapping is Leaflet points and the database has no
 * geometry type. The parcel location comes from the parent report's address
 * group rather than being keyed again here.
 */
class CadastralSiteInspection extends CadastralModel
{
    protected $table = 'cadastral_site_inspections';

    protected $casts = [
        'cadastral_report_id' => 'integer',
        'inspected_on'   => 'date',
        'gps_latitude'   => 'float',
        'gps_longitude'  => 'float',
        'gps_accuracy_m' => 'float',
        'encroachment'   => 'boolean',
    ];

    public function report(): BelongsTo
    {
        return $this->belongsTo(CadastralReport::class, 'cadastral_report_id');
    }

    public function hasFix(): bool
    {
        return $this->gps_latitude !== null && $this->gps_longitude !== null;
    }
}
