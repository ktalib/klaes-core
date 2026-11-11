<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 | Cadastral one-working-day-per-office timer, finished (user, 2026-11-09; Daniel's list of 08/11: deadline,
 | reminder, warning, default, penalty "addition to officer's dashboard + message to line manager", room for
 | genuine reasons). Additive only.
 |   cadastral_cases.timer_notified   the highest stage already rung at the current office (one bell per stage)
 |   cadastral_cases.exception_desk   the office a pause was set for; a move elsewhere ends it
 |   cadastral_cases.exception_at     when the pause was set
 |   cadastral_case_penalties         one row per file per office that reached the penalty stage: the desk tally
 */
return new class extends Migration
{
    public function up(): void
    {
        $s = Schema::connection('sqlsrv');
        if (! $s->hasTable('cadastral_cases') || ! $s->hasColumn('cadastral_cases', 'desk_since')) {
            throw new RuntimeException('Run 2026_11_09_020000_add_timer_columns_to_cadastral_cases first.');
        }
        $has = fn (string $c) => $s->hasColumn('cadastral_cases', $c);
        $s->table('cadastral_cases', function (Blueprint $t) use ($has) {
            if (! $has('timer_notified')) $t->string('timer_notified', 20)->nullable();
            if (! $has('exception_desk')) $t->string('exception_desk', 40)->nullable();
            if (! $has('exception_at')) $t->dateTime('exception_at')->nullable();
        });

        if (! $s->hasTable('cadastral_case_penalties')) {
            $s->create('cadastral_case_penalties', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('cadastral_case_id');
                $t->string('case_ref', 40)->nullable();
                $t->string('file_number', 100)->nullable();
                $t->string('desk', 40);
                $t->dateTime('desk_since')->nullable();
                $t->dateTime('deadline')->nullable();
                $t->dateTime('applied_at');
                $t->unsignedBigInteger('head_user_id')->nullable();   // the line manager told, when one is picked
                $t->timestamps();
                $t->index(['desk', 'applied_at'], 'cad_case_penalties_desk_idx');
                $t->index('cadastral_case_id', 'cad_case_penalties_case_idx');
            });
        }
    }

    public function down(): void
    {
        $s = Schema::connection('sqlsrv');
        $s->dropIfExists('cadastral_case_penalties');
        $drop = array_values(array_filter(['timer_notified', 'exception_desk', 'exception_at'], fn ($c) => $s->hasColumn('cadastral_cases', $c)));
        if ($drop) $s->table('cadastral_cases', fn (Blueprint $t) => $t->dropColumn($drop));
    }
};
