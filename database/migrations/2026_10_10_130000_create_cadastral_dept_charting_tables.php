<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cadastral Department workflow, step 8: the charting card
 * (docs/plans/CADASTRAL_CHARTING_CARD_DRAFT.md, built as drafted on 2026-10-10).
 *
 * Run with --path only:
 *   php artisan migrate --path=database/migrations/2026_10_10_130000_create_cadastral_dept_charting_tables.php
 *
 * Additive and guarded. One card per charting stage of a case; a correction is a
 * new version that keeps the old one (is_current = 0), as the old charting
 * screen does. Beacons are the optional coordinate rows of a card.
 */
return new class extends Migration
{
    public function up(): void
    {
        $s = Schema::connection('sqlsrv');

        if (! $s->hasTable('cadastral_chartings')) {
            $s->create('cadastral_chartings', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('cadastral_case_id');
                $t->string('charting_no', 30);
                $t->string('stage', 10);                    // single | first | second | check
                $t->string('step', 50);                     // workflow step it was made at
                $t->unsignedInteger('version')->default(1);
                $t->unsignedBigInteger('supersedes_id')->nullable();
                $t->boolean('is_current')->default(true);

                $t->boolean('charted')->nullable();
                $t->string('not_charted_reason', 255)->nullable();
                $t->boolean('falls_on_existing')->nullable();
                $t->string('existing_files', 500)->nullable();   // file numbers, comma-separated, from the selector
                $t->string('overlap_extent', 10)->nullable();    // fully | partly
                $t->boolean('agrees_with_first')->nullable();    // Direct 2nd charting
                $t->string('agree_note', 1000)->nullable();
                $t->boolean('matches_record')->nullable();       // Verification check
                $t->string('match_note', 1000)->nullable();
                $t->text('description')->nullable();

                $t->string('sheet_type', 10)->nullable();        // metric | imperial
                $t->string('sheet_no', 50)->nullable();
                $t->string('layout', 150)->nullable();
                $t->string('plan_no', 100)->nullable();
                $t->decimal('area_sqm', 14, 3)->nullable();
                $t->text('remarks')->nullable();

                $t->unsignedBigInteger('charted_by')->nullable();
                $t->dateTime('charted_at');
                $t->unsignedInteger('print_count')->default(0);
                $t->timestamps();

                $t->index(['cadastral_case_id', 'stage'], 'cad_chg_case_stage_idx');
                $t->index('charting_no', 'cad_chg_no_idx');
            });
        }

        if (! $s->hasTable('cadastral_charting_beacons')) {
            $s->create('cadastral_charting_beacons', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('cadastral_charting_id');
                $t->unsignedInteger('sort_order');
                $t->string('beacon', 30)->nullable();
                $t->decimal('easting', 14, 3)->nullable();
                $t->decimal('northing', 14, 3)->nullable();
                $t->string('remarks', 255)->nullable();
                $t->timestamps();

                $t->index('cadastral_charting_id', 'cad_chg_beacon_chg_idx');
            });
        }
    }

    public function down(): void
    {
        // Intentionally empty: no backups here; charting history must not be dropped.
    }
};
