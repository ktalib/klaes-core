<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Import of the department's paper-era compensation and resettlement allocation
 * lists ("ALL COMPENSATION" workbooks, user 2026-10-30) into the Survey records.
 *
 *   widen    farm areas are written to 4 decimals (0.0699 ha); survey_comp_cases.area_ha
 *            and the allocation line's acreage / govt_50 / farmer_50 kept 2. Plot
 *            numbers can be a list ("1518,1519"). The tables were empty when this ran.
 *   tag      import_ref on every table the import writes, so one batch can be found
 *            and removed as a whole; survey_projects.plan_no for the layout plan.
 *   staging  survey_comp_import_rows: every source row, with its file / sheet / row,
 *            what it became, and a duplicate flag across files (nothing is dropped).
 */
return new class extends Migration
{
    private const TAGGED = ['survey_co_files', 'survey_projects', 'survey_project_surveyors', 'survey_comp_cases', 'survey_beneficiaries', 'survey_plot_allocations'];

    public function up(): void
    {
        $schema = Schema::connection('sqlsrv');
        $db = DB::connection('sqlsrv');

        // Widen (empty tables: drop the plot_no index, alter, put it back).
        $db->statement('ALTER TABLE survey_comp_cases ALTER COLUMN area_ha DECIMAL(14,4) NULL');
        foreach (['acreage', 'govt_50', 'farmer_50'] as $c) {
            $db->statement("ALTER TABLE survey_plot_allocations ALTER COLUMN {$c} DECIMAL(14,4) NULL");
        }
        $db->statement('ALTER TABLE survey_plot_allocations ALTER COLUMN creetable NVARCHAR(20) NULL');
        $db->statement('DROP INDEX survey_plot_allocations_plot_no_index ON survey_plot_allocations');
        $db->statement('ALTER TABLE survey_plot_allocations ALTER COLUMN plot_no NVARCHAR(400) NULL');
        $db->statement('CREATE INDEX survey_plot_allocations_plot_no_index ON survey_plot_allocations (plot_no)');

        foreach (self::TAGGED as $table) {
            if (! $schema->hasColumn($table, 'import_ref')) {
                $schema->table($table, function (Blueprint $t) {
                    $t->string('import_ref', 40)->nullable()->index();
                });
            }
        }
        if (! $schema->hasColumn('survey_projects', 'plan_no')) {
            $schema->table('survey_projects', function (Blueprint $t) {
                $t->string('plan_no', 100)->nullable()->index();
            });
        }

        if (! $schema->hasTable('survey_comp_import_rows')) {
            $schema->create('survey_comp_import_rows', function (Blueprint $t) {
                $t->id();
                $t->string('import_ref', 40)->index();
                $t->string('source_file', 255);
                $t->string('source_sheet', 100);
                $t->integer('source_row');
                $t->string('kind', 20);                         // farmer | plot | total | other
                $t->text('raw');                                // the row as read, JSON
                $t->string('plan_key', 50)->nullable()->index();
                $t->string('surveyor_name', 255)->nullable();
                $t->string('farmer_name', 255)->nullable();
                $t->string('plot_no', 400)->nullable();
                $t->unsignedBigInteger('survey_comp_case_id')->nullable();
                $t->unsignedBigInteger('survey_plot_allocation_id')->nullable();
                $t->string('duplicate_key', 500)->nullable();
                $t->boolean('is_duplicate')->default(false);
                $t->string('note', 500)->nullable();
                $t->timestamps();
                $t->index(['source_file', 'source_sheet', 'source_row']);
            });
        }
    }

    public function down(): void
    {
        $schema = Schema::connection('sqlsrv');
        $schema->dropIfExists('survey_comp_import_rows');
        if ($schema->hasColumn('survey_projects', 'plan_no')) {
            $schema->table('survey_projects', function (Blueprint $t) {
                $t->dropIndex(['plan_no']);
                $t->dropColumn('plan_no');
            });
        }
        foreach (self::TAGGED as $table) {
            if ($schema->hasColumn($table, 'import_ref')) {
                $schema->table($table, function (Blueprint $t) {
                    $t->dropIndex(['import_ref']);
                    $t->dropColumn('import_ref');
                });
            }
        }
        // The widened columns are left wide: narrowing would cut stored values.
    }
};
