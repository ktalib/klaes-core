<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sidebar / workflow update 2026-10-29:
 *  - Boundary Dispute: Public Boundary gains "Others", with the boundary typed (boundary_type_other).
 *  - Economic Trees Assessment: trees are assessed Mature or Early (recording 286: the old
 *    Category Cash Crop / Fruit / Timber becomes the Type; the new Category is Mature / Early),
 *    each with its own rate. default_unit_price stays the Mature rate; early_unit_price is new.
 *    Tree lines on a land and in the Compensation Calculator carry the category (maturity).
 *
 * Additive only: nullable columns; existing rows read as Mature.
 */
return new class extends Migration
{
    public function up(): void
    {
        $schema = Schema::connection('sqlsrv');

        if (! $schema->hasColumn('survey_boundary_disputes', 'boundary_type_other')) {
            $schema->table('survey_boundary_disputes', fn (Blueprint $t) => $t->string('boundary_type_other', 100)->nullable());
        }
        if (! $schema->hasColumn('survey_tree_types', 'early_unit_price')) {
            $schema->table('survey_tree_types', fn (Blueprint $t) => $t->decimal('early_unit_price', 18, 2)->nullable());
        }
        if (! $schema->hasColumn('survey_case_trees', 'maturity')) {
            $schema->table('survey_case_trees', fn (Blueprint $t) => $t->string('maturity', 10)->nullable());
        }
    }

    public function down(): void
    {
        $schema = Schema::connection('sqlsrv');
        foreach ([['survey_boundary_disputes', 'boundary_type_other'], ['survey_tree_types', 'early_unit_price'], ['survey_case_trees', 'maturity']] as [$t, $c]) {
            if ($schema->hasColumn($t, $c)) $schema->table($t, fn (Blueprint $b) => $b->dropColumn($c));
        }
    }
};
