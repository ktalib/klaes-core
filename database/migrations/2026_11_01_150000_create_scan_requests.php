<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// "Needs Scanning" (user, 2026-11-01): Create File Index refuses a file whose pages cannot be
// found; the indexer adds it here so the scanning team scans it. A request closes itself when
// the scan is found (Check now) or when the file is indexed. Additive only.
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('sqlsrv')->create('scan_requests', function (Blueprint $t) {
            $t->id();
            $t->string('file_number', 150);
            $t->string('registry', 100)->nullable();
            $t->string('reason', 50)->default('not_scanned'); // not_scanned | empty_folder
            $t->string('note', 500)->nullable();
            $t->string('status', 20)->default('pending');     // pending | scanned | cancelled
            $t->unsignedBigInteger('requested_by')->nullable();
            $t->dateTime('resolved_at')->nullable();
            $t->unsignedBigInteger('resolved_by')->nullable();
            $t->string('resolution', 255)->nullable();
            $t->timestamps();
            $t->index(['status', 'created_at'], 'ix_scan_requests_status');
            $t->index('file_number', 'ix_scan_requests_file_number');
        });
    }

    public function down(): void
    {
        Schema::connection('sqlsrv')->dropIfExists('scan_requests');
    }
};
