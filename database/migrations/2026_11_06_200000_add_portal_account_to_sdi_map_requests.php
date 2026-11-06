<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Map requests a surveyor sends from the KSSGOF portal (user, 2026-11-06). The surveyor's account, so the
 * portal shows each surveyor their own and KSSGOF Admin lists them. Nullable: requests logged by Survey staff have none.
 */
return new class extends Migration
{
    public function up(): void
    {
        $schema = Schema::connection('sqlsrv');

        if (! $schema->hasColumn('sdi_map_requests', 'surveyor_portal_account_id')) {
            $schema->table('sdi_map_requests', function (Blueprint $t) {
                $t->unsignedBigInteger('surveyor_portal_account_id')->nullable()->index('ix_sdi_map_requests_portal_account');
            });
        }
    }

    public function down(): void
    {
        $schema = Schema::connection('sqlsrv');

        if ($schema->hasColumn('sdi_map_requests', 'surveyor_portal_account_id')) {
            $schema->table('sdi_map_requests', function (Blueprint $t) {
                $t->dropIndex('ix_sdi_map_requests_portal_account');
                $t->dropColumn('surveyor_portal_account_id');
            });
        }
    }
};
