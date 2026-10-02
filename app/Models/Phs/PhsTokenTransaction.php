<?php

namespace App\Models\Phs;

use Illuminate\Database\Eloquent\Model;

class PhsTokenTransaction extends Model
{
    protected $connection = 'sqlsrv';
    protected $table = 'phs_token_transactions';

    protected $fillable = [
        'phs_institution_id',
        'phs_member_id',
        'type',
        'tokens',
        'balance_after',
        'package_name',
        'amount',
        'payment_method',
        'status',
        'reference_no',
        'notes',
        'approved_by',
        'approved_at',
        'expires_at',
        // Token topup
        'topup_bundle_count',
        'topup_unit_price',
        'payment_gateway_reference',
        'payment_confirmed_at',
        'credited_at',
        // Payment validation (bank transfers)
        'expected_amount',
        'paid_amount',
        'payment_variance',
        'payment_proof_path',
        'bank_reference',
        'payment_date',
        'validation_status',
        'validation_notes',
        'validated_by',
        'validated_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'expected_amount' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'payment_variance' => 'decimal:2',
        'payment_date' => 'date',
        'validated_at' => 'datetime',
        'approved_at' => 'datetime',
        'expires_at' => 'datetime',
        'topup_bundle_count' => 'integer',
        'topup_unit_price' => 'decimal:2',
        'payment_confirmed_at' => 'datetime',
        'credited_at' => 'datetime',
    ];

    public function institution()
    {
        return $this->belongsTo(PhsInstitution::class, 'phs_institution_id');
    }

    public function member()
    {
        return $this->belongsTo(PhsMember::class, 'phs_member_id');
    }

    /** Staff (main app user) who verified the payment. */
    public function validator()
    {
        return $this->belongsTo(\App\Models\User::class, 'validated_by');
    }

    public function isPaymentComplete(): bool
    {
        return $this->validation_status === 'verified'
            && (float) $this->paid_amount >= (float) $this->expected_amount;
    }

    public function hasPaymentShortfall(): bool
    {
        return $this->expected_amount !== null
            && (float) $this->paid_amount < (float) $this->expected_amount;
    }

    /**
     * Derive validation_status from expected vs paid amounts.
     * Returns: verified | incomplete | overpaid
     */
    public static function deriveValidationStatus($expected, $paid): string
    {
        $expected = (float) $expected;
        $paid = (float) $paid;
        if ($paid < $expected) {
            return 'incomplete';
        }
        if ($paid > $expected) {
            return 'overpaid';
        }
        return 'verified';
    }
}
