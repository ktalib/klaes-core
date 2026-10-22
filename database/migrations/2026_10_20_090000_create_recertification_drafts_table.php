<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Draft mode for the recertification form: a half-filled application is saved
 * here (per officer) until it is submitted or discarded. Nothing is allocated
 * for a draft; the KN number is still given out only on submit.
 */
return new class extends Migration
{
    public function up(): void
    {
        $schema = Schema::connection('sqlsrv');
        if ($schema->hasTable('recertification_drafts')) {
            return;
        }

        $schema->create('recertification_drafts', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('draft_key', 40)->unique();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('land_file_no', 100)->nullable()->index();
            $table->string('applicant_name', 255)->nullable();
            $table->unsignedSmallInteger('current_step')->default(1);
            $table->longText('payload')->nullable();
            $table->string('status', 20)->default('open')->index();
            $table->unsignedBigInteger('application_id')->nullable();
            $table->dateTime('last_saved_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection('sqlsrv')->dropIfExists('recertification_drafts');
    }
};
