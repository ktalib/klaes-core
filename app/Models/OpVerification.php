<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OpVerification extends Model
{
    use HasFactory;

    protected $connection = 'sqlsrv';
    protected $table = 'op_verifications';
    protected $guarded = [];

    protected $casts = [
        'verified_at' => 'datetime',
    ];

    public const STATUSES = [
        'verified'   => 'OP Verified',
        'suspicious' => 'Suspicious',
    ];

    /**
     * Stable key for an OP row coming out of OpRecordSource, so repeat
     * verifications of the same OP resolve to the same history.
     */
    public static function recordKey(array $row): string
    {
        $propId  = trim((string) ($row['prop_id'] ?? ''));
        $capture = trim((string) ($row['source_capture_id'] ?? ''));
        $pra     = trim((string) ($row['pra_id'] ?? ''));
        $serial  = trim((string) ($row['op_serial_number'] ?? ''));

        if ($propId !== '')  return 'prop:' . $propId;
        if ($capture !== '') return 'cap:' . $capture;
        if ($pra !== '')     return 'pra:' . $pra;

        return 'serial:' . $serial;
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUSES[$this->status] ?? ucfirst((string) $this->status);
    }
}
