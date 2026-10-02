<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Resolves the third level of the parcel lineage cascade:
 *
 *     Ancestral PropID
 *       └── Parent PropID
 *             └── PropID
 *
 * Merged / subdivided / consolidated files can run deeper than two generations.
 * `parent_prop_id` records only the immediate predecessor, so the oldest
 * generation had to be recovered by walking the chain at read time
 * (LegalSearchService::resolveAncestorPropIds). This service computes the ROOT of
 * that chain so it can be stored on `ancestral_prop_id` and queried directly.
 *
 * Rules:
 *   - "Ancestral" is the OLDEST generation, not strictly the grandparent. A file
 *     with a single generation above it therefore has ancestral == parent; a
 *     three-deep file has ancestral == its great-grandparent's prop_id.
 *   - A prop_id with no parent is itself a root and stays NULL — the column never
 *     points a row at its own prop_id.
 *   - The chain is walked upward through file_indexings.parent_prop_id, which is
 *     the authoritative lineage index. A row's OWN parent_prop_id is preferred as
 *     the first hop when it has one (pra carries OP->ToT lineage that
 *     file_indexings does not).
 *   - Bounded depth (the same cap of 6 LegalSearchService uses) with a
 *     visited-set cycle guard, so bad data can never spin.
 *   - Where a row lists several comma-separated parents (a true merger), the
 *     first resolvable line wins — ancestral_prop_id is a single scalar by design.
 *   - Fully fail-open: any DB error resolves to null, never an exception.
 */
class PropIdLineageService
{
    /** Matches the depth cap in LegalSearchService::resolveAncestorPropIds(). */
    private const MAX_DEPTH = 6;

    /**
     * Tables carrying ancestral_prop_id, and the column identifying each row.
     * `fileNumber` has no prop_id column at all — its lineage is recorded solely
     * as parent_prop_id, which is why the key differs per table.
     */
    public const LINEAGE_TABLES = [
        'file_indexings' => 'id',
        'fileNumber' => 'id',
        'pra' => 'id',
        'CofO_staging' => 'id',
    ];

    /** prop_id => parent_prop_id raw string, memoised per request/run. */
    private array $parentCache = [];

    /** prop_id => resolved root prop_id (or null), memoised per request/run. */
    private array $resolvedCache = [];

    /**
     * True once warmParentMap() has loaded EVERY parent link, so a prop_id absent
     * from $parentCache is known to be a root and needs no query.
     */
    private bool $parentMapComplete = false;

    /**
     * Load every parent_prop_id link in one query.
     *
     * Only a small fraction of rows have a parent at all, so the whole lineage
     * graph fits comfortably in memory — and it turns the per-row chain walk from
     * a DB round-trip into a hash lookup. Backfills over hundreds of thousands of
     * rows depend on this; ordinary request-time callers can ignore it.
     */
    public function warmParentMap(): void
    {
        if ($this->parentMapComplete) {
            return;
        }

        try {
            DB::connection('sqlsrv')->table('file_indexings')
                ->whereNull('deleted_at')
                ->whereNotNull('prop_id')
                ->whereNotNull('parent_prop_id')
                ->where('parent_prop_id', '<>', '')
                ->orderBy('id')
                ->select(['prop_id', 'parent_prop_id'])
                ->chunk(5000, function ($rows) {
                    foreach ($rows as $row) {
                        $key = trim((string) $row->prop_id);
                        if ($key !== '' && !array_key_exists($key, $this->parentCache)) {
                            $this->parentCache[$key] = $row->parent_prop_id;
                        }
                    }
                });

            $this->parentMapComplete = true;
        } catch (\Throwable $e) {
            // Fail-open: fall back to per-prop_id lookups.
            Log::warning('PropIdLineageService: parent map warm failed', ['error' => $e->getMessage()]);
        }
    }

    /**
     * The root prop_id ABOVE the given prop_id, or null when it is already a root.
     */
    public function resolveAncestralPropId(?string $propId): ?string
    {
        $propId = trim((string) $propId);
        if ($propId === '') {
            return null;
        }

        if (array_key_exists($propId, $this->resolvedCache)) {
            return $this->resolvedCache[$propId];
        }

        $ancestral = $this->walkToRoot($propId, [$propId => true]);

        $this->resolvedCache[$propId] = $ancestral;

        return $ancestral;
    }

    /**
     * The row-level rule: derive ancestral_prop_id from what a row actually records.
     *
     * Prefers the row's own parent_prop_id as the first hop (authoritative for pra
     * OP->ToT and merger lineage), then keeps climbing. Falls back to walking from
     * the row's prop_id when it records no parent of its own.
     */
    public function resolveAncestralForRow(?string $propId, ?string $parentPropId): ?string
    {
        $parents = $this->splitPropIds($parentPropId);

        if (!empty($parents)) {
            $first = $parents[0];
            // The root above the parent — or the parent itself when it is the root.
            return $this->resolveAncestralPropId($first) ?? $first;
        }

        return $this->resolveAncestralPropId($propId);
    }

