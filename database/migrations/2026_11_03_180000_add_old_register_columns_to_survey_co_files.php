<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The old CO register books (1975/76 ledgers, user 2026-11-03), keyed in as a section of Capture CO File. The book's
 * Job No. is the CO file number, its Description the file / job title and its Remarks the file's remarks; the rest of
 * its columns are kept here, as written: File Ref., Approx. Area, No. of Farms, Date Received, Surveyor, Field Work
 * From / To, Plan Completed, Total Amount, Financial File Reference, Date Despatched, Date Paid.
 */
return new class extends Migration
{
    private const COLUMNS = ['old_file_ref', 'approx_area_ha', 'farms_count', 'date_received', 'surveyors_list_id', 'surveyor_name',
        'fieldwork_from', 'fieldwork_to', 'plan_completed_on', 'total_amount', 'financial_file_ref', 'despatched_on', 'paid_on'];

    public function up(): void
    {
        $schema = Schema::connection('sqlsrv');
        $has = fn ($c) => $schema->hasColumn('survey_co_files', $c);
        $schema->table('survey_co_files', function (Blueprint $t) use ($has) {
            if (! $has('old_file_ref')) $t->string('old_file_ref', 150)->nullable();
            if (! $has('approx_area_ha')) $t->decimal('approx_area_ha', 12, 4)->nullable();
            if (! $has('farms_count')) $t->integer('farms_count')->nullable();
            if (! $has('date_received')) $t->date('date_received')->nullable();
            if (! $has('surveyors_list_id')) $t->unsignedBigInteger('surveyors_list_id')->nullable();
            if (! $has('surveyor_name')) $t->string('surveyor_name', 255)->nullable();
            if (! $has('fieldwork_from')) $t->date('fieldwork_from')->nullable();
            if (! $has('fieldwork_to')) $t->date('fieldwork_to')->nullable();
            if (! $has('plan_completed_on')) $t->date('plan_completed_on')->nullable();
            if (! $has('total_amount')) $t->decimal('total_amount', 14, 2)->nullable();
            if (! $has('financial_file_ref')) $t->string('financial_file_ref', 100)->nullable();
            if (! $has('despatched_on')) $t->date('despatched_on')->nullable();
            if (! $has('paid_on')) $t->date('paid_on')->nullable();
        });
    }

    public function down(): void
    {
        Schema::connection('sqlsrv')->table('survey_co_files', fn (Blueprint $t) => $t->dropColumn(self::COLUMNS));
    }
};
