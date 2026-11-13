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
            'rows'      => $this->listQuery($r, $unit, $scope)->paginate(25)->withQueryString(),
            'term'      => trim((string) $r->query('q', '')),
            'stats'     => [
                'total'   => (clone $base)->count(),
                'final'   => (clone $base)->where('plan_type', 'Final Survey Plan')->count(),
                'app'     => (clone $base)->where('plan_type', 'Application Plan')->count(),
                'portal'  => (clone $base)->where('source', 'portal')->count(),
            ],
            'districts' => LookupController::options('districts'),
            'lgas'      => CompensationListReader::LGAS,
            'surveyors' => SurveyorListEntry::query()->where('is_active', true)->orderBy('first_name')->orderBy('surname')->get(['id', 'first_name', 'surname', 'other_name', 'rank']),
        ]);
    }

    public function store(Request $r)
    {
        abort_unless(Plan::installed(), 404);
        $plan = $this->plans->store($this->plans->validated($r, true), $r->file('plan_file'), Auth::user());

        return redirect()->route('survey-module.survey-plans.index')
            ->with('success', "{$plan->upload_ref} uploaded and sent to {$plan->unit}.");
    }

    /** The plan file, from the private disk. */
    public function file(Plan $plan)
    {
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
            'ref' => 'Upload Ref', 'type' => 'Type', 'unit' => 'Sent To', 'plot_no' => 'Plot No.', 'district' => 'District', 'lga' => 'LGA',
            'location' => 'Location', 'drawn_by' => 'Drawn By', 'source' => 'From', 'uploaded_by' => 'Uploaded By', 'uploaded' => 'Uploaded', 'status' => 'Status', 'stage' => 'Stage',
        ], fn ($x) => [
            'ref' => $x->upload_ref, 'type' => $x->plan_type, 'unit' => $x->unit, 'plot_no' => $x->plot_no, 'district' => $x->district, 'lga' => $x->lga,
            'location' => $x->location, 'drawn_by' => $x->drawn_by, 'source' => $x->source_label, 'uploaded_by' => $x->uploaded_by_name,
            'uploaded' => $this->exportDate($x->created_at), 'status' => $x->status, 'stage' => $x->stage_no . ' ' . $x->stage_label,
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

    public function show(Plan $plan)
    {
        $plan->load(['files', 'referenceBeacons']);
        $u = Auth::user();

        return view('survey_module.survey_plans.show', [
            'plan'     => $plan,
            'canCheck' => Stages::isChecker($u, $plan),
            'canSign'  => Stages::isSigner($u),
            'canFinal' => Stages::isStaffUploader($u, $plan),
        ]);
    }

    public function corrections(Request $r, Plan $plan, Stages $stages)
    {
        abort_unless(Stages::isChecker(Auth::user(), $plan), 403, 'Only the checking unit can send corrections.');
        $r->validate(['note' => 'required|string|max:2000', 'markups' => 'array|max:20', 'correction_file' => 'nullable|file|max:25600'],
            ['note.required' => 'Say what has to be corrected.']);
        if ($r->hasFile('correction_file')) $stages->checkFile($r->file('correction_file'), 'correction_file');
        $stages->sendCorrections($plan, Auth::user(), (string) $r->input('note'), (array) $r->input('markups', []), $r->file('correction_file'));

        return redirect()->route('survey-module.survey-plans.show', $plan)->with('success', "Corrections sent back on {$plan->upload_ref}.");
    }

    public function pass(Request $r, Plan $plan, Stages $stages)
    {
        abort_unless(Stages::isChecker(Auth::user(), $plan), 403, 'Only the checking unit can pass a plan.');
        $stages->passToSigning($plan, Auth::user(), $r->input('note'));

        return redirect()->route('survey-module.survey-plans.show', $plan)->with('success', "{$plan->upload_ref} passed and sent to the SG/DSG for signing.");
    }

    public function final(Request $r, Plan $plan, Stages $stages)
    {
        abort_unless(Stages::isStaffUploader(Auth::user(), $plan), 403, 'Only the uploader can send the final copy.');
        $r->validate(['final_file' => 'required|file|max:25600', 'note' => 'nullable|string|max:2000'], ['final_file.required' => 'Choose the corrected plan.']);
        $stages->uploadFinal($plan, $r->file('final_file'), Auth::user(), null, $r->input('note'));

        return redirect()->route('survey-module.survey-plans.show', $plan)->with('success', "Final copy of {$plan->upload_ref} sent to the SG/DSG.");
    }

    public function sign(Request $r, Plan $plan, Stages $stages)
    {
        abort_unless(Stages::isSigner(Auth::user()), 403, 'Only the SG or DSG can sign.');
        $r->validate(['signing_method' => 'required|in:digital,manual', 'signed_file' => 'required|file|max:25600', 'note' => 'nullable|string|max:2000'],
            ['signed_file.required' => 'Upload the signed copy.', 'signing_method.required' => 'Choose Digital or Manual.']);
        $stages->sign($plan, Auth::user(), (string) $r->input('signing_method'), $r->file('signed_file'), $r->input('note'));

        return redirect()->route('survey-module.survey-plans.show', $plan)->with('success', "{$plan->upload_ref} is signed.");
    }

    /** A stage file (correction, final, signed). */
    public function stageFile(Plan $plan, PlanFile $file)
    {
        abort_unless((int) $file->survey_plan_upload_id === (int) $plan->id, 404);
        abort_unless($file->file_path && Storage::disk(Plan::DISK)->exists($file->file_path), 404);

        return Storage::disk(Plan::DISK)->response($file->file_path, $file->file_name ?: basename($file->file_path), [
            'Cache-Control' => 'private, max-age=3600',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /** Reference beacon dropdown: captured beacons (with X / Y) and the Extant Beacons list. */
    public function beacons(Request $r)
    {
        return response()->json(['data' => $this->plans->searchBeacons((string) $r->query('q', ''))]);
    }
}
