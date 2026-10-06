<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Which module created a Legacy Parcel Update (manual linkage): Deeds
 * (/manual-linkage) or Land (/manual-linkage?url=land_view).
 *
 * Until now Land's page was view-only, so every existing row was made in Deeds
 * and is stamped so.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::connection('sqlsrv')->hasColumn('manual_file_linkages', 'source')) {
            Schema::connection('sqlsrv')->table('manual_file_linkages', function (Blueprint $table) {
                $table->string('source', 20)->nullable();
            });
        }

        DB::connection('sqlsrv')->table('manual_file_linkages')
            ->whereNull('source')
            ->update(['source' => 'Deeds']);
    }

    public function down(): void
    {
        if (Schema::connection('sqlsrv')->hasColumn('manual_file_linkages', 'source')) {
            Schema::connection('sqlsrv')->table('manual_file_linkages', function (Blueprint $table) {
                $table->dropColumn('source');
            });
        }
    }
};
