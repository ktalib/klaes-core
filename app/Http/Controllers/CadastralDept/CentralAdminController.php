<?php

namespace App\Http\Controllers\CadastralDept;

use App\Models\CadastralDept\CadastralCase;
use App\Services\CadastralDept\CadastralCaseTimer;
use App\Services\CadastralDept\CaseWorkflow;
use App\Services\CadastralDept\DeskAccess;
use App\Services\CadastralDept\FieldJackets;
use App\Services\CadastralDept\FileMovementCards;
use App\Services\CadastralDept\WorkflowEngine;
use Illuminate\Http\Request;

/**
 * Central Admin (CADASTRAL UPDATE, 2026-10-26): every open file in the department in one
 * table, each with the action menu its desk would show, so a Central Admin can move a file
 * that is stuck with an officer who is away or has no system access.
 *
 * The Central Admin role (config cadastral_dept.central_admin_role) works every desk and
 * stands in for every unit head (DeskAccess, HeadApproval); the writes go through the same
 * cadastral-dept routes, which log them as Central Admin interventions. Others in the
 * department can open the table, but its actions are greyed out.
 */
class CentralAdminController extends DeskController
{
    private const PER_PAGE = 50;
    private const STUCK_DAYS = 7;

    public function index(Request $request, WorkflowEngine $engine)
    {
        $user = $request->user();
        $canAct = DeskAccess::isCentralAdmin($user);
        abort_unless($canAct || app(DeskAccess::class)->inDepartment($user), 403,
            'Central Admin is for the Cadastral Department (role ' . DeskAccess::centralAdminRole() . ').');

        $def = $engine->definition();
        $wfFilter = $def->has((string) $request->query('workflow')) ? (string) $request->query('workflow') : null;
        $deskFilter = isset($def->desks()[(string) $request->query('desk')]) ? (string) $request->query('desk') : null;
        $stuck = $request->boolean('stuck');
        $term = trim((string) $request->query('q', ''));
        // One working day per office (recording 355): the admin sees every desk's warnings (user, 2026-11-09).
        $timerGroups = ['overdue' => ['default', 'penalty'], 'warning' => ['warning'], 'due_soon' => ['reminder'], 'penalty' => ['penalty']];
        $timerFilter = isset($timerGroups[(string) $request->query('timer')]) ? (string) $request->query('timer') : null;

        $installed = CaseWorkflow::installed();
        $rows = null;
        $cards = $menus = $timers = [];
        $deskCounts = collect();

        if ($installed) {
            $open = CadastralCase::query()->open();
            $deskCounts = (clone $open)->selectRaw('current_desk, count(*) as n')->groupBy('current_desk')->pluck('n', 'current_desk');
            $stuckCount = (clone $open)->where('updated_at', '<', now()->subDays(self::STUCK_DAYS))->count();
            $base = route('cadastral-dept.central-admin.index');
            $cards = [
                ['label' => 'Open files', 'value' => (int) $deskCounts->sum(), 'hint' => 'in the department now', 'colour' => 'blue', 'url' => $base, 'active' => !$deskFilter && !$stuck && !$timerFilter],
                ['label' => 'Waiting over ' . self::STUCK_DAYS . ' days', 'value' => $stuckCount, 'hint' => 'no movement in a week', 'colour' => 'red',
                    'url' => route('cadastral-dept.central-admin.index', ['stuck' => 1]), 'active' => $stuck],
                ['label' => 'Out with other departments', 'value' => (int) $deskCounts->filter(fn ($n, $d) => empty($def->desks()[$d]['internal']))->sum(),
                    'hint' => 'record their return here', 'colour' => 'purple'],
            ];
            $timers = app(CadastralCaseTimer::class)->states((clone $open)->get());
            if (CadastralCaseTimer::installed()) {
                foreach ([
                    'overdue'  => ['Over 1 working day', 'past their day at an office', 'red'],
                    'warning'  => ['Warning', 'nearly at their day', 'yellow'],
                    'due_soon' => ['Due soon', 'reminder stage', 'yellow'],
                ] as $key => [$label, $hint, $colour]) {
                    $cards[] = ['label' => $label, 'value' => collect($timers)->whereIn('stage', $timerGroups[$key])->count(), 'hint' => $hint, 'colour' => $colour,
                        'url' => route('cadastral-dept.central-admin.index', ['timer' => $key]), 'active' => $timerFilter === $key];
                }
            }
            // The penalty tally per office (user, 2026-11-09: "desk tally + unit head"), last 30 days.
            if (self::SHOW_PENALTY_CARD && CadastralCaseTimer::alertsInstalled()) {
                $penalties = app(CadastralCaseTimer::class)->penaltyCounts(now()->subDays(30));
                arsort($penalties);
                $worst = array_slice(array_map(fn ($d, $n) => $def->deskShort($d) . " {$n}", array_keys($penalties), $penalties), 0, 3);
                $cards[] = ['label' => 'Penalties (30 days)', 'value' => array_sum($penalties),
                    'hint' => $worst ? implode(' · ', $worst) : 'none recorded', 'colour' => 'red',
                    'url' => route('cadastral-dept.central-admin.index', ['timer' => 'penalty']), 'active' => $timerFilter === 'penalty'];
            }
            $timerIds = $timerFilter ? array_keys(array_filter($timers, fn ($t) => in_array($t['stage'], $timerGroups[$timerFilter], true))) : null;

            $rows = CadastralCase::query()->open()->with(['tasks', 'latestMovement', 'receiver', 'fmc'])
                ->when($timerIds !== null, fn ($q) => $q->whereIn('id', $timerIds ?: [0]))
                ->when($wfFilter, fn ($q) => $q->where('workflow', $wfFilter))
                ->when($deskFilter, fn ($q) => $q->where('current_desk', $deskFilter))
                ->when($stuck, fn ($q) => $q->where('updated_at', '<', now()->subDays(self::STUCK_DAYS)))
                ->when($term !== '', fn ($q) => $q->where(fn ($x) => $x->where('file_number', 'like', "%{$term}%")
                    ->orWhere('case_ref', 'like', "%{$term}%")
                    ->orWhere('file_title', 'like', "%{$term}%")))
                ->orderByDesc('created_at')->orderByDesc('id')   // newest registered first (user, 2026-10-26)
                ->paginate(self::PER_PAGE)->withQueryString();

            foreach ($rows as $case) {
                $menus[$case->id] = $this->menu($engine, $case);
            }
        }

        $attachDue = $canAct && collect($menus)->contains(fn ($m) => $m['jacket'] === 'attach_job');

        return view('cadastral_dept.central_admin.index', [
            'def' => $def,
            'installed' => $installed,
            'rows' => $rows,
            'menus' => $menus,
            'canAct' => $canAct,
            'centralRole' => DeskAccess::centralAdminRole(),
            'deskCounts' => $deskCounts,
            'fmcInstalled' => FileMovementCards::installed(),
            'fmcVisible' => FileMovementCards::canView(auth()->user()),   // config fmc_roles (2026-10-26)
            'waitingJobs' => $attachDue ? app(FieldJackets::class)->waitingJobs() : null,
            'surveyors' => $attachDue ? app(FieldJackets::class)->surveyors() : collect(),
            'filterWorkflow' => $wfFilter,
            'filterDesk' => $deskFilter,
            'stuck' => $stuck,
            'stuckDays' => self::STUCK_DAYS,
            'term' => $term,
            'cdCards' => $cards,
            'timers' => $timers,
            'timerFilter' => $timerFilter,
            'pauseReady' => $canAct && CadastralCaseTimer::alertsInstalled(),
            'pauseReasons' => CadastralCaseTimer::PAUSE_REASONS,
        ]);
    }

