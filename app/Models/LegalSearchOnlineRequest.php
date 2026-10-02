<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A public Online Legal Search request awaiting Director / Deputy Director
 * approval. Created once a guest payment is verified; the report is only
 * emailed out after an approver signs off.
 */
class LegalSearchOnlineRequest extends Model
{
    // Lives alongside the rest of the Online Legal Search portal tables.
    protected $connection = 'sqlsrv';

    protected $table = 'legal_search_online_requests';

    public const STATUS_PENDING  = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'request_no',
        'payment_id',
        'reference',
        'tracking_id',
        'requester_email',
        'requester_name',
        'requester_phone',
        'file_number',
        'search_params',
        'purpose_id',
        'purpose',
        'ip_address',
        'status',
        'submitted_at',
        'reviewed_by',
        'reviewer_name',
        'reviewer_rank',
        'reviewer_signature_path',
        'signed_at',
        'reviewed_at',
        'review_note',
        'rejection_reason',
        'emailed_at',
        'email_error',
    ];

    protected $casts = [
        'search_params' => 'array',
        'submitted_at'  => 'datetime',
        'reviewed_at'   => 'datetime',
        'signed_at'     => 'datetime',
        'emailed_at'    => 'datetime',
    ];

    public function payment(): BelongsTo
    {
        return $this->belongsTo(LegalSearchOnlinePayment::class, 'payment_id');
    }

    /**
     * Every request opened by the same payment, primary file first — i.e. the
     * whole multi-file basket this request belongs to, itself included.
     *
     * A payment covering N files opens N of these rows (see
     * LegalSearchApprovalService::openRequest — idempotent per payment+file, not
     * per payment), each with its own report and its own approve/reject
     * lifecycle. This is what lets an email or an admin screen say "file 2 of 3"
     * instead of reading as an unexplained duplicate.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, self>
     */
    public function basketSiblings(): \Illuminate\Database\Eloquent\Collection
    {
        if (empty($this->payment_id)) {
            return new \Illuminate\Database\Eloquent\Collection([$this]);
        }

        return static::where('payment_id', $this->payment_id)->orderBy('id')->get();
    }

    /** How many files the payment behind this request covers. */
    public function basketSize(): int
    {
        return $this->basketSiblings()->count();
    }

    /** This request's position in its basket (1-based), for "file 2 of 3" wording. */
    public function basketPosition(): int
    {
        $position = $this->basketSiblings()->search(fn ($r) => $r->id === $this->id);

        return $position === false ? 1 : $position + 1;
    }

    public function searchPurpose(): BelongsTo
    {
        return $this->belongsTo(OnlineLsSearchPurpose::class, 'purpose_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    /**
     * The identification behind this request.
     *
     * Resolved through the PAYMENT, not through request_id: a multi-file payment
     * opens several requests but carries one identification, and only the first
     * request gets the direct back-link. Going via the payment means every file in
     * the basket resolves to the same, correct record.
     */
    public function verification(): ?LegalSearchOnlineVerification
    {
        if (empty($this->payment_id)) {
            return null;
        }

        return LegalSearchOnlineVerification::where('payment_id', $this->payment_id)->first();
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    public function isRejected(): bool
    {
        return $this->status === self::STATUS_REJECTED;
    }

    /**
     * Human-facing request number (LSR-0001), derived from the row id so it is
     * unique and sequential. Mirrors how payments get their USER-0001 id.
     */
    public function assignRequestNo(): string
    {
        if (empty($this->request_no)) {
            $this->request_no = 'LSR-' . str_pad((string) $this->id, 4, '0', STR_PAD_LEFT);
            $this->save();
        }

        return $this->request_no;
    }
}
