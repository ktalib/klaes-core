<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'sqlsrv';

    /**
     * Soft-delete flag for VFC projects.
     *
     * A project owns valuation_compensations rows, vfc_sub_projects and
     * vfc_project_workers assignments, so a hard DELETE would orphan every
     * valuation captured against it. Valuations already use the same
     * `is_deleted` flag (ValuationCompensation::scopeActive), so the project
     * follows that convention: the row stays, the console hides it, and the
     * cascade is a matching flag on its valuations.
     */
    public function up(): void
    {
        if (!Schema::connection('sqlsrv')->hasTable('vfc_projects')) {
            return;
        }

        Schema::connection('sqlsrv')->table('vfc_projects', function (Blueprint $table) {
            if (!Schema::connection('sqlsrv')->hasColumn('vfc_projects', 'is_deleted')) {
                $table->boolean('is_deleted')->default(false);
            }
            if (!Schema::connection('sqlsrv')->hasColumn('vfc_projects', 'deleted_at')) {
                $table->dateTime('deleted_at')->nullable();
            }
            if (!Schema::connection('sqlsrv')->hasColumn('vfc_projects', 'deleted_by')) {
                $table->unsignedBigInteger('deleted_by')->nullable();
            }
        });
    }

    public function down(): void
    {
        if (!Schema::connection('sqlsrv')->hasTable('vfc_projects')) {
            return;
        }

        Schema::connection('sqlsrv')->table('vfc_projects', function (Blueprint $table) {
            $table->dropColumn(['is_deleted', 'deleted_at', 'deleted_by']);
        });
    }
};
