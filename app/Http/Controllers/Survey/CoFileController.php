<?php

namespace App\Http\Controllers\Survey;

use App\Http\Controllers\Controller;
use App\Models\Survey\SurveyCoFile;
use App\Support\AddressBuilder;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * CO (Compensation) files, numbered CO-<serial>-<year> (CO-9-2018; user, 2026-10-30).
 * The Commission CO File page has two modes: Commission (the system numbers a new
 * file) and Capture (an existing paper CO file keyed in under its own number,
 * tidied into the same format). Projects are opened under a CO file, one CO file to
 * many projects.
 */
class CoFileController extends Controller
{
    /** CO Files: the captured (existing) files only (user, 2026-10-30). */
    /** Related file numbers a CO file may carry: SIT, GKN or Misc KN, optionally temporary. */
    public const RELATED_FILE_PATTERN = '/^(SIT-\d{4}-\d+|GKN-\d+|MISC KN \d+|RDKN-\d+)( ?\(T\))?$/i';

    /**
     * One related-file field per kind (user, 2026-11-02): column => [label, pattern, example].
     * With Has Related File = Yes at least one is required. RDKN added and the order set to
     * SIT, Misc KN, GKN, RDKN (user's Survey Flow sheet, 2026-11-03).
     */
    public const RELATED_KINDS = [
        'related_sit_file_no'    => ['SIT', '/^SIT-\d{4}-\d+( ?\(T\))?$/i', 'SIT-2024-15'],
        'related_misckn_file_no' => ['Misc KN', '/^MISC KN \d+( ?\(T\))?$/i', 'MISC KN 45'],
        'related_gkn_file_no'    => ['GKN', '/^GKN-\d+( ?\(T\))?$/i', 'GKN-120'],
        'related_rdkn_file_no'   => ['RDKN', '/^RDKN-\d+( ?\(T\))?$/i', 'RDKN-12'],
        // A land file (user, 2026-11-03): an MLPP number from the selector's MLPP tab, e.g. RES-2019-123, kept as picked.
        'related_mlpp_file_no'   => ['MLPP', '/^[A-Z][A-Z0-9 \/-]*\d[A-Z0-9 \/-]*( ?\(T\))?$/i', 'RES-2019-123'],
    ];

    public function index(Request $r)
    {
        return $this->listing($r, SurveyCoFile::SOURCE_CAPTURED);
    }

    /** Commission CO File: the commissioned files only, with the button to commission one (user, 2026-10-30). */
    public function commissioned(Request $r)
    {
        return $this->listing($r, SurveyCoFile::SOURCE_COMMISSIONED);
    }

    /** The list page for one source. */
    public static function listRoute(string $source): string
    {
        return $source === SurveyCoFile::SOURCE_COMMISSIONED ? 'survey-module.compensation.co-files.commissioned' : 'survey-module.compensation.co-files';
    }

