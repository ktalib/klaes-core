<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Records the purpose the requester gave for an Online Legal Search.
 *
 * Both the id and the name are stored: the id links back to the lookup, and the
 * name is a snapshot so a historic request still reads correctly if the lookup
 * entry is later renamed or deactivated.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::connection('sqlsrv')->hasTable('legal_search_online_requests')) {
            return;
        }

        if (Schema::connection('sqlsrv')->hasColumn('legal_search_online_requests', 'purpose_id')) {
            return;
        }

        Schema::connection('sqlsrv')->table('legal_search_online_requests', function (Blueprint $table) {
            $table->unsignedBigInteger('purpose_id')->nullable()->after('search_params');
            $table->string('purpose', 150)->nullable()->after('purpose_id');

            $table->index('purpose_id');
        });
    }

    public function down(): void
    {
        if (!Schema::connection('sqlsrv')->hasTable('legal_search_online_requests')) {
            return;
        }

        Schema::connection('sqlsrv')->table('legal_search_online_requests', function (Blueprint $table) {
            $table->dropColumn(['purpose_id', 'purpose']);
        });
    }
};
