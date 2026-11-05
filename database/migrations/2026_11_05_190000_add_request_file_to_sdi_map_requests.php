<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** CR 03112026 item 12: the scanned map or sketch the requester brings. Additive and nullable. */
return new class extends Migration
{
    public function up(): void
    {
        $schema = Schema::connection('sqlsrv');
        if (! $schema->hasColumn('sdi_map_requests', 'request_file_path')) {
            $schema->table('sdi_map_requests', function (Blueprint $t) {
                $t->string('request_file_path', 500)->nullable();
                $t->string('request_file_name', 255)->nullable();
            });
        }
    }

    public function down(): void
    {
        $schema = Schema::connection('sqlsrv');
        if ($schema->hasColumn('sdi_map_requests', 'request_file_path')) {
            $schema->table('sdi_map_requests', fn (Blueprint $t) => $t->dropColumn(['request_file_path', 'request_file_name']));
        }
    }
};
