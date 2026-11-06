<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The company's RC number on a Private registration (user, 2026-11-06). Additive: one nullable column,
 * the same size as cadastral_surveyors.firm_rc_no, which it fills on approval.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::connection('sqlsrv')->hasColumn('surveyor_portal_accounts', 'company_rc_no')) {
            Schema::connection('sqlsrv')->table('surveyor_portal_accounts', function (Blueprint $t) {
                $t->string('company_rc_no', 50)->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::connection('sqlsrv')->hasColumn('surveyor_portal_accounts', 'company_rc_no')) {
            Schema::connection('sqlsrv')->table('surveyor_portal_accounts', function (Blueprint $t) {
                $t->dropColumn('company_rc_no');
            });
        }
    }
};
