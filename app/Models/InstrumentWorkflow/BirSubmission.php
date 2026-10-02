<?php

namespace App\Models\InstrumentWorkflow;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * One version of a captured instrument sent to BIR. The snapshot is frozen at
 * submission and is what the BIR officer reviews; resending after a decline is
 * a new row with the next version, so every decision stays on record.
 */
class BirSubmission extends Model
{
    protected $connection = 'sqlsrv';
    protected $table = 'bir_submissions';

    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_DECLINED = 'declined';

    protected $fillable = [
        'instrument_capture_id',
        'application_id',
        'version',
        'driver',
        'snapshot',
        'snapshot_hash',
        'status',
        'submitted_by',
        'submitted_by_name',
        'submitted_at',
        'decided_by',
        'decided_by_name',
        'decided_at',
        'decision_note',
        'decline_reason',
        'bir_reference',
    ];

    protected $casts = [
        'snapshot' => 'array',
        'version' => 'integer',
        'submitted_at' => 'datetime',
        'decided_at' => 'datetime',
    ];

    public function application()
    {
        return $this->belongsTo(InstrumentApplication::class, 'application_id');
    }

    public function capture(): ?object
    {
        return DB::connection('sqlsrv')->table('instrument_capture')->where('id', $this->instrument_capture_id)->first();
    }
}

