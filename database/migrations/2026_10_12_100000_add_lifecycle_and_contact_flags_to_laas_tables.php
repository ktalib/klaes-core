<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * LAAS Portal: lineage, transfers and contact flags.
 * See docs/plans/LAAS_PORTAL_REBUILD_PLAN.md.
 *
 * laas_applications
 *  lifecycle_status   'active' | 'retired' | 'transferred_out'. A cache for
 *                     listing filters; decommissioned_files stays the truth.
 *  lifecycle_changed_at
 *  transferred_from_application_id / transferred_to_application_id
 *                     The two rows of one ownership transfer: the old holder's
 *                     row points forward, the new holder's row points back.
 *  visible_until      The old holder sees the timeline and folio up to here.
 *  entity_id          customers_staging.entity_id, for traceability only. It is
 *                     one per file, so it never groups accounts.
 *  holder_source      Where the holder name came from: mls_file_no,
 *                     transfer:{instrument_capture_id}, staff.
 *
 * laas_applicants
 *  contact_status     'ok' | 'pending_update'. Pending accounts carry a
 *                     system-generated email (@portal.klaes.local) and/or phone
 *                     (SYS-########) until a real one is entered.
 *  contact_flagged_at, contact_flag_reason (no_phone, no_email, shared_phone)
 *
 * laas_transfer_candidates
 *  A registered assignment-type instrument naming someone other than the
 *  portal holder. Staff approve or dismiss it; nothing moves automatically.
 */
return new class extends Migration
{
    public function up(): void
    {
        $schema = Schema::connection('sqlsrv');

        $schema->table('laas_applications', function (Blueprint $table) use ($schema) {
            $add = fn (string $column) => !$schema->hasColumn('laas_applications', $column);

            if ($add('lifecycle_status')) {
                $table->string('lifecycle_status', 20)->nullable();
            }
            if ($add('lifecycle_changed_at')) {
                $table->dateTime('lifecycle_changed_at')->nullable();
            }
            if ($add('transferred_from_application_id')) {
                $table->unsignedBigInteger('transferred_from_application_id')->nullable();
            }
            if ($add('transferred_to_application_id')) {
                $table->unsignedBigInteger('transferred_to_application_id')->nullable();
            }
            if ($add('visible_until')) {
                $table->dateTime('visible_until')->nullable();
            }
            if ($add('entity_id')) {
                $table->unsignedBigInteger('entity_id')->nullable();
            }
            if ($add('holder_source')) {
                $table->string('holder_source', 60)->nullable();
            }
        });

        $schema->table('laas_applicants', function (Blueprint $table) use ($schema) {
            $add = fn (string $column) => !$schema->hasColumn('laas_applicants', $column);

            if ($add('contact_status')) {
                $table->string('contact_status', 20)->nullable();
            }
            if ($add('contact_flagged_at')) {
                $table->dateTime('contact_flagged_at')->nullable();
            }
            if ($add('contact_flag_reason')) {
                $table->string('contact_flag_reason', 60)->nullable();
            }
        });

        if (!$schema->hasTable('laas_transfer_candidates')) {
            $schema->create('laas_transfer_candidates', function (Blueprint $table) {
                $table->id();
                $table->string('file_number', 100);
                $table->unsignedBigInteger('laas_application_id')->nullable();
                $table->unsignedBigInteger('instrument_capture_id');
                $table->string('instrument_type', 150)->nullable();
                $table->string('current_holder', 255)->nullable();
                $table->string('transferee_name', 255)->nullable();
                $table->string('transferee_phone', 60)->nullable();
                $table->string('transferee_address', 500)->nullable();
                $table->dateTime('registered_at')->nullable();
                $table->string('status', 20)->default('pending'); // pending | approved | dismissed
                $table->text('decision_note')->nullable();
                $table->unsignedBigInteger('decided_by')->nullable();
                $table->dateTime('decided_at')->nullable();
                $table->unsignedBigInteger('new_application_id')->nullable();
                $table->timestamps();

                $table->unique('instrument_capture_id');
                $table->index('file_number');
                $table->index('status');
            });
        }
    }

    public function down(): void
    {
        $schema = Schema::connection('sqlsrv');

        $schema->dropIfExists('laas_transfer_candidates');

        $schema->table('laas_applicants', function (Blueprint $table) use ($schema) {
            foreach (['contact_status', 'contact_flagged_at', 'contact_flag_reason'] as $column) {
                if ($schema->hasColumn('laas_applicants', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        $schema->table('laas_applications', function (Blueprint $table) use ($schema) {
            foreach ([
                'lifecycle_status', 'lifecycle_changed_at', 'transferred_from_application_id',
                'transferred_to_application_id', 'visible_until', 'entity_id', 'holder_source',
            ] as $column) {
                if ($schema->hasColumn('laas_applications', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
