<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cadastral Surveyors from the Survey Department staff list (user, 2026-10-27: "Update the
 * Surveyors under Cadastral with the List of Surveyors from Survey"). Additive: a link from
 * each copied cadastral_surveyors row to its surveyors_list row, so the copy is kept in step
 * (App\Services\Cadastral\SurveyStaffSync) and never duplicated.
 */
return new class extends Migration
{
    public function up(): void
    {
        $schema = Schema::connection('sqlsrv');
        if (!$schema->hasColumn('cadastral_surveyors', 'surveyors_list_id')) {
            $schema->table('cadastral_surveyors', function (Blueprint $table) {
                $table->unsignedBigInteger('surveyors_list_id')->nullable();
                $table->index('surveyors_list_id', 'cad_surveyors_staff_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::connection('sqlsrv')->table('cadastral_surveyors', function (Blueprint $table) {
            $table->dropIndex('cad_surveyors_staff_idx');
            $table->dropColumn('surveyors_list_id');
        });
    }
};
