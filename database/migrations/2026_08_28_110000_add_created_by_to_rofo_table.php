<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * rofo.created_by — the user who generated an ST RofO.
 *
 * The ST RofO listing shows Date Created but had no way to show Created By: the
 * `rofo` table is a legacy table that never carried an author column, and the
 * date on the row came from subapplications.created_at (when the UNIT application
 * was captured), not from the RofO at all. Both halves of "who and when" now come
 * off the RofO itself for generated rows.
 *
 * land_recommendations and sltr_recommendations already have created_by; this is
 * the ST equivalent, and the Master Delete audit trail reads it too.
 *
 * Nullable, no backfill. Every RofO generated before this migration has no author
 * on record and there is nothing honest to put there — the listing prints a dash.
 * Guessing from the unit application's creator would name someone who may never
 * have touched the RofO.
 */
return new class extends Migration
{
    public function up(): void
    {
        $schema = Schema::connection('sqlsrv');

        if (!$schema->hasColumn('rofo', 'created_by')) {
            $schema->table('rofo', function (Blueprint $table) {
                $table->unsignedBigInteger('created_by')->nullable();
            });
        }
    }

    public function down(): void
    {
        $schema = Schema::connection('sqlsrv');

        if ($schema->hasColumn('rofo', 'created_by')) {
            $schema->table('rofo', function (Blueprint $table) {
                $table->dropColumn('created_by');
            });
        }
    }
};
