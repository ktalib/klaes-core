<?php

namespace App\Http\Controllers\Survey;

use App\Http\Controllers\Controller;
use App\Models\Survey\SurveyLpkn;
use App\Models\Survey\SurveyLpknCoordinate;
use App\Support\AddressBuilder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * LPKN — layout plan register.
 *
 * Layout registration, survey instructions, reports and beacon coordinates.
 *
 * Like Misc KN, the slide-open entry form doubles as the edit form (`?edit=`).
 */
class LpknController extends Controller
{
    public const STATUSES  = ['Draft', 'Submitted', 'Under Review', 'Approved', 'Rejected'];
    public const LAND_USES = ['Residential', 'Commercial', 'Industrial', 'Agricultural', 'Institutional', 'Mixed'];

    public const SECTIONS = [
        'instruction' => 'Instruction to Survey',
        'report' => 'Surveyors Report',
        'coordinates' => 'List of Coordinates',
        'observations' => 'Computation Field Observations',
    ];

    public function section(Request $request, string $section)
    {
        abort_unless(isset(self::SECTIONS[$section]), 404);
        $layouts = SurveyLpkn::orderBy('lpkn_number')->get(['id', 'lpkn_number', 'layout_name']);
        $layout = $request->filled('layout') ? SurveyLpkn::findOrFail($request->query('layout')) : null;
        $coordinates = $layout
            ? SurveyLpknCoordinate::where('survey_lpkn_id', $layout->id)->orderBy('sort_order')->orderBy('id')->get()
            : collect();
        $legs = [];
        if ($section === 'observations') {
            for ($i = 0; $i < $coordinates->count() - 1; $i++) {
                $from = $coordinates[$i];
                $to = $coordinates[$i + 1];
                $measurement = self::computeLeg($from->northing, $from->easting, $to->northing, $to->easting);
                $legs[] = ['from' => $from->beacon_id, 'to' => $to->beacon_id] + $measurement;
            }
        }

        return view('survey_module.records.lpkn_section', compact('section', 'layouts', 'layout', 'coordinates', 'legs'));
    }

    /** Grid bearing clockwise from north; null coordinates cannot form a leg. */
    public static function computeLeg($fromNorth, $fromEast, $toNorth, $toEast): array
    {
        if ($fromNorth === null || $fromEast === null || $toNorth === null || $toEast === null) {
            return ['distance' => null, 'bearing' => null];
        }
        $dn = (float) $toNorth - (float) $fromNorth;
        $de = (float) $toEast - (float) $fromEast;
        $distance = hypot($dn, $de);

        return [
            'distance' => $distance,
            'bearing' => $distance > 0 ? fmod(rad2deg(atan2($de, $dn)) + 360, 360) : null,
        ];
    }

    public function saveInstruction(Request $request, SurveyLpkn $lpkn)
    {
        $lpkn->update($request->validate([
            'its_number' => 'required|string|max:50',
            'its_issued_at' => 'required|date',
            'its_recipient' => 'required|string|max:255',
            'its_issued_by' => 'required|string|max:255',
            'its_instructions' => 'required|string|max:20000',
        ]));

        return redirect()->route('survey-module.records.lpkn.instruction', ['layout' => $lpkn->id])
            ->with('success', 'Instruction to Survey saved.');
    }

    public function saveReport(Request $request, SurveyLpkn $lpkn)
    {
        $lpkn->update($request->validate([
            'report_surveyor' => 'required|string|max:255',
            'report_date' => 'required|date',
            'surveyor_report' => 'required|string|max:20000',
        ]));

        return redirect()->route('survey-module.records.lpkn.report', ['layout' => $lpkn->id])
            ->with('success', 'Surveyors Report saved.');
    }

    public function saveCoordinate(Request $request, SurveyLpkn $lpkn)
    {
        $data = $request->validate([
            'beacon_id' => ['required', 'string', 'max:50', Rule::unique('sqlsrv.survey_lpkn_coordinates', 'beacon_id')
                ->where('survey_lpkn_id', $lpkn->id)->whereNull('deleted_at')],
            'sort_order' => 'required|integer|min:0|max:1000000',
            'northing' => 'required|numeric|between:-999999999999.999,999999999999.999',
            'easting' => 'required|numeric|between:-999999999999.999,999999999999.999',
            'elevation' => 'nullable|numeric|between:-999999999999.999,999999999999.999',
            'remarks' => 'nullable|string|max:500',
        ]);
        $data['survey_lpkn_id'] = $lpkn->id;
        SurveyLpknCoordinate::create($data);

        return redirect()->route('survey-module.records.lpkn.coordinates', ['layout' => $lpkn->id])
            ->with('success', 'Coordinate saved.');
    }

    public function deleteCoordinate(SurveyLpkn $lpkn, SurveyLpknCoordinate $coordinate)
    {
        abort_unless((int) $coordinate->survey_lpkn_id === (int) $lpkn->id, 404);
        $coordinate->delete();

        return redirect()->route('survey-module.records.lpkn.coordinates', ['layout' => $lpkn->id])
            ->with('success', 'Coordinate removed.');
    }

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
