<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Somewhere to keep the caveator's (or their solicitor's) phone number.
 *
 * caveats records `petitioner` and `petitioner_address` and no way to reach
 * them. Both caveat messages -- placed, and lifted -- go to the caveator, so a
 * number has to be captured on the Place Caveat form.
 *
 * Nullable. Existing caveats have no number, and the SMS falls back to the
 * resolver (file_indexings via caveats.prop_id) for those, which is a prefill of
 * last resort rather than a source of truth.
 *
 * NOTE: the caveats table itself was never created by a Laravel migration -- it
 * comes from database/sql/caveat_system_database_setup.sql -- so this migration
 * checks the table exists before touching it, and the SQL companion is the file
 * that actually runs on production.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::connection('sqlsrv')->hasTable('caveats')) {
            return;
        }

        if (Schema::connection('sqlsrv')->hasColumn('caveats', 'petitioner_phone')) {
            return;
        }

        Schema::connection('sqlsrv')->table('caveats', function (Blueprint $table) {
            $table->string('petitioner_phone', 100)->nullable()->after('petitioner_address');
        });
    }

    public function down(): void
    {
        if (!Schema::connection('sqlsrv')->hasColumn('caveats', 'petitioner_phone')) {
            return;
        }

        Schema::connection('sqlsrv')->table('caveats', function (Blueprint $table) {
            $table->dropColumn('petitioner_phone');
        });
    }
};
