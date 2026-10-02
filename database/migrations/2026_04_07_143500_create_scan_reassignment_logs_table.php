<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::connection('sqlsrv')->create('scan_reassignment_logs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('scanning_id');
            $table->string('from_file_number', 100);
            $table->string('to_file_number', 100);
            $table->unsignedInteger('from_file_indexing_id')->nullable();
            $table->unsignedInteger('to_file_indexing_id')->nullable();
            $table->string('from_path', 500);
            $table->string('to_path', 500);
            $table->string('reason', 500)->nullable();
            $table->unsignedBigInteger('reassigned_by');
            $table->timestamps();

            // Foreign keys
            $table->foreign('scanning_id')
                  ->references('id')
                  ->on('scannings')
                  ->onDelete('cascade');

            $table->foreign('from_file_indexing_id')
                  ->references('id')
                  ->on('file_indexings')
                  ->onDelete('set null');

            $table->foreign('to_file_indexing_id')
                  ->references('id')
                  ->on('file_indexings')
                  ->onDelete('set null');

            $table->foreign('reassigned_by')
                  ->references('id')
                  ->on('users')
                  ->onDelete('restrict');

            // Indexes
            $table->index('scanning_id');
            $table->index('from_file_number');
            $table->index('to_file_number');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::connection('sqlsrv')->dropIfExists('scan_reassignment_logs');
    }
};
