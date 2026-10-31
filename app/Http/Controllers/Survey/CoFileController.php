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
    public const RELATED_FILE_PATTERN = '/^(SIT-\d{4}-\d+|GKN-\d+|MISC KN \d+)( ?\(T\))?$/i';

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
        $numbers = $coFiles->getCollection()->pluck('related_file_no')->filter()->unique()->values()->all();
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
            'coFile'   => $coFile,
            'nextNo'   => SurveyCoFile::nextNumber(),
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
        return view('survey_module.compensation.co_file_register', ['coFile' => $coFile, 'nextNo' => null]);
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
        if (! $hasSitFile) {
            $r->merge(['related_file_no' => null]);
        }
        $rules = [
            'title'           => 'required|string|max:255',
            'commission_date' => $capture ? 'nullable|date' : 'required|date',
            'status'          => ['required', Rule::in(SurveyCoFile::statusesFor($capture ? SurveyCoFile::SOURCE_CAPTURED : SurveyCoFile::SOURCE_COMMISSIONED))],
            'remarks'         => 'nullable|string|max:4000',
            'has_sit_file'    => 'nullable|boolean',
            // Required when Has Related File is checked; selected from the global selector: SIT-YEAR-SERIAL, or
            // GKN-SERIAL / MISC KN SERIAL from the Survey tab (CR 30102026 item 1).
            'related_file_no' => [$hasSitFile ? 'required' : 'nullable', 'string', 'max:50', 'regex:' . self::RELATED_FILE_PATTERN],
        ] + AddressBuilder::rules('prop_');

        if ($capture) {
            // The paper file's own number: Prefix CO + Serial No + Year (checked below).
            // co_file_no in one piece is still accepted.
            if ($r->filled('co_serial') || $r->filled('co_year')) {
                $r->merge(['co_file_no' => 'CO-' . trim((string) $r->input('co_serial')) . '-' . trim((string) $r->input('co_year'))]);
            }
            $rules['co_file_no'] = ['required', 'string', 'max:50'];
            $rules['co_serial'] = ['nullable', 'regex:/^\d{1,7}$/'];
            $rules['co_year'] = ['nullable', 'integer', 'min:' . SurveyCoFile::FIRST_YEAR, 'max:' . date('Y')];
        }

        $data = $r->validate($rules, [
            'co_file_no.required'             => 'Enter the existing CO file number.',
            'co_serial.regex'                 => 'The serial number is digits only, e.g. 9.',
            'co_year.min'                     => 'The year must be ' . SurveyCoFile::FIRST_YEAR . ' or later.',
            'co_year.max'                     => 'The year cannot be in the future.',
            'related_file_no.regex'           => 'The related file must be a SIT, GKN or Misc KN file number, e.g. SIT-2024-15, GKN-120 or MISC KN 45.',
            'related_file_no.required'        => 'Select a related file or turn off Has Related File.',
            'prop_district.required'          => 'The district is required.',
            'prop_district_other.required_if' => 'Please specify the district.',
            'prop_street_other.required_if'   => 'Please specify the street.',
            'prop_lga.required'               => 'The LGA is required.',
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
        unset($data['co_serial'], $data['co_year']);
        unset($data['has_sit_file']);
        $data['title'] = trim(preg_replace('/\s+/', ' ', $data['title']));
        // An identifier: exact format, upper-case prefix only.
        $data['related_file_no'] = $hasSitFile
            ? preg_replace(['/^sit-/i', '/^gkn-/i', '/^misc kn /i', '/\(t\)$/'], ['SIT-', 'GKN-', 'MISC KN ', '(T)'], trim((string) ($data['related_file_no'] ?? '')))
            : null;

        return $data;
    }
}
