<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'sqlsrv';

    public function up()
    {
        Schema::connection('sqlsrv')->create('op_verifications', function (Blueprint $table) {
            $table->id();

            // Identity of the OP record that was verified. prop_id / capture / pra
            // mirror the OpRecordSource row; record_key is the stable lookup key
            // ("prop:123", "cap:45", "pra:9", or "serial:333" when nothing matched).
            $table->string('record_key', 100)->nullable()->index();
            $table->string('op_serial_number', 100)->nullable()->index();
            $table->string('prop_id', 50)->nullable();
            $table->unsignedBigInteger('source_capture_id')->nullable();
            $table->unsignedBigInteger('pra_id')->nullable();

            // Snapshot of what the officer saw at verification time
            $table->string('mls_file_number', 100)->nullable();
            $table->string('temp_fileno', 100)->nullable();
            $table->string('op_type', 100)->nullable();
            $table->string('grantor', 255)->nullable();
            $table->string('allottee', 255)->nullable();
            $table->string('property_description', 500)->nullable();

            // How it was checked and how it came out
            $table->string('method', 20)->default('serial');   // serial | qr
            $table->string('status', 30);                      // verified | suspicious | mismatch | not_found
            $table->text('remarks')->nullable();

            // Audit
            $table->unsignedBigInteger('verified_by')->nullable();
            $table->string('verified_by_name', 255)->nullable();
            $table->dateTime('verified_at')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::connection('sqlsrv')->dropIfExists('op_verifications');
    }
};
