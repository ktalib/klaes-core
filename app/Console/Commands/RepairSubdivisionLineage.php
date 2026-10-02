<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RepairSubdivisionLineage extends Command
{
    protected $signature = 'subdivision:repair-lineage
                            {--force : Actually execute the inserts (default is dry-run)}';

    protected $description = 'Insert missing file_indexing_links for subdivision RES-RC-1982-731 → RES-2026-2225/2226/2227';

    private const MOTHER_FILE = 'RES-RC-1982-731';
    private const FRAGMENTS   = ['RES-2026-2225', 'RES-2026-2226', 'RES-2026-2227'];
    // Archived file_indexings id for mother file (now in deprecated_records only)
    private const MOTHER_FI_ARCHIVED_ID = 87725;

    public function handle(): int
    {
        $dryRun = !$this->option('force');
        $conn   = DB::connection('sqlsrv');

        $this->line('');
        $this->info('=== Subdivision Lineage Repair: ' . self::MOTHER_FILE . ' ===');
        $this->line('Fragments: ' . implode(', ', self::FRAGMENTS));
        $this->line('');

        // 1. Fetch fragment file_indexings records
        $fragments = $conn->table('file_indexings')
            ->whereIn('file_number', self::FRAGMENTS)
            ->get(['id', 'file_number', 'land_use_type', 'plot_number', 'tp_no', 'location', 'lga', 'tracking_id', 'file_title']);

        if ($fragments->isEmpty()) {
            $this->error('No file_indexings records found for fragments. Aborting.');
            return 1;
        }

        $this->line("Found {$fragments->count()} fragment file_indexings record(s):");
        foreach ($fragments as $f) {
            $this->line("  id={$f->id}  file_number={$f->file_number}  tracking_id={$f->tracking_id}");
        }

        // 2. Check for existing links to avoid duplicates
        $existingD1 = $conn->table('file_indexing_links')
            ->whereIn('file_indexing_id', $fragments->pluck('id')->toArray())
            ->where('file_number', self::MOTHER_FILE)
            ->where('indexing_type', 'lineage_link')
            ->count();

        $existingD2 = $conn->table('file_indexing_links')
            ->where('file_indexing_id', self::MOTHER_FI_ARCHIVED_ID)
            ->whereIn('file_number', self::FRAGMENTS)
            ->where('indexing_type', 'lineage_link')
            ->count();

        $this->line('');
        $this->line("Existing Direction-1 links (fragment → mother): {$existingD1}");
        $this->line("Existing Direction-2 links (mother → fragment): {$existingD2}");

        $now           = now();
        $createdBy     = 'System (Subdivision Lineage Repair)';
        $linksToInsert = [];

        foreach ($fragments as $ni) {
            // Direction 1: new fragment file_indexings.id → mother file_number string
            if ($existingD1 === 0) {
                $linksToInsert[] = [
                    'file_indexing_id' => $ni->id,
                    'file_number'      => self::MOTHER_FILE,
                    'file_title'       => 'Parent/Source File',
                    'land_use_type'    => $ni->land_use_type,
                    'plot_number'      => $ni->plot_number,
                    'tp_no'            => $ni->tp_no,
                    'location'         => $ni->location,
                    'lga'              => $ni->lga,
                    'tracking_id'      => $ni->tracking_id,
                    'indexing_type'    => 'lineage_link',
                    'workflow_status'  => 'indexed',
                    'created_by'       => $createdBy,
                    'created_at'       => $now,
                    'updated_at'       => $now,
                ];
            }

            // Direction 2: archived mother id → new fragment file_number string
            if ($existingD2 === 0) {
                $linksToInsert[] = [
                    'file_indexing_id' => self::MOTHER_FI_ARCHIVED_ID,
                    'file_number'      => $ni->file_number,
                    'file_title'       => 'Subdivision/Merger/Link',
                    'land_use_type'    => $ni->land_use_type,
                    'plot_number'      => $ni->plot_number,
                    'tp_no'            => $ni->tp_no,
                    'location'         => $ni->location,
                    'lga'              => $ni->lga,
                    'tracking_id'      => $ni->tracking_id,
                    'indexing_type'    => 'lineage_link',
                    'workflow_status'  => 'indexed',
                    'created_by'       => $createdBy,
                    'created_at'       => $now,
                    'updated_at'       => $now,
                ];
            }
        }

        if (empty($linksToInsert)) {
            $this->info('All lineage links already exist — nothing to insert.');
            return 0;
        }

        $this->line('');
        $this->info("Links to insert: " . count($linksToInsert));
        foreach ($linksToInsert as $link) {
            $dir = $link['file_title'] === 'Parent/Source File' ? 'D1' : 'D2';
            $this->line("  [{$dir}] file_indexing_id={$link['file_indexing_id']}  file_number={$link['file_number']}");
        }

        if ($dryRun) {
            $this->line('');
            $this->warn('DRY RUN — no changes made. Run with --force to execute.');
            return 0;
        }

        $conn->table('file_indexing_links')->insert($linksToInsert);
        $this->line('');
        $this->info('✓ Inserted ' . count($linksToInsert) . ' lineage link(s) into file_indexing_links.');

        return 0;
    }
}
