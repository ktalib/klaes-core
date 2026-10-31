<?php

namespace App\Http\Controllers\Survey;

use App\Http\Controllers\Controller;
use App\Models\Survey\SurveyCompCase;
use App\Models\Survey\SurveyProject;
use App\Models\Survey\SurveyResettlementCalculation as Calc;
use App\Models\Survey\SurveyResettlementRate as Rate;
use App\Services\Survey\ResettlementCalculator;
use App\Support\AddressBuilder;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Resettlement Plot Allocation → Resettlement Calculator (docs/KLAES Survey Module –
 * Workflow_V02.md 2.3). Pick the land (its area fills in), the land use / purpose and
 * the density; the number of plots shows live for every density of that land use, and
 * the farmer's / Government's 50:50 shares on Land-to-Land projects. A result can be
 * saved against the land. Plot sizes are managed on the same page.
 */
class ResettlementCalculatorController extends Controller
{
    public function __construct(private ResettlementCalculator $calculator)
    {
    }

    public function index(Request $r)
    {
        $lands = SurveyCompCase::with(['project:id,project_code,name,scheme_type,purpose', 'beneficiaries:id,survey_comp_case_id,full_name'])
            ->orderByDesc('id')
            ->get(['id', 'case_ref', 'survey_project_id', 'scheme_type', 'area_ha', 'portion_size_ha', 'portion_no', 'prop_plot', 'prop_district', 'prop_district_other', 'prop_lga']);

        $calcs = Calc::with('land:id,case_ref')
            ->when(trim((string) $r->query('q')), fn ($q, $t) => $q->where(fn ($w) => $w->where('calc_ref', 'like', "%$t%")
                ->orWhereHas('land', fn ($l) => $l->where('case_ref', 'like', "%$t%"))))
            ->orderByDesc('id')->paginate(15)->withQueryString();

        return view('survey_module.compensation.resettlement_calculator', [
            'rates'    => Rate::orderBy('land_use')->orderBy('purpose')->orderBy('sort_order')->orderBy('id')->get(),
            'purposes' => ResettlementCalculator::purposes(),
            'calcs'    => $calcs,
            'roundings' => ResettlementCalculator::ROUNDINGS,
            'lands'    => $lands,
            'landMeta' => $lands->mapWithKeys(fn ($l) => [$l->id => [
                'ref'      => $l->case_ref,
                'area'     => $l->area_ha !== null ? (float) $l->area_ha : ($l->portion_size_ha !== null ? (float) $l->portion_size_ha : null),
                'plotForPlot' => ($l->scheme_type ?: $l->project?->scheme_type) === SurveyProject::SCHEME_LAND,
                'project'  => $l->project ? $l->project->project_code . ' · ' . $l->project->name : null,
                'farmers'  => $l->beneficiaries->pluck('full_name')->filter()->implode(', '),
                // Property order: Plot No., District, LGA.
                'place'    => collect([$l->prop_plot ? 'Plot ' . $l->prop_plot : null,
                    AddressBuilder::resolve($l->prop_district, $l->prop_district_other), AddressBuilder::resolve($l->prop_lga)])->filter()->implode(', '),
            ]]),
        ]);
    }

    /** Save a result. Recomputed here from the inputs; the page's figures are only a preview. */
    public function store(Request $r)
    {
        $data = $r->validate([
            'survey_comp_case_id' => ['nullable', Rule::exists('sqlsrv.survey_comp_cases', 'id')->whereNull('deleted_at')],
            'rate_id'             => ['required', Rule::exists('sqlsrv.survey_resettlement_rates', 'id')->whereNull('deleted_at')->where('is_active', 1)],
            'area_ha'             => 'required|numeric|gt:0|max:1000000',
            'deduction_percent'   => 'nullable|numeric|min:0|max:90',
            'rounding'            => ['required', Rule::in(array_keys(ResettlementCalculator::ROUNDINGS))],
            'purpose'             => 'required|string|max:150',
            'split'               => 'nullable|boolean',
            'notes'               => 'nullable|string|max:1000',
        ], [
            'purpose.required' => 'Choose the purpose.',
            'rate_id.required' => 'Choose the land use, purpose and density.',
            'area_ha.gt'       => 'The area must be more than zero hectares.',
        ]);

        $rate = Rate::findOrFail($data['rate_id']);
        // The density must be one offered for the purpose: its own sizes, or else the land use's general ones.
        $purpose = ResettlementCalculator::purposeOf($rate->land_use, $data['purpose']);
        $ownSizes = $purpose ? Rate::where('is_active', 1)->where('land_use', $rate->land_use)->whereRaw('UPPER(purpose) = ?', [mb_strtoupper($purpose)])->exists() : false;
        $fits = $purpose && ($rate->purpose ? mb_strtoupper($rate->purpose) === mb_strtoupper($purpose) : ! $ownSizes);
        if (! $fits) {
            throw ValidationException::withMessages(['rate_id' => 'That density is not one set for ' . ($purpose ?: $data['purpose']) . '. Choose the purpose and density again.']);
        }
        $land = ! empty($data['survey_comp_case_id']) ? SurveyCompCase::with('project')->find($data['survey_comp_case_id']) : null;
        // On a land the scheme decides: Land-to-Land always splits 50:50; Monetary Compensation never.
        $split = $land
            ? (($land->scheme_type ?: $land->project?->scheme_type) === SurveyProject::SCHEME_LAND)
            : (bool) ($data['split'] ?? false);

        $res = $this->calculator->calculate((float) $data['area_ha'], (float) $rate->plot_size_ha,
            (float) ($data['deduction_percent'] ?? 0), $data['rounding'], $split);

        $c = Calc::create([
            'calc_ref'                    => Calc::nextRef('calc_ref', 'RC', 4),
            'survey_comp_case_id'         => $land?->id,
            'survey_resettlement_rate_id' => $rate->id,
            'land_use'                    => $rate->land_use,
            'purpose'                     => $purpose,
            'density'                     => $rate->density,
            'plot_size_ha'                => $rate->plot_size_ha,
            'area_ha'                     => (float) $data['area_ha'],
            'deduction_percent'           => (float) ($data['deduction_percent'] ?? 0),
            'net_area_ha'                 => $res['net_area'],
            'exact_plots'                 => $res['exact'],
            'rounding'                    => $data['rounding'],
            'plots'                       => $res['plots'],
            'split_applied'               => $res['split'],
            'farmer_plots'                => $res['farmer'],
            'government_plots'            => $res['government'],
            'notes'                       => trim((string) ($data['notes'] ?? '')) ?: null,
        ]);

        return redirect()->route('survey-module.compensation.resettlement-calculator')
            ->with('success', "{$c->calc_ref} saved: {$c->plots} plots" . ($c->split_applied ? " (farmer {$c->farmer_plots}, Government {$c->government_plots})" : '') . ($land ? " for {$land->case_ref}." : '.'));
    }

