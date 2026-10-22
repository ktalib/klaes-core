<?php

namespace App\Http\Controllers\Survey;

use App\Http\Controllers\Controller;
use App\Models\CadastralDept\CadastralCase;
use App\Services\CadastralDept\CaseWorkflow;
use App\Services\CadastralDept\DeskAccess;
use Illuminate\Http\Request;

/**
 * Cadastral Files under Examination/Verification (user, 2026-10-21): the Cadastral
 * Department's Verification files, read-only from the Survey side. Opening a case
 * needs Cadastral access; anyone here may queue a file for Survey examination.
 */
class CadastralFilesController extends Controller
{
    /** The Survey Dept desk in config/cadastral_dept.php that examines files. */
    public const EXAMINATION_DESK = 'examination_unit';

    public function index(Request $r)
    {
        if (! CaseWorkflow::installed()) {
            return view('survey_module.workflow.cadastral_files', ['installed' => false]);
        }

        $base = fn () => CadastralCase::query()->where('workflow', 'verification');

        $q = $base()->orderByDesc('id');
        if ($term = trim((string) $r->query('q', ''))) {
            $q->where(fn ($w) => $w->where('file_number', 'like', "%{$term}%")
                ->orWhere('case_ref', 'like', "%{$term}%")
                ->orWhere('file_title', 'like', "%{$term}%"));
        }
        if (in_array($s = $r->query('status'), [CadastralCase::STATUS_IN_PROGRESS, CadastralCase::STATUS_COMPLETED, CadastralCase::STATUS_RETURNED], true)) {
            $q->where('status', $s);
        }
        if ($r->query('at') === 'examination') {
            $q->open()->where('current_desk', self::EXAMINATION_DESK);
        }

        return view('survey_module.workflow.cadastral_files', [
            'installed'    => true,
            'cases'        => $q->paginate(25)->withQueryString(),
            'canOpenCases' => DeskAccess::can($r->user(), 'view'),
            'stats'        => [
                'total'       => $base()->count(),
                'open'        => $base()->open()->count(),
                'examination' => $base()->open()->where('current_desk', self::EXAMINATION_DESK)->count(),
                'completed'   => $base()->where('status', CadastralCase::STATUS_COMPLETED)->count(),
            ],
        ]);
    }
}
