<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * KANGIS three-file linkage ("Option A", guide-faithful).
 *
 * The KANGIS Indexing Flow (docs/guides/# KANGIS Indexing Flow.md) models three
 * independently-indexed files that belong to one property:
 *
 *   - Old KANGIS File (legacy 4-letter prefix, e.g. "MLKN 1934") — the PARENT / root.
 *   - Land File       (e.g. "RES-2025-101")                       — child.
 *   - New KANGIS File (KN series, e.g. "KN67890")                 — child.
 *
 * Each file carries its OWN prop_id. The two children point up at the Old KANGIS
 * file's prop_id via `parent_prop_id` (a comma-separated ancestor list that
 * LegalSearchService already expands). This service is the single source of truth
 * for writing those links — used both at index time (FileIndexingController) and
 * by the `kangis:link-parent-propids` reconciliation command.
 *
 * Design constraints (confirmed with product):
 *   - Forward-only: never re-splits already-shared prop_ids; only fills links.
 *   - Best-effort at index time: if the Old KANGIS parent isn't indexed yet, the
 *     child's parent_prop_id is left for the reconciliation command to backfill.
 *   - Merge, don't overwrite: parent_prop_id is a list, so an existing ancestor
 *     (e.g. a subdivision mother) is preserved.
 */
class KangisParentLinkService
{
    private const CONNECTION = 'sqlsrv';

    /** Tables that hold transaction rows keyed back to a file number. */
    private const TRANSACTION_TABLES = ['pra', 'CofO_staging'];

    /**
     * Every write that changes parent_prop_id also refreshes ancestral_prop_id —
     * the root of the chain — so the three-level cascade stays consistent without
     * waiting for the next `propid:backfill-ancestral` sweep.
     *
     * Adds the column to $update only when the table has been migrated for it,
     * so an un-migrated database keeps working unchanged.
     */
    private function withAncestral(string $table, array $update, ?string $mergedParents, $propId = null): array
    {
        try {
            if (!Schema::connection(self::CONNECTION)->hasColumn($table, 'ancestral_prop_id')) {
                return $update;
            }

            $update['ancestral_prop_id'] = app(PropIdLineageService::class)
                ->resolveAncestralForRow($propId === null ? null : (string) $propId, $mergedParents);
        } catch (\Throwable $e) {
            // Fail-open: the parent link still gets written; the backfill will
            // reconcile ancestral_prop_id later.
            Log::warning('KangisParentLinkService: ancestral refresh skipped', [
                'table' => $table,
                'error' => $e->getMessage(),
            ]);
        }

        return $update;
    }

    /**
     * Legacy "Old KANGIS" number: a 4-letter prefix + serial (MLKN/KNML/KNGP/MNKL …),
     * optionally with a "_N" sibling suffix. This is the PARENT candidate.
     */
    public function isLegacyKangisNumber(?string $fileNo): bool
    {
        $v = strtoupper(trim((string) $fileNo));

        return $v !== '' && (bool) preg_match('/^[A-Z]{4}\s?\d{1,6}(_\d+)?$/', $v);
    }

    /**
     * New KANGIS number: the "KN" series written with NO separator (e.g. "KN67890").
     *
     * IMPORTANT: "KN 120" / "KN-120" (with a space or dash) is NOT a New KANGIS file —
     * it is a legacy MLS / land file number (see LegalSearchService::isOldMlsKnFileNo,
     * `/^KN[- ]\d+/`). Only the joined form "KN123" is a genuine New KANGIS file, so the
     * regex here deliberately forbids any separator between "KN" and the digits.
     */
    public function isNewKangisNumber(?string $fileNo): bool
    {
        $v = strtoupper(trim((string) $fileNo));

        return $v !== '' && (bool) preg_match('/^KN\d{1,6}(_\d+)?$/', $v);
    }

    /**
     * Legacy MLS / land file written in the "KN" style WITH a separator ("KN 120",
     * "KN-120"). These masquerade as New KANGIS numbers but are ordinary land files.
     */
    public function isOldMlsKnNumber(?string $fileNo): bool
    {
        $v = strtoupper(trim((string) $fileNo));

        return $v !== '' && (bool) preg_match('/^KN[- ]\d/', $v);
    }

