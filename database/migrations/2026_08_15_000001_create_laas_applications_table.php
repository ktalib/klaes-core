<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The LAAS intake record — the spine of the portal workflow.
 *
 * A public submission lives here and NOT in the live registry: nothing reaches
 * mls_file_no / survey_report_requests / land_recommendations until the Director
 * approves and MLP assigns a file number. From that point `file_number` is the
 * join key the existing Land 12 / Recommendation / RoFO modules are matched on,
 * which is why it carries its own index.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::connection('sqlsrv')->hasTable('laas_applications')) {
            return;
        }

        Schema::connection('sqlsrv')->create('laas_applications', function (Blueprint $table) {
            $table->id();
            $table->string('reference_no', 30)->unique();      // LAAS-2026-000001
            $table->unsignedBigInteger('laas_applicant_id')->nullable();

            // Applicant snapshot, frozen at submission — the account may be
            // edited later, but the application must show what was submitted.
            $table->string('applicant_name', 200)->nullable();
            $table->string('applicant_phone', 30)->nullable();
            $table->string('applicant_email', 150)->nullable();
            $table->string('applicant_address', 500)->nullable();
            $table->string('applicant_nin', 30)->nullable();
            $table->string('applicant_type', 50)->nullable();  // Individual | Corporate | Government

            // What is being applied for.
            $table->string('land_use', 50)->nullable();
            $table->unsignedBigInteger('purpose_id')->nullable();
            $table->unsignedBigInteger('lga_id')->nullable();
            $table->unsignedBigInteger('district_id')->nullable();
            $table->string('location', 500)->nullable();
            $table->string('plot_no', 100)->nullable();
            $table->string('approx_size', 100)->nullable();
            $table->string('existing_allocation_ref', 100)->nullable();
            $table->text('applicant_remarks')->nullable();

            $table->string('stage', 40)->default('draft');

            // Null until MLP allocates; the hooks resolve applications by this.
            $table->string('file_number', 100)->nullable();

            // Filled in as each downstream module acts on the file.
            $table->unsignedBigInteger('survey_report_request_id')->nullable();
            $table->unsignedBigInteger('land_recommendation_id')->nullable();
            $table->unsignedBigInteger('rofo_id')->nullable();

            $table->unsignedBigInteger('director_approved_by')->nullable();
            $table->dateTime('director_approved_at')->nullable();
            $table->text('rejection_reason')->nullable();

            $table->unsignedBigInteger('fileno_assigned_by')->nullable();
            $table->dateTime('fileno_assigned_at')->nullable();

            $table->dateTime('submitted_at')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->timestamps();

            $table->index('file_number');
            $table->index('stage');
            $table->index('laas_applicant_id');
            $table->index('submitted_at');
        });
    }

    public function down(): void
    {
        Schema::connection('sqlsrv')->dropIfExists('laas_applications');
    }
};
