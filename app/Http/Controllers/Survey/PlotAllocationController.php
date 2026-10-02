<?php

namespace App\Http\Controllers\Survey;

use App\Http\Controllers\Controller;
use App\Models\Survey\SurveyCompCase;
use App\Models\Survey\SurveyPlotAllocation;
use App\Models\Survey\SurveyProject;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Land-for-Land plot register: the 50:50 Farmer/Government split.
 *
 * One row is one plot. The split is taken from the ROW COUNT — the same source
 * SurveyCompCase::plotSplit() uses — not from the per-row govt_50 / farmer_50
 * columns, which are the surveyor's own field figures and are shown as entered.
 */
class PlotAllocationController extends Controller
{
    public function index(Request $r)
    {
        $case = $this->filterCase($r);

        $q = SurveyPlotAllocation::query()->with('case:id,case_ref,scheme_type');
        if ($case) $q->where('survey_comp_case_id', $case->id);

        if ($term = trim((string) $r->query('q', ''))) {
            $q->where(function ($w) use ($term) {
                $w->where('farmer_name', 'like', "%$term%")
                  ->orWhere('f_no', 'like', "%$term%")
                  ->orWhere('plot_no', 'like', "%$term%")
                  ->orWhere('op_no', 'like', "%$term%");
            });
        }

        $rows = (clone $q)->orderBy('sr')->orderBy('id')->paginate(50)->withQueryString();

        // Totals cover the whole filtered register, not just the visible page.
        // A real case defers to plotSplit() so the page can never disagree with the model.
        $totals = $case
            ? $case->plotSplit()
            : $this->splitOf((clone $q)->count());

        // What the surveyor actually wrote in the two 50% columns, for comparison.
        $entered = [
            'govt'   => (float) (clone $q)->sum('govt_50'),
            'farmer' => (float) (clone $q)->sum('farmer_50'),
        ];

        // Plot allocation is a Land-for-Land instrument; monetary cases are not offered.
        $landCases = SurveyCompCase::where('scheme_type', SurveyProject::SCHEME_LAND)
            ->withCount('plots')
            ->orderByDesc('id')->get(['id', 'case_ref', 'scheme_type', 'num_plots']);

        return view('survey_module.tools.plot_allocation', compact('rows', 'totals', 'entered', 'landCases', 'case'));
    }

    /** "Add Farmer" — a first row carrying the farmer's identity and field figures. */
    public function store(Request $r)
    {
        $data = $r->validate([
            'survey_comp_case_id' => 'nullable|integer',
            'f_no'                => 'required|string|max:50',
            'farmer_name'         => 'required|string|max:255',
            'acreage'             => 'nullable|numeric|min:0|max:9999999999',
            'creetable'           => 'nullable|in:Yes,No',
            'govt_50'             => 'nullable|numeric|min:0|max:9999999999',
            'farmer_50'           => 'nullable|numeric|min:0|max:9999999999',
            'plot_no'             => 'required|string|max:50',
            'op_no'               => 'nullable|string|max:50',
            'sign'                => 'nullable|string|max:100',
            'remarks'             => 'nullable|string|max:500',
        ], [
            'f_no.required'        => 'The farmer number is required.',
            'farmer_name.required' => 'The farmer name is required.',
            'plot_no.required'     => 'A plot number is required — one row is one plot.',
        ]);

        if ($refusal = $this->rejectNonLandCase($data['survey_comp_case_id'] ?? null)) {
            return $refusal;
        }

        $data['creetable'] = $data['creetable'] ?? 'Yes';
        $data['sr']        = $this->nextSr($data['survey_comp_case_id'] ?? null);

        $row = SurveyPlotAllocation::create($data);

        return $this->backToRegister($r, "Farmer {$row->farmer_name} added at plot {$row->plot_no} (SR {$row->sr}).");
    }

    /** "Add Plot" — a continuation row under the farmer already on the register. */
    public function addRow(Request $r)
    {
        $data = $r->validate([
            'survey_comp_case_id' => 'nullable|integer',
            'plot_no'             => 'nullable|string|max:50',
            'op_no'               => 'nullable|string|max:50',
            'sign'                => 'nullable|string|max:100',
            'remarks'             => 'nullable|string|max:500',
        ]);

        if ($refusal = $this->rejectNonLandCase($data['survey_comp_case_id'] ?? null)) {
            return $refusal;
        }

        $sr = $this->nextSr($data['survey_comp_case_id'] ?? null);

        // Blank plot/OP numbers get the obvious sequential defaults so the row is
        // usable straight away; both stay editable in the table. Empty strings are
        // dropped first, otherwise they would win over the defaults below.
        $data = array_filter($data, fn ($v) => $v !== null && $v !== '');

        $row = SurveyPlotAllocation::create($data + [
            'sr'      => $sr,
            'plot_no' => (string) $sr,
            'op_no'   => sprintf('OP-%03d', $sr),
        ]);

        return $this->backToRegister($r, "Plot row SR {$row->sr} added.");
    }

