<?php

namespace App\Http\Controllers\Survey;

use App\Http\Controllers\Controller;
use App\Models\Survey\SurveyCaseTree;
use App\Models\Survey\SurveyCompCase;
use App\Models\Survey\SurveyProject;
use App\Models\Survey\SurveyTreeType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Economic tree valuation catalogue and per-case tree lines.
 *
 * Monetary Compensation schemes only: line_total is always quantity x unit_price.
 *
 * A tree may have no rate yet (NULL, not ₦0): no official Kano schedule was
 * available when the catalogue was loaded (2026-10-25). Such a tree cannot be
 * valued until a rate is entered here or typed on the line.
 *
 * Each row is one crop / tree in one Category (Mature / Immature / Seedling) with its own rate;
 * Type is Cash Crop (priced per m²) or Economic Tree (priced per stand). Loaded from the Kano
 * schedule (user, 2026-11-01), with the per-hectare price and the cost of production per hectare.
 */
class TreeController extends Controller
{
    public const CATEGORIES = SurveyTreeType::TYPES;
    public const STATUSES   = ['Active', 'Inactive'];
    // Farm Assessment tabs (CR 30102026 item 6): Cash Crops = Type Cash Crop, Economic Trees = every other Type.
    public const TABS       = ['economic' => 'Economic Trees', 'cash' => 'Cash Crops'];
    public const CASH_CROP  = SurveyTreeType::CASH_CROP;
    /** Mature, Immature, Seedling. */
    public const MATURITY_ORDER = "CASE maturity WHEN 'Mature' THEN 0 WHEN 'Immature' THEN 1 ELSE 2 END";

    public function index(Request $r)
    {
        $tab = array_key_exists($r->query('tab'), self::TABS) ? $r->query('tab') : 'economic';
        $inTab = fn ($q) => $tab === 'cash'
            ? $q->where('category', self::CASH_CROP)
            : $q->where(fn ($w) => $w->whereNull('category')->orWhere('category', '!=', self::CASH_CROP));

        $filter = function ($q) use ($r, $inTab) {
            $inTab($q);
            if ($term = trim((string) $r->query('q', ''))) {
                $q->where(fn ($w) => $w->where('name', 'like', "%$term%")
                    ->orWhere('local_name', 'like', "%$term%")
                    ->orWhere('botanical_name', 'like', "%$term%"));
            }
            if ($cat = $r->query('category')) $q->where('category', $cat);
            if (in_array($r->query('maturity'), SurveyTreeType::MATURITIES, true)) $q->where('maturity', $r->query('maturity'));
            if ($st = $r->query('status'))    $q->where('status', $st);
            if ($r->query('rate') === 'unset') $q->whereNull('default_unit_price');
        };

        // One table row per tree, folding its Category rows (user, 2026-10-30): pages count trees.
        $treeNames = SurveyTreeType::query()->tap($filter)->select('name')->groupBy('name')->orderBy('name')
            ->paginate(15)->withQueryString();

        // The rows of the trees on this page. "Cases Using" and "Total Valued" come from the case lines, never stored.
        $treeTypes = SurveyTreeType::query()
            ->select('survey_tree_types.*')
            ->withCount('caseTrees as lines_count')
            ->withSum('caseTrees as total_valued', 'line_total')
            ->addSelect(['cases_using' => SurveyCaseTree::query()
                ->selectRaw('COUNT(DISTINCT survey_comp_case_id)')
                ->whereColumn('survey_case_trees.survey_tree_type_id', 'survey_tree_types.id'),
            ])
            ->tap($filter)
            ->whereIn('name', $treeNames->pluck('name')->all() ?: [''])
            ->orderBy('name')->orderByRaw(self::MATURITY_ORDER)
            ->get();

        // Figures for the open tab.
        $tabIds = SurveyTreeType::withTrashed()->tap($inTab)->pluck('id')->all() ?: [0];
        $stats = [
            'types'    => SurveyTreeType::query()->tap($inTab)->count(),
            'active'   => SurveyTreeType::query()->tap($inTab)->where('status', 'Active')->count(),
            'unrated'  => SurveyTreeType::query()->tap($inTab)->where('status', 'Active')->whereNull('default_unit_price')->count(),
            'lines'    => SurveyCaseTree::whereIn('survey_tree_type_id', $tabIds)->count(),
            'valued'   => (float) SurveyCaseTree::whereIn('survey_tree_type_id', $tabIds)->sum('line_total'),
        ];

        // Per-case tree lines live on this page too: pick a monetary case to work on.
        $monetaryCases = SurveyCompCase::where('scheme_type', SurveyProject::SCHEME_MONETARY)
            ->orderByDesc('id')->get(['id', 'case_ref', 'scheme_type']);

        $selectedCase = null;
        if ($caseId = $r->query('case')) {
            $selectedCase = SurveyCompCase::with(['trees' => fn ($t) => $t->orderByDesc('id')])->find($caseId);
        }

        // Catalogue rows for the "add line" select — inactive types are not offered.
        $activeTypes = SurveyTreeType::where('status', 'Active')->orderBy('name')->orderByRaw(self::MATURITY_ORDER)
            ->get(['id', 'name', 'local_name', 'category', 'maturity', 'default_unit_price']);

        return view('survey_module.compensation.trees', compact(
            'treeTypes', 'treeNames', 'stats', 'monetaryCases', 'selectedCase', 'activeTypes', 'tab'
        ));
    }

