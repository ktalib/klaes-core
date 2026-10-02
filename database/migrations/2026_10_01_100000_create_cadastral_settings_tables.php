<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cadastral settings — the values System Admin -> Configurable Entries ->
 * Cadastral edits (rebuild plan D4).
 *
 *   cadastral_settings            scalar and list settings, one row per key. The
 *                                 key is the config path under cadastral_module
 *                                 ('fee_sheet.rates.beacon'), so a key with no row
 *                                 falls back to config/cadastral_module.php.
 *   cadastral_area_fee_schedule   the Kano S.L.N. No. 3 of 1983 hectare schedule
 *                                 from the official fee sheet.
 *   cadastral_transport_bands     the transport distance bands.
 *
 * App\Services\Cadastral\CadastralSettings reads these and falls back to config
 * while they are missing, so the module works the same before and after this
 * runs. The tab itself only appears once cadastral_settings exists.
 *
 * Creates tables only. No rows: defaults are inserted by
 * Database\Seeders\CadastralSettingsSeeder, which only fills what is missing and
 * never overwrites an admin's edit.
 *
 * NO FOREIGN KEYS. updated_by is a users.id held as a plain integer, as on the
 * other cadastral_* tables: a hard delete elsewhere must not fail on account of
 * a settings row.
 *
 * EVERY create() IS GUARDED BY hasTable(), so a part-applied run can be re-run.
 * down() drops only these three tables, and only if they exist.
 *
 * DEPLOY WITH THE PATH FLAG. A bare migrate would run 27 unrelated pending
 * migrations against production, and --pretend is NOT a dry run on sqlsrv (it
 * executes):
 *
 *   php artisan migrate --database=sqlsrv \
 *     --path=database/migrations/2026_10_01_100000_create_cadastral_settings_tables.php --force
 *
 * then the defaults:
 *
 *   php artisan db:seed --database=sqlsrv --class="Database\Seeders\CadastralSettingsSeeder" --force
 */
return new class extends Migration
{
    private const CONN = 'sqlsrv';

    public function up(): void
    {
        $schema = Schema::connection(self::CONN);

        if (! $schema->hasTable('cadastral_settings')) {
            $schema->create('cadastral_settings', function (Blueprint $t) {
                $t->id();
                $t->string('key', 100)->unique();
                $t->text('value')->nullable();                    // nvarchar(max)
                $t->string('value_type', 20)->default('string');  // money|decimal|int|bool|string|choice|list
                $t->string('label', 255)->nullable();
                $t->string('group', 50)->nullable()->index();
                $t->unsignedBigInteger('updated_by')->nullable(); // users.id, no FK
                $t->timestamps();
            });
        }

        if (! $schema->hasTable('cadastral_area_fee_schedule')) {
            $schema->create('cadastral_area_fee_schedule', function (Blueprint $t) {
                $t->id();
                $t->decimal('hectares', 10, 2);
                $t->decimal('current_fee', 14, 2)->nullable();
                $t->decimal('proposed_fee', 14, 2)->nullable();
                // The sheet's two "per add hctr" columns, verbatim (open question Q2).
                $t->string('additional_note', 255)->nullable();
                $t->string('proposed_additional_note', 255)->nullable();
                $t->integer('sort_order')->default(0);
                $t->boolean('is_active')->default(true);
                $t->unsignedBigInteger('updated_by')->nullable(); // users.id, no FK
                $t->timestamps();

                $t->index('hectares');
            });
        }

        if (! $schema->hasTable('cadastral_transport_bands')) {
            $schema->create('cadastral_transport_bands', function (Blueprint $t) {
                $t->id();
                $t->string('label', 150);
                $t->decimal('min_km', 10, 2)->default(0);
                $t->decimal('max_km', 10, 2)->nullable();         // null = "and over"
                $t->decimal('fee', 14, 2);
                $t->integer('sort_order')->default(0);
                $t->boolean('is_active')->default(true);
                $t->unsignedBigInteger('updated_by')->nullable(); // users.id, no FK
                $t->timestamps();
            });
        }
    }

    public function down(): void
    {
        $schema = Schema::connection(self::CONN);

        foreach (['cadastral_transport_bands', 'cadastral_area_fee_schedule', 'cadastral_settings'] as $table) {
            if ($schema->hasTable($table)) {
                $schema->drop($table);
            }
        }
    }
};
