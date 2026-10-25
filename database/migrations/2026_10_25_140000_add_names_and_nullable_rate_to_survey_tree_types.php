<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Economic tree catalogue (user, 2026-10-25): Hausa and botanical names, and a
 * rate that may be "not set yet". No official Kano schedule was found, so trees
 * are loaded without rates; NULL must mean "no rate", distinct from ₦0.
 *
 * Additive: two nullable columns, and default_unit_price relaxed to NULL
 * (decimal(14,2) kept; its default 0 constraint is left in place).
 */
return new class extends Migration
{
    public function up(): void
    {
        $s = Schema::connection('sqlsrv');

        if (! $s->hasColumn('survey_tree_types', 'botanical_name')) {
            $s->table('survey_tree_types', function (Blueprint $t) {
                $t->string('local_name', 150)->nullable();     // Hausa
                $t->string('botanical_name', 255)->nullable();
            });
        }

        DB::connection('sqlsrv')->statement('ALTER TABLE survey_tree_types ALTER COLUMN default_unit_price decimal(14,2) NULL');
    }

    public function down(): void
    {
        $db = DB::connection('sqlsrv');
        $db->statement('UPDATE survey_tree_types SET default_unit_price = 0 WHERE default_unit_price IS NULL');
        $db->statement('ALTER TABLE survey_tree_types ALTER COLUMN default_unit_price decimal(14,2) NOT NULL');

        if (Schema::connection('sqlsrv')->hasColumn('survey_tree_types', 'botanical_name')) {
            Schema::connection('sqlsrv')->table('survey_tree_types', function (Blueprint $t) {
                $t->dropColumn(['local_name', 'botanical_name']);
            });
        }
    }
};
