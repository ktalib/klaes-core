<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Survey field apps, offline (user, 2026-10-25): the SCF app keeps cases, photos and
 * field readings on the phone while offline and syncs them later.
 *
 * - client_uuid on survey_comp_cases and survey_its_observations: the phone's own id for
 *   a record, so a sync that is retried after a dropped connection never saves twice.
 * - survey_case_photos: geo-tagged photos of the land and assets, kept with the case
 *   (user decision: a Survey-only store, not EDMS).
 *
 * Additive only.
 */
return new class extends Migration
{
    public function up(): void
    {
        $s = Schema::connection('sqlsrv');
        $db = DB::connection('sqlsrv');

        foreach (['survey_comp_cases' => 'ux_survey_cases_client_uuid', 'survey_its_observations' => 'ux_survey_obs_client_uuid'] as $table => $index) {
            if (! $s->hasColumn($table, 'client_uuid')) {
                $s->table($table, fn (Blueprint $t) => $t->string('client_uuid', 40)->nullable());
                $db->statement("CREATE UNIQUE INDEX {$index} ON {$table} (client_uuid) WHERE client_uuid IS NOT NULL");
            }
        }

        if (! $s->hasTable('survey_case_photos')) {
            $s->create('survey_case_photos', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('survey_comp_case_id')->index();
                $t->string('client_uuid', 40)->nullable();
                $t->string('path', 500);
                $t->string('original_name', 255)->nullable();
                $t->string('mime', 100)->nullable();
                $t->unsignedInteger('size')->nullable();
                $t->decimal('latitude', 11, 7)->nullable();
                $t->decimal('longitude', 11, 7)->nullable();
                $t->decimal('accuracy_m', 10, 2)->nullable();
                $t->dateTime('taken_at')->nullable();
                $t->string('caption', 255)->nullable();
                $t->unsignedBigInteger('created_by')->nullable();
                $t->unsignedBigInteger('updated_by')->nullable();
                $t->timestamps();
                $t->softDeletes();
            });
            $db->statement('CREATE UNIQUE INDEX ux_survey_case_photos_client_uuid ON survey_case_photos (client_uuid) WHERE client_uuid IS NOT NULL');
        }
    }

    public function down(): void
    {
        $s = Schema::connection('sqlsrv');
        $db = DB::connection('sqlsrv');
        $s->dropIfExists('survey_case_photos');
        foreach (['survey_comp_cases' => 'ux_survey_cases_client_uuid', 'survey_its_observations' => 'ux_survey_obs_client_uuid'] as $table => $index) {
            if ($s->hasColumn($table, 'client_uuid')) {
                $db->statement("DROP INDEX {$index} ON {$table}");
                $s->table($table, fn (Blueprint $t) => $t->dropColumn('client_uuid'));
            }
        }
    }
};
