<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Computation sheet.xlsx, as it is written (user, 2026-11-06): the Computation Sheet tab of Farm Assessment, grouped
 * by sheet. Text columns on purpose: cells hold "allocated", "Q16/Q17" and the like, and they show as written.
 * Separate from the compensation records; nothing here is a farmer, a land or an allocation line.
 */
return new class extends Migration
{
    public function up(): void
    {
        $schema = Schema::connection('sqlsrv');

        if (! $schema->hasTable('survey_computation_sheet_rows')) {
            $schema->create('survey_computation_sheet_rows', function (Blueprint $t) {
                $t->id();
                $t->string('import_ref', 40)->index('ix_survey_cs_rows_ref');
                $t->string('source_file', 255);
                $t->string('sheet_name', 100);
                $t->integer('source_row');
                $t->string('kind', 10);
                $t->string('farm_id', 100)->nullable();
                $t->string('tag_no', 100)->nullable();
                $t->string('sn_written', 50)->nullable();
                $t->string('first_name', 255)->nullable();
                $t->string('middle_name', 255)->nullable();
                $t->string('last_name', 255)->nullable();
                $t->string('sheet_no', 100)->nullable();
                $t->string('area_m2', 60)->nullable();
                $t->string('area_ha', 60)->nullable();
                $t->string('creatable', 60)->nullable();
                $t->string('govt_50', 60)->nullable();
                $t->string('farm_50', 60)->nullable();
                $t->string('plots', 500)->nullable();
                $t->string('remarks', 1000)->nullable();
                $t->text('raw')->nullable();
                $t->unsignedBigInteger('created_by')->nullable();
                $t->timestamps();
                $t->index(['sheet_name', 'source_row'], 'ix_survey_cs_rows_sheet');
            });
        }
    }

    public function down(): void
    {
        Schema::connection('sqlsrv')->dropIfExists('survey_computation_sheet_rows');
    }
};
