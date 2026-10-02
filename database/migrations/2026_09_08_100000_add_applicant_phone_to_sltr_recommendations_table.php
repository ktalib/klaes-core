<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * sltr_recommendations.applicant_phone — the number to reach the applicant on.
 *
 * WHY
 * Every other capture point in the registry records a phone number; the SLTR
 * recommendation form was the one that never did. The applicant's name and
 * address were captured, so a recommendation could be printed and posted but
 * the applicant could not be told it was ready. With the transactional SMS
 * work now leaning on captured numbers, an SLTR recommendation was a dead end.
 *
 * REQUIRED ON THE FORM, NULLABLE IN THE COLUMN
 * The form validates it as required (see SltrRecommendationController), because
 * it is data the officer has in hand at capture time. The column stays NULLable
 * because every row written before this migration has no number and must keep
 * loading — a NOT NULL column would have needed a backfill value, and inventing
 * one would put a fake number in front of the SMS dispatcher.
 *
 * Pinned to sqlsrv: config('database.default') is mysql, so a bare Schema call
 * would look for this table in the wrong database.
 */
return new class extends Migration
{
    public function up(): void
    {
        $schema = Schema::connection('sqlsrv');

        if ($schema->hasTable('sltr_recommendations') && !$schema->hasColumn('sltr_recommendations', 'applicant_phone')) {
            $schema->table('sltr_recommendations', function (Blueprint $table) {
                // 20 rather than 11: the validated form is 11 digits, but this
                // column also receives legacy edits and pasted values, and a
                // truncating column would silently corrupt them.
                $table->string('applicant_phone', 20)->nullable()->after('applicant_address');
            });
        }
    }

    public function down(): void
    {
        $schema = Schema::connection('sqlsrv');

        if ($schema->hasColumn('sltr_recommendations', 'applicant_phone')) {
            $schema->table('sltr_recommendations', function (Blueprint $table) {
                $table->dropColumn('applicant_phone');
            });
        }
    }
};
