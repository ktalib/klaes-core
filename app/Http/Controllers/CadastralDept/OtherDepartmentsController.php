<?php

namespace App\Http\Controllers\CadastralDept;

use App\Http\Controllers\Controller;
use App\Models\CadastralDept\CadastralCase;
use App\Services\CadastralDept\CaseWorkflow;
use App\Services\CadastralDept\DeskAccess;
use App\Services\CadastralDept\DeskDecisions;
use App\Services\CadastralDept\WorkflowDefinition;
use Illuminate\Http\Request;

/**
 * Survey sidebar > Other Departments > Cadastral (user, 2026-10-28): the Cadastral files now with
 * a Survey Department unit (Compensation Check, DSG Approval, Examination Check, SG Approval).
 * The unit's role (config cadastral_dept.external_desk_roles) opens its page and lets it send the
 * file on from the file page itself (DeskAccess::canActOn); the SG uploads the Final Survey Plan there.
 */
class OtherDepartmentsController extends Controller
{
    /** desk => page title, in the order of the sidebar. */
    public const PAGES = [
        'compensation_unit' => 'Compensation Check',
        'dsg' => 'DSG Approval',
        'examination_unit' => 'Examination Check',
        'surveyor_general' => 'SG Approval',
    ];

    /** The pages that are approval tables and get the Awaiting Approval / Approved tabs. */
    private const APPROVAL_PAGES = ['dsg', 'surveyor_general'];

    /** Rows on the Approved tab, newest first; search finds older ones. */
    private const APPROVED_LIMIT = 200;

    public function show(Request $request, string $desk, WorkflowDefinition $def, DeskAccess $access)
    {
        abort_unless(isset(self::PAGES[$desk]), 404);
        abort_unless($access->canWorkDesk($request->user(), $desk), 403,
            'This page needs the ' . (config('cadastral_dept.external_desk_roles', [])[$desk] ?? 'unit') . ' role.');

        $term = trim((string) $request->query('q', ''));
        $search = fn ($q) => $q->when($term !== '', fn ($q) => $q->where(fn ($w) => $w->where('file_number', 'like', "%{$term}%")
            ->orWhere('case_ref', 'like', "%{$term}%")->orWhere('file_title', 'like', "%{$term}%")));
        // DSG and SG Approval (CR item 13, 2026-11-09): Awaiting Approval / Approved tabs.
        $approvalTabs = in_array($desk, self::APPROVAL_PAGES, true);
        $tab = $approvalTabs && $request->query('tab') === 'approved' ? 'approved' : 'awaiting';
        $rows = collect();
        $tabCounts = $decisions = [];
        $approvedTotal = 0;
        if (CaseWorkflow::installed()) {
            $rows = $search(CadastralCase::query()->open()->with(['latestMovement'])->where('current_desk', $desk))
                ->orderByDesc('updated_at')->orderByDesc('id')->get();
            if ($approvalTabs) {
                $decided = app(DeskDecisions::class);
                $approvedQuery = $decided->decidedAt($search(CadastralCase::query()), $desk)
                    ->where(fn ($q) => $q->where('current_desk', '!=', $desk)->orWhere('status', '!=', CadastralCase::STATUS_IN_PROGRESS));
                $approvedTotal = (clone $approvedQuery)->count();
                $tabCounts = ['awaiting' => $rows->count(), 'approved' => $approvedTotal];
                if ($tab === 'approved') {
                    $rows = $approvedQuery->orderByDesc('updated_at')->orderByDesc('id')->limit(self::APPROVED_LIMIT)->get();
                    $decisions = $decided->latest($rows, $desk);
                }
            }
        }

        return view('cadastral_dept.other_departments.show', [
            'def' => $def,
            'desk' => $desk,
            'title' => self::PAGES[$desk],
            'rows' => $rows,
            'term' => $term,
            'approvalTabs' => $approvalTabs,
            'tab' => $tab,
            'tabCounts' => $tabCounts,
            'decisions' => $decisions,
            'approvedTotal' => $approvedTotal,
            'approvedLimit' => self::APPROVED_LIMIT,
        ]);
    }
}
