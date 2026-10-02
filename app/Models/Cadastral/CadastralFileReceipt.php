<?php

namespace App\Models\Cadastral;

use App\Models\CadastralShadowFile;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A file arriving at the Cadastral registry from Land, SLTR, ST, DCIV or KANGIS.
 *
 * The cadastral copy keeps the SAME file number as the source file, so the same
 * number legitimately appears on several receipts — one per time the file comes
 * in. file_number is indexed, never unique.
 */
class CadastralFileReceipt extends CadastralModel
{
    protected $table = 'cadastral_file_receipts';

    protected $casts = [
        'file_indexing_id'         => 'integer',
        'cadastral_shadow_file_id' => 'integer',
        'received_at'    => 'datetime',
        'registered_at'  => 'datetime',
        'archived_at'    => 'datetime',
        'duplicate_flag' => 'boolean',
        // Phase 3 hold columns. Casting an attribute the table does not have yet
        // is harmless: the cast only applies when the value is present.
        'hold_placed_by'  => 'integer',
        'hold_placed_at'  => 'datetime',
        'hold_cleared_by' => 'integer',
        'hold_cleared_at' => 'datetime',
    ];

    public const STATUSES = ['Received', 'Registered', 'Archived', 'Returned', 'Rejected'];

    public const PURPOSES = ['Verification', 'Customary', 'Statutory', 'Charting', 'Enquiry'];

    /** hold_status values; NULL means the receipt has never been held. */
    public const HOLD_ON      = 'On Hold';
    public const HOLD_CLEARED = 'Cleared';

    public function reports(): HasMany
    {
        return $this->hasMany(CadastralReport::class, 'cadastral_file_receipt_id');
    }

    /**
     * The correspondence (cadastral shadow) file this receipt created or was
     * matched to. Null when the correspondence exists only as the
     * file_indexings.is_corresponding_file flag, which has no row to point at.
     */
    public function shadowFile(): BelongsTo
    {
        return $this->belongsTo(CadastralShadowFile::class, 'cadastral_shadow_file_id');
    }

    /** False before the hold columns exist: the attribute is simply absent. */
    public function isOnHold(): bool
    {
        return ($this->attributes['hold_status'] ?? null) === self::HOLD_ON;
    }

    public function isConversion(): bool
    {
        return $this->file_class === 'conversion';
    }

    public function getStatusBadgeAttribute(): string
    {
        return match ($this->status) {
            'Registered' => 'active',
            'Archived'   => 'completed',
            'Rejected'   => 'rejected',
            'Returned'   => 'review',
            default      => 'pending',
        };
    }
}
