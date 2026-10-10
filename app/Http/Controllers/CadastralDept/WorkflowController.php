<?php

namespace App\Http\Controllers\CadastralDept;

use App\Http\Controllers\Controller;
use App\Models\CadastralDept\CadastralCase;
use App\Services\CadastralDept\CaseWorkflow;
use App\Services\CadastralDept\WorkflowDefinition;

class WorkflowController extends Controller
{
    /** Department home: open files per desk, per application type, and the latest intakes. */
    public function index(WorkflowDefinition $def)
    {
        $installed = CaseWorkflow::installed();
        $byDesk = $byWorkflow = [];
        $recent = collect();

        if ($installed) {
            $byDesk = CadastralCase::query()->open()
                ->selectRaw('current_desk, COUNT(*) AS n')->groupBy('current_desk')
                ->pluck('n', 'current_desk')->all();
            $byWorkflow = CadastralCase::query()->open()
                ->selectRaw('workflow, COUNT(*) AS n')->groupBy('workflow')
                ->pluck('n', 'workflow')->all();
            $recent = CadastralCase::query()->orderByDesc('id')->limit(10)->get();
        }

        return view('cadastral_dept.index', [
            'def' => $def,
            'installed' => $installed,
            'byDesk' => $byDesk,
            'byWorkflow' => $byWorkflow,
            'recent' => $recent,
        ]);
    }

    /** The three workflows from config, phase by phase. Needs no tables. */
    public function map(WorkflowDefinition $def)
    {
        return view('cadastral_dept.map', ['def' => $def]);
    }
}
