<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Passing & Checking (Final Survey Plan) under GKN, CR 30102026 item 21 (user, 2026-10-31): a form to check the
// distances on the final survey plan. A first version: Survey will give its own fields later. The lines checked
// (from beacon, to beacon, distance on the plan, distance measured) are kept as JSON on the row. Additive only.
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('sqlsrv')->create('survey_passing_checks', function (Blueprint $t) {
            $t->id();
            $t->string('check_ref', 30)->unique();
            $t->string('file_number', 100)->nullable();
            $t->string('plan_no', 100)->nullable();
            $t->string('computation_no', 30)->nullable();
            $t->unsignedBigInteger('surveyors_list_id')->nullable();
            $t->string('surveyor_name', 255)->nullable();
            $t->decimal('tolerance_m', 8, 3)->nullable();
            $t->text('lines')->nullable();
            $t->string('result', 30);
            $t->date('checked_at');
            $t->unsignedBigInteger('checked_by')->nullable();
            $t->string('remarks', 1000)->nullable();
            $t->unsignedBigInteger('created_by')->nullable();
            $t->unsignedBigInteger('updated_by')->nullable();
            $t->timestamps();
            $t->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::connection('sqlsrv')->dropIfExists('survey_passing_checks');
    }
};
