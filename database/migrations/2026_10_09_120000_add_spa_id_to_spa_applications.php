<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * SPAS ID: every SPAS land record gets one (SPAS-YYYY-####), whether or not
 * the owner knows the file number — most do not, so the file number became
 * optional (2026-10-09). It is the same sequence that numbered customary
 * titles, so a customary record's SPAS ID equals its generated file number.
 *
 * Fills the new column only: customary records take their SPAS-… file number,
 * the rest are numbered after them in creation order. Nothing else changes.
 */
return new class extends Migration {
    public function up(): void
    {
        $schema = Schema::connection('sqlsrv');
        $db     = DB::connection('sqlsrv');

        if (! $schema->hasColumn('spa_applications', 'spa_id')) {
            $schema->table('spa_applications', function (Blueprint $table) {
                $table->string('spa_id', 30)->nullable();
            });
        }

        // Customary: the generated file number IS the SPAS ID.
        $db->update("UPDATE spa_applications SET spa_id = file_number
                      WHERE spa_id IS NULL AND file_number LIKE 'SPAS-[0-9][0-9][0-9][0-9]-%'");

        // Everyone else: the next numbers in the sequence, oldest first.
        $rows = $db->table('spa_applications')->whereNull('spa_id')->orderBy('created_at')->orderBy('id')->get(['id', 'created_at']);
        foreach ($rows as $row) {
            $year = $row->created_at ? substr((string) $row->created_at, 0, 4) : date('Y');
            $max  = 0;
            foreach (['spa_id', 'file_number'] as $col) {
                foreach ($db->table('spa_applications')->where($col, 'like', "SPAS-{$year}-%")->pluck($col) as $v) {
                    $max = max($max, (int) substr($v, strrpos($v, '-') + 1));
                }
            }
            $db->table('spa_applications')->where('id', $row->id)
                ->update(['spa_id' => "SPAS-{$year}-" . str_pad((string) ($max + 1), 4, '0', STR_PAD_LEFT)]);
        }

        $exists = $db->selectOne("SELECT 1 AS x FROM sys.indexes WHERE object_id = OBJECT_ID('spa_applications') AND name = 'UQ_spa_applications_spa_id'");
        if (! $exists) {
            $db->statement('CREATE UNIQUE INDEX UQ_spa_applications_spa_id ON spa_applications (spa_id) WHERE spa_id IS NOT NULL');
        }
    }

    public function down(): void
    {
        $db = DB::connection('sqlsrv');
        if ($db->selectOne("SELECT 1 AS x FROM sys.indexes WHERE object_id = OBJECT_ID('spa_applications') AND name = 'UQ_spa_applications_spa_id'")) {
            $db->statement('DROP INDEX UQ_spa_applications_spa_id ON spa_applications');
        }
        if (Schema::connection('sqlsrv')->hasColumn('spa_applications', 'spa_id')) {
            Schema::connection('sqlsrv')->table('spa_applications', fn (Blueprint $t) => $t->dropColumn('spa_id'));
        }
    }
};
