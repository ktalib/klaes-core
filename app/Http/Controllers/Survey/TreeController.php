<?php

namespace App\Http\Controllers\Survey;

use App\Http\Controllers\Controller;
use App\Models\Survey\SurveyCaseTree;
use App\Models\Survey\SurveyCompCase;
use App\Models\Survey\SurveyProject;
use App\Models\Survey\SurveyTreeType;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Economic tree valuation catalogue and per-case tree lines.
 *
 * Monetary schemes only: line_total is always quantity x unit_price.
 */
class TreeController extends Controller
{
    public const CATEGORIES = ['Cash Crop', 'Timber', 'Fruit'];
    public const STATUSES   = ['Active', 'Inactive'];

    public function index(Request $r)
    {
        // "Cases Using" and "Total Valued" are derived from the case lines, never stored.
        $q = SurveyTreeType::query()
            ->select('survey_tree_types.*')
            ->withCount('caseTrees as lines_count')
            ->withSum('caseTrees as total_valued', 'line_total')
            ->addSelect(['cases_using' => SurveyCaseTree::query()
                ->selectRaw('COUNT(DISTINCT survey_comp_case_id)')
                ->whereColumn('survey_case_trees.survey_tree_type_id', 'survey_tree_types.id'),
            ]);

        if ($term = trim((string) $r->query('q', ''))) {
            $q->where('name', 'like', "%$term%");
        }
        if ($cat = $r->query('category')) $q->where('category', $cat);
        if ($st = $r->query('status'))    $q->where('status', $st);

        $treeTypes = $q->orderBy('name')->paginate(15)->withQueryString();

        $stats = [
            'types'    => SurveyTreeType::count(),
            'active'   => SurveyTreeType::where('status', 'Active')->count(),
            'lines'    => SurveyCaseTree::count(),
            'valued'   => (float) SurveyCaseTree::sum('line_total'),
        ];

        // Per-case tree lines live on this page too: pick a monetary case to work on.
        $monetaryCases = SurveyCompCase::where('scheme_type', SurveyProject::SCHEME_MONETARY)
            ->orderByDesc('id')->get(['id', 'case_ref', 'scheme_type']);

        $selectedCase = null;
        if ($caseId = $r->query('case')) {
            $selectedCase = SurveyCompCase::with(['trees' => fn ($t) => $t->orderByDesc('id')])->find($caseId);
        }

        // Catalogue rows for the "add line" select — inactive types are not offered.
        $activeTypes = SurveyTreeType::where('status', 'Active')->orderBy('name')
            ->get(['id', 'name', 'category', 'default_unit_price']);

        return view('survey_module.compensation.trees', compact(
            'treeTypes', 'stats', 'monetaryCases', 'selectedCase', 'activeTypes'
        ));
    }

    public function store(Request $r)
    {
        // survey_tree_types.name carries a hard unique index that does NOT exclude
        // soft-deleted rows, so a name freed by a delete cannot simply be re-inserted.
        // Revive the retired entry instead — its historic case lines still point at it.
        $retired = SurveyTreeType::onlyTrashed()->where('name', $r->input('name'))->first();

        if ($retired) {
            $retired->restore();
            $retired->update($this->validated($r, $retired));

            return back()->with('success',
                "Tree type {$retired->name} was previously retired and has been restored with the details you entered.");
        }

        $type = SurveyTreeType::create($this->validated($r));

        return back()->with('success', "Tree type {$type->name} added to the catalogue.");
    }

    public function update(Request $r, SurveyTreeType $treeType)
    {
        $data = $this->validated($r, $treeType);

        $treeType->update($data);

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
                "Case {$case->case_ref} is a Land-for-Land case. Economic trees are monetary-only — allocate plots in Plot Allocation instead.");
        }

        $data = $r->validate([
            'survey_tree_type_id' => ['required', Rule::exists('sqlsrv.survey_tree_types', 'id')->whereNull('deleted_at')],
            'quantity'            => 'required|integer|min:1|max:1000000',
            'unit_price'          => 'nullable|numeric|min:0|max:99999999999',
        ], [
            'survey_tree_type_id.required' => 'Choose a tree type from the catalogue.',
            'survey_tree_type_id.exists'   => 'That tree type is not in the catalogue.',
            'quantity.min'                 => 'Quantity must be at least 1.',
        ]);

        $type = SurveyTreeType::findOrFail($data['survey_tree_type_id']);

        // The name is snapshotted so the line still reads correctly if the
        // catalogue entry is later renamed or retired. line_total is deliberately
        // NOT taken from the request — SurveyCaseTree recomputes it on save.
        $line = $case->trees()->create([
            'survey_tree_type_id' => $type->id,
            'tree_type'           => $type->name,
            'quantity'            => (int) $data['quantity'],
            'unit_price'          => $data['unit_price'] ?? $type->default_unit_price,
        ]);

        return back()->with('success', sprintf(
            '%s x %s added to %s — line total %s.',
            $line->quantity, $type->name, $case->case_ref, '₦' . number_format((float) $line->line_total, 2)
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
        // Deliberately not scoped to whereNull('deleted_at') — the table's unique
        // index covers retired rows too, so the validator must see them or the
        // insert would fail at the database instead of at the form.
        $unique = Rule::unique('sqlsrv.survey_tree_types', 'name');
        if ($existing) $unique->ignore($existing->id);

        return $r->validate([
            'name'               => ['required', 'string', 'max:255', $unique],
            'category'           => ['required', Rule::in(self::CATEGORIES)],
            'default_unit_price' => 'required|numeric|min:0|max:999999999999',
            'status'             => ['required', Rule::in(self::STATUSES)],
        ], [
            'name.unique'   => 'That tree type is already in the catalogue (it may be a retired entry).',
            'category.in'   => 'Category must be Cash Crop, Timber or Fruit.',
        ]);
    }
}
