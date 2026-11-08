<?php

namespace App\Http\Controllers\Survey;

use App\Http\Controllers\Controller;
use App\Models\Cadastral\CadastralSurveyJob;
use App\Models\Survey\SurveyIts;
use App\Models\Survey\SurveyorListEntry;
use App\Services\Survey\ItsBeacons;
use App\Services\Survey\SurveySettings;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * GKN > Instruction to Survey (user transcript, 2026-10-25).
 *
 * The register lists Survey's own instructions (survey_its) and, read-only, every
 * instruction Cadastral has issued (cadastral_survey_jobs with an I to S number),
 * with a Source column. Survey's create form follows the Cadastral survey-job
 * pattern: register the job (Draft, job number), then issue the I to S (number,
 * date, issuer) and print it. The surveyor is Survey Department staff
 * (surveyors_list), as these are Government jobs.
 *
 * The file can come from the global file number selector (GKN / LPKN / Misc KN)
 * or be backfilled from a selected Computation File.
 */
class SurveyItsController extends Controller
{
    use \App\Http\Controllers\Survey\Concerns\ExportsRecords;

    /** Beacon numbers picked or typed on the form, held between validated() and the save. */
    private ?string $beaconText = null;

    public function __construct(private ItsBeacons $beacons)
    {
    }

    public function index(Request $r)
    {
        $tab = $r->query('tab') === 'private' ? 'private' : 'government';
        $rows = $this->itsRows($r);
        $page = max(1, (int) $r->query('page', 1));

        return view('survey_module.gkn.its.index', [
            'tab'   => $tab,
            'rows'  => new LengthAwarePaginator($rows->forPage($page, 20)->values(), $rows->count(), 20, $page, ['path' => $r->url(), 'query' => $r->query()]),
            'stats' => [
                'survey'    => SurveyIts::count(),
                'draft'     => SurveyIts::where('status', SurveyIts::DRAFT)->count(),
                'issued'    => SurveyIts::where('status', SurveyIts::ISSUED)->count(),
                'cadastral' => CadastralSurveyJob::whereNotNull('its_number')->count(),
            ],
        ]);
    }

    public function create()
    {
        $cfs = $this->computationFiles();

        return view('survey_module.gkn.its.form', ['its' => new SurveyIts(), 'surveyors' => $this->surveyors(), 'computationFiles' => $cfs,
            'indexedLpkn' => $this->indexedLpknFiles($cfs->pluck('computation_no')->all()),
            'surveyTypes' => SurveySettings::surveyTypes(), 'beaconList' => []]);
    }

    public function store(Request $r)
    {
        $data = $this->validated($r);
        $its = DB::connection('sqlsrv')->transaction(function () use ($data) {
            $its = SurveyIts::create($data + ['job_ref' => SurveyIts::nextRef('job_ref', 'SJ', 4), 'status' => SurveyIts::DRAFT]);
            $this->beacons->save($this->beaconText, $its->surveyor_name, 'survey_its_id', $its->id, 'beacon_picks', false);

            return $its;
        });

        return redirect()->route('survey-module.gkn.its.edit', $its)
            ->with('success', "Survey job {$its->job_ref} registered for {$its->file_number}. Check the instruction and issue it.");
    }

    public function edit(SurveyIts $its)
    {
        $cfs = $this->computationFiles();

        return view('survey_module.gkn.its.form', ['its' => $its, 'surveyors' => $this->surveyors($its->surveyor_id), 'computationFiles' => $cfs,
            'indexedLpkn' => $this->indexedLpknFiles($cfs->pluck('computation_no')->all()),
            'surveyTypes' => SurveySettings::surveyTypes(), 'beaconList' => $this->beacons->forSurveyIts($its->id)]);
    }

    public function update(Request $r, SurveyIts $its)
    {
        if ($its->isIssued()) {
            return back()->with('error', "{$its->its_number} has been issued and can no longer be edited.");
        }

        // The file stays the job's own once registered.
        $data = $this->validated($r, $its);
        DB::connection('sqlsrv')->transaction(function () use ($its, $data) {
            $its->update($data);
            $this->beacons->save($this->beaconText, $its->surveyor_name, 'survey_its_id', $its->id, 'beacon_picks', false);
        });

        return back()->with('success', "{$its->job_ref} updated.");
    }

