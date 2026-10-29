<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Compensation complaints follow the printed Compensation Complaint Form as
 * amended by hand (docs/KLAES Survey Module – Workflow_V02.md 2.4, 2026-10-29):
 * Farm No / Section / Project type, Monetary (A) or Land to Land (B), the three
 * category blocks, supporting documents and the official-use Assigned To.
 * Additive only: every column is nullable; existing columns are untouched.
 */
return new class extends Migration
{
    private const COLUMNS = [
        'farm_no', 'section_no', 'project_type', 'complainant_email', 'representative_name',
        'representative_relationship', 'file_reference', 'map_sheet_no', 'district', 'lga',
        'compensation_type', 'original_amount', 'original_plots', 'expected_amount', 'expected_plots',
        'field_officer_name', 'correct_plot_coordinates', 'boundary_type', 'affected_party',
        'supporting_documents', 'other_documents', 'assigned_to',
    ];

    public function up(): void
    {
        $schema = Schema::connection('sqlsrv');
        if (! $schema->hasTable('survey_complaints') || $schema->hasColumn('survey_complaints', 'farm_no')) {
            return;
        }

        $schema->table('survey_complaints', function (Blueprint $t) {
            // Section 1: complainant
            $t->string('farm_no', 50)->nullable();
            $t->string('section_no', 50)->nullable();
            $t->string('project_type', 100)->nullable();
            $t->string('complainant_email', 255)->nullable();
            $t->string('representative_name', 255)->nullable();
            $t->string('representative_relationship', 100)->nullable();

            // Section 2: property & compensation (A = monetary, B = land to land)
            $t->string('file_reference', 100)->nullable();
            $t->string('map_sheet_no', 50)->nullable();
            $t->string('district', 255)->nullable();
            $t->string('lga', 255)->nullable();
            $t->string('compensation_type', 20)->nullable();
            $t->decimal('original_amount', 18, 2)->nullable();
            $t->integer('original_plots')->nullable();

            // Section 3: category blocks (details stay in `description`)
            $t->decimal('expected_amount', 18, 2)->nullable();
            $t->integer('expected_plots')->nullable();
            $t->string('field_officer_name', 255)->nullable();
            $t->string('correct_plot_coordinates', 1000)->nullable();
            $t->string('boundary_type', 50)->nullable();
            $t->string('affected_party', 255)->nullable();

            // Section 4 and official use
            $t->string('supporting_documents', 500)->nullable();
            $t->string('other_documents', 500)->nullable();
            $t->string('assigned_to', 255)->nullable();
        });
    }

    public function down(): void
    {
        $schema = Schema::connection('sqlsrv');
        if ($schema->hasColumn('survey_complaints', 'farm_no')) {
            $schema->table('survey_complaints', fn (Blueprint $t) => $t->dropColumn(self::COLUMNS));
        }
    }
};