    /* -------------------------- plot sizes -------------------------- */

    public function storeRate(Request $r)
    {
        $data = $this->rateData($r);
        $data['sort_order'] = (int) Rate::where('land_use', $data['land_use'])->max('sort_order') + 1;
        $rate = Rate::create($data + ['is_active' => true]);

        return back()->with('success', "Plot size for {$rate->label} added: {$rate->plot_size_ha} ha.");
    }

    public function updateRate(Request $r, Rate $rate)
    {
        $rate->update($this->rateData($r, $rate));

        return back()->with('success', "Plot size for {$rate->label} updated: {$rate->plot_size_ha} ha.");
    }

    public function toggleRate(Rate $rate)
    {
        $rate->update(['is_active' => ! $rate->is_active]);

        return back()->with('success', "{$rate->label} " . ($rate->is_active ? 'restored.' : 'retired: it is no longer offered.'));
    }

    private function rateData(Request $r, ?Rate $self = null): array
    {
        $data = $r->validate([
            'land_use'     => ['required', Rule::in(ResettlementCalculator::LAND_USES)],
            'purpose'      => 'nullable|string|max:150',
            'density'      => 'nullable|string|max:50',
            'code'         => 'nullable|string|max:10',
            'plot_size_ha' => 'required|numeric|gt:0|max:1000',
            'source'       => 'nullable|string|max:255',
        ], ['plot_size_ha.gt' => 'The plot size must be more than zero hectares.']);

        $one = fn ($v) => ($v = trim(preg_replace('/\s+/', ' ', (string) $v))) === '' ? null : $v;
        // Names of land uses and densities in Title Case ("residential" -> "Residential"); the code is an acronym.
        $data['land_use'] = Str::title((string) $one($data['land_use']));
        $data['density'] = $one($data['density'] ?? null) ? Str::title($one($data['density'])) : null;
        $data['code'] = $one($data['code'] ?? null) ? strtoupper($one($data['code'])) : null;
        $data['source'] = $one($data['source'] ?? null);
        // Purpose: one of the land use's purposes (its listed spelling), or none = every purpose of the land use.
        if ($one($data['purpose'] ?? null) !== null) {
            $data['purpose'] = ResettlementCalculator::purposeOf($data['land_use'], $data['purpose'])
                ?? throw ValidationException::withMessages(['purpose' => "That purpose is not one of {$data['land_use']}'s purposes."]);
        } else {
            $data['purpose'] = null;
        }

        $dupe = Rate::withTrashed()->when($self, fn ($q) => $q->whereKeyNot($self->id))
            ->whereRaw('LOWER(land_use) = ?', [mb_strtolower($data['land_use'])])
            ->where(fn ($q) => $data['purpose'] === null ? $q->whereNull('purpose') : $q->whereRaw('UPPER(purpose) = ?', [mb_strtoupper($data['purpose'])]))
            ->where(fn ($q) => $data['density'] === null ? $q->whereNull('density') : $q->whereRaw('LOWER(density) = ?', [mb_strtolower($data['density'])]))
            ->first();
        if ($dupe) {
            throw ValidationException::withMessages(['land_use' => "{$dupe->label} already has a plot size" . ($dupe->is_active ? '.' : ' (retired; restore it instead).')]);
        }

        return $data;
    }
}
