<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * BUK/Zakara Layout Confirmation Forms (user, 2026-11-02; new paper template). A serial is issued
 * with a blank printed form; the filled paper is keyed in against its serial. A serial is voided,
 * never deleted, so printed numbers stay accounted for.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::connection('sqlsrv')->hasTable('buk_zakara_forms')) {
            return;
        }

        Schema::connection('sqlsrv')->create('buk_zakara_forms', function (Blueprint $t) {
            $t->id();
            $t->unsignedInteger('serial_no')->unique();
            $t->string('status', 20)->default('Generated');   // Generated, Printed, Captured, Voided
            $t->unsignedInteger('print_count')->default(0);
            $t->dateTime('printed_at')->nullable();

            // Land owner
            $t->string('land_owner_name', 255)->nullable();
            $t->string('section', 100)->nullable();
            $t->string('id_number', 100)->nullable();
            $t->string('location', 255)->nullable();
            $t->string('nin', 20)->nullable();
            $t->string('gsm', 30)->nullable();
            $t->string('address', 500)->nullable();
            // Vendor / agent
            $t->string('vendor_name', 255)->nullable();
            $t->string('vendor_address', 500)->nullable();
            $t->boolean('vendor_signed')->default(false);
            $t->date('vendor_date')->nullable();
            $t->string('vendor_gsm', 30)->nullable();
            // Committee chairman, ward / village head, head of compensations
            $t->string('chairman_name', 255)->nullable();
            $t->boolean('chairman_signed')->default(false);
            $t->date('chairman_date')->nullable();
            $t->string('village_head_name', 255)->nullable();
            $t->boolean('village_head_signed')->default(false);
            $t->date('village_head_date')->nullable();
            $t->string('compensation_head_name', 255)->nullable();
            $t->boolean('compensation_head_signed')->default(false);
            $t->date('compensation_head_date')->nullable();

            $t->string('void_reason', 500)->nullable();
            $t->unsignedBigInteger('voided_by')->nullable();
            $t->dateTime('voided_at')->nullable();
            $t->unsignedBigInteger('captured_by')->nullable();
            $t->dateTime('captured_at')->nullable();
            $t->unsignedBigInteger('created_by')->nullable();
            $t->unsignedBigInteger('updated_by')->nullable();
            $t->timestamps();

            $t->index('status');
        });
    }

    public function down(): void
    {
        Schema::connection('sqlsrv')->dropIfExists('buk_zakara_forms');
    }
};
