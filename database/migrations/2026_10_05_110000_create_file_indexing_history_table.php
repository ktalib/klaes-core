<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * History of file-title renames made by a Deed of Assignment / Gift (or other
 * transfer) registration. One row per record whose name actually changed, in
 * each table the title is mirrored to, so the previous owner's name is never
 * lost when InstrumentRegistrationService::syncPartyNames() overwrites it.
 */
return new class extends Migration
{
    public function up(): void
    {
        $schema = Schema::connection('sqlsrv');

        if ($schema->hasTable('file_indexing_history')) {
            return;
        }

        $schema->create('file_indexing_history', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('file_number', 100)->index();
            $table->string('table_name', 50);
            $table->unsignedBigInteger('record_id');
            $table->text('old_name')->nullable();
            $table->text('new_name')->nullable();
            $table->string('instrument_type', 150)->nullable();
            $table->unsignedBigInteger('deed_registration_id')->nullable()->index();
            $table->unsignedBigInteger('instrument_capture_id')->nullable()->index();
            $table->string('source', 30)->nullable();
            $table->unsignedBigInteger('changed_by')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['table_name', 'record_id']);
        });
    }

    public function down(): void
    {
        Schema::connection('sqlsrv')->dropIfExists('file_indexing_history');
    }
};
