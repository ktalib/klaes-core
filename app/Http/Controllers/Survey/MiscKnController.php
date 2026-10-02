<?php

namespace App\Http\Controllers\Survey;

use App\Http\Controllers\Controller;
use App\Models\Survey\SurveyMiscKn;
use App\Support\AddressBuilder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Miscellaneous cadastral notes — boundary observations, disputes and survey
 * notes that belong to no single GKN or case file but still need a reference,
 * an owner and a location.
 *
 * The register is a single page: the prototype's slide-open entry form doubles
 * as the edit form. `?edit=<id>` reopens it pre-filled, which keeps one
 * address builder on the page instead of one per row.
 */
class MiscKnController extends Controller
{
    /** The categories the register accepts. */
    public const CATEGORIES = ['Boundary', 'Dispute', 'Survey Note', 'Other'];

    public function index(Request $r)
    {
        $q = SurveyMiscKn::query();

        if ($term = trim((string) $r->query('q', ''))) {
            $q->where(function ($w) use ($term) {
                $w->where('ref_no', 'like', "%$term%")
                  ->orWhere('title', 'like', "%$term%")
                  ->orWhere('linked_ref', 'like', "%$term%")
                  ->orWhere('officer', 'like', "%$term%")
                  ->orWhere('prop_district', 'like', "%$term%")
                  ->orWhere('prop_district_other', 'like', "%$term%");
            });
        }

        if ($c = $r->query('category')) $q->where('category', $c);

        $notes = $q->orderByDesc('id')->paginate(15)->withQueryString();

        // Editing reuses the inline form rather than a separate page.
        $editing = $r->filled('edit') ? SurveyMiscKn::find($r->query('edit')) : null;
        $note    = $editing ?: new SurveyMiscKn([
            'category'    => 'Survey Note',
            'record_date' => now()->toDateString(),
            'officer'     => optional($r->user())->name,
            'prop_state'  => 'Kano',
        ]);

        $stats = [
            'total'      => SurveyMiscKn::count(),
            'disputes'   => SurveyMiscKn::where('category', 'Dispute')->count(),
            'boundary'   => SurveyMiscKn::where('category', 'Boundary')->count(),
            'this_month' => SurveyMiscKn::whereBetween('record_date', [
                                now()->startOfMonth()->toDateString(),
                                now()->endOfMonth()->toDateString(),
                            ])->count(),
        ];

        return view('survey_module.records.misc', compact('notes', 'note', 'editing', 'stats'));
    }

    public function store(Request $r)
    {
        $data = $this->validated($r);

        $data['ref_no'] = SurveyMiscKn::nextRef('ref_no', 'MKN');
        $note = SurveyMiscKn::create($data);

        return redirect()
            ->route('survey-module.records.misc')
            ->with('success', "Note {$note->ref_no} recorded.");
    }

    public function update(Request $r, SurveyMiscKn $misc)
    {
        $misc->update($this->validated($r));

        return redirect()
            ->route('survey-module.records.misc')
            ->with('success', "Note {$misc->ref_no} updated.");
    }

    public function destroy(SurveyMiscKn $misc)
    {
        $ref = $misc->ref_no;
        $misc->delete();

        return redirect()
            ->route('survey-module.records.misc')
            ->with('success', "Note {$ref} deleted.");
    }

    /**
     * ref_no is never accepted from the client — it is generated on create and
     * immutable afterwards.
     *
     * @return array<string,mixed>
     */
    private function validated(Request $r): array
    {
        $rules = [
            'title'       => 'required|string|max:255',
            'category'    => ['required', Rule::in(self::CATEGORIES)],
            'linked_ref'  => 'nullable|string|max:100',
            'record_date' => 'nullable|date',
            'officer'     => 'nullable|string|max:255',
            'details'     => 'nullable|string|max:4000',
        ] + AddressBuilder::rules('prop_');

        return $r->validate($rules, [
            'title.required'                  => 'A note title is required.',
            'category.in'                     => 'Choose one of the listed categories.',
            'prop_district.required'          => 'The district is required.',
            'prop_district_other.required_if' => 'Please specify the district.',
            'prop_street_other.required_if'   => 'Please specify the street.',
            'prop_lga.required'               => 'The LGA is required.',
        ]);
    }
}
