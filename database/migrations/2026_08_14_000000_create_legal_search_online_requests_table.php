<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Online Legal Search requests awaiting Director / Deputy Director approval.
 *
 * The public portal no longer releases a report the moment a payment clears.
 * Payment now opens a *request*, which a Director or Deputy Director reviews;
 * on approval the report is emailed to the requester as a PDF.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::connection('sqlsrv')->hasTable('legal_search_online_requests')) {
            return;
        }

        Schema::connection('sqlsrv')->create('legal_search_online_requests', function (Blueprint $table) {
            $table->id();
            $table->string('request_no', 30)->nullable()->unique();

            // Link back to the guest payment that opened this request.
            $table->unsignedBigInteger('payment_id')->nullable();
            $table->string('reference', 60)->nullable();
            $table->string('tracking_id', 30)->nullable();

            // Requester (guest — no portal account).
            $table->string('requester_email', 255);
            $table->string('requester_name', 150)->nullable();
            $table->string('requester_phone', 30)->nullable();

            // What was asked for.
            $table->string('file_number', 100)->nullable();
            $table->json('search_params')->nullable();
            $table->string('ip_address', 45)->nullable();

            // Review state: pending | approved | rejected.
            $table->string('status', 20)->default('pending');
            $table->dateTime('submitted_at')->nullable();
            $table->unsignedBigInteger('reviewed_by')->nullable();
            $table->string('reviewer_name', 150)->nullable();
            $table->string('reviewer_rank', 100)->nullable();
            $table->dateTime('reviewed_at')->nullable();
            $table->text('review_note')->nullable();
            $table->text('rejection_reason')->nullable();

            // Delivery outcome.
            $table->dateTime('emailed_at')->nullable();
            $table->text('email_error')->nullable();

            $table->timestamps();

            $table->index('status');
            $table->index('payment_id');
            $table->index('file_number');
            $table->index('requester_email');
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::connection('sqlsrv')->dropIfExists('legal_search_online_requests');
    }
};
