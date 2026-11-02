<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Kano valuation schedule for the Farm Assessment catalogue (user, 2026-11-01: the Cash Crops and
 * Economic Trees spreadsheets). Each row keeps its rate per unit in default_unit_price (per m² for a
 * cash crop, per stand for an economic tree); added here: the per-hectare price, the cost of
 * production per hectare (which values the land under the crop / tree) and the schedule details.
 *
 * The unique index becomes (name, Type, Category) over live rows only: White acacia and Ziziphus are
 * in both lists (user), and the replaced catalogue's retired rows must not block the new ones.
 */
return new class extends Migration
{
    public function up(): void
    {
        $schema = Schema::connection('sqlsrv');
        $add = fn (string $col) => ! $schema->hasColumn('survey_tree_types', $col);

        $schema->table('survey_tree_types', function (Blueprint $t) use ($add) {
            if ($add('price_per_ha'))         $t->decimal('price_per_ha', 18, 2)->nullable();
            if ($add('cost_per_ha'))          $t->decimal('cost_per_ha', 18, 2)->nullable();
            if ($add('planting_rate_per_ha')) $t->decimal('planting_rate_per_ha', 14, 2)->nullable();
            if ($add('planting_spacing'))     $t->string('planting_spacing', 60)->nullable();
            if ($add('maturity_period'))      $t->string('maturity_period', 60)->nullable();
            if ($add('economic_life'))        $t->string('economic_life', 60)->nullable();
            if ($add('schedule_sn'))          $t->integer('schedule_sn')->nullable();
        });

        $db = DB::connection('sqlsrv');
        $indexes = collect($db->select("SELECT name FROM sys.indexes WHERE object_id = OBJECT_ID('survey_tree_types')"))->pluck('name');
        if ($indexes->contains('survey_tree_types_name_maturity_unique')) {
            $db->statement('DROP INDEX survey_tree_types_name_maturity_unique ON survey_tree_types');
        }
        if (! $indexes->contains('survey_tree_types_name_type_maturity_unique')) {
            $db->statement('CREATE UNIQUE INDEX survey_tree_types_name_type_maturity_unique ON survey_tree_types (name, category, maturity) WHERE deleted_at IS NULL');
        }
    }

    public function down(): void
    {
        $db = DB::connection('sqlsrv');
        $db->statement('DROP INDEX IF EXISTS survey_tree_types_name_type_maturity_unique ON survey_tree_types');
        // Only possible while no name repeats per Category across every row.
        $db->statement('CREATE UNIQUE INDEX survey_tree_types_name_maturity_unique ON survey_tree_types (name, maturity)');
        Schema::connection('sqlsrv')->table('survey_tree_types', function (Blueprint $t) {
            $t->dropColumn(['price_per_ha', 'cost_per_ha', 'planting_rate_per_ha', 'planting_spacing', 'maturity_period', 'economic_life', 'schedule_sn']);
        });
    }
};
