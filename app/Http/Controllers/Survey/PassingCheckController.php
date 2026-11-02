<?php

namespace App\Http\Controllers\Survey;

use App\Http\Controllers\Controller;
use App\Models\Survey\SurveyorListEntry;
use App\Models\Survey\SurveyPassingCheck as PC;
use App\Services\CadastralDept\Normalise;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

/**
 * GKN > Passing & Checking (Final Survey Plan), CR 30102026 item 21 (user, 2026-10-31): the
 * distances on the final survey plan checked line by line, beacon to beacon. A first version;
 * Survey will give its own fields.
 */
class PassingCheckController extends Controller
{
    public function index(Request $r)
    {
        $term = trim(preg_replace('/\s+/', ' ', (string) $r->query('q', '')));
        $like = '%' . str_replace(['[', '%', '_'], ['[[]', '[%]', '[_]'], $term) . '%';

        return view('survey_module.gkn.passing_checks', [
            'rows'      => PC::query()->with('checker:id,first_name,last_name')
                ->when($term !== '', fn ($q) => $q->where(fn ($w) => $w->where('check_ref', 'like', $like)->orWhere('file_number', 'like', $like)
                    ->orWhere('plan_no', 'like', $like)->orWhere('computation_no', 'like', $like)->orWhere('surveyor_name', 'like', $like)))
                ->when(in_array($r->query('result'), PC::RESULTS, true), fn ($q) => $q->where('result', $r->query('result')))
                ->orderByDesc('checked_at')->orderByDesc('id')->paginate(25)->withQueryString(),
            'term'      => $term,
            'editing'   => $r->query('edit') ? PC::find($r->query('edit')) : null,
            'surveyors' => SurveyorListEntry::query()->where('is_active', true)->orderBy('first_name')->orderBy('surname')->get(['id', 'first_name', 'surname', 'other_name', 'rank']),
        ]);
    }

    public function store(Request $r)
    {
        $pc = PC::create($this->validated($r) + ['check_ref' => PC::nextRef('check_ref', 'PC'), 'checked_by' => Auth::id()]);

        return redirect()->route('survey-module.gkn.passing-checks')->with('success', "{$pc->check_ref} saved: {$pc->result}.");
    }

    public function update(Request $r, PC $passingCheck)
    {
        $passingCheck->update($this->validated($r));

        return redirect()->route('survey-module.gkn.passing-checks')->with('success', "{$passingCheck->check_ref} updated: {$passingCheck->result}.");
    }

    private function validated(Request $r): array
    {
        // Lines left completely blank are not lines.
        $r->merge(['lines' => array_values(array_filter((array) $r->input('lines'), fn ($l) => is_array($l)
            && collect($l)->filter(fn ($v) => trim((string) $v) !== '')->isNotEmpty()))]);

        $d = $r->validate([
            'file_number'              => 'nullable|string|max:100',
            'plan_no'                  => 'nullable|string|max:100',
            'computation_no'           => 'nullable|string|max:30',
            'surveyors_list_id'        => ['nullable', Rule::exists('sqlsrv.surveyors_list', 'id')],
            'tolerance_m'              => 'nullable|numeric|min:0|max:1000',
            'lines'                    => 'required|array|min:1|max:200',
            'lines.*.from'             => 'required|string|max:30',
            'lines.*.to'               => 'required|string|max:30|different:lines.*.from',
            'lines.*.plan_distance'    => 'required|numeric|gt:0|max:1000000',
            'lines.*.measured_distance'=> 'required|numeric|gt:0|max:1000000',
            'result'                   => ['required', Rule::in(PC::RESULTS)],
            'checked_at'               => 'required|date|before_or_equal:today',
            'remarks'                  => 'nullable|string|max:1000',
        ], [
            'lines.required'                     => 'Add at least one line checked.',
            'lines.*.from.required'              => 'Every line needs the beacon it runs from.',
            'lines.*.to.required'                => 'Every line needs the beacon it runs to.',
            'lines.*.plan_distance.required'     => 'Every line needs the distance on the plan.',
            'lines.*.measured_distance.required' => 'Every line needs the distance measured.',
            'lines.*.to.different'               => 'A line runs between two different beacons.',
        ]);

        // Beacon numbers are identifiers: upper case, spacing tidied.
        $beacon = fn ($v) => mb_strtoupper(trim(preg_replace('/\s+/', ' ', (string) $v)));
        $lines = array_map(fn ($l) => [
            'from'              => $beacon($l['from']),
            'to'                => $beacon($l['to']),
            'plan_distance'     => round((float) $l['plan_distance'], 3),
            'measured_distance' => round((float) $l['measured_distance'], 3),
            'difference'        => round((float) $l['measured_distance'] - (float) $l['plan_distance'], 3),
        ], $d['lines']);
        $surveyor = ! empty($d['surveyors_list_id']) ? SurveyorListEntry::find($d['surveyors_list_id']) : null;
        $clean = fn ($v) => ($v = trim(preg_replace('/\s+/u', ' ', (string) $v))) === '' ? null : $v;

        return [
            'file_number'       => $clean($d['file_number'] ?? null),
            'plan_no'           => $clean($d['plan_no'] ?? null),
            'computation_no'    => ($c = $clean($d['computation_no'] ?? null)) ? mb_strtoupper($c) : null,
            'surveyors_list_id' => $surveyor?->id,
            'surveyor_name'     => $surveyor?->full_name,
            'tolerance_m'       => isset($d['tolerance_m']) && $d['tolerance_m'] !== '' ? (float) $d['tolerance_m'] : null,
            'lines'             => $lines,
            'result'            => $d['result'],
            'checked_at'        => $d['checked_at'],
            'remarks'           => Normalise::text($d['remarks'] ?? null) ?: null,
        ];
    }
}
