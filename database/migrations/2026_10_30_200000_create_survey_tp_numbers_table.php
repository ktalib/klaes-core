<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TP Nos register (user, 2026-10-30): "add the TPNos to the survey module, under Layout
 * Plans, sort by serial no". Staff capture each layout plan's TP number (prefix + serial,
 * e.g. TP/KNUPDA + 338A = TP/KNUPDA/338A); the list is sorted by serial number. It starts
 * empty — it is not filled from the projects' plan numbers.
 */
return new class extends Migration
{
    public function up(): void
    {
        $schema = Schema::connection('sqlsrv');
        if ($schema->hasTable('survey_tp_numbers')) return;

        $schema->create('survey_tp_numbers', function (Blueprint $t) {
            $t->id();
            $t->string('tp_no', 120)->index();            // prefix/serial, as shown
            $t->string('prefix', 80);                       // e.g. TP/KNUPDA
            $t->string('serial_no', 40);                    // e.g. 338A
            $t->integer('serial_sort')->nullable()->index(); // leading number of the serial, for sorting
            $t->string('layout_name', 255);
            $t->string('purpose', 150)->nullable();
            $t->integer('plot_count')->nullable();
            $t->decimal('area_ha', 14, 4)->nullable();
            $t->date('approval_date')->nullable();
            $t->string('remarks', 2000)->nullable();
            $t->string('prop_house', 100)->nullable();
            $t->string('prop_plot', 100)->nullable();
            $t->string('prop_street', 500)->nullable();
            $t->string('prop_street_other', 255)->nullable();
            $t->string('prop_district', 500)->nullable();
            $t->string('prop_district_other', 255)->nullable();
            $t->string('prop_lga', 500)->nullable();
            $t->string('prop_state', 100)->nullable();
            $t->unsignedBigInteger('created_by')->nullable();
            $t->unsignedBigInteger('updated_by')->nullable();
            $t->timestamps();
            $t->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::connection('sqlsrv')->dropIfExists('survey_tp_numbers');
    }
};
