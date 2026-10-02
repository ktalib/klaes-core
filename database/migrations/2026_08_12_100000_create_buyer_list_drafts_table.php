<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Autosave store for the Add/Edit Buyers capture (Sectional Titling).
 *
 * A buyers list is keyed a row at a time — title, names, unit, section,
 * measurements — and officers report losing the whole form mid-entry. Nothing on
 * that screen was persisted until the final "Save Buyers", so whatever empties
 * the form (a session timeout, a 419, a stray navigation, a re-initialised Alpine
 * component) took every unsaved row with it. The browser now writes the form here
 * every few seconds so the work outlives the page that was keying it.
 *
 * ONE DRAFT PER FILE. `draft_key` is the application's file number, normalised —
 * so returning to the same file updates the draft that is already there instead
 * of leaving a trail of half-finished copies to choose between. The displayed
 * name ("COM-1991-46 — 12 Aug 2026") is refreshed on each save and is for reading
 * only; nothing keys off it.
 *
 * A draft is deliberately schema-free: `payload` holds the rows exactly as the
 * browser had them, valid or not. Nothing here is authoritative — the real
 * validation still happens in BuyerListController::addBuyers() on submit.
 */
return new class extends Migration
{
    protected $connection = 'sqlsrv';

    public function up()
    {
        if (Schema::connection('sqlsrv')->hasTable('buyer_list_drafts')) {
            return;
        }

        Schema::connection('sqlsrv')->create('buyer_list_drafts', function (Blueprint $table) {
            $table->id();

            // The normalised file number. Unique, because the point of keying on
            // the file is that a second visit updates the first visit's draft.
            $table->string('draft_key', 120)->unique();

            // What the resume banner shows: "COM-1991-46 — 12 Aug 2026".
            $table->string('draft_name', 190)->nullable();

            $table->string('file_no', 120)->nullable();
            $table->unsignedBigInteger('application_id')->nullable()->index();

            // Drafts are shared per file rather than per officer, so who touched
            // it last is worth naming in the resume banner.
            $table->unsignedBigInteger('last_saved_by')->nullable()->index();

            // Every captured row as JSON. text() maps to nvarchar(max) on sqlsrv:
            // a long buyers list runs well past the 4000-character default.
            $table->text('payload')->nullable();

            // Denormalised off the payload so the resume banner can say
            // "7 buyers in progress" without decoding it.
            $table->integer('rows_total')->default(0);
            $table->integer('rows_filled')->default(0);

            // open      — still being keyed, offered for resume
            // submitted — the rows were saved to buyer_list; kept for the trail
            // discarded — the user threw it away
            $table->string('status', 20)->default('open')->index();

            // Separate from updated_at: the moment the BROWSER last had its work
            // accepted, which is what the "Saved 12:04" badge reports.
            $table->dateTime('last_saved_at')->nullable();

            $table->timestamps();

            // The resume lookup: this application's open draft.
            $table->index(['application_id', 'status'], 'idx_buyer_list_drafts_app_status');
        });
    }

    public function down()
    {
        Schema::connection('sqlsrv')->dropIfExists('buyer_list_drafts');
    }
};
