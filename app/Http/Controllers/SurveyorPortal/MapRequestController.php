<?php

namespace App\Http\Controllers\SurveyorPortal;

use App\Http\Controllers\Controller;
use App\Models\Sdi\SdiMapRequest;
use App\Models\Sdi\SdiMapType;
use App\Models\Sdi\SdiMda;
use App\Services\Survey\PortalMapRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** Map requests on the KSSGOF portal (user, 2026-11-06). Every request shown is the surveyor's own. */
class MapRequestController extends Controller
{
    /** The MDA list's "Other (specify)" (user, 2026-11-06): the staff form's list, active MDAs only. */
    public const MDA_OTHER = '__other__';

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
        return view('surveyor_portal.map_requests.form', [
            'mapTypes' => SdiMapType::where('is_active', true)->orderBy('sort_order')->get(['id', 'name']),
            'mdas' => SdiMda::with('category:id,name,sort_order')->where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(),
            'account' => $this->me(),
        ]);
    }

    public function store(Request $r)
    {
        $data = $r->validate([
            'contact_name'     => 'required|string|max:200',
            'contact_phone'    => 'required|string|max:50',
            'contact_email'    => 'nullable|email:rfc|max:255',
            'mda_id'           => ['required', 'string', 'max:30'],
            'requester_other'  => ['nullable', 'string', 'max:300', Rule::requiredIf(fn () => $r->input('mda_id') === self::MDA_OTHER)],
            'map_type_id'      => ['required'],
            'map_type_other'   => ['nullable', 'string', 'max:150', Rule::requiredIf(fn () => $r->input('map_type_id') === SdiMapType::OTHER)],
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
            'mda_id.required'           => 'Choose the MDA, or Other.',
            'requester_other.required'  => 'Name the requesting body.',
            'map_type_id.required'      => 'Choose the type of map needed.',
            'map_type_other.required'   => 'Specify the type of map.',
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
        foreach (['contact_name', 'contact_phone', 'map_name', 'map_description', 'map_reference'] as $k) $data[$k] = $one($data[$k] ?? null);
        $data['contact_email'] = trim((string) ($data['contact_email'] ?? '')) ?: null;
        $data['output_formats'] = array_values(array_unique($data['output_formats']));

        // Map type: a listed (active) type, or the type typed under Others. As the staff form.
        if ($data['map_type_id'] === SdiMapType::OTHER && ! SdiMapRequest::supportsOtherType()) {
            throw ValidationException::withMessages(['map_type_id' => 'Choose the type of map needed.']);
        }
        [$data['map_type_id'], $otherType] = SdiMapType::resolveChoice(is_scalar($data['map_type_id']) ? (string) $data['map_type_id'] : '', $data['map_type_other'] ?? null);
        unset($data['map_type_other']);
        if (SdiMapRequest::supportsOtherType()) $data['map_type_other'] = $otherType;

        // A listed MDA, or the typed name unless it is a listed MDA typed again. Stored as the staff form stores it.
        if ($data['mda_id'] === self::MDA_OTHER) {
            $typed = $one($data['requester_other'] ?? null);
            $match = SdiMda::where('is_active', true)->where(fn ($q) => $q->whereRaw('LOWER(name) = ?', [mb_strtolower($typed)])
                ->orWhereRaw('LOWER(abbreviation) = ?', [mb_strtolower($typed)]))->first();
            $data['mda_id'] = $match?->id;
            $data['requester_other'] = $match ? null : $typed;
        } else {
            // ctype_digit first: a public form, and SQL Server errors on a non-number here.
            if (! ctype_digit($data['mda_id']) || ! SdiMda::where('is_active', true)->whereKey($data['mda_id'])->exists()) {
                throw ValidationException::withMessages(['mda_id' => 'Choose the MDA, or Other.']);
            }
            $data['mda_id'] = (int) $data['mda_id'];
            $data['requester_other'] = null;
        }

        $account = $this->me();
        $m = $this->requests->submit($account, $data, $r->file('request_file'));

        if ($account) {
            return redirect()->route('surveyor-portal.map-requests.show', $m)->with('status', "Map request {$m->request_ref} has gone to the Production Unit.");
        }

        return redirect()->route('surveyor-portal.map-requests.create')->with('status', "Map request {$m->request_ref} has gone to the Production Unit.");
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
