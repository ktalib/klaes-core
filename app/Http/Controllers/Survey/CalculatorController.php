<?php

namespace App\Http\Controllers\Survey;

use App\Http\Controllers\Controller;
use App\Models\Survey\SurveyCompCase;
use App\Models\Survey\SurveyProject;
use App\Models\Survey\SurveyTreeType;
use App\Services\Survey\SurveySettings;
use App\Services\SurveyCompensationSchedule;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Estimating tool for both schemes. Monetary Compensation: cash crops and economic trees with the land
 * under them (Kano schedule), grouped into four sections.
 * Land to Land: split by the sharing ratio (50:50 unless the project set another), with a configurable odd-plot rule.
 *
 * Nothing is persisted here, but every figure is computed in calculate() rather
 * than in the browser so the numbers can be checked against the catalogue and
 * against SurveyCompCase::plotSplit(), which owns the 50:50 rule.
 *
 * Picking a project shows its per-beneficiary schedule in the tab for the
 * project's scheme (SurveyCompensationSchedule); the estimator below it stays
 * for quick figures not tied to a project.
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

    /** The four sections of the Monetary estimate, in order (recording 302, user 2026-11-01). */
    public const GROUPS = [
        'cash'      => 'Cash Crops',
        'cash_land' => 'Cash Crop Land',
        'trees'     => 'Economic Trees',
        'tree_land' => 'Economic Tree Land',
    ];

    /**
     * Monetary Compensation estimate (Kano schedule, user 2026-11-01). Each line is one cash crop or
     * economic tree in a Category, and the land under it follows it:
     *   cash crop      area × rate (per m², or per hectare when the area is in hectares)
     *   economic tree  stands × rate per stand
     *   its land       land (ha; m² ÷ 10,000) × the crop's / tree's cost of production per hectare
     * One table grouped into Cash Crops, Cash Crop Land, Economic Trees and Economic Tree Land, each
     * with a subtotal, and the grand total of the four.
     */
    private function calculateMonetary(Request $r): array
    {
        // Rows with nothing picked are not lines.
        $r->merge(['lines' => array_values(array_filter((array) $r->input('lines'),
            fn ($l) => is_array($l) && trim((string) ($l['survey_tree_type_id'] ?? '')) !== ''))]);

        $data = $r->validate([
            'lines'                       => 'required|array|min:1',
            'lines.*.survey_tree_type_id' => ['required', Rule::exists('sqlsrv.survey_tree_types', 'id')->whereNull('deleted_at')],
            'lines.*.quantity'            => 'required|numeric|gt:0|max:100000000',
            'lines.*.quantity_unit'       => ['nullable', Rule::in(['sqm', 'hectare'])],
            'lines.*.unit_price'          => 'nullable|numeric|min:0|max:99999999999',
            'lines.*.land_size'           => 'nullable|numeric|min:0|max:100000000',
            'lines.*.land_unit'           => ['nullable', Rule::in(['sqm', 'hectare'])],
            'lines.*.land_rate'           => 'nullable|numeric|min:0|max:99999999999',
            'survey_comp_case_id'         => 'nullable|integer',
            'beneficiaries'               => 'nullable|integer|min:1|max:100000',
        ], [
            'lines.required'                     => 'Add at least one cash crop or economic tree to estimate.',
            'lines.*.survey_tree_type_id.exists' => 'One of the selected crops / trees is not in the catalogue.',
            'lines.*.quantity.required'          => 'Every line needs the area (cash crop) or the number of stands (tree).',
            'lines.*.quantity.gt'                => 'The area or number of stands must be more than zero.',
        ]);

        $types = SurveyTreeType::whereIn('id', collect($data['lines'])->pluck('survey_tree_type_id'))->get()->keyBy('id');
        $fail = fn (string $msg) => throw \Illuminate\Validation\ValidationException::withMessages(['lines' => $msg]);
        $blank = fn ($v) => ! isset($v) || trim((string) $v) === '';

        $groups = array_map(fn ($label) => ['label' => $label, 'rows' => [], 'subtotal' => 0.0], self::GROUPS);

        foreach ($data['lines'] as $line) {
            $type = $types[$line['survey_tree_type_id']];
            $crop = $type->isCashCrop();
            $maturity = $type->maturity ?: SurveyTreeType::MATURE;
            $qty = (float) $line['quantity'];
            if (! $crop && floor($qty) != $qty) {
                $fail("{$type->label}: the number of stands must be a whole number.");
            }
            $unit = $crop ? ($line['quantity_unit'] ?? 'sqm') : 'stand';

            // A blank rate falls back to the catalogue; a typed one is an override. No rate means no valuation, never ₦0.
            $catalogue = $type->rateFor($unit);
            $rate = $blank($line['unit_price'] ?? null) ? $catalogue : (float) $line['unit_price'];
            if ($rate === null) {
                $fail("{$type->label} has no rate per " . ($unit === 'hectare' ? 'hectare' : $type->unitLabel()) . ' yet. Enter it in the Farm Assessment catalogue, or type it on the line.');
            }
            $amount = round($qty * $rate, 2);
            $key = $crop ? 'cash' : 'trees';
            $groups[$key]['rows'][] = [
                'item'       => $type->name,
                'category'   => $maturity,
                'quantity'   => $qty,
                'unit'       => $unit,
                'rate'       => $rate,
                'amount'     => $amount,
                'overridden' => $catalogue === null || $rate != $catalogue,
            ];
            $groups[$key]['subtotal'] += $amount;

            // The land under it: hectares × cost of production per hectare.
            $landSize = (float) ($line['land_size'] ?? 0);
            if ($landSize > 0) {
                $landUnit = $line['land_unit'] ?? 'hectare';
                $ha = self::convertArea($landSize, $landUnit, 'hectare');
                $costCatalogue = $type->costPerHa();
                $cost = $blank($line['land_rate'] ?? null) ? $costCatalogue : (float) $line['land_rate'];
                if ($cost === null) {
                    $fail("{$type->name} has no cost of production per hectare yet, so its land cannot be valued. Enter it in the catalogue, or type it on the line.");
                }
                $landAmount = round($ha * $cost, 2);
                $landKey = $crop ? 'cash_land' : 'tree_land';
                $groups[$landKey]['rows'][] = [
                    'item'       => 'Land: ' . $type->name,
                    'category'   => null,
                    'quantity'   => $landSize,
                    'unit'       => $landUnit,
                    'hectares'   => $ha,
                    'rate'       => $cost,
                    'amount'     => $landAmount,
                    'overridden' => $costCatalogue === null || $cost != $costCatalogue,
                ];
                $groups[$landKey]['subtotal'] += $landAmount;
            }
        }

        $total = round(array_sum(array_column($groups, 'subtotal')), 2);

        [$case, $beneficiaries, $source] = $this->beneficiaryCount($data);

        return [
            'mode'                 => 'monetary',
            'groups'               => $groups,
            'total'                => $total,
            'beneficiaries'        => $beneficiaries,
            'beneficiary_source'   => $source,
            'avg_per_beneficiary'  => $beneficiaries > 0 ? $total / $beneficiaries : null,
            'case_ref'             => $case?->case_ref,
        ];
    }
    /** An area from one unit to another: 1 hectare = 10,000 m². */
    public static function convertArea(float $size, string $from, string $to): float
    {
        if ($from === $to) return $size;

        return $from === 'hectare' ? $size * 10000 : $size / 10000;
    }

    /** Land to Land estimate: split by the sharing ratio, odd plot to whichever side the rule names. */
    private function calculateLand(Request $r): array
    {
        $data = $r->validate([
            'total_plots'         => 'required|integer|min:1|max:1000000',
            'odd_rule'            => ['nullable', Rule::in(self::ODD_RULES)],
            'farmer_pct'          => ['nullable', 'integer', Rule::in(SurveySettings::sharingRatios()->pluck('farmer_pct')->map(fn ($p) => (int) $p)->all())],
            'survey_comp_case_id' => 'nullable|integer',
            'beneficiaries'       => 'nullable|integer|min:1|max:100000',
        ], [
            'total_plots.required' => 'Enter the number of creatable plots.',
            'total_plots.min'      => 'There must be at least one plot to split.',
            'farmer_pct.in'        => 'Choose a sharing ratio from the list.',
        ]);

        $total = (int) $data['total_plots'];
        $rule  = $data['odd_rule'] ?? 'govt';

        [$case, $beneficiaries, $source] = $this->beneficiaryCount($data);

        // The sharing ratio (CR 30102026 item 11): a case uses its project's; an ad-hoc estimate the one picked.
        $farmerPct = $case ? ($case->project?->farmer_share_pct ?? SurveySettings::GENERAL_FARMER_PCT)
            : (int) ($data['farmer_pct'] ?? SurveySettings::defaultFarmerPct());

        // The system-wide rule (the farmer takes the whole plots of their share, Government the rest)
        // lives in SurveySettings::splitPlots(), which SurveyCompCase::plotSplit() uses too.
        $split = SurveySettings::splitPlots($total, $farmerPct);

        if ($rule === 'farmer' && ($total * $farmerPct) % 100 !== 0) {
            // Same split, other way round — only the part plot moves, to the farmer.
            $split['farmer']++;
            $split['govt']--;
        }

        return [
            'mode'                => 'land',
            'total_plots'         => $split['total'],
            'govt_plots'          => $split['govt'],
            'farmer_plots'        => $split['farmer'],
            'odd_rule'            => $rule,
            'odd_plot'            => ($total * $farmerPct) % 100 !== 0,
            'ratio'               => SurveySettings::ratioLabel($farmerPct),
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
        $project = $r->query('project') ? SurveyProject::find($r->query('project')) : null;

        $mode = in_array($r->input('mode'), self::MODES, true)
            ? $r->input('mode')
            : ($project && ! $project->isMonetary() ? 'land' : 'monetary');

        return [
            'mode'     => $mode,
            'projects' => SurveyProject::orderByDesc('id')->get(['id', 'project_code', 'name', 'scheme_type']),
            'project'  => $project,
            'schedule' => $project ? app(SurveyCompensationSchedule::class)->forProject($project) : null,

            'treeTypes' => SurveyTreeType::where('status', 'Active')->orderBy('name')
                ->orderByRaw(TreeController::MATURITY_ORDER)
                ->get(['id', 'name', 'local_name', 'category', 'maturity', 'default_unit_price', 'price_per_ha', 'cost_per_ha']),

            'cases' => SurveyCompCase::with('project:id,farmer_share_pct')->withCount('beneficiaries')
                ->orderByDesc('id')
                ->get(['id', 'case_ref', 'scheme_type', 'num_plots', 'survey_project_id']),

            // Sharing ratios (CR 30102026 item 11) and the Classmark (item 8), from Configurable Entities.
            'ratios'     => SurveySettings::sharingRatios(),
            'landRates'  => SurveySettings::landRates(),
            'classmarks' => SurveySettings::classmarks()->map(fn ($c) => ['from' => (float) $c->from_fraction, 'to' => (float) $c->to_fraction, 'plots' => (float) $c->plots])->values(),

            'schemeMonetary' => SurveyProject::SCHEME_MONETARY,
        ];
    }
}
