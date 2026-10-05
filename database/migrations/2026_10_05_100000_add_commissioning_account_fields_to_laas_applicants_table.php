<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Let File Commissioning open a LAAS Portal account on the applicant's behalf.
 *
 *  username              What the commissioning SMS tells the applicant to sign
 *                        in with. Nullable because self-registered accounts
 *                        predate it; unique only among the rows that carry one,
 *                        which on SQL Server needs a filtered index — a plain
 *                        UNIQUE constraint admits a single NULL.
 *  must_change_password  The SMS carries a temporary password. Set on every
 *                        account commissioning creates; cleared by the first
 *                        password change.
 *  account_origin        'portal' (registered themselves) or 'commissioning'.
 */
return new class extends Migration
{
    public function up(): void
    {
        $schema = Schema::connection('sqlsrv');

        $schema->table('laas_applicants', function (Blueprint $table) use ($schema) {
            if (!$schema->hasColumn('laas_applicants', 'username')) {
                $table->string('username', 60)->nullable()->after('name');
            }
            if (!$schema->hasColumn('laas_applicants', 'must_change_password')) {
                $table->boolean('must_change_password')->default(false)->after('password');
            }
            if (!$schema->hasColumn('laas_applicants', 'account_origin')) {
                $table->string('account_origin', 20)->nullable()->after('status');
            }
        });

        DB::connection('sqlsrv')->statement(
            "IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'laas_applicants_username_unique'
                            AND object_id = OBJECT_ID('laas_applicants'))
             CREATE UNIQUE INDEX laas_applicants_username_unique
                 ON laas_applicants (username) WHERE username IS NOT NULL"
        );
    }

    public function down(): void
    {
        DB::connection('sqlsrv')->statement(
            "IF EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'laas_applicants_username_unique'
                        AND object_id = OBJECT_ID('laas_applicants'))
             DROP INDEX laas_applicants_username_unique ON laas_applicants"
        );

        $schema = Schema::connection('sqlsrv');

        $schema->table('laas_applicants', function (Blueprint $table) use ($schema) {
            foreach (['username', 'must_change_password', 'account_origin'] as $column) {
                if ($schema->hasColumn('laas_applicants', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
