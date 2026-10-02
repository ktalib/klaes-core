<?php

namespace App\Http\Controllers\Survey;

use App\Http\Controllers\Controller;
use App\Models\Survey\SurveyExamination;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * QA/QC verification queue for survey and compensation deliverables.
 *
 * An item enters Queued, is worked on (In Review) and leaves either Passed or
 * Returned. Both exits stamp reviewed_at, which is what the turnaround KPI is
 * measured from — so the tiles stay honest without a separate audit table.
 */
class ExaminationController extends Controller
{
    public const TYPES      = ['Compensation Plan', 'Boundary Survey', 'Layout Plan', 'Plot Allocation', 'Occupancy Permit', 'Other'];
    public const PRIORITIES = ['High', 'Normal', 'Low'];
    public const STATUSES   = ['Queued', 'In Review', 'Passed', 'Returned'];

    /** Statuses that still need a reviewer's attention. */
    public const OPEN_STATUSES = ['Queued', 'In Review'];

    public function index(Request $r)
    {
        $q = SurveyExamination::query();

        if ($term = trim((string) $r->query('q', ''))) {
            $q->where(function ($w) use ($term) {
                $w->where('exam_ref', 'like', "%$term%")
                  ->orWhere('linked_ref', 'like', "%$term%")
                  ->orWhere('exam_type', 'like', "%$term%")
                  ->orWhere('submitted_by', 'like', "%$term%");
            });
        }

        if ($s = $r->query('status'))   $q->where('status', $s);
        if ($p = $r->query('priority')) $q->where('priority', $p);

        $exams = $q->orderByDesc('id')->paginate(15)->withQueryString();

        // ?review=<id> opens the findings panel against one item.
        $reviewing = $r->filled('review') ? SurveyExamination::find($r->query('review')) : null;

        $exam = new SurveyExamination([
            'priority'     => 'Normal',
            'status'       => 'Queued',
            'submitted_by' => optional($r->user())->name,
        ]);

        return view('survey_module.workflow.examination', [
            'exams'     => $exams,
            'exam'      => $exam,
            'reviewing' => $reviewing,
            'stats'     => $this->stats(),
        ]);
    }

    public function store(Request $r)
    {
        $data = $r->validate([
            'linked_ref'   => 'required|string|max:100',
            'exam_type'    => ['required', Rule::in(self::TYPES)],
            'submitted_by' => 'required|string|max:255',
            'priority'     => ['required', Rule::in(self::PRIORITIES)],
            'status'       => ['nullable', Rule::in(self::OPEN_STATUSES)],
            'findings'     => 'nullable|string|max:4000',
        ], [
            'linked_ref.required'   => 'A case, GKN or LPKN reference is required.',
            'exam_type.in'          => 'Choose one of the listed examination types.',
            'submitted_by.required' => 'Record who submitted the item.',
        ]);

        // An item cannot be filed as already reviewed; it has to go through the queue.
        $data['status']   = $data['status'] ?? 'Queued';
        $data['exam_ref'] = SurveyExamination::nextRef('exam_ref', 'EX');

        $exam = SurveyExamination::create($data);

        return redirect()
            ->route('survey-module.workflow.examination')
            ->with('success', "{$exam->exam_ref} queued for examination.");
    }

    public function pass(Request $r, SurveyExamination $examination)
    {
        $data = $r->validate(['findings' => 'nullable|string|max:4000']);

        $examination->update([
            'status'      => 'Passed',
            'reviewed_at' => now(),
            // Keep the previous findings when the reviewer passes without comment.
            'findings'    => trim((string) ($data['findings'] ?? '')) ?: $examination->findings,
        ]);

        return redirect()
            ->route('survey-module.workflow.examination')
            ->with('success', "{$examination->exam_ref} passed examination.");
    }

    /**
     * Returning is a rejection, so the reason is mandatory — the submitter has
     * to be told what to fix.
     */
    public function returnItem(Request $r, SurveyExamination $examination)
    {
        $data = $r->validate(
            ['findings' => 'required|string|max:4000'],
            ['findings.required' => 'A reason is required when returning an item.']
        );

        $examination->update([
            'status'      => 'Returned',
            'reviewed_at' => now(),
            'findings'    => $data['findings'],
        ]);

        return redirect()
            ->route('survey-module.workflow.examination')
            ->with('success', "{$examination->exam_ref} returned to {$examination->submitted_by}.");
    }

    public function destroy(SurveyExamination $examination)
    {
        $ref = $examination->exam_ref;
        $examination->delete();

        return redirect()
            ->route('survey-module.workflow.examination')
            ->with('success', "Examination {$ref} removed from the queue.");
    }

    /**
     * The four KPI tiles. All live — no literals.
     *
     * @return array<string,mixed>
     */
    private function stats(): array
    {
        $days = $this->avgTurnaroundDays();

        return [
            'awaiting'     => SurveyExamination::whereIn('status', self::OPEN_STATUSES)->count(),
            'passed_today' => SurveyExamination::where('status', 'Passed')
                                ->whereDate('reviewed_at', now()->toDateString())->count(),
            'returned'     => SurveyExamination::where('status', 'Returned')->count(),
            'avg_days'     => $days,
            'avg_label'    => $days === null ? '—' : number_format($days, 1) . 'd',
        ];
    }

    /**
     * Mean reviewed_at - created_at, in days, over items that have been
     * reviewed. Null when nothing has been reviewed yet, so the tile can show
     * a dash instead of a misleading 0.0d.
     */
    private function avgTurnaroundDays(): ?float
    {
        $row = SurveyExamination::query()
            ->whereNotNull('reviewed_at')
            ->whereNotNull('created_at')
            ->selectRaw('AVG(CAST(DATEDIFF(minute, created_at, reviewed_at) AS FLOAT)) AS avg_minutes')
            ->first();

        $minutes = $row ? $row->avg_minutes : null;

        return $minutes === null ? null : ((float) $minutes) / 1440;
    }
}
