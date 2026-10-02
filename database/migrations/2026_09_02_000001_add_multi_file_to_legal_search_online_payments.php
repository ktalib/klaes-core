<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * One Online Legal Search payment may now cover several files, charged at the
 * unit price per file.
 *
 * The payment row keeps `file_number` as the PRIMARY (first) file so every
 * existing lookup — result(), the status page, the admin queue — keeps working
 * untouched. `file_numbers` records the whole set and `file_count` the number
 * actually charged for, which is what the amount is verified against.
 *
 * Each file still gets its own legal_search_online_requests row, sharing this
 * payment_id: a Legal Search report is a per-file document with its own
 * particulars and signature, and a Director must be able to approve one file
 * while rejecting another.
 *
 * Existing rows are single-file; the defaults describe them correctly, so no
 * backfill is needed.
 */
return new class extends Migration
{
    public function up(): void
    {
        $table = 'legal_search_online_payments';

        if (!Schema::connection('sqlsrv')->hasTable($table)) {
            return;
        }

        Schema::connection('sqlsrv')->table($table, function (Blueprint $blueprint) use ($table) {
            if (!Schema::connection('sqlsrv')->hasColumn($table, 'file_count')) {
                $blueprint->unsignedSmallInteger('file_count')->default(1);
            }

            if (!Schema::connection('sqlsrv')->hasColumn($table, 'file_numbers')) {
                // JSON array of every file number this payment covers, primary first.
                $blueprint->text('file_numbers')->nullable();
            }
        });

        // A payment must always cover at least one file.
        DB::connection('sqlsrv')->statement(
            "IF OBJECT_ID('chk_lsop_file_count', 'C') IS NULL
             ALTER TABLE legal_search_online_payments
             ADD CONSTRAINT chk_lsop_file_count CHECK (file_count >= 1)"
        );
    }

    public function down(): void
    {
        $table = 'legal_search_online_payments';

        if (!Schema::connection('sqlsrv')->hasTable($table)) {
            return;
        }

        DB::connection('sqlsrv')->statement(
            "IF OBJECT_ID('chk_lsop_file_count', 'C') IS NOT NULL
             ALTER TABLE legal_search_online_payments DROP CONSTRAINT chk_lsop_file_count"
        );

        Schema::connection('sqlsrv')->table($table, function (Blueprint $blueprint) {
            $blueprint->dropColumn(['file_count', 'file_numbers']);
        });
    }
};
