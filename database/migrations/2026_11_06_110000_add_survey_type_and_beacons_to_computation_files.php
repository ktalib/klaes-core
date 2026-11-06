<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Survey Type and Beacon Numbers on Commission Computation File (user, 2026-11-06), as on the I to S.
 * Additive: two nullable columns. A beacon stays on one job only: survey_its_beacons keeps its unique number.
 */
return new class extends Migration
{
    public function up(): void
    {
        $schema = Schema::connection('sqlsrv');

        if (! $schema->hasColumn('survey_computation_files', 'survey_type')) {
            $schema->table('survey_computation_files', function (Blueprint $t) {
                $t->string('survey_type', 100)->nullable();
            });
        }
        if (! $schema->hasColumn('survey_its_beacons', 'survey_computation_file_id')) {
            $schema->table('survey_its_beacons', function (Blueprint $t) {
                $t->unsignedBigInteger('survey_computation_file_id')->nullable()->index('ix_survey_its_beacons_cf');
            });
        }
    }

    public function down(): void
    {
        $schema = Schema::connection('sqlsrv');

        if ($schema->hasColumn('survey_its_beacons', 'survey_computation_file_id')) {
            $schema->table('survey_its_beacons', function (Blueprint $t) {
                $t->dropIndex('ix_survey_its_beacons_cf');
                $t->dropColumn('survey_computation_file_id');
            });
        }
        if ($schema->hasColumn('survey_computation_files', 'survey_type')) {
            $schema->table('survey_computation_files', function (Blueprint $t) {
                $t->dropColumn('survey_type');
            });
        }
    }
};
