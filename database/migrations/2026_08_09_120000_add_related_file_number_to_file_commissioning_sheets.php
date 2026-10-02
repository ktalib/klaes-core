<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 2026_06_26_172236 added related_file_number AND related_file_title, but on some
 * databases only related_file_title landed — so CommissioningSheetController::
 * generateAndPrint() fails with "Invalid column name 'related_file_number'" for any
 * file that has a related file (every ST conversion, and any MLS file whose
 * fileNumber.related_fileno is set).
 *
 * Guarded so it is a no-op wherever the original migration applied in full.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::connection('sqlsrv')->hasColumn('file_commissioning_sheets', 'related_file_number')) {
            return;
        }

        Schema::connection('sqlsrv')->table('file_commissioning_sheets', function (Blueprint $table) {
            $table->string('related_file_number', 255)->nullable()->after('lga');
        });
    }

    public function down(): void
    {
        // Left in place: dropping it would re-break the sheets that depend on it.
    }
};
