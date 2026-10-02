<?php

namespace App\Services\Concerns;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The mechanics shared by every "push this file's facts out to the systems holding a
 * copy of them" path — FileIndexingPropagationService, where the File Indexing record
 * is the origin, and RecommendationPropagationService, where a recommendation edit is.
 *
 * The two rules below are the reason this is shared rather than written twice. They
 * are what keeps a propagation from destroying data, and a second copy of them would
 * drift from the first the moment either side is touched:
 *
 * 1. ONLY WHAT CHANGED. The payload is a diff, never a snapshot. A blanket push stomps
 *    values in the other systems that were deliberately different — those tables are
 *    not copies of the origin, they are separate records that happen to share facts.
 *
 * 2. NEVER A BLANK. A field cleared at the origin does not mean the other systems
 *    should lose their copy: they are usually populated from sources the origin never
 *    sees. See isBlankFact().
 */
trait PropagatesFileFacts
{
    /**
     * Column listings already read, as table => [lowercased column => true], plus a
     * false entry for a table that does not exist.
     *
     * These are legacy tables with no migrations behind them, so every write checks the
     * schema before it touches anything — and Schema::hasColumn() queries
     * INFORMATION_SCHEMA on EVERY call, with no cache of its own in Laravel 9. Six
     * targets of nine columns each, unmemoised, is over fifty schema round-trips per
     * record; across a 200-child batch that is tens of thousands of them inside the
     * batch transaction, which is a save that times out rather than one that fails
     * honestly. The schema cannot change mid-request, so it is read once.
     *
     * Static rather than per-instance: the callers resolve this service out of the
     * container per record, so an instance cache would be thrown away each time.
     */
    private static array $schemaCache = [];

    /**
     * Rule 2. A fact with no value propagates nothing — it is not an instruction to
     * erase the value the other systems hold.
     */
    protected function isBlankFact($value): bool
    {
        return $value === null || trim((string) $value) === '';
    }

    /**
     * Does $table exist, and does it have $column? One INFORMATION_SCHEMA read per
     * table per request, however many columns are asked about afterwards.
     */
    protected function tableHasColumn(string $table, string $column): bool
    {
        $columns = $this->columnsOf($table);

        return $columns !== false && isset($columns[strtolower($column)]);
    }

    protected function tableExists(string $table): bool
    {
        return $this->columnsOf($table) !== false;
    }

    /**
     * @return array<string,true>|false  false when the table is not there at all
     */
    private function columnsOf(string $table)
    {
        return self::$schemaCache[$table] ??= (
            Schema::connection('sqlsrv')->hasTable($table)
                ? array_fill_keys(
                    array_map('strtolower', Schema::connection('sqlsrv')->getColumnListing($table)),
                    true
                )
                : false
        );
    }

    /**
     * Write the changed facts to one target table.
     *
     * $map is keyed by fact name in the shared vocabulary ('district', 'plot_number',
     * 'file_title' …) and valued by the column, or columns, that table calls it.
     * Facts absent from $changes are not written, which is rule 1: a target only ever
     * receives what actually moved.
     *
     * Every table and column is checked before it is used. These are legacy SQL Server
     * tables with no migrations behind them, so the schema is discovered, not assumed —
     * a target missing a column is skipped rather than fatal.
     *
     * $scope narrows which rows are eligible beyond the file-number match — a target
     * whose reads are filtered (a soft-delete flag, a workflow type) needs its writes
     * filtered the same way, or the two stop describing the same set of rows.
     */
    protected function applyTo(string $table, array $map, array $changes, array $fileNumbers, array $keyColumns, ?callable $scope = null): int
    {
        if (empty($fileNumbers) || !$this->tableExists($table)) {
            return 0;
        }

        $payload = [];

        foreach ($map as $fact => $columns) {
            if (!array_key_exists($fact, $changes)) {
                continue;
            }

            foreach ((array) $columns as $column) {
                if ($this->tableHasColumn($table, $column)) {
                    $payload[$column] = $changes[$fact];
                }
            }
        }

        if (empty($payload)) {
            return 0;
        }

        $keyColumns = array_values(array_filter(
            $keyColumns,
            fn ($column) => $this->tableHasColumn($table, $column)
        ));

        if (empty($keyColumns)) {
            return 0;
        }

        if ($this->tableHasColumn($table, 'updated_at')) {
            $payload['updated_at'] = now();
        }

        $query = DB::connection('sqlsrv')
            ->table($table)
            ->where(function ($query) use ($keyColumns, $fileNumbers) {
                foreach ($keyColumns as $index => $column) {
                    $index === 0
                        ? $query->whereIn($column, $fileNumbers)
                        : $query->orWhereIn($column, $fileNumbers);
                }
            });

        if ($scope) {
            $scope($query);
        }

        return $query->update($payload);
    }

    /**
     * Every spelling of a file number that a target table might be holding it under.
     * "X(T)" and "X" are the same file recorded in different columns, so both are
     * asked for whichever one was supplied.
     */
    protected function fileNumberVariantsFrom(array $candidates): array
    {
        $variants = [];

        foreach ($candidates as $candidate) {
            $value = trim((string) ($candidate ?? ''));
            if ($value === '') {
                continue;
            }

            $variants[strtoupper($value)] = $value;

            $base = trim((string) preg_replace('/\(\s*T\s*\)\s*$/i', '', $value));
            if ($base !== '' && $base !== $value) {
                $variants[strtoupper($base)] = $base;
            } elseif ($base !== '') {
                $withTemp = $base . '(T)';
                $variants[strtoupper($withTemp)] = $withTemp;
            }
        }

        return array_values($variants);
    }

    protected function scalarize($value)
    {
        if (is_array($value)) {
            return $value === [] ? null : (is_scalar($value[0] ?? null) ? $value[0] : null);
        }

        if (is_string($value) && $value !== '' && ($value[0] === '[' || $value[0] === '{')) {
            $decoded = json_decode($value, true);
            if (is_array($decoded)) {
                return $this->scalarize($decoded);
            }
        }

        return is_scalar($value) ? trim((string) $value) : null;
    }

    protected function normalize($value): string
    {
        return strtoupper(trim((string) $value));
    }
}