    /** Inline edit of the four columns the register keeps editable. */
    public function update(Request $r, SurveyPlotAllocation $plot)
    {
        $data = $r->validate([
            'plot_no' => 'nullable|string|max:50',
            'op_no'   => 'nullable|string|max:50',
            'sign'    => 'nullable|string|max:100',
            'remarks' => 'nullable|string|max:500',
        ]);

        $plot->update($data);

        return $this->backToRegister($r, "Row SR {$plot->sr} updated.");
    }

    public function destroy(Request $r, SurveyPlotAllocation $plot)
    {
        $sr    = $plot->sr;
        $scope = $plot->survey_comp_case_id;

        $plot->delete();
        $this->resequence($scope);

        return $this->backToRegister($r, "Row SR {$sr} deleted; the register has been renumbered.");
    }

    /** CSV of the register as filtered, in the table's own column order. */
    public function export(Request $r): StreamedResponse
    {
        $case = $this->filterCase($r);

        $q = SurveyPlotAllocation::query();
        if ($case) $q->where('survey_comp_case_id', $case->id);

        if ($term = trim((string) $r->query('q', ''))) {
            $q->where(function ($w) use ($term) {
                $w->where('farmer_name', 'like', "%$term%")
                  ->orWhere('f_no', 'like', "%$term%")
                  ->orWhere('plot_no', 'like', "%$term%")
                  ->orWhere('op_no', 'like', "%$term%");
            });
        }

        $rows   = (clone $q)->orderBy('sr')->orderBy('id')->get();
        $totals = $case ? $case->plotSplit() : $this->splitOf($rows->count());

        $name = 'plot-allocation-' . ($case ? strtolower($case->case_ref) . '-' : '') . date('Ymd-His') . '.csv';

        return response()->streamDownload(function () use ($rows, $totals, $case) {
            $out = fopen('php://output', 'w');

            fputcsv($out, ['SR', 'F. No.', 'Farmers Name', 'Acreage', 'Creetable',
                'Govt. 50%', 'Farmer 50%', 'Plot No.', 'OP No.', 'Sign', 'Remarks']);

            foreach ($rows as $row) {
                fputcsv($out, [
                    $row->sr, $row->f_no, $row->farmer_name, $row->acreage, $row->creetable,
                    $row->govt_50, $row->farmer_50, $row->plot_no, $row->op_no, $row->sign, $row->remarks,
                ]);
            }

            // The split is a property of the register, so it travels with the rows.
            fputcsv($out, []);
            fputcsv($out, ['Case', $case?->case_ref ?? 'All cases']);
            fputcsv($out, ['Total Plots', $totals['total']]);
            fputcsv($out, ['Govt. 50%', $totals['govt']]);
            fputcsv($out, ['Farmer 50%', $totals['farmer']]);

            fclose($out);
        }, $name, ['Content-Type' => 'text/csv']);
    }

    /* ------------------------------- internals ------------------------------- */

    private function filterCase(Request $r): ?SurveyCompCase
    {
        $id = $r->query('case');

        return $id ? SurveyCompCase::find($id) : null;
    }

    /** Same rule as SurveyCompCase::plotSplit(): the odd plot goes to Government. */
    private function splitOf(int $total): array
    {
        return [
            'total'  => $total,
            'govt'   => (int) ceil($total / 2),
            'farmer' => (int) floor($total / 2),
        ];
    }

    /** Plot allocation is meaningless for a cash case — refuse rather than silently file it. */
    private function rejectNonLandCase($caseId)
    {
        if (! $caseId) return null;

        $case = SurveyCompCase::find($caseId);

        if (! $case) {
            return back()->with('error', 'That case no longer exists.')->withInput();
        }

        if ($case->isMonetary()) {
            return back()->with('error',
                "Case {$case->case_ref} is a Monetary (cash for trees) case. Plot allocation applies to Land-for-Land cases only.")
                ->withInput();
        }

        return null;
    }

    /** SR runs 1..n within a case (or within the unassigned register). */
    private function nextSr($caseId): int
    {
        $q = SurveyPlotAllocation::query();
        $caseId ? $q->where('survey_comp_case_id', $caseId) : $q->whereNull('survey_comp_case_id');

        return ((int) $q->max('sr')) + 1;
    }

    /** Close the gap a delete leaves so SR stays a clean 1..n sequence. */
    private function resequence($caseId): void
    {
        $q = SurveyPlotAllocation::query();
        $caseId ? $q->where('survey_comp_case_id', $caseId) : $q->whereNull('survey_comp_case_id');

        $rows = $q->orderBy('sr')->orderBy('id')->get();

        DB::connection('sqlsrv')->transaction(function () use ($rows) {
            foreach ($rows as $i => $row) {
                if ((int) $row->sr !== $i + 1) {
                    $row->update(['sr' => $i + 1]);
                }
            }
        });
    }

    /** Writes come from the register page, so keep the active case filter on the redirect. */
    private function backToRegister(Request $r, string $message)
    {
        $case = $r->input('survey_comp_case_id') ?: $r->query('case');

        return redirect()
            ->route('survey-module.tools.plot-allocation', $case ? ['case' => $case] : [])
            ->with('success', $message);
    }
}
