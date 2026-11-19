<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * CON-RES files are Private layouts in the Beacon Register, never Public ("Government") (user, 2026-11-19).
 * The 257 existing lines were moved first (backup: storage/app/backups/survey_beacon_register_conres_government_20261119.json).
 * The form rule (SurveyBeaconRegister::mustBePrivate) also covers a CON-RES file that is not the line's first one.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::connection('sqlsrv')->statement(
            "IF NOT EXISTS (SELECT 1 FROM sys.check_constraints WHERE name = 'ck_sbr_conres_private')
             ALTER TABLE survey_beacon_register WITH CHECK ADD CONSTRAINT ck_sbr_conres_private
             CHECK (NOT (layout_type = 'Government' AND LTRIM(file_number) LIKE 'CON-RES%'))"
        );
    }

    public function down(): void
    {
        DB::connection('sqlsrv')->statement(
            "IF EXISTS (SELECT 1 FROM sys.check_constraints WHERE name = 'ck_sbr_conres_private')
             ALTER TABLE survey_beacon_register DROP CONSTRAINT ck_sbr_conres_private"
        );
    }
};
