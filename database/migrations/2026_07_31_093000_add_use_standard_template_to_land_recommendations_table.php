<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'sqlsrv';

    public function up()
    {
        // The column is applied directly on live databases, so guard the migration
        // to keep it re-runnable.
        if (Schema::connection('sqlsrv')->hasColumn('land_recommendations', 'use_standard_template')) {
            return;
        }

        Schema::connection('sqlsrv')->table('land_recommendations', function (Blueprint $table) {
            // When set, the record keeps its application_type (extra fields / old file
            // number) but prints the standard Direct / Conversion template.
            $table->boolean('use_standard_template')->default(false)->after('application_type');
        });
    }

    public function down()
    {
        if (!Schema::connection('sqlsrv')->hasColumn('land_recommendations', 'use_standard_template')) {
            return;
        }

        Schema::connection('sqlsrv')->table('land_recommendations', function (Blueprint $table) {
            $table->dropColumn('use_standard_template');
        });
    }
};
