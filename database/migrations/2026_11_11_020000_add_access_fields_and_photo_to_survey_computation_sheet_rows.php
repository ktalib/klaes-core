<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 | KMC Phase1 Compensation.accdb, exported from the SG's Access database with the farmers' passport photographs (user,
 | 2026-11-11: "import them to the same staging table with the images"). Its farm records carry District, Staff ID,
 | Supervisor and Camera beside the Computation Sheet's columns, and a photo kept on the local disk (no file number,
 | so not EDMS). Text columns, as written, like the rest of the table.
 */
return new class extends Migration
{
    private const COLUMNS = ['district' => 255, 'staff_id' => 100, 'supervisor' => 255, 'camera' => 100, 'photo_path' => 500, 'photo_name' => 255];

    public function up(): void
    {
        $s = Schema::connection('sqlsrv');
        $s->table('survey_computation_sheet_rows', function (Blueprint $t) use ($s) {
            foreach (self::COLUMNS as $c => $len) {
                if (! $s->hasColumn('survey_computation_sheet_rows', $c)) $t->string($c, $len)->nullable();
            }
        });
    }

    public function down(): void
    {
        $s = Schema::connection('sqlsrv');
        foreach (array_keys(self::COLUMNS) as $c) {
            if ($s->hasColumn('survey_computation_sheet_rows', $c)) $s->table('survey_computation_sheet_rows', fn (Blueprint $t) => $t->dropColumn($c));
        }
    }
};
