<?php

namespace App\Http\Controllers\Survey;

use App\Http\Controllers\Controller;
use App\Models\Sdi\SdiMapRequest;
use App\Models\Sdi\SdiMapType;
use App\Models\Sdi\SdiMda;
use App\Services\Survey\LandUsePoints;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Geo-informatics → SDI → Map Request (docs/KLAES Survey Module – Workflow_V02.md 4).
 *
 * Ported from ALAES's SDI map requests: the area is drawn on a map, not typed.
 * In KLAES the request is logged by Survey staff on behalf of the requesting
 * MDA (or a body not in the registry, typed under "Other"), so there is an MDA
 * picker rather than ALAES's account-bound MDA. Logged requests feed the
 * Production Unit (SdiProductionController); a request can be edited or deleted
 * only while still Pending.
 */
class SdiMapRequestController extends Controller
{
    public const REQUESTER_OTHER = '__other__';

    public function index(Request $r)
    {
        $q = SdiMapRequest::with(['mda', 'mapType']);

        if ($term = trim((string) $r->query('q', ''))) {
            $q->where(function ($w) use ($term) {
                foreach (['request_ref', 'map_name', 'map_reference', 'contact_name', 'contact_phone', 'requester_other'] as $col) {
                    $w->orWhere($col, 'like', "%$term%");
                }
                $w->orWhereHas('mda', fn ($m) => $m->where('name', 'like', "%$term%")->orWhere('abbreviation', 'like', "%$term%"));
            });
        }
        if ($st = $r->query('status')) $q->where('status', $st);
        if ($m = $r->query('mda'))     $q->where('mda_id', $m);
        if ($t = $r->query('type'))    $q->where('map_type_id', $t);

        $counts = SdiMapRequest::selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status');

        return view('survey_module.sdi.map_requests', [
            'requests' => $q->orderByDesc('id')->paginate(15)->withQueryString(),
            'counts'   => $counts,
            'mdas'     => SdiMda::orderBy('name')->get(['id', 'name', 'abbreviation']),
            'mapTypes' => SdiMapType::orderBy('sort_order')->get(['id', 'name']),
        ]);
    }

    public function create()
    {
        return view('survey_module.sdi.map_request_form', $this->formData(new SdiMapRequest([
            'received_date' => now()->toDateString(),
            'priority'      => 'medium',
        ])));
    }

    public function store(Request $r)
    {
        $data = $this->validated($r);
        $data['request_ref'] = SdiMapRequest::nextRef('request_ref', 'MR', 4);
        $data['status'] = SdiMapRequest::PENDING;
        $data['requested_by'] = Auth::id();

        $m = SdiMapRequest::create($data);

        return redirect()->route('survey-module.sdi.map-requests.show', $m)
            ->with('success', "Map request {$m->request_ref} logged; it is now in the Production Unit queue.");
    }

    public function edit(SdiMapRequest $mapRequest)
    {
        if ($mapRequest->status !== SdiMapRequest::PENDING) {
            return redirect()->route('survey-module.sdi.map-requests.show', $mapRequest)
                ->with('error', "{$mapRequest->request_ref} is {$mapRequest->statusLabel()}; only a Pending request can be edited.");
        }

        return view('survey_module.sdi.map_request_form', $this->formData($mapRequest));
    }

    public function update(Request $r, SdiMapRequest $mapRequest)
    {
        if ($mapRequest->status !== SdiMapRequest::PENDING) {
            return back()->with('error', "{$mapRequest->request_ref} is {$mapRequest->statusLabel()}; only a Pending request can be edited.");
        }

        $mapRequest->update($this->validated($r));

        return redirect()->route('survey-module.sdi.map-requests.show', $mapRequest)
            ->with('success', "Map request {$mapRequest->request_ref} updated.");
    }

    public function show(SdiMapRequest $mapRequest)
    {
        return view('survey_module.sdi.map_request_show', [
            'm' => $mapRequest->load(['mda', 'mapType', 'requester', 'actionedBy']),
        ]);
    }

    public function destroy(SdiMapRequest $mapRequest)
    {
        if ($mapRequest->status !== SdiMapRequest::PENDING) {
            return back()->with('error', "{$mapRequest->request_ref} is {$mapRequest->statusLabel()}; only a Pending request logged in error can be deleted.");
        }

        $ref = $mapRequest->request_ref;
        $mapRequest->delete();

        return redirect()->route('survey-module.sdi.map-requests')->with('success', "Map request {$ref} deleted.");
    }

