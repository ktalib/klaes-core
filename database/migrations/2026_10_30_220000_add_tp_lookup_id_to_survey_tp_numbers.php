<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * TP Nos register is filled from tp_lookups (user, 2026-10-30: "the source should be the
 * tp no table, but only the valid tp numbers"). tp_lookup_id links a row to the lookup it
 * came from (and lets survey:sync-tp-lookups --undo find them); the lookups carry no
 * layout name, so layout_name may now be empty.
 */
return new class extends Migration
{
    public function up(): void
    {
        $schema = Schema::connection('sqlsrv');
        if (! $schema->hasColumn('survey_tp_numbers', 'tp_lookup_id')) {
            $schema->table('survey_tp_numbers', function (Blueprint $t) {
                $t->integer('tp_lookup_id')->nullable()->index();
            });
        }
        DB::connection('sqlsrv')->statement('ALTER TABLE survey_tp_numbers ALTER COLUMN layout_name NVARCHAR(255) NULL');
    }

    public function down(): void
    {
        Schema::connection('sqlsrv')->table('survey_tp_numbers', function (Blueprint $t) {
            $t->dropIndex(['tp_lookup_id']);
            $t->dropColumn('tp_lookup_id');
        });
    }
};
