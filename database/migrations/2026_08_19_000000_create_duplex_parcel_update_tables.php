<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Duplex Parcel Update — several parcel updates carried as ONE instruction.
 *
 * The five single workflows (Change of Purpose, Subdivision, Merger, Extension,
 * Separation) each commission real file numbers at the end of their own cycle, so
 * chaining them means three captures, three approvals, three memos. A duplex holds
 * every stage on a temporary HOLDING number, takes one approval and one memo, and
 * commissions the lot in a single pass at the end.
 *
 * Nothing here touches the live registry: holding numbers live only in
 * duplex_parcel_update_files and are decommissioned at commit. The real file
 * numbers are still minted by MlsFileNoController (generateBatch /
 * generateMlsFileNumber) so the lineage, PRA and decommissioning rules stay in
 * exactly one place.
 *
 * A duplex carries TWO or more updates — that is what makes it a duplex. A single
 * update belongs on its own page (Plot Subdivision / Merger / Extension /
 * Separation). The rule is enforced at capture, in the wizard and in store(); the
 * schema stays permissive because duplexes captured before it may hold one stage.
 */
return new class extends Migration
{
    public function up(): void
    {
        $schema = Schema::connection('sqlsrv');

        if (!$schema->hasTable('duplex_parcel_updates')) {
            $schema->create('duplex_parcel_updates', function (Blueprint $table) {
                $table->id();

                // Human reference threaded through every stage, holding number and
                // resulting file: DPX-2026-0007.
                $table->string('duplex_id', 40)->unique();

                $table->string('applicant_name', 255)->nullable();
                $table->string('file_title', 500)->nullable();

                // The real registry files the duplex starts from (JSON array).
                $table->text('source_file_nos')->nullable();

                // The canonical ordered plan: [{type, rank, count}] in TICK order.
                // Rank here is the execution order — never re-derive it from a
                // hard-coded type list.
                $table->text('stages')->nullable();

                // draft | captured | pending | approved | in_land | committed | rejected
                $table->string('status', 30)->default('draft');

                $table->string('land_use', 50)->nullable();
                $table->string('plot_no', 100)->nullable();
                $table->string('house_no', 100)->nullable();
                $table->string('street_name', 255)->nullable();
                $table->string('district', 255)->nullable();
                $table->string('lga', 255)->nullable();
                $table->string('state', 100)->nullable();
                $table->string('phone', 50)->nullable();
                $table->string('address', 500)->nullable();

                $table->decimal('land_value', 18, 2)->nullable();
                $table->decimal('knupda_fee', 18, 2)->nullable();
                $table->string('knupda_status', 50)->nullable();
                $table->text('knupda_remarks')->nullable();

                $table->text('remarks')->nullable();

                $table->dateTime('application_generated_at')->nullable();
                $table->dateTime('recommendation_generated_at')->nullable();
                $table->dateTime('conveyance_generated_at')->nullable();
                $table->dateTime('sent_to_land_at')->nullable();
                $table->dateTime('committed_at')->nullable();

                $table->unsignedBigInteger('captured_by')->nullable();
                $table->unsignedBigInteger('updated_by')->nullable();
                $table->unsignedBigInteger('approved_by')->nullable();
                $table->unsignedBigInteger('committed_by')->nullable();

                $table->tinyInteger('is_deleted')->default(0);
                $table->unsignedBigInteger('deleted_by')->nullable();
                $table->dateTime('deleted_at')->nullable();

                $table->timestamps();

                $table->index('status');
                $table->index('created_at');
            });
        }

        if (!$schema->hasTable('duplex_parcel_update_stages')) {
            $schema->create('duplex_parcel_update_stages', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('duplex_parcel_update_id');
                $table->string('duplex_id', 40)->nullable();

                // subdivision | merger | extension | separation | change_of_purpose
                $table->string('type', 40);

                // Execution order, assigned in tick order. The same type may appear
                // more than once, so (duplex, type) is NOT unique — (duplex, rank) is.
                $table->integer('rank');

                // pending | done | rejected
                $table->string('status', 20)->default('pending');

                // The holding number this stage consumes. Null on the first stage,
                // which consumes the real source file(s) instead.
                $table->string('input_holding_no', 60)->nullable();

                $table->integer('plot_count')->nullable();

                // Everything the stage collected: plot sizes, per-plot holders, the
                // new land use, which child holdings a CoP applies to.
                $table->text('payload')->nullable();

                $table->string('tracking_id', 100)->nullable();
                $table->text('reject_reason')->nullable();
                $table->dateTime('completed_at')->nullable();

                $table->unsignedBigInteger('captured_by')->nullable();
                $table->unsignedBigInteger('updated_by')->nullable();
                $table->timestamps();

                $table->index('duplex_parcel_update_id');
                $table->unique(['duplex_parcel_update_id', 'rank'], 'UX_duplex_stage_rank');
            });
        }

        if (!$schema->hasTable('duplex_parcel_update_files')) {
            $schema->create('duplex_parcel_update_files', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('duplex_parcel_update_id');
                $table->unsignedBigInteger('duplex_parcel_update_stage_id')->nullable();
                $table->string('duplex_id', 40)->nullable();

                // source  = a real registry file the duplex consumes
                // holding = an internal DPX-...-Hnn number, never a registry file
                // result  = the real file number minted at commit
                $table->string('role', 20);

                $table->string('holding_no', 60)->nullable();
                $table->string('source_file_no', 100)->nullable();
                $table->string('final_file_no', 100)->nullable();
                $table->string('file_title', 500)->nullable();

                $table->decimal('plot_size', 18, 4)->nullable();
                $table->string('holder_name', 255)->nullable();

                $table->string('prop_id', 50)->nullable();
                $table->string('parent_prop_id', 255)->nullable();

                // Sources and spent holding files are retired at commit.
                $table->tinyInteger('will_decommission')->default(0);
                $table->tinyInteger('decommissioned')->default(0);

                $table->integer('sequence')->default(0);
                $table->timestamps();

                $table->index('duplex_parcel_update_id');
                $table->index('duplex_parcel_update_stage_id');
                $table->index('holding_no');
            });
        }
    }

    public function down(): void
    {
        $schema = Schema::connection('sqlsrv');
        $schema->dropIfExists('duplex_parcel_update_files');
        $schema->dropIfExists('duplex_parcel_update_stages');
        $schema->dropIfExists('duplex_parcel_updates');
    }
};
