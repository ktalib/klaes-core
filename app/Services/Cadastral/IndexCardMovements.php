<?php

namespace App\Services\Cadastral;

use App\Models\AuditLog;
use App\Models\Cadastral\CadastralIndexCard;
use App\Services\AuditService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Where an index card is in the Cadastral Department: its movement stage.
 *
 * TWO RECORDS, AND WHY NEITHER IS A NEW TABLE.
 *
 *  1. The FILE's movement between offices is file_tracker.movement_log, read
 *     live (CadastralRegistryLookup::movements). This module never writes it:
 *     FileTracker::addMovementLog() moves the file's current_office for the
 *     whole Ministry and belongs to the tracker's own hand-over and acceptance
 *     flow, and most files (52k tracked of 170k indexed) have no tracker row
 *     to append to at all.
 *
 *  2. The CARD's stage inside Cadastral — Commissioned, With Charting, With
 *     Survey … — is not an office hand-over, so it does not belong in the
 *     tracker. It is recorded as an append-only audit_logs entry
 *     (CADASTRAL_INDEX_CARD_MOVED, resource cadastral_index_card), the same
 *     place the module already keeps the history of receipt holds. audit_logs
 *     is indexed on (resource_type, resource_id), every entry carries who and
 *     when, and nothing in KLAES edits it — which is the property a movement
 *     history needs. A cadastral_index_card_movements table would have been the
 *     parallel movement log the plan says not to build.
 *
 * The write is NOT best-effort: a stage the officer was told is recorded must
 * be recorded, so record() runs inside the caller's transaction and an audit
 * failure rolls the whole change back.
 */
class IndexCardMovements
{
    public const ACTION   = 'CADASTRAL_INDEX_CARD_MOVED';
    public const RESOURCE = 'cadastral_index_card';

    /** Stage key => label. Ordered as a file usually travels. */
    public const STAGES = [
        'commissioned'     => 'Commissioned',
        'charting'         => 'With Charting',
        'survey'           => 'With Survey',
        'report'           => 'With the Report Unit',
        'plan_description' => 'With Plan & Description',
        'registry'         => 'Returned to Registry',
        'dispatched'       => 'Dispatched',
        'archived'         => 'Archived',
    ];

    public function __construct(private AuditService $audit) {}

    public static function label(?string $stage): string
    {
        return self::STAGES[$stage] ?? ucfirst(str_replace('_', ' ', (string) $stage));
    }

    /**
     * Record a stage. Call inside the caller's sqlsrv transaction (audit_logs
     * lives on the same connection, so it commits or rolls back with the card).
     */
    public function record(CadastralIndexCard $card, string $stage, ?string $note = null, ?string $from = null): void
    {
        $this->audit->logAction(
            self::ACTION,
            self::RESOURCE,
            $card->id,
            $from ? ['stage' => $from] : null,
            array_filter([
                'stage'       => $stage,
                'stage_label' => self::label($stage),
                'note'        => $note,
                'file_number' => $card->file_number,
                'actor_name'  => auth()->user()->name ?? null,
            ], fn ($v) => $v !== null && $v !== ''),
            "{$card->card_ref} ({$card->file_number}): " . self::label($stage)
        );
    }

    /**
     * One card's stage history, newest first.
     *
     * @return Collection<int, array{stage: ?string, label: string, from: ?string, note: ?string, by: ?string, at: mixed}>
     */
    public function history(CadastralIndexCard $card, int $limit = 100): Collection
    {
        return AuditLog::query()
            ->where('resource_type', self::RESOURCE)
            ->where('resource_id', $card->id)
            ->where('action', self::ACTION)
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->map(fn (AuditLog $log) => $this->shape($log));
    }

    /**
     * The latest stage of each card, keyed by card id — one query per page.
     *
     * @param  int[]  $cardIds
     * @return array<int, array>
     */
    public function latestFor(array $cardIds): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $cardIds))));

        if ($ids === []) {
            return [];
        }

        $latestIds = AuditLog::query()
            ->where('resource_type', self::RESOURCE)
            ->whereIn('resource_id', $ids)
            ->where('action', self::ACTION)
            ->groupBy('resource_id')
            ->selectRaw('MAX(id) as id')
            ->pluck('id')
            ->all();

        return AuditLog::query()->whereIn('id', $latestIds)->get()
            ->mapWithKeys(fn (AuditLog $log) => [(int) $log->resource_id => $this->shape($log)])
            ->all();
    }

    /**
     * The tracker's current office for each file number, keyed by number.
     * Two columns per row — never movement_log, which can run to megabytes.
     *
     * @param  string[]  $fileNumbers
     * @return array<string, string>
     */
    public function trackerOffices(array $fileNumbers): array
    {
        $numbers = array_values(array_unique(array_filter(array_map('trim', $fileNumbers))));

        if ($numbers === []) {
            return [];
        }

        return DB::connection('sqlsrv')->table('file_tracker')
            ->whereIn('file_number', $numbers)
            ->orderBy('id')                          // later rows win below
            ->get(['file_number', 'current_office_name'])
            ->filter(fn ($r) => trim((string) $r->current_office_name) !== '')
            ->mapWithKeys(fn ($r) => [trim((string) $r->file_number) => (string) $r->current_office_name])
            ->all();
    }

    private function shape(AuditLog $log): array
    {
        $new = is_array($log->new_values) ? $log->new_values : (json_decode((string) $log->new_values, true) ?: []);
        $old = is_array($log->old_values) ? $log->old_values : (json_decode((string) $log->old_values, true) ?: []);

        return [
            'stage' => $new['stage'] ?? null,
            'label' => $new['stage_label'] ?? self::label($new['stage'] ?? null),
            'from'  => isset($old['stage']) ? self::label($old['stage']) : null,
            'note'  => $new['note'] ?? null,
            'by'    => $new['actor_name'] ?? null,
            'at'    => $log->created_at,
        ];
    }
}
