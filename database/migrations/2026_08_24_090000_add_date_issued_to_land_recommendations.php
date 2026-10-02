<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * DATE OF ISSUE gets a column of its own.
 *
 * The RofO letter printed application_date as its DATE OF ISSUE, and the print
 * dialog wrote to it. But application_date is the recommendation's own field —
 * required on the recommendation form, listed and exported with it, and printed on
 * page 2 of the letter as the applicant's acceptance date. Issuing a letter has no
 * business editing it.
 *
 * date_issued is that value and nothing else: null until someone issues the letter
 * and keys the date in at the printer. It is deliberately NOT backfilled from
 * application_date — a date on this column means a person chose it.
 */
return new class extends Migration
{
    protected $connection = 'sqlsrv';

    public function up(): void
    {
        Schema::connection('sqlsrv')->table('land_recommendations', function (Blueprint $table) {
            $table->date('date_issued')->nullable()->after('application_date');
        });
    }

    public function down(): void
    {
        Schema::connection('sqlsrv')->table('land_recommendations', function (Blueprint $table) {
            $table->dropColumn('date_issued');
        });
    }
};
