<?php

namespace App\Http\Controllers\Survey;

use App\Http\Controllers\Controller;
use App\Models\Survey\SurveyorListEntry;
use App\Models\Survey\SurveyPlanUpload as Plan;
use App\Services\Survey\CompensationListReader;
use App\Services\Survey\SurveyPlanUploads;
use App\Services\Survey\SurveyPlanStages as Stages;
use App\Models\Survey\SurveyPlanFile as PlanFile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

/**
 * Survey Plan Upload (Survey Update 5g-5i, user 2026-11-10). One page per place on the sidebar:
 *   Survey > Survey Plan Upload                                         every upload, with the short form;
 *   GKN > Checking & Passing (Survey Plan) > Uploaded Survey Plan       those sent to GKN;
 *   Examination/Verification > Checking & Passing > Uploaded Survey Plan  those sent to Examination/Verification.
 */
class SurveyPlanUploadController extends Controller
{
    use \App\Http\Controllers\Survey\Concerns\ExportsRecords;

    public function __construct(private SurveyPlanUploads $plans)
    {
    }

    public function index(Request $r)
    {
        return $this->page($r, null);
    }

    public function gkn(Request $r)
    {
        return $this->page($r, Plan::UNIT_GKN);
    }

    public function examination(Request $r)
    {
        return $this->page($r, Plan::UNIT_EV);
    }

    /** SG/DSG > Signing and Upload (Survey Plan): plans at stage 3 (to sign) and 4 (signed). CR 11112026-2. */
    public function signing(Request $r)
    {
        abort_unless(Stages::isSigner(Auth::user()), 403, 'This page is for the Surveyor General and the DSG.');

        return $this->page($r, null, 'signing');
    }

    /** MLPP-SG Portal Admin > Uploaded Survey: the plans private surveyors sent from the portal. CR 11112026-2. */
    public function portalPlans(Request $r)
    {
        abort_unless(Stages::isSigner(Auth::user()), 403, 'This page is for the Surveyor General and the DSG.');

        return $this->page($r, null, 'portal');
    }

    private function page(Request $r, ?string $unit, ?string $scope = null)
    {
        abort_unless(Plan::installed(), 404);

        $base = $this->scoped(Plan::query(), $unit, $scope);

        return view('survey_module.survey_plans.index', [
            'unit'      => $unit,
            'listScope' => $scope,
            // Survey Plan Management (Recording 5, 2026-11-16): a table each for Manual Upload and Digital Plotting.
            'method'    => $this->method($r),
            'methodCounts' => [
                Plan::METHOD_MANUAL  => (clone $base)->ofMethod(Plan::METHOD_MANUAL)->count(),
                Plan::METHOD_DIGITAL => (clone $base)->ofMethod(Plan::METHOD_DIGITAL)->count(),
            ],
            'rows'      => $this->listQuery($r, $unit, $scope)
                ->when(\App\Models\Survey\SurveyApplicationPlan::installed(), fn ($q) => $q->with('applicationPlan'))
                ->with('files')   // the row menu's plotted data (2026-11-18)
                ->paginate(25)->withQueryString(),
            'term'      => trim((string) $r->query('q', '')),
            'stats'     => [
                'total'   => (clone $base)->count(),
                'final'   => (clone $base)->where('plan_type', 'Final Survey Plan')->count(),
                'app'     => (clone $base)->where('plan_type', 'Application Plan')->count(),
                'portal'  => (clone $base)->where('source', 'portal')->count(),
            ],
            'districts' => LookupController::options('districts'),
            'lgas'      => CompensationListReader::LGAS,
            'surveyors' => $this->surveyors(),
        ]);
    }

    private function surveyors()
    {
        return SurveyorListEntry::query()->where('is_active', true)->orderBy('first_name')->orderBy('surname')->get(['id', 'first_name', 'surname', 'other_name', 'rank']);
    }

