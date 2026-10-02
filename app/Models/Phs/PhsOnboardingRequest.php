<?php

namespace App\Models\Phs;

use Illuminate\Database\Eloquent\Model;

class PhsOnboardingRequest extends Model
{
    protected $connection = 'sqlsrv';

    const STATUS_PENDING             = 'pending';
    const STATUS_DOCUMENTS_APPROVED  = 'documents_approved';
    const STATUS_AWAITING_SLA        = 'awaiting_sla';
    const STATUS_PAYMENT_PENDING     = 'payment_pending';
    const STATUS_PAYMENT_RECEIVED    = 'payment_received';
    const STATUS_SLA_UPLOADED        = 'sla_uploaded';
    const STATUS_SLA_APPROVED        = 'sla_approved';
    const STATUS_APPROVED            = 'approved';
    const STATUS_REJECTED            = 'rejected';
    const STATUS_ACTIVATED           = 'activated';

    const PAYMENT_NOT_PAID = 'not_paid';
    const PAYMENT_INCOMPLETE = 'incomplete';
    const PAYMENT_COMPLETED = 'completed';
    const PAYMENT_OVERPAID = 'overpaid';

    protected $fillable = [
        'organization_name',
        'organization_type',
        'contact_name',
        'contact_email',
        'phone',
        'address',
        'department',
        'job_title',
        'initial_token_package',
        'additional_notes',
        'status',
        'payment_reference',
        'payment_amount',
        'payment_received_at',
        'approved_at',
        'approved_by',
        'rejection_reason',
        'activation_token',
        'activation_token_expires_at',
        'created_phs_institution_id',
        'invoice_number',
        'invoice_generated_at',
        'invoice_sent_at',
        'invoice_pdf_path',
        'cac_registration_number',
        'cac_document_path',
        'additional_documents',
        'payment_status',
        'expected_amount',
        'verified_amount',
        'outstanding_amount',
        'payment_verified_at',
        'payment_verified_by',
        'payment_verification_notes',
        'request_letter_path',
        'lsa_token',
        'lsa_signed_document_path',
        'lsa_signed_at',
        'payment_token',
        'legal_approved_at',
        'legal_approved_by',
        'legal_rejection_reason',
        'legal_sla_approved_at',
        'legal_sla_approved_by',
        'paystack_reference',
        'paystack_amount',
    ];

    protected $casts = [
        'payment_received_at' => 'datetime',
        'approved_at' => 'datetime',
        'activation_token_expires_at' => 'datetime',
        'invoice_generated_at' => 'datetime',
        'invoice_sent_at' => 'datetime',
        'additional_documents' => 'array',
        'expected_amount' => 'decimal:2',
        'verified_amount' => 'decimal:2',
        'outstanding_amount' => 'decimal:2',
        'payment_verified_at' => 'datetime',
        'lsa_signed_at' => 'datetime',
        'legal_approved_at' => 'datetime',
        'legal_sla_approved_at' => 'datetime',
        'paystack_amount' => 'decimal:2',
    ];

    /** Staff (main app user) who verified the payment. */
    public function paymentVerifier()
    {
        return $this->belongsTo(\App\Models\User::class, 'payment_verified_by');
    }

    /**
     * Derive a payment status from expected vs verified amounts.
     * not_paid | incomplete | completed | overpaid
     */
    public static function derivePaymentStatus($expected, $verified): string
    {
        $expected = (float) $expected;
        $verified = (float) $verified;
        if ($verified <= 0) {
            return self::PAYMENT_NOT_PAID;
        }
        if ($verified < $expected) {
            return self::PAYMENT_INCOMPLETE;
        }
        if ($verified > $expected) {
            return self::PAYMENT_OVERPAID;
        }
        return self::PAYMENT_COMPLETED;
    }

    public function isPaymentComplete(): bool
    {
        return in_array($this->payment_status, [self::PAYMENT_COMPLETED, self::PAYMENT_OVERPAID], true);
    }

    /**
     * Build a unique invoice number: PHS-INV-YYYYMMDD-XXXX (per-day sequence).
     */
    public function generateInvoiceNumber(): string
    {
        $date = now()->format('Ymd');

        $last = static::whereNotNull('invoice_number')
            ->where('invoice_number', 'like', 'PHS-INV-' . $date . '-%')
            ->orderByDesc('invoice_number')
            ->first();

        $sequence = $last ? ((int) substr($last->invoice_number, -4)) + 1 : 1;

        return 'PHS-INV-' . $date . '-' . str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
    }

    public function institution()
    {
        return $this->belongsTo(PhsInstitution::class, 'created_phs_institution_id');
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    public function canRegister(): bool
    {
        if ($this->status !== self::STATUS_APPROVED && $this->status !== self::STATUS_ACTIVATED) {
            return false;
        }

        if (!$this->activation_token || !$this->activation_token_expires_at) {
            return false;
        }

        return now()->isBefore($this->activation_token_expires_at);
    }

    public function generateActivationToken(): void
    {
        $this->activation_token = \Str::random(64);
        $this->activation_token_expires_at = now()->addDays(7);
        $this->save();
    }

    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }
}
