<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Beacon Register (user, 2026-10-23):
 *  - File number (from the global selector, optional) and X / Y coordinates.
 *  - The surveyor is no longer required, so surveyor_name becomes nullable.
 *    SQL Server will not ALTER a column an index depends on, so the index is
 *    dropped and recreated around it.
 * Additive / relaxing only; no data is changed.
 */
return new class extends Migration
{
    public function up(): void
    {
        $s = Schema::connection('sqlsrv');
        $db = DB::connection('sqlsrv');

        if (! $s->hasColumn('survey_beacon_register', 'file_number')) {
            $s->table('survey_beacon_register', function (Blueprint $t) {
                $t->string('file_number', 100)->nullable();
                $t->decimal('coord_x', 18, 4)->nullable();   // Easting
                $t->decimal('coord_y', 18, 4)->nullable();   // Northing
                $t->index('file_number');
            });
        }

        $nullable = $db->selectOne(
            "SELECT is_nullable FROM sys.columns WHERE object_id = OBJECT_ID('survey_beacon_register') AND name = 'surveyor_name'"
        );
        if ($nullable && ! (int) $nullable->is_nullable) {
            $db->statement("IF EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'survey_beacon_register_surveyor_name_index' AND object_id = OBJECT_ID('survey_beacon_register'))
                DROP INDEX survey_beacon_register_surveyor_name_index ON survey_beacon_register");
            $db->statement('ALTER TABLE survey_beacon_register ALTER COLUMN surveyor_name nvarchar(255) NULL');
            $db->statement('CREATE INDEX survey_beacon_register_surveyor_name_index ON survey_beacon_register (surveyor_name)');
        }
    }

    public function down(): void
    {
        // surveyor_name stays nullable: rows saved without a surveyor would block NOT NULL.
        Schema::connection('sqlsrv')->table('survey_beacon_register', function (Blueprint $t) {
            $t->dropIndex(['file_number']);
            $t->dropColumn(['file_number', 'coord_x', 'coord_y']);
        });
    }
};
