<?php

namespace App\Services;

use App\Models\OldFileNumber;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * The single writer for a file's old (previous / duplicated) file number.
 *
 * Two screens feed it, both on the MLPP File Number Generator:
 *   - Re-Issuance of FileNo  -> "Old FileNo (Duplicate)" on the generate form
 *   - the list's Edit modal  -> the "Old File Number" checkbox
 *
 * Each call writes three places and keeps them consistent:
 *   1. old_file_numbers          - the ledger, every old number the file ever carried
 *   2. mls_file_no.old_fileno    - current value, what the Edit modal reads back
 *   3. file_indexings.old_fileno - current value, for indexing screens and search
 *
 * Nothing here throws into the caller's transaction: an old number is descriptive
 * metadata, and failing to record it must never abort a file-number generation.
 */
class OldFileNumberService
{
    public const LABEL_OLD       = 'Old FileNo';
    public const LABEL_DUPLICATE = 'Old FileNo (Duplicate FileNo)';

    /**
     * Sources that make the number a true duplicate, not just a previous one. The other
     * tests are the file's own type, Re-Issuance of FileNo (what the listing's DUP badge
     * and the commissioning sheet read), and the number sitting in the Duplicate Files
     * register (duplicate_fileno), which is where most of these numbers come from.
     */
    private const DUPLICATE_SOURCES = [OldFileNumber::SOURCE_REISSUANCE, OldFileNumber::SOURCE_MOVE];

