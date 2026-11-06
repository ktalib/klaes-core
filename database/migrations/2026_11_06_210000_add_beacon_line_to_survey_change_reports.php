<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The Private Beacon Register line a Surveyor's Report (Private) was raised from, when that line has no file number
 * to match it by (user, 2026-11-06). Nullable: other reports match by file number or come from a Register File line.
 */
return new class extends Migration
{
    public function up(): void
    {
        $schema = Schema::connection('sqlsrv');

        if (! $schema->hasColumn('survey_change_reports', 'survey_beacon_register_id')) {
            $schema->table('survey_change_reports', function (Blueprint $t) {
                $t->unsignedBigInteger('survey_beacon_register_id')->nullable()->index('ix_survey_change_reports_beacon_line');
            });
        }
    }

    public function down(): void
    {
        $schema = Schema::connection('sqlsrv');

        if ($schema->hasColumn('survey_change_reports', 'survey_beacon_register_id')) {
            $schema->table('survey_change_reports', function (Blueprint $t) {
                $t->dropIndex('ix_survey_change_reports_beacon_line');
                $t->dropColumn('survey_beacon_register_id');
            });
        }
    }
};
