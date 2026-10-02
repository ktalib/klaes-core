<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // One-time production data patch against the live 'sqlsrv' connection
        // (there is no isolated test copy of it) — never run it as part of a
        // test's migrate:fresh/RefreshDatabase setup.
        if (app()->environment('testing')) {
            return;
        }

        // Fix paths in users table - remove 'public/' prefix
        DB::connection('sqlsrv')->table('users')
            ->where('signature', 'like', 'public/land_officer_signatures/%')
            ->update([
                'signature' => DB::raw("REPLACE(signature, 'public/land_officer_signatures/', 'land_officer_signatures/')")
            ]);

        // Fix paths in land_officers table - remove 'public/' prefix
        DB::connection('sqlsrv')->table('land_officers')
            ->where('signature_file', 'like', 'public/land_officer_signatures/%')
            ->update([
                'signature_file' => DB::raw("REPLACE(signature_file, 'public/land_officer_signatures/', 'land_officer_signatures/')")
            ]);
    }

    public function down(): void
    {
        if (app()->environment('testing')) {
            return;
        }

        // Revert: add 'public/' prefix back
        DB::connection('sqlsrv')->table('users')
            ->where('signature', 'like', 'land_officer_signatures/%')
            ->where('signature', 'not like', 'public/%')
            ->update([
                'signature' => DB::raw("REPLACE(signature, 'land_officer_signatures/', 'public/land_officer_signatures/')")
            ]);

        DB::connection('sqlsrv')->table('land_officers')
            ->where('signature_file', 'like', 'land_officer_signatures/%')
            ->where('signature_file', 'not like', 'public/%')
            ->update([
                'signature_file' => DB::raw("REPLACE(signature_file, 'land_officer_signatures/', 'public/land_officer_signatures/')")
            ]);
    }
};
