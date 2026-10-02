<?php

namespace App\Services;

use App\Models\TitleStatusApplication;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Repair a Re-grant that was captured wrongly.
 *
 * Two corrections, sharing one reversal engine:
 *
 *   SWAP — the operator entered the two file numbers the wrong way round. The file
 *          recorded as the successor is really the parent, and vice versa.
 *   UNDO — there was no Re-grant at all. The record is withdrawn and both files are
 *          put back the way they were.
 *
 * Why this is more than an UPDATE of two columns: recording a Re-grant writes FOUR
 * things (see {@see TitleStatusService::recordRegrant()} and the File Indexing path in
 * TitleStatusController::store()):
 *
 *   1. the title_status_applications row — often a reciprocal PAIR, one row per direction
 *   2. title-status flags on BOTH files, across every source table holding that number
 *   3. a related_file_number linkage row, successor -> parent
 *   4. a real decommissioning of the PARENT — archived to decommissioned_files and
 *      flagged is_decommissioned on the live rows
 *
 * So a reversed entry has retired the wrong file. Correcting only the register row would
 * leave the register saying one thing and the files themselves another, which is worse
 * than the original error because it hides it. Every call here therefore unwinds all four.
 *
 * NOTHING IS DELETED. Register rows are soft-deleted (is_deleted), and a decommissioning
 * is released by marking its archive row false_decommissioning = 1 — the value every
 * query in this codebase already reads as "a flag, not a real retirement" — with the
 * reason amended to say who released it and why. The archive row survives as the audit
 * trail of the mistake.
 */
class RegrantCorrectionService
{
    public const MODE_SWAP = 'swap';
    public const MODE_UNDO = 'undo';

    /**
     * The reason decommissionRegrantParent() stamps on a Re-grant retirement:
     * "Re-grant → {successor}". Only retirements bearing it are ours to release —
     * a file retired by a merger or a subdivision must be left alone.
     */
    private const REGRANT_REASON_PREFIX = 'Re-grant →';

    /**
     * Every spelling `title_status_type` carries for a Re-grant on the live rows.
     *
     * Two paths write this column with different vocabularies and both are in the data:
     * flagAndDecommission() writes the SLUG ('regranted'), while the Re-grant commissioning
     * path writes the LABEL ('Re-grant'). applyFlags()' own docblock records the split.
     * Clearing on the labels alone silently matched nothing on the ~2/3 of rows holding the
     * slug, which left an undone Re-grant still flagged on both files.
     */
    private const FLAG_TYPES = [
        TitleStatusApplication::TYPE_REGRANT,
        TitleStatusApplication::TYPE_REGRANT_FROM,
        TitleStatusApplication::TYPE_REGRANT_TO,
        'regranted',
    ];

    /**
     * Tables carrying the decommission attributes, with the column holding this file's
     * number. Mirrors PlotWorkflowService::decommissionFiles() exactly — a release that
     * misses a table leaves the file half-retired.
     */
    private const DECOMMISSION_TABLES = [
        'fileNumber'        => ['mlsfNo', 'kangisFileNo'],
        'file_indexings'    => ['file_number', 'kangis_file_no'],
        'mls_file_no'       => ['full_file_number'],
        'entities_staging'  => ['file_number'],
        'customers_staging' => ['file_number'],
        'kangis_grouping'   => ['kangis_fileno_placeholder'],
    ];

    public function __construct(
        protected TitleStatusService $titleStatus,
        protected RegrantTermService $termService
    ) {}

