<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 | MLPP-SG company login (user, 2026-11-10): an approved company gets one portal login, sent once by SMS and
 | email. It is a surveyor_portal_accounts row with surveyor_type 'Company', so it signs in like a surveyor
 | (username + password, sign-in code, security questions) but sees the company's own sidebar.
 |
 | - must_change_password: the sent password is temporary (LAAS commissioning pattern).
 | - login_sms_sent_at / login_email_sent_at: when the one-time login details went out.
 | - The phone and email unique indexes now cover surveyors only: a company's phone or email is often its
 |   principal surveyor's too. One login per company (filtered unique on company_id).
 | Additive; the existing account keeps its values.
 */
return new class extends Migration
{
    private function db()
    {
        return DB::connection('sqlsrv');
    }

    private function hasIndex(string $name): bool
    {
        return (bool) $this->db()->selectOne('SELECT 1 AS x FROM sys.indexes WHERE name = ? AND object_id = OBJECT_ID(?)', [$name, 'surveyor_portal_accounts']);
    }

    public function up(): void
    {
        $s = Schema::connection('sqlsrv');
        $s->table('surveyor_portal_accounts', function (Blueprint $t) use ($s) {
            if (! $s->hasColumn('surveyor_portal_accounts', 'must_change_password')) $t->boolean('must_change_password')->default(false);
            if (! $s->hasColumn('surveyor_portal_accounts', 'login_sms_sent_at')) $t->dateTime('login_sms_sent_at')->nullable();
            if (! $s->hasColumn('surveyor_portal_accounts', 'login_email_sent_at')) $t->dateTime('login_email_sent_at')->nullable();
        });

        foreach (['phone', 'email'] as $col) {
            $name = "ux_surveyor_portal_{$col}";
            if ($this->hasIndex($name)) $this->db()->statement("DROP INDEX {$name} ON surveyor_portal_accounts");
            $this->db()->statement("CREATE UNIQUE INDEX {$name} ON surveyor_portal_accounts ({$col}) WHERE surveyor_type <> 'Company'");
        }
        if (! $this->hasIndex('ux_surveyor_portal_company_login')) {
            $this->db()->statement("CREATE UNIQUE INDEX ux_surveyor_portal_company_login ON surveyor_portal_accounts (company_id) WHERE surveyor_type = 'Company'");
        }
    }

    public function down(): void
    {
        if ($this->hasIndex('ux_surveyor_portal_company_login')) {
            $this->db()->statement('DROP INDEX ux_surveyor_portal_company_login ON surveyor_portal_accounts');
        }
        // Back to whole-table unique only when no company login shares a surveyor's phone or email.
        foreach (['phone', 'email'] as $col) {
            $name = "ux_surveyor_portal_{$col}";
            if ($this->hasIndex($name)) $this->db()->statement("DROP INDEX {$name} ON surveyor_portal_accounts");
            $this->db()->statement("CREATE UNIQUE INDEX {$name} ON surveyor_portal_accounts ({$col})");
        }
        $s = Schema::connection('sqlsrv');
        foreach (['must_change_password', 'login_sms_sent_at', 'login_email_sent_at'] as $col) {
            if ($s->hasColumn('surveyor_portal_accounts', $col)) {
                if ($col === 'must_change_password') {
                    // The boolean's default constraint has a generated name; drop it first.
                    $df = $this->db()->selectOne("SELECT d.name FROM sys.default_constraints d JOIN sys.columns c ON c.object_id = d.parent_object_id AND c.column_id = d.parent_column_id WHERE d.parent_object_id = OBJECT_ID('surveyor_portal_accounts') AND c.name = 'must_change_password'");
                    if ($df) $this->db()->statement("ALTER TABLE surveyor_portal_accounts DROP CONSTRAINT [{$df->name}]");
                }
                $s->table('surveyor_portal_accounts', fn (Blueprint $t) => $t->dropColumn($col));
            }
        }
    }
};
