<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * An Old Register entry need not have a date (user, 2026-11-03: "remove validation on the date"); lines in the old
 * book may be undated. OP showings still require it in the form; only the column allows NULL.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::connection('sqlsrv')->statement('ALTER TABLE survey_op_showings ALTER COLUMN shown_on DATE NULL');
    }

    public function down(): void
    {
        if (DB::connection('sqlsrv')->table('survey_op_showings')->whereNull('shown_on')->exists()) {
            throw new RuntimeException('Some showings have no date; give them one before making shown_on NOT NULL again.');
        }
        DB::connection('sqlsrv')->statement('ALTER TABLE survey_op_showings ALTER COLUMN shown_on DATE NOT NULL');
    }
};
