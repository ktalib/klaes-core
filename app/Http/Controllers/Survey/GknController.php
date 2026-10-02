<?php

namespace App\Http\Controllers\Survey;

use App\Http\Controllers\Controller;
use App\Models\Survey\SurveyFileMovement;
use App\Models\Survey\SurveyGknRecord;
use App\Support\AddressBuilder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

/**
 * Government Known Numbers: land parcels and file movement tracking.
 *
 * GKN numbers are normally generated in the "GKN-YYYY-000" series, but the
 * registry also carries legacy numbers, so a manual override is allowed as long
 * as it is unique. The parcel's location uses the shared address builder, so
 * "District, LGA, State" is composed the same way as everywhere else.
 */
class GknController extends Controller
{
    public const STATUSES  = ['Active', 'Survey', 'Deeded', 'Closed'];
    public const LAND_USES = ['Residential', 'Commercial', 'Agricultural', 'Institutional', 'Mixed'];

    public const MOVE_IN_TRANSIT = 'In Transit';
    public const MOVE_RECEIVED   = 'Received';

    /* ------------------------------ Government lands ------------------------------ */

    public function index(Request $r)
    {
        $q = SurveyGknRecord::query();

        if ($term = trim((string) $r->query('q', ''))) {
            $q->where(function ($w) use ($term) {
                $w->where('gkn_number', 'like', "%$term%")
                  ->orWhere('title', 'like', "%$term%")
                  ->orWhere('survey_officer', 'like', "%$term%")
                  ->orWhere('prop_district', 'like', "%$term%")
                  ->orWhere('prop_district_other', 'like', "%$term%")
                  ->orWhere('prop_lga', 'like', "%$term%");
            });
        }
        if ($st = $r->query('status'))   $q->where('status', $st);
        if ($lu = $r->query('land_use')) $q->where('land_use', $lu);

        $records = $q->orderByDesc('id')->paginate(15)->withQueryString();

        $stats = [
            'total'  => SurveyGknRecord::count(),
            'active' => SurveyGknRecord::where('status', 'Active')->count(),
            'survey' => SurveyGknRecord::where('status', 'Survey')->count(),
            'area'   => (float) SurveyGknRecord::sum('area_ha'),
        ];

        return view('survey_module.gkn.lands', compact('records', 'stats'));
    }

    public function create(Request $r)
    {
        $gkn = new SurveyGknRecord([
            'status'      => 'Active',
            'land_use'    => 'Residential',
            'record_date' => Carbon::today(),
            'prop_state'  => 'Kano',
        ]);

        return view('survey_module.gkn.register', [
            'gkn'       => $gkn,
            'suggested' => SurveyGknRecord::nextRef('gkn_number', 'GKN'),
        ]);
    }

    public function store(Request $r)
    {
        $data = $this->validated($r);

        // Blank means "number it for me"; anything typed is kept, having already
        // been checked for uniqueness.
        $data['gkn_number'] = trim((string) ($data['gkn_number'] ?? ''))
            ?: SurveyGknRecord::nextRef('gkn_number', 'GKN');

        $gkn = SurveyGknRecord::create($data);

        return redirect()
            ->route('survey-module.gkn.lands')
            ->with('success', "{$gkn->gkn_number} registered at {$gkn->property_location}.");
    }

    public function edit(SurveyGknRecord $gkn)
    {
        return view('survey_module.gkn.register', [
            'gkn'       => $gkn,
            'suggested' => $gkn->gkn_number,
        ]);
    }

    public function update(Request $r, SurveyGknRecord $gkn)
    {
        $data = $this->validated($r, $gkn);

        // An existing parcel keeps its number if the box is cleared.
        $data['gkn_number'] = trim((string) ($data['gkn_number'] ?? '')) ?: $gkn->gkn_number;

        $gkn->update($data);

        return redirect()
            ->route('survey-module.gkn.lands')
            ->with('success', "{$gkn->gkn_number} updated.");
    }

    public function destroy(SurveyGknRecord $gkn)
    {
        // The file is still out of the office; deleting the parcel would orphan it.
        $inTransit = SurveyFileMovement::where('file_ref', $gkn->gkn_number)
            ->where('status', self::MOVE_IN_TRANSIT)
            ->count();

        if ($inTransit) {
            return back()->with('error',
                "{$gkn->gkn_number} has $inTransit file movement(s) still in transit. Receive them first.");
        }

        $number = $gkn->gkn_number;
        $gkn->delete();

        return back()->with('success', "$number deleted.");
    }

