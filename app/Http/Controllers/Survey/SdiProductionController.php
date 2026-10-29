<?php

namespace App\Http\Controllers\Survey;

use App\Http\Controllers\Controller;
use App\Models\Sdi\SdiMapRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * Geo-informatics → SDI → Production Unit: the GIS unit's queue of map
 * requests. Pending -> In Progress (an officer takes it) -> Fulfilled (the
 * produced map is uploaded) or Rejected (with the reason). A closed request
 * cannot be reopened, as in ALAES.
 */
class SdiProductionController extends Controller
{
    public const TABS = [
        'queue'    => 'Queue',
        'produced' => 'Produced',
        'rejected' => 'Rejected',
    ];

    public function index(Request $r)
    {
        $tab = array_key_exists((string) $r->query('tab'), self::TABS) ? $r->query('tab') : 'queue';

        $q = SdiMapRequest::with(['mda', 'mapType', 'actionedBy']);
        match ($tab) {
            'produced' => $q->where('status', SdiMapRequest::FULFILLED)->orderByDesc('actioned_at'),
            'rejected' => $q->where('status', SdiMapRequest::REJECTED)->orderByDesc('actioned_at'),
            // High priority first, then the oldest due date / oldest request.
            default    => $q->open()
                ->orderByRaw("CASE priority WHEN 'high' THEN 0 WHEN 'medium' THEN 1 ELSE 2 END")
                ->orderByRaw('CASE WHEN due_date IS NULL THEN 1 ELSE 0 END')->orderBy('due_date')->orderBy('id'),
        };

        if ($term = trim((string) $r->query('q', ''))) {
            $q->where(function ($w) use ($term) {
                foreach (['request_ref', 'map_name', 'production_officer', 'requester_other'] as $col) {
                    $w->orWhere($col, 'like', "%$term%");
                }
                $w->orWhereHas('mda', fn ($m) => $m->where('name', 'like', "%$term%")->orWhere('abbreviation', 'like', "%$term%"));
            });
        }

        $counts = SdiMapRequest::selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status');

        return view('survey_module.sdi.production', [
            'tab'      => $tab,
            'requests' => $q->paginate(15)->withQueryString(),
            'counts'   => $counts,
            'overdue'  => SdiMapRequest::open()->whereNotNull('due_date')->where('due_date', '<', today())->count(),
        ]);
    }

    /** An officer takes the request into production. */
    public function start(Request $r, SdiMapRequest $mapRequest)
    {
        $data = $r->validate(['production_officer' => 'required|string|max:200']);

        if ($mapRequest->status !== SdiMapRequest::PENDING) {
            return back()->with('error', "{$mapRequest->request_ref} is already {$mapRequest->statusLabel()}.");
        }

        $mapRequest->update([
            'status'             => SdiMapRequest::IN_PROGRESS,
            'production_officer' => trim(preg_replace('/\s+/', ' ', $data['production_officer'])),
            'started_at'         => now(),
        ]);

        return back()->with('success', "{$mapRequest->request_ref} is now In Progress with {$mapRequest->production_officer}.");
    }

    /** The map is produced: its file is uploaded and the request closes. */
    public function fulfil(Request $r, SdiMapRequest $mapRequest)
    {
        $data = $r->validate([
            'deliverable'   => 'required|file|max:51200|mimes:pdf,png,jpg,jpeg,tif,tiff,dwg,dxf,zip,kml,kmz',
            'response_note' => 'nullable|string|max:2000',
        ], [
            'deliverable.required' => 'Upload the produced map.',
            'deliverable.mimes'    => 'Upload a PDF, image, DWG/DXF, KML/KMZ, or a ZIP (e.g. a Shapefile).',
        ]);

        if (! $mapRequest->isOpen()) {
            return back()->with('error', "{$mapRequest->request_ref} is already {$mapRequest->statusLabel()}.");
        }

        $file = $r->file('deliverable');
        $ext  = strtolower($file->getClientOriginalExtension() ?: $file->extension() ?: 'bin');
        $path = $file->storeAs("app/survey/sdi/map-requests/{$mapRequest->id}", Str::uuid() . '.' . $ext, SdiMapRequest::DISK);

        $mapRequest->update([
            'status'                    => SdiMapRequest::FULFILLED,
            'production_officer'        => $mapRequest->production_officer ?: (Auth::user()?->name),
            'started_at'                => $mapRequest->started_at ?: now(),
            'deliverable_path'          => $path,
            'deliverable_original_name' => mb_substr($file->getClientOriginalName(), 0, 255),
            'response_note'             => $this->note($data['response_note'] ?? null),
            'actioned_by'               => Auth::id(),
            'actioned_at'               => now(),
        ]);

        return back()->with('success', "{$mapRequest->request_ref} fulfilled: map uploaded.");
    }

    public function reject(Request $r, SdiMapRequest $mapRequest)
    {
        $data = $r->validate(['response_note' => 'required|string|max:2000'], ['response_note.required' => 'Give the reason for rejecting.']);

        if (! $mapRequest->isOpen()) {
            return back()->with('error', "{$mapRequest->request_ref} is already {$mapRequest->statusLabel()}.");
        }

        $mapRequest->update([
            'status'        => SdiMapRequest::REJECTED,
            'response_note' => $this->note($data['response_note']),
            'actioned_by'   => Auth::id(),
            'actioned_at'   => now(),
        ]);

        return back()->with('success', "{$mapRequest->request_ref} rejected.");
    }

    private function note(?string $v): ?string
    {
        $v = trim((string) $v);

        return $v === '' ? null : $v;
    }
}
