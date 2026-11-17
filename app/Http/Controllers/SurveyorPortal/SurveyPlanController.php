<?php

namespace App\Http\Controllers\SurveyorPortal;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Survey\LookupController;
use App\Models\Survey\SurveyPlanUpload as Plan;
use App\Services\Survey\CompensationListReader;
use App\Services\Survey\SurveyPlanUploads;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

/**
 * Survey Plan Upload on the MLPP-SG portal (Survey Update 5g/5h, user 2026-11-10: "both" KLAES and the
 * portal). The same short form as KLAES; "Drawn by" starts as the surveyor's own name. The surveyor sees
 * only their own uploads; the unit chosen gets them in KLAES.
 */
class SurveyPlanController extends Controller
{
    public function __construct(private SurveyPlanUploads $plans)
    {
    }

    private function me()
    {
        return Auth::guard('surveyor')->user();
    }

    /**
     * Survey Plan Management (Recording 5, 2026-11-16): Manual Upload (the short form, the plan file, the coordinate
     * system and one reference beacon) and the surveyor's uploads, a table each for Manual Upload and Digital Plotting.
     */
    public function index(Request $r)
    {
        abort_unless(Plan::installed(), 404);
        $method = $r->query('method') === Plan::METHOD_DIGITAL ? Plan::METHOD_DIGITAL : Plan::METHOD_MANUAL;
        $mine = fn () => Plan::where('surveyor_portal_account_id', $this->me()->id);

        return view('surveyor_portal.survey_plans.index', [
            'rows' => $mine()->ofMethod($method)->orderByDesc('id')->paginate(20)->withQueryString(),
            'method' => $method,
            'methodCounts' => [
                Plan::METHOD_MANUAL => $mine()->ofMethod(Plan::METHOD_MANUAL)->count(),
                Plan::METHOD_DIGITAL => $mine()->ofMethod(Plan::METHOD_DIGITAL)->count(),
            ],
            'districts' => LookupController::options('districts'),
            'lgas' => CompensationListReader::LGAS,
            'me' => $this->me(),
        ]);
    }

    /** Digital Plotting: its own page, for the map's room. No file: the plotting is the plan. */
    public function digital()
    {
        abort_unless(Plan::installed(), 404);

        return view('surveyor_portal.survey_plans.digital', [
            'districts' => LookupController::options('districts'),
            'lgas' => CompensationListReader::LGAS,
            'me' => $this->me(),
        ]);
    }

    /** Survey Plan Upload > Signed (user, 2026-11-12): the surveyor's plans with a signed copy. */
    public function signed(Request $r)
    {
        abort_unless(Plan::installed(), 404);
        $term = trim((string) $r->query('q', ''));

        return view('surveyor_portal.survey_plans.signed', [
            'rows' => Plan::with('files')->where('surveyor_portal_account_id', $this->me()->id)->where('stage', Plan::STAGE_SIGNED)
                ->when($term !== '', fn ($q) => $q->where(fn ($w) => $w->where('upload_ref', 'like', "%{$term}%")->orWhere('plot_no', 'like', "%{$term}%")
                    ->orWhere('district', 'like', "%{$term}%")->orWhere('lga', 'like', "%{$term}%")->orWhere('location', 'like', "%{$term}%")))
                ->orderByDesc('id')->paginate(25)->withQueryString(),
            'search' => $term,
        ]);
    }

    public function store(Request $r)
    {
        abort_unless(Plan::installed(), 404);
        $method = $r->input('plan_method') === Plan::METHOD_DIGITAL ? Plan::METHOD_DIGITAL : Plan::METHOD_MANUAL;
        $plan = $this->plans->store($this->plans->validated($r, false, $method), $r->file('plan_file'), null, $this->me());

        return redirect()->route('surveyor-portal.survey-plans.index', ['method' => $method])
            ->with('status', "{$plan->upload_ref} " . ($plan->isDigital() ? 'plotted' : 'uploaded') . " and sent to {$plan->unit}.");
    }

    /** The surveyor's own plan: its stage, reference beacons, corrections and signed copy (CR 11112026-2). */
    public function show(int $plan)
    {
        $p = Plan::with(['files', 'referenceBeacons'])->where('surveyor_portal_account_id', $this->me()->id)->findOrFail($plan);

        return view('surveyor_portal.survey_plans.show', ['plan' => $p]);
    }

    /** Stage 2 -> 3: the corrected final copy, straight to the SG/DSG. */
    public function final(Request $r, int $plan, \App\Services\Survey\SurveyPlanStages $stages)
    {
        $p = Plan::where('surveyor_portal_account_id', $this->me()->id)->findOrFail($plan);
        $r->validate(['final_file' => 'required|file|max:25600', 'note' => 'nullable|string|max:2000'], ['final_file.required' => 'Choose the corrected plan.']);
        $stages->uploadFinal($p, $r->file('final_file'), null, $this->me(), $r->input('note'));

        return redirect()->route('surveyor-portal.survey-plans.show', $p->id)->with('status', "Final copy of {$p->upload_ref} sent to the SG/DSG for signing.");
    }

    /** A stage file of the surveyor's own plan. */
    public function stageFile(int $plan, int $file)
    {
        $p = Plan::where('surveyor_portal_account_id', $this->me()->id)->findOrFail($plan);
        $f = \App\Models\Survey\SurveyPlanFile::where('survey_plan_upload_id', $p->id)->findOrFail($file);
        abort_unless($f->file_path && Storage::disk(Plan::DISK)->exists($f->file_path), 404);

        return Storage::disk(Plan::DISK)->response($f->file_path, $f->file_name ?: basename($f->file_path), [
            'Cache-Control' => 'private, max-age=3600',
            'X-Content-Type-Options' => 'nosniff',
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

    public function beacons(Request $r)
    {
        return response()->json(['data' => $this->plans->searchBeacons((string) $r->query('q', ''))]);
    }

    /** The surveyor's own plan file. */
    public function file(int $plan)
    {
        $p = Plan::where('surveyor_portal_account_id', $this->me()->id)->findOrFail($plan);
        if ($p->isDigital() && ! $p->file_path) {
            return redirect()->route('surveyor-portal.survey-plans.show', $p->id);
        }
        abort_unless($p->file_path && Storage::disk(Plan::DISK)->exists($p->file_path), 404);

        return Storage::disk(Plan::DISK)->response($p->file_path, $p->file_name ?: basename($p->file_path), [
            'Cache-Control' => 'private, max-age=3600',
            'X-Content-Type-Options' => 'nosniff',
        ]);
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
