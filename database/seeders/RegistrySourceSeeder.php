<?php

namespace Database\Seeders;

use App\Services\RegistrySourceService;
use Illuminate\Database\Seeder;

/**
 * Seeds the registry_sources lookup rows (SLTR, Cadastral, KANGIS, Physical
 * Planning) and backfills the existing on-disk folders/documents.
 *
 * Idempotent — re-running updates rows in place and never duplicates. The
 * heavy folder scan is delegated to the same logic behind `php artisan
 * registry:sync`, so a fresh environment can be primed with a single seed.
 *
 *   php artisan db:seed --class=Database\\Seeders\\RegistrySourceSeeder
 */
class RegistrySourceSeeder extends Seeder
{
    public function run(): void
    {
        /** @var RegistrySourceService $service */
        $service = app(RegistrySourceService::class);

        $lookups = $service->syncLookups();
        $this->command?->info("Seeded {$lookups} registry lookup row(s).");

        $stats = $service->sync();
        $this->command?->info(
            "Backfilled {$stats['folders']} folder(s) and {$stats['documents']} document(s) "
            . "across {$stats['registries']} registry(ies)."
        );

        foreach ($stats['skipped'] as $s) {
            $this->command?->warn("Skipped {$s}");
        }
    }
}
