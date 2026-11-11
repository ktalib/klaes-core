<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 | A farmer the Computation Sheet already holds gets the KMC Access photo and fields on its own row, not a second row
 | (user, 2026-11-11: "check the one we imported before ... so that it won't duplicate"). enriched_ref is the batch that
 | filled them; enriched_tag_no is 1 when it also filled a blank Tag No. Both let --undo put the row back as it was.
 */
return new class extends Migration
{
    public function up(): void
    {
        $s = Schema::connection('sqlsrv');
        $s->table('survey_computation_sheet_rows', function (Blueprint $t) use ($s) {
            if (! $s->hasColumn('survey_computation_sheet_rows', 'enriched_ref')) $t->string('enriched_ref', 40)->nullable()->index('ix_survey_cs_rows_enriched');
            if (! $s->hasColumn('survey_computation_sheet_rows', 'enriched_tag_no')) $t->boolean('enriched_tag_no')->default(false);
        });
    }

    public function down(): void
    {
        $s = Schema::connection('sqlsrv');
        if ($s->hasColumn('survey_computation_sheet_rows', 'enriched_ref')) {
            $s->table('survey_computation_sheet_rows', function (Blueprint $t) {
                $t->dropIndex('ix_survey_cs_rows_enriched');
                $t->dropColumn('enriched_ref');
            });
        }
        if ($s->hasColumn('survey_computation_sheet_rows', 'enriched_tag_no')) {
            $s->table('survey_computation_sheet_rows', fn (Blueprint $t) => $t->dropColumn('enriched_tag_no'));
        }
    }
};
