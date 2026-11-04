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

            return view('survey_module.gkn.computation_files', compact('tab', 'rows', 'term') + ['stats' => $this->stats()]);
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

        $editing = $r->query('edit') ? CF::find($r->query('edit')) : null;

        return view('survey_module.gkn.computation_files', compact('tab', 'rows', 'term', 'its', 'editing') + [
            'stats'     => $this->stats(),
            // Instruction to Survey is back on this form as an optional pick (user, 2026-11-03): Survey's own
            // I to S that name no other Computation File. On edit it shows the one already linked either way.
            'itsOptions' => SurveyIts::query()->where('status', '!=', SurveyIts::CANCELLED)
                ->where(fn ($q) => $q->whereNull('survey_computation_file_id')->when($editing, fn ($w) => $w->orWhere('survey_computation_file_id', $editing->id)))
                ->orderByDesc('id')->limit(1000)->get(['id', 'its_number', 'job_ref', 'file_number', 'file_title']),
            'editingIts' => $editing ? (SurveyIts::where('survey_computation_file_id', $editing->id)->orderBy('id')->value('id') ?: $editing->survey_its_id) : null,
            // Shown on the Commission form; the number itself is given on save (user, 2026-11-02).
            'nextNo'    => ComputationNumbers::peek(CF::PREFIX),
            'surveyors' => SurveyorListEntry::query()->where('is_active', true)->orderBy('first_name')->orderBy('surname')->get(['id', 'first_name', 'surname', 'other_name', 'rank']),
        ]);
    }

    /** Dashboard cards for both tabs (user, 2026-11-03: "most of the survey pages did not have dashboard cards"). */
    private function stats(): array
    {
        $private = Schema::connection('sqlsrv')->hasTable('cadastral_field_jackets')
            ? \App\Models\CadastralDept\CadastralFieldJacket::query()->count() : 0;

        return [
            'government' => CF::count(),
            'private'    => $private,
            'month'      => CF::where('commissioned_at', '>=', now()->startOfMonth())->count(),
            'with_its'   => CF::whereIn('id', SurveyIts::whereNotNull('survey_computation_file_id')->select('survey_computation_file_id'))
                ->orWhereNotNull('survey_its_id')->count(),
        ];
    }

    public function store(Request $r)
    {
        $data = $this->validated($r);

        $cf = DB::connection('sqlsrv')->transaction(function () use ($data) {
            $no = ComputationNumbers::next(CF::PREFIX);
            // LPKN-123: no year in the number (user, 2026-11-02); year records when it was commissioned.
            $year = (int) date('Y');
            $serial = (int) substr(strrchr($no, '-'), 1);

            $cf = CF::create($data + [
                'computation_no'  => $no,
                'year'            => (int) $year,
                'serial'          => (int) $serial,
                'commissioned_by' => Auth::id(),
                'commissioned_at' => now(),
            ]);
            $this->linkIts($cf);

            return $cf;
        });

        return redirect()->route('survey-module.gkn.computation-files')->with('success', "Computation File {$cf->computation_no} commissioned.");
    }

    public function update(Request $r, CF $computationFile)
    {
        DB::connection('sqlsrv')->transaction(function () use ($r, $computationFile) {
            $computationFile->update($this->validated($r) + ['updated_by' => Auth::id()]);
            $this->linkIts($computationFile);
        });

        return redirect()->route('survey-module.gkn.computation-files')->with('success', "Computation File {$computationFile->computation_no} updated.");
    }

    /**
     * The I to S picked here also names this Computation File on its own form, when it names none yet,
     * so both screens agree. An I to S that already names another file is left as it is.
     */
    private function linkIts(CF $cf): void
    {
        if (! $cf->survey_its_id) {
            return;
        }
        SurveyIts::whereKey($cf->survey_its_id)->whereNull('survey_computation_file_id')
            ->update(['survey_computation_file_id' => $cf->id, 'computation_no' => $cf->computation_no]);
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
            'survey_its_id'     => ['nullable', 'integer', Rule::exists('sqlsrv.survey_its', 'id')],
        ], [
            'surveyors_list_id.required' => 'Choose the surveyor.',
            'survey_its_id.exists'       => 'That Instruction to Survey no longer exists. Pick another.',
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
            // Optional again (user, 2026-11-03; it was taken off on 2026-11-02).
            'survey_its_id'     => ! empty($d['survey_its_id']) ? (int) $d['survey_its_id'] : null,
            'remarks'           => Normalise::text($d['remarks'] ?? null) ?: null,
        ];
    }
}
