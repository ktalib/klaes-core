<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Computation Files (Field Jackets), CR 30102026 items 12-14 (user, 2026-10-31):
//   - Government: Survey commissions its own, numbered LPKN-<year>-<serial> (survey_computation_files).
//   - Private: Cadastral's Computation Files keep FJ-<year>-<5 digits> as a system code and now also carry
//     their real number LPPL-<year>-<serial>, from 1 (cadastral_field_jackets.lppl_no).
//   - survey_computation_serials: one counter per prefix and year, settable under Configurable Entities >
//     Serial Initialization so the LPKN register continues from Survey's last serial.
// Additive only.
return new class extends Migration
{
    public function up(): void
    {
        $s = Schema::connection('sqlsrv');

        $s->create('survey_computation_serials', function (Blueprint $t) {
            $t->id();
            $t->string('prefix', 10);
            $t->unsignedSmallInteger('year');
            $t->unsignedInteger('last_serial')->default(0);
            $t->boolean('is_initialized')->default(false);
            $t->dateTime('initialized_at')->nullable();
            $t->unsignedBigInteger('initialized_by')->nullable();
            $t->boolean('is_locked')->default(false);
            $t->timestamps();
            $t->unique(['prefix', 'year']);
        });

        $s->create('survey_computation_files', function (Blueprint $t) {
            $t->id();
            $t->string('computation_no', 30)->unique();
            $t->unsignedSmallInteger('year');
            $t->unsignedInteger('serial');
            $t->string('file_number', 100)->nullable();
            $t->string('file_title', 500)->nullable();
            $t->string('plot_no', 100)->nullable();
            $t->string('district', 150)->nullable();
            $t->string('lga', 150)->nullable();
            $t->string('location', 500)->nullable();
            $t->unsignedBigInteger('surveyors_list_id')->nullable();
            $t->string('surveyor_name', 255)->nullable();
            $t->unsignedBigInteger('survey_its_id')->nullable();
            $t->string('remarks', 1000)->nullable();
            $t->unsignedBigInteger('commissioned_by')->nullable();
            $t->dateTime('commissioned_at')->nullable();
            $t->unsignedBigInteger('updated_by')->nullable();
            $t->timestamps();
            $t->softDeletes();
        });

        $s->table('cadastral_field_jackets', function (Blueprint $t) {
            $t->string('lppl_no', 30)->nullable();
        });
    }

    public function down(): void
    {
        $s = Schema::connection('sqlsrv');
        $s->table('cadastral_field_jackets', fn (Blueprint $t) => $t->dropColumn('lppl_no'));
        $s->dropIfExists('survey_computation_files');
        $s->dropIfExists('survey_computation_serials');
    }
};
