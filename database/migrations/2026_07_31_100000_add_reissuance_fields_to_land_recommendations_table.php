<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'sqlsrv';

    public function up()
    {
        $schema = Schema::connection('sqlsrv');

        // RofO re-issuance: a re-issued letter is captured as its own
        // recommendation row (it deliberately repeats an existing file number),
        // so it needs a flag to tell it apart from the original.
        //   reissuance_source: 'klaes'  — original was generated in KLAES
        //                      'legacy' — original pre-dates KLAES
        //   reissued_from_id:  source recommendation, when it exists in KLAES
        if (!$schema->hasColumn('land_recommendations', 'is_reissuance')) {
            $schema->table('land_recommendations', function (Blueprint $table) {
                $table->boolean('is_reissuance')->default(false);
            });
        }

        if (!$schema->hasColumn('land_recommendations', 'reissuance_source')) {
            $schema->table('land_recommendations', function (Blueprint $table) {
                $table->string('reissuance_source', 20)->nullable();
            });
        }

        if (!$schema->hasColumn('land_recommendations', 'reissued_from_id')) {
            $schema->table('land_recommendations', function (Blueprint $table) {
                $table->integer('reissued_from_id')->nullable();
            });
        }
    }

    public function down()
    {
        $schema = Schema::connection('sqlsrv');

        foreach (['reissued_from_id', 'reissuance_source', 'is_reissuance'] as $column) {
            if ($schema->hasColumn('land_recommendations', $column)) {
                $schema->table('land_recommendations', function (Blueprint $table) use ($column) {
                    $table->dropColumn($column);
                });
            }
        }
    }
};
