<?php

namespace App\Services\CadastralDept;

use App\Models\CadastralDept\CadastralCase;
use App\Services\Cadastral\CadastralSettings;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * One working day per office (recording 355: "those files should not use more than one day in each office").
 *
 * The clock starts when the file reaches its current desk (the latest movement into that desk; the
 * registration time if it never moved) and restarts on every move to another desk. Steps at the same desk
 * do not restart it. Only desks inside the department are timed. With "Count only working days" on,
 * Saturdays and Sundays are not counted, so 24 hours = one working day (Friday 10:00 is due Monday 10:00).
 *
 * Stages, as shares of the allowance (Configurable Entries > Cadastral > Workflow Policy): on_time, reminder
 * (reminder %), warning (warning %), default (past the deadline), penalty (penalty switched ON and the
 * penalty delay passed).
 *
 * Notifications (user, 2026-11-09, "finish it and enable it"; Daniel's list of 08/11): the deadline goes out
 * with the "received at" bell (WorkflowNotifications::plan), then each later stage rings once per office
 * (Due soon, Warning, Overdue, Penalty) to the desk's officers and the admins; the penalty also goes to the
 * unit head picked in Central Admin (the line manager) and is logged in cadastral_case_penalties, the desk
 * tally. When more than SUMMARY_OVER files at one office reach the same stage in one run (the backlog), that
 * office gets one summary bell instead. Bell only, no SMS.
 *
 * Pause (genuine reasons: illness, leave, other assignment): Central Admin sets exception_until for the
 * current office. While paused the file is on time; when the pause ends the office gets a fresh working day
 * from the end of the pause. A move to another office ends it.
 *
 * state()/states() are read-only (desk lists, Central Admin); evaluateAll() stores the stage every
 * 15 minutes (cadastral:timeline-evaluate) and logs each change.
 */
class CadastralCaseTimer
{
    public const STAGES = [
        'on_time'  => ['label' => 'On time',  'tone' => 'green'],
        'reminder' => ['label' => 'Due soon', 'tone' => 'yellow'],
        'warning'  => ['label' => 'Warning',  'tone' => 'orange'],
        'default'  => ['label' => 'Overdue',  'tone' => 'red'],
        'penalty'  => ['label' => 'Penalty',  'tone' => 'red'],
    ];

    /** How far each stage is along; a stage rings when it is past the one already rung at this office. */
    public const RANK = ['on_time' => 0, 'reminder' => 1, 'warning' => 2, 'default' => 3, 'penalty' => 4];

    /** More files than this at one office reaching one stage in one run get a single summary bell. */
    public const SUMMARY_OVER = 3;

    /** Genuine reasons for a pause (Daniel, 08/11: "illness, leave, other assignments from above"). */
    public const PAUSE_REASONS = ['Illness', 'Leave', 'Other assignment'];

    private ?CadastralSettings $settings = null;
    private ?array $internalDesks = null;

    public static function installed(): bool
    {
        static $ok = null;
        if ($ok === null) {
            $s = Schema::connection('sqlsrv');
            $ok = $s->hasTable('cadastral_cases') && $s->hasColumn('cadastral_cases', 'timer_status') && $s->hasColumn('cadastral_cases', 'desk_since');
        }

        return $ok;
    }

    /** The stage bells, pause and penalty tally (migration 2026_11_09_120000). */
    public static function alertsInstalled(): bool
    {
        static $ok = null;
        if ($ok === null) {
            $s = Schema::connection('sqlsrv');
            $ok = self::installed() && $s->hasColumn('cadastral_cases', 'timer_notified') && $s->hasColumn('cadastral_cases', 'exception_desk')
                && $s->hasTable('cadastral_case_penalties');
        }

        return $ok;
    }

    /** Whether the clock runs at this desk (inside the department). */
    public function isTimed(?string $desk): bool
    {
        return $desk !== null && $this->timed($desk);
    }

    /** When a file that reaches an office at $from must move on. */
    public function deadlineFrom(Carbon $from): Carbon
    {
        $s = $this->settings();

        return $this->addHours($from, max(1, (int) $s->slaHoursPerOffice()), $s->workingDaysOnly());
    }

    /** The pause set for the file's current office, if any (an old row without a desk counts as this office). */
    private function pauseHere(CadastralCase $case): ?Carbon
    {
        if (! $case->exception_until) return null;
        $desk = $case->getAttribute('exception_desk');
        if ($desk !== null && $desk !== '' && $desk !== $case->current_desk) return null;

        return Carbon::parse($case->exception_until);
    }

    private function settings(): CadastralSettings
    {
        return $this->settings ??= new CadastralSettings();
    }

    /** Desks inside the department: the ones the clock runs on. */
    private function timed(string $desk): bool
    {
        if ($this->internalDesks === null) {
            $this->internalDesks = array_keys(array_filter(app(WorkflowEngine::class)->definition()->desks(), fn ($d) => ! empty($d['internal'])));
        }

        return in_array($desk, $this->internalDesks, true);
    }

    /**
     * When each case reached its current desk: the latest movement into that desk from another desk.
     *
     * @param  Collection<int, CadastralCase>  $cases
     * @return array<int, Carbon>  case id => arrival
     */
    public function arrivals(Collection $cases): array
    {
        if ($cases->isEmpty()) return [];
        $rows = DB::connection('sqlsrv')->table('cadastral_case_movements as m')
            ->join('cadastral_cases as c', 'c.id', '=', 'm.cadastral_case_id')
            ->whereIn('m.cadastral_case_id', $cases->pluck('id')->all())
            ->whereColumn('m.to_desk', 'c.current_desk')
            ->where(fn ($q) => $q->whereNull('m.from_desk')->orWhereColumn('m.from_desk', '!=', 'm.to_desk'))
            ->groupBy('m.cadastral_case_id')
            ->selectRaw('m.cadastral_case_id as id, MAX(m.acted_at) as arrived')
            ->pluck('arrived', 'id');

        $out = [];
        foreach ($cases as $c) {
            $at = $rows[$c->id] ?? null;
            $out[$c->id] = $at ? Carbon::parse($at) : ($c->received_at ? Carbon::parse($c->received_at) : Carbon::parse($c->created_at));
        }

        return $out;
    }

    /** The timer of one open case, read only. Null when the file is not timed (finished, or outside the department). */
    public function state(CadastralCase $case, ?Carbon $arrived = null): ?array
    {
        if ($case->status !== CadastralCase::STATUS_IN_PROGRESS || ! $this->timed((string) $case->current_desk)) return null;
        $arrived ??= $this->arrivals(collect([$case]))[$case->id] ?? null;
        if (! $arrived) return null;

        $s = $this->settings();
        $hours = max(1, (int) $s->slaHoursPerOffice());
        $workingOnly = $s->workingDaysOnly();
        $now = now();
        $pause = $this->pauseHere($case);
        $paused = $pause && $now->lt($pause);
        // A pause that has ended gives the office a fresh working day from its end.
        $from = $pause && ! $paused && $pause->gt($arrived) ? $pause->copy() : $arrived;
        $deadline = $this->addHours($from, $hours, $workingOnly);
        $elapsed = $this->hoursBetween($from, $now, $workingOnly);

        $stage = 'on_time';
        if (! $paused) {
            if ($now->gte($deadline)) {
                $stage = 'default';
                if ($s->penaltyEnabled() && $now->gte($deadline->copy()->addHours(max(0, $s->penaltyDelayHours())))) $stage = 'penalty';
            } elseif ($elapsed >= $hours * $s->warningPercent() / 100) {
                $stage = 'warning';
            } elseif ($elapsed >= $hours * $s->reminderPercent() / 100) {
                $stage = 'reminder';
            }
        }
        $left = $hours - $elapsed;

        return [
            'stage'    => $stage,
            'label'    => self::STAGES[$stage]['label'],
            'tone'     => self::STAGES[$stage]['tone'],
            'since'    => $arrived,
            'deadline' => $deadline,
            'paused'   => $paused,
            'pause_reason' => $paused ? $case->exception_reason : null,
            'text'     => $paused ? 'Paused until ' . $pause->format('d/m/Y H:i') . ($case->exception_reason ? ' (' . $case->exception_reason . ')' : '')
                : ($left > 0 ? 'Due ' . $deadline->format('d/m/Y H:i') . ' (' . $this->span($left) . ' left)'
                             : 'Was due ' . $deadline->format('d/m/Y H:i') . ' (' . $this->span(-$left) . ' over)'),
        ];
    }

    /** @return array<int, array> case id => state, for the open, timed cases among $cases */
    public function states(Collection $cases): array
    {
        if (! self::installed()) return [];
        $arrivals = $this->arrivals($cases);
        $out = [];
        foreach ($cases as $c) {
            if ($st = $this->state($c, $arrivals[$c->id] ?? null)) $out[$c->id] = $st;
        }

        return $out;
    }

    /** Store each open case's stage; returns how many changed. $dryRun counts without saving. */
    public function evaluateAll(bool $dryRun = false): int
    {
        if (! self::installed()) return 0;
        $alertsOn = self::alertsInstalled();
        $changed = 0;
        $alerts = [];   // [case, state, stage] to ring after the run
        CadastralCase::query()->open()->orderBy('id')->chunkById(200, function ($cases) use (&$changed, &$alerts, $dryRun, $alertsOn) {
            foreach ($this->states($cases) as $id => $st) {
                $case = $cases->firstWhere('id', $id);
                $newDesk = $case->sla_desk !== $case->current_desk;
                $values = [
                    'desk_since' => $st['since'],
                    'sla_desk' => $case->current_desk,
                    'sla_deadline' => $st['deadline'],
                    'timer_status' => $st['stage'],
                ];
                // The first time an office goes past its day; a move to another office starts clean.
                $nowOverdue = in_array($st['stage'], ['default', 'penalty'], true) && ($newDesk || ! $case->defaulted_at);
                if (in_array($st['stage'], ['default', 'penalty'], true)) {
                    $values['defaulted_at'] = $newDesk || ! $case->defaulted_at ? now() : $case->defaulted_at;
                } elseif ($newDesk) {
                    $values['defaulted_at'] = null;
                }
                $penaltyNow = $st['stage'] === 'penalty' && ($newDesk || ! $case->penalty_applied_at);
                if ($st['stage'] === 'penalty') {
                    $values['penalty_applied_at'] = $penaltyNow ? now() : $case->penalty_applied_at;
                } elseif ($newDesk) {
                    $values['penalty_applied_at'] = null;
                }

                // Each stage rings once per office: the highest one reached and not yet rung.
                $ring = null;
                if ($alertsOn) {
                    $rung = $newDesk ? null : $case->timer_notified;
                    if (self::RANK[$st['stage']] > (self::RANK[$rung] ?? 0)) {
                        $ring = $st['stage'];
                        $values['timer_notified'] = $st['stage'];
                    } elseif ($newDesk) {
                        $values['timer_notified'] = null;
                    }
                    // A pause belongs to one office.
                    if ($newDesk && $case->exception_desk && $case->exception_desk !== $case->current_desk) {
                        $values += ['exception_until' => null, 'exception_reason' => null, 'exception_approved_by' => null, 'exception_desk' => null, 'exception_at' => null];
                    }
                }

                $same = ! $newDesk && ! $ring && $case->timer_status === $st['stage']
                    && $case->sla_deadline && Carbon::parse($case->sla_deadline)->equalTo($st['deadline']);
                if ($same) continue;
                $changed++;
                if ($dryRun) continue;

                DB::connection('sqlsrv')->table('cadastral_cases')->where('id', $id)->update($values);
                if ($alertsOn) {
                    if ($penaltyNow) $this->logPenalty($case, $st);
                    if ($ring) $alerts[] = [$case, $st, $ring];
                } elseif ($nowOverdue) {
                    app(WorkflowNotifications::class)->overdue($case, $st);
                }
                if ($case->timer_status !== $st['stage']) {
                    Log::info("Cadastral timer: {$case->case_ref} at {$case->current_desk} is {$st['label']}", [
                        'cadastral_case_id' => $id, 'from' => $case->timer_status, 'to' => $st['stage'],
                        'since' => $st['since']->toDateTimeString(), 'deadline' => $st['deadline']->toDateTimeString(),
                    ]);
                }
            }
        });

        $this->ring($alerts);

        return $changed;
    }

    /** One bell per file, or one per office and stage when more than SUMMARY_OVER arrive together. */
    private function ring(array $alerts): void
    {
        if (! $alerts) return;
        $notes = app(WorkflowNotifications::class);
        $groups = [];
        foreach ($alerts as $a) {
            $groups[$a[0]->current_desk . '|' . $a[2]][] = $a;
        }
        foreach ($groups as $group) {
            if (count($group) > self::SUMMARY_OVER) {
                $notes->timerSummary((string) $group[0][0]->current_desk, $group[0][2], $group);
                continue;
            }
            foreach ($group as [$case, $st, $stage]) {
                $notes->timerAlert($case, $st, $stage);
            }
        }
    }

    /** The desk tally: one row per file per office that reached the penalty stage. */
    private function logPenalty(CadastralCase $case, array $st): void
    {
        try {
            DB::connection('sqlsrv')->table('cadastral_case_penalties')->insert([
                'cadastral_case_id' => $case->id,
                'case_ref' => $case->case_ref,
                'file_number' => $case->file_number,
                'desk' => (string) $case->current_desk,
                'desk_since' => $st['since'],
                'deadline' => $st['deadline'],
                'applied_at' => now(),
                'head_user_id' => HeadApproval::headUserId((string) $case->current_desk),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('Cadastral penalty log failed', ['case' => $case->case_ref, 'error' => $e->getMessage()]);
        }
    }

    /**
     * Pause the clock at the file's current office for a genuine reason, until $until (Central Admin).
     * Returns the error to show, or null when done.
     */
    public function pause(CadastralCase $case, string $reason, Carbon $until, int $byUserId): ?string
    {
        if (! self::alertsInstalled()) return 'The pause is not installed yet.';
        if (! $this->state($case)) return 'This file is not being timed (finished, or out with another department).';
        if ($until->lte(now())) return 'Choose a time after now.';

        DB::connection('sqlsrv')->table('cadastral_cases')->where('id', $case->id)->update([
            'exception_until' => $until, 'exception_reason' => $reason, 'exception_approved_by' => $byUserId,
            'exception_desk' => $case->current_desk, 'exception_at' => now(),
            // The day after the pause is a new one: its stages ring again. A penalty already logged stays.
            'timer_notified' => null, 'defaulted_at' => null, 'penalty_applied_at' => null,
        ]);
        Log::info("Cadastral timer: {$case->case_ref} paused at {$case->current_desk} until {$until->toDateTimeString()}", ['reason' => $reason, 'by' => $byUserId]);

        return null;
    }

    /** End a pause now: the office gets a fresh working day from this moment. */
    public function resume(CadastralCase $case, int $byUserId): void
    {
        if (! self::alertsInstalled() || ! $case->exception_until) return;
        DB::connection('sqlsrv')->table('cadastral_cases')->where('id', $case->id)->update(['exception_until' => now()]);
        Log::info("Cadastral timer: {$case->case_ref} pause ended early at {$case->current_desk}", ['by' => $byUserId]);
    }

    /** Penalties logged per desk since $since: desk => count. */
    public function penaltyCounts(?Carbon $since = null): array
    {
        if (! self::alertsInstalled()) return [];

        return DB::connection('sqlsrv')->table('cadastral_case_penalties')
            ->when($since, fn ($q) => $q->where('applied_at', '>=', $since))
            ->groupBy('desk')->selectRaw('desk, COUNT(*) as n')->pluck('n', 'desk')->map(fn ($n) => (int) $n)->all();
    }

    /** $hours of counted time after $from: every hour, or weekday hours only. */
    public function addHours(Carbon $from, int $hours, bool $workingOnly): Carbon
    {
        $at = $from->copy();
        if (! $workingOnly) return $at->addHours($hours);

        // Whole-second timestamps: a stored time carries fractions of a second, and a rounded-down "minutes to
        // midnight" of 0 would never move the clock (the loop that froze the scheduler on 2026-11-08).
        $left = $hours * 3600;
        $guard = 0;
        while ($left > 0 && $guard++ < 10000) {
            $midnight = $at->copy()->addDay()->startOfDay();
            if ($at->isWeekend()) {
                $at = $midnight;
                continue;
            }
            $take = min($left, max(1, $midnight->getTimestamp() - $at->getTimestamp()));
            $at = $at->copy()->addSeconds($take);
            $left -= $take;
        }

        return $at;
    }

    /** Counted hours from $start to $end (0 if $end is earlier). */
    public function hoursBetween(Carbon $start, Carbon $end, bool $workingOnly): float
    {
        if ($end->lte($start)) return 0.0;
        if (! $workingOnly) return $start->diffInMinutes($end) / 60;

        $seconds = 0;
        $at = $start->copy();
        $guard = 0;
        while ($at->lt($end) && $guard++ < 10000) {
            $next = $at->copy()->addDay()->startOfDay();
            if ($next->gt($end)) $next = $end->copy();
            if (! $at->isWeekend()) $seconds += max(0, $next->getTimestamp() - $at->getTimestamp());
            $at = $next;
        }

        return $seconds / 3600;
    }

    private function span(float $hours): string
    {
        if ($hours < 1) return max(1, (int) round($hours * 60)) . ' min';
        if ($hours < 48) return round($hours, 1) . ' h';

        return round($hours / 24, 1) . ' days';
    }
}
