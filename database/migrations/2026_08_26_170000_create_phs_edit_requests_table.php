<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * PHS "Send Edit Request" — correction workflow for a search result the member
 * says is wrong, and the authorisation record for the free re-run that follows.
 *
 * Deliberately NOT folded into phs_feedback. Feedback is a message thread; this
 * is a state machine that ends by AUTHORISING A FREE SEARCH, so it needs its own
 * status column, its own audit columns, and above all a single-use consumption
 * marker. Overloading feedback would make "has this member already had their
 * free re-run?" a question about the newest row in a message thread, which is
 * exactly the kind of ambiguity that turns into a billing dispute.
 *
 * The free-re-run guard lives in three columns and must stay that way:
 *   status              -> only READY_FOR_RERUN authorises a free search
 *   file_number         -> the re-run must be for the SAME file, not a new one
 *   rerun_search_log_id -> set once the re-run happens; a second attempt is
 *                          refused because this is no longer null
 *
 * original_result stores the report payload the member actually saw, so the
 * admin corrects against what was complained about rather than against whatever
 * the record looks like by the time they open it.
 *
 * NOTE FOR DEPLOYMENT: artisan's migrations ledger lives in MySQL while these
 * tables are created on sqlsrv. The `migrations` table visible on sqlsrv is
 * stale and must not be trusted. Ship the paired SQL file in database/sql/
 * alongside this migration.
 */
return new class extends Migration
{
    public function up(): void
    {
        $schema = Schema::connection('sqlsrv');

        if ($schema->hasTable('phs_edit_requests')) {
            return;
        }

        $schema->create('phs_edit_requests', function (Blueprint $table) {
            $table->bigIncrements('id');

            // who is complaining
            $table->unsignedBigInteger('phs_institution_id')->nullable();
            $table->unsignedBigInteger('phs_member_id')->nullable();
            $table->string('requester_name', 190)->nullable();
            $table->string('requester_email', 190)->nullable();

            // what they are complaining about
            $table->unsignedBigInteger('search_log_id')->nullable();
            $table->string('reference_no', 60)->nullable();
            $table->string('file_number', 100)->nullable();
            $table->string('reason_category', 40)->nullable();
            $table->text('reason')->nullable();

            // The report exactly as the member received it. nvarchar(max) rather
            // than ->json(): the older PHS tables all store JSON in nvarchar and
            // the readers json_decode() it, so this matches its neighbours.
            $table->text('original_result')->nullable();

            // workflow
            $table->string('status', 30)->default('edit_requested');
            $table->dateTime('requested_at')->nullable();

            // correction
            $table->unsignedBigInteger('reviewed_by')->nullable();
            $table->string('reviewer_name', 190)->nullable();
            $table->text('admin_response')->nullable();
            $table->dateTime('corrected_at')->nullable();

            // the free re-run this request authorises, consumed exactly once
            $table->unsignedBigInteger('rerun_search_log_id')->nullable();
            $table->dateTime('rerun_at')->nullable();
            $table->unsignedBigInteger('rerun_by')->nullable();

            $table->string('ip_address', 45)->nullable();
            $table->timestamps();

            // No foreign keys, matching the house style on sqlsrv tables that
            // reference legacy//portal tables.
            $table->index('phs_institution_id', 'ix_per_institution');
            $table->index('phs_member_id', 'ix_per_member');
            $table->index('status', 'ix_per_status');
            $table->index('file_number', 'ix_per_file_number');
            $table->index('reference_no', 'ix_per_reference');
        });

        // One OPEN (unconsumed) edit request per member+file. Without this, a
        // member could open several requests on the same file and collect a free
        // re-run for each. Filtered so consumed/closed rows never block a new one.
        DB::connection('sqlsrv')->statement(
            'CREATE UNIQUE INDEX ux_per_open_per_member_file
               ON phs_edit_requests (phs_member_id, file_number)
             WHERE rerun_search_log_id IS NULL
               AND status IN (\'edit_requested\', \'ready_for_rerun\')'
        );
    }

    public function down(): void
    {
        Schema::connection('sqlsrv')->dropIfExists('phs_edit_requests');
    }
};
