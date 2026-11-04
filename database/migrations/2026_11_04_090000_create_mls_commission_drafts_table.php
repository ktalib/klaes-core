<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Draft mode for the MLPP Commission New File Number card (MLS FC): a half-filled
 * card is saved here (per officer) until it is generated or discarded. Nothing is
 * allocated for a draft; the serial and tracking ID are read afresh on resume.
 */
return new class extends Migration
{
    public function up(): void
    {
        $schema = Schema::connection('sqlsrv');
        if ($schema->hasTable('mls_commission_drafts')) {
            return;
        }

        $schema->create('mls_commission_drafts', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('draft_key', 40)->unique();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('file_title', 255)->nullable();
            $table->string('file_type', 60)->nullable();
            $table->string('land_use', 30)->nullable();
            $table->string('plot_no', 100)->nullable();
            $table->string('district', 150)->nullable();
            $table->string('lga', 150)->nullable();
            $table->longText('payload')->nullable();
            $table->string('status', 20)->default('open')->index();
            $table->string('committed_file_numbers', 2000)->nullable();
            $table->dateTime('last_saved_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection('sqlsrv')->dropIfExists('mls_commission_drafts');
    }
};
