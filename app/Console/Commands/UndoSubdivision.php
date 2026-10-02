<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class UndoSubdivision extends Command
{
    protected $signature = 'subdivision:undo
                            {--force : Actually execute the rollback (default is dry-run)}';

    protected $description = 'Undo the wrong subdivision of RES-RC-1982-731 into RES-RC-2026-5/6/7';

    // ── Hardcoded constants for this specific reversal ──────────────────────
    private const MOTHER_FILE       = 'RES-RC-1982-731';
    private const FRAGMENTS         = ['RES-RC-2026-5', 'RES-RC-2026-6', 'RES-RC-2026-7'];
    private const PRA_IDS           = [149579, 149580, 149581];
    private const PROP_ID_MASTER    = [87680, 87681, 87682];   // PropID_Master.id for fragments
    private const DEPRECATED_ID     = 3;      // deprecated_records.id
    private const ORIG_FN_ID        = 70992;  // original fileNumber.id to restore
    private const ORIG_FI_ID        = 87725;  // original file_indexings.id to restore
    private const LAND_USE          = 'RES-RC';
    private const YEAR              = 2026;
    private const SERIAL_ROLLBACK   = 4;      // last_serial before batch (5,6,7 were issued)

    public function handle(): int
    {
        $dryRun = !$this->option('force');
        $conn   = DB::connection('sqlsrv');

        $this->line('');
        $this->info('=== Subdivision Rollback: ' . self::MOTHER_FILE . ' ===');
        $this->line('Fragments: ' . implode(', ', self::FRAGMENTS));
        $this->line('');

        // ── 1. Validate source data ─────────────────────────────────────────
        $dep = $conn->table('deprecated_records')->where('id', self::DEPRECATED_ID)->first();
        if (!$dep) {
            $this->error('deprecated_records id=' . self::DEPRECATED_ID . ' not found. Aborting.');
            return 1;
        }
        if ($dep->file_number !== self::MOTHER_FILE) {
            $this->error('deprecated_records id=' . self::DEPRECATED_ID . ' is for "' . $dep->file_number . '", not ' . self::MOTHER_FILE . '. Aborting.');
            return 1;
        }

        // Check mother not already in active tables
        if ($conn->table('fileNumber')->where('mlsfNo', self::MOTHER_FILE)->exists()) {
            $this->warn(self::MOTHER_FILE . ' already exists in fileNumber. Aborting to prevent duplicate.');
            return 1;
        }
        if ($conn->table('file_indexings')->where('file_number', self::MOTHER_FILE)->exists()) {
            $this->warn(self::MOTHER_FILE . ' already exists in file_indexings. Aborting to prevent duplicate.');
            return 1;
        }
        // Check original IDs are free
        if ($conn->table('fileNumber')->where('id', self::ORIG_FN_ID)->exists()) {
            $this->warn('fileNumber id=' . self::ORIG_FN_ID . ' is already in use by another record. Cannot restore with original ID.');
            return 1;
        }
        if ($conn->table('file_indexings')->where('id', self::ORIG_FI_ID)->exists()) {
            $this->warn('file_indexings id=' . self::ORIG_FI_ID . ' is already in use by another record. Cannot restore with original ID.');
            return 1;
        }

        $this->line('Source data (deprecated_records #' . self::DEPRECATED_ID . '):');
        $this->line('  file_number : ' . $dep->file_number);
        $this->line('  file_title  : ' . $dep->file_title);
        $this->line('  tracking_id : ' . $dep->tracking_id);
        $this->line('  location    : ' . $dep->location);
        $this->line('  lga         : ' . $dep->lga);
        $this->line('  batch_no    : ' . $dep->batch_no);

        $this->line('');
        $this->info('Actions that will execute:');
        $this->line('  [INSERT]  fileNumber       id=' . self::ORIG_FN_ID . '  mlsfNo=' . self::MOTHER_FILE);
        $this->line('  [INSERT]  file_indexings   id=' . self::ORIG_FI_ID . '  file_number=' . self::MOTHER_FILE);
        $this->line('  [DELETE]  pra              ids ' . implode(', ', self::PRA_IDS));
        $this->line('  [DELETE]  PropID_Master    ids ' . implode(', ', self::PROP_ID_MASTER));
        $this->line('  [DELETE]  deprecated_records id=' . self::DEPRECATED_ID);
        $this->line('  [UPDATE]  plot_subdivision_applications → status=approved');
        $this->line('  [UPDATE]  mls_serial_control last_serial=' . self::SERIAL_ROLLBACK . ' (RES-RC 2026)');
        $this->line('  [CLEANUP] entities_staging / customers_staging / grouping for fragments (no-op if already clean)');

        if ($dryRun) {
            $this->line('');
            $this->warn('DRY RUN — no changes made. Run with --force to execute.');
            return 0;
        }

        // ── 2. Execute inside a transaction ─────────────────────────────────
        $this->line('');
        $this->info('Executing rollback...');

        $conn->beginTransaction();
        try {

            // 2a. Restore fileNumber with original ID via IDENTITY INSERT (raw SQL)
            $pdo = $conn->getPdo();
            $pdo->exec('SET IDENTITY_INSERT [fileNumber] ON');
            $conn->table('fileNumber')->insert([
                'id'                => self::ORIG_FN_ID,
                'mlsfNo'            => $dep->file_number,
                'FileName'          => $dep->file_title,
                'kangisFileNo'      => null,
                'NewKANGISFileNo'   => null,
                'commissioning_date'=> null,
                'type'              => 'Generated',
                'tracking_id'       => $dep->tracking_id,
                'location'          => $dep->location,
                'lga'               => $dep->lga,
                'plot_no'           => $dep->plot_number,
                'tp_no'             => $dep->tp_no ?: null,
                'created_by'        => $dep->created_by,
                'updated_by'        => $dep->updated_by,
                'is_decommissioned' => 0,
                'is_deleted'        => 0,
                'SOURCE'            => null,
            ]);
            $pdo->exec('SET IDENTITY_INSERT [fileNumber] OFF');
            $this->line('  [OK] Restored fileNumber id=' . self::ORIG_FN_ID . ' mlsfNo=' . self::MOTHER_FILE);

            // 2b. Restore file_indexings with original ID via IDENTITY INSERT (raw SQL)
            $pdo->exec('SET IDENTITY_INSERT [file_indexings] ON');
            $conn->table('file_indexings')->insert([
                'id'                => self::ORIG_FI_ID,
                'file_number'       => $dep->file_number,
                'file_title'        => $dep->file_title,
                'land_use_type'     => $dep->land_use_type,
                'plot_number'       => $dep->plot_number,
                'district'          => $dep->district,
                'lga'               => $dep->lga,
                'location'          => $dep->location,
                'plot_size'         => $dep->plot_size ?: null,
                'tp_no'             => $dep->tp_no ?: null,
                'lpkn_no'           => $dep->lpkn_no ?: null,
                'tracking_id'       => $dep->tracking_id,
                'original_holder'   => $dep->original_holder,
                'current_holder'    => $dep->current_holder,
                'parent_prop_id'    => $dep->parent_prop_id ?: null,
                'related_fileno'    => $dep->related_fileno ?: null,
                'has_transaction'   => $dep->has_transaction,
                'workflow_status'   => $dep->workflow_status,
                'serial_no'         => $dep->serial_no ?: null,
                'batch_no'          => $dep->batch_no,
                'registry'          => $dep->registry,
                'general_registry'  => $dep->general_registry,
                'prop_id'           => $dep->prop_id ?: null,
                'phone'             => $dep->phone ?: null,
                'residence_address' => $dep->residence_address ?: null,
                'created_by'        => $dep->created_by,
                'updated_by'        => $dep->updated_by,
                'is_deleted'        => 0,
                'created_at'        => now(),
                'updated_at'        => now(),
            ]);
            $pdo->exec('SET IDENTITY_INSERT [file_indexings] OFF');
            $this->line('  [OK] Restored file_indexings id=' . self::ORIG_FI_ID . ' file_number=' . self::MOTHER_FILE);

            // 2c. Delete PRA records for the 3 fragments
            $praDel = $conn->table('pra')->whereIn('id', self::PRA_IDS)->delete();
            $this->line("  [OK] Deleted pra rows: {$praDel}");

            // 2d. Delete PropID_Master entries for fragment prop_ids
            $pidDel = $conn->table('PropID_Master')->whereIn('id', self::PROP_ID_MASTER)->delete();
            $this->line("  [OK] Deleted PropID_Master rows: {$pidDel}");

            // 2e. Cleanup fragment leftovers (should already be clean, but be thorough)
            $c1 = $conn->table('entities_staging')->whereIn('file_number', self::FRAGMENTS)->delete();
            $c2 = $conn->table('customers_staging')->whereIn('file_number', self::FRAGMENTS)->delete();
            $c3 = $conn->table('grouping')
                ->whereIn('mls_fileno', self::FRAGMENTS)
                ->update(['mls_fileno' => null, 'mapping' => 0, 'updated_at' => now()]);
            $this->line("  [OK] Fragment cleanup — entities_staging:{$c1}, customers_staging:{$c2}, grouping_reset:{$c3}");

            // 2f. Delete the archive record
            $conn->table('deprecated_records')->where('id', self::DEPRECATED_ID)->delete();
            $this->line('  [OK] Deleted deprecated_records id=' . self::DEPRECATED_ID);

            // 2g. Reset subdivision application status
            $appUpd = $conn->table('plot_subdivision_applications')
                ->where('file_no', self::MOTHER_FILE)
                ->where('status', 'commissioned')
                ->update([
                    'status'     => 'approved',
                    'remarks'    => 'Rollback: subdivision undone — ' . now()->toDateTimeString(),
                    'updated_at' => now(),
                ]);
            $this->line("  [OK] Reset plot_subdivision_applications: {$appUpd} row(s)");

            // 2h. Roll back the serial counter to 4 (before the 3 fragments were issued)
            $serialUpd = $conn->table('mls_serial_control')
                ->where('land_use', self::LAND_USE)
                ->where('year', self::YEAR)
                ->update(['last_serial' => self::SERIAL_ROLLBACK, 'updated_at' => now()]);
            $this->line("  [OK] mls_serial_control last_serial → " . self::SERIAL_ROLLBACK . " ({$serialUpd} row(s))");

            $conn->commit();

            $this->line('');
            $this->info('✓ Rollback complete.');
            $this->line('  ' . self::MOTHER_FILE . ' restored to fileNumber and file_indexings.');
            $this->line('  Fragments removed from pra + PropID_Master.');
            $this->line('  Serial counter rolled back to ' . self::SERIAL_ROLLBACK . '.');

        } catch (\Throwable $e) {
            $conn->rollBack();
            // Safety: turn off IDENTITY_INSERT if it was left on
            try { $conn->getPdo()->exec('SET IDENTITY_INSERT [fileNumber] OFF'); } catch (\Throwable $_) {}
            try { $conn->getPdo()->exec('SET IDENTITY_INSERT [file_indexings] OFF'); } catch (\Throwable $_) {}

            $this->error('Rollback FAILED — transaction rolled back: ' . $e->getMessage());
            $this->line($e->getTraceAsString());
            return 1;
        }

        return 0;
    }
}
