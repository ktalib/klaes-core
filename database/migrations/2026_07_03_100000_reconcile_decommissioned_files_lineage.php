<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * One-off reconciliation for files that were genuinely decommissioned/superseded by a land
 * transaction (Subdivision, Merger, Separation, Change of Purpose, File Extension) but whose
 * ACTIVE records survived the hard-delete. The audit found 1 surviving file_indexings row
 * (IND-2021-13) and fileNumber archive rows still flagged is_decommissioned = 0.
 *
 * IMPORTANT: this migration deliberately does NOT touch the Legal Search staging tables
 * (file_history_staging, CofO_staging, pra, deed_registrations). Legal Search relies on those
 * rows to display the SUCCESSOR file's lineage/history via prop_id / parent_prop_id expansion.
 * Suppression of a decommissioned file when it is searched directly is handled at query time in
 * LegalSearchService::getDecommissionedFileNumbers(), not by deleting history.
 *
 * Only rows where false_decommissioning = 0 (or NULL) are reconciled; false_decommissioning = 1
 * marks Title-Status flags that are NOT real decommissions.
 *
 * Idempotent: re-running finds nothing left to update. No down() — decommissioning is not reversed.
 */
return new class extends Migration
{
    public function up(): void
    {
        $conn = DB::connection('sqlsrv');

        // Collect every real-decommissioned file-number variant.
        $query = $conn->table('decommissioned_files');
        if (Schema::connection('sqlsrv')->hasColumn('decommissioned_files', 'false_decommissioning')) {
            $query->where(function ($q) {
                $q->where('false_decommissioning', 0)->orWhereNull('false_decommissioning');
            });
        }
        $rows = $query->get(['file_no', 'mls_file_no', 'kangis_file_no', 'new_kangis_file_no']);

        $variants = [];
        foreach ($rows as $r) {
            foreach (['file_no', 'mls_file_no', 'kangis_file_no', 'new_kangis_file_no'] as $col) {
                $v = trim((string) ($r->$col ?? ''));
                if ($v !== '') {
                    $variants[strtoupper($v)] = $v; // dedupe case-insensitively, keep original casing
                }
            }
        }
        $variants = array_values($variants);

        if (empty($variants)) {
            return;
        }

        $now = now();

        foreach (array_chunk($variants, 500) as $chunk) {
            // 1. Soft-delete any surviving file_indexings rows (e.g. IND-2021-13).
            if (Schema::connection('sqlsrv')->hasColumn('file_indexings', 'deleted_at')) {
                $fiUpdate = ['deleted_at' => $now];
                if (Schema::connection('sqlsrv')->hasColumn('file_indexings', 'updated_at')) {
                    $fiUpdate['updated_at'] = $now;
                }
                $conn->table('file_indexings')
                    ->whereIn('file_number', $chunk)
                    ->whereNull('deleted_at')
                    ->update($fiUpdate);
            }

            // 2. Flag any surviving fileNumber archive rows as decommissioned.
            if (Schema::connection('sqlsrv')->hasColumn('fileNumber', 'is_decommissioned')) {
                $fnUpdate = ['is_decommissioned' => 1];
                if (Schema::connection('sqlsrv')->hasColumn('fileNumber', 'updated_at')) {
                    $fnUpdate['updated_at'] = $now;
                }
                $conn->table('fileNumber')
                    ->whereIn('mlsfNo', $chunk)
                    ->where(function ($q) {
                        $q->where('is_decommissioned', 0)->orWhereNull('is_decommissioned');
                    })
                    ->update($fnUpdate);
            }
        }
    }

    public function down(): void
    {
        // Intentionally irreversible: we do not un-decommission files.
    }
};
