<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Timeline (Days) — the agreed return window — was only ever a UI field: it computed
 * the Expected Return Date and was then discarded, leaving no way to tell a deliberate
 * 90-day loan from a mistyped one. Storing it makes the countdown reconstructible and
 * lets an override be spotted against the purpose's own turnaround.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('sqlsrv')->table('file_tracker', function (Blueprint $table) {
            $table->unsignedSmallInteger('timeline_days')->nullable()->after('deadline');
        });
    }

    public function down(): void
    {
        Schema::connection('sqlsrv')->table('file_tracker', function (Blueprint $table) {
            $table->dropColumn('timeline_days');
        });
    }
};
