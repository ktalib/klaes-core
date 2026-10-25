<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Survey beneficiaries seeded from VFC valuation records (user, 2026-10-25) keep
 * the valuation_compensations id they came from, so the seed can be traced and
 * never loads the same person twice. Additive only.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::connection('sqlsrv')->hasColumn('survey_beneficiaries', 'vfc_valuation_id')) {
            return;
        }

        Schema::connection('sqlsrv')->table('survey_beneficiaries', function (Blueprint $t) {
            $t->unsignedBigInteger('vfc_valuation_id')->nullable()->index();
        });
    }

    public function down(): void
    {
        if (! Schema::connection('sqlsrv')->hasColumn('survey_beneficiaries', 'vfc_valuation_id')) {
            return;
        }

        Schema::connection('sqlsrv')->table('survey_beneficiaries', function (Blueprint $t) {
            $t->dropIndex(['vfc_valuation_id']);
            $t->dropColumn('vfc_valuation_id');
        });
    }
};
