<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * GKN > Layout Plan (2026-10-25): a layout plan belongs to the application file it was
 * drawn for (an LPKN file from GKN Applications), picked with the global file number
 * selector. Additive only: one nullable column.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::connection('sqlsrv')->hasColumn('survey_lpkn', 'application_file_number')) {
            return;
        }
        Schema::connection('sqlsrv')->table('survey_lpkn', function (Blueprint $t) {
            $t->string('application_file_number', 100)->nullable()->index();
        });
    }

    public function down(): void
    {
        if (! Schema::connection('sqlsrv')->hasColumn('survey_lpkn', 'application_file_number')) {
            return;
        }
        Schema::connection('sqlsrv')->table('survey_lpkn', function (Blueprint $t) {
            $t->dropIndex(['application_file_number']);
            $t->dropColumn('application_file_number');
        });
    }
};
