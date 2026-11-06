<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Loss of Document applications (user, 2026-11-06), raised under Parcel/Title Management
 * (Post CofO), separately for Land FC and OSS. A row is either a regular file, which
 * already has its number, or an OP found by serial number.
 *
 * Kept off oss_applications: the OSS lists are driven from that table and checkDuplicate
 * reads it by file number, so a loss application on a live file would show up there. It
 * held no loss-of-document rows when this table was created.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::connection('sqlsrv')->hasTable('loss_of_document_applications')) {
            return;
        }

        Schema::connection('sqlsrv')->create('loss_of_document_applications', function (Blueprint $table) {
            $table->id();
            $table->string('module', 10)->index();          // land | oss
            $table->string('document_kind', 10);            // regular | op
            $table->string('applicant_name', 255);
            $table->string('file_no', 255)->nullable()->index();
            $table->string('op_serial_number', 100)->nullable();
            $table->string('op_source', 10)->nullable();    // capture | pra, as App\Services\Survey\OpLookup keys them
            $table->unsignedBigInteger('op_id')->nullable();
            $table->string('op_type', 100)->nullable();
            $table->string('nationality', 255)->nullable();
            $table->string('occupation', 255)->nullable();
            $table->string('phone', 50)->nullable();
            $table->string('email', 255)->nullable();
            $table->string('residential_address', 2000)->nullable();
            $table->string('correspondence_address', 2000)->nullable();
            $table->string('land_use', 255)->nullable();
            $table->string('plot_no', 100)->nullable();
            $table->string('plan_no', 100)->nullable();
            $table->string('district', 255)->nullable();
            $table->string('lga', 255)->nullable();
            $table->string('location', 2000)->nullable();
            $table->text('remarks')->nullable();
            $table->string('passport_photo', 500)->nullable();
            $table->string('status', 20)->default('pending');
            $table->unsignedBigInteger('captured_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection('sqlsrv')->dropIfExists('loss_of_document_applications');
    }
};
