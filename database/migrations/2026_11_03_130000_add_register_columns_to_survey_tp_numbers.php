<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Public Layout Plans from Survey's own register (user, 2026-11-03: "KANO LAYOUTS - cleaned", the Ministry's
 * register of layout drawings), replacing the list drawn from the general TP lookup table.
 *   register_sn   the S/N in that register (its drawing-number order)
 *   drawing_date  the DATE column as written (23/04/70, JUN-84, ...); many are partial, so not a date column
 *   check_note    the register's NOTES (CHECK) column: reused numbers, suspect dates, missing titles
 *   source        kano_layouts (the register) or manual (added on the page); older rows stay NULL and are not listed
 */
return new class extends Migration
{
    public function up(): void
    {
        $schema = Schema::connection('sqlsrv');
        $schema->table('survey_tp_numbers', function (Blueprint $t) use ($schema) {
            if (! $schema->hasColumn('survey_tp_numbers', 'register_sn')) $t->integer('register_sn')->nullable()->index();
            if (! $schema->hasColumn('survey_tp_numbers', 'drawing_date')) $t->string('drawing_date', 40)->nullable();
            if (! $schema->hasColumn('survey_tp_numbers', 'check_note')) $t->string('check_note', 500)->nullable();
            if (! $schema->hasColumn('survey_tp_numbers', 'source')) $t->string('source', 40)->nullable()->index();
        });
    }

    public function down(): void
    {
        $schema = Schema::connection('sqlsrv');
        $schema->table('survey_tp_numbers', function (Blueprint $t) {
            $t->dropIndex(['register_sn']);
            $t->dropIndex(['source']);
            $t->dropColumn(['register_sn', 'drawing_date', 'check_note', 'source']);
        });
    }
};
