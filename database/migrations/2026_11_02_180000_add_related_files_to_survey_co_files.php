<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * CO files: one related-file field per kind, SIT, GKN and Misc KN (user, 2026-11-02); with Has
 * Related File = Yes at least one is required. related_file_no stays as the first of them for
 * what already reads it. Existing related numbers are copied into the matching new column
 * (backup: storage/app/backups/survey_co_files_related_before_split_20261102_170525.csv);
 * related_file_no itself is left as it is.
 */
return new class extends Migration
{
    public function up(): void
    {
        $schema = Schema::connection('sqlsrv');
        if (! $schema->hasColumn('survey_co_files', 'related_sit_file_no')) {
            $schema->table('survey_co_files', function (Blueprint $t) {
                $t->string('related_sit_file_no', 50)->nullable();
                $t->string('related_gkn_file_no', 50)->nullable();
                $t->string('related_misckn_file_no', 50)->nullable();
            });
        }

        $db = DB::connection('sqlsrv');
        $db->table('survey_co_files')->whereNull('related_sit_file_no')->where('related_file_no', 'like', 'SIT-%')
            ->update(['related_sit_file_no' => DB::raw('related_file_no')]);
        $db->table('survey_co_files')->whereNull('related_gkn_file_no')->where('related_file_no', 'like', 'GKN-%')
            ->update(['related_gkn_file_no' => DB::raw('related_file_no')]);
        $db->table('survey_co_files')->whereNull('related_misckn_file_no')->where('related_file_no', 'like', 'MISC KN %')
            ->update(['related_misckn_file_no' => DB::raw('related_file_no')]);
    }

    public function down(): void
    {
        $schema = Schema::connection('sqlsrv');
        if ($schema->hasColumn('survey_co_files', 'related_sit_file_no')) {
            $schema->table('survey_co_files', function (Blueprint $t) {
                $t->dropColumn(['related_sit_file_no', 'related_gkn_file_no', 'related_misckn_file_no']);
            });
        }
    }
};
