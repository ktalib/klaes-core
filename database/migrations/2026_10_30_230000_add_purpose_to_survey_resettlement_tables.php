<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Resettlement Calculator: Land Use -> Purpose -> Density (user, 2026-10-30). Purposes are
 * the KLAES purposes lookup (purposes.name, by land use). A plot size with no purpose
 * applies to every purpose of its land use; the calculation records the purpose picked.
 */
return new class extends Migration
{
    public function up(): void
    {
        $schema = Schema::connection('sqlsrv');
        foreach (['survey_resettlement_rates', 'survey_resettlement_calculations'] as $table) {
            if (! $schema->hasColumn($table, 'purpose')) {
                $schema->table($table, fn (Blueprint $t) => $t->string('purpose', 150)->nullable());
            }
        }
    }

    public function down(): void
    {
        foreach (['survey_resettlement_rates', 'survey_resettlement_calculations'] as $table) {
            Schema::connection('sqlsrv')->table($table, fn (Blueprint $t) => $t->dropColumn('purpose'));
        }
    }
};
