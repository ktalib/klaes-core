<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * CO files: a fourth related-file field, RDKN (user, 2026-11-03), beside SIT, GKN and Misc KN
 * (2026_11_02_180000). Additive and nullable; no existing row is changed.
 */
return new class extends Migration
{
    public function up(): void
    {
        $schema = Schema::connection('sqlsrv');
        if (! $schema->hasColumn('survey_co_files', 'related_rdkn_file_no')) {
            $schema->table('survey_co_files', function (Blueprint $t) {
                $t->string('related_rdkn_file_no', 50)->nullable();
            });
        }
    }

    public function down(): void
    {
        $schema = Schema::connection('sqlsrv');
        if ($schema->hasColumn('survey_co_files', 'related_rdkn_file_no')) {
            $schema->table('survey_co_files', function (Blueprint $t) {
                $t->dropColumn('related_rdkn_file_no');
            });
        }
    }
};
