<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * DATE OF ISSUE gets a column of its own on the SLTR side too.
 *
 * The same reasoning as 2026_08_24_090000_add_date_issued_to_land_recommendations,
 * and the same trap avoided. This table already carries two dates that look like
 * candidates and are not:
 *
 *   application_date     the applicant's own date, required on the recommendation
 *                        form and listed with it. Issuing a letter has no business
 *                        editing it.
 *   rofo_date_generated  when the RofO was generated in KLAES, which is a fact
 *                        about this system, not about the letter in the
 *                        applicant's hand.
 *
 * date_issued is the date the letter is issued and nothing else: null until
 * someone keys it in on the White Copy. Deliberately NOT backfilled from either
 * column above — a date here means a person chose it, and a proof that shows an
 * invented date proofreads as correct and prints as wrong.
 */
return new class extends Migration
{
    protected $connection = 'sqlsrv';

    public function up(): void
    {
        Schema::connection('sqlsrv')->table('sltr_recommendations', function (Blueprint $table) {
            $table->date('date_issued')->nullable()->after('application_date');
        });
    }

    public function down(): void
    {
        Schema::connection('sqlsrv')->table('sltr_recommendations', function (Blueprint $table) {
            $table->dropColumn('date_issued');
        });
    }
};
