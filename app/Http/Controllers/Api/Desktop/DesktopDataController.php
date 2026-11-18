<?php

namespace App\Http\Controllers\Api\Desktop;

use App\Http\Controllers\Controller;
use App\Models\Survey\SurveyPlanUpload as Plan;
use App\Services\Survey\SurveyCoordinates;
use App\Services\Survey\SurveyPlanUploads;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * Read-only data for KLAES-CORE Survey Desktop (/api/desktop/v1): the survey plans from Survey Plan Upload with their
 * beacons, the plan files, the beacon search the web card uses, and the Beacon Register for the offline cache.
 * Nothing here writes; sending work back from the desktop is a later step (plan section 5).
 */
class DesktopDataController extends Controller
{
    /** Survey plans, newest first; ?since= only those changed after that time (the app's next sync). */
    public function plans(Request $r): JsonResponse
    {
        abort_unless(Plan::installed(), 404);
        $since = $this->since($r);
        $rows = Plan::query()->with('referenceBeacons')
            ->when($since, fn ($q) => $q->where('updated_at', '>', $since))
            ->orderByDesc('id')->limit(5000)->get();

        return response()->json(['data' => $rows->map(fn (Plan $p) => $this->plan($p))->values(), 'as_of' => now()->toIso8601String()]);
    }

    public function show(Plan $plan): JsonResponse
    {
        $plan->load('referenceBeacons');

        return response()->json(['data' => $this->plan($plan)]);
    }

    /** The uploaded plan file (the web's survey-plans.file), from the private disk. */
    public function file(Plan $plan)
    {
        abort_unless($plan->file_path && Storage::disk(Plan::DISK)->exists($plan->file_path), 404, 'This survey plan has no file.');

        return Storage::disk(Plan::DISK)->download($plan->file_path, $plan->file_name ?: basename($plan->file_path));
    }

    public function searchBeacons(Request $r, SurveyPlanUploads $plans): JsonResponse
    {
        return response()->json(['data' => $plans->searchBeacons((string) $r->query('q', ''), 50)]);
    }

    /**
     * The Beacon Register (then the Control Point Register) rows with a valid X / Y, as the web's beacon search takes
     * them: X a six-digit Easting, Y a seven-digit Northing, Minna / UTM 32N. Paged; ?since= for changes only.
     */
    public function register(Request $r): JsonResponse
    {
        $since = $this->since($r);
        $page = max(1, (int) $r->query('page', 1));
        $per = 2000;
        $db = DB::connection('sqlsrv');
        $q = null;
        foreach (['survey_beacon_register' => 'beacon', 'survey_control_point_register' => 'control point'] as $table => $kind) {
            if (! Schema::connection('sqlsrv')->hasTable($table)) {
                continue;
            }
            $part = $db->table($table)->whereNull('deleted_at')->whereNotNull('coord_x')->whereNotNull('coord_y')
                ->whereBetween('coord_x', [100000, 999999.9999])->whereBetween('coord_y', [1000000, 1999999.9999])
                ->when($since, fn ($w) => $w->where('updated_at', '>', $since))
                ->select('beacon_from as beacon_no', 'coord_x as x', 'coord_y as y', 'plan_no', 'prop_district as district', 'updated_at', 'id',
                    DB::raw("'" . $kind . "' as kind"));
            $q = $q ? $q->unionAll($part) : $part;
        }
        if (! $q) {
            return response()->json(['data' => [], 'next_page' => null, 'as_of' => now()->toIso8601String()]);
        }
        $rows = DB::connection('sqlsrv')->query()->fromSub($q, 'b')->orderBy('kind')->orderBy('id')
            ->offset(($page - 1) * $per)->limit($per + 1)->get();
        $more = $rows->count() > $per;
        $data = $rows->take($per)->filter(fn ($b) => SurveyCoordinates::isEasting((float) $b->x) && SurveyCoordinates::isNorthing((float) $b->y))
            ->map(fn ($b) => ['beacon_no' => trim((string) $b->beacon_no), 'x' => (float) $b->x, 'y' => (float) $b->y, 'crs' => '26332',
                'kind' => $b->kind, 'plan_no' => $b->plan_no, 'district' => $b->district, 'updated_at' => $b->updated_at])
            ->filter(fn ($b) => $b['beacon_no'] !== '')->values();

        return response()->json(['data' => $data, 'next_page' => $more ? $page + 1 : null, 'as_of' => now()->toIso8601String()]);
    }

    private function since(Request $r): ?Carbon
    {
        $s = trim((string) $r->query('since', ''));
        if ($s === '') {
            return null;
        }
        try {
            return Carbon::parse($s);
        } catch (\Throwable) {
            return null;
        }
    }

    /** One plan in KLAES's own column names (the app's Plan.from_api reads these). */
    private function plan(Plan $p): array
    {
        return [
            'id' => $p->id, 'upload_ref' => $p->upload_ref, 'plot_no' => $p->plot_no, 'district' => $p->district, 'lga' => $p->lga,
            'location' => $p->location, 'plan_method' => $p->method, 'method_label' => $p->method_label, 'plan_type' => $p->plan_type,
            'unit' => $p->unit, 'stage' => $p->stage_no, 'stage_label' => Plan::STAGE_CODES[$p->stage_no] ?? '', 'stage_status' => $p->stage_status, 'coord_system' => $p->coord_system ?: '26332',
            'map_sheet_metric' => $p->map_sheet_metric, 'map_sheet_imperial' => $p->map_sheet_imperial, 'file_name' => $p->file_name,
            'has_file' => (bool) $p->file_path, 'source' => $p->source, 'uploaded_by_name' => $p->uploaded_by_name,
            'created_at' => optional($p->created_at)->toDateTimeString(), 'updated_at' => optional($p->updated_at)->toIso8601String(),
            'reference_beacons' => $p->referenceBeacons->map(fn ($b) => ['beacon_no' => $b->beacon_no, 'coord_x' => $b->coord_x !== null ? (float) $b->coord_x : null,
                'coord_y' => $b->coord_y !== null ? (float) $b->coord_y : null, 'source' => $b->source])->values(),
        ];
    }
}