    private function listing(Request $r, string $only)
    {
        $q = SurveyCoFile::query()->where('source', $only)
            ->withCount('projects')
            ->withSum('projects', 'acquisition_size_ha')
            ->with(['projects.surveyors', 'creator:id,first_name,last_name']);

        if ($term = trim((string) $r->query('q', ''))) {
            $q->where(function ($w) use ($term) {
                $w->where('co_file_no', 'like', "%$term%")
                  ->orWhere('title', 'like', "%$term%")
                  ->orWhere('related_file_no', 'like', "%$term%")
                  ->orWhere('related_sit_file_no', 'like', "%$term%")
                  ->orWhere('related_gkn_file_no', 'like', "%$term%")
                  ->orWhere('related_misckn_file_no', 'like', "%$term%")
                  ->orWhere('related_rdkn_file_no', 'like', "%$term%")
                  ->orWhere('related_mlpp_file_no', 'like', "%$term%")
                  ->orWhere('old_file_ref', 'like', "%$term%")->orWhere('financial_file_ref', 'like', "%$term%")
                  ->orWhere('surveyor_name', 'like', "%$term%")
                  ->orWhereHas('projects', fn ($p) => $p->where('name', 'like', "%$term%")
                                                        ->orWhere('project_code', 'like', "%$term%")
                                                        ->orWhere('plan_no', 'like', "%$term%"));
            });
        }
        if ($st = $r->query('status')) $q->where('status', $st);
        if ($from = $r->query('from')) $q->whereDate('commission_date', '>=', $from);
        if ($to = $r->query('to'))     $q->whereDate('commission_date', '<=', $to);
        $q->origin($r->query('origin'));

        $coFiles = $q->orderByDesc('id')->paginate(15)->withQueryString();

        // The related file's title under its number (CR 30102026 item 31): file_indexings on the
        // file-number string, else its fileNumber row, as relatedLookup() does.
        $relatedTitles = [];
        $numbers = $coFiles->getCollection()
            ->flatMap(fn ($f) => [$f->related_file_no, $f->related_sit_file_no, $f->related_gkn_file_no, $f->related_misckn_file_no, $f->related_rdkn_file_no, $f->related_mlpp_file_no])
            ->filter()->unique()->values()->all();
        if ($numbers) {
            $db = DB::connection('sqlsrv');
            $live = fn ($w) => $w->whereNull('is_deleted')->orWhere('is_deleted', 0);
            foreach ($db->table('fileNumber')->whereIn('mlsfNo', $numbers)->where($live)->orderBy('id')->get(['mlsfNo', 'FileName']) as $x) {
                if (trim((string) $x->FileName) !== '') $relatedTitles[mb_strtoupper(trim($x->mlsfNo))] = trim($x->FileName);
            }
            foreach ($db->table('file_indexings')->whereIn('file_number', $numbers)->where($live)->orderBy('id')->get(['file_number', 'file_title']) as $x) {
                if (trim((string) $x->file_title) !== '') $relatedTitles[mb_strtoupper(trim($x->file_number))] = trim($x->file_title);
            }
        }

        $mine = fn () => SurveyCoFile::where('source', $only);
        $stats = [
            'total'    => $mine()->count(),
            'active'   => $mine()->whereIn('status', ['Commissioned', SurveyCoFile::CAPTURED_STATUS, 'Active'])->count(),
            'projects' => \App\Models\Survey\SurveyProject::whereIn('survey_co_file_id', $mine()->select('id'))->count(),
            'closed'   => $mine()->where('status', 'Closed')->count(),
        ];

        return view('survey_module.compensation.co_files', compact('coFiles', 'stats', 'only', 'relatedTitles'));
    }

    public function create(Request $r)
    {
        $capture = $r->query('mode') === SurveyCoFile::SOURCE_CAPTURED
            || old('source') === SurveyCoFile::SOURCE_CAPTURED;

        $coFile = new SurveyCoFile([
            'source'          => $capture ? SurveyCoFile::SOURCE_CAPTURED : SurveyCoFile::SOURCE_COMMISSIONED,
            'status'          => $capture ? SurveyCoFile::CAPTURED_STATUS : 'Commissioned',
            'commission_date' => $capture ? null : now()->toDateString(),
            'prop_state'      => 'Kano',
        ]);

        return view('survey_module.compensation.co_file_register', [
            'coFile'    => $coFile,
            'nextNo'    => SurveyCoFile::nextNumber(),
            'surveyors' => $capture ? $this->surveyors() : collect(),
        ]);
    }

    public function store(Request $r)
    {
        $capture = $r->input('source') === SurveyCoFile::SOURCE_CAPTURED;
        $data = $this->validated($r, null, $capture);

        $data['source'] = $capture ? SurveyCoFile::SOURCE_CAPTURED : SurveyCoFile::SOURCE_COMMISSIONED;
        if ($capture) {
            $coFile = SurveyCoFile::create($data);
        } else {
            // Numbered on save, never from the form. The number is unique in the table, so
            // if someone else took it in the same moment, take the next one.
            for ($try = 1; ; $try++) {
                try {
                    $coFile = SurveyCoFile::create(['co_file_no' => SurveyCoFile::nextNumber()] + $data);
                    break;
                } catch (QueryException $e) {
                    if ($try >= 3) throw $e;
                }
            }
        }

        return redirect()
            ->route(self::listRoute($coFile->source))
            ->with('success', $capture
                ? "Existing CO file {$coFile->co_file_no} captured."
                : "CO file {$coFile->co_file_no} commissioned. Open its projects from Projects.");
    }

    public function edit(SurveyCoFile $coFile)
    {
        return view('survey_module.compensation.co_file_register', ['coFile' => $coFile, 'nextNo' => null,
            'surveyors' => $coFile->source === SurveyCoFile::SOURCE_CAPTURED ? $this->surveyors() : collect()]);
    }

    public function update(Request $r, SurveyCoFile $coFile)
    {
        $capture = $coFile->source === SurveyCoFile::SOURCE_CAPTURED;
        $data = $this->validated($r, $coFile, $capture);

        // A commissioned number is the system's; only a captured one may be corrected.
        if (! $capture) unset($data['co_file_no']);

        $coFile->update($data);

        return redirect()
            ->route(self::listRoute($coFile->source))
            ->with('success', "CO file {$coFile->co_file_no} updated.");
    }

