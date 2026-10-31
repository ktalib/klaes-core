<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Resettlement Calculator (Resettlement Plot Allocation; docs/KLAES Survey Module – Workflow_V02.md 2.3):
 * plots = area (ha) ÷ plot size (ha), the plot size set by land use / purpose and density.
 *
 *  - survey_resettlement_rates: the plot sizes. Seeded with the SG's Residential figures
 *    (29 Oct 2026): HD 0.0454, MD 0.0714, LD 0.1667 ha. Other land uses are added in the app
 *    when the SG supplies them; rows are retired, never deleted.
 *  - survey_resettlement_calculations: each saved calculation, against the land it was for.
 *
 * Additive only.
 */
return new class extends Migration
{
    public function up(): void
    {
        $schema = Schema::connection('sqlsrv');

        if (! $schema->hasTable('survey_resettlement_rates')) {
            $schema->create('survey_resettlement_rates', function (Blueprint $t) {
                $t->id();
                $t->string('land_use', 100);
                $t->string('density', 50)->nullable();
                $t->string('code', 10)->nullable();
                $t->decimal('plot_size_ha', 10, 4);
                $t->integer('sort_order')->default(0);
                $t->boolean('is_active')->default(true);
                $t->string('source', 255)->nullable();
                $t->unsignedBigInteger('created_by')->nullable();
                $t->unsignedBigInteger('updated_by')->nullable();
                $t->timestamps();
                $t->softDeletes();
            });

            $now = now();
            $src = 'SG, 29 Oct 2026';
            DB::connection('sqlsrv')->table('survey_resettlement_rates')->insert([
                ['land_use' => 'Residential', 'density' => 'High Density',   'code' => 'HD', 'plot_size_ha' => 0.0454, 'sort_order' => 1, 'is_active' => 1, 'source' => $src, 'created_at' => $now, 'updated_at' => $now],
                ['land_use' => 'Residential', 'density' => 'Medium Density', 'code' => 'MD', 'plot_size_ha' => 0.0714, 'sort_order' => 2, 'is_active' => 1, 'source' => $src, 'created_at' => $now, 'updated_at' => $now],
                ['land_use' => 'Residential', 'density' => 'Low Density',    'code' => 'LD', 'plot_size_ha' => 0.1667, 'sort_order' => 3, 'is_active' => 1, 'source' => $src, 'created_at' => $now, 'updated_at' => $now],
            ]);
        }

        if (! $schema->hasTable('survey_resettlement_calculations')) {
            $schema->create('survey_resettlement_calculations', function (Blueprint $t) {
                $t->id();
                $t->string('calc_ref', 30)->unique();
                $t->unsignedBigInteger('survey_comp_case_id')->nullable()->index();
                $t->unsignedBigInteger('survey_resettlement_rate_id')->nullable();
                $t->string('land_use', 100);
                $t->string('density', 50)->nullable();
                $t->decimal('plot_size_ha', 10, 4);
                $t->decimal('area_ha', 12, 4);
                $t->decimal('deduction_percent', 5, 2)->default(0);
                $t->decimal('net_area_ha', 12, 4);
                $t->decimal('exact_plots', 12, 4);
                $t->string('rounding', 10);
                $t->integer('plots');
                $t->boolean('split_applied')->default(false);
                $t->integer('farmer_plots')->nullable();
                $t->integer('government_plots')->nullable();
                $t->string('notes', 1000)->nullable();
                $t->unsignedBigInteger('created_by')->nullable();
                $t->unsignedBigInteger('updated_by')->nullable();
                $t->timestamps();
                $t->softDeletes();
            });
        }
    }

    public function down(): void
    {
        $schema = Schema::connection('sqlsrv');
        $schema->dropIfExists('survey_resettlement_calculations');
        $schema->dropIfExists('survey_resettlement_rates');
    }
};
