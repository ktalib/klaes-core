<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Applicant identification + ID name verification for the public Online Legal
 * Search portal.
 *
 * Why its own table rather than columns on legal_search_online_payments: the
 * applicant is identified and verified BEFORE any payment is attempted, so at
 * write time neither a payment nor a request row exists. This row is created
 * first and linked forward (payment_id / request_id) once the payment clears,
 * which is also what keeps a single applicant from being recorded twice.
 *
 * SCOPE: id_verification_status records whether the typed name matched the text
 * read off the document. It is NOT a statement about the document's authenticity
 * or about who uploaded it. See config/id_verification.php.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::connection('sqlsrv')->hasTable('legal_search_online_verifications')) {
            return;
        }

        Schema::connection('sqlsrv')->create('legal_search_online_verifications', function (Blueprint $table) {
            $table->id();

            // Which search this identification was submitted for.
            $table->string('file_number', 100)->nullable();
            $table->string('requester_email', 255)->nullable();

            // Bound to the browser session that submitted it, so one applicant
            // cannot present another applicant's verification at payment time.
            $table->string('session_token', 64)->nullable()->unique();

            // Applicant identification, as typed.
            $table->string('applicant_full_name', 200);
            $table->string('applicant_phone', 30);
            $table->string('applicant_address', 500);

            $table->string('identification_type', 50);
            $table->string('identification_type_other', 120)->nullable();

            // Paths on the private disk. Never rendered into HTML or handed to JS.
            $table->string('id_front_path', 255)->nullable();
            $table->string('id_back_path', 255)->nullable();

            // Raw OCR text is only kept when config('id_verification.store_raw_text')
            // is on — it is a second copy of the document's personal data.
            $table->text('id_ocr_text')->nullable();

            $table->decimal('id_name_match_score', 5, 2)->default(0);

            // pending | verified | review | failed. Constrained below.
            $table->string('id_verification_status', 20)->default('pending');
            $table->dateTime('id_verified_at')->nullable();

            // Set once the applicant pays; until then this row is unattached.
            $table->unsignedBigInteger('payment_id')->nullable();
            $table->unsignedBigInteger('request_id')->nullable();

            $table->string('ip_address', 45)->nullable();
            $table->timestamps();

            $table->index('file_number');
            $table->index('requester_email');
            $table->index('id_verification_status');
            $table->index('payment_id');
        });

        // Enforced in the database as well as the model: a status the application
        // does not recognise must never reach this column.
        DB::connection('sqlsrv')->statement("
            ALTER TABLE legal_search_online_verifications
            ADD CONSTRAINT chk_lsov_status
            CHECK (id_verification_status IN ('pending','verified','review','failed'))
        ");
    }

    public function down(): void
    {
        Schema::connection('sqlsrv')->dropIfExists('legal_search_online_verifications');
    }
};
