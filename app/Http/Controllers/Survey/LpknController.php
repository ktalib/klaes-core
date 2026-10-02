<?php

namespace App\Http\Controllers\Survey;

use App\Http\Controllers\Controller;
use App\Models\Survey\SurveyLpkn;
use App\Support\AddressBuilder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * LPKN — layout plan register.
 *
 * Covers what the schema holds: the layout plan number, its size, land use and
 * approval status. The concept note's I-to-S generation, list-of-coordinates
 * form and plan-validation workflow have no columns yet and are deliberately
 * not faked here.
 *
 * Like Misc KN, the slide-open entry form doubles as the edit form (`?edit=`).
 */
class LpknController extends Controller
{
    public const STATUSES  = ['Draft', 'Submitted', 'Under Review', 'Approved', 'Rejected'];
    public const LAND_USES = ['Residential', 'Commercial', 'Industrial', 'Agricultural', 'Institutional', 'Mixed'];

    public function index(Request $r)
    {
        $q = SurveyLpkn::query();

        if ($term = trim((string) $r->query('q', ''))) {
            $q->where(function ($w) use ($term) {
                $w->where('lpkn_number', 'like', "%$term%")
                  ->orWhere('layout_name', 'like', "%$term%")
                  ->orWhere('remarks', 'like', "%$term%")
                  ->orWhere('prop_district', 'like', "%$term%")
                  ->orWhere('prop_district_other', 'like', "%$term%");
            });
        }

        if ($s = $r->query('status'))    $q->where('status', $s);
        if ($u = $r->query('land_use'))  $q->where('land_use', $u);

        $layouts = $q->orderByDesc('id')->paginate(15)->withQueryString();

        $editing = $r->filled('edit') ? SurveyLpkn::find($r->query('edit')) : null;
        $layout  = $editing ?: new SurveyLpkn([
            'status'      => 'Draft',
            'record_date' => now()->toDateString(),
            'prop_state'  => 'Kano',
        ]);

        $stats = [
            'total'    => SurveyLpkn::count(),
            'approved' => SurveyLpkn::where('status', 'Approved')->count(),
            'pending'  => SurveyLpkn::whereIn('status', ['Draft', 'Submitted', 'Under Review'])->count(),
            'plots'    => (int) SurveyLpkn::sum('plot_count'),
            'area'     => (float) SurveyLpkn::sum('area_ha'),
        ];

        return view('survey_module.records.lpkn', compact('layouts', 'layout', 'editing', 'stats'));
    }

    public function store(Request $r)
    {
        $data = $this->validated($r);

        $data['lpkn_number'] = SurveyLpkn::nextRef('lpkn_number', 'LPKN');
        $layout = SurveyLpkn::create($data);

        return redirect()
            ->route('survey-module.records.lpkn')
            ->with('success', "Layout {$layout->lpkn_number} registered.");
    }

    public function update(Request $r, SurveyLpkn $lpkn)
    {
        $lpkn->update($this->validated($r));

        return redirect()
            ->route('survey-module.records.lpkn')
            ->with('success', "Layout {$lpkn->lpkn_number} updated.");
    }

    public function destroy(SurveyLpkn $lpkn)
    {
        // An approved layout has been issued to the public; retire it by status.
        if ($lpkn->status === 'Approved') {
            return redirect()
                ->route('survey-module.records.lpkn')
                ->with('error', "{$lpkn->lpkn_number} is approved and cannot be deleted. Set its status to Rejected instead.");
        }

        $ref = $lpkn->lpkn_number;
        $lpkn->delete();

        return redirect()
            ->route('survey-module.records.lpkn')
            ->with('success', "Layout {$ref} deleted.");
    }

    /**
     * lpkn_number is generated, never posted.
     *
     * @return array<string,mixed>
     */
    private function validated(Request $r): array
    {
        $rules = [
            'layout_name' => 'required|string|max:255',
            'plot_count'  => 'nullable|integer|min:0|max:100000',
            'area_ha'     => 'nullable|numeric|min:0|max:999999.99',
            'land_use'    => ['nullable', Rule::in(self::LAND_USES)],
            'record_date' => 'nullable|date',
            'status'      => ['required', Rule::in(self::STATUSES)],
            'remarks'     => 'nullable|string|max:4000',
        ] + AddressBuilder::rules('prop_');

        return $r->validate($rules, [
            'layout_name.required'            => 'The layout name is required.',
            'status.in'                       => 'Choose one of the listed statuses.',
            'plot_count.integer'              => 'The number of plots must be a whole number.',
            'prop_district.required'          => 'The district is required.',
            'prop_district_other.required_if' => 'Please specify the district.',
            'prop_street_other.required_if'   => 'Please specify the street.',
            'prop_lga.required'               => 'The LGA is required.',
        ]);
    }
}
