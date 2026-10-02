<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Bring back the file_indexings rows that decommissioning hard-deleted before
 * 2026-08-15, restoring them FLAGGED rather than active.
 *
 * Until the soft-decommission change, PlotWorkflowService archived a file into
 * decommissioned_files + deprecated_records and then deleted its live rows. The
 * indexing detail survives only in deprecated_records, which this command replays
 * back into file_indexings with is_decommissioned = 1 and the decommission
 * attributes taken from the archive — the exact state the new code would have left
 * behind had it been in place at the time.
 *
 * WHAT CANNOT BE RECOVERED
 * Only file_indexings was ever archived. The matching fileNumber, customers_staging
 * and entities_staging rows were deleted without a copy, so they are gone for good;
 * this command reports them rather than inventing them. Files decommissioned before
 * deprecated_records existed have no archive either and are reported as unrecoverable.
 *
 * SAFETY
 *   - Dry run by default; --apply is required to write anything.
 *   - Skips any file that already has a live file_indexings row, so it can never
 *     duplicate a file number (file_indexings.file_number is UNIQUE) or overwrite
 *     data that a user has since re-entered by hand.
 *   - Covers every REAL decommissioning, i.e. false_decommissioning <> 1. That
 *     includes ST handovers (2), which are decommissionings like any other. Only a
 *     title-status flag (1) is excluded: it never decommissioned or deleted anything,
 *     so there is nothing to bring back. See App\Support\DecommissionScope.
 *   - Re-runnable: a second pass finds the rows already restored and skips them.
 */
class RestoreHardDeletedDecommissionedFiles extends Command
{
    protected $signature = 'decommissioning:restore-deleted
        {--file= : Only restore this file number}
        {--apply : Write the restored rows (default is a dry run)}
        {--limit=0 : Stop after this many files (0 = no limit)}';

    protected $description = 'Restore file_indexings rows hard-deleted by pre-2026-08-15 decommissioning, flagged as decommissioned.';

    public function handle(): int
    {
        $conn   = DB::connection('sqlsrv');
        $schema = Schema::connection('sqlsrv');
        $file   = trim((string) $this->option('file'));
        $apply  = (bool) $this->option('apply');
        $limit  = max(0, (int) $this->option('limit'));

        if (!$schema->hasColumn('file_indexings', 'is_decommissioned')) {
            $this->error('file_indexings has no is_decommissioned column — run the 2026_08_15_100000 migration first.');

            return self::FAILURE;
        }

        // Every real decommissioning: false_decommissioning <> 1. ST handovers (2) are
        // included; only a title-status flag (1) is skipped, having deleted nothing.
        $archived = $conn->table('decommissioned_files')
            ->where(function ($q) {
                $q->where('false_decommissioning', '<>', \App\Support\DecommissionScope::FALSE_DECOMMISSIONING)->orWhereNull('false_decommissioning');
            })
            ->when($file !== '', fn ($q) => $q->where('file_no', $file))
            ->orderBy('decommissioning_date')
            ->get();

        $restored = 0;
        $skipped = 0;
        $unrecoverable = [];

        foreach ($archived as $row) {
            $fileNo = trim((string) ($row->file_no ?? ''));
            if ($fileNo === '') {
                continue;
            }

            if ($limit > 0 && $restored >= $limit) {
                break;
            }

            $liveExists = $conn->table('file_indexings')
                ->whereRaw('LTRIM(RTRIM(file_number)) = ?', [$fileNo])
                ->exists();

            if ($liveExists) {
                $skipped++;
                continue;
            }

            $archive = $conn->table('deprecated_records')
                ->whereRaw('LTRIM(RTRIM(file_number)) = ?', [$fileNo])
                ->orderByDesc('id')
                ->first();

            if (!$archive) {
                $unrecoverable[] = $fileNo;
                continue;
            }

            $payload = [
                'file_number'            => $fileNo,
                'file_title'             => $archive->file_title ?? ($row->file_name ?? null),
                'land_use_type'          => $archive->land_use_type ?? null,
                'plot_number'            => $archive->plot_number ?? null,
                'district'               => $archive->district ?? null,
                'lga'                    => $archive->lga ?? null,
                'location'               => $archive->location ?? null,
                'plot_size'              => $archive->plot_size ?? null,
                'tp_no'                  => $archive->tp_no ?? null,
                'lpkn_no'                => $archive->lpkn_no ?? null,
                'tracking_id'            => $archive->tracking_id ?? null,
                'original_holder'        => $archive->original_holder ?? null,
                'current_holder'         => $archive->current_holder ?? null,
                'parent_prop_id'         => $archive->parent_prop_id ?? null,
                'related_fileno'         => $archive->related_fileno ?? null,
                'has_transaction'        => $archive->has_transaction ?? 0,
                'serial_no'              => $archive->serial_no ?? null,
                'batch_no'               => $archive->batch_no ?? null,
                'workflow_status'        => $archive->workflow_status ?? null,
                'registry'               => $archive->registry ?? null,
                'general_registry'       => $archive->general_registry ?? null,
                'prop_id'                => $archive->prop_id ?? null,
                'phone'                  => $archive->phone ?? null,
                'residence_address'      => $archive->residence_address ?? null,
                'kangis_file_no'         => $row->kangis_file_no ?? null,
                'new_kangis_file_no'     => $row->new_kangis_file_no ?? null,
                // created_by is NOT NULL on file_indexings — a restored row with no
                // recorded creator would abort the insert, so fall back to the user
                // who decommissioned it, then to 1 (System).
                'created_by'             => $archive->created_by ?: 1,
                'updated_by'             => $archive->updated_by ?? null,
                'created_at'             => $archive->created_at ?? ($row->created_at ?? now()),
                'updated_at'             => now(),
                // The whole point: it comes back decommissioned, not active.
                'is_decommissioned'      => 1,
                'decommissioned_at'      => $row->decommissioning_date ?? null,
                'decommissioned_by'      => $row->decommissioned_by ?? null,
                'decommissioning_reason' => $row->decommissioning_reason ?? ($archive->workflow_type ?? null),
                'successor_file_no'      => $row->successor_file_no ?? null,
            ];

            // Drop anything this database does not actually have a column for.
            $payload = array_filter(
                $payload,
                fn ($column) => $schema->hasColumn('file_indexings', $column),
                ARRAY_FILTER_USE_KEY
            );

            if (!$apply) {
                $this->line("  [DRY] would restore {$fileNo} — " . ($payload['decommissioning_reason'] ?? 'no reason recorded'));
                $restored++;
                continue;
            }

            try {
                $conn->table('file_indexings')->insert($payload);
                $this->info("  [OK]  restored {$fileNo}");
                $restored++;
            } catch (\Throwable $e) {
                $this->error("  [FAIL] {$fileNo}: " . $e->getMessage());
            }
        }

        $this->newLine();
        $this->line($apply ? 'APPLIED' : 'DRY RUN — re-run with --apply to write');
        $this->table(
            ['Restored', 'Already live (skipped)', 'No archive to restore from'],
            [[$restored, $skipped, count($unrecoverable)]]
        );

        if (!empty($unrecoverable)) {
            $this->warn('These files were deleted with no deprecated_records copy and cannot be restored:');
            $this->line('  ' . implode(', ', $unrecoverable));
        }

        $this->newLine();
        $this->warn('Note: fileNumber, customers_staging and entities_staging rows were never archived and are NOT restored by this command.');

        return self::SUCCESS;
    }
}