    /** Issue the I to S: number, date and issuer; the instruction is locked from here. */
    public function issue(Request $r, SurveyIts $its)
    {
        $data = $r->validate(['its_instructions' => 'required|string|max:8000'], [
            'its_instructions.required' => 'The instruction text is required.',
        ]);

        if ($its->isIssued()) return back()->with('error', "{$its->job_ref} already carries {$its->its_number}.");
        if ($its->status === SurveyIts::CANCELLED) return back()->with('error', "{$its->job_ref} is cancelled.");
        // A listed surveyor, or one typed under "Other (specify)" (name only).
        if (! $its->surveyor_name) return back()->with('error', 'Assign a surveyor before issuing the instruction.');

        $its = DB::connection('sqlsrv')->transaction(function () use ($its, $data) {
            $its->update([
                'its_instructions'      => trim($data['its_instructions']),
                'its_number'            => SurveyIts::nextItsNumber(),
                'its_issued_at'         => now()->toDateString(),
                'its_issued_by'         => Auth::user()?->name,
                'its_issued_by_user_id' => Auth::id(),
                'issued_at'             => now(),
                'status'                => SurveyIts::ISSUED,
            ]);

            return $its;
        });

        return redirect()->route('survey-module.gkn.its.index')
            ->with('success', "Instruction {$its->its_number} issued to {$its->surveyor_name}.");
    }

    public function complete(SurveyIts $its)
    {
        if (! in_array($its->status, [SurveyIts::ISSUED, SurveyIts::REPORTED], true)) {
            return back()->with('error', "{$its->job_ref} is {$its->status}; only an issued job can be completed.");
        }
        $its->update(['status' => SurveyIts::COMPLETED, 'completed_at' => now()]);

        return back()->with('success', "{$its->its_number} marked completed.");
    }

    public function cancel(Request $r, SurveyIts $its)
    {
        $reason = trim((string) $r->input('reason', ''));
        if ($its->isClosed()) return back()->with('error', "{$its->job_ref} is already {$its->status}.");
        if ($reason === '') return back()->with('error', 'Give a reason for cancelling.');

        DB::connection('sqlsrv')->transaction(function () use ($its, $reason) {
            $its->update(['status' => SurveyIts::CANCELLED, 'remarks' => trim(($its->remarks ? $its->remarks . "\n" : '') . 'Cancelled ' . now()->format('d M Y') . ': ' . $reason)]);
            // A cancelled job gives its beacons back to the surveyor.
            DB::connection('sqlsrv')->table('survey_its_beacons')->where('survey_its_id', $its->id)->delete();
        });

        return back()->with('success', "{$its->job_ref} cancelled.");
    }

    public function print(SurveyIts $its)
    {
        if (! $its->isIssued()) return back()->with('error', "{$its->job_ref} has no instruction to print yet.");

        return view('survey_module.gkn.its.print', ['its' => $its->load('surveyor'), 'beaconList' => $this->beacons->forSurveyIts($its->id)]);
    }

    /** Beacon numbers free for the form's search, as on the Computation File form. */
    public function beaconSearch(Request $r)
    {
        $id = (string) $r->query('surveyor', '');
        $surveyor = ctype_digit($id) ? SurveyorListEntry::find($id)?->full_name : (string) $r->query('surveyor_other', '');

        return response()->json(['numbers' => $this->beacons->available(
            (string) $r->query('q', ''), $surveyor, 'survey_its_id', $r->query('its') ? (int) $r->query('its') : null,
        )]);
    }

