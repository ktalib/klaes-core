<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A Quick Search request raised on a file number that exists in BOTH
 * file_indexings and duplicate_fileno must record WHICH of those physical files
 * the requester asked for. The file number cannot carry that: both records share
 * it, which is the entire reason the selection list exists.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('sqlsrv')->table('digital_file_tracking_requests', function (Blueprint $table) {
            // Primary key of the chosen row, in the table named by
            // selected_record_source. Null on every request raised outside the
            // duplicate-selection flow (the normal, single-file case).
            $table->unsignedBigInteger('selected_record_id')->nullable()->after('file_title');
            // 'file_indexings' or 'duplicate_fileno' — the two source tables the
            // Quick Search selection list draws from
            // (FileLocationResolver::SOURCE_INDEXED / SOURCE_DUPLICATE).
            $table->string('selected_record_source', 32)->nullable()->after('selected_record_id');
        });
    }

    public function down(): void
    {
        Schema::connection('sqlsrv')->table('digital_file_tracking_requests', function (Blueprint $table) {
            $table->dropColumn(['selected_record_id', 'selected_record_source']);
        });
    }
};