    public function destroy(SurveyCoFile $coFile)
    {
        if ($n = $coFile->projects()->count()) {
            return back()->with('error',
                "{$coFile->co_file_no} holds {$n} project(s) and cannot be deleted. Set its status to Closed instead.");
        }

        $no = $coFile->co_file_no;
        $coFile->delete();

        return back()->with('success', "CO file {$no} deleted.");
    }

    /**
     * The related SIT file picked in the selector: is it indexed, and what do the
     * records hold for it (title, plot, district, LGA), from its file_indexings row
     * matched on the file-number string, else its fileNumber row.
     */
    public function relatedLookup(Request $r)
    {
        $number = trim(mb_substr((string) $r->query('file_number', ''), 0, 50));
        if ($number === '') {
            return response()->json(['indexed' => false, 'message' => 'No file number given.'], 422);
        }

        $db  = DB::connection('sqlsrv');
        $out = ['file_number' => $number, 'indexed' => false, 'known' => false,
                'title' => null, 'plot' => null, 'district' => null, 'lga' => null];
        $live = fn ($q) => $q->whereNull('is_deleted')->orWhere('is_deleted', 0);

        if ($fi = $db->table('file_indexings')->where('file_number', $number)->where($live)
            ->orderByDesc('id')->first(['file_title', 'plot_number', 'district', 'lga'])) {
            $out = array_merge($out, ['indexed' => true, 'known' => true, 'title' => $fi->file_title,
                'plot' => $fi->plot_number, 'district' => $fi->district, 'lga' => $fi->lga]);
        } elseif ($fn = $db->table('fileNumber')->where('mlsfNo', $number)->where($live)
            ->orderByDesc('id')->first(['FileName', 'plot_no', 'lga'])) {
            $out = array_merge($out, ['known' => true, 'title' => $fn->FileName, 'plot' => $fn->plot_no, 'lga' => $fn->lga]);
        }

        return response()->json(array_map(fn ($v) => is_string($v) ? (trim($v) === '' ? null : trim($v)) : $v, $out));
    }

    /**
     * A CO number picked in the global file number selector's CO tab: is it in
     * KLAES, and which file is it. Used by the CO file picker on the project and
     * capture forms.
     */
    public function numberLookup(Request $r)
    {
        $no = SurveyCoFile::normaliseNumber((string) $r->query('co_file_no', ''));
        if (! $no) {
            return response()->json(['valid' => false, 'message' => 'Not a CO number; use CO-serial-year, e.g. CO-9-2018.'], 422);
        }
        $year = (int) substr($no, strrpos($no, '-') + 1);
        $f = SurveyCoFile::where('co_file_no', $no)->withCount('projects')->first();

        return response()->json([
            'valid'    => $year >= SurveyCoFile::FIRST_YEAR && $year <= (int) date('Y'),
            'number'   => $no,
            'exists'   => (bool) $f,
            'id'       => $f?->id,
            'title'    => $f?->display_title,
            'status'   => $f?->status,
            'projects' => $f ? (int) $f->projects_count : null,
        ]);
    }

    /** Every CO file for the global file number selector's CO dropdown, newest year first. */
    public function options()
    {
        $rows = SurveyCoFile::with('projects:id,survey_co_file_id,plan_no')
            ->get(['id', 'co_file_no', 'title', 'status'])
            ->map(fn ($f) => [
                'number'   => $f->co_file_no,
                'title'    => $f->display_title,
                'status'   => $f->status,
                'projects' => $f->projects->count(),
                'tp'       => implode(', ', $f->tpNumbers()),
                'sort'     => preg_match('/^CO-(\d+)-(\d{4})$/', (string) $f->co_file_no, $m) ? $m[2] * 10000000 + $m[1] : 0,
            ])
            ->sortByDesc('sort')->values()
            ->map(fn ($r) => array_diff_key($r, ['sort' => 1]));

        return response()->json($rows);
    }

