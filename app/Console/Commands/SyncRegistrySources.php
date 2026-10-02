<?php

namespace App\Console\Commands;

use App\Services\RegistrySourceService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Scan the on-disk registry folders and import new file folders / scanned
 * documents into the database. Idempotent — safe to run on production whenever
 * synchronisation is required (e.g. on a cron, or after new scans are dropped).
 *
 *   php artisan registry:sync                 # all active registries
 *   php artisan registry:sync --registry=SLTR # one registry (by code)
 *   php artisan registry:sync --lookups       # only refresh the lookup table
 */
class SyncRegistrySources extends Command
{
    protected $signature = 'registry:sync
                            {--registry= : Limit the scan to a single registry code (e.g. SLTR, CAD, KANGIS, PP)}
                            {--lookups : Only seed/refresh the registry_sources lookup rows, then exit}';

    protected $description = 'Scan registry folders and import file folders + scanned documents (idempotent)';

    public function __construct(protected RegistrySourceService $service)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $base = config('registry_sources.base_path');
        $this->info("Registry source base path: {$base}");

        if ($this->option('lookups')) {
            $n = $this->service->syncLookups();
            $this->info("✓ Synced {$n} registry lookup row(s).");
            return self::SUCCESS;
        }

        $code = $this->option('registry') ?: null;
        if ($code) {
            $this->info("Scanning registry: {$code}");
        } else {
            $this->info('Scanning all active registries…');
        }

        try {
            $stats = $this->service->sync($code);

            $this->newLine();
            $this->info("✓ Sync complete.");
            $this->line("  Registries scanned : {$stats['registries']}");
            $this->line("  Folders processed  : {$stats['folders']}");
            $this->line("  Documents found    : {$stats['documents']}");

            foreach ($stats['details'] as $d) {
                $this->line("  • {$d}");
            }
            foreach ($stats['skipped'] as $s) {
                $this->warn("  ! Skipped {$s}");
            }

            Log::info('registry:sync completed', $stats);
            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('registry:sync failed: ' . $e->getMessage());
            Log::error('registry:sync error: ' . $e->getMessage(), ['exception' => $e]);
            return self::FAILURE;
        }
    }
}
