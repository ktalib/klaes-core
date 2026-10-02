<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LegalSearchToken extends Model
{
    use HasFactory;

    /**
     * How long a token stays valid for repeat use, counted from its first use
     * (used_at). A never-used token's window has not started yet, so it does
     * not expire while sitting unused.
     */
    public const VALIDITY_HOURS = 24;

    /**
     * Requesting bodies available as the "Reason for Payment" when a token is
     * flagged General — i.e. a statutory search, not one paid for by a client.
     */
    public const GENERAL_REASONS = ['Court', 'EFCC', 'Public Complaints', 'NDIC'];

    /**
     * A General search is not paid for by a client, so it carries a placeholder
     * receipt number and a fixed payment reason.
     */
    public const GENERAL_RECEIPT = 'System Generated';
    public const GENERAL_PAYMENT_REASON = 'Payment for Legal Search';

    protected $connection = 'sqlsrv';
    protected $table = 'legal_search_tokens';

    protected $fillable = [
        'token',
        'file_number',
        'applicant_name',
        'client_name',
        'property_location',
        'client_address',
        'payment_reason',
        'is_general',
        'general_body',
        'amount_paid',
        'receipt_number',
        'date_paid',
        'is_used',
        'usage_count',
        'used_at',
        'created_by'
    ];

    protected $casts = [
        'date_paid' => 'date',
        'is_used' => 'boolean',
        'is_general' => 'boolean',
        'usage_count' => 'integer',
        'used_at' => 'datetime',
    ];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Whether the token's 24-hour window (from its first use) has elapsed.
     * A never-used token is not expired — its window hasn't started yet.
     */
    public function getIsExpiredAttribute(): bool
    {
        if (!$this->used_at) {
            return false;
        }
        return now()->greaterThan($this->used_at->copy()->addHours(self::VALIDITY_HOURS));
    }

    /**
     * Whether the token can currently be spent: never used, or used but still
     * within its 24-hour window.
     */
    public function getIsAvailableAttribute(): bool
    {
        return !$this->is_expired;
    }

    /**
     * When the token's validity window closes; null while it has never been used
     * (the window hasn't started).
     */
    public function getExpiresAtAttribute(): ?\Illuminate\Support\Carbon
    {
        return $this->used_at ? $this->used_at->copy()->addHours(self::VALIDITY_HOURS) : null;
    }
}
