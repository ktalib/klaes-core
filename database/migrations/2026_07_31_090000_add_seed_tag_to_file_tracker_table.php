<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Marks trackers that were generated rather than logged by a registry officer.
 * Demo/training rows are otherwise indistinguishable from production movements,
 * so there was no safe way to clear them out again — the previous attempt fell
 * back to guessing at a random 90% of the whole table. A tagged row can be found
 * and deleted exactly: WHERE seed_tag = '<tag>'. NULL means a real record.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('sqlsrv')->table('file_tracker', function (Blueprint $table) {
            if (! Schema::connection('sqlsrv')->hasColumn('file_tracker', 'seed_tag')) {
                $table->string('seed_tag', 64)->nullable()->after('file_request_type');
                $table->index('seed_tag');
            }
        });
    }

    public function down(): void
    {
        Schema::connection('sqlsrv')->table('file_tracker', function (Blueprint $table) {
            if (Schema::connection('sqlsrv')->hasColumn('file_tracker', 'seed_tag')) {
                $table->dropIndex(['seed_tag']);
                $table->dropColumn('seed_tag');
            }
        });
    }
};
