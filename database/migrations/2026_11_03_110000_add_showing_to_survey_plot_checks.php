<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A ground check of an OP's plot answers one OP showing (user, 2026-11-03: "the first step is check
 * record [the OP showing]; the second, from the field, the ground check, against that record").
 * Additive and nullable: layout-plot checks have no showing.
 */
return new class extends Migration
{
    public function up(): void
    {
        $schema = Schema::connection('sqlsrv');
        if (! $schema->hasColumn('survey_plot_checks', 'survey_op_showing_id')) {
            $schema->table('survey_plot_checks', function (Blueprint $t) {
                $t->unsignedBigInteger('survey_op_showing_id')->nullable()->index();
            });
        }
    }

    public function down(): void
    {
        $schema = Schema::connection('sqlsrv');
        if ($schema->hasColumn('survey_plot_checks', 'survey_op_showing_id')) {
            $schema->table('survey_plot_checks', function (Blueprint $t) {
                $t->dropIndex(['survey_op_showing_id']);
                $t->dropColumn('survey_op_showing_id');
            });
        }
    }
};
