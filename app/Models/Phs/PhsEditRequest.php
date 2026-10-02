<?php

namespace App\Models\Phs;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A member's request to have a search result corrected, and the authorisation
 * record for the single free re-run that follows.
 *
 * Lifecycle:
 *   EDIT_REQUESTED  member reported the result as wrong; sits in the PHS-P
 *                   Admin queue
 *   READY_FOR_RERUN admin corrected the records and returned it; this is the
 *                   ONLY status that authorises a search without a token
 *   COMPLETED       the free re-run has been taken (rerun_search_log_id set);
 *                   the authorisation is spent and cannot be reused
 *   DECLINED        admin found nothing to correct; no free re-run is granted
 *
 * @see \App\Services\Phs\PhsEditRequestService for the guarded transitions.
 */
class PhsEditRequest extends Model
{
    protected $connection = 'sqlsrv';
    protected $table = 'phs_edit_requests';

    public const STATUS_EDIT_REQUESTED  = 'edit_requested';
    public const STATUS_READY_FOR_RERUN = 'ready_for_rerun';
    public const STATUS_COMPLETED       = 'completed';
    public const STATUS_DECLINED        = 'declined';

    /** Human labels for the workflow states, as shown to members and admins. */
    public const STATUS_LABELS = [
        self::STATUS_EDIT_REQUESTED  => 'Edit Requested',
        self::STATUS_READY_FOR_RERUN => 'Ready for Re-run',
        self::STATUS_COMPLETED       => 'Completed',
        self::STATUS_DECLINED        => 'Declined',
    ];

    /**
     * Why the member says the result is wrong. Mirrors PhsFeedback::CATEGORIES
     * so the two queues read the same way, plus the "too much" case the feedback
     * list has no word for.
     */
    public const REASONS = [
        'incomplete_transaction' => 'Incomplete — transactions are missing',
        'wrong_transaction'      => 'Wrong / incorrect transaction shown',
        'unrelated_records'      => 'Contains unrelated records from another file',
        'missing_record'         => 'A record is missing entirely',
        'other'                  => 'Other',
    ];

    protected $fillable = [
        'phs_institution_id',
        'phs_member_id',
        'requester_name',
        'requester_email',
        'search_log_id',
        'reference_no',
        'file_number',
        'reason_category',
        'reason',
        'original_result',
        'status',
        'requested_at',
        'reviewed_by',
        'reviewer_name',
        'admin_response',
        'corrected_at',
        'rerun_search_log_id',
        'rerun_at',
        'rerun_by',
        'ip_address',
    ];

    protected $casts = [
        'requested_at' => 'datetime',
        'corrected_at' => 'datetime',
        'rerun_at'     => 'datetime',
    ];

    public function institution(): BelongsTo
    {
        return $this->belongsTo(PhsInstitution::class, 'phs_institution_id');
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(PhsMember::class, 'phs_member_id');
    }

    public function searchLog(): BelongsTo
    {
        return $this->belongsTo(PhsSearchLog::class, 'search_log_id');
    }

    public function statusLabel(): string
    {
        return self::STATUS_LABELS[$this->status] ?? ucwords(str_replace('_', ' ', (string) $this->status));
    }

    public function reasonLabel(): string
    {
        return self::REASONS[$this->reason_category]
            ?? ucwords(str_replace('_', ' ', (string) $this->reason_category));
    }

    /**
     * The report the member originally saw, decoded.
     *
     * Stored as a JSON string in nvarchar(max) rather than a cast column, to
     * match how the other PHS tables hold JSON. Returns [] rather than null on
     * malformed data — a snapshot that cannot be read must not break the queue.
     */
    public function originalResult(): array
    {
        $raw = trim((string) $this->original_result);
        if ($raw === '') {
            return [];
        }

        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Whether this request currently authorises a search without a token.
     *
     * Deliberately strict, and the ONLY place that answers this question: the
     * status must be READY_FOR_RERUN and the authorisation must not already have
     * been spent. Callers must not re-derive this from the status alone.
     */
    public function authorisesFreeRerun(): bool
    {
        return $this->status === self::STATUS_READY_FOR_RERUN
            && $this->rerun_search_log_id === null;
    }

    public function scopeOpen($query)
    {
        return $query->whereIn('status', [self::STATUS_EDIT_REQUESTED, self::STATUS_READY_FOR_RERUN]);
    }

    public function scopeAwaitingAdmin($query)
    {
        return $query->where('status', self::STATUS_EDIT_REQUESTED);
    }
}