    /** The list's tab: Manual Upload (default) or Digital Plotting. */
    private function method(Request $r): string
    {
        return $r->query('method') === Plan::METHOD_DIGITAL ? Plan::METHOD_DIGITAL : Plan::METHOD_MANUAL;
    }

    /**
     * Digital Plotting (Recording 5, 2026-11-16): its own page, for the room the map needs. 1 coordinate system,
     * 2 the beacons (X / Y or traverse; the first is the reference), 3 map, 4 plan details (no file), 5 export.
     */
    public function digital()
    {
        abort_unless(Plan::installed(), 404);

        return view('survey_module.survey_plans.digital', [
            'districts' => LookupController::options('districts'),
            'lgas'      => CompensationListReader::LGAS,
            'surveyors' => $this->surveyors(),
        ]);
    }

    public function store(Request $r)
    {
        abort_unless(Plan::installed(), 404);
        $method = $r->input('plan_method') === Plan::METHOD_DIGITAL ? Plan::METHOD_DIGITAL : Plan::METHOD_MANUAL;
        $plan = $this->plans->store($this->plans->validated($r, true, $method), $r->file('plan_file'), Auth::user());

        return redirect()->route('survey-module.survey-plans.index', ['method' => $method])
            ->with('success', "{$plan->upload_ref} " . ($plan->isDigital() ? 'plotted' : 'uploaded') . " and sent to {$plan->unit}.");
    }

