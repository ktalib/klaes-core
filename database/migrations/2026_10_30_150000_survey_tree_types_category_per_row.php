<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Economic tree Category (Mature / Early) is a field of each catalogue row, with
 * that row's one rate (user, 2026-10-30): "Mango – Mature" and "Mango – Early" are
 * two entries. Replaces the 2026-10-29 two-rates-per-row layout; no Early rate had
 * been entered, so nothing moves. The name is unique per category, not overall.
 * early_unit_price is left in place, unused.
 */
return new class extends Migration
{
    public function up(): void
    {
        $schema = Schema::connection('sqlsrv');
        $db = DB::connection('sqlsrv');

        if (! $schema->hasColumn('survey_tree_types', 'maturity')) {
            $schema->table('survey_tree_types', function (Blueprint $t) {
                $t->string('maturity', 10)->default('Mature');
            });
            $db->table('survey_tree_types')->whereNull('maturity')->update(['maturity' => 'Mature']);
        }

        $indexes = collect($db->select("SELECT name FROM sys.indexes WHERE object_id = OBJECT_ID('survey_tree_types')"))->pluck('name');
        if ($indexes->contains('survey_tree_types_name_unique')) {
            $db->statement('DROP INDEX survey_tree_types_name_unique ON survey_tree_types');
        }
        if (! $indexes->contains('survey_tree_types_name_maturity_unique')) {
            $db->statement('CREATE UNIQUE INDEX survey_tree_types_name_maturity_unique ON survey_tree_types (name, maturity)');
        }
    }

    public function down(): void
    {
        $db = DB::connection('sqlsrv');
        $db->statement('DROP INDEX IF EXISTS survey_tree_types_name_maturity_unique ON survey_tree_types');
        // Only possible while each name has one row.
        $db->statement('CREATE UNIQUE INDEX survey_tree_types_name_unique ON survey_tree_types (name)');
        Schema::connection('sqlsrv')->table('survey_tree_types', function (Blueprint $t) {
            $t->dropColumn('maturity');
        });
    }
};
