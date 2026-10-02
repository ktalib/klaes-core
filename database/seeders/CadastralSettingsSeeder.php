<?php

namespace Database\Seeders;

use App\Services\Cadastral\CadastralSettings;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Defaults for the Cadastral settings tables, from config/cadastral_module.php.
 *
 * FILLS GAPS, NEVER OVERWRITES. A setting key is inserted only when no row has
 * that key. The schedule and the bands are inserted only into an empty table.
 * Whatever an admin has saved in Configurable Entries -- a changed rate, a row
 * switched off -- is left exactly as it is, so this is safe to run again after
 * a deploy adds a new key.
 *
 * Not registered in DatabaseSeeder: run it by class, after the
 * 2026_10_01_100000 migration:
 *
 *   php artisan db:seed --database=sqlsrv --class="Database\Seeders\CadastralSettingsSeeder" --force
 */
class CadastralSettingsSeeder extends Seeder
{
    private const CONN = 'sqlsrv';

    public function run(): void
    {
        $schema = Schema::connection(self::CONN);
        $db = DB::connection(self::CONN);
        $now = now();

        foreach ([CadastralSettings::TABLE_SETTINGS, CadastralSettings::TABLE_SCHEDULE, CadastralSettings::TABLE_BANDS] as $table) {
            if (! $schema->hasTable($table)) {
                $this->command?->error("{$table} does not exist. Run the 2026_10_01_100000 migration first; nothing was seeded.");

                return;
            }
        }

        // ---- Settings -----------------------------------------------------------
        $existing = $db->table(CadastralSettings::TABLE_SETTINGS)->pluck('key')->all();
        $added = 0;

        foreach (CadastralSettings::DEFINITIONS as $key => $meta) {
            if (in_array($key, $existing, true)) {
                continue;
            }

            $db->table(CadastralSettings::TABLE_SETTINGS)->insert([
                'key' => $key,
                'value' => CadastralSettings::encode(config('cadastral_module.' . $key), $meta['type']),
                'value_type' => $meta['type'],
                'label' => $meta['label'],
                'group' => $meta['group'],
                'updated_by' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $added++;
        }

        $this->command?->info("cadastral_settings: {$added} key(s) added, " . count($existing) . ' already present.');

        // ---- Area-fee schedule and transport bands ------------------------------
        // Seeded only into an EMPTY table. Matching row by row (on hectares, on
        // label) would re-add a default an admin had edited -- a band renamed
        // "1 - 10 km" would come back as a second band -- so any row at all
        // means the table belongs to the admins now.
        foreach ([
            CadastralSettings::TABLE_SCHEDULE => CadastralSettings::defaultScheduleRows(),
            CadastralSettings::TABLE_BANDS => CadastralSettings::defaultBandRows(),
        ] as $table => $rows) {
            $present = $db->table($table)->count();

            if ($present > 0) {
                $this->command?->info("{$table}: {$present} row(s) already present, left as they are.");

                continue;
            }

            foreach ($rows as $row) {
                $db->table($table)->insert($row + ['created_at' => $now, 'updated_at' => $now]);
            }

            $this->command?->info("{$table}: " . count($rows) . ' default row(s) added.');
        }

        CadastralSettings::forget();
    }
}
