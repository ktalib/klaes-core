<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 | Page 2's return slip on the form (CR 10112026 call, user 2026-11-11): the field surveyor's Place (District, LGA
 | dropdowns) and Date, and a tick box each for the five Field Records submitted in the field jacket. Rank is the
 | existing surveyor_rank, now shown and editable on the form.
 */
return new class extends Migration
{
    private const RECORDS = ['rec_report', 'rec_survey_diagram', 'rec_field_books', 'rec_computation_sheets', 'rec_surveyors_plan'];

    public function up(): void
    {
        $s = Schema::connection('sqlsrv');
        $s->table('survey_its', function (Blueprint $t) use ($s) {
            if (! $s->hasColumn('survey_its', 'return_district')) $t->string('return_district', 255)->nullable();
            if (! $s->hasColumn('survey_its', 'return_lga')) $t->string('return_lga', 255)->nullable();
            if (! $s->hasColumn('survey_its', 'return_date')) $t->date('return_date')->nullable();
            foreach (self::RECORDS as $c) {
                if (! $s->hasColumn('survey_its', $c)) $t->boolean($c)->default(false);
            }
        });
    }

    public function down(): void
    {
        $s = Schema::connection('sqlsrv');
        foreach (array_merge(['return_district', 'return_lga', 'return_date'], self::RECORDS) as $c) {
            if ($s->hasColumn('survey_its', $c)) $s->table('survey_its', fn (Blueprint $t) => $t->dropColumn($c));
        }
    }
};