    /** Backfill for a number picked in the global file number selector. */
    public function lookup(Request $r)
    {
        $number = trim(mb_substr((string) $r->query('file_number', ''), 0, 100));
        if ($number === '') return response()->json(['found' => false], 422);

        $db = DB::connection('sqlsrv');
        $fi = $db->table('file_indexings')->where('file_number', $number)
            ->where(fn ($q) => $q->whereNull('is_deleted')->orWhere('is_deleted', 0))
            ->orderByDesc('id')->first(['id', 'file_title', 'plot_number', 'district', 'lga', 'location']);

        $out = ['found' => (bool) $fi, 'file_number' => $number, 'type' => GknApplicationController::typeOf($number),
            'title' => $fi->file_title ?? null, 'plot' => $fi->plot_number ?? null, 'district' => $fi->district ?? null,
            'lga' => $fi->lga ?? null, 'location' => $fi->location ?? null];
        if (! $fi && ($fn = $db->table('fileNumber')->where(fn ($q) => $q->where('gkn_fileno', $number)->orWhere('mlsfNo', $number))
            ->where(fn ($q) => $q->whereNull('is_deleted')->orWhere('is_deleted', 0))->orderByDesc('id')->first(['FileName', 'plot_no', 'lga']))) {
            $out = array_merge($out, ['found' => true, 'title' => $fn->FileName, 'plot' => $fn->plot_no, 'lga' => $fn->lga]);
        }

        $out['open_jobs'] = SurveyIts::where('file_number', $number)->whereNotIn('status', [SurveyIts::COMPLETED, SurveyIts::CANCELLED])->pluck('job_ref')->all();

        return response()->json(array_map(fn ($v) => is_string($v) ? (trim($v) === '' ? null : trim($v)) : $v, $out));
    }

    /* ------------------------------------------------------------------ */

    private function surveyors(?int $keep = null)
    {
        return SurveyorListEntry::query()
            ->where(fn ($q) => $q->where('is_active', true)->when($keep, fn ($w) => $w->orWhere('id', $keep)))
            ->orderBy('first_name')->orderBy('surname')
            ->get(['id', 'first_name', 'surname', 'other_name', 'rank', 'is_active']);
    }

    /** Survey's commissioned Computation Files (LPKN), newest first, for the I to S form (user, 2026-11-02). */
    private function computationFiles()
    {
        if (! \Illuminate\Support\Facades\Schema::connection('sqlsrv')->hasTable('survey_computation_files')) {
            return collect();
        }

        $files = \App\Models\Survey\SurveyComputationFile::query()->orderByDesc('id')->limit(1000)
            ->get(['id', 'computation_no', 'file_number', 'file_title', 'plot_no', 'district', 'lga', 'surveyors_list_id', 'survey_type']);
        // Shown when the file is picked: these stay on the Computation File.
        $beacons = DB::connection('sqlsrv')->table('survey_its_beacons')->whereIn('survey_computation_file_id', $files->pluck('id')->all() ?: [0])
            ->orderBy('beacon_series')->orderBy('beacon_seq')->get(['survey_computation_file_id', 'beacon_no'])->groupBy('survey_computation_file_id');
        foreach ($files as $file) {
            $file->setAttribute('beacon_list', collect($beacons->get($file->id, []))->map(fn ($b) => \App\Models\Survey\SurveyBeaconRegister::display($b->beacon_no))->implode(', '));
        }

        return $files;
    }

    /**
     * LPKN files already in File Indexing (before Survey commissioned Computation Files), offered beside the
     * commissioned ones (user, 2026-11-03: "this should load all LPKN files, even those existing ones").
     * Posted as "idx:<file number>"; the I to S then keeps the number with no Computation File row.
     */
    private function indexedLpknFiles(array $skip = [])
    {
        return DB::connection('sqlsrv')->table('file_indexings')
            ->where('file_number', 'like', 'LPKN%')
            ->where(fn ($q) => $q->whereNull('is_deleted')->orWhere('is_deleted', 0))
            ->whereNotIn('file_number', $skip ?: [''])
            ->orderByRaw('LEN(file_number), file_number')->limit(2000)
            ->get(['file_number', 'file_title', 'plot_number', 'district', 'lga']);
    }

