<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The consent letter now depends on who the parties are, not only on the kind
 * of consent: an individual-to-individual sale pays 3% stamp duty to KIRS, a
 * sale involving a company pays 1.5% to FIRS, and a mortgage has its own rates.
 *
 * transaction_type records that choice (a key of consent_bill.transaction_types)
 * and stamp_duty_payee the authority the letter directed stamp duty to, so a
 * reprint names the same payee even after the configuration moves on.
 *
 * Both are nullable: consents captured before this existed have neither and
 * keep printing the letter they were issued with.
 */
return new class extends Migration
{
    private const CONNECTION = 'sqlsrv';

    private const COLUMNS = ['transaction_type', 'stamp_duty_payee'];

    public function up(): void
    {
        $schema = Schema::connection(self::CONNECTION);

        Schema::connection(self::CONNECTION)->table('consent_applications', function (Blueprint $table) use ($schema) {
            if (!$schema->hasColumn('consent_applications', 'transaction_type')) {
                $table->string('transaction_type', 40)->nullable();
            }
            if (!$schema->hasColumn('consent_applications', 'stamp_duty_payee')) {
                $table->string('stamp_duty_payee', 10)->nullable();
            }
        });
    }

    public function down(): void
    {
        $schema = Schema::connection(self::CONNECTION);
        $present = array_values(array_filter(
            self::COLUMNS,
            fn($column) => $schema->hasColumn('consent_applications', $column)
        ));

        if ($present === []) {
            return;
        }

        Schema::connection(self::CONNECTION)->table('consent_applications', function (Blueprint $table) use ($present) {
            $table->dropColumn($present);
        });
    }
};
