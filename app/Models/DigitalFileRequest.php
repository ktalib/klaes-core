<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DigitalFileRequest extends Model
{
    protected $connection = 'sqlsrv';
    protected $table      = 'digital_file_tracking_requests';

    // ── Status constants ──────────────────────────────────────────────────────
    const STATUS_PENDING    = 'Pending';
    const STATUS_APPROVED   = 'Approved';
    const STATUS_REJECTED   = 'Rejected';
    const STATUS_REDIRECTED = 'Redirected';
    const STATUS_IN_TRANSIT = 'In Transit';
    const STATUS_COMPLETED  = 'Completed';
    const STATUS_CANCELED   = 'Canceled';

    // ── Request type constants ────────────────────────────────────────────────
    const TYPE_PHYSICAL = 'Physical';
    const TYPE_DIGITAL  = 'Digital';

    protected $fillable = [
        'request_no',
        'request_type',
        'file_no',
        'file_title',
        // Which physical file was asked for, when the searched number is
        // registered in both file_indexings and duplicate_fileno. The number
        // itself cannot say — both records carry it.
        'selected_record_id',
        'selected_record_source',
        'requester_user_id',
        'sending_officer',
        'receiving_officer',
        'source_office_id',
        'source_office_name',
        'destination_office_id',
        'destination_office_name',
        'current_file_location',
        'current_file_holder',
        'is_redirected',
        'request_status',
        'remarks',
        'rejection_reason',
        'requested_at',
        'approved_at',
        'rejected_at',
        'approved_by',
        'rejected_by',
    ];

    protected $casts = [
        'is_redirected' => 'boolean',
        'requested_at'  => 'datetime',
        'approved_at'   => 'datetime',
        'rejected_at'   => 'datetime',
        'created_at'    => 'datetime',
        'updated_at'    => 'datetime',
    ];

    // ── Relationships ─────────────────────────────────────────────────────────

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_user_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function rejecter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }

    public function sourceOffice(): BelongsTo
    {
        return $this->belongsTo(Office::class, 'source_office_id');
    }

    public function destinationOffice(): BelongsTo
    {
        return $this->belongsTo(Office::class, 'destination_office_id');
    }

    public function digitalAccess()
    {
        return $this->hasOne(DigitalFileAccess::class, 'digital_request_id');
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /**
     * Generate a sequential request number: DFR-2026-00001
     */
    public static function generateRequestNo(): string
    {
        $year  = now()->format('Y');
        $prefix = "DFR-{$year}-";

        $last = static::where('request_no', 'like', $prefix . '%')
            ->orderByDesc('id')
            ->value('request_no');

        $next = $last
            ? (int) substr($last, strlen($prefix)) + 1
            : 1;

        return $prefix . str_pad($next, 5, '0', STR_PAD_LEFT);
    }

    /** Status badge colour for Tailwind */
    public function getStatusBadgeClassAttribute(): string
    {
        return match ($this->request_status) {
            self::STATUS_PENDING    => 'bg-yellow-100 text-yellow-800',
            self::STATUS_APPROVED   => 'bg-green-100 text-green-800',
            self::STATUS_REJECTED   => 'bg-red-100 text-red-800',
            self::STATUS_REDIRECTED => 'bg-blue-100 text-blue-800',
            self::STATUS_IN_TRANSIT => 'bg-indigo-100 text-indigo-800',
            self::STATUS_COMPLETED  => 'bg-gray-100 text-gray-700',
            self::STATUS_CANCELED   => 'bg-rose-100 text-rose-800',
            default                 => 'bg-gray-100 text-gray-600',
        };
    }

    // ── Scopes ────────────────────────────────────────────────────────────────

    /**
     * Restrict to KANGIS files by file-number format
     * (KN…, KNML, KNGP, MLKN, MNKL — covers New KANGIS + legacy KANGIS formats).
     */
    public function scopeKangisFiles($q)
    {
        return $q->where(function ($w) {
            $w->whereRaw("UPPER(LTRIM(file_no)) LIKE 'KN%'")
              ->orWhereRaw("UPPER(LTRIM(file_no)) LIKE 'MLKN%'")
              ->orWhereRaw("UPPER(LTRIM(file_no)) LIKE 'MNKL%'");
        });
    }

    public function scopePending($q)    { return $q->where('request_status', self::STATUS_PENDING); }
    public function scopeApproved($q)   { return $q->where('request_status', self::STATUS_APPROVED); }
    public function scopeRejected($q)   { return $q->where('request_status', self::STATUS_REJECTED); }
    public function scopeForUser($q, int $userId) { return $q->where('requester_user_id', $userId); }
}
