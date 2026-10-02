<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The mother's recommendation letter, scanned, for a subdivision batch.
 *
 * A subdivision is one grant split into plots: the children inherit the mother's
 * recommendation rather than each earning one of their own, so the letter is
 * printed once, for the mother, and signed on paper. The mothers of these batches
 * have no recommendation record in KLAES at all — the letter exists only as that
 * signed sheet — so there is nothing to print for a child and nothing to link to.
 * Scanning it once and hanging it off the batch is what gives all 500 children
 * something to show.
 *
 * Keyed on rofo_batch_id, uniquely: one scan per batch is the whole point. A
 * re-upload replaces the row rather than adding a second, so "which is the current
 * letter" is never a question.
 *
 * Deliberately NOT a column on land_recommendations. The document belongs to the
 * batch, not to any one child; storing it per child would write the same path 500
 * times and leave the next upload to reconcile them.
 *
 * NOTE FOR DEPLOYMENT: artisan's migrations ledger lives in MySQL while these
 * tables are created on sqlsrv. The `migrations` table visible on sqlsrv is stale
 * and must not be trusted. Ship the paired SQL files in database/sql/ alongside
 * this migration.
 */
return new class extends Migration
{
    public function up(): void
    {
        $schema = Schema::connection('sqlsrv');

        if ($schema->hasTable('land_recommendation_batch_documents')) {
            return;
        }

        $schema->create('land_recommendation_batch_documents', function (Blueprint $table) {
            $table->bigIncrements('id');

            // The batch this letter covers. Unique: a batch has exactly one mother
            // recommendation, and a re-upload updates this row in place.
            $table->string('rofo_batch_id', 60)->unique();

            // Denormalised so the record still reads on its own once the batch's
            // children have been edited, re-batched or deleted.
            $table->string('mother_file_no', 100)->nullable();

            // Relative to the 'public' disk (storage/app/public), as every other
            // upload in this application stores it.
            $table->string('path', 500);
            $table->string('original_name', 255)->nullable();
            $table->string('mime_type', 120)->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();

            $table->unsignedBigInteger('uploaded_by')->nullable();
            $table->dateTime('uploaded_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection('sqlsrv')->dropIfExists('land_recommendation_batch_documents');
    }
};
