<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 | A company login sees its surveyors' notifications too (user, 2026-11-10). It has its own read mark on them, so the
 | company opening its Notifications never marks them read for the surveyor. Additive, nullable.
 */
return new class extends Migration
{
    public function up(): void
    {
        $s = Schema::connection('sqlsrv');
        if (! $s->hasColumn('surveyor_portal_notifications', 'company_read_at')) {
            $s->table('surveyor_portal_notifications', fn (Blueprint $t) => $t->dateTime('company_read_at')->nullable());
        }
    }

    public function down(): void
    {
        $s = Schema::connection('sqlsrv');
        if ($s->hasColumn('surveyor_portal_notifications', 'company_read_at')) {
            $s->table('surveyor_portal_notifications', fn (Blueprint $t) => $t->dropColumn('company_read_at'));
        }
    }
};
