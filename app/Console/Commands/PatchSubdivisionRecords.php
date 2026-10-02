<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * One-time patch: insert the mls_file_no / fileNumber / file_indexings rows
 * that were silently rolled back during the RES-2026-2225/2226/2227 batch
 * commissioning due to XACT_ABORT being triggered by a batch_no type mismatch
 * in the lineage-link SELECT query.
 *
 * All other data (pra, PropID_Master, decommissioned_files, deprecated_records,
 * mls_serial_control, plot_subdivision_applications) was already committed.
 */
class PatchSubdivisionRecords extends Command
{
    protected $signature = 'subdivision:patch-records
                            {--force : Actually execute (default is dry-run)}';

    protected $description = 'Insert missing mls_file_no / fileNumber / file_indexings for RES-2026-2225/6/7';

    // Mother file data (from deprecated_records id=4)
    private const PARENT_PROP_ID    = 28352;
    private const MOTHER_FILE       = 'RES-RC-1982-731';
    private const MOTHER_OWNER      = 'DR YAHAYA MOHAMMED';
    private const RELATED_FILENO    = '["RES-RC-1982-731"]';

    // Fragment data sourced from pra records 149610/149611/149612
    private const FRAGMENTS = [
        [
            'file_number'  => 'RES-2026-2225',
            'file_name'    => 'UMAR FARUK MOHD',
            'plot_no'      => 'C',
            'location'     => 'UDB ROAD',
            'lga'          => 'Nasarawa',
            'prop_id'      => 80012092,
            'serial'       => 2225,
        ],
        [
            'file_number'  => 'RES-2026-2226',
            'file_name'    => 'IDRIS MOHAMMED',
            'plot_no'      => 'A',
            'location'     => 'UDB ROAD',
            'lga'          => 'Nasarawa',
            'prop_id'      => 80012093,
            'serial'       => 2226,
        ],
        [
            'file_number'  => 'RES-2026-2227',
            'file_name'    => 'SHEHU (WHITE) MUHAMMAD',
            'plot_no'      => 'B',
            'location'     => 'UDB ROAD',
            'lga'          => 'Nasarawa',
            'prop_id'      => 80012094,
            'serial'       => 2227,
        ],
    ];

    public function handle(): int
    {
        $dryRun = !$this->option('force');
        $conn   = DB::connection('sqlsrv');
        $now    = now();

        $this->line('');
        $this->info('=== Patch Missing Subdivision Records ===');
        $this->line('Fragments: RES-2026-2225, RES-2026-2226, RES-2026-2227');
        $this->line('');

        // ── Guard: abort if any fragment already exists ──────────────────────
        $existingFiles = collect(self::FRAGMENTS)->pluck('file_number')->toArray();

        $inMls = $conn->table('mls_file_no')->whereIn('full_file_number', $existingFiles)->pluck('full_file_number')->toArray();
        $inFn  = $conn->table('fileNumber')->whereIn('mlsfNo', $existingFiles)->pluck('mlsfNo')->toArray();
        $inFi  = $conn->table('file_indexings')->whereIn('file_number', $existingFiles)->pluck('file_number')->toArray();

        if (!empty($inMls) || !empty($inFn) || !empty($inFi)) {
            $this->warn('Some records already exist — aborting to prevent duplicates.');
            $this->line('  mls_file_no: '   . (empty($inMls) ? 'clean' : implode(', ', $inMls)));
            $this->line('  fileNumber: '    . (empty($inFn)  ? 'clean' : implode(', ', $inFn)));
            $this->line('  file_indexings: '. (empty($inFi)  ? 'clean' : implode(', ', $inFi)));
            return 1;
        }

        // ── Preview ──────────────────────────────────────────────────────────
        $this->info('Records to insert:');
        foreach (self::FRAGMENTS as $f) {
            $this->line("  {$f['file_number']} — {$f['file_name']} | Plot {$f['plot_no']} | {$f['location']} | prop_id {$f['prop_id']}");
        }

        if ($dryRun) {
            $this->line('');
            $this->warn('DRY RUN — no changes made. Run with --force to execute.');
            return 0;
        }

        // ── Execute ───────────────────────────────────────────────────────────
        $this->line('');
        $this->info('Inserting records...');

        $conn->beginTransaction();
        try {
            foreach (self::FRAGMENTS as $f) {
                // Generate a unique tracking ID
                $trackingId = $this->generateTrackingId($conn);

                // mls_file_no
                $conn->table('mls_file_no')->insert([
                    'land_use'         => 'RES',
                    'year'             => 2026,
                    'serial_number'    => $f['serial'],
                    'full_file_number' => $f['file_number'],
                    'file_name'        => $f['file_name'],
                    'plot_no'          => $f['plot_no'],
                    'location'         => $f['location'],
                    'lga'              => $f['lga'],
                    'tracking_id'      => $trackingId,
                    'file_option'      => 'subdivision',
                    'source'           => 'Subdivision',
                    'created_by'       => 'System Admin',
                    'created_at'       => $now,
                    'updated_at'       => $now,
                ]);

                // fileNumber
                $conn->table('fileNumber')->insert([
                    'mlsfNo'     => $f['file_number'],
                    'FileName'   => $f['file_name'],
                    'tracking_id'=> $trackingId,
                    'plot_no'    => $f['plot_no'],
                    'location'   => $f['location'],
                    'lga'        => $f['lga'],
                    'type'       => 'MlsFileNO',
                    'SOURCE'     => 'MLS_Commissioned',
                    'is_deleted' => 0,
                    'created_by' => 'System Admin',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                // file_indexings
                $conn->table('file_indexings')->insert([
                    'file_number'    => $f['file_number'],
                    'file_title'     => $f['file_name'],
                    'land_use_type'  => 'RESIDENTIAL',
                    'plot_number'    => $f['plot_no'],
                    'location'       => $f['location'],
                    'lga'            => $f['lga'],
                    'tracking_id'    => $trackingId,
                    'current_holder' => $f['file_name'],
                    'original_holder'=> self::MOTHER_OWNER,
                    'parent_prop_id' => self::PARENT_PROP_ID,
                    'related_fileno' => self::RELATED_FILENO,
                    'prop_id'        => $f['prop_id'],
                    'workflow_status'=> 'indexed',
                    'is_deleted'     => 0,
                    'created_by'     => 'System Admin',
                    'created_at'     => $now,
                    'updated_at'     => $now,
                ]);

                $this->line("  [OK] {$f['file_number']} tracking_id={$trackingId}");
            }

            $conn->commit();
            $this->line('');
            $this->info('✓ Patch complete. All 3 fragment records inserted into mls_file_no, fileNumber, file_indexings.');

        } catch (\Throwable $e) {
            $conn->rollBack();
            $this->error('Patch FAILED — rolled back: ' . $e->getMessage());
            return 1;
        }

        return 0;
    }

    private function generateTrackingId($conn): string
    {
        do {
            $id = 'TRK-' . strtoupper(substr(str_shuffle('ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), 0, 8))
                 . '-' . strtoupper(substr(str_shuffle('ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), 0, 5));
        } while (
            $conn->table('mls_file_no')->where('tracking_id', $id)->exists() ||
            $conn->table('fileNumber')->where('tracking_id', $id)->exists()
        );
        return $id;
    }
}
