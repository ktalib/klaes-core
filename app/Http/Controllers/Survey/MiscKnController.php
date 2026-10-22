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

    /**
     * The Misc KN page lists the Misc KN files on the file indexing table
     * (user, 2026-10-21): numbered "MISC KN 818" or "MISC-KN-5". Read-only;
     * the files are indexed and edited in File Indexing. Decommissioned files
     * stay listed, flagged.
     */
    public function index(Request $r)
    {
        $live = fn ($q) => $q->where('file_number', 'like', 'MISC%KN%')
            ->where(fn ($w) => $w->whereNull('is_deleted')->orWhere('is_deleted', 0));
        $db = \Illuminate\Support\Facades\DB::connection('sqlsrv');

        $q = $live($db->table('file_indexings'));
        if ($term = trim((string) $r->query('q', ''))) {
            $q->where(fn ($w) => $w->where('file_number', 'like', "%$term%")
                ->orWhere('file_title', 'like', "%$term%")
                ->orWhere('district', 'like', "%$term%")
                ->orWhere('lga', 'like', "%$term%")
                ->orWhere('plot_number', 'like', "%$term%"));
        }
        if ($lga = trim((string) $r->query('lga', ''))) {
            $q->where('lga', $lga);
        }

        $files = $q->orderByDesc('id')
            ->select(['id', 'file_number', 'file_title', 'plot_number', 'district', 'lga', 'land_use_type',
                      'registry', 'created_at', 'created_by', 'is_decommissioned'])
            ->paginate(15)->withQueryString();

        $stats = [
            'total'          => $live($db->table('file_indexings'))->count(),
            'this_month'     => $live($db->table('file_indexings'))->where('created_at', '>=', now()->startOfMonth())->count(),
            'lgas'           => $live($db->table('file_indexings'))->whereNotNull('lga')->where('lga', '<>', '')->distinct()->count('lga'),
            'decommissioned' => $live($db->table('file_indexings'))->where('is_decommissioned', 1)->count(),
        ];
        $lgas = $live($db->table('file_indexings'))->whereNotNull('lga')->where('lga', '<>', '')
            ->distinct()->orderBy('lga')->pluck('lga');

        return view('survey_module.records.misc', [
            'files'        => $files,
            'stats'        => $stats,
            'lgas'         => $lgas,
            // The row opens the file in the File Digital Library (filearchive.*).
            'canViewLibrary' => (bool) optional($r->user())->canDo('File Digital Library - Doc-WARE', 'view'),
        ]);
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
