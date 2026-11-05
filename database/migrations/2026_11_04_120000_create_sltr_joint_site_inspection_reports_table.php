<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SLTR Joint Site Inspection Reports: a copy of joint_site_inspection_reports kept for
 * Physical Planning > SLTR Applications only, so SLTR reports never mix with the ST /
 * Conversion rows. application_id holds the SLTR file's file_indexings.id.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::connection('sqlsrv')->hasTable('sltr_joint_site_inspection_reports')) {
            return;
        }

        Schema::connection('sqlsrv')->create('sltr_joint_site_inspection_reports', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('application_id', 50)->nullable();
            $table->date('inspection_date')->nullable();
            $table->string('lkn_number', 191)->nullable();
            $table->string('applicant_name', 191)->nullable();
            $table->string('location', 191)->nullable();
            $table->string('plot_number', 191)->nullable();
            $table->string('scheme_number', 191)->nullable();
            $table->boolean('available_on_ground')->default(false);
            $table->text('boundary_description')->nullable();
            $table->string('sections_count', 50)->nullable();
            $table->string('road_reservation', 191)->nullable();
            $table->string('prevailing_land_use', 191)->nullable();
            $table->string('applied_land_use', 191)->nullable();
            $table->text('shared_utilities')->nullable();
            $table->string('compliance_status', 191)->nullable();
            $table->boolean('has_additional_observations')->default(false);
            $table->text('additional_observations')->nullable();
            $table->string('inspection_officer', 191)->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->dateTime('created_at')->nullable();
            $table->dateTime('updated_at')->nullable();
            $table->unsignedBigInteger('sub_application_id')->nullable();
            $table->text('existing_site_measurement_summary')->nullable();
            $table->text('existing_site_measurement_entries')->nullable();
            $table->boolean('is_generated')->default(false);
            $table->boolean('is_submitted')->default(false);
            $table->boolean('is_approved')->default(false);
            $table->string('approved_by', 255)->nullable();
            $table->dateTime('approved_at', 7)->nullable();
            $table->dateTime('generated_at')->nullable();
            $table->dateTime('submitted_at')->nullable();
            $table->string('generated_by', 191)->nullable();
            $table->string('submitted_by', 191)->nullable();
            $table->string('unit_dimension', 255)->nullable();
            $table->string('unit_number', 100)->nullable();
            $table->integer('inspection_officer_id')->nullable();
            $table->text('applicant_address')->nullable();
            $table->text('applicant_address_components')->nullable();
            $table->text('location_components')->nullable();
            $table->string('source', 50)->nullable();
            $table->string('purpose', 255)->nullable();
            $table->string('private_layout_number', 100)->nullable();
            $table->string('conformity', 100)->nullable();
            $table->string('accessibility', 100)->nullable();
            $table->string('existing_road_reservation', 100)->nullable();
            $table->string('recommended_road_reservation', 100)->nullable();
            $table->string('adequate_size_requirement', 100)->nullable();
            $table->string('land_traversing_utility', 255)->nullable();
            $table->text('existing_site_length')->nullable();
            $table->text('existing_site_width')->nullable();
            $table->decimal('existing_site_area', 18, 2)->nullable();
            $table->text('recommended_site_length')->nullable();
            $table->text('recommended_site_width')->nullable();
            $table->decimal('recommended_site_area', 18, 2)->nullable();
            $table->string('file_number', 100)->nullable();
            $table->string('observed_road_category', 255)->nullable();
            $table->string('recommended_comment_status', 100)->nullable();
            $table->string('how_many_meters', 100)->nullable();

            $table->index('application_id', 'sltr_jsi_reports_application_id_index');
            $table->index('file_number', 'sltr_jsi_reports_file_number_index');
        });
    }

    public function down(): void
    {
        Schema::connection('sqlsrv')->dropIfExists('sltr_joint_site_inspection_reports');
    }
};
