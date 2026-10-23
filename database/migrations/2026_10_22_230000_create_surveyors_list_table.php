<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Survey Department staff list, loaded from Surveyor.xlsx (user, 2026-10-22).
 *
 * Ministry staff, not the SURCON register (cadastral_surveyors). Additive only.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::connection('sqlsrv')->hasTable('surveyors_list')) {
            return;
        }

        Schema::connection('sqlsrv')->create('surveyors_list', function (Blueprint $t) {
            $t->id();
            $t->integer('sn')->nullable();
            $t->string('file_no', 100)->nullable();
            $t->string('staff_id', 50)->nullable();
            $t->string('first_name', 150)->nullable();
            $t->string('surname', 150)->nullable();
            $t->string('other_name', 150)->nullable();
            $t->string('rank', 150)->nullable();
            $t->string('unit', 100)->nullable();
            $t->string('phone', 50)->nullable();
            $t->string('email', 255)->nullable();
            $t->string('gender', 20)->nullable();
            $t->date('date_of_birth')->nullable();
            $t->date('date_of_first_appointment')->nullable();
            $t->integer('grade_level')->nullable();
            $t->string('recommendation', 100)->nullable();
            $t->string('source_sheet', 50)->nullable();
            $t->boolean('is_active')->default(true);
            $t->timestamps();
            $t->index('staff_id');
            $t->index('surname');
        });
    }

    public function down(): void
    {
        Schema::connection('sqlsrv')->dropIfExists('surveyors_list');
    }
};