    /**
     * A crop / tree is added in all three Categories at once (Mature, Immature, Seedling), each
     * with its own rate per unit and per hectare; the cost of production per hectare is shared.
     * A Category it already has is left as it is.
     */
    public function store(Request $r)
    {
        $rules = [
            'name'           => ['required', 'string', 'max:255'],
            'local_name'     => 'nullable|string|max:150',
            'botanical_name' => 'nullable|string|max:255',
            'category'       => ['required', Rule::in(self::CATEGORIES)],
            'cost_per_ha'    => 'nullable|numeric|min:0|max:999999999999',
            'status'         => ['required', Rule::in(self::STATUSES)],
        ];
        foreach (SurveyTreeType::MATURITIES as $m) {
            // Blank = no rate set yet; the calculator refuses the row until there is one.
            $rules['rate.' . $m] = 'nullable|numeric|min:0|max:999999999999';
            $rules['rate_ha.' . $m] = 'nullable|numeric|min:0|max:999999999999';
        }
        $data = $r->validate($rules, ['category.in' => 'Type must be Cash Crop or Economic Tree.']);

        $name = trim(preg_replace('/\s+/', ' ', $data['name']));
        $shared = ['name' => $name, 'category' => $data['category'], 'status' => $data['status'], 'cost_per_ha' => self::blank($data['cost_per_ha'] ?? null)];
        foreach (['local_name', 'botanical_name'] as $k) $shared[$k] = trim((string) ($data[$k] ?? '')) ?: null;

        $added = []; $kept = [];
        DB::connection('sqlsrv')->transaction(function () use ($name, $shared, $data, &$added, &$kept) {
            foreach (SurveyTreeType::MATURITIES as $maturity) {
                // A blank rate means "not set", never ₦0; always sent so the column default (0) cannot apply.
                $row = $shared + ['maturity' => $maturity,
                    'default_unit_price' => self::blank($data['rate'][$maturity] ?? null),
                    'price_per_ha' => self::blank($data['rate_ha'][$maturity] ?? null)];
                $exists = SurveyTreeType::where('name', $name)->where('category', $shared['category'])->where('maturity', $maturity)->exists();
                if ($exists) { $kept[] = $maturity; continue; }
                SurveyTreeType::create($row);
                $added[] = $maturity;
            }
        });

        if (! $added) {
            throw ValidationException::withMessages(['name' => "{$name} is already a {$shared['category']} in every Category. Edit its rows to change them."]);
        }

        return back()->with('success', "{$name} added as " . implode(', ', $added)
            . ($kept ? '; its ' . implode(' and ', $kept) . ' row was already there and is unchanged.' : '.'));
    }

    private static function blank($v)
    {
        return trim((string) $v) === '' ? null : $v;
    }
    public function update(Request $r, SurveyTreeType $treeType)
    {
        $data = $this->validated($r, $treeType);
        $oldName = $treeType->name;
        $oldType = $treeType->category;

        $treeType->update($data);

        // The crop / tree's other Category rows keep the same names, Type and cost of production.
        SurveyTreeType::where('name', $oldName)->where('category', $oldType)->whereKeyNot($treeType->id)
            ->where('maturity', '!=', $treeType->maturity)
            ->update(['name' => $treeType->name, 'local_name' => $treeType->local_name,
                      'botanical_name' => $treeType->botanical_name, 'category' => $treeType->category,
                      'cost_per_ha' => $treeType->cost_per_ha]);

        // Existing case lines keep the price they were valued at — the catalogue
        // price is only a default for new lines.
        return back()->with('success', "Tree type {$treeType->name} updated. Existing case lines keep their recorded prices.");
    }

    public function destroy(SurveyTreeType $treeType)
    {
        $lines = $treeType->caseTrees()->count();

        if ($lines > 0) {
            $cases = $treeType->caseTrees()->distinct()->count('survey_comp_case_id');

            return back()->with('error',
                "{$treeType->name} is used by {$lines} case tree line(s) across {$cases} case(s) and cannot be deleted. Set its status to Inactive instead.");
        }

        $name = $treeType->name;
        $treeType->delete();

        return back()->with('success', "Tree type {$name} deleted.");
    }

