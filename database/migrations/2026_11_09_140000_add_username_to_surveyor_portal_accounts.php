<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 | KSSGOF portal sign-in by username and password (user, 2026-11-09). Registration asks for the username; accounts
 | made before it existed (test accounts) get their email as their username. Unique, ignoring case (the column's
 | collation is case-insensitive); the filtered index lets older rows stay blank until filled. Additive.
 */
return new class extends Migration
{
    public function up(): void
    {
        $s = Schema::connection('sqlsrv');
        if (! $s->hasColumn('surveyor_portal_accounts', 'username')) {
            $s->table('surveyor_portal_accounts', fn (Blueprint $t) => $t->string('username', 150)->nullable());
        }

        DB::connection('sqlsrv')->table('surveyor_portal_accounts')
            ->where(fn ($q) => $q->whereNull('username')->orWhere('username', ''))
            ->whereNotNull('email')->where('email', '!=', '')
            ->update(['username' => DB::raw('LOWER(LTRIM(RTRIM(email)))')]);

        $exists = DB::connection('sqlsrv')->selectOne("SELECT 1 AS x FROM sys.indexes WHERE name = 'ux_surveyor_portal_username'");
        if (! $exists) {
            DB::connection('sqlsrv')->statement('CREATE UNIQUE INDEX ux_surveyor_portal_username ON surveyor_portal_accounts (username) WHERE username IS NOT NULL');
        }
    }

    public function down(): void
    {
        $s = Schema::connection('sqlsrv');
        if (DB::connection('sqlsrv')->selectOne("SELECT 1 AS x FROM sys.indexes WHERE name = 'ux_surveyor_portal_username'")) {
            DB::connection('sqlsrv')->statement('DROP INDEX ux_surveyor_portal_username ON surveyor_portal_accounts');
        }
        if ($s->hasColumn('surveyor_portal_accounts', 'username')) {
            $s->table('surveyor_portal_accounts', fn (Blueprint $t) => $t->dropColumn('username'));
        }
    }
};
