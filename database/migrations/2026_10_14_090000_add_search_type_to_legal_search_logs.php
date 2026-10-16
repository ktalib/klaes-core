<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Legal Search Reports: whether a logged search was Official or Commercial.
 *
 *   search_type  Official | Commercial
 *
 * Set when the search is logged, from the Transaction Token Control (TTC) token
 * the search ran on: a token with a Requesting Body (Court, EFCC, ...) is
 * Official, a General token is Commercial. Additive: one nullable column; no
 * existing row changes (earlier logs never stored their token, so they stay blank).
 */
return new class extends Migration {
    public function up(): void
    {
        $schema = Schema::connection('sqlsrv');

        if (!$schema->hasColumn('legal_search_logs', 'search_type')) {
            $schema->table('legal_search_logs', function (Blueprint $table) {
                $table->string('search_type', 20)->nullable();
            });
        }
    }

    public function down(): void
    {
        $schema = Schema::connection('sqlsrv');
        if ($schema->hasColumn('legal_search_logs', 'search_type')) {
            $schema->table('legal_search_logs', function (Blueprint $table) {
                $table->dropColumn('search_type');
            });
        }
    }
};
