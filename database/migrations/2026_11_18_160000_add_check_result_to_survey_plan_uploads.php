<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 | Survey plan stage 2, Check (Kunle, 2026-11-18): the checker records whether the plan falls on location and whether
 | there are corrections, then sends it back to the surveyor who drew it. Additive only; plans checked before have none.
 */
return new class extends Migration
{
    private const COLUMNS = ['check_result', 'check_location', 'check_note', 'checked_by', 'checked_by_name', 'checked_at'];

    public function up(): void
    {
        $schema = Schema::connection('sqlsrv');
        if ($schema->hasColumn('survey_plan_uploads', 'check_result')) {
            return;
        }
        $schema->table('survey_plan_uploads', function (Blueprint $t) {
            $t->string('check_result', 20)->nullable();     // corrections | none
            $t->string('check_location', 20)->nullable();   // falls | not_falls
            $t->string('check_note', 2000)->nullable();
            $t->unsignedBigInteger('checked_by')->nullable();
            $t->string('checked_by_name', 255)->nullable();
            $t->dateTime('checked_at')->nullable();
        });
    }

    public function down(): void
    {
        $schema = Schema::connection('sqlsrv');
        foreach (self::COLUMNS as $c) {
            if ($schema->hasColumn('survey_plan_uploads', $c)) {
                $schema->table('survey_plan_uploads', fn (Blueprint $t) => $t->dropColumn($c));
            }
        }
    }
};
