<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * File Request (FR) raised from web Quick Search to the SCB Monitor (the
 * mobile-only file searcher). The monitor runs the physical search and feeds
 * back Found / Not Found.
 */
class FileSearchRequest extends Model
{
    protected $connection = 'sqlsrv';
    protected $table      = 'file_search_requests';

    const STATUS_PENDING   = 'PENDING';
    const STATUS_SEARCHING = 'SEARCHING';
    const STATUS_FOUND     = 'FOUND';
    const STATUS_NOT_FOUND = 'NOT_FOUND';
    const STATUS_CLOSED    = 'CLOSED';

    const SOURCE_QUICK_SEARCH = 'QUICK_SEARCH';
    const SOURCE_DFR          = 'DFR';

    // Kind of "Not Found" the SCB Monitor reports when a physical search fails.
    const NOT_FOUND_MISSING = 'MISSING';   // file genuinely cannot be located
    const NOT_FOUND_PENDING = 'PENDING';   // search not yet concluded / expected to turn up

    protected $fillable = [
        'request_no',
        'file_number',
        'file_title',
        'requester_user_id',
        'assigned_monitor_id',
        'status',
        'resolved_status',
        'current_location',
        'feedback_note',
        'not_found_type',
        'responded_by',
        'responded_at',
        'source',
        'front_desk_acted_at',
        'front_desk_acted_by',
        'priority',
        'receiving_officer',
        'requester_office',
        'requester_department',
        'registry',
        'registry_code',
        'is_ofs',
        'ofs_rank',
        'request_purpose_id',
        'request_purpose_name',
        'expected_return_date',
    ];

    protected $casts = [
        'responded_at'        => 'datetime',
        'front_desk_acted_at' => 'datetime',
        'created_at'          => 'datetime',
        'updated_at'          => 'datetime',
        'is_ofs'              => 'boolean',
        'expected_return_date' => 'date',
    ];

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_user_id');
    }

    public function monitor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_monitor_id');
    }

    public function responder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responded_by');
    }

    /**
     * Generate a sequential request number: FR-2026-00001
     */
    public static function generateRequestNo(): string
    {
        $prefix = 'FR-' . now()->format('Y') . '-';

        $last = static::where('request_no', 'like', $prefix . '%')
            ->orderByDesc('id')
            ->value('request_no');

        $next = $last ? (int) substr($last, strlen($prefix)) + 1 : 1;

        return $prefix . str_pad($next, 5, '0', STR_PAD_LEFT);
    }

    /**
     * A file search request has no countdown. The return clock starts when the file is
     * logged OUT (file_tracker.created_at → file_tracker.deadline) — not when the request
     * was raised. Counting from created_at meant a request sitting unanswered burned days
     * off a window the requesting office had not yet received the file for.
     *
     * Always null, so nothing renders a "N days left" badge against a request.
     */
    public function getDaysUntilDeadlineAttribute()
    {
        return null;
    }

    /**
     * Timeline state for a request: 'pending' — the clock has not started. The Expected
     * Return Date is still shown as the date the requester asked for; it just does not
     * tick until the file is logged out and a FileTracker takes over the timeline.
     * Null when no date was captured at all, so the row simply shows "—".
     */
    public function getTimelineStatusAttribute()
    {
        return $this->expected_return_date ? 'pending' : null;
    }

    public function scopePending($q)   { return $q->where('status', self::STATUS_PENDING); }
    public function scopeOpen($q)      { return $q->whereIn('status', [self::STATUS_PENDING, self::STATUS_SEARCHING]); }

    /** Open (pending/searching) requests for a given file number. */
    public function scopeOpenForFile($q, ?string $fileNumber)
    {
        return $q->open()->where('file_number', $fileNumber);
    }

    /**
     * Active requests for a file: still in the live queue — i.e. open, or already
     * responded by SCB (FOUND/NOT_FOUND) but not yet acted on by the Front Desk and
     * not closed. Used to catch a re-send so the user updates the existing request
     * instead of raising a duplicate row.
     */
    public function scopeActiveForFile($q, ?string $fileNumber)
    {
        return $q->where('file_number', $fileNumber)
                 ->whereNull('front_desk_acted_at')
                 ->where('status', '!=', self::STATUS_CLOSED);
    }

    /**
     * Resolve a requester's seniority weight from config/file_request_priority.php.
     * Matches the given officer string (a name and/or rank) case-insensitively
     * against the configured rank keys; returns the configured default otherwise.
     */
    public static function priorityFor(?string $officer): int
    {
        $officer = trim((string) $officer);
        $default = (int) config('file_request_priority.default', 0);
        if ($officer === '') {
            return $default;
        }

        $haystack = mb_strtolower($officer);
        foreach ((array) config('file_request_priority.ranks', []) as $rank => $weight) {
            if (mb_strpos($haystack, mb_strtolower((string) $rank)) !== false) {
                return (int) $weight;
            }
        }

        return $default;
    }
}
