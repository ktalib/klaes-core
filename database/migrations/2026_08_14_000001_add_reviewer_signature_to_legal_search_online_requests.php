<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Records the approver's digital signature on an Online Legal Search request.
 *
 * Signing is an explicit act at approval time — the approver presses "Sign" in
 * the approve dialog — so the signature path is stored per request rather than
 * read from the user's profile at render time. A request approved without
 * signing keeps the blank signature line.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::connection('sqlsrv')->hasTable('legal_search_online_requests')) {
            return;
        }

        if (Schema::connection('sqlsrv')->hasColumn('legal_search_online_requests', 'reviewer_signature_path')) {
            return;
        }

        Schema::connection('sqlsrv')->table('legal_search_online_requests', function (Blueprint $table) {
            $table->string('reviewer_signature_path', 255)->nullable()->after('reviewer_rank');
            $table->dateTime('signed_at')->nullable()->after('reviewer_signature_path');
        });
    }

    public function down(): void
    {
        if (!Schema::connection('sqlsrv')->hasTable('legal_search_online_requests')) {
            return;
        }

        Schema::connection('sqlsrv')->table('legal_search_online_requests', function (Blueprint $table) {
            $table->dropColumn(['reviewer_signature_path', 'signed_at']);
        });
    }
};
