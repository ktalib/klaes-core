<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Compensation complaints (user, 2026-10-25; docs/KLAES-CORE Survey Module.md 2.4):
 * grievances from compensation or OP allocation, linked to the project, case and
 * beneficiary. Open -> Under Investigation -> Resolved / Escalated. Additive only.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::connection('sqlsrv')->hasTable('survey_complaints')) {
            return;
        }

        Schema::connection('sqlsrv')->create('survey_complaints', function (Blueprint $t) {
            $t->id();
            $t->string('complaint_ref', 30)->unique();
            $t->unsignedBigInteger('survey_project_id')->nullable()->index();
            $t->unsignedBigInteger('survey_comp_case_id')->nullable()->index();
            $t->unsignedBigInteger('survey_beneficiary_id')->nullable()->index();
            $t->string('complainant_name', 255);
            $t->string('complainant_phone', 50)->nullable();
            $t->string('category', 100);
            $t->string('subject', 255);
            $t->string('description', 4000)->nullable();
            $t->date('received_date');
            $t->string('status', 30)->default('Open');
            $t->string('resolution', 4000)->nullable();
            $t->timestamp('resolved_at')->nullable();
            $t->unsignedBigInteger('resolved_by')->nullable();
            $t->unsignedBigInteger('created_by')->nullable();
            $t->unsignedBigInteger('updated_by')->nullable();
            $t->timestamps();
            $t->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::connection('sqlsrv')->dropIfExists('survey_complaints');
    }
};
