<?php

namespace App\Models\Laas;

use Illuminate\Database\Eloquent\Model;

/**
 * A registered assignment-type instrument naming someone other than the
 * file's portal holder. Staff approve it (the file moves to the new holder's
 * account) or dismiss it. See LaasTransferService.
 */
class LaasTransferCandidate extends Model
{
    protected $connection = 'sqlsrv';
    protected $table = 'laas_transfer_candidates';

    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_DISMISSED = 'dismissed';

    protected $fillable = [
        'file_number',
        'laas_application_id',
        'instrument_capture_id',
        'instrument_type',
        'current_holder',
        'transferee_name',
        'transferee_phone',
        'transferee_address',
        'registered_at',
        'status',
        'decision_note',
        'decided_by',
        'decided_at',
        'new_application_id',
    ];

    protected $casts = [
        'registered_at' => 'datetime',
        'decided_at'    => 'datetime',
    ];

    public function application()
    {
        return $this->belongsTo(LaasApplication::class, 'laas_application_id');
    }
}
