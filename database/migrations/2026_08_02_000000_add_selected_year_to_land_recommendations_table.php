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
        if (!Schema::connection('sqlsrv')->hasColumn('land_recommendations', 'selected_year')) {
            Schema::connection('sqlsrv')->table('land_recommendations', function (Blueprint $table) {
                $table->integer('selected_year')->nullable();
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        if (Schema::connection('sqlsrv')->hasColumn('land_recommendations', 'selected_year')) {
            Schema::connection('sqlsrv')->table('land_recommendations', function (Blueprint $table) {
                $table->dropColumn('selected_year');
            });
        }
    }
};
