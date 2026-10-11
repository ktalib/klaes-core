<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SPAS: the allottee's response to a Notice of Contravention of Land Use.
 *
 * One row per First Serve notice, keyed in from the Ministry's paper form
 * (docs/templates/SPAS_Notice_Response.html, supplied 2026-10-09). The
 * sections mirror the form: A allottee, B property, C chosen response,
 * D undertaking, E official use. A recorded response stops the automatic
 * Second Serve, since that is only owed "if there is no response".
 *
 * New table only; nothing existing is changed.
 */
return new class extends Migration {
    public function up(): void
    {
        if (Schema::connection('sqlsrv')->hasTable('spa_notice_responses')) {
            return;
        }

        Schema::connection('sqlsrv')->create('spa_notice_responses', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('spa_notice_id')->unique();
            $table->unsignedBigInteger('spa_application_id')->nullable()->index();
            $table->string('file_number', 100)->nullable()->index();

            $table->string('reference_no', 100)->nullable();
            $table->date('original_notice_date')->nullable();
            $table->date('response_date')->nullable();

            // A — allottee
            $table->string('allottee_name', 255)->nullable();
            $table->string('allottee_address', 500)->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('email', 150)->nullable();
            $table->string('representative_name', 255)->nullable();

            // B — property
            $table->string('plot_number', 100)->nullable();
            $table->string('location', 500)->nullable();
            $table->string('approved_land_use', 255)->nullable();
            $table->string('current_land_use', 255)->nullable();

            // C — response: formalize | change_of_use | appeal | surrender | other
            $table->string('response_option', 30);
            $table->text('appeal_grounds')->nullable();
            $table->text('other_details')->nullable();

            // D — undertaking
            $table->boolean('declaration_signed')->default(false);
            $table->date('allottee_signed_date')->nullable();

            // E — official use
            $table->string('received_by', 255)->nullable();
            $table->string('receiver_designation', 255)->nullable();
            $table->date('date_received')->nullable();
            $table->string('time_received', 10)->nullable();
            $table->text('action_remarks')->nullable();
            $table->string('director_decision', 20)->nullable(); // noted | referred | closed
            $table->string('referred_to', 255)->nullable();
            $table->date('director_date')->nullable();

            // Attachments checklist (keys), as ticked on the form.
            $table->text('attachments')->nullable();

            $table->string('created_by', 255)->nullable();
            $table->string('updated_by', 255)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection('sqlsrv')->dropIfExists('spa_notice_responses');
    }
};
