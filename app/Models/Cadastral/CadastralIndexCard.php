<?php

namespace App\Models\Cadastral;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Schema;

/**
 * The cadastral index card, as data.
 *
 * One live card per file number, enforced by a filtered unique index so a
 * soft-deleted card does not block a replacement.
 *
 * The card's movement record is deliberately NOT stored here — it is read
 * through from file_tracker for the same file number, which is what keeps it in
 * step with the rest of KLAES rather than becoming a second, staler copy. The
 * card's own stage inside Cadastral is in audit_logs (IndexCardMovements).
 *
 * Since Phase 5 a card is commissioned from a registered intake receipt and
 * keeps cadastral_file_receipt_id / file_indexing_id — once
 * 2026_10_02_110000_cadastral_phase5 has added them (linkInstalled()).
 */
class CadastralIndexCard extends CadastralModel
{
    protected $table = 'cadastral_index_cards';

    protected $casts = [
        'cadastral_chart_id'     => 'integer',
        'commissioned_at'        => 'datetime',
        'file_status_changed_at' => 'datetime',
        'last_printed_at'        => 'datetime',
        'print_count'            => 'integer',
        // Phase 5 pointers. Casting an attribute the table does not have yet is
        // harmless: the cast only applies when the value is present.
        'cadastral_file_receipt_id' => 'integer',
        'file_indexing_id'          => 'integer',
    ];

    /** hasColumn costs a round trip; one answer per request (per process). */
    private static ?bool $linkInstalled = null;

    /** Whether the receipt / file-index pointers exist yet. */
    public static function linkInstalled(): bool
    {
        return self::$linkInstalled ??= Schema::connection('sqlsrv')
            ->hasColumn('cadastral_index_cards', 'cadastral_file_receipt_id');
    }

    /** Forget the cached answer — for a script that adds the columns mid-run. */
    public static function refreshLinkInstalled(): void
    {
        self::$linkInstalled = null;
    }

    /** File statuses the Cadastral Information Unit maintains (concept note 4.3d). */
    public const FILE_STATUSES = [
        'open'               => 'Open',
        'closed'             => 'Closed',
        'revoked'            => 'Revoked',
        'reinstated'         => 'Reinstated',
        'withdrawn'          => 'Withdrawn',
        'change_of_purpose'  => 'Change of Purpose',
    ];

    public function chart(): BelongsTo
    {
        return $this->belongsTo(CadastralChart::class, 'cadastral_chart_id');
    }

    /** The intake receipt the card was commissioned from (null before Phase 5). */
    public function receipt(): BelongsTo
    {
        return $this->belongsTo(CadastralFileReceipt::class, 'cadastral_file_receipt_id');
    }

    /**
     * The receipt, whether or not the pointer column exists: by pointer, else
     * the latest registered receipt for the same file number.
     */
    public function sourceReceipt(): ?CadastralFileReceipt
    {
        if ($id = $this->attributes['cadastral_file_receipt_id'] ?? null) {
            return CadastralFileReceipt::find($id);
        }

        return CadastralFileReceipt::query()
            ->where('file_number', $this->file_number)
            ->whereNotNull('registered_at')
            ->orderByDesc('id')
            ->first();
    }

    public function statusEvents(): HasMany
    {
        return $this->hasMany(CadastralFileStatusEvent::class, 'cadastral_index_card_id')
            ->orderByDesc('id');
    }

    public function surveyJobs(): HasMany
    {
        return $this->hasMany(CadastralSurveyJob::class, 'cadastral_index_card_id');
    }

    public function getFileStatusLabelAttribute(): string
    {
        return self::FILE_STATUSES[$this->file_status] ?? ucfirst((string) $this->file_status);
    }

    public function getStatusBadgeAttribute(): string
    {
        return match ($this->file_status) {
            'open', 'reinstated'          => 'active',
            'revoked', 'withdrawn'        => 'rejected',
            'closed'                      => 'completed',
            default                       => 'pending',
        };
    }
}
