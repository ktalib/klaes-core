<?php

namespace Database\Seeders;

use App\Models\Gender;
use App\Services\GenderNormalizer;
use Illuminate\Database\Seeder;

/**
 * Seeds the `genders` lookup with the client's four values — the same list
 * GenderNormalizer::CANON enforces on the write paths. Government bodies fold into
 * Corporate; there is deliberately no fifth value.
 *
 * Re-run safely: php artisan db:seed --class=GenderSeeder
 */
class GenderSeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            ['name' => GenderNormalizer::MALE,      'code' => 'M', 'sort_order' => 1],
            ['name' => GenderNormalizer::FEMALE,    'code' => 'F', 'sort_order' => 2],
            ['name' => GenderNormalizer::CORPORATE, 'code' => 'C', 'sort_order' => 3],
            ['name' => GenderNormalizer::JOINT,     'code' => 'J', 'sort_order' => 4],
        ];

        foreach ($rows as $row) {
            Gender::updateOrCreate(
                ['name' => $row['name']],
                ['code' => $row['code'], 'sort_order' => $row['sort_order'], 'is_active' => true]
            );
        }

        // Anything else that reached the table is not a value the write paths accept.
        $deactivated = Gender::whereNotIn('name', GenderNormalizer::CANON)
            ->where('is_active', true)
            ->update(['is_active' => false]);

        Gender::flushOptionsCache();

        $this->command?->info('Seeded ' . count($rows) . ' genders, deactivated ' . $deactivated . ' non-canonical entries.');
    }
}