    /**
     * What the correction would do, without writing anything. The UI shows this verbatim
     * before asking for confirmation — on a register with no backups, an officer gets to
     * read the consequence before causing it.
     */
    public function preview(int $id, string $mode): array
    {
        $record = $this->findRecord($id);
        $group  = $this->group($record);
        [$successor, $parent] = $this->roles($record);

        if ($parent === '' || $successor === '') {
            throw new \RuntimeException('This record has only one file number on it, so there is no direction to correct. Edit it in Title Status instead.');
        }

        $rows = $group->map(fn ($r) => [
            'id'         => $r->id,
            'title_type' => $r->title_type,
            'before'     => trim((string) $r->file_no) . ' → ' . trim((string) $r->see_fileno),
            'after'      => $mode === self::MODE_SWAP
                ? trim((string) $r->see_fileno) . ' → ' . trim((string) $r->file_no)
                : 'withdrawn',
        ])->values()->all();

        $release = $this->releasePlan($parent, $successor);

        $plan = [
            'mode'            => $mode,
            'record_id'       => $record->id,
            'current'         => ['successor' => $successor, 'parent' => $parent],
            'rows'            => $rows,
            'release'         => $release,
            'warnings'        => $release['warnings'],
        ];

        if ($mode === self::MODE_SWAP) {
            $plan['resulting'] = ['successor' => $parent, 'parent' => $successor];
            $plan['flags'] = [
                $parent    => "This File has been Re-granted from {$successor}",
                $successor => "This File has been Re-granted to {$parent}",
            ];
            // The swap retires the file that was wrongly left live.
            $plan['retire'] = $successor;
        } else {
            $plan['resulting'] = null;
            $plan['flags'] = [
                $successor => 'cleared — no Re-grant',
                $parent    => 'cleared — no Re-grant',
            ];
            $plan['retire'] = null;
        }

        return $plan;
    }

    /**
     * Apply the correction. Everything runs in one transaction: a half-applied swap
     * would leave the register and the file flags disagreeing, which is the exact
     * failure this tool exists to fix.
     *
     * @param string|null $note free-text the officer gives for the audit trail
     */
    public function apply(int $id, string $mode, ?string $note = null): array
    {
        $plan = $this->preview($id, $mode);

        $record = $this->findRecord($id);
        $group  = $this->group($record);
        [$successor, $parent] = $this->roles($record);

        $actor = $this->actorName();
        $stamp = trim((string) $note) !== ''
            ? ' — ' . trim((string) $note)
            : '';

        DB::connection('sqlsrv')->transaction(function () use ($group, $mode, $successor, $parent, $actor, $stamp) {
            // 1. Release the parent's retirement. Both corrections need this: the swap
            //    re-applies it to the other file, the undo leaves both files live.
            $this->release($parent, $successor, $actor, $mode, $stamp);

            // 2. Clear the Re-grant title-status flags off both files. Restricted to
            //    rows currently carrying a Re-grant type, so a Litigation or Revocation
            //    flag written over the top since is not wiped by this.
            $this->titleStatus->clearFlags($successor, self::FLAG_TYPES);
            $this->titleStatus->clearFlags($parent, self::FLAG_TYPES);

            if ($mode === self::MODE_SWAP) {
                $this->applySwap($group, $successor, $parent);
            } else {
                $this->applyUndo($group, $successor, $parent, $actor, $stamp);
            }
        });

        // The due list is cached for 15 minutes and both corrections change who belongs
        // on it — an undone file becomes due again, a swapped one changes which number
        // carries the Re-grant.
        $this->termService->flushCache();

        Log::info('Re-grant correction applied', [
            'mode'      => $mode,
            'record_id' => $id,
            'successor' => $successor,
            'parent'    => $parent,
            'rows'      => array_column($plan['rows'], 'id'),
            'by'        => $actor,
            'note'      => $note,
        ]);

        return $plan;
    }

    // ───────────────────────── swap ─────────────────────────

