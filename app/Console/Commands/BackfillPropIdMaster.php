<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;

class BackfillPropIdMaster extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'propid:backfill';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Syncs all existing prop_ids from legacy tables to PropID_Master to prevent allocator collisions.';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $db = DB::connection('sqlsrv');
        $now = Carbon::now()->toDateTimeString();
        $inserted = 0;
        $skipped  = 0;

        $this->info("Loading existing PropID_Master entries...");
        $existing = $db->table('PropID_Master')
            ->whereNotNull('prop_id')
            ->pluck('prop_id')
            ->flip()
            ->all();
        $this->comment("  Found: " . count($existing) . " existing entries");

        // ---- SOURCE 1: instrument_capture ----
        $this->info("\nProcessing instrument_capture...");
        $db->table('instrument_capture')
            ->whereNotNull('prop_id')
            ->whereRaw("ISNUMERIC(prop_id) = 1")
            ->whereRaw("TRY_CAST(prop_id AS bigint) > 0")
            ->whereRaw("TRY_CAST(prop_id AS bigint) < 2147483647")
            ->orderBy('prop_id')
            ->select(['prop_id', 'mlsFNo', 'temp_fileno', 'id'])
            ->chunk(100, function ($rows) use ($db, &$existing, &$inserted, &$skipped, $now) {
                $toInsert = [];
                foreach ($rows as $row) {
                    $pid = (string) $row->prop_id;
                    if (isset($existing[$pid])) {
                        $skipped++;
                        continue;
                    }
                    $existing[$pid] = 1;
                    $toInsert[] = [
                        'prop_id'              => (int) $pid,
                        'primary_file_number'  => 'BACKFILL-' . $pid,
                        'mlsFNo'               => $row->mlsFNo,
                        'temp_fileno'          => $row->temp_fileno,
                        'source_table'         => 'instrument_capture',
                        'source_record_id'     => (int) $row->id,
                        'status'               => 'active',
                        'notes'                => 'backfill-auto',
                        'created_at'           => $now,
                        'updated_at'           => $now,
                    ];
                }
                if (!empty($toInsert)) {
                    $db->table('PropID_Master')->insert($toInsert);
                    $inserted += count($toInsert);
                    $this->line("  Inserted batch: " . count($toInsert) . " | Total inserted: $inserted");
                }
            });

        // ---- SOURCE 2: pra ----
        $this->info("\nProcessing pra...");
        $db->table('pra')
            ->whereNotNull('prop_id')
            ->whereRaw("ISNUMERIC(prop_id) = 1")
            ->whereRaw("TRY_CAST(prop_id AS bigint) > 0")
            ->whereRaw("TRY_CAST(prop_id AS bigint) < 2147483647")
            ->where('is_deleted', '!=', 1)
            ->orderBy('prop_id')
            ->select(['prop_id', 'mlsFNo', 'temp_fileno', 'id'])
            ->chunk(100, function ($rows) use ($db, &$existing, &$inserted, &$skipped, $now) {
                $toInsert = [];
                foreach ($rows as $row) {
                    $pid = (string) $row->prop_id;
                    if (isset($existing[$pid])) {
                        $skipped++;
                        continue;
                    }
                    $existing[$pid] = 1;
                    $toInsert[] = [
                        'prop_id'             => (int) $pid,
                        'primary_file_number' => 'BACKFILL-' . $pid,
                        'mlsFNo'              => $row->mlsFNo,
                        'temp_fileno'         => $row->temp_fileno,
                        'source_table'        => 'pra',
                        'source_record_id'    => (int) $row->id,
                        'status'              => 'active',
                        'notes'               => 'backfill-auto',
                        'created_at'          => $now,
                        'updated_at'          => $now,
                    ];
                }
                if (!empty($toInsert)) {
                    $db->table('PropID_Master')->insert($toInsert);
                    $inserted += count($toInsert);
                    $this->line("  Inserted batch: " . count($toInsert) . " | Total inserted: $inserted");
                }
            });

        $this->info("\n=== BACKFILL COMPLETE ===");
        $this->info("Newly inserted: $inserted");
        $this->info("Already existed (skipped): $skipped");
        
        return 0;
    }
}
