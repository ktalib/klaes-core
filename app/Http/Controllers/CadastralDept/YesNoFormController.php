<?php

namespace App\Http\Controllers\CadastralDept;

use App\Http\Controllers\Controller;
use App\Http\Controllers\InstrumentWorkflow\Concerns\RunsWorkflowActions;
use App\Models\CadastralDept\CadastralCase;
use App\Models\CadastralDept\CadastralYesNoForm;
use App\Services\CadastralDept\WorkflowDefinition;
use App\Services\CadastralDept\YesNoForms;
use App\Services\DocumentQr\QrRenderer;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** The Yes/No form ("Report on Application – Cadastral Department") at AD Cad Report. */
class YesNoFormController extends Controller
{
    use RunsWorkflowActions;

    public function __construct(private YesNoForms $forms)
    {
    }

    public function show(int $case, WorkflowDefinition $def)
    {
        $case = CadastralCase::findOrFail($case);
        $form = $this->forms->forCase($case);

        return view('cadastral_dept.yes_no.show', [
            'def' => $def,
            'case' => $case,
            'form' => $form,
            'installed' => YesNoForms::installed(),
            'editable' => YesNoForms::installed() && $this->forms->dueNow($case)
                && !in_array(YesNoForms::TASK, $case->doneTaskKeys($case->current_step), true),
            'refs' => $this->forms->refs($case, $form),
            'area' => $this->forms->appliedArea($case, $form),
        ]);
    }

    public function save(Request $request, int $case)
    {
        $yn = ['nullable', Rule::in(['Yes', 'No'])];
        $data = $request->validate([
            'lkn_no' => 'nullable|string|max:100',
            'sltr_no' => 'nullable|string|max:100',
            'govt_item_no' => 'nullable|string|max:100',
            'sit_no' => 'nullable|string|max:100',
            'q1_plan_sufficient' => $yn,
            'q2_ground_open' => $yn,
            'q2_overlapping_title' => 'nullable|required_if:q2_ground_open,No|string|max:500',
            'q3_beaconed' => $yn,
            'q3_tracing_no' => 'nullable|required_if:q3_beaconed,Yes|string|max:100',
            'q3_deposition_plan_no' => 'nullable|string|max:100',
            'q3_unapproved_town_plan_no' => 'nullable|string|max:100',
            'q3_layout_no' => 'nullable|string|max:100',
            'q3_separate_survey' => array_merge($yn, ['required_if:q3_beaconed,No']),
            'q4_town_plan' => $yn,
            'q4_town_plan_no' => 'nullable|required_if:q4_town_plan,Yes|string|max:100',
            'q4_shape_agrees' => array_merge($yn, ['required_if:q4_town_plan,Yes']),
            'q4_purpose' => ['nullable', 'required_if:q4_town_plan,Yes', Rule::in(array_merge(CadastralYesNoForm::PURPOSES, ['Other']))],
            'q4_purpose_other' => 'nullable|required_if:q4_purpose,Other|string|max:100',
            'q4_area_for_purpose' => array_merge($yn, ['required_if:q4_town_plan,Yes']),
            'q5_previous_title' => $yn,
            'q5_details' => 'nullable|required_if:q5_previous_title,Yes|string|max:1000',
            'q6_railway' => $yn,
            'q7_trunk_road' => $yn,
            'area_applied_ha' => 'nullable|numeric|min:0|max:99999999',
        ], [
            'q2_overlapping_title.required_if' => 'Q2 is No: say what title or application lies over the land.',
            'q3_tracing_no.required_if' => 'Q3 is Yes: give the Tracing No.',
            'q3_separate_survey.required_if' => 'Q3 is No: say whether a separate survey is required.',
            'q4_town_plan_no.required_if' => 'Q4 is Yes: give the Town Plan No.',
            'q4_shape_agrees.required_if' => 'Q4 is Yes: say whether the shape agrees with the Town Plan.',
            'q4_purpose.required_if' => 'Q4 is Yes: choose the purpose applied for.',
            'q4_purpose_other.required_if' => 'Q4(c): specify the other purpose.',
            'q4_area_for_purpose.required_if' => 'Q4 is Yes: say whether the Town Plan shows the area for that purpose.',
            'q5_details.required_if' => 'Q5 is Yes: give the details.',
        ]);

        // "Other" stores the officer's own words.
        if (($data['q4_purpose'] ?? null) === 'Other') {
            $data['q4_purpose'] = $data['q4_purpose_other'];
        }
        unset($data['q4_purpose_other']);

        $case = CadastralCase::findOrFail($case);

        return $this->attempt(fn () => $this->forms->save($case, $data, auth()->id()), 'Yes/No form saved.');
    }

    /** The official print, its blanks filled; the QR opens the file's page in KLAES. */
    public function print(int $case, QrRenderer $qr)
    {
        $case = CadastralCase::findOrFail($case);
        $form = $this->forms->forCase($case);
        if ($form) {
            $this->forms->countPrint($form);
        }
        $area = $this->forms->appliedArea($case, $form);

        return view('cadastral_dept.yes_no.print', [
            'case' => $case,
            'form' => $form,
            'refs' => $this->forms->refs($case, $form),
            'areaHa' => $area['ha'],
            'areaAcres' => $area['ha'] === null ? null : round($area['ha'] * CadastralYesNoForm::ACRES_PER_HECTARE_AS_PRINTED, 4),
            'qr' => $qr->dataUri(route('cadastral-dept.cases.show', $case->id), 140),
        ]);
    }
}
