<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * CO file indexing / commissioning (user, 2026-10-30): an optional related SIT
 * file, picked in the global file number selector, indexed or not.
 */
return new class extends Migration
{
    public function up(): void
    {
        $schema = Schema::connection('sqlsrv');
        if (! $schema->hasColumn('survey_co_files', 'related_file_no')) {
            $schema->table('survey_co_files', function (Blueprint $t) {
                $t->string('related_file_no', 50)->nullable()->index();
            });
        }
    }

    public function down(): void
    {
        $schema = Schema::connection('sqlsrv');
        if ($schema->hasColumn('survey_co_files', 'related_file_no')) {
            $schema->table('survey_co_files', function (Blueprint $t) {
                $t->dropIndex(['related_file_no']);
                $t->dropColumn('related_file_no');
            });
        }
    }
};
