<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Single writer for the `related_file_number` register.
 *
 * The register is what Legal Search (LegalSearchService::relatedFileNumberRows) and the
 * Related File Numbers page read; `file_indexings.related_fileno` is only the JSON list on
 * the row itself. Before this service the two drifted: File Indexing wrote the JSON column
 * and nothing else, and MLS commissioning registered only the *typed* related-file widget,
 * so a plain related number never reached the register at all.
 *
 * Conventions this class enforces (they match the rows already in the table):
 *   - one row per related file number, holding the bare number, never a JSON array
 *   - keyed by (source_table, source_id, related_fileno); there is no unique index on the
 *     live table (486 source_ids legitimately carry several rows), so uniqueness is kept here
 *   - best-effort: every failure is logged and swallowed. A missing link must never roll back
 *     a saved indexing record or an issued file number.
 */
class RelatedFileNumberRegistrar
{
    public const TABLE = 'related_file_number';

    /** Type written when the caller has no relationship type to offer. */
    public const DEFAULT_TYPE = 'Related File';

    /**
     * Types this service is allowed to prune. Everything else (Mother File, Subdivision,
     * KANGIS Recertification, Re-grant, Resettlement) was written by a workflow that owns
     * its own edge, and dropping it because an operator edited the related-file list on the
     * indexing form would silently delete lineage another module depends on.
     */
    private const PRUNABLE_TYPES = ['', 'other', 'related file'];

    /**
     * Upsert the register rows for one indexing record.
     *
     * @param  array<int,string|array> $related  bare numbers, or ['file_no' => .., 'type' => ..]
     * @param  array $options  prune: the caller confirmed $related is the COMPLETE list, so
     *         generic rows no longer in it may be removed. Off by default: a partial list must
     *         never delete anything. default_type, location are passthrough column values.
     */
    public function sync(
        ?int $sourceId,
        ?string $fileNumber,
        ?string $fileTitle,
        $propId,
        array $related,
        array $options = [],
        string $sourceTable = 'file_indexings'
    ): void {
        // source_id is NOT NULL: with no anchor row there is nothing to register.
        if (empty($sourceId)) {
            return;
        }

        $prune       = (bool) ($options['prune'] ?? false);
        $defaultType = (string) ($options['default_type'] ?? self::DEFAULT_TYPE);
        $location    = $options['location'] ?? null;

        try {
            if (!Schema::connection('sqlsrv')->hasTable(self::TABLE)) {
                return;
            }

            $wanted = $this->normalizeInput($related, $defaultType);

            if (empty($wanted) && !$prune) {
                return;
            }

            $conn = DB::connection('sqlsrv');
            $now  = now();

            $existing = $conn->table(self::TABLE)
                ->where('source_table', $sourceTable)
                ->where('source_id', (int) $sourceId)
                ->get(['id', 'related_fileno', 'transaction_type']);

            // Index the existing rows by normalized number so a re-save updates in place
            // instead of stacking duplicates.
            $byKey = [];
            // A KANGIS land row lists several numbers ("KNGP 10, KNGP 7"): index each one.
            foreach ($existing as $row) {
                foreach ($this->listItems($row->related_fileno) as $item) {
                    $byKey[$this->key($item)][] = $row;
                }
            }

            $propIdValue = ($propId !== null && $propId !== '') ? (string) $propId : null;

            foreach ($wanted as $key => $item) {
                $shared = [
                    'prop_id'     => $propIdValue,
                    'file_number' => $this->clip($fileNumber, 255),
                    'file_title'  => $this->clip($fileTitle, 500),
                    'location'    => $this->clip($location, 500),
                    'updated_at'  => $now,
                ];

                if (isset($byKey[$key])) {
                    $row = $byKey[$key][0];

                    // Only promote the type when this caller actually knows one: a generic
                    // re-save must not overwrite "Mother File" with "Related File".
                    if ($this->isGeneric($row->transaction_type) && !$this->isGeneric($item['type'])) {
                        $shared['transaction_type'] = $this->clip($item['type'], 60);
                        $shared['comment']          = $this->clip($item['comment'], 500);
                    }

                    $conn->table(self::TABLE)->where('id', $row->id)->update($shared);
                    continue;
                }

                $conn->table(self::TABLE)->insert($shared + [
                    'related_fileno'   => $this->clip($item['file_no'], 500),
                    'source_table'     => $sourceTable,
                    'source_id'        => (int) $sourceId,
                    'transaction_type' => $this->clip($item['type'], 60),
                    'comment'          => $this->clip($item['comment'], 500),
                    'created_at'       => $now,
                ]);
            }

            if ($prune) {
                $this->prune($conn, $existing, array_keys($wanted));
            }
        } catch (\Throwable $e) {
            Log::warning('RelatedFileNumberRegistrar::sync failed', [
                'source_table' => $sourceTable,
                'source_id'    => $sourceId,
                'file_number'  => $fileNumber,
                'error'        => $e->getMessage(),
            ]);
        }
    }

