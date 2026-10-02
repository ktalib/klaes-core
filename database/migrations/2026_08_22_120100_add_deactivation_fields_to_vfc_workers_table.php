<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'sqlsrv';

    /**
     * Retirement / schedule-change bookkeeping for the VFC worker pool.
     *
     * vfc_workers already carries `is_active` (the project console only offers
     * active workers), but nothing recorded WHY or WHEN an officer left the
     * pool. Deactivating instead of deleting keeps the pool row — and the
     * worker_code trail on their captured valuations — intact.
     */
    public function up(): void
    {
        if (!Schema::connection('sqlsrv')->hasTable('vfc_workers')) {
            return;
        }

        Schema::connection('sqlsrv')->table('vfc_workers', function (Blueprint $table) {
            if (!Schema::connection('sqlsrv')->hasColumn('vfc_workers', 'deactivated_at')) {
                $table->dateTime('deactivated_at')->nullable();
            }
            if (!Schema::connection('sqlsrv')->hasColumn('vfc_workers', 'deactivation_reason')) {
                $table->string('deactivation_reason', 255)->nullable();
            }
        });
    }

    public function down(): void
    {
        if (!Schema::connection('sqlsrv')->hasTable('vfc_workers')) {
            return;
        }

        Schema::connection('sqlsrv')->table('vfc_workers', function (Blueprint $table) {
            $table->dropColumn(['deactivated_at', 'deactivation_reason']);
        });
    }
};
