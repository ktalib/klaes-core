<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Site Inspection Template Update — the revised inspection sheet for Master JSI.
 *
 * The existing sheet (master_jsi_reports + master_jsi_portions) is retained in full:
 * legacy records keep every column and every measurement row, and old reports must
 * still view, edit and print. This migration is purely additive — nullable columns
 * on the parent so old rows are untouched, and seven child tables for the things the
 * revised template asks for but a single sheet could not hold:
 *
 *   - multiple purposes on ONE inspection (stored individually, never as a label)
 *   - persons present / applicant's representatives
 *   - merger properties (2+ plots with their own references and measurements)
 *   - extension portions (repeatable, not "years")
 *   - subdivision plots (one row per proposed plot)
 *   - change-of-purpose rows (current -> proposed, from the configured land uses)
 *   - supporting evidence (photographs, coordinates, survey plan, sketch, other)
 *
 * Areas stay in SQUARE METRES as the single base unit; hectares are derived at read
 * time (ParcelSizeSummary). A unit choice is echoed per row only so the sheet can
 * show what the officer entered, never stored as the authority.
 *
 * The status pipeline is unchanged. "Return" is recorded in supervisor_decision and
 * stamps returned_at/by; the report drops back to the generated state so the officer
 * can edit and resubmit without inventing a new status for the gate.
 */
