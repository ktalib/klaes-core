<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Append-only audit trail of a file's captured state.
 *
 * file_indexings is edited IN PLACE, so every correction, re-index, new link and
 * newly captured transaction silently overwrites what was there before. This table
 * keeps the "before": one row per version of the file, written whenever the indexing
 * UI changes it, holding the whole readable state of the file at that moment plus a
 * diff against the previous version.
 *
 * Rows are NEVER updated or deleted. A change produces a NEW version — that is the
 * whole point, and App\Services\FileSnapshotService only ever inserts.
 *
 * Deliberately NOT written from model events: artisan backfills and bulk imports
 * touch tens of thousands of rows and would drown the real operator trail. Only the
 * three indexing UI entry points capture (store / update / storeFromIndexing).
 *
 * NOTE FOR DEPLOYMENT: artisan's migrations ledger lives in MySQL while this table is
 * created on sqlsrv. The `migrations` table visible on sqlsrv is stale and must not be
 * trusted. Ship the paired SQL files in database/sql/ alongside this migration.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('sqlsrv')->create('file_snapshots', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->unsignedBigInteger('file_indexing_id')->nullable();

            // Identity AS AT this version. Denormalised on purpose: a file number can
            // be corrected on the row itself, and the trail must still show what the
            // file was called when each snapshot was taken.
            $table->string('file_number', 255)->nullable();
            $table->string('temp_file_no', 255)->nullable();
            $table->string('tracking_id', 255)->nullable();
            $table->unsignedBigInteger('prop_id')->nullable();
            $table->unsignedBigInteger('parent_prop_id')->nullable();

            // 1-based, per file_indexing_id. Allocated under a lock so two concurrent
            // saves cannot claim the same number.
            $table->unsignedInteger('version')->default(1);

            // indexed | edited | linked | transaction_added
            $table->string('event_type', 40);
            $table->string('event_label', 255)->nullable();

            // The snapshot itself, and the diff against the previous version.
            // longText -> nvarchar(max) on sqlsrv. `changes` is NULL on version 1:
            // there is nothing to compare a first snapshot against.
            $table->longText('payload')->nullable();
            $table->longText('changes')->nullable();
            $table->unsignedInteger('changed_field_count')->default(0);

            // sha256 of payload. A save that changed nothing produces an identical
            // hash and is skipped, so the trail is not padded with no-op versions.
            $table->char('payload_hash', 64)->nullable();

            $table->unsignedBigInteger('performed_by')->nullable();
            // Denormalised: users get renamed, deactivated and deleted, and an audit
            // row that can no longer name who acted has lost most of its value.
            $table->string('performed_by_name', 255)->nullable();
            $table->dateTime('performed_at')->nullable();

            // file_indexing.store | file_indexing.update | property_record.store_from_indexing
            $table->string('source', 60)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 512)->nullable();

            $table->timestamps();

            $table->index(['file_indexing_id', 'version'], 'ix_fsnap_file_version');
            $table->index('file_number', 'ix_fsnap_file_number');
            $table->index(['event_type', 'performed_at'], 'ix_fsnap_event');
        });
    }

    public function down(): void
    {
        Schema::connection('sqlsrv')->dropIfExists('file_snapshots');
    }
};
