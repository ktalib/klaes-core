<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Earlier change list p.2 + docs/KLAES Survey Module – Workflow_V02.md 3, 5.1, 5.3 (user, 2026-10-29):
 *
 *  - Surveyor's Report (Private) carries the same core records as the GKN report:
 *    List of Coordinates and Computation Field Observations reuse the GKN tables,
 *    anchored to the report (its_source = 'private', survey_change_report_id).
 *  - survey_documents: the Sketch Plan (and later other Survey uploads), a POINTER to a
 *    scannings row filed in the file's EDMS folder under Survey Registry; never a copy.
 *  - survey_op_showings (+ photos): Showing History on Compensation Status → Verification
 *    (Records): each time a surveyor shows the allottee the site, with date, time, images,
 *    and from the 2nd showing a reason (Change of Ownership / Verification).
 *
 * Additive only: new tables and one nullable column on each of two tables.
 */
return new class extends Migration
{
    public function up(): void
    {
        $schema = Schema::connection('sqlsrv');

        foreach (['survey_its_coordinates', 'survey_its_observations'] as $t) {
            if ($schema->hasTable($t) && ! $schema->hasColumn($t, 'survey_change_report_id')) {
                $schema->table($t, fn (Blueprint $b) => $b->unsignedBigInteger('survey_change_report_id')->nullable()->index());
            }
        }

        if (! $schema->hasTable('survey_documents')) {
            $schema->create('survey_documents', function (Blueprint $t) {
                $t->id();
                // Anchor of the record the document was filed for: survey | cadastral | private, and its id.
                $t->string('owner_type', 30);
                $t->unsignedBigInteger('owner_id');
                $t->string('kind', 40);
                $t->unsignedBigInteger('scanning_id')->nullable();
                $t->unsignedBigInteger('file_indexing_id')->nullable();
                $t->string('file_number', 100)->nullable();
                $t->string('original_name', 255)->nullable();
                $t->string('mime', 100)->nullable();
                $t->unsignedBigInteger('size')->nullable();
                $t->unsignedBigInteger('uploaded_by')->nullable();
                $t->boolean('is_superseded')->default(false);
                $t->timestamp('superseded_at')->nullable();
                $t->unsignedBigInteger('superseded_by_id')->nullable();
                $t->timestamps();
                $t->index(['owner_type', 'owner_id', 'kind']);
            });
        }

        if (! $schema->hasTable('survey_op_showings')) {
            $schema->create('survey_op_showings', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('survey_op_record_id')->index();
                $t->integer('showing_no');
                $t->date('shown_on');
                $t->string('shown_at', 5);
                $t->unsignedBigInteger('surveyors_list_id')->nullable();
                $t->string('surveyor_name', 255);
                $t->string('reason', 60)->nullable();
                $t->string('notes', 2000)->nullable();
                $t->unsignedBigInteger('created_by')->nullable();
                $t->unsignedBigInteger('updated_by')->nullable();
                $t->timestamps();
                $t->softDeletes();
            });
        }

        if (! $schema->hasTable('survey_op_showing_photos')) {
            $schema->create('survey_op_showing_photos', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('survey_op_showing_id')->index();
                $t->string('path', 500);
                $t->string('original_name', 255)->nullable();
                $t->string('mime', 100)->nullable();
                $t->unsignedBigInteger('size')->nullable();
                $t->unsignedBigInteger('created_by')->nullable();
                $t->unsignedBigInteger('updated_by')->nullable();
                $t->timestamps();
                $t->softDeletes();
            });
        }
    }

    public function down(): void
    {
        $schema = Schema::connection('sqlsrv');
        foreach (['survey_op_showing_photos', 'survey_op_showings', 'survey_documents'] as $t) {
            $schema->dropIfExists($t);
        }
        foreach (['survey_its_coordinates', 'survey_its_observations'] as $t) {
            if ($schema->hasColumn($t, 'survey_change_report_id')) {
                $schema->table($t, function (Blueprint $b) {
                    $b->dropIndex(['survey_change_report_id']);
                    $b->dropColumn('survey_change_report_id');
                });
            }
        }
    }
};