    /**
     * Swap the two file numbers on every row of the pair, then re-apply the Re-grant
     * the right way round.
     *
     * The column values are swapped and `title_type` is LEFT ALONE. Either would flip the
     * direction on its own — doing both would cancel out — and swapping the values keeps
     * the register's de-duplication priority (which prefers a 'Re-granted From' row) and
     * the reciprocal pairing intact.
     */
    private function applySwap(Collection $group, string $successor, string $parent): void
    {
        foreach ($group as $row) {
            $fileNo = trim((string) $row->file_no);
            $seeNo  = trim((string) $row->see_fileno);

            $updates = [
                'file_no'    => $seeNo,
                'see_fileno' => $fileNo,
                'updated_by' => Auth::id(),
                'updated_at' => now(),
            ];

            // The row's descriptive columns (title, holder, plot, location…) describe the
            // file in file_no. That is now a different file, so they are re-read rather
            // than carried across — leaving them would attribute one file's holder and
            // plot to another, which is how this kind of error spreads.
            $updates += $this->fileDetails($seeNo);

            $remark = $this->titleStatus->generateRemark(
                (string) $row->title_type,
                (string) ($row->initiated_by ?? 'Ministry'),
                '',
                (string) ($updates['applicant_name'] ?? ''),
                $seeNo,
                $fileNo
            );

            $updates['remark'] = $remark;

            // `reason` holds the generated wording on rows written by the Re-grant paths,
            // but an officer's own words on rows raised from the due list. Only the
            // generated kind is rewritten; a typed reason is the operator's and is kept.
            if ($this->isGeneratedReason((string) ($row->reason ?? ''))) {
                $updates['reason'] = $remark;
            }

            DB::connection('sqlsrv')->table('title_status_applications')
                ->where('id', $row->id)
                ->update($updates);
        }

        // Re-point the linkage row, successor -> parent, now the other way round.
        $this->repointFileLink($successor, $parent);

        // Re-apply the flags and retire the file that really is the parent. The roles
        // have exchanged: what was the successor is now the parent.
        $newSuccessor = $parent;
        $newParent    = $successor;

        $this->titleStatus->applyFlags(
            $newSuccessor,
            TitleStatusApplication::TYPE_REGRANT,
            "This File has been Re-granted from {$newParent}"
        );

        $this->titleStatus->applyFlags(
            $newParent,
            TitleStatusApplication::TYPE_REGRANT,
            "This File has been Re-granted to {$newSuccessor}"
        );

        $this->titleStatus->decommissionRegrantParent($newParent, $newSuccessor);
    }

    // ───────────────────────── undo ─────────────────────────

    /** Withdraw the record. Soft-deleted, so the row survives as evidence of the mistake. */
    private function applyUndo(Collection $group, string $successor, string $parent, string $actor, string $stamp): void
    {
        foreach ($group as $row) {
            DB::connection('sqlsrv')->table('title_status_applications')
                ->where('id', $row->id)
                ->update([
                    'is_deleted' => 1,
                    'deleted_by' => Auth::id(),
                    'deleted_at' => now(),
                    'updated_by' => Auth::id(),
                    'updated_at' => now(),
                    'remark'     => mb_substr(
                        "Withdrawn — not a Re-grant. Reversed by {$actor}{$stamp}",
                        0,
                        500
                    ),
                ]);
        }

        $this->dropFileLink($successor, $parent);
    }

    // ─────────────────── decommissioning release ───────────────────

    /**
     * Can the parent's retirement be released, and what is in the way?
     *
     * A file may have been retired more than once — re-granted, then its successor
     * merged, say. Only the Re-grant retirement is ours to release; any OTHER real
     * archive row means the file stays retired and only the register row is corrected.
     * That case is reported rather than silently half-done.
     */
    private function releasePlan(string $parent, string $successor): array
    {
        $rows = $this->archiveRows($parent);

        $ours = $rows->filter(fn ($r) => $this->isRegrantRetirement($r, $successor));
        $other = $rows->reject(fn ($r) => $this->isRegrantRetirement($r, $successor));

        $warnings = [];
        if ($ours->isEmpty()) {
            $warnings[] = "{$parent} carries no Re-grant retirement to release — it may have been retired by hand, or never retired at all. The register row is still corrected.";
        }
        if ($other->isNotEmpty()) {
            $reasons = $other->pluck('decommissioning_reason')->filter()->unique()->take(3)->implode('; ');
            $warnings[] = "{$parent} is also retired by another workflow ({$reasons}), so it stays decommissioned. Only its Re-grant retirement is released.";
        }

        return [
            'file'            => $parent,
            'releasable'      => $ours->pluck('id')->all(),
            'blocking'        => $other->pluck('id')->all(),
            'clears_live_row' => $ours->isNotEmpty() && $other->isEmpty(),
            'warnings'        => $warnings,
        ];
    }