    private function validated(Request $r, ?SurveyCoFile $existing, bool $capture): array
    {
        $hasSitFile = $r->boolean('has_sit_file');
        // Old CO Register on (user, 2026-11-03): no validation on Has Related File, File / Job Title or District;
        // the old books often lack them.
        $oldReg = $capture && $r->boolean('old_register');
        $kinds = array_keys(self::RELATED_KINDS);
        if (! $hasSitFile) {
            $r->merge(array_fill_keys(array_merge(['related_file_no'], $kinds), null));
        }
        // At least one of SIT, GKN and Misc KN when Has Related File is Yes (user, 2026-11-02).
        if (! $oldReg && $hasSitFile && ! collect($kinds)->contains(fn ($k) => trim((string) $r->input($k)) !== '')) {
            throw ValidationException::withMessages(['related_sit_file_no' => 'Has Related File is Yes: select at least one related file (SIT, Misc KN, GKN, RDKN or MLPP), or turn it off.']);
        }
        $rules = [
            'title'           => ($oldReg ? 'nullable' : 'required') . '|string|max:255',
            'commission_date' => $capture ? 'nullable|date' : 'required|date',
            'status'          => ['required', Rule::in(SurveyCoFile::statusesFor($capture ? SurveyCoFile::SOURCE_CAPTURED : SurveyCoFile::SOURCE_COMMISSIONED))],
            'remarks'         => 'nullable|string|max:4000',
            'has_sit_file'    => 'nullable|boolean',
            // Required when Has Related File is checked; selected from the global selector: SIT-YEAR-SERIAL, or
            // GKN-SERIAL / MISC KN SERIAL from the Survey tab (CR 30102026 item 1).
        ] + collect(self::RELATED_KINDS)->map(fn ($k) => ['nullable', 'string', 'max:50', 'regex:' . $k[1]])->all()
          + AddressBuilder::rules('prop_');
        if ($oldReg) $rules['prop_district'] = 'nullable|array';

        if ($capture) {
            // The paper file's own number: Prefix CO + Serial No + Year (checked below).
            // co_file_no in one piece is still accepted.
            if ($r->filled('co_serial') || $r->filled('co_year')) {
                $r->merge(['co_file_no' => 'CO-' . trim((string) $r->input('co_serial')) . '-' . trim((string) $r->input('co_year'))
                    . ($r->boolean('co_temporary') ? '(T)' : '')]);
            }
            $rules['co_file_no'] = ['required', 'string', 'max:50'];
            $rules['co_serial'] = ['nullable', 'regex:/^\d{1,7}$/'];
            $rules['co_year'] = ['nullable', 'integer', 'min:' . SurveyCoFile::FIRST_YEAR, 'max:' . date('Y')];
            // Old CO Register section (user, 2026-11-03): the ledger's other columns, all optional, as written.
            $rules += [
                'old_file_ref'       => 'nullable|string|max:150',
                'approx_area_ha'     => 'nullable|numeric|min:0|max:99999999',
                'farms_count'        => 'nullable|integer|min:0|max:1000000',
                'date_received'      => 'nullable|date',
                'surveyor'           => 'nullable|string|max:20',
                'surveyor_other'     => ['nullable', 'string', 'max:255', Rule::requiredIf(fn () => $r->input('surveyor') === 'other')],
                'fieldwork_from'     => 'nullable|date',
                'fieldwork_to'       => 'nullable|date',
                'plan_completed_on'  => 'nullable|date',
                'total_amount'       => 'nullable|numeric|min:0|max:999999999999',
                'financial_file_ref' => 'nullable|string|max:100',
                'despatched_on'      => 'nullable|date',
                'paid_on'            => 'nullable|date',
            ];
        }

        $data = $r->validate($rules, [
            'co_file_no.required'             => 'Enter the existing CO file number.',
            'co_serial.regex'                 => 'The serial number is digits only, e.g. 9.',
            'co_year.min'                     => 'The year must be ' . SurveyCoFile::FIRST_YEAR . ' or later.',
            'co_year.max'                     => 'The year cannot be in the future.',
            'related_sit_file_no.regex'       => 'The SIT field takes a SIT file number, e.g. SIT-2024-15.',
            'related_gkn_file_no.regex'       => 'The GKN field takes a GKN file number, e.g. GKN-120.',
            'related_misckn_file_no.regex'    => 'The Misc KN field takes a Misc KN file number, e.g. MISC KN 45.',
            'related_rdkn_file_no.regex'      => 'The RDKN field takes an RDKN file number, e.g. RDKN-12.',
            'related_mlpp_file_no.regex'      => 'The MLPP field takes an MLPP file number, e.g. RES-2019-123.',
            'prop_district.required'          => 'The district is required.',
            'prop_district_other.required_if' => 'Please specify the district.',
            'prop_street_other.required_if'   => 'Please specify the street.',
            'prop_lga.required'               => 'The LGA is required.',
            'surveyor_other.required'         => 'Old CO Register: type the surveyor\'s name.',
            'approx_area_ha.numeric'          => 'Old CO Register: the approx. area is a number of hectares, e.g. 0.52.',
            'farms_count.integer'             => 'Old CO Register: the number of farms is a whole number.',
            'total_amount.numeric'            => 'Old CO Register: the total amount is a number, e.g. 41.60.',
        ]);

        if ($capture) {
            $no = SurveyCoFile::normaliseNumber($data['co_file_no']);
            $year = $no ? (int) substr($no, strrpos($no, '-') + 1) : null;
            if (! $no || $year < SurveyCoFile::FIRST_YEAR || $year > (int) date('Y')) {
                throw ValidationException::withMessages(['co_file_no' => 'Enter the CO file number as CO-serial-year, e.g. CO-9-2018.']);
            }
            $taken = SurveyCoFile::withTrashed()->where('co_file_no', $no)
                ->when($existing, fn ($q) => $q->where('id', '!=', $existing->id))->exists();
            if ($taken) {
                throw ValidationException::withMessages(['co_file_no' => "CO file {$no} is already registered."]);
            }
            $data['co_file_no'] = $no;
        }
        unset($data['co_serial'], $data['co_year'], $data['co_temporary']);
        if ($capture) $data = $this->oldRegisterValues($data);
        unset($data['has_sit_file']);
        // The column cannot be empty: an untitled old-register file is stored with a blank title.
        $data['title'] = trim(preg_replace('/\s+/', ' ', (string) ($data['title'] ?? '')));
        // Identifiers: exact format, upper-case prefix only. related_file_no keeps the first of the
        // three for what already reads it (listing title, commissioning sheet, search).
        foreach ($kinds as $k) {
            $v = trim((string) ($data[$k] ?? ''));
            $data[$k] = $hasSitFile && $v !== ''
                ? preg_replace(['/^sit-/i', '/^gkn-/i', '/^misc kn /i', '/^rdkn-/i', '/\(t\)$/'], ['SIT-', 'GKN-', 'MISC KN ', 'RDKN-', '(T)'], $v)
                : null;
        }
        $data['related_file_no'] = $data['related_sit_file_no'] ?? $data['related_gkn_file_no'] ?? $data['related_misckn_file_no'] ?? $data['related_rdkn_file_no'] ?? $data['related_mlpp_file_no'];

        return $data;
    }

