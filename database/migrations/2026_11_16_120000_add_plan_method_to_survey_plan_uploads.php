<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 | Survey Plan Management (user, Recording 5, 2026-11-16): a plan is either a Manual Upload (a scanned / hard copy
 | file, with the coordinate system and one reference beacon) or a Digital Plotting (no file: the beacons and the
 | traverse are plotted on the map). plan_method 'manual' | 'digital' (null = before this, all were manual uploads);
 | plot_data = the plotted beacons and traverse lines as JSON. file_path may now be null (a digital plot has no file).
 | Additive: no rows change.
 */
return new class extends Migration
{
    public function up(): void
    {
        $s = Schema::connection('sqlsrv');
        if (! $s->hasColumn('survey_plan_uploads', 'plan_method')) {
            DB::connection('sqlsrv')->statement('ALTER TABLE survey_plan_uploads ADD plan_method nvarchar(10) NULL');
        }
        if (! $s->hasColumn('survey_plan_uploads', 'plot_data')) {
            DB::connection('sqlsrv')->statement('ALTER TABLE survey_plan_uploads ADD plot_data nvarchar(max) NULL');
        }
        DB::connection('sqlsrv')->statement('ALTER TABLE survey_plan_uploads ALTER COLUMN file_path nvarchar(500) NULL');
    }

    public function down(): void
    {
        // file_path stays nullable: digital plots have none.
        foreach (['plan_method', 'plot_data'] as $col) {
            if (Schema::connection('sqlsrv')->hasColumn('survey_plan_uploads', $col)) {
                DB::connection('sqlsrv')->statement("ALTER TABLE survey_plan_uploads DROP COLUMN {$col}");
            }
        }
    }
};