    private function validated(Request $r, ?SurveyIts $existing = null): array
    {
        // "Other (specify)": whitespace-normalised, case kept. A name already on the
        // List of Surveyors (any case/spacing) is taken as that surveyor.
        $other = null;
        if ($r->input('surveyor_id') === 'other') {
            $other = preg_replace('/\s+/u', ' ', trim((string) $r->input('surveyor_other')));
            $match = $other === '' ? null : SurveyorListEntry::get(['id', 'first_name', 'surname', 'other_name'])
                ->first(fn ($s) => mb_strtoupper($s->full_name) === mb_strtoupper($other));
            $r->merge($match ? ['surveyor_id' => (string) $match->id, 'surveyor_other' => null] : ['surveyor_other' => $other]);
            if ($match) $other = null;
        }

        $data = $r->validate([
            'file_number' => 'nullable|string|max:100',
            'file_title'  => 'nullable|string|max:500',
            'plot_no'     => 'nullable|string|max:100',
            'district'    => 'nullable|string|max:255',
            'lga'         => 'nullable|string|max:255',
            'surveyor_id' => ['nullable', function ($attr, $value, $fail) {
                if ($value !== 'other' && ! SurveyorListEntry::whereKey($value)->exists()) $fail('Select a surveyor from the list, or Other (specify).');
            }],
            'surveyor_other' => 'required_if:surveyor_id,other|nullable|string|max:255',
            'job_scope'   => 'nullable|string|max:4000',
            'its_instructions' => 'nullable|string|max:8000',
            'remarks'     => 'nullable|string|max:2000',
            // Survey type + beacons (CR 03112026 item 11).
            'survey_type'       => 'nullable|string|max:100',
            'survey_type_other' => 'required_if:survey_type,Other|nullable|string|max:100',
            // Searchable, with Other for typed numbers and ranges (user, 2026-11-06), as on the Computation File.
            'beacon_picks'      => 'nullable|array',
            'beacon_picks.*'    => 'string|max:40',
            'beacon_other'      => ['nullable', 'string', 'max:8000', \Illuminate\Validation\Rule::requiredIf(fn () => in_array('Other', (array) $r->input('beacon_picks'), true))],
            // The Computation File this instruction is for (user, 2026-11-02).
            // ... or an LPKN file already in File Indexing, posted as "idx:<file number>" (user, 2026-11-03).
            'survey_computation_file_id' => ['nullable', 'string', 'max:120', function ($attr, $value, $fail) {
                $ok = str_starts_with((string) $value, 'idx:')
                    ? DB::connection('sqlsrv')->table('file_indexings')->where('file_number', substr($value, 4))->where('file_number', 'like', 'LPKN%')->exists()
                    : ctype_digit((string) $value) && \App\Models\Survey\SurveyComputationFile::whereKey((int) $value)->exists();
                if (! $ok) $fail('That Computation File no longer exists. Pick another.');
            }],
        ], [
            'survey_computation_file_id.exists' => 'That Computation File no longer exists. Pick another.',
            'surveyor_other.required_if' => 'Surveyor is Other: please specify the surveyor\'s name.',
            'survey_type_other.required_if' => 'Survey Type is Other: please specify it.',
            'beacon_other.required' => 'Beacon Numbers has Other: please type the numbers.',
        ]);
        $data['survey_type'] = SurveySettings::surveyTypeValue($data['survey_type'] ?? null, $data['survey_type_other'] ?? null);
        $picks = (array) ($data['beacon_picks'] ?? []);
        $this->beaconText = implode(', ', array_filter(array_merge(
            array_diff($picks, ['Other']),
            in_array('Other', $picks, true) ? [$data['beacon_other'] ?? ''] : [],
        )));
        unset($data['surveyor_other'], $data['survey_type_other'], $data['beacon_picks'], $data['beacon_other']);
        // Keep the number as it was when picked, beside the link.
        $pick = (string) ($data['survey_computation_file_id'] ?? '');
        $computationFileNumber = null;
        if (str_starts_with($pick, 'idx:')) {
            $data['survey_computation_file_id'] = null;
            $data['computation_no'] = substr($pick, 4);
            $computationFileNumber = substr($pick, 4);
        } else {
            $data['survey_computation_file_id'] = $pick !== '' ? (int) $pick : null;
            $computationFile = $pick !== ''
                ? \App\Models\Survey\SurveyComputationFile::whereKey((int) $pick)->first(['computation_no', 'file_number'])
                : null;
            $data['computation_no'] = $computationFile?->computation_no;
            $computationFileNumber = $computationFile?->file_number;
        }

        if ($existing) {
            unset($data['file_number']);
        } else {
            $data['file_number'] = $computationFileNumber ?: trim((string) ($data['file_number'] ?? ''));
            $data['file_type'] = $data['file_number'] !== '' ? GknApplicationController::typeOf($data['file_number']) : null;
        }

        // The surveyor's name and rank are kept as they were when assigned.
        if (($data['surveyor_id'] ?? null) === 'other') {
            $data['surveyor_id'] = null;
            $data['surveyor_name'] = $other;
            $data['surveyor_rank'] = null;
        } else {
            $sv = ! empty($data['surveyor_id']) ? SurveyorListEntry::find($data['surveyor_id']) : null;
            $data['surveyor_name'] = $sv?->full_name;
            $data['surveyor_rank'] = $sv?->rank;
        }

        // The location is district + LGA; the plot number never goes in it.
        $data['location'] = implode(', ', array_filter([$data['district'] ?? null, $data['lga'] ?? null])) ?: null;
        foreach (['file_title', 'job_scope', 'remarks'] as $k) {
            if (isset($data[$k])) $data[$k] = trim(preg_replace('/[ \t]+/', ' ', $data[$k])) ?: null;
        }

        return $data;
    }

