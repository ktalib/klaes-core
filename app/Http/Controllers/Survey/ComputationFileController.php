<?php

namespace App\Http\Controllers\Survey;

use App\Http\Controllers\Controller;
use App\Models\Survey\SurveyComputationFile as CF;
use App\Models\Survey\SurveyIts;
use App\Models\Survey\SurveyorListEntry;
use App\Services\Survey\ComputationNumbers;
use App\Services\CadastralDept\Normalise;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

/**
 * Survey > GKN > Survey Management > Computation Files (Field Jackets), CR 30102026 items 12-14
 * (user, 2026-10-31):
 *   Government - commissioned here by the Survey Department, numbered LPKN-<serial> (no year, user 2026-11-02);
 *   Private    - commissioned by Cadastral (cadastral_field_jackets), numbered LPPL-<year>-<serial>,
 *                listed here read-only.
 */
class ComputationFileController extends Controller
{
    public function index(Request $r)
    {
        $tab = $r->query('tab') === 'private' ? 'private' : 'government';
        $term = trim(preg_replace('/\s+/', ' ', (string) $r->query('q', '')));
        $like = '%' . str_replace(['[', '%', '_'], ['[[]', '[%]', '[_]'], $term) . '%';

        if ($tab === 'private') {
            $rows = Schema::connection('sqlsrv')->hasTable('cadastral_field_jackets')
                ? \App\Models\CadastralDept\CadastralFieldJacket::query()->with(['surveyor', 'job'])
                    ->when($term !== '', fn ($q) => $q->where(fn ($w) => $w->where('lppl_no', 'like', $like)->orWhere('field_jacket_no', 'like', $like)
                        ->orWhere('file_number', 'like', $like)->orWhere('file_title', 'like', $like)))
                    ->orderByDesc('commissioned_at')->orderByDesc('id')->paginate(25)->withQueryString()
                : new \Illuminate\Pagination\LengthAwarePaginator([], 0, 25);

            return view('survey_module.gkn.computation_files', compact('tab', 'rows', 'term'));
        }

        $rows = CF::query()->with('commissioner:id,first_name,last_name')
            ->when($term !== '', fn ($q) => $q->where(fn ($w) => $w->where('computation_no', 'like', $like)->orWhere('file_number', 'like', $like)
                ->orWhere('file_title', 'like', $like)->orWhere('surveyor_name', 'like', $like)))
            ->orderByDesc('commissioned_at')->orderByDesc('id')->paginate(25)->withQueryString();
        // I to S per Computation File: the instruction that names it (user, 2026-11-02), else the one
        // picked on this form before then. Issued number, or the job ref while it is a draft.
        $ids = $rows->getCollection()->pluck('id')->all();
        $its = SurveyIts::query()->whereIn('survey_computation_file_id', $ids ?: [0])->orderBy('id')->get(['survey_computation_file_id', 'its_number', 'job_ref'])
            ->mapWithKeys(fn ($i) => [$i->survey_computation_file_id => $i->its_number ?: $i->job_ref])->all();
        foreach (SurveyIts::query()->whereIn('id', $rows->getCollection()->pluck('survey_its_id')->filter()->all() ?: [0])->get(['id', 'its_number', 'job_ref']) as $old) {
            foreach ($rows->getCollection()->where('survey_its_id', $old->id) as $cfRow) {
                $its[$cfRow->id] ??= $old->its_number ?: $old->job_ref;
            }
        }

        return view('survey_module.gkn.computation_files', compact('tab', 'rows', 'term', 'its') + [
            'editing'   => $r->query('edit') ? CF::find($r->query('edit')) : null,
            // Shown on the Commission form; the number itself is given on save (user, 2026-11-02).
            'nextNo'    => ComputationNumbers::peek(CF::PREFIX),
            'surveyors' => SurveyorListEntry::query()->where('is_active', true)->orderBy('first_name')->orderBy('surname')->get(['id', 'first_name', 'surname', 'other_name', 'rank']),
        ]);
    }

    public function store(Request $r)
    {
        $data = $this->validated($r);

        $cf = DB::connection('sqlsrv')->transaction(function () use ($data) {
            $no = ComputationNumbers::next(CF::PREFIX);
            // LPKN-123: no year in the number (user, 2026-11-02); year records when it was commissioned.
            $year = (int) date('Y');
            $serial = (int) substr(strrchr($no, '-'), 1);

            return CF::create($data + [
                'computation_no'  => $no,
                'year'            => (int) $year,
                'serial'          => (int) $serial,
                'commissioned_by' => Auth::id(),
                'commissioned_at' => now(),
            ]);
        });

        return redirect()->route('survey-module.gkn.computation-files')->with('success', "Computation File {$cf->computation_no} commissioned.");
    }

    public function update(Request $r, CF $computationFile)
    {
        $computationFile->update($this->validated($r) + ['updated_by' => Auth::id()]);

        return redirect()->route('survey-module.gkn.computation-files')->with('success', "Computation File {$computationFile->computation_no} updated.");
    }

    private function validated(Request $r): array
    {
        $d = $r->validate([
            'file_number'       => 'nullable|string|max:100',
            'file_title'        => 'nullable|string|max:500',
            'plot_no'           => 'nullable|string|max:100',
            'district'          => 'nullable|string|max:150',
            'lga'               => 'nullable|string|max:150',
            'location'          => 'nullable|string|max:500',
            'surveyors_list_id' => ['required', Rule::exists('sqlsrv.surveyors_list', 'id')],
            'remarks'           => 'nullable|string|max:1000',
        ], [
            'surveyors_list_id.required' => 'Choose the surveyor.',
        ]);
        $surveyor = SurveyorListEntry::find($d['surveyors_list_id']);
        $clean = fn ($v) => ($v = trim(preg_replace('/\s+/u', ' ', (string) $v))) === '' ? null : $v;

        return [
            // An identifier: kept exactly as picked.
            'file_number'       => $clean($d['file_number'] ?? null),
            'file_title'        => $clean($d['file_title'] ?? null),
            'plot_no'           => $clean($d['plot_no'] ?? null),
            'district'          => $clean($d['district'] ?? null),
            'lga'               => $clean($d['lga'] ?? null),
            'location'          => $clean($d['location'] ?? null),
            'surveyors_list_id' => $surveyor->id,
            'surveyor_name'     => $surveyor->full_name,
            // survey_its_id is no longer set here (user, 2026-11-02); an old link stays as it was.
            'remarks'           => Normalise::text($d['remarks'] ?? null) ?: null,
        ];
    }
}
