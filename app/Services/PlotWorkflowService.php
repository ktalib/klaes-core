<?php

namespace App\Services;

use App\Models\DecommissionedFiles;
use App\Models\FileIndexing;
use App\Models\FileNumber;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

class PlotWorkflowService
{
    /**
     * Archive and flag retired files. Commissioning callers can request removal
     * of indexed parents from fileNumber, file_indexings, customers_staging and
     * entities_staging after archival, within the same transaction.
     *
     * Unindexed parents and other callers retain flag-only behaviour. Grouping
     * provenance and transaction/history tables are never deleted here.
     *
     * @param string|null $successorFileNo One successor number or a CSV list.
     * @param bool $removeIndexedParent Enable live-row removal for commissioning.
     */
    public function decommissionFiles(array $fileNumbers, string $reason, ?string $commissionedBy = null, ?string $successorFileNo = null, bool $removeIndexedParent = false): array
    {
        $summary = [
            'archived' => [],
            'deleted' => 0,
            'flagged' => 0,
            'removed_rows' => [],
            // fileNo => its existing decommissioned_files row (kept; not recorded again)
            'already_decommissioned' => [],
            'errors' => []
        ];

        $commissionedBy = $commissionedBy ?: (Auth::user() ? Auth::user()->first_name . ' ' . Auth::user()->last_name : 'System');

        foreach ($fileNumbers as $fileNo) {
            $connection = DB::connection('sqlsrv');
            $transactionLevel = $connection->transactionLevel();
            try {
                $connection->beginTransaction();
                // 1. Fetch records from all active tables. Match the file's OWN identifiers —
                //    its MLS number (mlsfNo / file_number) OR its KANGIS number (kangisFileNo /
                //    kangis_file_no). A KANGIS-only file (MLS = N/A, e.g. "MLKN 2455") stores its
                //    number in the KANGIS column, so an mlsfNo-only match would miss it and leave
                //    the live row behind after archiving. (Do NOT match new_kangis_file_no — that
                //    is a recert pointer to a SUCCESSOR file, not this file's own identity.)
                $fileRecord = DB::connection('sqlsrv')->table('fileNumber')
                    ->where(function ($q) use ($fileNo) {
                        $q->where('mlsfNo', $fileNo)->orWhere('kangisFileNo', $fileNo);
                    })->lockForUpdate()->first();
                $indexingRecord = DB::connection('sqlsrv')->table('file_indexings')
                    ->where(function ($q) use ($fileNo) {
                        $q->where('file_number', $fileNo)->orWhere('kangis_file_no', $fileNo);
                    })->lockForUpdate()->first();

                if (!$fileRecord && !$indexingRecord) {
                    $connection->rollBack($transactionLevel);
                    $summary['errors'][] = "File $fileNo not found in active records.";
                    continue;
                }

                $removeLiveRows = $removeIndexedParent && $indexingRecord !== null;
                if ($removeLiveRows) {
                    $successors = array_filter(array_map('trim', explode(',', (string) $successorFileNo)));
                    $ownNumbers = array_filter([$fileNo, $fileRecord->mlsfNo ?? null, $fileRecord->kangisFileNo ?? null,
                        $indexingRecord->file_number ?? null, $indexingRecord->kangis_file_no ?? null]);
                    if (!$successors || array_intersect(array_map('strtoupper', $successors), array_map('strtoupper', $ownNumbers))) {
                        throw new \RuntimeException('Parent removal requires a distinct commissioned successor.');
                    }
                }

                // 2. Insert into decommissioned_files
                $decommissionRow = [
                    'file_number_id' => (int) ($fileRecord->id ?? ($indexingRecord->id ?? 0)),
                    'file_no' => $fileNo,
                    'mls_file_no' => $fileNo,
                    'kangis_file_no' => $fileRecord->kangisFileNo ?? ($indexingRecord->kangis_file_no ?? null),
                    'new_kangis_file_no' => $fileRecord->NewKANGISFileNo ?? ($indexingRecord->new_kangis_file_no ?? null),
                    'file_name' => $fileRecord->FileName ?? ($indexingRecord->file_title ?? 'N/A'),
                    'commissioning_date' => $fileRecord->commissioning_date ?? null,
                    'decommissioning_date' => now(),
                    'decommissioning_reason' => $reason,
                    'decommissioned_by' => $commissionedBy,
                    'created_at' => now(),
                    'updated_at' => now()
                ];
                // Store the old -> successor pointer when the column exists (added 2026_07_03).
                if ($successorFileNo && Schema::connection('sqlsrv')->hasColumn('decommissioned_files', 'successor_file_no')) {
                    $decommissionRow['successor_file_no'] = $successorFileNo;
                }
                // Genuine KLAES parcel-update decommission — its File Decommissioning row shows the
                // real Date Decommissioned (event_type column added 2026_07_21).
                if (Schema::connection('sqlsrv')->hasColumn('decommissioned_files', 'event_type')) {
                    $decommissionRow['event_type'] = 'parcel_update_new';
                }
                // The locked live row serializes simultaneous attempts for this file.
                // A file already in decommissioned_files is never recorded a second time,
                // whichever workflow decommissions it again — a retry, a later chunk, or a
                // different parcel update on an already-retired file. Its first record
                // stands, and the live-row flags below are healed from that record so a
                // second workflow cannot overwrite the original reason / successor.
                $existingDecommission = $this->activeDecommissionRecord($fileNo);
                $alreadyArchived = $existingDecommission !== null;
                if (!$alreadyArchived) {
                    $connection->table('decommissioned_files')->insert($decommissionRow);
                    $flagReason    = $reason;
                    $flagBy        = $commissionedBy;
                    $flagSuccessor = $successorFileNo;
                } else {
                    $summary['already_decommissioned'][$fileNo] = $existingDecommission;
                    // "File Already Decommissioned Before: Yes" — this workflow's successors
                    // are added to that first record rather than written as a second one
                    // (user rule, 2026-10-22). Its reason and date stay as they were.
                    $flagSuccessor = $this->mergeSuccessorList(
                        $existingDecommission->successor_file_no ?? null,
                        $successorFileNo
                    );
                    if ($flagSuccessor !== (string) ($existingDecommission->successor_file_no ?? '')
                        && Schema::connection('sqlsrv')->hasColumn('decommissioned_files', 'successor_file_no')) {
                        $connection->table('decommissioned_files')
                            ->where('id', $existingDecommission->id)
                            ->update(['successor_file_no' => $flagSuccessor, 'updated_at' => now()]);
                        $summary['successors_appended'][$fileNo] = (int) $existingDecommission->id;
                    }
                    $flagReason    = (string) ($existingDecommission->decommissioning_reason ?? $reason);
                    $flagBy        = (string) ($existingDecommission->decommissioned_by ?? $commissionedBy);
                    $flagSuccessor = $flagSuccessor !== '' ? $flagSuccessor : null;
                    // Its live rows stay too. Removal is only for the first retirement, which
                    // archives the indexing row to deprecated_records first; here nothing would
                    // be archived, so removing them would lose the record — as happened to
                    // CON-AG-2010-110, deleted by a backfill linkage after its Change of Purpose.
                    $removeLiveRows = false;
                }

                // 2.5 Archive detailed indexing record to deprecated_records before deletion
                if ($indexingRecord && !$alreadyArchived) {
                    DB::connection('sqlsrv')->table('deprecated_records')->insert([
                        'file_indexing_id' => (int) ($indexingRecord->id ?? 0),
                        'file_number' => $indexingRecord->file_number ?? $fileNo,
                        'file_title' => $indexingRecord->file_title ?? null,
                        'land_use_type' => $indexingRecord->land_use_type ?? null,
                        'plot_number' => $indexingRecord->plot_number ?? null,
                        'district' => $indexingRecord->district ?? null,
                        'lga' => $indexingRecord->lga ?? null,
                        'location' => $indexingRecord->location ?? null,
                        'plot_size' => $indexingRecord->plot_size ?? null,
                        'tp_no' => $indexingRecord->tp_no ?? null,
                        'lpkn_no' => $indexingRecord->lpkn_no ?? null,
                        'tracking_id' => $indexingRecord->tracking_id ?? null,
                        'original_holder' => $indexingRecord->original_holder ?? null,
                        'current_holder' => $indexingRecord->current_holder ?? null,
                        'parent_prop_id' => $indexingRecord->parent_prop_id ?? null,
                        'related_fileno' => $indexingRecord->related_fileno ?? null,
                        'has_transaction' => $indexingRecord->has_transaction ?? 0,
                        'workflow_type' => $reason,
                        'decommissioned_by' => $commissionedBy,
                        'decommissioned_at' => now(),
                        'created_by' => $indexingRecord->created_by ?? null,
                        'updated_by' => $indexingRecord->updated_by ?? null,
                        'serial_no' => $indexingRecord->serial_no ?? null,
                        'batch_no' => $indexingRecord->batch_no ?? null,
                        'workflow_status' => $indexingRecord->workflow_status ?? null,
                        'registry' => $indexingRecord->registry ?? null,
                        'prop_id' => $indexingRecord->prop_id ?? null,
                        'phone' => $indexingRecord->phone ?? null,
                        'residence_address' => $indexingRecord->residence_address ?? null,
                        'general_registry' => $indexingRecord->general_registry ?? null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                // 3. Flag the live rows. NOTHING IS DELETED — a decommissioned file keeps its
                //    indexing detail, its customer/entity parties and its grouping placeholder,
                //    and carries the decommission attributes so every screen can badge it
                //    without joining back to decommissioned_files.
                //
                //    Mirror the lookup above so a KANGIS-only file is flagged by its KANGIS
                //    number too — otherwise the archive row is written but the live row stays
                //    unflagged and keeps surfacing as an active file.
                $flagged = 0;

                $flagged += $this->flagDecommissioned('fileNumber', function ($q) use ($fileNo) {
                    $q->where('mlsfNo', $fileNo)->orWhere('kangisFileNo', $fileNo);
                }, $flagReason, $flagBy, $flagSuccessor);

                $flagged += $this->flagDecommissioned('file_indexings', function ($q) use ($fileNo) {
                    $q->where('file_number', $fileNo)->orWhere('kangis_file_no', $fileNo);
                }, $flagReason, $flagBy, $flagSuccessor);

                // The commissioning register. Left unflagged, it went on reporting a
                // retired file as live — and it is where `source` is read from, so a
                // plot retired by a later Change of Purpose still counted as an active
                // Subdivision child. It holds the MLS number only; a KANGIS-only file
                // has no row here, which is why there is no second column to match on.
                $flagged += $this->flagDecommissioned('mls_file_no', function ($q) use ($fileNo) {
                    $q->where('full_file_number', $fileNo);
                }, $flagReason, $flagBy, $flagSuccessor);

                $flagged += $this->flagDecommissioned('entities_staging', function ($q) use ($fileNo) {
                    $q->where('file_number', $fileNo);
                }, $flagReason, $flagBy, $flagSuccessor);

                $flagged += $this->flagDecommissioned('customers_staging', function ($q) use ($fileNo) {
                    $q->where('file_number', $fileNo);
                }, $flagReason, $flagBy, $flagSuccessor);

                // Grouping placeholders are flagged, never removed: deleting any row from a
                // grouping table during decommissioning is forbidden, because the grouping
                // record is the file's provenance and outlives the file's active life.
                $flagged += $this->flagDecommissioned('kangis_grouping', function ($q) use ($fileNo) {
                    $q->where('kangis_fileno_placeholder', $fileNo);
                }, $flagReason, $flagBy, $flagSuccessor);

                // NOTE: We deliberately DO NOT delete the file's rows from the Legal Search staging
                // tables (file_history_staging, CofO_staging, pra, deed_registrations). Legal Search
                // relies on those rows to display the successor file's lineage/history via prop_id and
                // parent_prop_id expansion. Suppression of a decommissioned file when it is searched
                // directly is handled at query time in LegalSearchService (see getDecommissionedFileNumbers).

                $removedRows = $removeLiveRows
                    ? app(DecommissionedParentRemovalService::class)->remove($fileNo)
                    : [];

                $connection->commit();
                $summary['archived'][] = $fileNo;
                $summary['flagged'] += $flagged;
                foreach ($removedRows as $table => $count) {
                    $summary['removed_rows'][$table] = ($summary['removed_rows'][$table] ?? 0) + $count;
                }
                // Kept for callers that still read 'deleted'; nothing is deleted any more, so it
                // now counts files decommissioned rather than rows removed.
                $summary['deleted']++;

                $this->logPlotsWorkflow('info', "File decommissioned and flagged: $fileNo", [
                    'reason'    => $reason,
                    'rows_flagged' => $flagged,
                ]);
            } catch (\Throwable $e) {
                if ($connection->transactionLevel() > $transactionLevel) {
                    $connection->rollBack($transactionLevel);
                }
                $this->logPlotsWorkflow('error', "Failed to decommission file: $fileNo", ['error' => $e->getMessage()]);
                $summary['errors'][] = "Error decommissioning $fileNo: " . $e->getMessage();
                if ($removeIndexedParent) {
                    // A commissioning transaction must not report success while
                    // leaving the selected indexed parent in the live registers.
                    throw $e;
                }
            }
        }

        // Materialise / refresh the parcel-update (merger) group so the File Tracking Sheet and
        // File Movement History can stitch every related file's movement log into one lineage.
        // Best-effort only — this must never fail the decommission itself.
        try {
            $mergerService = app(\App\Services\FileMergerService::class);
            $seed = $successorFileNo ?: ($fileNumbers[0] ?? null);
            if ($seed) {
                $mergerService->rebuildForFile($seed);
            }
            // Also seed from each decommissioned parent so a group is still built when the
            // successor is not yet linked (e.g. the caller passed no successor file number).
            foreach ($fileNumbers as $parentFileNo) {
                $mergerService->rebuildForFile($parentFileNo);
            }
        } catch (\Throwable $mergerError) {
            $this->logPlotsWorkflow('warning', 'File merger group rebuild skipped', [
                'error' => $mergerError->getMessage(),
            ]);
        }

        // LAAS Portal: badge the retired files on their holders' accounts. After the
        // caller's commit (commissioning wraps this in its own transaction), and
        // best-effort; laas:reconcile catches anything missed.
        if (!empty($summary['archived'])) {
            self::refreshLaasLineage($summary['archived']);
        }

        return $summary;
    }

    /**
     * Re-read the LAAS Portal lifecycle of these files once the current sqlsrv
     * transaction commits (immediately when none is open). Public so the undo
     * paths (re-grant correction, subdivision undo, restore) can call it too.
     */
    public static function refreshLaasLineage(array $fileNumbers): void
    {
        $run = function () use ($fileNumbers) {
            try {
                app(\App\Services\Laas\LaasLineageService::class)->refresh($fileNumbers);
            } catch (\Throwable $e) {
                Log::warning('LAAS lineage refresh skipped', ['files' => $fileNumbers, 'error' => $e->getMessage()]);
            }
        };

        try {
            DB::connection('sqlsrv')->afterCommit($run);
        } catch (\Throwable $e) {
            $run();
        }
    }

    /**
     * Stamp the decommission attributes on whichever live rows $match selects.
     *
     * Replaces the hard DELETE this service used to run. Each column is written only
     * when the table actually has it, so the service works before and after the
     * 2026_08_15_100000 migration and tolerates tables patched by hand.
     *
     * fileNumber is the one table with pre-existing decommission columns under older
     * names (is_decommissioned / decommissioning_date / decommissioning_reason). Its
     * decommissioning_date is kept in step with decommissioned_at so the File
     * Decommissioning screen, which still reads the old column, keeps working.
     *
     * Already-flagged rows are refreshed rather than skipped: a file decommissioned
     * twice (subdivided, then its fragments merged) should show the latest reason
     * and successor, and decommissioned_files retains the full history either way.
     *
     * Public because decommissioning is not only driven from this service: the
     * `decommissioning:temp-files` sweep flags temporary "(T)" records that the
     * file-number lookup above cannot reach, and must stamp them with exactly the
     * same column set rather than keeping a second copy of it.
     *
     * @param  callable $match  receives the query builder to apply the row filter
     * @return int              rows flagged
     */
    public function flagDecommissioned(
        string $table,
        callable $match,
        string $reason,
        string $decommissionedBy,
        ?string $successorFileNo
    ): int {
        try {
            $schema = Schema::connection('sqlsrv');

            if (!$schema->hasTable($table) || !$schema->hasColumn($table, 'is_decommissioned')) {
                return 0;
            }

            $now = now();

            $payload = ['is_decommissioned' => 1];

            $optional = [
                'decommissioned_at'      => $now,
                'decommissioned_by'      => $decommissionedBy,
                'decommissioning_reason' => $reason,
                'successor_file_no'      => $successorFileNo,
                // fileNumber's original column name for the same fact.
                'decommissioning_date'   => $now,
                'updated_at'             => $now,
            ];

            foreach ($optional as $column => $value) {
                if ($schema->hasColumn($table, $column)) {
                    $payload[$column] = $value;
                }
            }

            $query = DB::connection('sqlsrv')->table($table);
            $query->where($match);

            return $query->update($payload);
        } catch (\Exception $e) {
            // A flag that fails must not abort the decommission — decommissioned_files
            // has already recorded it, and this row can be repaired by re-running.
            $this->logPlotsWorkflow('warning', "Could not flag decommissioned rows in $table", [
                'error' => $e->getMessage(),
            ]);

            return 0;
        }
    }

    /**
     * Is this file already flagged as decommissioned?
     *
     * Mirrors decommissionFiles()' lookup (MLS number OR KANGIS number) so a
     * KANGIS-only mother is recognised too.
     */
    public function isDecommissioned(string $fileNo): bool
    {
        $flagged = DB::connection('sqlsrv')->table('fileNumber')
            ->where(function ($q) use ($fileNo) {
                $q->where('mlsfNo', $fileNo)->orWhere('kangisFileNo', $fileNo);
            })
            ->where('is_decommissioned', 1)
            ->exists();

        if ($flagged) {
            return true;
        }

        return DB::connection('sqlsrv')->table('decommissioned_files')
            ->where('file_no', $fileNo)
            ->exists();
    }

    /**
     * The file's existing REAL decommission record, or null.
     *
     * A file already in decommissioned_files is never recorded there again, whatever
     * workflow decommissions it a second time (user rule, 2026-10-21). A title-status
     * flag (false_decommissioning = 1) is not a decommission, so it does not count —
     * see DecommissionScope. The first record wins: it holds the original reason and
     * successor.
     */
    /**
     * "File Already Decommissioned Before" for each file: Yes when it already has a
     * REAL decommissioning record (that record is then extended, not duplicated),
     * No otherwise. The parcel-update forms show this locked — it is read from the
     * records, never chosen.
     *
     * @return array<string, array{answer: string, record_id: ?int, date: ?string, reason: ?string, successor: ?string}>
     */
    public function decommissionedBefore(array $fileNumbers): array
    {
        $out = [];
        foreach ($fileNumbers as $fileNo) {
            $fileNo = trim((string) $fileNo);
            if ($fileNo === '' || isset($out[$fileNo])) {
                continue;
            }
            $row = $this->activeDecommissionRecord($fileNo);
            $out[$fileNo] = [
                'answer'    => $row ? 'Yes' : 'No',
                'record_id' => $row ? (int) $row->id : null,
                'date'      => $row && $row->decommissioning_date ? \Carbon\Carbon::parse($row->decommissioning_date)->format('d/m/Y') : null,
                'reason'    => $row ? (string) $row->decommissioning_reason : null,
                'successor' => $row ? ($row->successor_file_no ?? null) : null,
            ];
        }

        return $out;
    }

    /**
     * "File Already Decommissioned Before: Yes" for workflows that write their own
     * decommission (the Change of Purpose commissioning): add $successors to the file's
     * first real record and re-flag its live rows from that record — original reason,
     * merged successor list. Returns the record id, or null when the file has none
     * (the caller then writes its new record as usual).
     */
    public function extendExistingDecommission(string $fileNo, string $successors): ?int
    {
        $existing = $this->activeDecommissionRecord($fileNo);
        if (!$existing) {
            return null;
        }

        $merged = $this->mergeSuccessorList($existing->successor_file_no ?? null, $successors);
        if ($merged !== (string) ($existing->successor_file_no ?? '')
            && Schema::connection('sqlsrv')->hasColumn('decommissioned_files', 'successor_file_no')) {
            DB::connection('sqlsrv')->table('decommissioned_files')
                ->where('id', $existing->id)
                ->update(['successor_file_no' => $merged, 'updated_at' => now()]);
        }

        $reason = (string) ($existing->decommissioning_reason ?? '');
        $by     = (string) ($existing->decommissioned_by ?? '');
        $this->flagDecommissioned('fileNumber', fn ($q) => $q->where('mlsfNo', $fileNo)->orWhere('kangisFileNo', $fileNo), $reason, $by, $merged);
        $this->flagDecommissioned('file_indexings', fn ($q) => $q->where('file_number', $fileNo)->orWhere('kangis_file_no', $fileNo), $reason, $by, $merged);
        $this->flagDecommissioned('mls_file_no', fn ($q) => $q->where('full_file_number', $fileNo), $reason, $by, $merged);

        // flagDecommissioned() stamps now(); the file was retired on the record's date.
        if (!empty($existing->decommissioning_date)) {
            foreach ([['fileNumber', ['mlsfNo', 'kangisFileNo']], ['file_indexings', ['file_number', 'kangis_file_no']], ['mls_file_no', ['full_file_number']]] as [$table, $columns]) {
                $dates = array_values(array_filter(['decommissioned_at', 'decommissioning_date'],
                    fn ($c) => Schema::connection('sqlsrv')->hasColumn($table, $c)));
                if ($dates) {
                    DB::connection('sqlsrv')->table($table)
                        ->where(function ($q) use ($columns, $fileNo) {
                            foreach ($columns as $c) {
                                $q->orWhere($c, $fileNo);
                            }
                        })
                        ->update(array_fill_keys($dates, $existing->decommissioning_date));
                }
            }
        }

        return (int) $existing->id;
    }

    /**
     * The value saved in an application's previously_decommissioned column: "Yes" / "No"
     * for one source file, "FILE=Yes; FILE=No" for several, null for none.
     */
    public function decommissionedBeforeAnswer(array $fileNumbers): ?string
    {
        $status = $this->decommissionedBefore($fileNumbers);
        if (!$status) {
            return null;
        }
        if (count($status) === 1) {
            return reset($status)['answer'];
        }

        return mb_substr(implode('; ', array_map(
            fn ($fileNo, $s) => $fileNo . '=' . $s['answer'],
            array_keys($status),
            $status
        )), 0, 2000);
    }

    /** "A, B" + "B, C" => "A,B,C" — the comma list successor_file_no holds, deduplicated in order. */
    private function mergeSuccessorList(?string $existing, ?string $add): string
    {
        $split = fn ($s) => array_filter(array_map('trim', explode(',', (string) $s)), fn ($v) => $v !== '');
        $merged = [];
        foreach (array_merge($split($existing), $split($add)) as $fileNo) {
            $merged[mb_strtoupper($fileNo)] ??= $fileNo;
        }

        return implode(',', array_values($merged));
    }

    public function activeDecommissionRecord(string $fileNo): ?object
    {
        $fileNo = trim($fileNo);
        if ($fileNo === '') {
            return null;
        }

        $query = DB::connection('sqlsrv')->table('decommissioned_files')
            ->where(function ($q) use ($fileNo) {
                $q->where('file_no', $fileNo)->orWhere('mls_file_no', $fileNo);
            });

        return \App\Support\DecommissionScope::real($query)->orderBy('id')->first();
    }

    /**
     * Add successors to a file that is ALREADY decommissioned, without archiving it again.
     *
     * A subdivision larger than the generator's 200-file batch cap is commissioned in
     * several runs. The mother is decommissioned by the first run; later runs must not
     * write a second decommissioned_files / deprecated_records row for the same event —
     * they only need their fragments appended to the successor list so the
     * Decommissioned Files screen shows every fragment, not just the first chunk's.
     *
     * Nothing is deleted here either: this only rewrites the successor column on rows
     * that already exist.
     */

    public function appendSuccessors(string $fileNo, array $successorFileNumbers): int
    {
        $successorFileNumbers = array_values(array_filter($successorFileNumbers, fn ($f) => $f !== null && $f !== ''));
        if (empty($successorFileNumbers)) {
            return 0;
        }

        $merge = function (?string $existing) use ($successorFileNumbers): string {
            $current = array_filter(array_map('trim', explode(',', (string) $existing)));

            return implode(',', array_values(array_unique(array_merge($current, $successorFileNumbers))));
        };

        $updated = 0;

        try {
            if (Schema::connection('sqlsrv')->hasColumn('decommissioned_files', 'successor_file_no')) {
                $rows = DB::connection('sqlsrv')->table('decommissioned_files')
                    ->where('file_no', $fileNo)
                    ->get(['id', 'successor_file_no']);

                foreach ($rows as $row) {
                    $updated += DB::connection('sqlsrv')->table('decommissioned_files')
                        ->where('id', $row->id)
                        ->update([
                            'successor_file_no' => $merge($row->successor_file_no ?? null),
                            'updated_at'        => now(),
                        ]);
                }
            }

            foreach ([['fileNumber', 'mlsfNo'], ['file_indexings', 'file_number']] as [$table, $column]) {
                if (!Schema::connection('sqlsrv')->hasColumn($table, 'successor_file_no')) {
                    continue;
                }

                $rows = DB::connection('sqlsrv')->table($table)
                    ->where($column, $fileNo)
                    ->get(['id', 'successor_file_no']);

                foreach ($rows as $row) {
                    $updated += DB::connection('sqlsrv')->table($table)
                        ->where('id', $row->id)
                        ->update(['successor_file_no' => $merge($row->successor_file_no ?? null)]);
                }
            }
        } catch (\Exception $e) {
            $this->logPlotsWorkflow('warning', "Could not append successors for $fileNo", [
                'error'      => $e->getMessage(),
                'successors' => $successorFileNumbers,
            ]);
        }

        $this->logPlotsWorkflow('info', "Successors appended to decommissioned file: $fileNo", [
            'successors' => $successorFileNumbers,
            'rows'       => $updated,
        ]);

        return $updated;
    }

    /**
     * Update historical prop_id across various tables.
     */
    public function updateHistoricalPropId(array $oldPropIds, int $newPropId): int
    {
        if (empty($oldPropIds)) {
            return 0;
        }

        $tables = [
            'pra' => 'Prop_id',
            'deeds_registrations' => 'prop_id', // Assuming these exist
            'c_of_o' => 'prop_id',
            'billings' => 'prop_id',
            'deeds_bill_balances_metadata' => 'prop_id',
            'file_history_staging' => 'prop_id',
            'PropID_Master' => 'prop_id', // Handle lineage in master? Or just leave it?
        ];

        $totalUpdated = 0;

        foreach ($tables as $table => $column) {
            try {
                if (Schema::connection('sqlsrv')->hasTable($table) && Schema::connection('sqlsrv')->hasColumn($table, $column)) {
                    $updated = DB::connection('sqlsrv')->table($table)
                        ->whereIn($column, $oldPropIds)
                        ->update([$column => $newPropId, 'updated_at' => now()]);
                    $totalUpdated += $updated;
                }
            } catch (\Exception $e) {
                $this->logPlotsWorkflow('warning', "Could not update historical records in table $table", ['error' => $e->getMessage()]);
            }
        }

        return $totalUpdated;
    }

    /**
     * Specialized logger for Plot Workflow actions.
     */
    private function logPlotsWorkflow(string $level, string $message, array $context = []): void
    {
        try {
            $logger = Log::build([
                'driver' => 'single',
                'path' => storage_path('logs/plots-workflow.log'),
                'level' => 'debug',
            ]);
            $logger->{$level}($message, $context);
        } catch (\Throwable $e) {
            Log::warning('Failed to write Plots workflow audit log from service', [
                'message' => $message,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
