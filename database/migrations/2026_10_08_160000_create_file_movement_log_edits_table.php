<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Hidden history of edits to File Movement (Department) logs. Editing a log
 * rewrites the entry in file_tracker.movement_log; the entry as it was before
 * (and after) each edit is kept here, so the original is never lost. No screen
 * reads this table.
 *
 * Append-only: a trigger refuses UPDATE and DELETE, so a stored copy cannot be
 * altered or removed through the application.
 */
return new class extends Migration
{
    public function up(): void
    {
        $schema = Schema::connection('sqlsrv');

        if ($schema->hasTable('file_movement_log_edits')) {
            return;
        }

        $schema->create('file_movement_log_edits', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('file_tracker_id')->index();
            $table->string('tracking_id', 100)->nullable();
            $table->string('file_number', 255)->nullable()->index();
            $table->integer('entry_index');
            $table->string('log_id', 100)->nullable();
            $table->string('office_code', 50)->nullable();
            $table->text('changed_fields');
            $table->text('before_entry');
            $table->text('after_entry');
            $table->text('tracker_before')->nullable();
            $table->text('tracker_after')->nullable();
            $table->string('reason', 500);
            $table->unsignedBigInteger('edited_by');
            $table->string('edited_by_name', 255)->nullable();
            $table->string('acting_office', 50)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->timestamp('created_at')->nullable();
        });

        DB::connection('sqlsrv')->statement(<<<'SQL'
CREATE TRIGGER trg_file_movement_log_edits_append_only
ON file_movement_log_edits
INSTEAD OF UPDATE, DELETE
AS
BEGIN
    THROW 50000, 'file_movement_log_edits is append-only.', 1;
END
SQL);
    }

    public function down(): void
    {
        Schema::connection('sqlsrv')->dropIfExists('file_movement_log_edits');
    }
};
