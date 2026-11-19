<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 | Survey plan details (user, 2026-11-18): the TP No. (the layout's TP number, the Application Plan's "Plan No."),
 | on Manual Upload and Digital Plotting. Optional; from the TP list or Other. Additive only.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::connection('sqlsrv')->hasColumn('survey_plan_uploads', 'tp_no')) {
            Schema::connection('sqlsrv')->table('survey_plan_uploads', function (Blueprint $t) {
                $t->string('tp_no', 120)->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::connection('sqlsrv')->hasColumn('survey_plan_uploads', 'tp_no')) {
            Schema::connection('sqlsrv')->table('survey_plan_uploads', fn (Blueprint $t) => $t->dropColumn('tp_no'));
        }
    }
};
