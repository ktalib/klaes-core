<?php

namespace App\Http\Controllers\Survey;

use App\Http\Controllers\Controller;
use App\Models\Survey\SurveyCompCase;
use App\Models\Survey\SurveyProject;
use App\Models\Survey\SurveyTreeType;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Estimating tool for both schemes. Monetary: tree x qty x unit price.
 * Land-for-Land: 50:50 split with a configurable odd-plot rule.
 *
 * Nothing is persisted here, but every figure is computed in calculate() rather
 * than in the browser so the numbers can be checked against the catalogue and
 * against SurveyCompCase::plotSplit(), which owns the 50:50 rule.
 */
class CalculatorController extends Controller
{
    public const MODES = ['monetary', 'land'];

    /** Which side of a 50:50 split the odd plot falls to. */
    public const ODD_RULES = ['govt', 'farmer'];

    public function index(Request $r)
    {
        return view('survey_module.compensation.calculator', $this->pageData($r) + ['result' => null]);
    }

    public function calculate(Request $r)
    {
        $mode = $r->input('mode');
        if (! in_array($mode, self::MODES, true)) {
            $mode = 'monetary';
        }

        // The two modes are mutually exclusive — only the chosen one is validated
        // or computed, so stray fields from the other panel can never leak in.
        $result = $mode === 'land'
            ? $this->calculateLand($r)
            : $this->calculateMonetary($r);

        if ($r->ajax() || $r->wantsJson()) {
            return response()->json($result);
        }

        return view('survey_module.compensation.calculator', $this->pageData($r) + ['result' => $result]);
    }

    /** Monetary estimate: a multi-line quote, each line tree x qty x unit price. */
    private function calculateMonetary(Request $r): array
    {
        $data = $r->validate([
            'lines'                       => 'required|array|min:1',
            'lines.*.survey_tree_type_id' => ['required', Rule::exists('sqlsrv.survey_tree_types', 'id')->whereNull('deleted_at')],
            'lines.*.quantity'            => 'required|integer|min:1|max:1000000',
            'lines.*.unit_price'          => 'nullable|numeric|min:0|max:99999999999',
            'survey_comp_case_id'         => 'nullable|integer',
            'beneficiaries'               => 'nullable|integer|min:1|max:100000',
        ], [
            'lines.required'                     => 'Add at least one tree line to estimate.',
            'lines.*.survey_tree_type_id.exists' => 'One of the selected tree types is not in the catalogue.',
        ]);

        $types = SurveyTreeType::whereIn('id', collect($data['lines'])->pluck('survey_tree_type_id'))
            ->get()->keyBy('id');

        $lines = [];
        $total = 0.0;

        foreach ($data['lines'] as $line) {
            $type = $types[$line['survey_tree_type_id']];

            // A blank unit price falls back to the catalogue; a supplied one is an
            // explicit override. Either way the line total is computed here, not sent.
            $unit      = isset($line['unit_price']) && $line['unit_price'] !== ''
                ? (float) $line['unit_price']
                : (float) $type->default_unit_price;
            $qty       = (int) $line['quantity'];
            $lineTotal = $qty * $unit;
            $total    += $lineTotal;

            $lines[] = [
                'tree_type'  => $type->name,
                'category'   => $type->category,
                'quantity'   => $qty,
                'unit_price' => $unit,
                'line_total' => $lineTotal,
                'overridden' => $unit !== (float) $type->default_unit_price,
            ];
        }

        [$case, $beneficiaries, $source] = $this->beneficiaryCount($data);

        return [
            'mode'                 => 'monetary',
            'lines'                => $lines,
            'total'                => $total,
            'beneficiaries'        => $beneficiaries,
            'beneficiary_source'   => $source,
            'avg_per_beneficiary'  => $beneficiaries > 0 ? $total / $beneficiaries : null,
            'case_ref'             => $case?->case_ref,
        ];
    }

    /** Land-for-Land estimate: the 50:50 split, odd plot to whichever side the rule names. */
    private function calculateLand(Request $r): array
    {
        $data = $r->validate([
            'total_plots'         => 'required|integer|min:1|max:1000000',
            'odd_rule'            => ['nullable', Rule::in(self::ODD_RULES)],
            'survey_comp_case_id' => 'nullable|integer',
            'beneficiaries'       => 'nullable|integer|min:1|max:100000',
        ], [
            'total_plots.required' => 'Enter the number of plots to carve.',
            'total_plots.min'      => 'There must be at least one plot to split.',
        ]);

        $total = (int) $data['total_plots'];
        $rule  = $data['odd_rule'] ?? 'govt';

        // The system-wide rule (odd plot to Government) lives in SurveyCompCase::plotSplit();
        // a transient case reuses it rather than re-deriving ceil/floor here.
        $split = (new SurveyCompCase(['num_plots' => $total]))->plotSplit();

        if ($rule === 'farmer') {
            // Same split, other way round — only the odd plot moves.
            $split = ['total' => $total, 'govt' => (int) floor($total / 2), 'farmer' => (int) ceil($total / 2)];
        }

        [$case, $beneficiaries, $source] = $this->beneficiaryCount($data);

        return [
            'mode'                => 'land',
            'total_plots'         => $split['total'],
            'govt_plots'          => $split['govt'],
            'farmer_plots'        => $split['farmer'],
            'odd_rule'            => $rule,
            'odd_plot'            => $total % 2 === 1,
            'is_default_rule'     => $rule === 'govt',
            'beneficiaries'       => $beneficiaries,
            'beneficiary_source'  => $source,
            'plots_per_farmer'    => $beneficiaries > 0 ? $split['farmer'] / $beneficiaries : null,
            'case_ref'            => $case?->case_ref,
        ];
    }

    /**
     * Beneficiary count for the per-beneficiary averages. A selected case supplies
     * a real count; otherwise the user's own figure is used, and if neither is
     * given there is no average to show rather than a made-up one.
     *
     * @return array{0: ?SurveyCompCase, 1: int, 2: string}
     */
    private function beneficiaryCount(array $data): array
    {
        $case = ! empty($data['survey_comp_case_id'])
            ? SurveyCompCase::find($data['survey_comp_case_id'])
            : null;

        if ($case) {
            return [$case, $case->beneficiaries()->count(), "registered on {$case->case_ref}"];
        }

        $manual = (int) ($data['beneficiaries'] ?? 0);

        return [null, $manual, $manual > 0 ? 'entered manually' : 'not set'];
    }

    /** Catalogue and case lists the two panels are driven from. */
    private function pageData(Request $r): array
    {
        return [
            'mode' => in_array($r->input('mode'), self::MODES, true) ? $r->input('mode') : 'monetary',

            'treeTypes' => SurveyTreeType::where('status', 'Active')->orderBy('name')
                ->get(['id', 'name', 'category', 'default_unit_price']),

            'cases' => SurveyCompCase::withCount('beneficiaries')
                ->orderByDesc('id')
                ->get(['id', 'case_ref', 'scheme_type', 'num_plots']),

            'schemeMonetary' => SurveyProject::SCHEME_MONETARY,
        ];
    }
}
