@extends('surveyor_portal.layouts.portal')

@section('title', $m->request_ref . ' — KSSGOF Portal')
@section('heading', 'Map Request ' . $m->request_ref)

@push('styles')
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
          integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="" />
    <style>
        #mrsMap { height: 340px; border-radius: 8px; border: 1px solid #B8BEC5; z-index: 0; font: inherit; }
        #mrsMap img { max-width: none !important; max-height: none !important; }
        #mrsMap img.leaflet-tile { mix-blend-mode: normal !important; width: 256.5px !important; height: 256.5px !important; }
        #mrsMap .leaflet-control-attribution { font-size: 9px; }
    </style>
@endpush

@section('main')
    @php
        $badge = ['pending' => 'submitted', 'in_progress' => 'issued', 'fulfilled' => 'accepted', 'rejected' => 'rejected'];
        $rows = [
            'Status' => null, 'Date' => optional($m->received_date)->format('d/m/Y'), 'Map Name' => $m->map_name, 'Map Type' => $m->mapType?->name,
            'Map Reference' => $m->map_reference, 'Formats' => $m->formatsLabel(), 'Description' => $m->map_description,
        ];
    @endphp
    <div class="ks-card p-6 mb-4">
        <dl class="grid sm:grid-cols-2 gap-x-8 gap-y-2 text-sm">
            @foreach ($rows as $label => $value)
                <div class="flex justify-between gap-4" style="border-bottom:1px solid #ECEEF0;padding:6px 0;">
                    <dt class="ks-help">{{ $label }}</dt>
                    <dd class="font-semibold text-right">
                        @if ($label === 'Status')
                            <span class="ks-badge {{ $badge[$m->status] ?? '' }}">{{ $m->statusLabel() }}</span>
                        @else
                            {{ $value ?: '—' }}
                        @endif
                    </dd>
                </div>
            @endforeach
        </dl>
        @if ($m->response_note)
            <div class="ks-flash {{ $m->status === \App\Models\Sdi\SdiMapRequest::REJECTED ? 'bad' : 'ok' }}" style="margin:16px 0 0;">{{ $m->response_note }}</div>
        @endif
        <div class="flex flex-wrap gap-2" style="margin-top:16px;">
            @if ($m->status === \App\Models\Sdi\SdiMapRequest::FULFILLED && $m->deliverable_path)
                <a class="ks-btn ks-btn-green" href="{{ route('surveyor-portal.map-requests.map', $m) }}"><i data-lucide="download" class="w-4 h-4"></i> Download Map</a>
            @endif
            @if ($m->request_file_path)
                <a class="ks-btn ks-btn-light" href="{{ route('surveyor-portal.map-requests.request-file', $m) }}"><i data-lucide="file-down" class="w-4 h-4"></i> {{ $m->request_file_name ?: 'Scanned Map or Sketch' }}</a>
            @endif
        </div>
    </div>

    <div class="ks-card p-6">
        <h2 class="font-bold mb-3">Area to be Mapped</h2>
        <div id="mrsMap"></div>
        @php $pts = $m->boundaryPoints(); @endphp
        @if ($pts)
            <div class="ks-help" style="margin-top:8px;">Boundary points: X = longitude, Y = latitude (WGS 84).</div>
            <ol style="columns:2;font-family:ui-monospace,monospace;font-size:12px;margin:6px 0 0;padding-left:18px;list-style:decimal;">
                @foreach ($pts as $p)
                    <li>P{{ $loop->iteration }} X {{ number_format($p['x'], 6, '.', '') }} Y {{ number_format($p['y'], 6, '.', '') }}</li>
                @endforeach
            </ol>
        @endif
    </div>
@endsection

@push('scripts')
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
<script src="{{ asset('js/survey/sdi-basemaps.js') }}?v=2"></script>
<script>
(function () {
    var box = document.getElementById('mrsMap');
    if (!window.L) { box.style.display = 'none'; return; }
    var map = L.map(box, { scrollWheelZoom: false });
    SdiMapRequestBasemaps(map).show('satellite');
    var layer = L.geoJSON(@json(json_decode($m->geometry_geojson)), { style: { color: '#FACC15', weight: 2, fillOpacity: 0.15 } }).addTo(map);
    map.fitBounds(layer.getBounds(), { maxZoom: 17 });
})();
</script>
@endpush
