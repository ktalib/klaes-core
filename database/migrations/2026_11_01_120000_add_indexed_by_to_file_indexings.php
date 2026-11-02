<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Indexing piece-rate pay (Kunle, 2026-11-01; docs/plans/INDEXING_PIECE_RATE_PAYROLL_PLAN.md).
// created_by keeps holding the indexer's NAME (many screens read it; user: never convert it).
// These two columns sit beside it: who indexed the file (user id) and when, written only by
// the File Indexing form. Pay reads these, so commissioning/backfill rows never earn.
// Additive: two nullable columns + one index; no existing value changes.
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('sqlsrv')->table('file_indexings', function (Blueprint $t) {
            $t->unsignedBigInteger('indexed_by_user_id')->nullable();
            $t->dateTime('indexed_at')->nullable();
            $t->index(['indexed_by_user_id', 'indexed_at'], 'ix_file_indexings_indexed_by');
        });
    }

    public function down(): void
    {
        Schema::connection('sqlsrv')->table('file_indexings', function (Blueprint $t) {
            $t->dropIndex('ix_file_indexings_indexed_by');
            $t->dropColumn(['indexed_by_user_id', 'indexed_at']);
        });
    }
};
