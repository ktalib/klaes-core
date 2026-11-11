<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 | The Cadastral one-working-day-per-office timer (recording 355; CadastralCaseTimer). These columns were first
 | written into 2026_10_10_100000, which had already run, so they never reached the database (found 2026-11-09).
 | desk_since / sla_desk are the per-office clock: when the file reached its current desk, and which desk the
 | deadline belongs to, so the clock restarts on every move. Additive only.
 */
return new class extends Migration
{
    private const COLUMNS = ['sla_deadline', 'timer_status', 'exception_reason', 'exception_approved_by', 'exception_until',
        'defaulted_at', 'penalty_applied_at', 'desk_since', 'sla_desk'];

    public function up(): void
    {
        $s = Schema::connection('sqlsrv');
        if (! $s->hasTable('cadastral_cases')) {
            throw new RuntimeException('cadastral_cases must exist first.');
        }
        $has = fn (string $c) => $s->hasColumn('cadastral_cases', $c);
        $s->table('cadastral_cases', function (Blueprint $t) use ($has) {
            if (! $has('sla_deadline')) $t->dateTime('sla_deadline')->nullable();
            if (! $has('timer_status')) $t->string('timer_status', 20)->nullable();   // on_time | reminder | warning | default | penalty
            if (! $has('exception_reason')) $t->text('exception_reason')->nullable();
            if (! $has('exception_approved_by')) $t->unsignedBigInteger('exception_approved_by')->nullable();
            if (! $has('exception_until')) $t->dateTime('exception_until')->nullable();
            if (! $has('defaulted_at')) $t->dateTime('defaulted_at')->nullable();
            if (! $has('penalty_applied_at')) $t->dateTime('penalty_applied_at')->nullable();
            if (! $has('desk_since')) $t->dateTime('desk_since')->nullable();
            if (! $has('sla_desk')) $t->string('sla_desk', 40)->nullable();
        });
        if (! collect(Schema::connection('sqlsrv')->getConnection()->select("SELECT name FROM sys.indexes WHERE name = 'cad_cases_timer_status_idx'"))->count()) {
            $s->table('cadastral_cases', fn (Blueprint $t) => $t->index('timer_status', 'cad_cases_timer_status_idx'));
        }
    }

    public function down(): void
    {
        $s = Schema::connection('sqlsrv');
        if (collect($s->getConnection()->select("SELECT name FROM sys.indexes WHERE name = 'cad_cases_timer_status_idx'"))->count()) {
            $s->table('cadastral_cases', fn (Blueprint $t) => $t->dropIndex('cad_cases_timer_status_idx'));
        }
        $drop = array_values(array_filter(self::COLUMNS, fn ($c) => $s->hasColumn('cadastral_cases', $c)));
        if ($drop) $s->table('cadastral_cases', fn (Blueprint $t) => $t->dropColumn($drop));
    }
};