    /**
     * Old CO Register section: references kept as written (spaces tidied), empty values stored as NULL (an empty
     * date would read as 1900-01-01), and the surveyor from the list or typed under Other (taken as that staff
     * member if the name is on the list; the 1970s surveyors mostly are not).
     */
    private function oldRegisterValues(array $data): array
    {
        foreach (SurveyCoFile::OLD_REGISTER_FIELDS as $k) {
            if (! array_key_exists($k, $data)) continue;
            $v = is_string($data[$k]) ? trim(preg_replace('/\s+/', ' ', $data[$k])) : $data[$k];
            $data[$k] = $v === '' ? null : $v;
        }
        // The section switched off sends nothing: the file's ledger details stay as they are.
        if (! array_key_exists('surveyor', $data)) {
            unset($data['surveyor_other']);

            return $data;
        }
        $choice = (string) ($data['surveyor'] ?? '');
        [$staffId, $name] = [null, null];
        if ($choice === 'other') {
            $name = trim(preg_replace('/\s+/', ' ', (string) ($data['surveyor_other'] ?? '')));
            $match = \App\Models\Survey\SurveyorListEntry::all()->first(fn ($s) => mb_strtolower($s->full_name) === mb_strtolower($name));
            [$staffId, $name] = [$match?->id, $match?->full_name ?? $name];
        } elseif ($choice !== '') {
            $s = \App\Models\Survey\SurveyorListEntry::find((int) $choice);
            if (! $s) throw ValidationException::withMessages(['surveyor' => 'Old CO Register: choose the surveyor from the list, or Other.']);
            [$staffId, $name] = [$s->id, $s->full_name];
        }
        unset($data['surveyor'], $data['surveyor_other']);
        $data['surveyors_list_id'] = $staffId;
        $data['surveyor_name'] = $name;

        return $data;
    }

    private function surveyors()
    {
        return \App\Models\Survey\SurveyorListEntry::query()->where(fn ($q) => $q->whereNull('is_active')->orWhere('is_active', 1))
            ->orderBy('first_name')->orderBy('surname')->get(['id', 'first_name', 'surname', 'other_name', 'rank']);
    }
}
