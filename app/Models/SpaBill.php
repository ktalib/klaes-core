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
     * SPAS-BILL-YYYY-### — the next free bill reference.
     *
     * Lives on the model because both paths that raise a bill need it: the
     * automatic one (SpaBillingService) and the hand-entered one
     * (SpecialAssignmentController::storeBill). Two copies of the numbering
     * could disagree about the next number and issue the same reference twice.
     *
     * Bills raised before 2026-08-18 carry the older "SPA-BILL" prefix and are
     * counted too, so the sequence continues. See GeneratesSpaReference.
     */
    public static function nextReference(): string
    {
        $year = now()->year;

        $issued = static::where('reference_id', 'like', static::spaPrefixPattern('-BILL-'.$year.'-%'))
            ->pluck('reference_id');

        return 'SPAS-BILL-'.$year.'-'.str_pad((string) static::nextSpaSequence($issued), 3, '0', STR_PAD_LEFT);
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