    /**
     * Drop the generic rows that are no longer in the submitted list. Typed edges owned by
     * other workflows are left alone: see PRUNABLE_TYPES.
     */
    private function prune($conn, $existing, array $keptKeys): void
    {
        $kept = array_flip($keptKeys);
        $ids  = [];

        foreach ($existing as $row) {
            if (array_filter($this->listItems($row->related_fileno), fn ($item) => isset($kept[$this->key($item)]))) {
                continue;
            }
            if (!$this->isGeneric($row->transaction_type)) {
                continue;
            }
            $ids[] = $row->id;
        }

        if (!empty($ids)) {
            $conn->table(self::TABLE)->whereIn('id', $ids)->delete();
        }
    }

    /**
     * Flatten the caller's list into [normalized key => [file_no, type, comment]], dropping
     * blanks and duplicates. Accepts bare strings, the ['file_no' => .., 'type' => ..] shape
     * used by the commissioning forms, and a JSON-encoded array (the raw column value).
     *
     * @param  array<int,string|array> $related
     * @return array<string,array>
     */
    public function normalizeInput(array $related, string $defaultType = self::DEFAULT_TYPE): array
    {
        $out = [];

        foreach ($related as $entry) {
            if (is_array($entry)) {
                $fileNo  = trim((string) ($entry['file_no'] ?? $entry['file_number'] ?? ''));
                $type    = trim((string) ($entry['type'] ?? '')) ?: $defaultType;
                $comment = $entry['comment'] ?? null;
            } else {
                $fileNo  = trim((string) $entry);
                $type    = $defaultType;
                $comment = null;
            }

            if ($fileNo === '') {
                continue;
            }

            // A raw related_fileno column value arrives as '["RES-2024-10"]'.
            if ($fileNo[0] === '[') {
                $decoded = json_decode($fileNo, true);
                if (is_array($decoded)) {
                    foreach ($this->normalizeInput($decoded, $type) as $k => $v) {
                        $out[$k] = $v;
                    }
                    continue;
                }
            }

            $key = $this->key($fileNo);
            if ($key === '' || isset($out[$key])) {
                continue;
            }

            $out[$key] = [
                'file_no' => $fileNo,
                'type'    => $type,
                'comment' => $comment,
            ];
        }

        return $out;
    }