    /**
     * Release the Re-grant retirement on $parent.
     *
     * The archive row is kept and marked false_decommissioning = 1 — the marker every
     * query here already treats as "not a real retirement" — with the reason amended to
     * record the reversal. The live rows are only un-flagged when NO other real
     * retirement remains, so a file retired twice stays retired.
     */
    private function release(string $parent, string $successor, string $actor, string $mode, string $stamp): void
    {
        $plan = $this->releasePlan($parent, $successor);

        if ($plan['releasable'] !== []) {
            $verb = $mode === self::MODE_SWAP ? 'direction corrected' : 'withdrawn, not a Re-grant';

            foreach ($plan['releasable'] as $archiveId) {
                $row = DB::connection('sqlsrv')->table('decommissioned_files')->where('id', $archiveId)->first();
                $reason = mb_substr(
                    (string) ($row->decommissioning_reason ?? '')
                        . " [RELEASED {$verb} by {$actor} on " . now()->format('d/m/Y H:i') . "{$stamp}]",
                    0,
                    1000
                );

                DB::connection('sqlsrv')->table('decommissioned_files')
                    ->where('id', $archiveId)
                    ->update([
                        'false_decommissioning'  => 1,
                        'decommissioning_reason' => $reason,
                        'updated_at'             => now(),
                    ]);
            }
        }

        if (!$plan['clears_live_row']) {
            return;
        }

        $this->clearDecommissionFlags($parent);
    }

    /** Un-flag the live rows across every table decommissionFiles() stamps. */
    private function clearDecommissionFlags(string $fileNo): void
    {
        $schema = Schema::connection('sqlsrv');

        foreach (self::DECOMMISSION_TABLES as $table => $columns) {
            try {
                if (!$schema->hasTable($table) || !$schema->hasColumn($table, 'is_decommissioned')) {
                    continue;
                }

                $payload = ['is_decommissioned' => 0];

                foreach ([
                    'decommissioned_at',
                    'decommissioned_by',
                    'decommissioning_reason',
                    'decommissioning_date',
                    'successor_file_no',
                ] as $column) {
                    if ($schema->hasColumn($table, $column)) {
                        $payload[$column] = null;
                    }
                }

                if ($schema->hasColumn($table, 'updated_at')) {
                    $payload['updated_at'] = now();
                }

                DB::connection('sqlsrv')->table($table)
                    ->where(function ($q) use ($columns, $fileNo) {
                        foreach ($columns as $i => $column) {
                            $i === 0 ? $q->where($column, $fileNo) : $q->orWhere($column, $fileNo);
                        }
                    })
                    ->update($payload);
            } catch (\Exception $e) {
                Log::warning("Re-grant correction: could not clear decommission flags on {$table} for {$fileNo}: " . $e->getMessage());
            }
        }
    }

    /** Real (not already-false) archive rows for a file, under either of its number columns. */
    private function archiveRows(string $fileNo): Collection
    {
        return collect(DB::connection('sqlsrv')->table('decommissioned_files')
            ->where(fn ($q) => $q->where('file_no', $fileNo)->orWhere('mls_file_no', $fileNo))
            ->where(fn ($q) => $q->where('false_decommissioning', '<>', 1)->orWhereNull('false_decommissioning'))
            ->get(['id', 'decommissioning_reason', 'successor_file_no']));
    }

    /**
     * Is this archive row the Re-grant retirement we wrote?
     *
     * Matched on the reason prefix decommissionRegrantParent() stamps, or on the
     * successor pointer — a row written before the reason carried the arrow still
     * names the successor, and that identifies it just as well.
     */
    private function isRegrantRetirement(object $row, string $successor): bool
    {
        $reason = (string) ($row->decommissioning_reason ?? '');

        if (str_starts_with($reason, self::REGRANT_REASON_PREFIX)) {
            return true;
        }

        return strcasecmp(trim((string) ($row->successor_file_no ?? '')), $successor) === 0
            && stripos($reason, 'grant') !== false;
    }

    // ───────────────────────── linkage ─────────────────────────

    /** Turn the successor -> parent linkage row around. */
    private function repointFileLink(string $successor, string $parent): void
    {
        try {
            if (!Schema::connection('sqlsrv')->hasTable('related_file_number')) {
                return;
            }

            $indexingId = DB::connection('sqlsrv')->table('file_indexings')
                ->where('file_number', $parent)->value('id');

            DB::connection('sqlsrv')->table('related_file_number')
                ->where('file_number', $successor)
                ->where('related_fileno', $parent)
                ->update(array_filter([
                    'file_number'    => $parent,
                    'related_fileno' => $successor,
                    'source_id'      => $indexingId ? (int) $indexingId : null,
                    'comment'        => "This File has been Re-granted to {$successor}",
                    'updated_at'     => now(),
                ], fn ($v) => $v !== null));
        } catch (\Exception $e) {
            Log::warning("Re-grant correction: could not re-point related_file_number {$successor} -> {$parent}: " . $e->getMessage());
        }
    }

