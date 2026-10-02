<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::connection('sqlsrv')->create('phs_email_histories', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('phs_institution_id')->nullable();
            $table->unsignedBigInteger('phs_onboarding_request_id')->nullable();
            $table->string('recipient_email', 255);
            $table->string('subject', 500)->nullable();
            $table->longText('body_html')->nullable();
            $table->longText('body_text')->nullable();
            $table->string('message_id', 255)->nullable();
            $table->string('mailable', 255)->nullable();
            $table->string('mailer', 150)->nullable();
            $table->longText('meta')->nullable();
            $table->dateTime('sent_at')->nullable();
            $table->timestamps();

            $table->index('phs_institution_id');
            $table->index('phs_onboarding_request_id');
            $table->index('recipient_email');
            $table->index('sent_at');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::connection('sqlsrv')->dropIfExists('phs_email_histories');
    }
};