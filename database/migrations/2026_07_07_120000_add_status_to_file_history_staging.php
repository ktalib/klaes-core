<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'sqlsrv';

    /**
     * Instrument lifecycle status captured on each property transaction during
     * File Indexing (the Property Transaction Details modal), for ALL instruments.
     * Values: Normal (default), Normal Cancellation, Total Cancellation.
     *
     * CofO_staging and pra already received a `status` column in
     * 2026_06_30_120000_add_status_to_cofo_staging_and_pra_tables. This adds the
     * matching column to file_history_staging so the value survives the
     * column filter in PropertyRecordController::storeFromIndexing and is
     * persisted for File History instruments too.
     */
    private array $tables = [
        'file_history_staging',
    ];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            if (!Schema::connection('sqlsrv')->hasTable($table)) {
                continue;
            }

            if (Schema::connection('sqlsrv')->hasColumn($table, 'status')) {
                continue;
            }

            Schema::connection('sqlsrv')->table($table, function (Blueprint $t) {
                $t->string('status', 50)->default('Normal')->nullable();
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $table) {
            if (!Schema::connection('sqlsrv')->hasTable($table)) {
                continue;
            }

            if (!Schema::connection('sqlsrv')->hasColumn($table, 'status')) {
                continue;
            }

            Schema::connection('sqlsrv')->table($table, function (Blueprint $t) {
                $t->dropColumn('status');
            });
        }
    }
};
