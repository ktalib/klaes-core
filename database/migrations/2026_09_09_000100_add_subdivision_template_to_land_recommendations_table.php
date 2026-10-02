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
        if (Schema::connection('sqlsrv')->hasColumn('land_recommendations', 'use_subdivision_template')) {
            return;
        }

        Schema::connection('sqlsrv')->table('land_recommendations', function (Blueprint $table) {
            // A child of a subdivision that was never captured through the batch
            // flow, because its mother file is not indexed and so cannot be picked
            // from anywhere. The record joins no batch — this flag alone is what
            // makes it print as a subdivision child.
            //
            // The mother file number itself needs no column of its own: it is the
            // record's `old_file_number`, exactly as it is for a child saved through
            // a real subdivision batch (storeBatch() writes the mother into both
            // old_file_number and batch_mother_file_no). The only difference here is
            // that it is typed by hand instead of picked.
            $table->boolean('use_subdivision_template')->default(false)->after('use_standard_template');
        });
    }

    public function down()
    {
        if (!Schema::connection('sqlsrv')->hasColumn('land_recommendations', 'use_subdivision_template')) {
            return;
        }

        Schema::connection('sqlsrv')->table('land_recommendations', function (Blueprint $table) {
            $table->dropColumn('use_subdivision_template');
        });
    }
};
