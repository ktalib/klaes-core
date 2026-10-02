<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An applicant's identification submission for a public Online Legal Search,
 * together with the result of comparing the name they typed against the text
 * OCR read off their uploaded ID.
 *
 * SCOPE: `id_verification_status` says only whether those two names matched. It
 * is not a finding about the document's authenticity, and nothing in the system
 * may present it as one. See config/id_verification.php.
 */
class LegalSearchOnlineVerification extends Model
{
    // The Online Legal Search portal lives on the sqlsrv connection.
    protected $connection = 'sqlsrv';

    public const STATUS_PENDING  = 'pending';
    public const STATUS_VERIFIED = 'verified';
    public const STATUS_REVIEW   = 'review';
    public const STATUS_FAILED   = 'failed';

    public const STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_VERIFIED,
        self::STATUS_REVIEW,
        self::STATUS_FAILED,
    ];

    public const CUSTOMER_INDIVIDUAL = 'individual';
    public const CUSTOMER_LAWYER     = 'lawyer';

    protected $fillable = [
        'file_number',
        'requester_email',
        'session_token',
        'customer_type',
        'call_to_bar_number',
        'bar_number_status',
        'applicant_full_name',
        'applicant_phone',
        'applicant_address',
        'identification_type',
        'identification_type_other',
        'id_front_path',
        'id_back_path',
        'id_ocr_text',
        'id_name_match_score',
        'id_verification_status',
        'id_verified_at',
        'payment_id',
        'request_id',
        'ip_address',
    ];

    protected $casts = [
        'id_name_match_score' => 'float',
        'id_verified_at'      => 'datetime',
        'payment_id'          => 'integer',
        'request_id'          => 'integer',
    ];

    /*
     * Document paths are sensitive: a stray ->toJson() on this model must not
     * leak where the images live, nor the OCR transcript of someone's ID.
     */
    protected $hidden = [
        'id_front_path',
        'id_back_path',
        'id_ocr_text',
        'session_token',
    ];

    public function payment(): BelongsTo
    {
        return $this->belongsTo(LegalSearchOnlinePayment::class, 'payment_id');
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(LegalSearchOnlineRequest::class, 'request_id');
    }

    public function isLawyer(): bool
    {
        return $this->customer_type === self::CUSTOMER_LAWYER;
    }

    /** Human label for the customer type. */
    public function customerTypeLabel(): string
    {
        return (string) config(
            'id_verification.customer_types.' . $this->customer_type . '.label',
            'Individual'
        );
    }

    /** Only a verified applicant may open the checkout. */
    public function isVerified(): bool
    {
        return $this->id_verification_status === self::STATUS_VERIFIED;
    }

    /** Human label for the ID type, including the free-text "other" case. */
    public function identificationLabel(): string
    {
        if ($this->identification_type === 'other') {
            return trim((string) $this->identification_type_other) ?: 'Other government-issued ID';
        }

        return (string) config(
            'id_verification.types.' . $this->identification_type . '.label',
            $this->identification_type
        );
    }
}