    /**
     * Drop the linkage row for a Re-grant that never happened.
     *
     * This one IS a delete, and deliberately so: related_file_number is a live pointer,
     * not an archive, and leaving it would keep showing the two files as related on the
     * File Indexing and Legal Search screens after the Re-grant has been withdrawn. The
     * soft-deleted register row remains as the record that it once existed.
     */
    private function dropFileLink(string $successor, string $parent): void
    {
        try {
            if (!Schema::connection('sqlsrv')->hasTable('related_file_number')) {
                return;
            }

            DB::connection('sqlsrv')->table('related_file_number')
                ->where('file_number', $successor)
                ->where('related_fileno', $parent)
                ->where(fn ($q) => $q->where('transaction_type', 'LIKE', '%Re-grant%')->orWhereNull('transaction_type'))
                ->delete();
        } catch (\Exception $e) {
            Log::warning("Re-grant correction: could not drop related_file_number {$successor} -> {$parent}: " . $e->getMessage());
        }
    }

    // ───────────────────────── helpers ─────────────────────────

    private function findRecord(int $id): TitleStatusApplication
    {
        $record = TitleStatusApplication::whereIn('title_type', TitleStatusApplication::REGRANT_TYPES)
            ->where(fn ($q) => $q->whereNull('is_deleted')->orWhere('is_deleted', 0))
            ->find($id);

        if (!$record) {
            throw new \RuntimeException('That Re-grant record no longer exists, or has already been withdrawn.');
        }

        return $record;
    }

    /**
     * The record plus any reciprocal rows — "Re-granted From" on the new file and
     * "Re-granted To" on the old one are stored as two independent rows, and correcting
     * one without the other would leave the pair contradicting itself.
     */
    private function group(TitleStatusApplication $record): Collection
    {
        $fileNo = trim((string) $record->file_no);
        $seeNo  = trim((string) $record->see_fileno);

        $rows = collect([$record]);

        if ($fileNo === '' || $seeNo === '') {
            return $rows;
        }

        $reciprocals = TitleStatusApplication::whereIn('title_type', TitleStatusApplication::REGRANT_TYPES)
            ->where(fn ($q) => $q->whereNull('is_deleted')->orWhere('is_deleted', 0))
            ->where('file_no', $seeNo)
            ->where('see_fileno', $fileNo)
            ->where('id', '<>', $record->id)
            ->get();

        return $rows->concat($reciprocals);
    }

    /**
     * Which file is the successor (the new one) and which the parent (the one replaced).
     * `title_type` says what role `file_no` plays: "Re-granted To" puts the OLD file in
     * file_no; every other Re-grant type puts the NEW one there.
     *
     * @return array{0:string,1:string} [successor, parent]
     */
    private function roles(TitleStatusApplication $record): array
    {
        $fileNo = trim((string) $record->file_no);
        $seeNo  = trim((string) $record->see_fileno);

        return $record->title_type === TitleStatusApplication::TYPE_REGRANT_TO
            ? [$seeNo, $fileNo]
            : [$fileNo, $seeNo];
    }

    /** The descriptive columns, re-read for whichever file now sits in file_no. */
    private function fileDetails(string $fileNo): array
    {
        $row = DB::connection('sqlsrv')->table('file_indexings')
            ->where('file_number', $fileNo)
            ->first();

        if (!$row) {
            return [];
        }

        return array_filter([
            'source_table'   => 'file_indexings',
            'source_id'      => $row->id ?? null,
            'file_title'     => $row->file_title ?? null,
            'applicant_name' => $row->current_holder ?? ($row->original_holder ?? null),
            'plot_no'        => $row->plot_number ?? null,
            'district'       => $row->district ?? null,
            'lga'            => $row->lga ?? null,
            'location'       => $row->location ?? null,
            'land_use'       => $row->land_use_type ?? null,
        ], fn ($v) => $v !== null && $v !== '');
    }

    /** Wording written by the Re-grant paths, as opposed to an officer's own reason. */
    private function isGeneratedReason(string $reason): bool
    {
        return $reason === '' || stripos(trim($reason), 'This File has been Re-granted') === 0;
    }

    private function actorName(): string
    {
        $user = Auth::user();

        if (!$user) {
            return 'System';
        }

        $name = trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? $user->name ?? ''));

        return $name !== '' ? $name : ('User #' . $user->id);
    }
}