    /**
     * Recompute and persist ancestral_prop_id for specific rows of one table.
     *
     * @param  string $table  One of LINEAGE_TABLES
     * @param  array  $ids    Row ids (the table's key column)
     * @return int Rows written
     */
    public function syncAncestralFor(string $table, array $ids): int
    {
        $key = $this->keyColumn($table);

        if (empty($ids) || !$this->tableIsReady($table)) {
            return 0;
        }

        $conn = DB::connection('sqlsrv');
        $hasPropId = Schema::connection('sqlsrv')->hasColumn($table, 'prop_id');
        $columns = array_values(array_filter([$key, $hasPropId ? 'prop_id' : null, 'parent_prop_id']));

        $written = 0;

        foreach (array_chunk(array_values(array_unique($ids)), 500) as $chunk) {
            try {
                $rows = $conn->table($table)->whereIn($key, $chunk)->get($columns);
            } catch (\Throwable $e) {
                Log::warning('PropIdLineageService: ancestral read failed', [
                    'table' => $table,
                    'error' => $e->getMessage(),
                ]);
                continue;
            }

            foreach ($rows as $row) {
                $ancestral = $this->resolveAncestralForRow(
                    $hasPropId ? ($row->prop_id ?? null) : null,
                    $row->parent_prop_id ?? null
                );

                try {
                    $written += $conn->table($table)
                        ->where($key, $row->{$key})
                        ->update(['ancestral_prop_id' => $ancestral]);
                } catch (\Throwable $e) {
                    Log::warning('PropIdLineageService: ancestral write failed', [
                        'table' => $table,
                        'key' => $key . '=' . $row->{$key},
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        return $written;
    }

    /**
     * Write ancestral_prop_id for a single row identified by any column.
     * Used by the index/link write paths so newly-linked files are correct
     * immediately, without waiting for the next backfill.
     */
    public function stampAncestralOnRow(
        string $table,
        string $keyColumn,
        $keyValue,
        ?string $propId,
        ?string $parentPropId = null
    ): bool {
        if (!array_key_exists($table, self::LINEAGE_TABLES) || !$this->tableIsReady($table)) {
            return false;
        }

        try {
            return DB::connection('sqlsrv')
                ->table($table)
                ->where($keyColumn, $keyValue)
                ->update(['ancestral_prop_id' => $this->resolveAncestralForRow($propId, $parentPropId)]) > 0;
        } catch (\Throwable $e) {
            Log::warning('PropIdLineageService: ancestral stamp failed', [
                'table' => $table,
                'key' => $keyColumn . '=' . $keyValue,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * True when the table exists and has been migrated with ancestral_prop_id.
     * Keeps every caller safe on an un-migrated database.
     */
    public function tableIsReady(string $table): bool
    {
        try {
            return Schema::connection('sqlsrv')->hasTable($table)
                && Schema::connection('sqlsrv')->hasColumn($table, 'ancestral_prop_id')
                && Schema::connection('sqlsrv')->hasColumn($table, 'parent_prop_id');
        } catch (\Throwable $e) {
            return false;
        }
    }

    public function keyColumn(string $table): string
    {
        if (!array_key_exists($table, self::LINEAGE_TABLES)) {
            throw new \InvalidArgumentException("Invalid lineage table: {$table}");
        }

        return self::LINEAGE_TABLES[$table];
    }

    /** Drop memoised lookups. Keeps a warmed parent map intact — that is the point of it. */
    public function flushCache(bool $includeParentMap = false): void
    {
        $this->resolvedCache = [];

        if ($includeParentMap) {
            $this->parentCache = [];
            $this->parentMapComplete = false;
        }
    }

    /**
     * Climb parent_prop_id links until the top, returning the last prop_id reached
     * (null when the starting point has no parent at all).
     */
    private function walkToRoot(string $start, array $visited): ?string
    {
        $ancestral = null;
        $current = $start;

        for ($depth = 0; $depth < self::MAX_DEPTH; $depth++) {
            $next = null;
            foreach ($this->splitPropIds($this->lookupParent($current)) as $candidate) {
                if (!isset($visited[$candidate])) {
                    $next = $candidate;
                    break;
                }
            }

            if ($next === null) {
                break; // top of the chain (or a cycle)
            }

            $visited[$next] = true;
            $ancestral = $next;
            $current = $next;
        }

        return $ancestral;
    }

    /**
     * file_indexings is the authoritative lineage index for a prop_id's parent.
     */
    private function lookupParent(string $propId): ?string
    {
        if (array_key_exists($propId, $this->parentCache)) {
            return $this->parentCache[$propId];
        }

        // With the full map loaded, an absent prop_id is definitively a root.
        if ($this->parentMapComplete) {
            return null;
        }

        $parent = null;

        try {
            $parent = DB::connection('sqlsrv')->table('file_indexings')
                ->where('prop_id', $propId)
                ->whereNull('deleted_at')
                ->value('parent_prop_id');
        } catch (\Throwable $e) {
            $parent = null; // fail-open: treat as a root
        }

        $this->parentCache[$propId] = $parent;

        return $parent;
    }

    /** Same comma-separated semantics LegalSearchService::splitPropIds() uses. */
    private function splitPropIds($raw): array
    {
        $out = [];
        foreach (explode(',', (string) $raw) as $p) {
            $p = trim($p);
            if ($p !== '') {
                $out[] = $p;
            }
        }

        return $out;
    }
}
