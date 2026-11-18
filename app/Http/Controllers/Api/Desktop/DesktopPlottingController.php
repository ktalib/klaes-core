<?php

namespace App\Http\Controllers\Api\Desktop;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Survey\LookupController;
use App\Models\Survey\SurveyorListEntry;
use App\Models\Survey\SurveyPlanUpload as Plan;
use App\Services\Survey\CompensationListReader;
use App\Services\Survey\MapSheets;
use App\Services\Survey\SurveyPlanUploads;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Digital Plotting from KLAES-CORE Survey Desktop (user, 2026-11-18): the lists the form needs, and "Plot and Send",
 * which goes through the same validation and store as the web's Digital Plotting (SurveyPlanUploads).
 */
class DesktopPlottingController extends Controller
{
    /** Every list on the Digital Plotting form, so the app can fill it offline from its last copy. */
    public function lookups(): JsonResponse
    {
        abort_unless(Plan::installed(), 404);

        return response()->json(['data' => [
            'plan_types' => Plan::TYPES,
            'units' => collect(Plan::UNITS)->map(fn ($label, $value) => ['value' => $value, 'label' => $label])->values(),
            'districts' => array_values(LookupController::options('districts')),
            'lgas' => array_values(CompensationListReader::LGAS),
            'map_sheets_metric' => array_values(MapSheets::METRIC),
            'map_sheets_imperial' => collect(MapSheets::IMPERIAL)->map(fn ($name, $code) => ['value' => $code, 'label' => "{$code} · {$name}"])->values(),
            'surveyors' => SurveyorListEntry::query()->where('is_active', true)->orderBy('first_name')->orderBy('surname')
                ->get(['id', 'first_name', 'surname', 'other_name', 'rank'])
                ->map(fn ($s) => ['value' => (string) $s->id, 'label' => trim($s->full_name . ($s->rank ? ' · ' . $s->rank : ''))])->values(),
            'checker_formats' => collect(Plan::CHECK_FORMATS)->map(fn ($label, $value) => ['value' => $value, 'label' => $label])->values(),
            'coord_systems' => collect(Plan::COORD_SYSTEMS)->map(fn ($label, $value) => ['value' => (string) $value, 'label' => $label])->values(),
        ], 'as_of' => now()->toIso8601String()]);
    }

    /**
     * Plot and Send: the web form's fields (multipart, plan_method=digital). client_uuid makes a resend from the outbox
     * return the plan it already made instead of making a second one.
     */
    public function store(Request $r, SurveyPlanUploads $plans): JsonResponse
    {
        abort_unless(Plan::installed(), 404);
        $r->validate(['client_uuid' => 'required|string|max:64']);
        $key = 'desktop-plotting:' . $r->user()->id . ':' . $r->input('client_uuid');
        if (($id = Cache::get($key)) && ($done = Plan::find($id))) {
            return response()->json(['data' => ['id' => $done->id, 'upload_ref' => $done->upload_ref, 'unit' => $done->unit], 'repeat' => true]);
        }
        $r->merge(['plan_method' => Plan::METHOD_DIGITAL]);
        $plan = $plans->store($plans->validated($r, true, Plan::METHOD_DIGITAL), $r->file('plan_file'), $r->user());
        Cache::put($key, $plan->id, now()->addDays(60));

        return response()->json(['data' => ['id' => $plan->id, 'upload_ref' => $plan->upload_ref, 'unit' => $plan->unit],
            'message' => "{$plan->upload_ref} plotted and sent to {$plan->unit}."], 201);
    }
}