    /** Pause the file's timer at its current office for a genuine reason (user, 2026-11-09: Central Admin only). */
    public function pauseTimer(Request $request, int $timerCase, CadastralCaseTimer $timer)
    {
        abort_unless(DeskAccess::isCentralAdmin($request->user()), 403, 'Only Central Admin pauses the timer (role ' . DeskAccess::centralAdminRole() . ').');
        $case = CadastralCase::query()->findOrFail($timerCase);
        $data = $request->validate([
            'reason' => ['required', 'string', 'in:' . implode(',', array_merge(CadastralCaseTimer::PAUSE_REASONS, ['Other']))],
            'reason_other' => ['nullable', 'required_if:reason,Other', 'string', 'max:200'],
            'until' => ['required', 'date'],
        ], ['reason_other.required_if' => 'Specify the reason.']);
        $reason = $data['reason'] === 'Other' ? trim((string) $data['reason_other']) : $data['reason'];
        $until = \Carbon\Carbon::parse($data['until']);

        if ($error = $timer->pause($case, $reason, $until, (int) $request->user()->id)) {
            return back()->with('error', $error);
        }

        return back()->with('success', "{$case->file_number}: timer paused at {$case->deskShort()} until " . $until->format('d/m/Y H:i') . " ({$reason}).");
    }

    /** End a pause early: the office gets a fresh working day from now. */
    public function resumeTimer(Request $request, int $timerCase, CadastralCaseTimer $timer)
    {
        abort_unless(DeskAccess::isCentralAdmin($request->user()), 403, 'Only Central Admin resumes the timer (role ' . DeskAccess::centralAdminRole() . ').');
        $case = CadastralCase::query()->findOrFail($timerCase);
        $timer->resume($case, (int) $request->user()->id);

        return back()->with('success', "{$case->file_number}: timer running again at {$case->deskShort()}; one working day from now.");
    }
}
