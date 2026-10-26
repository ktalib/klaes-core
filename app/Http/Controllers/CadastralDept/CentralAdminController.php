<?php

namespace App\Http\Controllers\CadastralDept;

use App\Models\CadastralDept\CadastralCase;
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

        $installed = CaseWorkflow::installed();
        $rows = null;
        $cards = $menus = [];
        $deskCounts = collect();

        if ($installed) {
            $open = CadastralCase::query()->open();
            $deskCounts = (clone $open)->selectRaw('current_desk, count(*) as n')->groupBy('current_desk')->pluck('n', 'current_desk');
            $stuckCount = (clone $open)->where('updated_at', '<', now()->subDays(self::STUCK_DAYS))->count();
            $base = route('cadastral-dept.central-admin.index');
            $cards = [
                ['label' => 'Open files', 'value' => (int) $deskCounts->sum(), 'hint' => 'in the department now', 'colour' => 'blue', 'url' => $base, 'active' => !$deskFilter && !$stuck],
                ['label' => 'Waiting over ' . self::STUCK_DAYS . ' days', 'value' => $stuckCount, 'hint' => 'no movement in a week', 'colour' => 'red',
                    'url' => route('cadastral-dept.central-admin.index', ['stuck' => 1]), 'active' => $stuck],
                ['label' => 'Out with other departments', 'value' => (int) $deskCounts->filter(fn ($n, $d) => empty($def->desks()[$d]['internal']))->sum(),
                    'hint' => 'record their return here', 'colour' => 'purple'],
            ];

            $rows = CadastralCase::query()->open()->with(['tasks', 'latestMovement', 'receiver', 'fmc'])
                ->when($wfFilter, fn ($q) => $q->where('workflow', $wfFilter))
                ->when($deskFilter, fn ($q) => $q->where('current_desk', $deskFilter))
                ->when($stuck, fn ($q) => $q->where('updated_at', '<', now()->subDays(self::STUCK_DAYS)))
                ->when($term !== '', fn ($q) => $q->where(fn ($x) => $x->where('file_number', 'like', "%{$term}%")
                    ->orWhere('case_ref', 'like', "%{$term}%")
                    ->orWhere('file_title', 'like', "%{$term}%")))
                ->orderBy('updated_at')   // longest waiting first
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
            'waitingJobs' => $attachDue ? app(FieldJackets::class)->waitingJobs() : null,
            'surveyors' => $attachDue ? app(FieldJackets::class)->surveyors() : collect(),
            'filterWorkflow' => $wfFilter,
            'filterDesk' => $deskFilter,
            'stuck' => $stuck,
            'stuckDays' => self::STUCK_DAYS,
            'term' => $term,
            'cdCards' => $cards,
        ]);
    }
}
