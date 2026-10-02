<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'sqlsrv';

    /**
     * Resetting a security paper code now records *why*, because the three reasons
     * do not all mean the same thing for the physical sheet:
     *
     *   mistake_output  – the sheet was printed wrong / mutilated. The paper is
     *                     destroyed, so the code must never return to the pool.
     *   spc_mismatch    – the wrong code was keyed in; the sheet is untouched and
     *                     the code goes back to the pool.
     *   drop_spc        – the code is simply detached; back to the pool.
     *
     * `status` is the human-readable state. A voided code deliberately keeps
     * `is_used = 1` so that every existing pool query (`where is_used = false`,
     * in LandRofo/Rofo/SltrRofo/Cofo controllers and the shared
     * /security-paper-codes/available endpoint) excludes it with no change.
     */
    public function up(): void
    {
        if (!Schema::connection('sqlsrv')->hasTable('global_security_paper_codes')) {
            return;
        }

        Schema::connection('sqlsrv')->table('global_security_paper_codes', function (Blueprint $table) {
            if (!Schema::connection('sqlsrv')->hasColumn('global_security_paper_codes', 'status')) {
                $table->string('status', 20)->nullable();
            }
            if (!Schema::connection('sqlsrv')->hasColumn('global_security_paper_codes', 'void_reason')) {
                $table->string('void_reason', 40)->nullable();
            }
            if (!Schema::connection('sqlsrv')->hasColumn('global_security_paper_codes', 'void_note')) {
                $table->string('void_note', 500)->nullable();
            }
            if (!Schema::connection('sqlsrv')->hasColumn('global_security_paper_codes', 'voided_by')) {
                $table->string('voided_by', 191)->nullable();
            }
            if (!Schema::connection('sqlsrv')->hasColumn('global_security_paper_codes', 'voided_at')) {
                $table->dateTime('voided_at')->nullable();
            }
        });

        // Backfill from the existing is_used flag: nothing is voided yet.
        DB::connection('sqlsrv')->table('global_security_paper_codes')
            ->whereNull('status')
            ->update(['status' => DB::raw("CASE WHEN is_used = 1 THEN 'assigned' ELSE 'available' END")]);
    }

    public function down(): void
    {
        if (!Schema::connection('sqlsrv')->hasTable('global_security_paper_codes')) {
            return;
        }

        foreach (['status', 'void_reason', 'void_note', 'voided_by', 'voided_at'] as $column) {
            if (Schema::connection('sqlsrv')->hasColumn('global_security_paper_codes', $column)) {
                Schema::connection('sqlsrv')->table('global_security_paper_codes', function (Blueprint $table) use ($column) {
                    $table->dropColumn($column);
                });
            }
        }
    }
};
