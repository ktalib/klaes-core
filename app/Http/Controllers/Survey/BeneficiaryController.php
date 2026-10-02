<?php

namespace App\Http\Controllers\Survey;

use App\Http\Controllers\Controller;
use App\Models\Survey\SurveyBeneficiary;
use App\Models\Survey\SurveyCompCase;
use App\Models\Survey\SurveyOpRecord;
use App\Support\AddressBuilder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Farmers and landowners attached to compensation cases.
 *
 * One page does the lot: the list plus an inline panel that doubles as the
 * create and the edit form, so editing never leaves the register.
 */
class BeneficiaryController extends Controller
{
    public const STATUSES = ['Verified', 'Pending', 'Review'];

    public function index(Request $r)
    {
        return view('survey_module.compensation.beneficiaries', $this->pageData($r));
    }

    public function store(Request $r)
    {
        $data = $this->validated($r);

        $beneficiary = SurveyBeneficiary::create($data);

        return redirect()
            ->route('survey-module.compensation.beneficiaries')
            ->with('success', "Beneficiary {$beneficiary->full_name} added at {$beneficiary->person_address}.");
    }

    /** Same page, with the inline panel opened on this record. */
    public function edit(Request $r, SurveyBeneficiary $beneficiary)
    {
        return view('survey_module.compensation.beneficiaries', $this->pageData($r, $beneficiary));
    }

    public function update(Request $r, SurveyBeneficiary $beneficiary)
    {
        $beneficiary->update($this->validated($r));

        return redirect()
            ->route('survey-module.compensation.beneficiaries')
            ->with('success', "Beneficiary {$beneficiary->full_name} updated.");
    }

    public function destroy(SurveyBeneficiary $beneficiary)
    {
        // An issued or drafted OP names this person; deleting them would leave
        // that permit pointing at nothing.
        if ($n = SurveyOpRecord::where('survey_beneficiary_id', $beneficiary->id)->count()) {
            return back()->with('error',
                "{$beneficiary->full_name} is named on {$n} occupancy permit record(s) and cannot be deleted. "
                . 'Remove those OP records first, or set the beneficiary status to Review instead.');
        }

        $name = $beneficiary->full_name;
        $beneficiary->delete();

        return back()->with('success', "Beneficiary {$name} deleted.");
    }

    /* ------------------------------------------------------------------ */

    /** The list, its filters and the inline form's model. */
    private function pageData(Request $r, ?SurveyBeneficiary $editing = null): array
    {
        $q = SurveyBeneficiary::query()->with('case.project');

        if ($term = trim((string) $r->query('q', ''))) {
            $q->where(function ($w) use ($term) {
                $w->where('full_name', 'like', "%$term%")
                  ->orWhere('phone', 'like', "%$term%")
                  ->orWhere('nin', 'like', "%$term%")
                  ->orWhere('addr_district', 'like', "%$term%")
                  ->orWhere('addr_district_other', 'like', "%$term%")
                  ->orWhereHas('case', fn ($c) => $c->where('case_ref', 'like', "%$term%"));
            });
        }
        if ($st = $r->query('status')) $q->where('status', $st);
        if ($c  = $r->query('case'))   $q->where('survey_comp_case_id', $c);

        $beneficiaries = $q->orderByDesc('id')->paginate(15)->withQueryString();

        $stats = [
            'total'    => SurveyBeneficiary::count(),
            'verified' => SurveyBeneficiary::where('status', 'Verified')->count(),
            'pending'  => SurveyBeneficiary::where('status', 'Pending')->count(),
            'linked'   => SurveyBeneficiary::whereNotNull('survey_comp_case_id')->count(),
        ];

        return [
            'beneficiaries' => $beneficiaries,
            'stats'         => $stats,
            'cases'         => SurveyCompCase::with('project')->orderByDesc('id')->get(),
            // The inline panel edits this record, or creates a new one.
            'beneficiary'   => $editing ?? new SurveyBeneficiary([
                'status'     => 'Pending',
                'addr_state' => 'Kano',
            ]),
        ];
    }

    private function validated(Request $r): array
    {
        $rules = [
            'survey_comp_case_id' => ['nullable', Rule::exists('sqlsrv.survey_comp_cases', 'id')->whereNull('deleted_at')],
            'full_name'           => 'required|string|max:255',
            'phone'               => 'nullable|string|max:50',
            'nin'                 => 'nullable|string|max:20',
            'status'              => ['required', Rule::in(self::STATUSES)],
            'bank_name'           => 'nullable|string|max:255',
            'account_name'        => 'nullable|string|max:255',
            'account_number'      => 'nullable|string|max:50',
        ] + AddressBuilder::rules('addr_');

        return $r->validate($rules, [
            'full_name.required'              => "The beneficiary's full name is required.",
            'survey_comp_case_id.exists'      => 'That compensation case does not exist.',
            'addr_district.required'          => 'The district is required.',
            'addr_district_other.required_if' => 'Please specify the district.',
            'addr_street_other.required_if'   => 'Please specify the street.',
            'addr_lga.required'               => 'The LGA is required.',
            'addr_state.required'             => 'The state is required.',
        ]);
    }
}