    /** The open tab's list, shared with Export Records. */
    private function itsRows(Request $r)
    {
        $term   = trim((string) $r->query('q', ''));
        // Government / Private tabs (CR 30102026 item 15): Government = Survey's own jobs, Private = Cadastral's
        // (private survey, items 13-14). Each tab lists one source.
        $tab    = $r->query('tab') === 'private' ? 'private' : 'government';
        $source = $tab === 'private' ? 'cadastral' : 'survey';
        $status = $r->query('status');

        $rows = collect();

        if ($source !== 'cadastral') {
            $q = SurveyIts::query();
            if ($term !== '') {
                $q->where(fn ($w) => $w->where('file_number', 'like', "%$term%")->orWhere('job_ref', 'like', "%$term%")
                    ->orWhere('its_number', 'like', "%$term%")->orWhere('file_title', 'like', "%$term%")
                    ->orWhere('surveyor_name', 'like', "%$term%"));
            }
            if ($status) $q->where('status', $status);
            foreach ($q->get() as $i) {
                $rows->push(['source' => 'survey', 'model' => $i, 'id' => $i->id, 'its_number' => $i->its_number, 'job' => $i->job_ref,
                    'file_number' => $i->file_number, 'file_title' => $i->file_title, 'plot' => $i->plot_no, 'district' => $i->district,
                    'lga' => $i->lga, 'surveyor' => $i->surveyor_name, 'issued' => $i->its_issued_at, 'status' => $i->status,
                    'sort' => $i->issued_at ?? $i->created_at]);
            }
        }

        if ($source !== 'survey') {
            $q = CadastralSurveyJob::query()->whereNotNull('its_number');
            if ($term !== '') {
                $q->where(fn ($w) => $w->where('file_number', 'like', "%$term%")->orWhere('job_number', 'like', "%$term%")
                    ->orWhere('its_number', 'like', "%$term%")->orWhere('file_title', 'like', "%$term%")
                    ->orWhere('surveyor_name', 'like', "%$term%"));
            }
            if ($status) $q->where('status', $status);
            foreach ($q->get() as $j) {
                $rows->push(['source' => 'cadastral', 'model' => $j, 'id' => $j->id, 'its_number' => $j->its_number, 'job' => $j->job_number,
                    'file_number' => $j->file_number, 'file_title' => $j->file_title, 'plot' => $j->prop_plot, 'district' => $j->prop_district,
                    'lga' => $j->prop_lga, 'surveyor' => $j->surveyor_name, 'issued' => $j->its_issued_at, 'status' => $j->status,
                    'sort' => $j->issued_at ?? $j->created_at]);
            }
        }

        $rows = $rows->sortByDesc(fn ($x) => optional($x['sort'])->timestamp ?? 0)->values();

        return $rows;
    }

    public function export(Request $r)
    {
        return $this->exportRecords($r, fn () => $this->itsRows($r), [
            'source' => 'Source', 'its_number' => 'I to S No.', 'job' => 'Job No.', 'file_number' => 'File No.', 'title' => 'Title', 'plot' => 'Plot No.', 'district' => 'District',
            'lga' => 'LGA', 'surveyor' => 'Surveyor', 'issued' => 'Issued', 'status' => 'Status',
        ], fn ($x) => [
            'source' => $x['source'] === 'survey' ? 'Survey' : 'Cadastral', 'its_number' => $x['its_number'], 'job' => $x['job'], 'file_number' => $x['file_number'],
            'title' => $x['file_title'], 'plot' => $x['plot'], 'district' => $x['district'], 'lga' => $x['lga'], 'surveyor' => $x['surveyor'],
            'issued' => $this->exportDate($x['issued']), 'status' => $x['status'],
        ], 'sort');
    }
}