return new class extends Migration
{
    public function up(): void
    {
        $schema = Schema::connection('sqlsrv');

        // ---- Parent: additive, all nullable, safe for existing records ---------
        $schema->table('master_jsi_reports', function (Blueprint $table) {
            // Site characteristics / measurement unit. sqm is the base unit; this
            // only says which unit the officer entered so the print echoes it.
            $table->string('site_unit', 10)->nullable();
            $table->decimal('site_existing_area_sqm', 18, 2)->nullable();
            $table->decimal('site_recommended_area_sqm', 18, 2)->nullable();

            // Authorised override of the calculated recommended area + audit reason.
            $table->decimal('recommended_total_override_sqm', 18, 2)->nullable();
            $table->string('override_reason', 500)->nullable();

            // Merger details.
            $table->string('proposed_merged_plot_number', 100)->nullable();
            $table->decimal('recommended_merged_area_sqm', 18, 2)->nullable();
            $table->text('merger_remarks')->nullable();

            // Extension remarks.
            $table->text('extension_remarks')->nullable();

            // Location coordinates (supporting evidence kind).
            $table->string('location_coordinates', 500)->nullable();

            // Inspection findings (section 6).
            $table->boolean('finding_site_suitable')->nullable();
            $table->boolean('finding_site_accessible')->nullable();
            $table->string('development_status', 40)->nullable();
            $table->text('officer_recommendation')->nullable();
            $table->string('officer_designation', 255)->nullable();
            $table->text('further_directions')->nullable();

            // Certification and approval (section 8).
            $table->text('supervisor_remarks')->nullable();
            $table->string('supervisor_decision', 20)->nullable();
            $table->dateTime('returned_at')->nullable();
            $table->unsignedBigInteger('returned_by')->nullable();
        });

        // ---- Purpose (one row per selected purpose) ----------------------------
        if (!$schema->hasTable('master_jsi_purposes')) {
            $schema->create('master_jsi_purposes', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('master_jsi_report_id');
                $table->string('purpose', 40);
                $table->integer('sequence')->default(0);
                $table->timestamps();

                $table->index('master_jsi_report_id', 'IX_master_jsi_purpose_report');
            });
        }

        // ---- Persons present during the inspection ------------------------------
        // participant: applicant | applicant_representative
        if (!$schema->hasTable('master_jsi_participants')) {
            $schema->create('master_jsi_participants', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('master_jsi_report_id');
                $table->string('participant', 30);
                $table->string('name', 255)->nullable();
                $table->string('phone', 40)->nullable();
                $table->string('relationship', 255)->nullable();
                $table->integer('sequence')->default(0);
                $table->timestamps();

                $table->index('master_jsi_report_id', 'IX_master_jsi_participant_report');
            });
        }

        // ---- Merger properties (2+) --------------------------------------------
        if (!$schema->hasTable('master_jsi_merger_properties')) {
            $schema->create('master_jsi_merger_properties', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('master_jsi_report_id');
                $table->string('property_file_number', 100)->nullable();
                $table->string('plot_number', 100)->nullable();
                $table->decimal('area_sqm', 18, 2)->nullable();
                $table->string('unit', 10)->nullable();
                $table->integer('sequence')->default(0);
                $table->timestamps();

                $table->index('master_jsi_report_id', 'IX_master_jsi_merger_prop_report');
            });
        }

        // ---- Extension portions (repeatable strips) -----------------------------
        if (!$schema->hasTable('master_jsi_extension_portions')) {
            $schema->create('master_jsi_extension_portions', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('master_jsi_report_id');
                $table->string('extension_number', 100)->nullable();
                $table->string('description', 500)->nullable();
                $table->decimal('area_sqm', 18, 2)->nullable();
                $table->string('unit', 10)->nullable();
                $table->integer('sequence')->default(0);
                $table->timestamps();

                $table->index('master_jsi_report_id', 'IX_master_jsi_extension_report');
            });
        }

        // ---- Subdivision plots (one row per proposed plot) ----------------------
        if (!$schema->hasTable('master_jsi_subdivision_plots')) {
            $schema->create('master_jsi_subdivision_plots', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('master_jsi_report_id');
                $table->string('plot_number', 100)->nullable();
                $table->decimal('area_sqm', 18, 2)->nullable();
                $table->string('unit', 10)->nullable();
                $table->string('remarks', 500)->nullable();
                $table->integer('sequence')->default(0);
                $table->timestamps();

                $table->index('master_jsi_report_id', 'IX_master_jsi_subdiv_plot_report');
            });
        }

        // ---- Change-of-purpose rows (current -> proposed) -----------------------
        if (!$schema->hasTable('master_jsi_purpose_changes')) {
            $schema->create('master_jsi_purpose_changes', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('master_jsi_report_id');
                $table->string('current_land_use', 255)->nullable();
                $table->string('proposed_land_use', 255)->nullable();
                $table->decimal('area_sqm', 18, 2)->nullable();
                $table->string('unit', 10)->nullable();
                $table->string('remarks', 500)->nullable();
                $table->integer('sequence')->default(0);
                $table->timestamps();

                $table->index('master_jsi_report_id', 'IX_master_jsi_purpose_change_report');
            });
        }

        // ---- Supporting evidence -------------------------------------------------
        if (!$schema->hasTable('master_jsi_evidence')) {
            $schema->create('master_jsi_evidence', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('master_jsi_report_id');
                // site_photograph | location_coordinates | survey_plan | sketch | other
                $table->string('kind', 40)->nullable();
                $table->string('file_path', 500)->nullable();
                $table->text('file_name')->nullable();
                $table->string('description', 1000)->nullable();
                $table->string('value', 1000)->nullable();
                $table->integer('sequence')->default(0);
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();

                $table->index('master_jsi_report_id', 'IX_master_jsi_evidence_report');
            });
        }
    }

    public function down(): void
    {
        $schema = Schema::connection('sqlsrv');

        $schema->dropIfExists('master_jsi_evidence');
        $schema->dropIfExists('master_jsi_purpose_changes');
        $schema->dropIfExists('master_jsi_subdivision_plots');
        $schema->dropIfExists('master_jsi_extension_portions');
        $schema->dropIfExists('master_jsi_merger_properties');
        $schema->dropIfExists('master_jsi_participants');
        $schema->dropIfExists('master_jsi_purposes');

        $schema->table('master_jsi_reports', function (Blueprint $table) {
            $table->dropColumn([
                'site_unit',
                'site_existing_area_sqm',
                'site_recommended_area_sqm',
                'recommended_total_override_sqm',
                'override_reason',
                'proposed_merged_plot_number',
                'recommended_merged_area_sqm',
                'merger_remarks',
                'extension_remarks',
                'location_coordinates',
                'finding_site_suitable',
                'finding_site_accessible',
                'development_status',
                'officer_recommendation',
                'officer_designation',
                'further_directions',
                'supervisor_remarks',
                'supervisor_decision',
                'returned_at',
                'returned_by',
            ]);
        });
    }
};