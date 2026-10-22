<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Bill Master: every bill must carry a Bill Ref ID (user, 2026-10-18), the
 * same way the module bills do (SPAS-BILL-2026-014, CBL-2026-0002,
 * DBBL-2026-00600, ...). Replaces the optional bill_no with a required,
 * unique bill_ref_id.
 *
 * Only runs while bill_master is empty, so no bill loses its number.
 */
return new class extends Migration {
    public function up(): void
    {
        $schema = Schema::connection('sqlsrv');

        if (!$schema->hasTable('bill_master') || $schema->hasColumn('bill_master', 'bill_ref_id')) {
            return;
        }

        if (DB::connection('sqlsrv')->table('bill_master')->exists()) {
            throw new RuntimeException('bill_master already has rows; move bill_no into bill_ref_id by hand.');
        }

        $schema->table('bill_master', function (Blueprint $table) {
            $table->dropUnique('bill_master_bill_no_unique');
        });

        $schema->table('bill_master', function (Blueprint $table) {
            $table->dropColumn('bill_no');
        });

        $schema->table('bill_master', function (Blueprint $table) {
            $table->string('bill_ref_id', 100)->unique();
        });
    }

    public function down(): void
    {
        $schema = Schema::connection('sqlsrv');

        if (!$schema->hasColumn('bill_master', 'bill_ref_id')) {
            return;
        }

        $schema->table('bill_master', function (Blueprint $table) {
            $table->dropUnique('bill_master_bill_ref_id_unique');
        });

        $schema->table('bill_master', function (Blueprint $table) {
            $table->dropColumn('bill_ref_id');
        });

        $schema->table('bill_master', function (Blueprint $table) {
            $table->string('bill_no', 100)->nullable()->unique();
        });
    }
};
