<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * duplex_parcel_updates.recommendation_printed_at — when the memo was first printed.
 *
 * Approval rests on the recommendation, and the register could only tell that one had
 * been DRAWN (`recommendation_generated_at`). Drawing it and putting it in front of the
 * officer who signs are two different events: a memo generated and never printed has
 * been read by nobody, and the duplex was approvable in that state.
 *
 * Stamped once, on the first print, by printRecommendation(). Re-printing does not move
 * it — the question it answers is "has this been off the screen and onto paper", and the
 * first time is the answer. Nullable, because every duplex printed before this column
 * existed has no record of it; those show as unprinted and are printed again, which
 * costs a sheet of paper rather than an incorrect approval.
 */
return new class extends Migration
{
    public function up(): void
    {
        $schema = Schema::connection('sqlsrv');

        if (!$schema->hasColumn('duplex_parcel_updates', 'recommendation_printed_at')) {
            $schema->table('duplex_parcel_updates', function (Blueprint $table) {
                $table->dateTime('recommendation_printed_at')->nullable();
            });
        }
    }

    public function down(): void
    {
        $schema = Schema::connection('sqlsrv');

        if ($schema->hasColumn('duplex_parcel_updates', 'recommendation_printed_at')) {
            $schema->table('duplex_parcel_updates', function (Blueprint $table) {
                $table->dropColumn('recommendation_printed_at');
            });
        }
    }
};