    /** Indexed files grouped by point with their land-use mix, for the Land Use Map overlay. */
    public function landUsePoints(LandUsePoints $points)
    {
        return response()->json($points->all())->header('Cache-Control', 'private, max-age=600');
    }

    /** The produced map, streamed from the private disk. */
    public function deliverable(SdiMapRequest $mapRequest)
    {
        abort_unless($mapRequest->deliverable_path && Storage::disk(SdiMapRequest::DISK)->exists($mapRequest->deliverable_path), 404);

        return Storage::disk(SdiMapRequest::DISK)->download(
            $mapRequest->deliverable_path,
            $mapRequest->deliverable_original_name ?: basename($mapRequest->deliverable_path)
        );
    }

    /* ------------------------------------------------------------------ */

    private function formData(SdiMapRequest $m): array
    {
        return [
            'm'        => $m,
            'mdas'     => SdiMda::with('category:id,name,sort_order')->where('is_active', true)
                ->orWhere('id', $m->mda_id)->orderBy('sort_order')->orderBy('name')->get(),
            'mapTypes' => SdiMapType::where('is_active', true)->orWhere('id', $m->map_type_id)->orderBy('sort_order')->get(),
        ];
    }

    private function validated(Request $r): array
    {
        $data = $r->validate([
            'mda_id'           => ['required', 'string'],
            'requester_other'  => ['nullable', 'string', 'max:300', Rule::requiredIf(fn () => $r->input('mda_id') === self::REQUESTER_OTHER)],
            'contact_name'     => 'required|string|max:200',
            'contact_phone'    => 'required|string|max:50',
            'contact_email'    => 'nullable|email|max:255',
            'map_type_id'      => ['required', Rule::exists('sqlsrv.sdi_map_types', 'id')->whereNull('deleted_at')],
            'map_name'         => 'required|string|max:300',
            'map_reference'    => 'nullable|string|max:120',
            'map_description'  => 'nullable|string|max:2000',
            'output_formats'   => 'required|array|min:1',
            'output_formats.*' => [Rule::in(array_keys(SdiMapRequest::FORMATS))],
            'priority'         => ['required', Rule::in(array_keys(SdiMapRequest::PRIORITY_LABELS))],
            'received_date'    => 'required|date|before_or_equal:today',
            'due_date'         => 'nullable|date|after_or_equal:received_date',
            'geometry_geojson' => 'required|json',
        ], [
            'mda_id.required'          => 'Choose the requesting MDA, or Other.',
            'requester_other.required' => 'Name the requesting body.',
            'map_type_id.required'     => 'Choose the type of map needed.',
            'output_formats.required'  => 'Tick at least one format.',
            'geometry_geojson.required'=> 'Draw the area to be mapped.',
            'due_date.after_or_equal'  => 'The due date cannot be before the date received.',
        ]);

        $one = fn ($v) => ($v = trim(preg_replace('/\s+/', ' ', (string) $v))) === '' ? null : $v;

        // Requesting body: a registry MDA, or the typed name, unless it is a listed MDA typed again.
        if ($data['mda_id'] === self::REQUESTER_OTHER) {
            $typed = $one($data['requester_other']);
            $match = SdiMda::whereRaw('LOWER(name) = ?', [mb_strtolower($typed)])
                ->orWhereRaw('LOWER(abbreviation) = ?', [mb_strtolower($typed)])->first();
            $data['mda_id'] = $match?->id;
            $data['requester_other'] = $match ? null : $typed;
        } else {
            if (! SdiMda::whereKey($data['mda_id'])->exists()) {
                throw ValidationException::withMessages(['mda_id' => 'Choose the requesting MDA, or Other.']);
            }
            $data['mda_id'] = (int) $data['mda_id'];
            $data['requester_other'] = null;
        }

        // The drawn shape is the source of truth; its bounding box is recomputed, never trusted.
        $bounds = SdiMapRequest::boundsFromGeoJson(json_decode($data['geometry_geojson'], true));
        if (! $bounds) {
            throw ValidationException::withMessages(['geometry_geojson' => 'The drawn area could not be read. Draw it again.']);
        }

        foreach (['contact_name', 'map_name', 'map_description'] as $k) $data[$k] = $one($data[$k] ?? null);
        foreach (['contact_phone', 'contact_email', 'map_reference'] as $k) {
            $v = trim((string) ($data[$k] ?? ''));
            $data[$k] = $v === '' ? null : $v;
        }
        $data['output_formats'] = array_values(array_unique($data['output_formats']));

        return $data + $bounds;
    }
}
