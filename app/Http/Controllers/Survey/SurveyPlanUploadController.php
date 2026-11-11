<?php

namespace App\Http\Controllers\Survey;

use App\Http\Controllers\Controller;
use App\Models\Survey\SurveyorListEntry;
use App\Models\Survey\SurveyPlanUpload as Plan;
use App\Services\Survey\CompensationListReader;
use App\Services\Survey\SurveyPlanUploads;
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

    private function page(Request $r, ?string $unit)
    {
        abort_unless(Plan::installed(), 404);

        $base = Plan::query()->when($unit, fn ($q) => $q->where('unit', $unit));

        return view('survey_module.survey_plans.index', [
            'unit'      => $unit,
            'rows'      => $this->listQuery($r, $unit)->paginate(25)->withQueryString(),
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

    /** The list's filters and order, shared with Export Records. */
    private function listQuery(Request $r, ?string $unit)
    {
        $term = trim(preg_replace('/\s+/', ' ', (string) $r->query('q', '')));
        $like = '%' . str_replace(['[', '%', '_'], ['[[]', '[%]', '[_]'], $term) . '%';

        return Plan::query()
            ->when($unit, fn ($q) => $q->where('unit', $unit))
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

        return $this->exportRecords($r, fn () => $this->listQuery($r, $unit), [
            'ref' => 'Upload Ref', 'type' => 'Type', 'unit' => 'Sent To', 'plot_no' => 'Plot No.', 'district' => 'District', 'lga' => 'LGA',
            'location' => 'Location', 'drawn_by' => 'Drawn By', 'source' => 'From', 'uploaded_by' => 'Uploaded By', 'uploaded' => 'Uploaded', 'status' => 'Status',
        ], fn ($x) => [
            'ref' => $x->upload_ref, 'type' => $x->plan_type, 'unit' => $x->unit, 'plot_no' => $x->plot_no, 'district' => $x->district, 'lga' => $x->lga,
            'location' => $x->location, 'drawn_by' => $x->drawn_by, 'source' => $x->source_label, 'uploaded_by' => $x->uploaded_by_name,
            'uploaded' => $this->exportDate($x->created_at), 'status' => $x->status,
        ], 'created_at');
    }
}
