<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Record Plot Showing picks the layout's TP No. from the Public Layout Plans register (the KANO LAYOUTS drawing
 * register, user 2026-11-03), or Other with the number typed. tp_no keeps the number as shown either way;
 * survey_tp_number_id is set only when it was picked from the register.
 */
return new class extends Migration
{
    public function up(): void
    {
        $schema = Schema::connection('sqlsrv');
        $schema->table('survey_op_showings', function (Blueprint $t) use ($schema) {
            if (! $schema->hasColumn('survey_op_showings', 'survey_tp_number_id')) $t->unsignedBigInteger('survey_tp_number_id')->nullable()->index();
            if (! $schema->hasColumn('survey_op_showings', 'tp_no')) $t->string('tp_no', 100)->nullable();
        });
    }

    public function down(): void
    {
        Schema::connection('sqlsrv')->table('survey_op_showings', function (Blueprint $t) {
            $t->dropIndex(['survey_tp_number_id']);
            $t->dropColumn(['survey_tp_number_id', 'tp_no']);
        });
    }
};
