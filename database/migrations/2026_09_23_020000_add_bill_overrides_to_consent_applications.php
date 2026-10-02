<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-line manual overrides on the consent bill.
 *
 * The amount columns always hold the EFFECTIVE figure — what the letter prints.
 * This column records, per overridden line, what the formula would have given,
 * what was charged instead, why, by whom and when. Without it an overridden
 * bill is indistinguishable from a miscalculated one.
 */
return new class extends Migration
{
    private const CONNECTION = 'sqlsrv';

    public function up(): void
    {
        if (Schema::connection(self::CONNECTION)->hasColumn('consent_applications', 'bill_overrides')) {
            return;
        }

        Schema::connection(self::CONNECTION)->table('consent_applications', function (Blueprint $table) {
            $table->text('bill_overrides')->nullable();
        });
    }

    public function down(): void
    {
        if (!Schema::connection(self::CONNECTION)->hasColumn('consent_applications', 'bill_overrides')) {
            return;
        }

        Schema::connection(self::CONNECTION)->table('consent_applications', function (Blueprint $table) {
            $table->dropColumn('bill_overrides');
        });
    }
};
