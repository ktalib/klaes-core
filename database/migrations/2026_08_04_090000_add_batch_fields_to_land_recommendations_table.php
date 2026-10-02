<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'sqlsrv';

    public function up()
    {
        // Columns are applied directly on live databases, so guard each one to keep
        // the migration re-runnable.
        Schema::connection('sqlsrv')->table('land_recommendations', function (Blueprint $table) {
            // A Plot Subdivision batch saves one recommendation per child of the
            // mother file. All rows in the batch share rofo_batch_id, which is what
            // groups them back together on the RofO table.
            if (!Schema::connection('sqlsrv')->hasColumn('land_recommendations', 'rofo_batch_id')) {
                $table->string('rofo_batch_id', 60)->nullable()->after('old_file_number');
            }
            // The mother file the batch was generated from. Denormalised from
            // old_file_number so the grouping survives if a child row is edited.
            if (!Schema::connection('sqlsrv')->hasColumn('land_recommendations', 'batch_mother_file_no')) {
                $table->string('batch_mother_file_no', 100)->nullable()->after('rofo_batch_id');
            }
            // 1-based position within the batch, so children list in the order the
            // user saw them in the capture table rather than by insert id.
            if (!Schema::connection('sqlsrv')->hasColumn('land_recommendations', 'batch_seq')) {
                $table->integer('batch_seq')->nullable()->after('batch_mother_file_no');
            }
        });

        if (!$this->indexExists('land_recommendations', 'idx_land_recommendations_rofo_batch_id')) {
            Schema::connection('sqlsrv')->table('land_recommendations', function (Blueprint $table) {
                $table->index('rofo_batch_id', 'idx_land_recommendations_rofo_batch_id');
            });
        }
    }

    public function down()
    {
        if ($this->indexExists('land_recommendations', 'idx_land_recommendations_rofo_batch_id')) {
            Schema::connection('sqlsrv')->table('land_recommendations', function (Blueprint $table) {
                $table->dropIndex('idx_land_recommendations_rofo_batch_id');
            });
        }

        foreach (['rofo_batch_id', 'batch_mother_file_no', 'batch_seq'] as $column) {
            if (Schema::connection('sqlsrv')->hasColumn('land_recommendations', $column)) {
                Schema::connection('sqlsrv')->table('land_recommendations', function (Blueprint $table) use ($column) {
                    $table->dropColumn($column);
                });
            }
        }
    }

    private function indexExists(string $table, string $index): bool
    {
        return (bool) \Illuminate\Support\Facades\DB::connection('sqlsrv')
            ->selectOne('SELECT 1 AS found FROM sys.indexes WHERE name = ? AND object_id = OBJECT_ID(?)', [$index, $table]);
    }
};
