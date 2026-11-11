<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/*
 | The SG's Add Surveyor no longer asks for an email (user, 2026-11-10). surveyor_portal_accounts.email becomes
 | nullable, and its unique index covers only the emails given (and still surveyors only, not company logins).
 | The portal's own sign-up still requires one. Existing rows unchanged (none is blank).
 */
return new class extends Migration
{
    private function db()
    {
        return DB::connection('sqlsrv');
    }

    private function dropIndex(): void
    {
        if ($this->db()->selectOne("SELECT 1 AS x FROM sys.indexes WHERE name = 'ux_surveyor_portal_email' AND object_id = OBJECT_ID('surveyor_portal_accounts')")) {
            $this->db()->statement('DROP INDEX ux_surveyor_portal_email ON surveyor_portal_accounts');
        }
    }

    public function up(): void
    {
        $this->dropIndex();
        $this->db()->statement('ALTER TABLE surveyor_portal_accounts ALTER COLUMN email NVARCHAR(150) COLLATE SQL_Latin1_General_CP1_CI_AS NULL');
        $this->db()->statement("CREATE UNIQUE INDEX ux_surveyor_portal_email ON surveyor_portal_accounts (email) WHERE surveyor_type <> 'Company' AND email IS NOT NULL");
    }

    public function down(): void
    {
        // Only possible while every account still has an email.
        $this->dropIndex();
        $this->db()->statement('ALTER TABLE surveyor_portal_accounts ALTER COLUMN email NVARCHAR(150) COLLATE SQL_Latin1_General_CP1_CI_AS NOT NULL');
        $this->db()->statement("CREATE UNIQUE INDEX ux_surveyor_portal_email ON surveyor_portal_accounts (email) WHERE surveyor_type <> 'Company'");
    }
};
