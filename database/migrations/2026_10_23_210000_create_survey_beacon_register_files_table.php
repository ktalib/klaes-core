<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Beacon Register: several file numbers per line (user, 2026-10-23).
 *
 * One row per file number, in the order they were picked. The existing single
 * survey_beacon_register.file_number values are COPIED in (the column is left
 * as it is, and from now on holds the line's first file number). Additive only:
 * no existing row is changed.
 */
return new class extends Migration
{
    public function up(): void
    {
        $s = Schema::connection('sqlsrv');
        $db = DB::connection('sqlsrv');

        if (! $s->hasTable('survey_beacon_register_files')) {
            $s->create('survey_beacon_register_files', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('survey_beacon_register_id');
                $t->string('file_number', 100);
                $t->smallInteger('sort_order')->default(0);
                $t->unsignedBigInteger('created_by')->nullable();
                $t->unsignedBigInteger('updated_by')->nullable();
                $t->timestamps();

                $t->index('survey_beacon_register_id', 'ix_beacon_files_register');
                $t->index('file_number', 'ix_beacon_files_file_number');
            });
        }

        // Copy the single file numbers already recorded; skip any already copied.
        $db->statement("
            INSERT INTO survey_beacon_register_files (survey_beacon_register_id, file_number, sort_order, created_by, updated_by, created_at, updated_at)
            SELECT r.id, LTRIM(RTRIM(r.file_number)), 0, r.created_by, r.updated_by, GETDATE(), GETDATE()
            FROM survey_beacon_register r
            WHERE r.file_number IS NOT NULL AND LTRIM(RTRIM(r.file_number)) <> ''
              AND NOT EXISTS (SELECT 1 FROM survey_beacon_register_files f WHERE f.survey_beacon_register_id = r.id)
        ");
    }

    public function down(): void
    {
        Schema::connection('sqlsrv')->dropIfExists('survey_beacon_register_files');
    }
};
