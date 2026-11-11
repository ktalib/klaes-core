<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 | Survey Update 5g-5i (user, 2026-11-10): Survey Plan Upload. A very short form, on KLAES and on the
 | MLPP-SG portal: where the plan is (plot, district, LGA), who drew it, the type (Final Survey Plan or
 | Application Plan) and the unit it goes to (GKN for public / government, Examination/Verification for
 | private). The unit lists it under Checking & Passing (Survey Plan) > Uploaded Survey Plan and is told.
 | The plan file is kept on the private disk: a plan has no indexed file to file it under in EDMS.
 */
return new class extends Migration
{
    public function up(): void
    {
        $schema = Schema::connection('sqlsrv');
        if ($schema->hasTable('survey_plan_uploads')) {
            return;
        }
        $schema->create('survey_plan_uploads', function (Blueprint $t) {
            $t->id();
            $t->string('upload_ref', 30)->unique();
            $t->string('unit', 40)->index();             // GKN | Examination/Verification
            $t->string('plan_type', 40);                 // Final Survey Plan | Application Plan
            $t->string('plot_no', 100)->nullable();
            $t->string('district', 150)->nullable();
            $t->string('lga', 150)->nullable();
            $t->string('location', 500)->nullable();     // district + LGA (+ state); never the plot
            $t->unsignedBigInteger('surveyors_list_id')->nullable();
            $t->string('drawn_by', 255);
            $t->string('remarks', 1000)->nullable();
            $t->string('file_path', 500);
            $t->string('file_name', 255)->nullable();
            $t->string('file_mime', 120)->nullable();
            $t->unsignedBigInteger('file_size')->nullable();
            $t->string('source', 20)->default('klaes');  // klaes | portal
            $t->unsignedBigInteger('surveyor_portal_account_id')->nullable()->index();
            $t->string('uploaded_by_name', 255)->nullable();
            $t->string('status', 30)->default('Submitted');
            $t->unsignedBigInteger('created_by')->nullable();
            $t->unsignedBigInteger('updated_by')->nullable();
            $t->timestamps();
            $t->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::connection('sqlsrv')->dropIfExists('survey_plan_uploads');
    }
};
