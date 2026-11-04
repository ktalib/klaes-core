<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * CO files: a fifth related-file field, MLPP (user, 2026-11-03: "add mlpp to the drop down too, because some of
 * the records have land files"), beside SIT, Misc KN, GKN and RDKN. Additive and nullable; no existing row is changed.
 */
return new class extends Migration
{
    public function up(): void
    {
        $schema = Schema::connection('sqlsrv');
        if (! $schema->hasColumn('survey_co_files', 'related_mlpp_file_no')) {
            $schema->table('survey_co_files', function (Blueprint $t) {
                $t->string('related_mlpp_file_no', 50)->nullable();
            });
        }
    }

    public function down(): void
    {
        $schema = Schema::connection('sqlsrv');
        if ($schema->hasColumn('survey_co_files', 'related_mlpp_file_no')) {
            $schema->table('survey_co_files', function (Blueprint $t) {
                $t->dropColumn('related_mlpp_file_no');
            });
        }
    }
};
