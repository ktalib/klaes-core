<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** KSSGOF registration asks for a passport photograph (user, 2026-11-05); the SG sees it before approving. */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::connection('sqlsrv')->hasColumn('surveyor_portal_accounts', 'passport_path')) return;

        Schema::connection('sqlsrv')->table('surveyor_portal_accounts', function (Blueprint $t) {
            $t->string('passport_path', 500)->nullable();
        });
    }

    public function down(): void
    {
        if (! Schema::connection('sqlsrv')->hasColumn('surveyor_portal_accounts', 'passport_path')) return;

        Schema::connection('sqlsrv')->table('surveyor_portal_accounts', function (Blueprint $t) {
            $t->dropColumn('passport_path');
        });
    }
};
