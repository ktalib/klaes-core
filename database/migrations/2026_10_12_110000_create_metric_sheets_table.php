<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Metric map sheets as a lookup table (user, 2026-10-12), for the Charting Card's
 * Sheet dropdown and anything else that needs them.
 *
 * Loaded from database/data/metric_sheets.csv, which was built from the two
 * dropdown components already in use (components/metricSheetIndex and
 * components/metricSheetNo): 696 sheets at 1:2000, each index code (e.g. P-10)
 * belonging to exactly one sheet number (NO 41/17 NW). `label` keeps the
 * components' full spelling, "NO 41/17 NW (P-10) Scale:-1:2000", which is what
 * the Survey screens store in Metric_Sheet_No.
 *
 * Run with --path only:
 *   php artisan migrate --path=database/migrations/2026_10_12_110000_create_metric_sheets_table.php
 *
 * Additive: a new table, filled only while empty.
 */
return new class extends Migration
{
    public function up(): void
    {
        $s = Schema::connection('sqlsrv');

        if (! $s->hasTable('metric_sheets')) {
            $s->create('metric_sheets', function (Blueprint $t) {
                $t->id();
                $t->string('sheet_index', 10)->unique();
                $t->string('sheet_no', 30);
                $t->string('scale', 20)->nullable();
                $t->string('label', 60)->unique();
                $t->boolean('is_active')->default(true);
                $t->timestamps();
            });
        }

        $db = DB::connection('sqlsrv');
        if ($db->table('metric_sheets')->exists()) {
            return;
        }

        $fh = fopen(database_path('data/metric_sheets.csv'), 'r');
        $head = fgetcsv($fh);
        $rows = [];
        $now = now();
        while (($r = fgetcsv($fh)) !== false) {
            if (count($r) !== count($head)) {
                continue;
            }
            $rows[] = array_combine($head, array_map('trim', $r)) + ['is_active' => 1, 'created_at' => $now, 'updated_at' => $now];
        }
        fclose($fh);

        // sqlsrv takes at most 2100 parameters per statement.
        foreach (array_chunk($rows, 200) as $chunk) {
            $db->table('metric_sheets')->insert($chunk);
        }
    }

    public function down(): void
    {
        Schema::connection('sqlsrv')->dropIfExists('metric_sheets');
    }
};
