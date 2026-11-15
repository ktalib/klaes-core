<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 | Survey Plan Upload: the plan's map sheet (CR 141102026 item 3, user 2026-11-15), Metric (Kano metropolitan
 | index grid, e.g. P10) and Imperial (1:50,000 topo quarter sheet, e.g. 57SE). Lists in
 | App\Services\Survey\MapSheets. Optional; plans uploaded before have none. Additive only.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['map_sheet_metric', 'map_sheet_imperial'] as $col) {
            if (! Schema::connection('sqlsrv')->hasColumn('survey_plan_uploads', $col)) {
                Schema::connection('sqlsrv')->table('survey_plan_uploads', function (Blueprint $t) use ($col) {
                    $t->string($col, 10)->nullable();
                });
            }
        }
    }

    public function down(): void
    {
        foreach (['map_sheet_metric', 'map_sheet_imperial'] as $col) {
            if (Schema::connection('sqlsrv')->hasColumn('survey_plan_uploads', $col)) {
                Schema::connection('sqlsrv')->table('survey_plan_uploads', function (Blueprint $t) use ($col) {
                    $t->dropColumn($col);
                });
            }
        }
    }
};
