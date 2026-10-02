<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Old file numbers — the flat ledger behind the two places an old number is picked:
 *
 *   1. MLPP File Number Generator -> Re-Issuance of FileNo -> "Old FileNo (Duplicate)"
 *   2. The generator list's Edit modal -> "Old File Number" checkbox
 *
 * Both already wrote a single string to mls_file_no.old_fileno, which can only ever
 * hold ONE value and is silently overwritten the next time the file is edited. This
 * table keeps every old number a file has carried, one row each, so the history
 * survives; mls_file_no.old_fileno and file_indexings.old_fileno stay as the
 * "current" mirrors that the existing screens read.
 */
return new class extends Migration
{
    public function up(): void
    {
        $schema = Schema::connection('sqlsrv');

        if ($schema->hasTable('old_file_numbers')) {
            return;
        }

        $schema->create('old_file_numbers', function (Blueprint $table) {
            $table->id();

            // The live registry number the file is known by today (mls_file_no
            // .full_file_number / fileNumber.mlsfNo / file_indexings.file_number).
            $table->string('file_number', 100);

            // The legacy / duplicated number this file used to carry.
            $table->string('old_file_number', 100);

            // Title as it stood on the old file, when the picker supplied one.
            $table->string('old_file_title', 500)->nullable();

            // reissuance | edit | manual | import — where the value was entered, kept
            // so a Re-Issuance record is distinguishable from a plain correction.
            $table->string('source', 30)->default('manual');

            // Resolved once, at write time, so the mapping is auditable rather than
            // re-derived on every read. Nullable: an unindexed file is still valid.
            $table->unsignedBigInteger('file_indexing_id')->nullable();

            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            // One row per (file, old number) pair. Re-saving the same pair updates
            // the existing row instead of stacking duplicates.
            $table->unique(['file_number', 'old_file_number'], 'old_file_numbers_pair_unique');
            $table->index('old_file_number', 'old_file_numbers_old_idx');
            $table->index('file_indexing_id', 'old_file_numbers_indexing_idx');
        });
    }

    public function down(): void
    {
        Schema::connection('sqlsrv')->dropIfExists('old_file_numbers');
    }
};