    /**
     * Read-only prop_id lookup for a file number. Checks file_indexings first,
     * then PropID_Master. Never allocates — a parent that isn't indexed yet
     * simply returns null (the reconciliation command backfills later).
     */
    public function lookupExistingPropId(?string $fileNumber): ?int
    {
        $fileNumber = trim((string) $fileNumber);
        if ($fileNumber === '') {
            return null;
        }

        $conn = DB::connection(self::CONNECTION);

        try {
            $row = $conn->table('file_indexings')
                ->whereRaw('UPPER(LTRIM(RTRIM(file_number))) = UPPER(?)', [$fileNumber])
                ->whereNotNull('prop_id')
                ->orderByDesc('id')
                ->first(['prop_id']);

            if ($row && $row->prop_id) {
                return (int) $row->prop_id;
            }

            if (Schema::connection(self::CONNECTION)->hasTable('PropID_Master')) {
                $upper = strtoupper($fileNumber);
                $master = $conn->table('PropID_Master')
                    ->where(function ($q) use ($upper) {
                        foreach (['primary_file_number', 'mlsFNo', 'kangisFileNo', 'NewKANGISFileno'] as $col) {
                            $q->orWhereRaw('UPPER(LTRIM(RTRIM(' . $col . '))) = ?', [$upper]);
                        }
                    })
                    ->whereNotNull('prop_id')
                    ->orderByDesc('updated_at')
                    ->first(['prop_id']);

                if ($master && $master->prop_id) {
                    return (int) $master->prop_id;
                }
            }
        } catch (\Throwable $e) {
            Log::warning('KangisParentLinkService::lookupExistingPropId failed', [
                'file_number' => $fileNumber,
                'error' => $e->getMessage(),
            ]);
        }

        return null;
    }

    /**
     * Merge a prop_id into an existing comma-separated ancestor list without
     * duplicating it. Returns the new list string.
     */
    public function mergePropIdList(?string $existing, $add): string
    {
        $add = trim((string) $add);
        $ids = array_values(array_filter(
            array_map('trim', explode(',', (string) $existing)),
            fn ($v) => $v !== ''
        ));

        if ($add !== '' && !in_array($add, $ids, true)) {
            $ids[] = $add;
        }

        return implode(',', array_values(array_unique($ids)));
    }