    /**
     * Record an old file number for a live file. Returns the ledger row id, or null
     * when there was nothing to record.
     */
    public function record(
        ?string $fileNumber,
        ?string $oldFileNumber,
        string $source = OldFileNumber::SOURCE_MANUAL,
        ?string $oldFileTitle = null,
        ?int $createdBy = null
    ): ?int {
        $fileNumber    = $this->normalize($fileNumber);
        $oldFileNumber = $this->normalize($oldFileNumber);

        if ($fileNumber === '' || $oldFileNumber === '') {
            return null;
        }

        // A file cannot be its own predecessor; guards against a picker that
        // echoes back the number being generated.
        if (strcasecmp($fileNumber, $oldFileNumber) === 0) {
            return null;
        }

        try {
            $indexingId = $this->resolveFileIndexingId($fileNumber);

            $row = OldFileNumber::on('sqlsrv')
                ->where('file_number', $fileNumber)
                ->where('old_file_number', $oldFileNumber)
                ->first();

            if ($row) {
                $row->source = $source;

                if ($oldFileTitle !== null && trim($oldFileTitle) !== '') {
                    $row->old_file_title = trim($oldFileTitle);
                }

                if ($indexingId !== null) {
                    $row->file_indexing_id = $indexingId;
                }

                $row->save();
            } else {
                $row = OldFileNumber::on('sqlsrv')->create([
                    'file_number'      => $fileNumber,
                    'old_file_number'  => $oldFileNumber,
                    'old_file_title'   => $oldFileTitle !== null && trim($oldFileTitle) !== '' ? trim($oldFileTitle) : null,
                    'source'           => $source,
                    'file_indexing_id' => $indexingId,
                    'created_by'       => $createdBy,
                ]);
            }

            $this->mirror($fileNumber, $oldFileNumber);

            return (int) $row->id;
        } catch (\Throwable $e) {
            Log::warning('OldFileNumberService::record failed', [
                'file_number'     => $fileNumber,
                'old_file_number' => $oldFileNumber,
                'source'          => $source,
                'error'           => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Drop the current old-number mirrors for a file - used when the Edit modal's
     * "Old File Number" box is unticked, or the value is cleared.
     *
     * The ledger rows are deliberately LEFT IN PLACE: unticking the box on one
     * screen is not a statement that the file never carried that number. Pass
     * $purgeLedger only for a genuine correction of a wrong entry.
     */
    public function clear(?string $fileNumber, bool $purgeLedger = false): void
    {
        $fileNumber = $this->normalize($fileNumber);

        if ($fileNumber === '') {
            return;
        }

        try {
            if ($purgeLedger) {
                OldFileNumber::on('sqlsrv')->where('file_number', $fileNumber)->delete();
            }

            $this->mirror($fileNumber, null);
        } catch (\Throwable $e) {
            Log::warning('OldFileNumberService::clear failed', [
                'file_number' => $fileNumber,
                'error'       => $e->getMessage(),
            ]);
        }
    }

    /**
     * Every old number recorded against a file, newest first.
     *
     * @return \Illuminate\Support\Collection
     */
    public function historyFor(?string $fileNumber)
    {
        $fileNumber = $this->normalize($fileNumber);

        if ($fileNumber === '') {
            return collect();
        }

        return OldFileNumber::on('sqlsrv')
            ->where('file_number', $fileNumber)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();
    }

    /**
     * Reverse lookup: which live file replaced this old number?
     */
    public function currentFileNumberFor(?string $oldFileNumber): ?string
    {
        $oldFileNumber = $this->normalize($oldFileNumber);

        if ($oldFileNumber === '') {
            return null;
        }

        $row = OldFileNumber::on('sqlsrv')
            ->where('old_file_number', $oldFileNumber)
            ->orderByDesc('id')
            ->first();

        return $row->file_number ?? null;
    }

    /**
     * Quick Search: the live file(s) this number is an old number of. Reads the ledger
     * and mls_file_no.old_fileno together: numbers set before the ledger existed are
     * only on the column, so currentFileNumberFor() alone misses them.
     *
     * @return array<int,array{file_number:string,file_title:?string,is_duplicate:bool,label:string}>
     */
    public function currentFilesFor(?string $oldFileNumber): array
    {
        $oldFileNumber = $this->normalize($oldFileNumber);

        if ($oldFileNumber === '') {
            return [];
        }

        return array_map(fn ($pair) => [
            'file_number'  => $pair['file_number'],
            'file_title'   => $pair['file_title'],
            'is_duplicate' => $pair['is_duplicate'],
            'label'        => $pair['label'],
        ], $this->pairs(
            fn ($q) => $q->where('old_file_number', $oldFileNumber),
            fn ($q) => $q->where('old_fileno', $oldFileNumber)
        ));
    }

    /**
     * Quick Search: the old number(s) a live file carries, from the same two stores.
     *
     * @return array<int,array{file_number:string,file_title:?string,is_duplicate:bool,label:string}>
     */
    public function oldNumbersFor(?string $fileNumber): array
    {
        $fileNumber = $this->normalize($fileNumber);

        if ($fileNumber === '') {
            return [];
        }

        return array_map(fn ($pair) => [
            'file_number'  => $pair['old_file_number'],
            'file_title'   => $pair['old_file_title'],
            'is_duplicate' => $pair['is_duplicate'],
            'label'        => $pair['label'],
        ], $this->pairs(
            fn ($q) => $q->where('file_number', $fileNumber),
            fn ($q) => $q->where('full_file_number', $fileNumber)
        ));
    }

    /**
     * Old numbers matching what is typed into a file-number dropdown.
     *
     * @return array<int,array{file_number:string,old_file_number:string,file_title:?string,is_duplicate:bool,label:string}>
     */
    public function search(?string $term, int $limit = 10): array
    {
        $term = $this->normalize($term);

        if (mb_strlen($term) < 2) {
            return [];
        }

        $like = '%' . str_replace(['[', '%', '_'], ['[[]', '[%]', '[_]'], $term) . '%';

        return array_slice($this->pairs(
            fn ($q) => $q->where('old_file_number', 'like', $like),
            fn ($q) => $q->where('old_fileno', 'like', $like)
        ), 0, $limit);
    }

    /**
     * Every (live file, old number) pair the two scopes select. Read-only and quiet on
     * failure: recognising an old number must never break a search.
     */
    private function pairs(\Closure $ledgerScope, \Closure $mlsScope): array
    {
        try {
            $conn = DB::connection('sqlsrv');
            $pairs = [];

            $add = function (?string $file, ?string $old, ?string $title, bool $duplicate) use (&$pairs) {
                $file = trim((string) $file);
                $old  = trim((string) $old);
                if ($file === '' || $old === '' || strcasecmp($file, $old) === 0) {
                    return;
                }
                $key = strtoupper($file . '|' . $old);
                $pairs[$key] = [
                    'file_number'     => $pairs[$key]['file_number'] ?? $file,
                    'old_file_number' => $pairs[$key]['old_file_number'] ?? $old,
                    'old_file_title'  => $pairs[$key]['old_file_title'] ?? (trim((string) $title) ?: null),
                    'is_duplicate'    => $duplicate || ($pairs[$key]['is_duplicate'] ?? false),
                ];
            };

            $ledger = $ledgerScope(OldFileNumber::on('sqlsrv'))
                ->orderByDesc('id')
                ->get(['file_number', 'old_file_number', 'old_file_title', 'source']);
            foreach ($ledger as $row) {
                $add($row->file_number, $row->old_file_number, $row->old_file_title, in_array($row->source, self::DUPLICATE_SOURCES, true));
            }

            if (Schema::connection('sqlsrv')->hasColumn('mls_file_no', 'old_fileno')) {
                $columns = $mlsScope($conn->table('mls_file_no'))
                    ->whereNotNull('old_fileno')
                    ->where('old_fileno', '<>', '')
                    ->get(['full_file_number', 'old_fileno']);
                foreach ($columns as $row) {
                    $add($row->full_file_number, $row->old_fileno, null, false);
                }
            }

            if (empty($pairs)) {
                return [];
            }

            // Each live file's title and type. A file with only deleted rows no longer
            // answers to the number; a file with no row here keeps its ledger entry.
            $details = [];
            $files = array_values(array_unique(array_column($pairs, 'file_number')));
            foreach (array_chunk($files, 500) as $chunk) {
                $rows = $conn->table('mls_file_no')
                    ->whereIn('full_file_number', $chunk)
                    ->orderBy('id')
                    ->get(['full_file_number', 'file_name', 'file_option', 'is_deleted']);
                foreach ($rows as $row) {
                    $key = strtoupper(trim((string) $row->full_file_number));
                    $live = empty($row->is_deleted);
                    $details[$key] = [
                        'title'      => trim((string) $row->file_name) ?: ($details[$key]['title'] ?? null),
                        'reissuance' => strtolower(trim((string) $row->file_option)) === 'reissuance' || ($details[$key]['reissuance'] ?? false),
                        'live'       => $live || ($details[$key]['live'] ?? false),
                    ];
                }
            }

            $registered = [];
            $olds = array_values(array_unique(array_column($pairs, 'old_file_number')));
            foreach (array_chunk($olds, 500) as $chunk) {
                $rows = $conn->table('duplicate_fileno')
                    ->whereIn('file_number', $chunk)
                    ->whereRaw("UPPER(LTRIM(RTRIM(category))) = 'DUPLICATE FILES'")
                    ->pluck('file_number');
                foreach ($rows as $number) {
                    $registered[strtoupper(trim((string) $number))] = true;
                }
            }

            $out = [];
            foreach ($pairs as $pair) {
                $detail = $details[strtoupper($pair['file_number'])] ?? null;
                if ($detail && !$detail['live']) {
                    continue;
                }
                $pair['file_title'] = $detail['title'] ?? null;
                $pair['is_duplicate'] = $pair['is_duplicate']
                    || ($detail['reissuance'] ?? false)
                    || isset($registered[strtoupper($pair['old_file_number'])]);
                $pair['label'] = $pair['is_duplicate'] ? self::LABEL_DUPLICATE : self::LABEL_OLD;
                $out[] = $pair;
            }

            return $out;
        } catch (\Throwable $e) {
            Log::warning('OldFileNumberService lookup failed', ['error' => $e->getMessage()]);

            return [];
        }
    }

    /**
     * Push the current value onto both mirror columns. Each is guarded by
     * hasColumn() so a half-migrated environment degrades instead of erroring.
     */
    private function mirror(string $fileNumber, ?string $oldFileNumber): void
    {
        if (Schema::connection('sqlsrv')->hasColumn('mls_file_no', 'old_fileno')) {
            DB::connection('sqlsrv')
                ->table('mls_file_no')
                ->whereRaw('UPPER(LTRIM(RTRIM(full_file_number))) = UPPER(?)', [$fileNumber])
                ->update(['old_fileno' => $oldFileNumber]);
        }

        if (Schema::connection('sqlsrv')->hasColumn('file_indexings', 'old_fileno')) {
            DB::connection('sqlsrv')
                ->table('file_indexings')
                ->where(function ($q) use ($fileNumber) {
                    $q->whereRaw('UPPER(LTRIM(RTRIM(file_number))) = UPPER(?)', [$fileNumber]);

                    // "X(T)" and "X" are the same physical file held in different
                    // columns - a literal match on file_number alone misses temps.
                    if (Schema::connection('sqlsrv')->hasColumn('file_indexings', 'temp_file_no')) {
                        $q->orWhereRaw('UPPER(LTRIM(RTRIM(temp_file_no))) = UPPER(?)', [$fileNumber]);
                    }
                })
                ->update(['old_fileno' => $oldFileNumber]);
        }
    }

    /**
     * Resolved once at write time so the ledger row carries an auditable link
     * rather than a value re-derived on every read.
     */
    private function resolveFileIndexingId(string $fileNumber): ?int
    {
        $hasTemp = Schema::connection('sqlsrv')->hasColumn('file_indexings', 'temp_file_no');

        $id = DB::connection('sqlsrv')
            ->table('file_indexings')
            ->where(function ($q) use ($fileNumber, $hasTemp) {
                $q->whereRaw('UPPER(LTRIM(RTRIM(file_number))) = UPPER(?)', [$fileNumber]);

                if ($hasTemp) {
                    $q->orWhereRaw('UPPER(LTRIM(RTRIM(temp_file_no))) = UPPER(?)', [$fileNumber]);
                }
            })
            ->orderBy('id')
            ->value('id');

        return $id ? (int) $id : null;
    }

    private function normalize(?string $value): string
    {
        // Trailing spaces/hyphens come off the file-number pickers verbatim.
        return trim(preg_replace('/\s+/', ' ', (string) $value), " \t\n\r\0\x0B-");
    }
}
