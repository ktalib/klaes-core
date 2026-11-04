<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Resettlement OP page: Existing / New tabs (user, 2026-11-03: "this is when you put all the OPs that are
 * existing ... the generators will only be applicable for new"). An existing OP is captured as written,
 * with its allottee and plot, and is not tied to a case. Additive and nullable; no row is changed.
 */
return new class extends Migration
{
    public function up(): void
    {
        $schema = Schema::connection('sqlsrv');
        if (! $schema->hasColumn('survey_op_records', 'is_existing')) {
            $schema->table('survey_op_records', function (Blueprint $t) {
                $t->boolean('is_existing')->nullable();
                $t->unsignedBigInteger('survey_project_id')->nullable();
                $t->string('allottee_name', 255)->nullable();
                $t->string('plot_no', 100)->nullable();
                $t->string('district', 150)->nullable();
                $t->string('lga', 150)->nullable();
            });
        }
    }

    public function down(): void
    {
        $schema = Schema::connection('sqlsrv');
        if ($schema->hasColumn('survey_op_records', 'is_existing')) {
            $schema->table('survey_op_records', function (Blueprint $t) {
                $t->dropColumn(['is_existing', 'survey_project_id', 'allottee_name', 'plot_no', 'district', 'lga']);
            });
        }
    }
};