    /** Match key: case- and whitespace-insensitive, the way the register is read back. */
    /**
     * KANGIS Registry save (user, 2026-10-20): a land file that is already in the register
     * gets no new row. Its row (File Number = the land file) lists the KANGIS files related
     * to it — "KNGP 10, KNGP 7, KNGP 9" — and this KANGIS file is added to that list. With no
     * row yet, one is made for the land file. A KANGIS record that already has its own row
     * for the land file (written before this rule) is left as it is. Land files dropped from the record ($removedLandNos)
     * lose this KANGIS number from their list. Best-effort, like sync().
     *
     * @param  string[]  $landNos
     * @param  string[]  $removedLandNos
     */
    public function attachKangis(string $kangisNo, int $kangisId, array $landNos, array $removedLandNos = []): void
    {
        $kangisNo = trim($kangisNo);
        if ($kangisNo === '' || !$kangisId) {
            return;
        }
        try {
            if (!Schema::connection('sqlsrv')->hasTable(self::TABLE)) {
                return;
            }
            $conn = DB::connection('sqlsrv');
            $now = now();

            foreach ($this->uniqueNumbers($landNos) as $landNo) {
                $conn->transaction(function () use ($conn, $now, $kangisNo, $kangisId, $landNo) {
                    // This KANGIS record already has its own row for the land file (written before
                    // this rule): left as it is (user, 2026-10-20).
                    $ownRow = $conn->table(self::TABLE)
                        ->where('source_table', 'file_indexings')
                        ->where('source_id', $kangisId)
                        ->whereRaw("REPLACE(UPPER(LTRIM(RTRIM(related_fileno))), ' ', '') = ?", [$this->compact($landNo)])
                        ->exists();
                    if ($ownRow) {
                        return;
                    }
                    $row = $this->landRow($conn, $landNo);
                    if ($row) {
                        $items = $this->listItems($row->related_fileno);
                        if (!array_filter($items, fn ($i) => $this->compact($i) === $this->compact($kangisNo))) {
                            $items[] = $kangisNo;
                            $conn->table(self::TABLE)->where('id', $row->id)->update([
                                'related_fileno' => $this->clip(implode(', ', $items), 500),
                                'updated_at' => $now,
                            ]);
                        }
                    } else {
                        $land = $conn->table('file_indexings')
                            ->whereRaw("REPLACE(UPPER(LTRIM(RTRIM(file_number))), ' ', '') = ?", [$this->compact($landNo)])
                            ->where(fn ($q) => $q->whereNull('is_deleted')->orWhere('is_deleted', 0))
                            ->where(fn ($q) => $q->whereNull('general_registry')->orWhere('general_registry', '!=', 'KANGIS Registry'))
                            ->orderBy('id')
                            ->first(['id', 'file_number', 'file_title', 'prop_id', 'location']);
                        $conn->table(self::TABLE)->insert([
                            'related_fileno' => $this->clip($kangisNo, 500),
                            'prop_id' => $land && $land->prop_id !== null ? (string) $land->prop_id : null,
                            'source_table' => 'file_indexings',
                            'source_id' => $land ? (int) $land->id : $kangisId,
                            'file_number' => $this->clip($land->file_number ?? $landNo, 255),
                            'file_title' => $this->clip($land->file_title ?? null, 500),
                            'location' => $this->clip($land->location ?? null, 500),
                            'transaction_type' => self::DEFAULT_TYPE,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ]);
                    }
                });
            }

            if ($removedLandNos) {
                $this->detachKangis($kangisNo, $removedLandNos);
            }
        } catch (\Throwable $e) {
            Log::warning('RelatedFileNumberRegistrar::attachKangis failed', [
                'kangis' => $kangisNo,
                'land_files' => $landNos,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Take a KANGIS number off its land files' rows (land file removed from the KANGIS
     * record, or the KANGIS record deleted). A row left with nothing in it is removed.
     *
     * @param  string[]  $landNos
     */
    public function detachKangis(string $kangisNo, array $landNos): void
    {
        try {
            $conn = DB::connection('sqlsrv');
            foreach ($this->uniqueNumbers($landNos) as $landNo) {
                $row = $this->landRow($conn, $landNo);
                if (!$row) {
                    continue;
                }
                $items = $this->listItems($row->related_fileno);
                $kept = array_values(array_filter($items, fn ($i) => $this->compact($i) !== $this->compact($kangisNo)));
                if (count($kept) === count($items)) {
                    continue;
                }
                if ($kept) {
                    $conn->table(self::TABLE)->where('id', $row->id)->update([
                        'related_fileno' => $this->clip(implode(', ', $kept), 500),
                        'updated_at' => now(),
                    ]);
                } else {
                    $conn->table(self::TABLE)->where('id', $row->id)->delete();
                }
            }
        } catch (\Throwable $e) {
            Log::warning('RelatedFileNumberRegistrar::detachKangis failed', [
                'kangis' => $kangisNo,
                'land_files' => $landNos,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /** The land file's own generic row (File Number = the land file), oldest first. */
    private function landRow($conn, string $landNo): ?object
    {
        return $conn->table(self::TABLE)
            ->whereRaw("REPLACE(UPPER(LTRIM(RTRIM(file_number))), ' ', '') = ?", [$this->compact($landNo)])
            ->where(fn ($q) => $q->whereNull('transaction_type')->orWhereIn(DB::raw('LOWER(LTRIM(RTRIM(transaction_type)))'), ['', 'other', 'related file']))
            ->orderByRaw("CASE WHEN source_table = 'file_indexings' THEN 0 ELSE 1 END")
            ->orderBy('id')
            ->get(['id', 'related_fileno'])
            // Only a row listing KANGIS numbers only: a land-to-land link is never mixed in.
            ->first(fn ($r) => $this->listItems($r->related_fileno)
                && !array_filter($this->listItems($r->related_fileno), fn ($i) => !preg_match('/^(KNML|MLKN|MNKL|KNGP|KN)\s*\d/i', trim($i))));
    }

    /** "KNGP 10, KNGP 7" -> ['KNGP 10', 'KNGP 7']; a single number -> [it]. */
    private function listItems(?string $value): array
    {
        return array_values(array_filter(array_map('trim', explode(',', (string) $value)), fn ($v) => $v !== ''));
    }

    /** @return string[] */
    private function uniqueNumbers(array $numbers): array
    {
        $out = [];
        foreach ($numbers as $n) {
            $n = trim((string) $n);
            if ($n !== '') {
                $out[$this->compact($n)] = $n;
            }
        }

        return array_values($out);
    }

    private function compact(?string $fileNo): string
    {
        return strtoupper(preg_replace('/\s+/', '', trim((string) $fileNo)));
    }

    private function key(?string $fileNo): string
    {
        return strtoupper(preg_replace('/\s+/', ' ', trim((string) $fileNo)));
    }

    private function isGeneric(?string $type): bool
    {
        return in_array(strtolower(trim((string) $type)), self::PRUNABLE_TYPES, true);
    }

    private function clip(?string $value, int $max): ?string
    {
        $value = ($value === null) ? null : trim($value);

        return ($value === null || $value === '') ? null : mb_substr($value, 0, $max);
    }
}
