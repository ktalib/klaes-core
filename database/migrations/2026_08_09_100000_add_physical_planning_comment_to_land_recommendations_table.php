<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Physical Planning's comment sits alongside the Physical Planning page no.
     * (page_2) on the recommendation form. TEXT rather than a varchar: it is a
     * free-form remark, and a 255-char cap would truncate silently on SQL Server.
     */
    public function up()
    {
        Schema::connection('sqlsrv')->table('land_recommendations', function (Blueprint $table) {
            $table->text('physical_planning_comment')->nullable()->after('survey_report');
        });
    }

    public function down()
    {
        Schema::connection('sqlsrv')->table('land_recommendations', function (Blueprint $table) {
            $table->dropColumn('physical_planning_comment');
        });
    }
};
