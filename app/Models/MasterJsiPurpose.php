<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One purpose selected on a Master JSI inspection.
 *
 * Purposes are stored individually (merger, extension, subdivision, separation,
 * change_of_purpose) so any combination stays possible — never as a joined label
 * like "Merger and Extension". A report with no purpose rows is a legacy sheet
 * whose single purpose is its parcel_update_type.
 */
class MasterJsiPurpose extends Model
{
    protected $connection = 'sqlsrv';

    protected $table = 'master_jsi_purposes';

    public const PURPOSES = [
        'merger'            => 'Merger',
        'extension'         => 'Extension',
        'subdivision'       => 'Subdivision',
        'separation'        => 'Separation',
        'change_of_purpose' => 'Change of Purpose',
    ];

    // Purpose slugs that share an inspection section (Separation prints the
    // Subdivision sheet, as the Ministry sample does).
    public const SECTION_MAP = [
        'separation' => 'subdivision',
    ];

    protected $fillable = [
        'master_jsi_report_id',
        'purpose',
        'sequence',
    ];

    protected $casts = [
        'sequence' => 'integer',
    ];

    public function report(): BelongsTo
    {
        return $this->belongsTo(MasterJsiReport::class, 'master_jsi_report_id');
    }

    public function label(): string
    {
        return self::PURPOSES[$this->purpose] ?? ucfirst(str_replace('_', ' ', (string) $this->purpose));
    }

    /** The section slug that renders it (separation and subdivision share one). */
    public function section(): string
    {
        return self::SECTION_MAP[$this->purpose] ?? $this->purpose;
    }
}