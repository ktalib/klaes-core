<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Registration fee became a percentage of the valuation rather than a flat
 * amount, so it needs its own rate column beside the amount — without it a
 * historical bill cannot be explained once the configured rate moves.
 *
 * The cgt_* and assignment_fee columns are deliberately left in place: the
 * lines they held are no longer billed, but bills already raised under them
 * must still reprint with the figures they were issued under.
 */
return new class extends Migration
{
    private const CONNECTION = 'sqlsrv';

    public function up(): void
    {
        if (Schema::connection(self::CONNECTION)->hasColumn('consent_applications', 'registration_rate')) {
            return;
        }

        Schema::connection(self::CONNECTION)->table('consent_applications', function (Blueprint $table) {
            $table->decimal('registration_rate', 8, 4)->nullable();
        });
    }

    public function down(): void
    {
        if (!Schema::connection(self::CONNECTION)->hasColumn('consent_applications', 'registration_rate')) {
            return;
        }

        Schema::connection(self::CONNECTION)->table('consent_applications', function (Blueprint $table) {
            $table->dropColumn('registration_rate');
        });
    }
};
