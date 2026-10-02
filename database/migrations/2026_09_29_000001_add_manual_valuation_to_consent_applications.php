<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Manual valuation override on a consent application.
 *
 * Files valued before KLAES existed have no valuation_reports row, so the
 * Valuation-before-Consent gate refuses them even though the work was done and
 * the applicant has paid. These columns record an authorised officer accepting
 * that off-system valuation, with who did it and why, so the override is a
 * traceable decision rather than an invisible bypass.
 *
 * Deliberately NOT a substitute for valuation_report_id: that column stays null
 * on an overridden consent, so a manual acceptance can never be mistaken for a
 * report the Ministry actually holds.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('sqlsrv')->table('consent_applications', function (Blueprint $table) {
            if (!Schema::connection('sqlsrv')->hasColumn('consent_applications', 'manual_valuation')) {
                // Default 0, not nullable: every existing row is a normal
                // consent, and "unknown" is not a meaningful third state here.
                $table->boolean('manual_valuation')->default(0);
            }

            if (!Schema::connection('sqlsrv')->hasColumn('consent_applications', 'manual_valuation_reason')) {
                $table->string('manual_valuation_reason', 500)->nullable();
            }

            // The off-system reference the officer is relying on — an old
            // valuation number, a receipt, a file note.
            if (!Schema::connection('sqlsrv')->hasColumn('consent_applications', 'manual_valuation_ref')) {
                $table->string('manual_valuation_ref', 150)->nullable();
            }

            if (!Schema::connection('sqlsrv')->hasColumn('consent_applications', 'manual_valuation_by')) {
                $table->string('manual_valuation_by', 150)->nullable();
            }

            if (!Schema::connection('sqlsrv')->hasColumn('consent_applications', 'manual_valuation_user_id')) {
                $table->unsignedBigInteger('manual_valuation_user_id')->nullable();
            }

            if (!Schema::connection('sqlsrv')->hasColumn('consent_applications', 'manual_valuation_at')) {
                $table->dateTime('manual_valuation_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::connection('sqlsrv')->table('consent_applications', function (Blueprint $table) {
            foreach ([
                'manual_valuation',
                'manual_valuation_reason',
                'manual_valuation_ref',
                'manual_valuation_by',
                'manual_valuation_user_id',
                'manual_valuation_at',
            ] as $column) {
                if (Schema::connection('sqlsrv')->hasColumn('consent_applications', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