    public function storeCaseTree(Request $r, SurveyCompCase $case)
    {
        // Trees are the monetary scheme's only payable item; a land case gets plots, not cash.
        if (! $case->isMonetary()) {
            return back()->with('error',
                "Case {$case->case_ref} is a Land to Land case. Economic trees are monetary-only — allocate plots in Plot Allocation instead.");
        }

        $data = $r->validate([
            'maturity'            => ['required', Rule::in(SurveyTreeType::MATURITIES)],
            'survey_tree_type_id' => ['required', Rule::exists('sqlsrv.survey_tree_types', 'id')->whereNull('deleted_at')],
            'quantity'            => 'required|integer|min:1|max:1000000',
            'unit_price'          => 'nullable|numeric|min:0|max:99999999999',
        ], [
            'maturity.required'            => 'Choose the Category (Mature, Immature or Seedling).',
            'survey_tree_type_id.required' => 'Choose a tree type from the catalogue.',
            'survey_tree_type_id.exists'   => 'That tree type is not in the catalogue.',
            'quantity.min'                 => 'Quantity must be at least 1.',
        ]);

        $type = SurveyTreeType::findOrFail($data['survey_tree_type_id']);

        // The catalogue row is one tree in one Category (Mature / Immature / Seedling), with its own rate;
        // the Category picked on the form must be that row's.
        $maturity = $type->maturity ?: SurveyTreeType::MATURE;
        if ($maturity !== $data['maturity']) {
            return back()->withInput()->with('error', "{$type->name} is listed as {$maturity}, not {$data['maturity']}. Pick the {$data['maturity']} row, or change the Category.");
        }
        $unit = $data['unit_price'] ?? $type->rate();
        if ($unit === null) {
            return back()->with('error',
                "{$type->label} has no rate yet. Enter it in the Farm Assessment catalogue, or type a unit price on the line.");
        }

        // The name is snapshotted so the line still reads correctly if the
        // catalogue entry is later renamed or retired. line_total is deliberately
        // NOT taken from the request — SurveyCaseTree recomputes it on save.
        $line = $case->trees()->create([
            'survey_tree_type_id' => $type->id,
            'tree_type'           => $type->name,
            'quantity'            => (int) $data['quantity'],
            'unit_price'          => $unit,
            'maturity'            => $maturity,
        ]);

        return back()->with('success', sprintf(
            '%s x %s (%s) added to %s — line total %s.',
            $line->quantity, $type->name, $maturity, $case->case_ref, '₦' . number_format((float) $line->line_total, 2)
        ));
    }

    public function destroyCaseTree(SurveyCaseTree $caseTree)
    {
        $label = "{$caseTree->quantity} x {$caseTree->tree_type}";
        $caseTree->delete();

        return back()->with('success', "Removed {$label} from the case.");
    }

    private function validated(Request $r, ?SurveyTreeType $existing = null): array
    {
        $data = $r->validate([
            'name'               => ['required', 'string', 'max:255'],
            'local_name'         => 'nullable|string|max:150',
            'botanical_name'     => 'nullable|string|max:255',
            'category'           => ['required', Rule::in(self::CATEGORIES)],
            'maturity'           => ['required', Rule::in(SurveyTreeType::MATURITIES)],
            // Blank = no rate set yet; the calculator refuses the row until there is one.
            'default_unit_price' => 'nullable|numeric|min:0|max:999999999999',
            'price_per_ha'       => 'nullable|numeric|min:0|max:999999999999',
            'cost_per_ha'        => 'nullable|numeric|min:0|max:999999999999',
            'status'             => ['required', Rule::in(self::STATUSES)],
        ], [
            'category.in'       => 'Type must be Cash Crop or Economic Tree.',
            'maturity.required' => 'Choose the Category: Mature, Immature or Seedling.',
        ]);
        $data['name'] = trim(preg_replace('/\s+/', ' ', $data['name']));

        // One live row per name, Type and Category (the unique index covers live rows only).
        $taken = SurveyTreeType::where('name', $data['name'])->where('category', $data['category'])->where('maturity', $data['maturity'])
            ->when($existing, fn ($q) => $q->where('id', '!=', $existing->id))->exists();
        if ($taken) {
            throw ValidationException::withMessages(['name' => "{$data['name']} ({$data['category']}, {$data['maturity']}) is already in the catalogue."]);
        }

        // A blank rate means "not set", never ₦0. Always sent explicitly: left out
        // of an insert, the column's database default (0) would apply.
        foreach (['default_unit_price', 'price_per_ha', 'cost_per_ha', 'local_name', 'botanical_name'] as $k) {
            $v = $data[$k] ?? null;
            $data[$k] = trim((string) $v) === '' ? null : $v;
        }

        return $data;
    }
}
