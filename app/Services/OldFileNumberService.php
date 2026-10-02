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
