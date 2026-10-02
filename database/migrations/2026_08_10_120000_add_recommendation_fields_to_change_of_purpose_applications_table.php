<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fields the Change of Purpose recommendation memo needs but the application
 * capture form never asked for: the folio page references, the site-plan
 * measurements and the term of the existing grant. They are entered on the
 * "Generate Recommendation" card and printed by
 * resources/views/change_of_purpose/print/recommendation.blade.php.
 */
return new class extends Migration
{
    protected $connection = 'sqlsrv';

    private const COLUMNS = [
        'rec_page_application',
        'rec_page_planning',
        'rec_page_site_plan',
        'rec_title_alias',
        'rec_measurement_a',
        'rec_measurement_b',
        'rec_term_years',
        'rec_commencement_date',
        'rec_residual_years',
    ];

    public function up(): void
    {
        Schema::connection('sqlsrv')->table('change_of_purpose_applications', function (Blueprint $table) {
            $has = fn (string $c) => Schema::connection('sqlsrv')
                ->hasColumn('change_of_purpose_applications', $c);

            if (!$has('rec_page_application')) $table->string('rec_page_application', 50)->nullable();
            if (!$has('rec_page_planning'))    $table->string('rec_page_planning', 50)->nullable();
            if (!$has('rec_page_site_plan'))   $table->string('rec_page_site_plan', 50)->nullable();
            // KANGIS / alias number printed in brackets after the title number.
            if (!$has('rec_title_alias'))      $table->string('rec_title_alias', 100)->nullable();
            // Free text — a run of dimensions ending in a hectarage, per part.
            if (!$has('rec_measurement_a'))    $table->text('rec_measurement_a')->nullable();
            if (!$has('rec_measurement_b'))    $table->text('rec_measurement_b')->nullable();
            if (!$has('rec_term_years'))       $table->integer('rec_term_years')->nullable();
            if (!$has('rec_commencement_date')) $table->date('rec_commencement_date')->nullable();
            if (!$has('rec_residual_years'))   $table->integer('rec_residual_years')->nullable();
        });
    }

    public function down(): void
    {
        Schema::connection('sqlsrv')->table('change_of_purpose_applications', function (Blueprint $table) {
            $table->dropColumn(self::COLUMNS);
        });
    }
};
