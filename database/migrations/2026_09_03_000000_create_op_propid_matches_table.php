<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The audit trail behind OP → File Property ID Matching.
 *
 * That page rewrites prop_id on Occupancy Permit rows in bulk: an officer picks the
 * confirmed file on the left, picks the OPs that belong to it on the right, and every
 * selected OP is moved onto the file's prop_id. The old value is overwritten in place
 * — there is nowhere in `pra` or `instrument_capture` that remembers what it used to
 * be — so without this table a mis-keyed batch is unrecoverable and unattributable.
 *
 * One row per record touched, not per batch: a batch that moved eight OPs and their
 * four companion Transfer of Title rows writes twelve rows, all sharing one
 * batch_ref. That is what makes Undo possible, and it is per-record precisely because
 * the undo must be able to skip a record that something else has since moved on.
 *
 * record_kind separates what the officer chose from what was carried along with it:
 *
 *   op          the Occupancy Permit the officer ticked
 *   companion   a Transfer of Title that shared that OP's old prop_id, or points at
 *               it through source_op_id — moved with it, because an OP and its ToT
 *               are one parcel and leaving the ToT behind silently breaks the file's
 *               Legal Search timeline
 *
 * previous_prop_id is nvarchar, not int: `pra.prop_id` is nvarchar(100) and holds
 * blanks and non-canonical long ids in the wild, and the point of the column is to
 * restore EXACTLY what was there, not a tidied reading of it.
 *
 * NOTE FOR DEPLOYMENT: artisan's migrations ledger lives in MySQL while this table is
 * created on sqlsrv. The `migrations` table visible on sqlsrv is stale and must not be
 * trusted. Ship database/sql/2026_09_03_create_op_propid_matches.sql and its
 * *_ledger.mysql.sql companion alongside this migration.
 */
return new class extends Migration
{
    public function up(): void
    {
        $schema = Schema::connection('sqlsrv');

        if ($schema->hasTable('op_propid_matches')) {
            return;
        }

        $schema->create('op_propid_matches', function (Blueprint $table) {
            $table->bigIncrements('id');

            // One batch of work, as the officer performed it. Undo is addressed by this.
            $table->string('batch_ref', 40)->index();

            // The control record: the file whose prop_id everything was moved onto.
            $table->string('target_file_number', 100)->nullable();
            $table->unsignedInteger('target_prop_id');

            // Which table the moved record lives in, and which row.
            $table->string('source_table', 40);
            $table->unsignedBigInteger('record_id');

            // 'op' — ticked by the officer; 'companion' — carried along with one.
            $table->string('record_kind', 20)->default('op');

            // Denormalised so a row still reads on its own once the record moves again.
            $table->string('op_serial_number', 100)->nullable();
            $table->string('record_file_number', 100)->nullable();

            $table->string('previous_prop_id', 100)->nullable();
            $table->string('new_prop_id', 100);

            $table->unsignedBigInteger('matched_by')->nullable();

            // Set when the batch is reversed. A reverted row is kept, not deleted: the
            // fact that a batch was undone is itself part of the file's history.
            $table->dateTime('reverted_at')->nullable();
            $table->unsignedBigInteger('reverted_by')->nullable();

            $table->timestamps();

            $table->index(['source_table', 'record_id'], 'op_propid_matches_record_idx');
            $table->index('target_prop_id', 'op_propid_matches_target_idx');
        });
    }

    public function down(): void
    {
        Schema::connection('sqlsrv')->dropIfExists('op_propid_matches');
    }
};
