<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 | CR 11112026-2, items 4-7 (user, 2026-11-11): Survey Plan Upload in four stages, with reference beacons.
 |   1 Upload              the plan as sent (survey_plan_uploads.file_*) + its reference beacons;
 |   2 Correction          the checking unit marks the plan up and sends it back to the uploader;
 |   3 Final copy          the uploader's corrected plan, straight to the SG/DSG for signing;
 |   4 Signed copy         the SG/DSG's signed plan, digital or manual (download, print, scan, upload).
 | Every file after the first is a survey_plan_files row; nothing already stored changes. Additive only.
 | survey_extant_beacons holds the Extant_Beacons.xlsx list (names only) for the reference beacon dropdown.
 */
return new class extends Migration
{
    public function up(): void
    {
        $schema = Schema::connection('sqlsrv');

        if (! $schema->hasColumn('survey_plan_uploads', 'stage')) {
            $schema->table('survey_plan_uploads', function (Blueprint $t) {
                $t->unsignedTinyInteger('stage')->default(1);           // 1..4, see above
                $t->string('signing_method', 20)->nullable();          // digital | manual
            });
        }

        if (! $schema->hasTable('survey_plan_files')) {
            $schema->create('survey_plan_files', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('survey_plan_upload_id')->index();
                $t->unsignedTinyInteger('stage');
                $t->string('kind', 30);                                 // correction | final | signed
                $t->string('file_path', 500);
                $t->string('file_name', 255)->nullable();
                $t->string('file_mime', 120)->nullable();
                $t->unsignedBigInteger('file_size')->nullable();
                $t->string('note', 2000)->nullable();
                $t->unsignedBigInteger('by_user_id')->nullable();
                $t->unsignedBigInteger('by_portal_account_id')->nullable();
                $t->string('by_name', 255)->nullable();
                $t->timestamps();
            });
        }

        if (! $schema->hasTable('survey_plan_reference_beacons')) {
            $schema->create('survey_plan_reference_beacons', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('survey_plan_upload_id')->index();
                $t->string('beacon_no', 60);
                $t->string('coord_x', 40)->nullable();                 // as entered: identifiers of a survey, kept exact
                $t->string('coord_y', 40)->nullable();
                $t->string('source', 20)->default('typed');            // captured | extant | typed
                $t->unsignedSmallInteger('sort')->default(0);
                $t->timestamps();
            });
        }

        if (! $schema->hasTable('survey_extant_beacons')) {
            $schema->create('survey_extant_beacons', function (Blueprint $t) {
                $t->id();
                $t->integer('object_id')->nullable();
                $t->string('beacon_name', 60);
                $t->string('name_key', 60)->index();                    // upper case, spaces removed
                $t->string('import_ref', 40)->index();
                $t->timestamp('created_at')->nullable();
            });
        }
    }

    public function down(): void
    {
        $schema = Schema::connection('sqlsrv');
        $schema->dropIfExists('survey_extant_beacons');
        $schema->dropIfExists('survey_plan_reference_beacons');
        $schema->dropIfExists('survey_plan_files');
        if ($schema->hasColumn('survey_plan_uploads', 'stage')) {
            $schema->table('survey_plan_uploads', function (Blueprint $t) {
                $t->dropColumn(['stage', 'signing_method']);
            });
        }
    }
};
