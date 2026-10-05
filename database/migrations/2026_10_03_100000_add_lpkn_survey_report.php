<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('sqlsrv')->table('survey_lpkn', function (Blueprint $table) {
            $table->string('report_surveyor', 255)->nullable();
            $table->date('report_date')->nullable();
            $table->text('surveyor_report')->nullable();
        });
    }

    public function down(): void
    {
        Schema::connection('sqlsrv')->table('survey_lpkn', function (Blueprint $table) {
            $table->dropColumn(['report_surveyor', 'report_date', 'surveyor_report']);
        });
    }
};
