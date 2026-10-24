<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Transactions pulled off a file by "Move Transaction" on Indexed Files (user,
 * 2026-10-24): a wrong or wrongly entered PRA / File History / CofO row that does
 * not belong to the physical file. Each row is snapshotted here, given a system
 * temp file no (the MOV-DUP-##### series shared with indexing_duplicates) and
 * deleted from its source table. Additive only.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::connection('sqlsrv')->hasTable('moved_transactions')) {
            return;
        }

        Schema::connection('sqlsrv')->create('moved_transactions', function (Blueprint $t) {
            $t->id();
            // One number per Move action, shared by every row moved together.
            $t->string('temp_file_no', 100);
            $t->unsignedBigInteger('file_indexing_id')->nullable();
            $t->string('file_number', 100)->nullable();
            $t->string('source_table', 64);
            $t->unsignedBigInteger('source_id');
            $t->string('transaction_type', 255)->nullable();
            $t->string('party_1', 500)->nullable();
            $t->string('party_2', 500)->nullable();
            $t->string('transaction_date', 50)->nullable();
            $t->string('reg_no', 50)->nullable();
            // The file the transaction really belongs to, when the operator knows it.
            $t->string('belongs_to', 100)->nullable();
            $t->string('reason', 500);
            // The whole source row, column for column, as it was before the delete.
            $t->longText('snapshot');
            $t->string('moved_by', 150)->nullable();
            $t->unsignedBigInteger('moved_by_id')->nullable();
            $t->dateTime('moved_at');
            $t->timestamps();
            $t->index('temp_file_no');
            $t->index('file_number');
            $t->index(['source_table', 'source_id']);
        });
    }

    public function down(): void
    {
        Schema::connection('sqlsrv')->dropIfExists('moved_transactions');
    }
};
