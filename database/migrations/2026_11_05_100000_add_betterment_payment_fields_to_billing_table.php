<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Betterment payment details on billing: what was actually paid (it can differ from the
 * bill) and the evidence of payment. Betterment_receipt_date and Betterment_receipt_notes
 * are what the receipt save and the director's approval page already expected.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('sqlsrv')->table('billing', function (Blueprint $table) {
            if (!Schema::connection('sqlsrv')->hasColumn('billing', 'Betterment_receipt_date')) {
                $table->string('Betterment_receipt_date', 50)->nullable();
            }
            if (!Schema::connection('sqlsrv')->hasColumn('billing', 'Betterment_receipt_notes')) {
                $table->text('Betterment_receipt_notes')->nullable();
            }
            if (!Schema::connection('sqlsrv')->hasColumn('billing', 'Betterment_amount_paid')) {
                $table->decimal('Betterment_amount_paid', 18, 2)->nullable();
            }
            if (!Schema::connection('sqlsrv')->hasColumn('billing', 'Betterment_evidence_path')) {
                $table->string('Betterment_evidence_path', 500)->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::connection('sqlsrv')->table('billing', function (Blueprint $table) {
            $table->dropColumn(['Betterment_receipt_date', 'Betterment_receipt_notes', 'Betterment_amount_paid', 'Betterment_evidence_path']);
        });
    }
};
