<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The Instruction to Survey names its Computation File (user, 2026-11-02: the Commission Computation
 * File form no longer picks an I to S; the I to S form picks the Computation File No. instead).
 * Additive: the link and the number as it was when picked.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::connection('sqlsrv')->hasTable('survey_its') || Schema::connection('sqlsrv')->hasColumn('survey_its', 'survey_computation_file_id')) {
            return;
        }
        Schema::connection('sqlsrv')->table('survey_its', function (Blueprint $t) {
            $t->unsignedBigInteger('survey_computation_file_id')->nullable()->index();
            $t->string('computation_no', 50)->nullable();
        });
    }

    public function down(): void
    {
        if (!Schema::connection('sqlsrv')->hasColumn('survey_its', 'survey_computation_file_id')) {
            return;
        }
        Schema::connection('sqlsrv')->table('survey_its', function (Blueprint $t) {
            $t->dropIndex(['survey_computation_file_id']);
            $t->dropColumn(['survey_computation_file_id', 'computation_no']);
        });
    }
};
