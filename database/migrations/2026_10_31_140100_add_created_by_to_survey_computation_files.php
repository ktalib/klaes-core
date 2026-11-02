<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// SurveyModel stamps created_by / updated_by on every Survey record; survey_computation_files lacked created_by.
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('sqlsrv')->table('survey_computation_files', function (Blueprint $t) {
            $t->unsignedBigInteger('created_by')->nullable();
        });
    }

    public function down(): void
    {
        Schema::connection('sqlsrv')->table('survey_computation_files', fn (Blueprint $t) => $t->dropColumn('created_by'));
    }
};
