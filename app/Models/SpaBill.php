<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SpaBill extends Model
{
    use \App\Models\Concerns\GeneratesSpaReference;

    protected $connection = 'sqlsrv';
    protected $table      = 'spa_bills';

    protected $fillable = [
        'spa_application_id', 'bill_type', 'description',
        'amount', 'reference_id', 'due_date', 'status', 'created_by',
        // How the bill came to exist: 'contravention' for one raised
        // automatically, null for a hand-entered legacy bill. Omitting it here
        // let mass assignment drop it silently, which broke the
        // one-bill-per-application guard — the lookup filters on this value, so
        // a null meant every save raised another bill.
        'source',
    ];

    protected $casts = [
        'amount'   => 'decimal:2',
        'due_date' => 'date',
    ];

    public function application()
    {
        return $this->belongsTo(SpaApplication::class, 'spa_application_id');
    }

    public function payments()
    {
        return $this->hasMany(SpaPayment::class, 'spa_bill_id');
    }

    /**
     * The next free bill reference: a Bill Master Bill Ref ID (BILL-YYYY-######).
     *
     * Lives on the model because both paths that raise a bill need it: the
     * automatic one (SpaBillingService) and the hand-entered one
     * (SpecialAssignmentController::storeBill). Two copies of the numbering
     * could disagree about the next number and issue the same reference twice.
     *
     * Until 2026-10-19 this was SPAS-BILL-YYYY-### (SPA-BILL- before 2026-08-18);
     * those references stay on the bills that carry them.
     */
    public static function nextReference(): string
    {
        // KLAES REV-M (2026-10-19): Bill Master issues every bill's reference, so a
        // new SPAS bill carries a BILL- Bill Ref ID and is mirrored into Bill Master
        // (App\Services\Billing\Modules\SpasBillMirror). SPAS-BILL- / SPA-BILL- refs
        // already issued keep their numbers until the approved consolidation.
        return \App\Services\Billing\BillRef::next(\App\Services\Billing\BillRef::SYSTEM);
    }

    /** What this bill was composed of, as at the moment it was raised. */
    public function lines()
    {
        return $this->hasMany(SpaBillLine::class, 'spa_bill_id');
    }

    public function getTotalPaidAttribute(): float
    {
        return (float) $this->payments()->sum('amount_paid');
    }

    public function getBalanceAttribute(): float
    {
        return (float) $this->amount - $this->total_paid;
    }
}
