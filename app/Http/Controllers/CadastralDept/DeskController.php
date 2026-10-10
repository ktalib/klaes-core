<?php

namespace App\Http\Controllers\CadastralDept;

use App\Http\Controllers\Controller;
use App\Models\CadastralDept\CadastralCase;
use App\Services\CadastralDept\CaseWorkflow;
use App\Services\CadastralDept\DeedsDispatchChecks;
use App\Services\CadastralDept\FileMovementCards;
use App\Services\CadastralDept\WorkflowDefinition;
use App\Services\CadastralDept\WorkflowEngine;
use App\Services\InstrumentWorkflow\WorkflowGuardException;
use Illuminate\Http\Request;

class DeskController extends Controller
{
    /**
     * A desk's listing. Each row carries an action menu with only what the file
     * can do at its current step (client brief: actions switch on at the right
     * step, never all at once).
     *
     * External desks have no listing of their own: files out with another
     * department are listed under the Cadastral desk that receives them back.
     * Registry also sees every other open file, frozen (view only).
     */
    public function show(Request $request, string $desk, WorkflowEngine $engine)
    {
        $def = $engine->definition();
        abort_unless(isset($def->internalDesks()[$desk]), 404);

        $wfFilter = $def->has((string) $request->query('workflow')) ? (string) $request->query('workflow') : null;
        $term = trim((string) $request->query('q', ''));
        $filter = function ($q) use ($wfFilter, $term) {
            return $q->when($wfFilter, fn ($w) => $w->where('workflow', $wfFilter))
                ->when($term !== '', fn ($w) => $w->where(fn ($x) => $x->where('file_number', 'like', "%{$term}%")
                    ->orWhere('case_ref', 'like', "%{$term}%")
                    ->orWhere('file_title', 'like', "%{$term}%")));
        };

        $installed = CaseWorkflow::installed();
        $here = $away = $frozen = collect();

        if ($installed) {
            $here = $filter(CadastralCase::query()->open()->with(['tasks', 'latestMovement'])->where('current_desk', $desk))
                ->orderBy('updated_at')->get();

            $recorded = [];
            foreach ($def->stepsForDesk($desk) as $wf => $steps) {
                foreach ($steps as $step) {
                    if (!empty($def->step($wf, $step)['external'])) {
                        $recorded[$wf][] = $step;
                    }
                }
            }
            if ($recorded) {
                $away = $filter(CadastralCase::query()->open()->with(['tasks', 'latestMovement']))
                    ->where(function ($q) use ($recorded) {
                        foreach ($recorded as $wf => $steps) {
                            $q->orWhere(fn ($w) => $w->where('workflow', $wf)->whereIn('current_step', $steps));
                        }
                    })
                    ->orderBy('updated_at')->get();
            }

            if ($desk === 'registry') {
                $frozen = $filter(CadastralCase::query()->open())
                    ->where('current_desk', '!=', 'registry')
                    ->whereNotIn('id', $away->pluck('id'))
                    ->orderByDesc('updated_at')->limit(200)->get();
            }
        }

        $menus = [];
        foreach ($here->concat($away) as $case) {
            $menus[$case->id] = $this->menu($engine, $case);
        }

        return view('cadastral_dept.desk', [
            'def' => $def,
            'desk' => $desk,
            'deskInfo' => $def->desk($desk),
            'installed' => $installed,
            'here' => $here,
            'away' => $away,
            'frozen' => $frozen,
            'menus' => $menus,
            'fmcInstalled' => FileMovementCards::installed(),
            'filterWorkflow' => $wfFilter,
            'term' => $term,
        ]);
    }

    /**
     * The row's action menu: the step's actions, or "finish the tasks" while any
     * task is open. An action that needs a reason opens the file page instead.
     *
     * @return array{tasks_open:int, actions:array, question:?string}
     */
    private function menu(WorkflowEngine $engine, CadastralCase $case): array
    {
        $def = $engine->definition();
        $step = $def->step($case->workflow, $case->current_step);
        $open = count($engine->missingTasks($case->workflow, $case->current_step, $case->doneTaskKeys()));

        try {
            $actions = $engine->actions($case->workflow, $case->current_step, $case->sub_type);
        } catch (WorkflowGuardException $e) {
            $actions = [];   // an OP file with no sub-type yet: the file page asks for it
        }

        // Where each choice sends the file, for the confirmation pop-up.
        foreach ($actions as &$a) {
            $a['to'] = $a['next']
                ? $def->deskShort($def->step($case->workflow, $a['next'])['desk'])
                : ($a['ends'] === 'returned' ? $case->fileSourceLabel() : 'Deeds / requester');
        }
        unset($a);

        // Checks (Send to Deeds): failing ones are listed instead of the action.
        $failing = $open ? [] : app(DeedsDispatchChecks::class)->failing($case);

        return ['tasks_open' => $open, 'actions' => $failing ? [] : $actions, 'failing' => $failing, 'question' => $step['question'] ?? null];
    }
}
