<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Files attached to a LAAS application — both directions.
 *
 * `source` separates what the applicant uploaded (ID card, passport, sketch)
 * from what the office produced for them to collect (recommendation, RoFO),
 * because only the latter may be downloaded from the status page.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::connection('sqlsrv')->hasTable('laas_documents')) {
            return;
        }

        Schema::connection('sqlsrv')->create('laas_documents', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('laas_application_id');
            $table->string('source', 20)->default('applicant');  // applicant | office
            $table->string('doc_type', 60)->nullable();          // id_card | passport | sketch | recommendation | rofo
            $table->string('original_name', 255)->nullable();
            $table->string('path', 500);
            $table->string('mime', 100)->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->unsignedBigInteger('uploaded_by')->nullable();
            $table->dateTime('uploaded_at')->nullable();
            $table->timestamps();

            $table->index('laas_application_id');
            $table->index('doc_type');
        });
    }

    public function down(): void
    {
        Schema::connection('sqlsrv')->dropIfExists('laas_documents');
    }
};
