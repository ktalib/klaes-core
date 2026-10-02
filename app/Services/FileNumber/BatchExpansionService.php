<?php

namespace App\Services\FileNumber;

use Illuminate\Support\Facades\DB;

/**
 * Turns one batch row on the MLS File Commission list back into the files it stands for.
 *
 * The list collapses every file sharing a `mls_file_no.batch_no` into a single row, keeping
 * only the newest member (`ROW_NUMBER() ... ORDER BY fn.id DESC`, `WHERE group_rn = 1` in
 * FileNumberController::getData). The row is then LABELLED with a range — COM-2026-78-84 —
 * but carries the id of just one file, COM-2026-84. Edit and Delete used that id directly,
 * so both acted on one file out of seven with nothing on screen saying so. 3,533 files sit
 * inside 141 such rows.
 *
 * Print never had this problem: it already passes `batch_no` through to
 * MlsFileNoController::getBatchRecords(). This service is the equivalent for the write
 * paths — the same expansion, kept in one place so Edit and Delete cannot drift apart.
 *
 * Membership is resolved through `mls_file_no.batch_no` -> `fileNumber.mlsfNo`. Each member
 * keeps its OWN `tracking_id` and `full_file_number` (verified across every batch in the
 * database), which is why the existing per-file cascade in
 * FileNumberController::cascadeDeleteFileRecord() is correct in isolation and a batch
 * delete is a loop over it rather than new cascade logic.
 */
class BatchExpansionService
{
    /**
     * The `fileNumber` rows belonging to a batch, oldest first.
     *
     * Ordering is by id ascending so callers report ranges the way the list draws them
     * (first file .. last file) rather than in the reverse order the grouping uses.
     *
     * @return \Illuminate\Support\Collection<int, object>
     */
    public function members(?string $batchNo)
    {
        $batchNo = trim((string) $batchNo);

        if ($batchNo === '') {
            return collect();
        }

        $db = DB::connection('sqlsrv');

        $fileNumbers = $db->table('mls_file_no')
            ->where('batch_no', $batchNo)
            ->where(function ($q) {
                $q->whereNull('is_deleted')->orWhere('is_deleted', 0);
            })
            ->pluck('full_file_number')
            ->filter(fn ($v) => trim((string) $v) !== '')
            ->values();

        if ($fileNumbers->isEmpty()) {
            return collect();
        }

        return $db->table('fileNumber')
            ->whereIn('mlsfNo', $fileNumbers->all())
            ->where(function ($q) {
                $q->whereNull('is_deleted')->orWhere('is_deleted', 0);
            })
            ->orderBy('id')
            ->get();
    }

    /**
     * Member ids only.
     *
     * @return array<int, int>
     */
    public function memberIds(?string $batchNo): array
    {
        return $this->members($batchNo)->pluck('id')->map(fn ($v) => (int) $v)->all();
    }

    /**
     * How many files a batch holds. 0 means the batch number resolved to nothing.
     */
    public function count(?string $batchNo): int
    {
        return $this->members($batchNo)->count();
    }

    /**
     * The batch a given fileNumber row belongs to, or '' when it is a standalone file.
     *
     * Used to verify a client-supplied `batch_no` really does contain the record being
     * edited — a batch number is a free parameter on the request, and expanding an
     * unrelated batch would write this file's values over someone else's.
     */
    public function batchNoFor($record): string
    {
        $mlsfNo = trim((string) ($record->mlsfNo ?? ''));

        if ($mlsfNo === '') {
            return '';
        }

        $row = DB::connection('sqlsrv')
            ->table('mls_file_no')
            ->where('full_file_number', $mlsfNo)
            ->where(function ($q) {
                $q->whereNull('is_deleted')->orWhere('is_deleted', 0);
            })
            ->orderByDesc('id')
            ->first(['batch_no']);

        return trim((string) ($row->batch_no ?? ''));
    }

    /**
     * Resolve the batch a write should apply to, refusing anything the record is not in.
     *
     * @return array{ok: bool, message: string, members: \Illuminate\Support\Collection}
     */
    public function resolveFor($record, ?string $requestedBatchNo): array
    {
        $requested = trim((string) $requestedBatchNo);
        $actual    = $this->batchNoFor($record);

        if ($actual === '') {
            return [
                'ok'      => false,
                'message' => 'This file is not part of a batch.',
                'members' => collect(),
            ];
        }

        // Trust the record, not the request. A mismatched batch number means a stale page
        // or a hand-built request; either way, expanding it would touch the wrong files.
        if ($requested !== '' && strcasecmp($requested, $actual) !== 0) {
            return [
                'ok'      => false,
                'message' => 'This file does not belong to the batch supplied with the request.',
                'members' => collect(),
            ];
        }

        $members = $this->members($actual);

        if ($members->count() < 2) {
            return [
                'ok'      => false,
                'message' => 'This batch holds only one file.',
                'members' => $members,
            ];
        }

        return ['ok' => true, 'message' => '', 'members' => $members];
    }

    /**
     * "COM-2026-78 … COM-2026-84" for a confirmation message.
     */
    public function describeRange($members): string
    {
        $numbers = collect($members)
            ->map(fn ($m) => trim((string) ($m->mlsfNo ?? '')))
            ->filter()
            ->values();

        if ($numbers->isEmpty()) {
            return '';
        }

        if ($numbers->count() === 1) {
            return $numbers->first();
        }

        return $numbers->first() . ' … ' . $numbers->last();
    }
}
