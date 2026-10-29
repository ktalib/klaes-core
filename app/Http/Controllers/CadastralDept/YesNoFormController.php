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
            'landUse' => $this->forms->fileLandUse($case),
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
            // Square metres in; hectares (m2 / 10,000) stored (transcript, 2026-10-16: no acres).
            'area_applied_sqm' => 'nullable|numeric|min:0|max:999999999999',
            // The dimensions the area is worked out from (2026-10-28), "46.13 m x 49.52 m; 20.68 m x 10 m".
            'area_dimensions' => 'nullable|string|max:4000',
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

        $data['area_applied_ha'] = isset($data['area_applied_sqm']) && $data['area_applied_sqm'] !== ''
            ? round((float) $data['area_applied_sqm'] / 10000, 4) : null;
        unset($data['area_applied_sqm']);
        if (!\Illuminate\Support\Facades\Schema::connection('sqlsrv')->hasColumn('cadastral_yes_no_forms', 'area_dimensions')) {
            unset($data['area_dimensions']);
        } else {
            $data['area_dimensions'] = trim((string) ($data['area_dimensions'] ?? '')) ?: null;
        }

        // "Other" stores the officer's own words.
        if (($data['q4_purpose'] ?? null) === 'Other') {
            $data['q4_purpose'] = $data['q4_purpose_other'];
        }
        unset($data['q4_purpose_other']);

        $case = CadastralCase::findOrFail($case);

        // Back to the File Movement Card after saving (user, 2026-10-12); the file page when it has none yet.
        $back = $case->fmc && \App\Services\CadastralDept\FileMovementCards::canView(auth()->user())
            ? route('cadastral-dept.cases.fmc.show', $case->id)
            : route('cadastral-dept.cases.show', $case->id);
        // All seven answered: stay on the form, where "Mark done" now shows (change request 26-10-2026:
        // "once they fill it, it should bring Mark done" instead of sending them back to find it).
        $answered = collect(array_keys(CadastralYesNoForm::QUESTIONS))->every(fn ($q) => in_array($data[$q] ?? null, ['Yes', 'No'], true));
        // After saving (user, 2026-10-28): all seven answered goes to the file, where the Yes/No task
        // is marked done with the step's other tasks; not yet answered stays on the form to finish it.
        if ($this->forms->dueNow($case)) {
            $back = $answered ? route('cadastral-dept.cases.show', $case->id) : route('cadastral-dept.cases.yes-no.show', $case->id);
        }

        return $this->attempt(fn () => $this->forms->save($case, $data, auth()->id()), "{$case->case_ref}: Yes/No form saved.", $back);
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
            'areaSqm' => $area['ha'] === null ? null : round($area['ha'] * 10000, 2),
            'qr' => $qr->dataUri(route('cadastral-dept.cases.show', $case->id), 140),
        ]);
    }
}
