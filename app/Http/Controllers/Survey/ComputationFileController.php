<?php

namespace App\Http\Controllers\Survey;

use App\Http\Controllers\Controller;
use App\Models\Survey\SurveyComputationFile as CF;
use App\Models\Survey\SurveyIts;
use App\Models\Survey\SurveyorListEntry;
use App\Services\Survey\ComputationNumbers;
use App\Services\CadastralDept\Normalise;
use App\Services\Survey\ItsBeacons;
use App\Support\AddressBuilder;
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
    use \App\Http\Controllers\Survey\Concerns\ExportsRecords;

    /** Beacon numbers picked or typed on the form, held between validated() and the save. */
    private ?string $beaconText = null;

    public function index(Request $r)
    {
        $tab = $r->query('tab') === 'private' ? 'private' : 'government';
        $term = trim(preg_replace('/\s+/', ' ', (string) $r->query('q', '')));
        $like = '%' . str_replace(['[', '%', '_'], ['[[]', '[%]', '[_]'], $term) . '%';

        if ($tab === 'private') {
            $rows = Schema::connection('sqlsrv')->hasTable('cadastral_field_jackets')
                ? $this->jacketQuery($r)->paginate(25)->withQueryString()
                : new \Illuminate\Pagination\LengthAwarePaginator([], 0, 25);

            return view('survey_module.gkn.computation_files', compact('tab', 'rows', 'term') + ['stats' => $this->stats()]);
        }

        $rows = $this->fileQuery($r)->paginate(25)->withQueryString();
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
                ->orderByDesc('id')->limit(1000)->get(['id', 'its_number', 'job_ref', 'file_number', 'file_title', 'survey_type']),
            // Beacons already on each I to S: they stay there, and are shown when it is picked.
            'itsBeacons' => DB::connection('sqlsrv')->table('survey_its_beacons')->whereNotNull('survey_its_id')->orderBy('beacon_series')->orderBy('beacon_seq')->get(['survey_its_id', 'beacon_no'])
                ->groupBy('survey_its_id')->map(fn ($g) => $g->map(fn ($b) => \App\Models\Survey\SurveyBeaconRegister::display($b->beacon_no))->implode(', ')),
            'surveyTypes' => \App\Services\Survey\SurveySettings::surveyTypes(),
            'savedBeacons' => $editing ? app(ItsBeacons::class)->forComputationFile($editing->id) : [],
            'beaconMap' => $this->beaconMap(),
            'editingIts' => $editing ? (SurveyIts::where('survey_computation_file_id', $editing->id)->orderBy('id')->value('id') ?: $editing->survey_its_id) : null,
            // Shown on the Commission form; the number itself is given on save (user, 2026-11-02).
            'nextNo'    => ComputationNumbers::peek(CF::PREFIX),
            // Job No, given on Commission too (Survey Update 5a, 2026-11-10).
            'nextJobNo' => CF::nextJobNo(),
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
                // Auto generated, never typed (Survey Update 5a); quoted on beacon requests.
                'job_no'          => CF::nextJobNo(),
                'year'            => (int) $year,
                'serial'          => (int) $serial,
                'commissioned_by' => Auth::id(),
                'commissioned_at' => now(),
            ]);
            $this->linkIts($cf);
            $this->saveBeacons($cf);

            return $cf;
        });

        return redirect()->route('survey-module.gkn.computation-files')->with('success', "Computation File {$cf->computation_no} commissioned, Job No {$cf->job_no}.");
    }

    public function update(Request $r, CF $computationFile)
    {
        DB::connection('sqlsrv')->transaction(function () use ($r, $computationFile) {
            $computationFile->update($this->validated($r) + ['updated_by' => Auth::id()]);
            $this->linkIts($computationFile);
            $this->saveBeacons($computationFile);
        });

        return redirect()->route('survey-module.gkn.computation-files')->with('success', "Computation File {$computationFile->computation_no} updated.");
    }

    /**
     * The I to S picked here also names this Computation File on its own form, when it names none yet,
     * so both screens agree. An I to S that already names another file is left as it is.
     */
    private function saveBeacons(CF $cf): void
    {
        $itsId = SurveyIts::where('survey_computation_file_id', $cf->id)->orderBy('id')->value('id') ?: $cf->survey_its_id;

        app(ItsBeacons::class)->saveForComputationFile($this->beaconText, $cf->surveyor_name, $cf->id, $itsId ? (int) $itsId : null);
    }

    /** Beacon numbers free for the form's search. */
    public function beaconSearch(Request $r)
    {
        $surveyor = $r->query('surveyor') ? SurveyorListEntry::find($r->query('surveyor')) : null;

        return response()->json(['numbers' => app(ItsBeacons::class)->available(
            (string) $r->query('q', ''), $surveyor?->full_name, 'survey_computation_file_id', $r->query('file') ? (int) $r->query('file') : null,
        )]);
    }

    /** Each Computation File's beacons as they read on screen: its own, then those on its I to S. */
    private function beaconMap(): array
    {
        $rows = DB::connection('sqlsrv')->table('survey_its_beacons')
            ->where(fn ($q) => $q->whereNotNull('survey_computation_file_id')->orWhereNotNull('survey_its_id'))
            ->orderBy('beacon_series')->orderBy('beacon_seq')->get(['survey_its_id', 'survey_computation_file_id', 'beacon_no']);
        $fileOfIts = CF::query()->whereNotNull('survey_its_id')->pluck('id', 'survey_its_id')->all()
            + SurveyIts::query()->whereNotNull('survey_computation_file_id')->pluck('survey_computation_file_id', 'id')->all();

        $map = [];
        foreach ($rows as $b) {
            $file = $b->survey_computation_file_id ?: ($fileOfIts[$b->survey_its_id] ?? null);
            if ($file) $map[$file][] = \App\Models\Survey\SurveyBeaconRegister::display($b->beacon_no);
        }

        return $map;
    }

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
            // Survey Type and Beacon Numbers, as on the I to S (user, 2026-11-06).
            'survey_type'       => 'nullable|string|max:100',
            'survey_type_other' => 'required_if:survey_type,Other|nullable|string|max:100',
            'beacon_picks'      => 'nullable|array',
            'beacon_picks.*'    => 'string|max:40',
            'beacon_other'      => ['nullable', 'string', 'max:8000', Rule::requiredIf(fn () => in_array('Other', (array) $r->input('beacon_picks'), true))],
        ], [
            'survey_type_other.required_if' => 'Survey Type is Other: please specify it.',
            'beacon_other.required' => 'Beacon Numbers has Other: please type the numbers.',
            'surveyors_list_id.required' => 'Choose the surveyor.',
            'survey_its_id.exists'       => 'That Instruction to Survey no longer exists. Pick another.',
        ]);
        $surveyor = SurveyorListEntry::find($d['surveyors_list_id']);
        $clean = fn ($v) => ($v = trim(preg_replace('/\s+/u', ' ', (string) $v))) === '' ? null : $v;

        $picks = (array) ($d['beacon_picks'] ?? []);
        $this->beaconText = implode(', ', array_filter(array_merge(
            array_diff($picks, ['Other']),
            in_array('Other', $picks, true) ? [$d['beacon_other'] ?? ''] : [],
        )));
        $district = $clean($d['district'] ?? null);
        $lga = $clean($d['lga'] ?? null);
        $location = $clean($d['location'] ?? null);
        if ($district || $lga) {
            $location = AddressBuilder::propertyLocation([
                'district' => $district,
                'lga' => $lga,
                'state' => 'Kano',
            ]);
        }

        return [
            // An identifier: kept exactly as picked.
            'file_number'       => $clean($d['file_number'] ?? null),
            'file_title'        => $clean($d['file_title'] ?? null),
            'plot_no'           => $clean($d['plot_no'] ?? null),
            'district'          => $district,
            'lga'               => $lga,
            'location'          => $location,
            'surveyors_list_id' => $surveyor->id,
            'surveyor_name'     => $surveyor->full_name,
            // Optional again (user, 2026-11-03; it was taken off on 2026-11-02).
            'survey_its_id'     => ! empty($d['survey_its_id']) ? (int) $d['survey_its_id'] : null,
            'survey_type'       => \App\Services\Survey\SurveySettings::surveyTypeValue($d['survey_type'] ?? null, $d['survey_type_other'] ?? null),
            'remarks'           => Normalise::text($d['remarks'] ?? null) ?: null,
        ];
    }

    /** The Private tab's filters and order, shared with Export Records. */
    private function jacketQuery(Request $r)
    {
        $term = trim(preg_replace('/\s+/', ' ', (string) $r->query('q', '')));
        $like = '%' . str_replace(['[', '%', '_'], ['[[]', '[%]', '[_]'], $term) . '%';

        return \App\Models\CadastralDept\CadastralFieldJacket::query()->with(['surveyor', 'job'])
                    ->when($term !== '', fn ($q) => $q->where(fn ($w) => $w->where('lppl_no', 'like', $like)->orWhere('field_jacket_no', 'like', $like)
                        ->orWhere('file_number', 'like', $like)->orWhere('file_title', 'like', $like)))
                    ->orderByDesc('commissioned_at')->orderByDesc('id');
    }

    /** The Government tab's filters and order, shared with Export Records. */
    private function fileQuery(Request $r)
    {
        $term = trim(preg_replace('/\s+/', ' ', (string) $r->query('q', '')));
        $like = '%' . str_replace(['[', '%', '_'], ['[[]', '[%]', '[_]'], $term) . '%';

        return CF::query()->with('commissioner:id,first_name,last_name')
            ->when($term !== '', fn ($q) => $q->where(fn ($w) => $w->where('computation_no', 'like', $like)->orWhere('job_no', 'like', $like)->orWhere('file_number', 'like', $like)
                ->orWhere('file_title', 'like', $like)->orWhere('surveyor_name', 'like', $like)))
            ->orderByDesc('commissioned_at')->orderByDesc('id');
    }

    public function export(Request $r)
    {
        $columns = [
            'number' => 'Computation File No.', 'file_number' => 'File No.', 'title' => 'Title', 'plot_no' => 'Plot No.', 'district' => 'District', 'lga' => 'LGA',
            'location' => 'Location', 'surveyor' => 'Surveyor',
        ];
        $common = fn ($x) => ['file_number' => $x->file_number, 'title' => $x->file_title, 'plot_no' => $x->plot_no, 'district' => $x->district, 'lga' => $x->lga,
            'location' => $x->location, 'commissioned' => $this->exportDate($x->commissioned_at)];

        if ($r->query('tab') === 'private') {
            return $this->exportRecords($r, fn () => Schema::connection('sqlsrv')->hasTable('cadastral_field_jackets') ? $this->jacketQuery($r) : collect(),
                $columns + ['job' => 'Job No.', 'commissioned' => 'Commissioned'],
                fn ($x) => ['number' => $x->lppl_no ?: $x->field_jacket_no, 'surveyor' => $x->surveyor?->display_name, 'job' => $x->job?->job_number] + $common($x), 'commissioned_at');
        }

        // I to S per Computation File, as on the page: the instruction that names it, else the one picked on the form.
        $its = [];
        foreach (SurveyIts::query()->whereNotNull('survey_computation_file_id')->orderBy('id')->get(['survey_computation_file_id', 'its_number', 'job_ref']) as $i) {
            $its[$i->survey_computation_file_id] ??= $i->its_number ?: $i->job_ref;
        }
        $picked = SurveyIts::query()->pluck('its_number', 'id')->all();
        $jobs = SurveyIts::query()->pluck('job_ref', 'id')->all();

        $beacons = $this->beaconMap();

        return $this->exportRecords($r, fn () => $this->fileQuery($r), $columns + ['job_no' => 'Job No.', 'survey_type' => 'Survey Type', 'beacons' => 'Beacon Numbers', 'its' => 'I to S', 'commissioned' => 'Commissioned'],
            fn ($x) => ['number' => $x->computation_no, 'job_no' => $x->job_no, 'surveyor' => $x->surveyor_name, 'survey_type' => $x->survey_type, 'beacons' => implode(', ', $beacons[$x->id] ?? []),
                'its' => $its[$x->id] ?? ($picked[$x->survey_its_id] ?? null) ?: ($jobs[$x->survey_its_id] ?? null)] + $common($x), 'commissioned_at');
    }
}
