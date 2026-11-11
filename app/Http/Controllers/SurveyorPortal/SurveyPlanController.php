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

    public function index()
    {
        abort_unless(Plan::installed(), 404);

        return view('surveyor_portal.survey_plans.index', [
            'rows' => Plan::where('surveyor_portal_account_id', $this->me()->id)->orderByDesc('id')->paginate(20),
            'districts' => LookupController::options('districts'),
            'lgas' => CompensationListReader::LGAS,
            'me' => $this->me(),
        ]);
    }

    public function store(Request $r)
    {
        abort_unless(Plan::installed(), 404);
        $plan = $this->plans->store($this->plans->validated($r, false), $r->file('plan_file'), null, $this->me());

        return redirect()->route('surveyor-portal.survey-plans.index')
            ->with('status', "{$plan->upload_ref} uploaded and sent to {$plan->unit}.");
    }

    /** The surveyor's own plan file. */
    public function file(int $plan)
    {
        $p = Plan::where('surveyor_portal_account_id', $this->me()->id)->findOrFail($plan);
        abort_unless($p->file_path && Storage::disk(Plan::DISK)->exists($p->file_path), 404);

        return Storage::disk(Plan::DISK)->response($p->file_path, $p->file_name ?: basename($p->file_path), [
            'Cache-Control' => 'private, max-age=3600',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