    /**
     * @param  SurveyGknRecord|null  $existing  the row being edited, excluded from the uniqueness check
     */
    private function validated(Request $r, ?SurveyGknRecord $existing = null): array
    {
        $rules = [
            // Unique against the sqlsrv table, including soft-deleted rows —
            // the database index does not ignore them either.
            'gkn_number'     => [
                'nullable', 'string', 'max:50',
                Rule::unique('sqlsrv.survey_gkn_records', 'gkn_number')->ignore($existing?->id),
            ],
            'title'          => 'required|string|max:255',
            'area_ha'        => 'nullable|numeric|min:0|max:9999999999',
            'land_use'       => ['required', Rule::in(self::LAND_USES)],
            'survey_officer' => 'nullable|string|max:255',
            'coordinates'    => 'nullable|string|max:255',
            'record_date'    => 'nullable|date',
            'status'         => ['required', Rule::in(self::STATUSES)],
            'remarks'        => 'nullable|string|max:4000',
        ] + AddressBuilder::rules('prop_');

        return $r->validate($rules, [
            'gkn_number.unique'               => 'That GKN number is already on the register.',
            'prop_district.required'          => 'The district is required.',
            'prop_district_other.required_if' => 'Please specify the district.',
            'prop_street_other.required_if'   => 'Please specify the street.',
            'prop_lga.required'               => 'The LGA is required.',
        ]);
    }

    /* -------------------------------- File tracking -------------------------------- */

    public function tracking(Request $r)
    {
        $q = SurveyFileMovement::query();

        if ($term = trim((string) $r->query('q', ''))) {
            $q->where(function ($w) use ($term) {
                $w->where('file_ref', 'like', "%$term%")
                  ->orWhere('from_office', 'like', "%$term%")
                  ->orWhere('to_office', 'like', "%$term%")
                  ->orWhere('purpose', 'like', "%$term%");
            });
        }
        if ($st = $r->query('status')) $q->where('status', $st);

        $movements = $q->orderByDesc('sent_at')->orderByDesc('id')->paginate(15)->withQueryString();

        $stats = [
            'total'      => SurveyFileMovement::count(),
            'in_transit' => SurveyFileMovement::where('status', self::MOVE_IN_TRANSIT)->count(),
            'received'   => SurveyFileMovement::where('status', self::MOVE_RECEIVED)->count(),
            'files'      => SurveyFileMovement::distinct()->count('file_ref'),
        ];

        // Offered as datalist hints so a movement can be logged against a parcel.
        $refs = SurveyGknRecord::orderByDesc('id')->limit(100)->pluck('gkn_number');

        return view('survey_module.gkn.tracking', compact('movements', 'stats', 'refs'));
    }

    public function storeMovement(Request $r)
    {
        $data = $r->validate([
            'file_ref'    => 'required|string|max:100',
            'from_office' => 'required|string|max:255',
            'to_office'   => 'required|string|max:255|different:from_office',
            'purpose'     => 'nullable|string|max:255',
            'sent_at'     => 'nullable|date',
            'remarks'     => 'nullable|string|max:4000',
        ], [
            'to_office.different' => 'A file cannot be sent to the office it is leaving.',
        ]);

        // A file lives in one place at a time: finish the open leg first.
        $open = SurveyFileMovement::where('file_ref', $data['file_ref'])
            ->where('status', self::MOVE_IN_TRANSIT)
            ->first();

        if ($open) {
            return back()
                ->withInput()
                ->with('error', "{$open->file_ref} is already in transit to {$open->to_office}. Mark it received first.");
        }

        $data['sent_at'] = !empty($data['sent_at']) ? Carbon::parse($data['sent_at']) : Carbon::now();
        $data['status']  = self::MOVE_IN_TRANSIT;

        $movement = SurveyFileMovement::create($data);

        return redirect()
            ->route('survey-module.gkn.tracking')
            ->with('success', "{$movement->file_ref} logged out to {$movement->to_office}.");
    }

    public function receiveMovement(Request $r, SurveyFileMovement $movement)
    {
        if ($movement->status === self::MOVE_RECEIVED) {
            return back()->with('error',
                "{$movement->file_ref} was already received on "
                . optional($movement->received_at)->format('Y-m-d H:i') . '.');
        }

        $data = $r->validate([
            'received_at' => 'nullable|date',
            'remarks'     => 'nullable|string|max:4000',
        ]);

        $movement->update([
            'status'      => self::MOVE_RECEIVED,
            'received_at' => !empty($data['received_at']) ? Carbon::parse($data['received_at']) : Carbon::now(),
            'remarks'     => ($data['remarks'] ?? null) ?: $movement->remarks,
        ]);

        return redirect()
            ->route('survey-module.gkn.tracking')
            ->with('success', "{$movement->file_ref} received at {$movement->to_office}.");
    }
}
