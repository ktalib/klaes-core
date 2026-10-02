<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Valuation-linked consent bill. Additive only: existing consent applications
 * keep working with every new column null, which is what distinguishes a record
 * captured before billing existed from one billed at zero.
 */
return new class extends Migration
{
    private const CONNECTION = 'sqlsrv';

    private const COLUMNS = [
        'valuation_report_id',
        'valuation_amount',
        'cgt_rate',
        'cgt_amount',
        'stamp_duty_rate',
        'stamp_duty_amount',
        'registration_fee',
        'assignment_fee',
        'processing_fee',
        'bill_total',
        'bill_computed_at',
    ];

    public function up(): void
    {
        Schema::connection(self::CONNECTION)->table('consent_applications', function (Blueprint $table) {
            // Which valuation the figures came from, so a later edit to that
            // report can be detected rather than silently changing this bill.
            if (!Schema::connection(self::CONNECTION)->hasColumn('consent_applications', 'valuation_report_id')) {
                $table->unsignedBigInteger('valuation_report_id')->nullable();
            }

            // The Section E amount as it stood when the bill was computed. Held
            // here, not read back from the valuation, so a reprint of an issued
            // letter keeps its original base.
            if (!Schema::connection(self::CONNECTION)->hasColumn('consent_applications', 'valuation_amount')) {
                $table->decimal('valuation_amount', 18, 2)->nullable();
            }

            // Rates are stored beside the amounts they produced: without them a
            // historical bill cannot be explained after the configured rate moves.
            foreach (['cgt_rate', 'stamp_duty_rate'] as $rate) {
                if (!Schema::connection(self::CONNECTION)->hasColumn('consent_applications', $rate)) {
                    $table->decimal($rate, 8, 4)->nullable();
                }
            }

            foreach ([
                'cgt_amount',
                'stamp_duty_amount',
                'registration_fee',
                'assignment_fee',
                'processing_fee',
                'bill_total',
            ] as $amount) {
                if (!Schema::connection(self::CONNECTION)->hasColumn('consent_applications', $amount)) {
                    $table->decimal($amount, 18, 2)->nullable();
                }
            }

            if (!Schema::connection(self::CONNECTION)->hasColumn('consent_applications', 'bill_computed_at')) {
                $table->dateTime('bill_computed_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::connection(self::CONNECTION)->table('consent_applications', function (Blueprint $table) {
            foreach (self::COLUMNS as $column) {
                if (Schema::connection(self::CONNECTION)->hasColumn('consent_applications', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
