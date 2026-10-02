<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Install the reference data the Land Registration module needs.
 *
 * A new instrument type in this system is a ROW, not a code change — the
 * lookup tables hold reference data, not schema — and there is deliberately no
 * migration for it: the migrations ledger lives in MySQL while these tables are
 * in SQL Server, so a migration would be marked run whether or not the rows
 * actually landed. `database/sql/2026_09_06_add_deed_of_purchase_registration.sql`
 * is the reviewable companion for a production DBA; this command is the same
 * work for dev and staging.
 *
 * Idempotent. The one thing it will never do twice is seed the numbering vault:
 * once a serial has been issued, re-seeding would re-issue numbers already
 * printed on certificates.
 */
class InstallLandRegistration extends Command
{
    protected $signature = 'land-registration:install {--force-vault : Re-seed the numbering vault even if it already exists. Only safe before the first registration.}';

    protected $description = 'Install the Deed of Purchase instrument type, its numbering vault and its role';

    public function handle(): int
    {
        $type = config('land_registration.instrument_type');
        $role = config('land_registration.role');
        $seed = config('land_registration.vault_seed');

        $this->info("Installing Land Registration reference data for: {$type}");
        $this->newLine();

        $db = DB::connection('sqlsrv');

        // 0. The two columns this module needs. Both are additive and nullable,
        //    so existing rows and the Deeds workflow are unaffected:
        //
        //    deed_registrations.registration_category  tells a Land Registration
        //      row from a Digitization one. The spec rules out a separate
        //      physical table, so this column IS the separation.
        //
        //    instrument_number_vaults.current_volume_count  how full the current
        //      volume is, which the 300-entry capacity rule counts against. It
        //      cannot be derived by counting registrations: a deleted one must
        //      not hand its slot back, because its number is never re-issued.
        $this->ensureColumn(
            'deed_registrations',
            'registration_category',
            "ALTER TABLE dbo.deed_registrations ADD registration_category varchar(50) NULL"
        );
        $this->ensureColumn(
            'instrument_number_vaults',
            'current_volume_count',
            "ALTER TABLE dbo.instrument_number_vaults ADD current_volume_count int NULL"
        );

        //    instrument_capture.receipt_no  the payment receipt behind the
        //      registration. The Amount beside it on the form needs no column:
        //      it is the purchase consideration and goes into the existing
        //      consideration_amount.
        $this->ensureColumn(
            'instrument_capture',
            'receipt_no',
            "ALTER TABLE dbo.instrument_capture ADD receipt_no varchar(100) NULL"
        );

        //    pra.consideration_amount / pra.receipt_no  the same pair on the PRA
        //      side. Historical Deeds of Purchase are backfilled through PRA, so
        //      they need somewhere to record what was paid too. instrument_capture
        //      already had consideration_amount; pra had neither.
        $this->ensureColumn(
            'pra',
            'consideration_amount',
            "ALTER TABLE dbo.pra ADD consideration_amount varchar(100) NULL"
        );
        $this->ensureColumn(
            'pra',
            'receipt_no',
            "ALTER TABLE dbo.pra ADD receipt_no varchar(100) NULL"
        );

        //    file_history_staging.consideration_amount / .receipt_no  the third
        //      capture surface. A transaction entered through Create File
        //      Indexing routes to file_history_staging, not pra, and the write
        //      is filtered to that table's own columns — so without these the
        //      two fields post and are dropped without any error.
        $this->ensureColumn(
            'file_history_staging',
            'consideration_amount',
            "ALTER TABLE dbo.file_history_staging ADD consideration_amount varchar(100) NULL"
        );
        $this->ensureColumn(
            'file_history_staging',
            'receipt_no',
            "ALTER TABLE dbo.file_history_staging ADD receipt_no varchar(100) NULL"
        );

        // 1. Registration-side lookup (drives the vault manager and the
        //    registration screen's type filter).
        if ($db->table('new_instrument_types')->where('name', $type)->exists()) {
            // Keep the description current. It is descriptive text only, and it
            // renders inside a table cell in the Manage Instrument Types modal,
            // so a long one there is a layout problem rather than a data one.
            $db->table('new_instrument_types')
                ->where('name', $type)
                ->where(function ($q) {
                    $q->where('description', '<>', config('land_registration.lut_description'))
                        ->orWhereNull('description');
                })
                ->update([
                    'description' => config('land_registration.lut_description'),
                    'updated_at' => now(),
                ]);

            $this->line("  " . str_pad('new_instrument_types', 46) . " already present");
        } else {
            $db->table('new_instrument_types')->insert([
                'name' => $type,
                'description' => config('land_registration.lut_description'),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $this->info("  " . str_pad('new_instrument_types', 46) . " added");
        }

        // 2. Legacy lookup (drives the capture dropdowns, PRA, File Indexing
        //    and Legal Search). The two tables are separate and neither reads
        //    the other, so both rows are required.
        if ($db->table('InstrumentTypes')->whereRaw('LTRIM(RTRIM(InstrumentName)) = ?', [$type])->exists()) {
            $db->table('InstrumentTypes')
                ->whereRaw('LTRIM(RTRIM(InstrumentName)) = ?', [$type])
                ->update(['Description' => config('land_registration.lut_description')]);

            $this->line("  " . str_pad('InstrumentTypes', 46) . " already present");
        } else {
            $db->table('InstrumentTypes')->insert([
                'InstrumentName' => $type,
                'Description' => config('land_registration.lut_description'),
                'IsActive' => 1,
            ]);
            $this->info("  " . str_pad('InstrumentTypes', 46) . " added");
        }

        // 3. The numbering vault. resolveVaultName() falls through every
        //    shared-vault branch for this name and returns it unchanged, so
        //    this type paginates on its own and cannot disturb the Deeds vaults.
        $vault = $db->table('instrument_number_vaults')->where('instrument_type', $type)->first();

        if ($vault && !$this->option('force-vault')) {
            $this->line(sprintf(
                "  %s already present, left untouched at %d/%d/%d",
                str_pad('instrument_number_vaults', 46),
                $vault->current_serial,
                $vault->current_page,
                $vault->current_volume
            ));
            $this->line("  (edit the running numbers from Manage Instrument Types, not from here)");
        } else {
            // A vault that has already issued numbers is not re-seeded without a
            // deliberate answer: the numbers it gave out may already be printed
            // on certificates, and re-issuing them cannot be undone.
            if ($vault) {
                $issued = (int) $vault->current_serial !== (int) $seed['current_serial']
                    || (int) $vault->current_volume !== (int) $seed['current_volume'];

                if ($issued && !$this->confirm(
                    "This vault stands at {$vault->current_serial}/{$vault->current_page}/{$vault->current_volume}. "
                    . 'Re-seeding may re-issue numbers that are already printed on certificates. Continue?',
                    false
                )) {
                    $this->warn('  vault left untouched');

                    return self::SUCCESS;
                }
            }

            $row = $seed;
            if (!Schema::connection('sqlsrv')->hasColumn('instrument_number_vaults', 'current_volume_count')) {
                unset($row['current_volume_count']);
            }

            $db->table('instrument_number_vaults')->updateOrInsert(
                ['instrument_type' => $type],
                $row + ['updated_at' => now()]
            );

            $this->info(sprintf(
                "  %s seeded, first registration will be %d/%d/%d",
                str_pad('instrument_number_vaults', 46),
                $seed['current_serial'] + 1,
                $seed['current_page'] + 1,
                $seed['current_volume']
            ));

            $capacity = (int) config('land_registration.numbering.volume_capacity');
            $used = (int) ($seed['current_volume_count'] ?? 0);
            $this->line(sprintf(
                "  volume %d is recorded as holding %d of %d entries, so it advances to %d after %d more",
                $seed['current_volume'],
                $used,
                $capacity,
                $seed['current_volume'] + 1,
                max(0, $capacity - $used)
            ));
        }

        // 4. The role. Roles are rows read back through User::assignedRoleNames();
        //    assign it to users from the existing User Management screen.
        if ($db->table('user_roles')->where('name', $role)->exists()) {
            $this->line("  " . str_pad('user_roles', 46) . " already present");
        } else {
            $db->table('user_roles')->insert([
                'name' => $role,
                'guard_name' => 'web',
                'description' => 'Capture and register instruments in the Land Registry',
                'is_active' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $this->info("  " . str_pad('user_roles', 46) . " added");
        }

        $this->newLine();
        $this->info('Done. Assign the "' . $role . '" role to users from User Management.');

        return self::SUCCESS;
    }

    /**
     * Add a column if it is not already there.
     *
     * Raw DDL rather than a migration: the migrations ledger lives in MySQL
     * while these tables are in SQL Server, so a migration would be recorded as
     * run whether or not the column actually landed.
     */
    private function ensureColumn(string $table, string $column, string $ddl): void
    {
        $label = str_pad($table . '.' . $column, 46);

        if (Schema::connection('sqlsrv')->hasColumn($table, $column)) {
            $this->line("  {$label} already present");

            return;
        }

        DB::connection('sqlsrv')->statement($ddl);
        $this->info("  {$label} added");
    }
}
