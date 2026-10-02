<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PromoteDistrictStaging extends Command
{
    protected $signature = 'district:promote-staging
        {--dry-run : Show what would happen without writing anything}';

    protected $description = 'Remove LGA names from dbo.districts, then promote district_staging into dbo.districts.';

    public function handle(): int
    {
        $conn   = DB::connection('sqlsrv');
        $dryRun = (bool) $this->option('dry-run');

        $this->step1RemoveLgas($conn, $dryRun);
        $this->step2Promote($conn, $dryRun);

        return self::SUCCESS;
    }

    // -------------------------------------------------------------------------
    // Step 1 — delete LGA names that ended up in dbo.districts
    // -------------------------------------------------------------------------

    private function step1RemoveLgas($conn, bool $dryRun): void
    {
        $this->info('=== Step 1: Remove LGA names from dbo.districts ===');

        // Fetch all LGA names (upper-cased for comparison)
        $lgaNames = $conn->table('lgas')
            ->whereNotNull('name')
            ->pluck('name')
            ->map(fn ($n) => strtoupper(trim($n)))
            ->filter()
            ->unique()
            ->values()
            ->all();

        if (empty($lgaNames)) {
            $this->warn('  No LGAs found. Skipping.');
            return;
        }

        // Find districts whose name (upper-cased) matches an LGA
        $toDelete = $conn->table('districts')
            ->whereNotNull('name')
            ->get(['id', 'name'])
            ->filter(fn ($d) => in_array(strtoupper(trim($d->name)), $lgaNames, true));

        if ($toDelete->isEmpty()) {
            $this->info('  No LGA names found in dbo.districts. Nothing to delete.');
            return;
        }

        $this->info("  Found {$toDelete->count()} district rows that are LGA names:");
        $this->table(
            ['id', 'name'],
            $toDelete->map(fn ($d) => [$d->id, $d->name])->toArray()
        );

        if ($dryRun) {
            $this->line("  [dry-run] Would delete {$toDelete->count()} rows.");
            return;
        }

        $ids = $toDelete->pluck('id')->all();
        foreach (array_chunk($ids, 200) as $chunk) {
            $conn->table('districts')->whereIn('id', $chunk)->delete();
        }

        $this->info("  Deleted {$toDelete->count()} LGA rows from dbo.districts.");
    }

    // -------------------------------------------------------------------------
    // Step 2 — promote district_staging → dbo.districts
    // -------------------------------------------------------------------------

    private function step2Promote($conn, bool $dryRun): void
    {
        $this->info('=== Step 2: Promote district_staging → dbo.districts ===');

        // Reload current district names after step-1 deletion
        $existingNames = $conn->table('districts')
            ->whereNotNull('name')
            ->pluck('name')
            ->map(fn ($n) => strtoupper(trim($n)))
            ->flip(); // use as set

        // Also reload LGA names to never re-insert them
        $lgaNames = $conn->table('lgas')
            ->whereNotNull('name')
            ->pluck('name')
            ->map(fn ($n) => strtoupper(trim($n)))
            ->flip();

        $candidates = $conn->table('district_staging')
            ->where('insert_status', 'pending')
            ->orderByDesc('occurrence')
            ->get(['id', 'name', 'slug', 'occurrence']);

        $toInsert  = [];
        $skipIds   = [];
        $lgaIds    = [];
        $usedSlugs = $conn->table('districts')
            ->whereNotNull('slug')
            ->pluck('slug')
            ->flip(); // existing slugs

        foreach ($candidates as $row) {
            $upper = strtoupper(trim($row->name));

            // Skip if it's an LGA name
            if (isset($lgaNames[$upper])) {
                $lgaIds[] = $row->id;
                continue;
            }

            // Skip if already in districts (name match)
            if (isset($existingNames[$upper])) {
                $skipIds[] = $row->id;
                continue;
            }

            // Resolve slug collision by appending occurrence
            $slug = $row->slug;
            if (isset($usedSlugs[$slug])) {
                $slug = $row->slug . '-' . $row->occurrence;
                if (isset($usedSlugs[$slug])) {
                    $skipIds[] = $row->id;
                    continue; // still colliding — skip
                }
            }
            $usedSlugs[$slug]      = true;
            $existingNames[$upper] = true;

            $toInsert[] = [
                'staging_id' => $row->id,
                'name'       => $row->name,
                'slug'       => $slug,
                'occurrence' => $row->occurrence,
            ];
        }

        $this->line("  To insert:          " . count($toInsert));
        $this->line("  Already in table:   " . count($skipIds));
        $this->line("  Skipped (LGA name): " . count($lgaIds));

        if (empty($toInsert)) {
            $this->info('  Nothing to insert.');
        }

        if ($dryRun) {
            $this->line('  [dry-run] Top 20 that would be inserted:');
            $this->table(
                ['name', 'slug', 'occurrence'],
                array_map(fn ($r) => [$r['name'], $r['slug'], $r['occurrence']], array_slice($toInsert, 0, 20))
            );
            return;
        }

        // Insert in chunks
        $inserted = 0;
        $now      = now();

        foreach (array_chunk($toInsert, 100) as $chunk) {
            $rows = array_map(fn ($r) => [
                'name'       => $r['name'],
                'slug'       => $r['slug'],
                'is_active'  => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ], $chunk);

            $conn->table('districts')->insert($rows);
            $inserted += count($chunk);

            // Mark staging rows as inserted
            $stagingIds = array_column($chunk, 'staging_id');
            $conn->table('district_staging')
                ->whereIn('id', $stagingIds)
                ->update(['insert_status' => 'inserted']);
        }

        // Mark already-existing staging rows as skipped
        if (!empty($skipIds)) {
            foreach (array_chunk($skipIds, 500) as $chunk) {
                $conn->table('district_staging')
                    ->whereIn('id', $chunk)
                    ->update(['insert_status' => 'skipped']);
            }
        }

        // Mark LGA-name staging rows as skipped
        if (!empty($lgaIds)) {
            foreach (array_chunk($lgaIds, 500) as $chunk) {
                $conn->table('district_staging')
                    ->whereIn('id', $chunk)
                    ->update(['insert_status' => 'skipped']);
            }
        }

        $totalDistricts = $conn->table('districts')->count();
        $this->info("  Inserted {$inserted} new districts.");
        $this->info("  dbo.districts now has {$totalDistricts} rows total.");
    }
}
