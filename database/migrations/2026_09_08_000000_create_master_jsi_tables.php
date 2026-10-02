<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Master JSI — the Physical Planning site observation for a parcel update.
 *
 * The Joint Site Inspection already existed, but only inside the OSS/Conversion
 * pipeline: joint_site_inspection_reports is keyed to application_id /
 * sub_application_id and is wired into billing, Sectional Titling and six printed
 * reports. The parcel-update workflows under Parcel/Title Management had no JSI at
 * all — they carried the KAMMA/Physical Planning "handshake", a four-field modal
 * (land value, fee, status, remarks) standing in for the planning clearance, whose
 * Approved value gated Generate Recommendation, Generate Application and Approve on
 * all six workflows.
 *
 * This is the real thing in its place: ONE form covering both categories —
 *
 *     SPU   the five single workflows (Subdivision, Separation, Merger,
 *           Extension, Change of Purpose)
 *     APU   the Duplex, where a JSI may attach to one stage
 *
 * — carrying the narrative and the S/N | Portion | Dimension | Measurement table
 * off the Ministry handwritten sheets. An approved Master JSI is what the
 * recommendation and the approval now rest on (see App\Support\MasterJsiGate).
 *
 * A separate table rather than columns on joint_site_inspection_reports: that
 * table application_id shape does not fit six different parcel-update tables,
 * and widening it would put this workflow nullable columns in front of every OSS
 * screen that reads it.
 *
 * The knupda_* columns on the parcel-update tables are deliberately NOT dropped.
 * They are history, DuplexCommitService still copies them forward, and records
 * already cleared through the handshake must keep working — MasterJsiGate reads
 * them as a legacy pass.
 *
 * Measurements are stored in SQUARE METRES. Hectares are derived at read time
 * (ParcelSizeSummary::hectares) so there is one authority for the number, and the
 * hectare-shaped legacy rows that exist elsewhere are not repeated here.
 */
return new class extends Migration
{
    public function up(): void
    {
        $schema = Schema::connection('sqlsrv');

        if (!$schema->hasTable('master_jsi_reports')) {
            $schema->create('master_jsi_reports', function (Blueprint $table) {
                $table->id();

                // Human reference carried on the printed sheet: MJSI-2026-0001.
                $table->string('jsi_ref', 40)->unique();

                // SPU (a single workflow) | APU (a duplex).
                $table->string('category', 10);

                // subdivision | separation | merger | extension | change_of_purpose
                // Mirrors DuplexParcelUpdate::TYPES — that list stays canonical.
                $table->string('parcel_update_type', 40);

                // Which record this inspection is about. subject_type is the
                // workflow slug, NOT a model class: the six workflows live in six
                // tables and a class name would break the moment one is moved.
                $table->string('subject_type', 40)->nullable();
                $table->unsignedBigInteger('subject_id')->nullable();

                // An APU inspection may cover ONE stage of the duplex rather than
                // the whole instruction.
                $table->unsignedBigInteger('duplex_stage_id')->nullable();

                $table->string('file_number', 100)->nullable();
                $table->string('file_title', 500)->nullable();

                // ---- Application details -------------------------------------
                $table->string('applicant_name', 255)->nullable();
                $table->date('inspection_date')->nullable();
                $table->string('location', 500)->nullable();
                $table->string('plot_number', 100)->nullable();
                $table->string('district', 255)->nullable();
                $table->string('lga', 255)->nullable();
                $table->string('inspection_officer', 255)->nullable();
                $table->unsignedBigInteger('inspection_officer_id')->nullable();

                // ---- Site observation ----------------------------------------
                // "physical site inspection was conducted and found to be
                //  available on ground"
                $table->string('available_on_ground', 50)->nullable();

                // "On the North: ...; On the East: ..." — compiled by the shared
                // HasBoundarySegments trait so it reads identically to the OSS JSI.
                $table->text('boundary_description')->nullable();

                $table->string('road_reservation', 700)->nullable();

                // "found Available and conform with the surrounding land use"
                $table->boolean('conformity')->nullable();

                $table->string('prevailing_land_use', 255)->nullable();
                $table->string('existing_land_use', 255)->nullable();
                $table->string('recommended_land_use', 255)->nullable();
                $table->string('existing_purpose', 255)->nullable();
                $table->string('recommended_purpose', 255)->nullable();

                // Subdivision/Separation: "subdivided into ___ Number", average size.
                $table->integer('number_of_units')->nullable();
                $table->string('average_size', 100)->nullable();

                $table->text('narrative_summary')->nullable();
                $table->text('additional_observations')->nullable();

                // ---- Workflow ------------------------------------------------
                // draft | generated | submitted | approved | rejected
                $table->string('status', 20)->default('draft');

                $table->dateTime('generated_at')->nullable();
                $table->unsignedBigInteger('generated_by')->nullable();
                $table->dateTime('submitted_at')->nullable();
                $table->unsignedBigInteger('submitted_by')->nullable();
                $table->dateTime('approved_at')->nullable();
                $table->unsignedBigInteger('approved_by')->nullable();
                $table->text('rejected_reason')->nullable();

                // The handover to Deeds. Like the duplex sent_to_land_at this is
                // a stamp and a notification — no registry writes.
                $table->dateTime('sent_to_deeds_at')->nullable();
                $table->unsignedBigInteger('sent_to_deeds_by')->nullable();

                // ---- Audit ---------------------------------------------------
                $table->unsignedBigInteger('created_by')->nullable();
                $table->unsignedBigInteger('updated_by')->nullable();
                $table->boolean('is_deleted')->default(false);
                $table->unsignedBigInteger('deleted_by')->nullable();
                $table->dateTime('deleted_at')->nullable();
                $table->timestamps();

                // The gate asks this question on every listing row.
                $table->index(['subject_type', 'subject_id'], 'IX_master_jsi_subject');
                $table->index('status', 'IX_master_jsi_status');
            });
        }

        if (!$schema->hasTable('master_jsi_portions')) {
            $schema->create('master_jsi_portions', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('master_jsi_report_id');

                // The S/N as the sheet numbers it.
                $table->integer('sn')->nullable();

                // Which line of the sheet this is: existing_title, extension_portion,
                // recommended_portion, portion_a, portion_b, merged_portion,
                // existing_site, subdivision_units, average_size,
                // existing_land_use, recommended_land_use.
                $table->string('role', 40)->nullable();

                // What the sheet prints in the PORTION column.
                $table->string('label', 255)->nullable();

                // "60 x 21 x 46" — sides as measured, via
                // ParcelSizeSummary::dimensionsText().
                $table->string('dimensions', 255)->nullable();

                // SQUARE METRES. The authority; hectares are derived for display.
                $table->decimal('area_sqm', 18, 2)->nullable();

                // Change of Purpose prints a land use against each row.
                $table->string('land_use', 255)->nullable();

                // Subdivision prints a count ("No of Subdivision").
                $table->integer('unit_count')->nullable();

                $table->integer('sequence')->default(0);
                $table->timestamps();

                $table->index('master_jsi_report_id', 'IX_master_jsi_portion_report');
            });
        }
    }

    public function down(): void
    {
        $schema = Schema::connection('sqlsrv');
        $schema->dropIfExists('master_jsi_portions');
        $schema->dropIfExists('master_jsi_reports');
    }
};
