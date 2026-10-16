<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cadastral Department workflow: the Cadastral Verification Report Sheet
 * (client DOCX "Cadastral Verification Report Sheet", 2026-10-15), completed
 * during the Verification workflow. One per case. Section 1 keeps what was
 * on the records when it was saved; the choices hold the sheet's own words.
 *
 * Run with --path only:
 *   php artisan migrate --path=database/migrations/2026_10_15_090000_create_cadastral_dept_verification_reports.php
 *
 * Additive and guarded.
 */
return new class extends Migration
{
    public function up(): void
    {
        $s = Schema::connection('sqlsrv');

        if (! $s->hasTable('cadastral_verification_reports')) {
            $s->create('cadastral_verification_reports', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('cadastral_case_id')->unique();
                $t->string('file_number', 100);

                // 1. General file information (filled from the FMC and the registry)
                $t->string('application_date', 40)->nullable();
                $t->string('allottee_name', 300)->nullable();
                $t->string('application_type', 100)->nullable();
                $t->string('plot_no', 100)->nullable();
                $t->string('layout_no', 150)->nullable();
                $t->string('district_location', 300)->nullable();
                $t->string('fmc_commission_date', 40)->nullable();

                // 2. Transaction history
                $t->string('original_holder_name', 300)->nullable();
                $t->string('original_holder_ref', 150)->nullable();
                $t->string('original_holder_date', 40)->nullable();
                $t->string('current_holder_name', 300)->nullable();
                $t->string('current_holder_ref', 150)->nullable();
                $t->string('current_holder_date', 40)->nullable();
                $t->string('current_transaction_nature', 100)->nullable();

                // 3. Encumbrance check
                $t->string('caveat_status', 20)->nullable();
                $t->string('caveat_ref', 300)->nullable();
                $t->string('mortgage_status', 20)->nullable();
                $t->string('mortgage_ref', 300)->nullable();
                $t->string('release_status', 20)->nullable();
                $t->string('release_ref', 300)->nullable();

                // 4. DCIV investigation check
                $t->string('dciv_status', 20)->nullable();
                $t->string('dciv_officer', 200)->nullable();
                $t->string('dciv_genuineness', 20)->nullable();
                $t->string('dciv_remarks', 2000)->nullable();

                // 5. Title status & parcel update check
                $t->string('title_status', 20)->nullable();
                $t->string('title_ref', 300)->nullable();
                $t->string('parcel_update_status', 20)->nullable();
                $t->string('survey_plan_status', 20)->nullable();
                $t->string('charting_status', 30)->nullable();

                // 6. Conclusion
                $t->string('verdict', 20)->nullable();
                $t->string('remarks', 4000)->nullable();

                $t->unsignedBigInteger('saved_by')->nullable();
                $t->dateTime('completed_at')->nullable();    // verdict given
                $t->unsignedInteger('print_count')->default(0);
                $t->timestamps();
            });
        }
    }

    public function down(): void
    {
        // Left in place on purpose: no backups here, and the rows are records.
    }
};
