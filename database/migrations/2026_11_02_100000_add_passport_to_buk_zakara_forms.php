<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The land owner's passport photo on a BUK/Zakara Confirmation Form (user, 2026-11-02). Kept with the
 * form on the public disk (buk_zakara/passports/): the form has no file number, so no EDMS folder.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::connection('sqlsrv')->hasColumn('buk_zakara_forms', 'passport_path')) {
            Schema::connection('sqlsrv')->table('buk_zakara_forms', function (Blueprint $t) {
                $t->string('passport_path', 255)->nullable();
            });
        }
    }

    public function down(): void
    {
        Schema::connection('sqlsrv')->table('buk_zakara_forms', function (Blueprint $t) {
            $t->dropColumn('passport_path');
        });
    }
};
