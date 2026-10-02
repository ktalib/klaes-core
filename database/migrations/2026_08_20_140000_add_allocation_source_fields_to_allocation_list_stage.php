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
        Schema::connection('sqlsrv')->table('allocation_list_stage', function (Blueprint $table) {
            if (!Schema::connection('sqlsrv')->hasColumn('allocation_list_stage', 'institution_category')) {
                // GOVERNMENT | OTHER — which of the two lookup lists this row's institution/addressed_to came from.
                $table->string('institution_category', 20)->nullable()->after('allocation_year');
            }
            if (!Schema::connection('sqlsrv')->hasColumn('allocation_list_stage', 'institution_name')) {
                $table->string('institution_name', 255)->nullable()->after('institution_category');
            }
            if (!Schema::connection('sqlsrv')->hasColumn('allocation_list_stage', 'addressed_to')) {
                $table->string('addressed_to', 255)->nullable()->after('institution_name');
            }
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::connection('sqlsrv')->table('allocation_list_stage', function (Blueprint $table) {
            foreach (['institution_category', 'institution_name', 'addressed_to'] as $column) {
                if (Schema::connection('sqlsrv')->hasColumn('allocation_list_stage', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
