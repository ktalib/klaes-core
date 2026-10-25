<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Survey Examination/Verification > Register File (user, 2026-10-25; transcript and
 * docs/KLAES-CORE Survey Module.md 5.1).
 *
 * One line per file received by Survey:
 * - from Cadastral, registered automatically from cadastral_case_movements when a case
 *   reaches the Compensation Unit (purpose verification) or the Examination Unit
 *   (purpose examination). The same file arriving twice is two lines;
 *   cadastral_movement_id keeps each movement to one line.
 * - registered in Survey directly (Verification path only, for now).
 *
 * Additive only: a new table.
 */
return new class extends Migration
{
    public function up(): void
    {
        $s = Schema::connection('sqlsrv');
        if ($s->hasTable('survey_register_files')) {
            return;
        }

        $s->create('survey_register_files', function (Blueprint $t) {
            $t->id();
            $t->string('register_ref', 30)->unique();
            $t->string('purpose', 20);                 // verification | examination
            $t->string('source', 20);                  // cadastral | survey
            $t->string('workflow', 20)->nullable();    // the Cadastral case's workflow, or verification
            $t->unsignedBigInteger('cadastral_case_id')->nullable()->index();
            $t->unsignedBigInteger('cadastral_movement_id')->nullable();
            $t->string('cadastral_case_ref', 30)->nullable();
            $t->string('file_type', 20)->nullable();
            $t->string('sub_type', 30)->nullable();
            $t->string('allocation_route', 30)->nullable();
            $t->string('file_source', 20)->nullable();
            $t->string('file_source_other', 100)->nullable();
            $t->string('file_number', 100)->index();
            $t->unsignedBigInteger('file_indexing_id')->nullable();
            $t->string('file_system', 20)->nullable();
            $t->string('tracking_id', 50)->nullable();
            $t->string('file_title', 500)->nullable();
            $t->string('plot_no', 100)->nullable();
            $t->string('district', 255)->nullable();
            $t->string('lga', 255)->nullable();
            $t->string('location', 500)->nullable();
            $t->string('correspondence_fileno', 100)->nullable();
            $t->dateTime('received_at')->nullable();
            $t->string('status', 20)->default('received');
            $t->string('finding', 50)->nullable();
            $t->string('remarks', 2000)->nullable();
            $t->string('notes', 4000)->nullable();
            $t->dateTime('closed_at')->nullable();
            $t->unsignedBigInteger('closed_by')->nullable();
            $t->unsignedBigInteger('created_by')->nullable();
            $t->unsignedBigInteger('updated_by')->nullable();
            $t->timestamps();
            $t->softDeletes();
        });

        // One line per Cadastral movement; Survey's own lines have none.
        DB::connection('sqlsrv')->statement(
            'CREATE UNIQUE INDEX survey_register_files_movement_unique ON survey_register_files (cadastral_movement_id) WHERE cadastral_movement_id IS NOT NULL'
        );
    }

    public function down(): void
    {
        Schema::connection('sqlsrv')->dropIfExists('survey_register_files');
    }
};