    /**
     * Persist a file's OWN prop_id (only when the row doesn't already carry one)
     * and, when supplied, its parent_prop_id (merged into the ancestor list).
     * Passing $parentPropId = null leaves any existing parent untouched — call
     * this for the root (Old KANGIS) file, whose parent is intentionally empty.
     */
    public function persistOwnPropId(int $fileIndexingId, ?int $propId, ?int $parentPropId = null): void
    {
        $conn = DB::connection(self::CONNECTION);

        try {
            $row = $conn->table('file_indexings')->where('id', $fileIndexingId)->first(['prop_id', 'parent_prop_id']);
            if (!$row) {
                return;
            }

            $update = [];
            if ($propId !== null && empty($row->prop_id)) {
                $update['prop_id'] = $propId;
            }
            if ($parentPropId !== null) {
                $merged = $this->mergePropIdList($row->parent_prop_id, $parentPropId);
                if ($merged !== (string) ($row->parent_prop_id ?? '')) {
                    $update['parent_prop_id'] = $merged;
                    $update = $this->withAncestral(
                        'file_indexings',
                        $update,
                        $merged,
                        $update['prop_id'] ?? $row->prop_id ?? null
                    );
                }
            }

            if (!empty($update)) {
                $update['updated_at'] = now();
                $conn->table('file_indexings')->where('id', $fileIndexingId)->update($update);
            }
        } catch (\Throwable $e) {
            Log::warning('KangisParentLinkService::persistOwnPropId failed', [
                'id' => $fileIndexingId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Set (merge) the parent_prop_id on the file-level rows for a file number:
     * file_indexings + fileNumber. Read-modify-write so an existing ancestor list
     * is preserved.
     */
    public function assignFileLevelParent(string $fileNumber, int $parentPropId): void
    {
        $fileNumber = trim($fileNumber);
        if ($fileNumber === '') {
            return;
        }

        $conn = DB::connection(self::CONNECTION);

        try {
            $fi = $conn->table('file_indexings')
                ->whereRaw('UPPER(LTRIM(RTRIM(file_number))) = UPPER(?)', [$fileNumber])
                ->get(['id', 'parent_prop_id']);

            foreach ($fi as $row) {
                $merged = $this->mergePropIdList($row->parent_prop_id, $parentPropId);
                if ($merged !== (string) ($row->parent_prop_id ?? '')) {
                    $conn->table('file_indexings')->where('id', $row->id)
                        ->update($this->withAncestral('file_indexings', [
                            'parent_prop_id' => $merged,
                            'updated_at' => now(),
                        ], $merged));
                }
            }

            if (Schema::connection(self::CONNECTION)->hasColumn('fileNumber', 'parent_prop_id')) {
                $fn = $conn->table('fileNumber')
                    ->where(function ($q) use ($fileNumber) {
                        $q->whereRaw('UPPER(LTRIM(RTRIM(mlsfNo))) = UPPER(?)', [$fileNumber])
                          ->orWhereRaw('UPPER(LTRIM(RTRIM(kangisFileNo))) = UPPER(?)', [$fileNumber])
                          ->orWhereRaw('UPPER(LTRIM(RTRIM(NewKANGISFileNo))) = UPPER(?)', [$fileNumber]);
                    })
                    ->get(['id', 'parent_prop_id']);

                foreach ($fn as $row) {
                    $merged = $this->mergePropIdList($row->parent_prop_id, $parentPropId);
                    if ($merged !== (string) ($row->parent_prop_id ?? '')) {
                        $conn->table('fileNumber')->where('id', $row->id)
                            ->update($this->withAncestral('fileNumber', [
                                'parent_prop_id' => $merged,
                                'updated_at' => now(),
                            ], $merged));
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::warning('KangisParentLinkService::assignFileLevelParent failed', [
                'file_number' => $fileNumber,
                'parent_prop_id' => $parentPropId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Set (merge) the parent_prop_id on the transaction rows (pra / CofO_staging)
     * for a file number. Matches rows whose mlsFNo or NewKANGISFileno equals the
     * file number — i.e. transactions saved AGAINST this file.
     *
     * @return int number of rows updated
     */
    public function assignTransactionParent(string $fileNumber, int $parentPropId): int
    {
        $fileNumber = trim($fileNumber);
        if ($fileNumber === '') {
            return 0;
        }

        $conn = DB::connection(self::CONNECTION);
        $updated = 0;

        foreach (self::TRANSACTION_TABLES as $table) {
            try {
                if (!Schema::connection(self::CONNECTION)->hasColumn($table, 'parent_prop_id')) {
                    continue;
                }

                $newKangisCol = Schema::connection(self::CONNECTION)->hasColumn($table, 'NewKANGISFileno')
                    ? 'NewKANGISFileno'
                    : (Schema::connection(self::CONNECTION)->hasColumn($table, 'NewKANGISFileNo') ? 'NewKANGISFileNo' : null);

                $rows = $conn->table($table)
                    ->where(function ($q) use ($fileNumber, $newKangisCol) {
                        $q->whereRaw('UPPER(LTRIM(RTRIM(mlsFNo))) = UPPER(?)', [$fileNumber]);
                        if ($newKangisCol !== null) {
                            $q->orWhereRaw('UPPER(LTRIM(RTRIM(' . $newKangisCol . '))) = UPPER(?)', [$fileNumber]);
                        }
                    })
                    ->get(['id', 'parent_prop_id']);

                foreach ($rows as $row) {
                    $merged = $this->mergePropIdList($row->parent_prop_id, $parentPropId);
                    if ($merged !== (string) ($row->parent_prop_id ?? '')) {
                        $conn->table($table)->where('id', $row->id)
                            ->update($this->withAncestral($table, [
                                'parent_prop_id' => $merged,
                                'updated_at' => now(),
                            ], $merged));
                        $updated++;
                    }
                }
            } catch (\Throwable $e) {
                Log::warning('KangisParentLinkService::assignTransactionParent failed', [
                    'table' => $table,
                    'file_number' => $fileNumber,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $updated;
    }

    /**
     * Full index-time linkage for a just-saved file.
     *
     * Returns the parent_prop_id that applies to THIS file's own transactions
     * (null when the file is the Old KANGIS root, or when its parent isn't
     * indexed yet), so the caller can stamp new transaction rows.
     *
     * @param  array<int,string>  $relatedFileNos
     */
    public function linkOnIndex(int $fileIndexingId, string $fileNumber, ?int $ownPropId, array $relatedFileNos): ?int
    {
        if ($ownPropId === null) {
            return null;
        }

        $relatedFileNos = array_values(array_filter(array_map('trim', $relatedFileNos), fn ($v) => $v !== ''));

        // Case 1: the file being indexed IS the Old KANGIS parent (root).
        if ($this->isLegacyKangisNumber($fileNumber)) {
            // Persist its own prop_id; it is the root, so no parent.
            $this->persistOwnPropId($fileIndexingId, $ownPropId, null);

            // Any already-indexed related LAND file (not another KANGIS number)
            // becomes a child pointing up at this parent.
            foreach ($relatedFileNos as $rel) {
                if ($this->isLegacyKangisNumber($rel) || $this->isNewKangisNumber($rel)) {
                    continue;
                }
                $this->assignFileLevelParent($rel, $ownPropId);
                $this->assignTransactionParent($rel, $ownPropId);
            }

            // The Old KANGIS root's own transactions have no parent.
            return null;
        }

        // Case 2: the file is NOT legacy KANGIS (e.g. a Land file). Look for an
        // Old KANGIS ancestor among its related files that is already indexed.
        $parentPropId = null;
        foreach ($relatedFileNos as $rel) {
            if ($this->isLegacyKangisNumber($rel)) {
                $pid = $this->lookupExistingPropId($rel);
                if ($pid !== null) {
                    $parentPropId = $pid;
                    break;
                }
            }
        }

        $this->persistOwnPropId($fileIndexingId, $ownPropId, $parentPropId);

        return $parentPropId;
    }
}
