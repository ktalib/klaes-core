<?php

namespace App\Models\InstrumentWorkflow;

use Illuminate\Database\Eloquent\Model;

/**
 * The TIN (BIR server) lookup for an application. The idempotency key makes a
 * retry return this row instead of registering a second taxpayer.
 *
 * ALAES obtains an ABSSIN Code here too and carries the applicant's outstanding
 * state liability onto the registration bill. Kano has no ABSSIN, so this is a
 * TIN lookup only and nothing tax-assessed is added to the bill.
 */
class TaxIdentityCheck extends Model
{
    protected $connection = 'sqlsrv';
    protected $table = 'tax_identity_checks';

    public const TIN_FOUND = 'FOUND';
    public const TIN_REGISTERED = 'REGISTERED';
    public const STATUS_FAILED = 'FAILED';

    protected $fillable = [
        'application_id',
        'idempotency_key',
        'request_hash',
        'tin_driver',
        'tin_status',
        'tin',
        'tin_message',
        'attempts',
        'last_error',
        'checked_by',
    ];

    protected $casts = [
        'attempts' => 'integer',
    ];

    public function isComplete(): bool
    {
        return (string) $this->tin !== '';
    }

    public function isDemo(): bool
    {
        return $this->tin_driver === 'demo';
    }

    public function application()
    {
        return $this->belongsTo(InstrumentApplication::class, 'application_id');
    }
}