    /** The plan file, from the private disk. */
    public function file(Plan $plan)
    {
        // A Digital Plotting without a file opens on the map; its file is optional (2026-11-16).
        if ($plan->isDigital() && ! $plan->file_path) {
            return redirect()->route('survey-module.survey-plans.check-plan', $plan);
        }
        abort_unless($plan->file_path && Storage::disk(Plan::DISK)->exists($plan->file_path), 404);

        return Storage::disk(Plan::DISK)->response($plan->file_path, $plan->file_name ?: basename($plan->file_path), [
            'Cache-Control' => 'private, max-age=3600',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /** A unit's list, the SG/DSG signing list (stage 3 and 4) or the portal's uploads. */
    private function scoped($q, ?string $unit, ?string $scope)
    {
        return $q->when($unit, fn ($w) => $w->where('unit', $unit))
            ->when($scope === 'signing', fn ($w) => $w->where('stage', '>=', Plan::STAGE_FINAL))
            ->when($scope === 'portal', fn ($w) => $w->where('source', 'portal'));
    }

    /** The list's filters and order, shared with Export Records. */
    private function listQuery(Request $r, ?string $unit, ?string $scope = null)
    {
        $term = trim(preg_replace('/\s+/', ' ', (string) $r->query('q', '')));
        $like = '%' . str_replace(['[', '%', '_'], ['[[]', '[%]', '[_]'], $term) . '%';

        return $this->scoped(Plan::query(), $unit, $scope)
            ->ofMethod($this->method($r))
            ->when(ctype_digit((string) $r->query('stage')), fn ($q) => $q->where('stage', (int) $r->query('stage')))
            ->when(! $unit && in_array($r->query('unit'), array_keys(Plan::UNITS), true), fn ($q) => $q->where('unit', $r->query('unit')))
            ->when(in_array($r->query('type'), Plan::TYPES, true), fn ($q) => $q->where('plan_type', $r->query('type')))
            ->when(in_array($r->query('source'), ['klaes', 'portal'], true), fn ($q) => $q->where('source', $r->query('source')))
            ->when($term !== '', fn ($q) => $q->where(fn ($w) => $w->where('upload_ref', 'like', $like)->orWhere('plot_no', 'like', $like)
                ->orWhere('district', 'like', $like)->orWhere('lga', 'like', $like)->orWhere('drawn_by', 'like', $like)->orWhere('uploaded_by_name', 'like', $like)))
            ->orderByDesc('created_at')->orderByDesc('id');
    }

    public function export(Request $r)
    {
        $unit = match ($r->query('scope')) { 'gkn' => Plan::UNIT_GKN, 'ev' => Plan::UNIT_EV, default => null };
        $scope = in_array($r->query('scope'), ['signing', 'portal'], true) ? $r->query('scope') : null;

        return $this->exportRecords($r, fn () => $this->listQuery($r, $unit, $scope), [
            'ref' => 'Upload Ref', 'method' => 'Method', 'type' => 'Type', 'unit' => 'Sent To', 'plot_no' => 'Plot No.', 'district' => 'District', 'lga' => 'LGA',
            'location' => 'Location', 'drawn_by' => 'Drawn By', 'source' => 'From', 'uploaded_by' => 'Uploaded By', 'uploaded' => 'Uploaded', 'status' => 'Status', 'stage' => 'Stage',
        ], fn ($x) => [
            'ref' => $x->upload_ref, 'method' => $x->method_label, 'type' => $x->plan_type, 'unit' => $x->unit, 'plot_no' => $x->plot_no, 'district' => $x->district, 'lga' => $x->lga,
            'location' => $x->location, 'drawn_by' => $x->drawn_by, 'source' => $x->source_label, 'uploaded_by' => $x->uploaded_by_name,
            'uploaded' => $this->exportDate($x->created_at), 'status' => $x->status, 'stage' => $x->step_no . ' ' . $x->stage_label,
        ], 'created_at');
    }

    /* ------------------------------------------------------------------------------------------------
     | The plan's own page and its four stages (CR 11112026-2, item 5).
     * ------------------------------------------------------------------------------------------------ */

    /** Survey Plan Check, blank: the coordinate system and reference coordinate entered by hand (user, 2026-11-12). */
    public function check()
    {
        return view('survey_module.survey_plans.check', ['plan' => null, 'prefill' => null]);
    }

    /**
     * Survey Plan Check filled in from an upload: its coordinate system and reference coordinate. Plans uploaded
     * before one reference was asked for bring their first beacon as the reference and the rest as other beacons.
     */
    public function checkPlan(Plan $plan)
    {
        $plan->load('referenceBeacons');
        $row = fn ($b) => ['beacon_no' => (string) $b->beacon_no, 'x' => (string) $b->coord_x, 'y' => (string) $b->coord_y, 'source' => (string) $b->source];
        $beacons = $plan->referenceBeacons->values();
        $place = collect([$plan->plot_no ? 'plot ' . $plan->plot_no : null, $plan->district, $plan->lga])->filter()->implode(', ');
        if ($plan->isDigital()) {
            $pd = (array) $plan->plot_data;

            return view('survey_module.survey_plans.check', ['plan' => $plan, 'prefill' => [
                'sys'    => $plan->coord_system,
                'ref'    => $beacons->isNotEmpty() ? $row($beacons->first()) : null,
                'others' => $beacons->map($row)->values()->all(),
                'closed' => ! empty($pd['closed']),
                'note'   => 'Digital plotting <b>' . e($plan->upload_ref) . '</b> (' . e($plan->plan_type) . ($place !== '' ? ', ' . e($place) : '') . '): '
                    . $beacons->count() . ' beacons as plotted' . (($pd['mode'] ?? '') === 'brg' ? ' by bearing and distance' : '') . ', in traverse order.',
            ]]);
        }
        $note = 'Filled in from upload <b>' . e($plan->upload_ref) . '</b> (' . e($plan->plan_type) . ($place !== '' ? ', ' . e($place) : '') . ')'
            . ($plan->coord_system
                ? ': the coordinate system and the reference coordinate are as the uploader entered them. Add the other beacons under the map.'
                : '. It was uploaded before the coordinate system was asked for: choose the system its X / Y are in.');

        return view('survey_module.survey_plans.check', ['plan' => $plan, 'prefill' => [
            'sys'    => $plan->coord_system,
            'ref'    => $beacons->isNotEmpty() ? $row($beacons->first()) : null,
            'others' => $beacons->slice(1)->map($row)->values()->all(),
            'note'   => $note,
        ]]);
    }

    /**
     * The Ministry's Application Plan (A4), filled from the upload (user, 2026-11-17): its particulars, and in the big
     * frame the parcel plotted to scale over the map. Blanks print as lines to fill by hand.
     */
    public function applicationPlan(Plan $plan)
    {
        $plan->load(['referenceBeacons', 'files']);

        // The uploaded plan shows in the frame (user, 2026-11-17): the newest copy (signed, then final, then the
        // upload itself) that is an image or a PDF. TIFF scans cannot be shown by a browser.
        $kind = fn (?string $mime, ?string $name) => match (true) {
            str_starts_with((string) $mime, 'image/') && ! str_contains((string) $mime, 'tif') => 'image',
            $mime === 'application/pdf' || str_ends_with(strtolower((string) $name), '.pdf') => 'pdf',
            default => null,
        };
        $planImage = null;
        foreach ([PlanFile::KIND_SIGNED, PlanFile::KIND_FINAL] as $k) {
            $f = $plan->latestFile($k);
            if ($f && $f->file_path && ($t = $kind($f->file_mime, $f->file_name))) {
                $planImage = ['url' => route('survey-module.survey-plans.stage-file', [$plan, $f]), 'type' => $t, 'name' => $f->file_name];
                break;
            }
        }
        if (! $planImage && $plan->file_path && ($t = $kind($plan->file_mime, $plan->file_name))) {
            $planImage = ['url' => route('survey-module.survey-plans.file', $plan), 'type' => $t, 'name' => $plan->file_name];
        }

        $ap = \App\Models\Survey\SurveyApplicationPlan::entriesFor($plan);
        // The QR at the top right (user, 2026-11-17): readable text plus the plan's page in KLAES.
        $qrPayload = collect([
            'KLAES Application Plan',
            'Ref: ' . $plan->upload_ref,
            $ap['plan_no'] !== '' ? 'Plan No: ' . $ap['plan_no'] : null,
            $ap['plot_no'] !== '' ? 'Plot No: ' . $ap['plot_no'] : null,
            $ap['location'] !== '' ? 'Location: ' . $ap['location'] : null,
            route('survey-module.survey-plans.show', $plan),
        ])->filter()->implode("\n");

        return view('survey_module.survey_plans.application_plan', [
            'planImage' => $planImage,
            'qrPayload' => $qrPayload,
            'ap' => $ap,
            'beacons' => $plan->referenceBeacons
                ->map(fn ($b) => ['no' => (string) $b->beacon_no, 'e' => $b->coord_x, 'n' => $b->coord_y])->all(),
            'coordSystem' => $plan->coord_system ?: '26332',
            'coordSystemLabel' => $plan->coord_system_label ?: Plan::COORD_SYSTEMS['26332'],
        ]);
    }

    /**
     * Save the Application Plan pop-up (user, 2026-11-17), then open the printout. Text is tidied per field
     * (development standard 3): names capitalised word by word, numbers and references kept as typed.
     */
    public function saveApplicationPlan(Request $r, Plan $plan)
    {
        $AP = \App\Models\Survey\SurveyApplicationPlan::class;
        abort_unless($AP::installed(), 404);
        $rules = [];
        foreach (array_keys($AP::FIELDS) as $k) {
            $rules[$k] = ['nullable', 'string', 'max:' . (in_array($k, ['address', 'location', 'purpose'], true) ? 300 : 120)];
        }
        $rules['scale'] = ['nullable', 'in:' . implode(',', $AP::SCALES)];
        $rules['ground_open'] = ['nullable', 'in:Yes,No'];
        $rules['date'] = ['nullable', 'date'];
        // Purpose and Plan No. are dropdowns with one Other + specify (user, 2026-11-17; development standard 6).
        $planNos = $AP::planNoChoices();
        $rules['purpose'] = ['nullable', \Illuminate\Validation\Rule::in([...$AP::PURPOSES, $AP::OTHER])];
        $rules['purpose_other'] = ['nullable', 'required_if:purpose,' . $AP::OTHER, 'string', 'max:300'];
        $rules['plan_no'] = ['nullable', \Illuminate\Validation\Rule::in([...$planNos, $AP::OTHER])];
        $rules['plan_no_other'] = ['nullable', 'required_if:plan_no,' . $AP::OTHER, 'string', 'max:120'];
        // Surveyed by / Checked by / Passed by: the Survey staff list, or Other + specify (user, 2026-11-18). A name saved
        // before (title case) still counts as listed.
        $people = $AP::peopleChoices();
        $peopleKeys = array_map('mb_strtolower', $people);
        foreach ($AP::PEOPLE_FIELDS as $k) {
            $rules[$k] = ['nullable', 'string', 'max:120', function ($attr, $v, $fail) use ($peopleKeys, $AP) {
                if ($v !== $AP::OTHER && ! in_array(mb_strtolower(trim((string) $v)), $peopleKeys, true)) $fail('Choose a name from the list, or Other.');
            }];
            $rules[$k . '_other'] = ['nullable', 'required_if:' . $k . ',' . $AP::OTHER, 'string', 'max:120'];
        }
        $rules['amount_paid'] = ['nullable', 'numeric', 'min:0', 'max:999999999999'];
        $d = $r->validate($rules, [
            'surveyed_by_other.required_if' => 'Specify who surveyed it.',
            'checked_by_other.required_if' => 'Specify who checked it.',
            'passed_by_other.required_if' => 'Specify who passed it.',
            'scale.in' => 'Choose a scale from the list, or Auto.',
            'amount_paid.numeric' => 'Amount Paid must be a number.',
            'date.date' => 'Date must be a date.',
            'purpose.in' => 'Choose the purpose from the list, or Other.',
            'purpose_other.required_if' => 'Specify the purpose.',
            'plan_no.in' => 'Choose the Plan No. from the list, or Other.',
            'plan_no_other.required_if' => 'Specify the Plan No.',
        ], $AP::FIELDS + ['purpose_other' => 'Specify purpose', 'plan_no_other' => 'Specify Plan No.', 'surveyed_by_other' => 'Specify Surveyed by', 'checked_by_other' => 'Specify Checked by', 'passed_by_other' => 'Specify Passed by']);
        foreach ($AP::PEOPLE_FIELDS as $k) {
            if (($d[$k] ?? null) === $AP::OTHER) $d[$k] = $AP::matchChoice(\App\Services\CadastralDept\Normalise::text($d[$k . '_other']), $people);
        }
        // Other: the typed value is kept; one that matches a listed choice (any case or spacing) becomes that choice.
        if (($d['purpose'] ?? null) === $AP::OTHER) {
            $d['purpose'] = $AP::matchChoice(\App\Services\CadastralDept\Normalise::text($d['purpose_other']), $AP::PURPOSES);
        }
        if (($d['plan_no'] ?? null) === $AP::OTHER) {
            $d['plan_no'] = $AP::matchChoice(\App\Services\CadastralDept\Normalise::text($d['plan_no_other']), $planNos);
        }

        $N = \App\Services\CadastralDept\Normalise::class;
        $fields = [];
        foreach (array_keys($AP::FIELDS) as $k) {
            $v = $N::text($d[$k] ?? null);
            // People's names in title case (initials kept), the Survey module's rule; Rank keeps acronyms (SSG, DSG).
            $fields[$k] = match (true) {
                in_array($k, ['applicant', 'surveyed_by', 'drawn_by', 'checked_by', 'passed_by'], true) => app(\App\Services\Survey\CompensationListReader::class)->personName($v),
                $k === 'rank' => $N::name($v),
                default => $v,
            };
        }
        // The "lies on" entries only count when the ground is not open.
        if ($fields['ground_open'] !== 'No') {
            foreach ($AP::LIES_ON as $k) $fields[$k] = '';
        }

        $row = $AP::query()->firstOrNew(['survey_plan_upload_id' => $plan->id]);
        $before = $row->exists ? $row->fields : null;
        $row->fields = $fields;
        $row->save();
        \Illuminate\Support\Facades\Log::info('Application Plan details saved', ['plan' => $plan->upload_ref, 'by' => Auth::id(), 'before' => $before, 'after' => $fields]);

        return redirect()->route('survey-module.survey-plans.application-plan', $plan);
    }

    public function show(Plan $plan)
    {
        $plan->load(['files', 'referenceBeacons']);
        $u = Auth::user();

        return view('survey_module.survey_plans.show', [
            'plan'     => $plan,
            'canCheck' => Stages::isChecker($u, $plan),
            'canSign'  => Stages::isSigner($u, $plan),
            'canFinal' => Stages::isStaffUploader($u, $plan),
            'traverse' => app(\App\Services\Survey\PlanTraverseSheet::class)->lines($plan),
        ]);
    }

    /** Stage 2, Check (Kunle, 2026-11-18): falls on location or not, corrections or none; back to the surveyor. */
    public function corrections(Request $r, Plan $plan, Stages $stages)
    {
        abort_unless(Stages::isChecker(Auth::user(), $plan), 403, 'Only the checking unit can check a plan, and never its own uploader.');
        $r->validate([
            'check_location'  => ['required', \Illuminate\Validation\Rule::in(array_keys(Plan::CHECK_LOCATIONS))],
            'check_result'    => ['required', \Illuminate\Validation\Rule::in(array_keys(Plan::CHECK_RESULTS))],
            'note'            => 'nullable|required_if:check_result,corrections|string|max:2000',
            'markups'         => 'array|max:20',
            'correction_file' => 'nullable|file|max:25600',
        ], [
            'check_location.required' => 'Say whether the map falls on location.',
            'check_result.required'   => 'Say whether there are corrections.',
            'note.required_if'        => 'Say what has to be corrected.',
        ]);
        if ($r->input('check_result') === 'corrections' && $r->hasFile('correction_file')) $stages->checkFile($r->file('correction_file'), 'correction_file');
        $stages->check($plan, Auth::user(), (string) $r->input('check_result'), (string) $r->input('check_location'), $r->input('note'),
            (array) $r->input('markups', []), $r->file('correction_file'));

        return redirect()->route('survey-module.survey-plans.show', $plan)->with('success', "{$plan->upload_ref} checked and sent back to {$plan->uploaded_by_name} for the final survey plan.");
    }

    /** The old "No corrections" button: now the same check, without corrections. */
    public function pass(Request $r, Plan $plan, Stages $stages)
    {
        $r->merge(['check_result' => 'none']);

        return $this->corrections($r, $plan, $stages);
    }

    public function final(Request $r, Plan $plan, Stages $stages)
    {
        abort_unless(Stages::isStaffUploader(Auth::user(), $plan), 403, 'Only the surveyor who uploaded the plan can send the final survey plan.');
        $r->validate(['final_file' => 'required|file|max:25600', 'produced' => 'nullable|in:manual,digital', 'note' => 'nullable|string|max:2000'],
            ['final_file.required' => 'Choose the final survey plan.']);
        $how = ['manual' => 'Produced manually (other software)', 'digital' => 'Produced by digital plotting'][$r->input('produced')] ?? null;
        $note = collect([$how, trim((string) $r->input('note')) ?: null])->filter()->implode('. ');
        $stages->uploadFinal($plan, $r->file('final_file'), Auth::user(), null, $note ?: null);

        return redirect()->route('survey-module.survey-plans.show', $plan)->with('success', "Final survey plan {$plan->upload_ref} sent to the SG/DSG.");
    }

    public function sign(Request $r, Plan $plan, Stages $stages)
    {
        abort_unless(Stages::isSigner(Auth::user(), $plan), 403, 'Only the SG or DSG can sign, and never their own upload.');
        $r->validate(['signing_method' => 'required|in:digital,manual', 'signed_file' => 'required|file|max:25600', 'note' => 'nullable|string|max:2000'],
            ['signed_file.required' => 'Upload the signed copy.', 'signing_method.required' => 'Choose Digital or Manual.']);
        $stages->sign($plan, Auth::user(), (string) $r->input('signing_method'), $r->file('signed_file'), $r->input('note'));

        return redirect()->route('survey-module.survey-plans.show', $plan)->with('success', "{$plan->upload_ref} is signed.");
    }

    /** A stage file (correction, final, signed). */
    public function stageFile(Plan $plan, PlanFile $file)
    {
        abort_unless((int) $file->survey_plan_upload_id === (int) $plan->id, 404);
        if ($file->file_path === PlanFile::GENERATED_TRAVERSE) return $this->traverse($plan);
        abort_unless($file->file_path && Storage::disk(Plan::DISK)->exists($file->file_path), 404);

        return Storage::disk(Plan::DISK)->response($file->file_path, $file->file_name ?: basename($file->file_path), [
            'Cache-Control' => 'private, max-age=3600',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /** A Digital Plotting's traverse with the reference beacon details, as CSV (Kunle, 2026-11-17). */
    public function traverse(Plan $plan)
    {
        abort_unless($plan->isDigital(), 404);

        return response(app(\App\Services\Survey\PlanTraverseSheet::class)->csv($plan), 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $plan->upload_ref . '-traverse.csv"',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    /** Add layer > Geodatabase (Recording 5, 2026-11-16): the zipped .gdb's catalog, its datasets and layers. */
    public function gdbCatalog(Request $r, \App\Services\Survey\GdbCatalog $catalog)
    {
        $r->validate(['gdb' => 'required|file|max:' . (\App\Services\Survey\GdbCatalog::MAX_MB * 1024)], ['gdb.max' => 'The geodatabase zip can be at most ' . \App\Services\Survey\GdbCatalog::MAX_MB . ' MB.']);
        try {
            return response()->json($catalog->open($r->file('gdb')));
        } catch (\InvalidArgumentException|\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /** The layers picked in the catalog, as GeoJSON in their own coordinates (the card converts them). */
    public function gdbCatalogRead(Request $r, \App\Services\Survey\GdbCatalog $catalog)
    {
        $d = $r->validate(['token' => 'required|string|max:40', 'layers' => 'required|array|max:50', 'layers.*' => 'string|max:160', 'bbox' => 'nullable|array|size:4', 'bbox.*' => 'numeric']);
        try {
            return response()->json($catalog->read($d['token'], $d['layers'], $d['bbox'] ?? null));
        } catch (\InvalidArgumentException|\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /** Reference beacon dropdown: captured beacons (with X / Y) and the Extant Beacons list. */
    public function beacons(Request $r)
    {
        return response()->json(['data' => $this->plans->searchBeacons((string) $r->query('q', ''))]);
    }

    /** Survey Plan Check > Export > Geodatabase (user, 2026-11-15): the card's features as a zipped File GDB. */
    public function gdb(Request $r, \App\Services\Survey\PlanCheckGdb $gdb)
    {
        $data = $r->validate(['epsg' => 'required|string|max:10', 'name' => 'nullable|string|max:60', 'layers' => 'required|array']);
        try {
            $zip = $gdb->make($data['layers'], $data['epsg'], $data['name'] ?? 'survey_plan');
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (\RuntimeException $e) {
            report($e);

            return response()->json(['message' => $e->getMessage()], 500);
        }

        return response()->download($zip, 'survey-plan-' . now()->format('YmdHi') . '-geodatabase.zip', ['Content-Type' => 'application/zip'])->deleteFileAfterSend();
    }
}
