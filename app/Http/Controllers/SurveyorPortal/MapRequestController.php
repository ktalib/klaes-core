<?php

namespace App\Http\Controllers\SurveyorPortal;

use App\Http\Controllers\Controller;
use App\Models\Sdi\SdiMapRequest;
use App\Models\Sdi\SdiMapType;
use App\Services\Survey\PortalMapRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** Map requests on the KSSGOF portal (user, 2026-11-06). Every request shown is the surveyor's own. */
class MapRequestController extends Controller
{
    private const FILE_TYPES = ['pdf', 'png', 'jpg', 'jpeg', 'tif', 'tiff', 'dwg', 'dxf', 'zip', 'kml', 'kmz'];

    public function __construct(private PortalMapRequests $requests)
    {
    }

    private function me()
    {
        return Auth::guard('surveyor')->user();
    }

    private function mine(SdiMapRequest $mapRequest): SdiMapRequest
    {
        abort_unless((int) $mapRequest->surveyor_portal_account_id === (int) $this->me()->id, 404);

        return $mapRequest;
    }

    public function index()
    {
        return view('surveyor_portal.map_requests.index', [
            'requests' => SdiMapRequest::with('mapType:id,name')->where('surveyor_portal_account_id', $this->me()->id)->orderByDesc('id')->paginate(20),
        ]);
    }

    public function create()
    {
        return view('surveyor_portal.map_requests.form', ['mapTypes' => SdiMapType::where('is_active', true)->orderBy('sort_order')->get(['id', 'name'])]);
    }

    public function store(Request $r)
    {
        $data = $r->validate([
            'map_type_id'      => ['required', Rule::exists('sqlsrv.sdi_map_types', 'id')->where('is_active', true)->whereNull('deleted_at')],
            'map_name'         => 'required|string|max:300',
            'map_reference'    => 'nullable|string|max:120',
            'map_description'  => 'nullable|string|max:2000',
            'output_formats'   => 'required|array|min:1',
            'output_formats.*' => [Rule::in(array_keys(SdiMapRequest::FORMATS))],
            'geometry_geojson' => 'required|string|max:200000',
            'request_file'     => ['nullable', 'file', 'max:51200', 'mimes:' . implode(',', self::FILE_TYPES), function ($attr, $file, $fail) {
                // mimes reads the contents; a wrong name is refused too.
                if (! in_array(strtolower($file->getClientOriginalExtension()), self::FILE_TYPES, true)) {
                    $fail('Upload a PDF, image, DWG/DXF, KML/KMZ, or a ZIP.');
                }
            }],
        ], [
            'map_type_id.required'      => 'Choose the type of map needed.',
            'map_name.required'         => 'Enter the map name.',
            'output_formats.required'   => 'Tick at least one format.',
            'geometry_geojson.required' => 'Draw the area to be mapped.',
            'request_file.mimes'        => 'Upload a PDF, image, DWG/DXF, KML/KMZ, or a ZIP.',
        ]);
        unset($data['request_file']);

        $data['geometry_geojson'] = PortalMapRequests::cleanGeometry($data['geometry_geojson']);
        if (! $data['geometry_geojson']) {
            throw ValidationException::withMessages(['geometry_geojson' => 'The drawn area could not be read. Draw it again.']);
        }

        $one = fn ($v) => ($v = trim(preg_replace('/\s+/', ' ', (string) $v))) === '' ? null : $v;
        foreach (['map_name', 'map_description', 'map_reference'] as $k) $data[$k] = $one($data[$k] ?? null);
        $data['output_formats'] = array_values(array_unique($data['output_formats']));

        $m = $this->requests->submit($this->me(), $data, $r->file('request_file'));

        return redirect()->route('surveyor-portal.map-requests.show', $m)->with('status', "Map request {$m->request_ref} has gone to the Production Unit.");
    }

    public function show(SdiMapRequest $mapRequest)
    {
        return view('surveyor_portal.map_requests.show', ['m' => $this->mine($mapRequest)->load('mapType:id,name')]);
    }

    public function requestFile(SdiMapRequest $mapRequest)
    {
        $this->mine($mapRequest);
        abort_unless($mapRequest->request_file_path, 404);

        return $this->requests->download($mapRequest->request_file_path, $mapRequest->request_file_name);
    }

    public function map(SdiMapRequest $mapRequest)
    {
        $this->mine($mapRequest);
        abort_unless($mapRequest->deliverable_path, 404);

        return $this->requests->download($mapRequest->deliverable_path, $mapRequest->deliverable_original_name);
    }
}
