<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $schema = Schema::connection('sqlsrv');
        if (! $schema->hasTable('surveyor_portal_notifications')) {
            throw new RuntimeException('The surveyor portal notifications table must exist first.');
        }

        DB::connection('sqlsrv')->transaction(function () use ($schema) {
            if (! $schema->hasColumn('surveyor_portal_notifications', 'company_id')) {
                $schema->table('surveyor_portal_notifications', function (Blueprint $table) {
                    $table->unsignedBigInteger('company_id')->nullable();
                });
            }

            DB::connection('sqlsrv')->statement("
                IF EXISTS (
                    SELECT 1 FROM sys.indexes
                    WHERE name = 'ix_survey_portal_notes_account'
                      AND object_id = OBJECT_ID('surveyor_portal_notifications')
                )
                DROP INDEX ix_survey_portal_notes_account ON surveyor_portal_notifications
            ");
            DB::connection('sqlsrv')->statement(
                'ALTER TABLE surveyor_portal_notifications ALTER COLUMN surveyor_portal_account_id bigint NULL'
            );
            DB::connection('sqlsrv')->statement("
                IF NOT EXISTS (
                    SELECT 1 FROM sys.indexes
                    WHERE name = 'ix_survey_portal_notes_account'
                      AND object_id = OBJECT_ID('surveyor_portal_notifications')
                )
                CREATE INDEX ix_survey_portal_notes_account
                    ON surveyor_portal_notifications (surveyor_portal_account_id)
            ");
            DB::connection('sqlsrv')->statement("
                IF NOT EXISTS (
                    SELECT 1 FROM sys.indexes
                    WHERE name = 'ix_survey_portal_notes_company'
                      AND object_id = OBJECT_ID('surveyor_portal_notifications')
                )
                CREATE INDEX ix_survey_portal_notes_company
                    ON surveyor_portal_notifications (company_id)
            ");
        });
    }

    public function down(): void
    {
        $schema = Schema::connection('sqlsrv');
        if (! $schema->hasTable('surveyor_portal_notifications')) {
            return;
        }

        DB::connection('sqlsrv')->transaction(function () use ($schema) {
            DB::connection('sqlsrv')->table('surveyor_portal_notifications')
                ->whereNull('surveyor_portal_account_id')
                ->delete();
            DB::connection('sqlsrv')->statement("
                IF EXISTS (
                    SELECT 1 FROM sys.indexes
                    WHERE name = 'ix_survey_portal_notes_company'
                      AND object_id = OBJECT_ID('surveyor_portal_notifications')
                )
                DROP INDEX ix_survey_portal_notes_company ON surveyor_portal_notifications
            ");

            if ($schema->hasColumn('surveyor_portal_notifications', 'company_id')) {
                $schema->table('surveyor_portal_notifications', fn (Blueprint $table) => $table->dropColumn('company_id'));
            }

            DB::connection('sqlsrv')->statement("
                IF EXISTS (
                    SELECT 1 FROM sys.indexes
                    WHERE name = 'ix_survey_portal_notes_account'
                      AND object_id = OBJECT_ID('surveyor_portal_notifications')
                )
                DROP INDEX ix_survey_portal_notes_account ON surveyor_portal_notifications
            ");
            DB::connection('sqlsrv')->statement(
                'ALTER TABLE surveyor_portal_notifications ALTER COLUMN surveyor_portal_account_id bigint NOT NULL'
            );
            DB::connection('sqlsrv')->statement("
                CREATE INDEX ix_survey_portal_notes_account
                    ON surveyor_portal_notifications (surveyor_portal_account_id)
            ");
        });
    }
};
