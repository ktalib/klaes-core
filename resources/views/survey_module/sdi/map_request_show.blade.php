@extends('survey_module.layouts.klaes')

@section('page-title', $m->request_ref . ' — SDI — KLAES')

@php
    use App\Models\Sdi\SdiMapRequest;

    $PageTitle = 'Map Request ' . $m->request_ref;
    $PageDescription = $m->map_name . ' · ' . $m->requesterName();
    $badge = [SdiMapRequest::PENDING => 'pending', SdiMapRequest::IN_PROGRESS => 'review', SdiMapRequest::FULFILLED => 'completed', SdiMapRequest::REJECTED => 'rejected'];
    $row = fn ($label, $value) => '<div><div style="font-size:12px;color:var(--gray-500);">' . e($label) . '</div><div style="font-size:14px;">' . (filled($value) ? e($value) : '—') . '</div></div>';
@endphp

@section('survey-content')
@include('survey_module.sdi._head')
@include('survey_module.partials._flash')

<style>
    .survey-proto .mrs-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 18px; align-items: start; }
    .survey-proto .mrs-card { background: #fff; border: 1px solid var(--gray-200, #e5e7eb); border-radius: 12px; padding: 18px; margin-bottom: 18px; }
    .survey-proto .mrs-card h3 { font-size: 15px; font-weight: 700; margin: 0 0 12px; }
    .survey-proto .mrs-fields { display: grid; grid-template-columns: repeat(auto-fit, minmax(170px, 1fr)); gap: 12px 18px; }
    @media (max-width: 1100px) { .survey-proto .mrs-grid { grid-template-columns: 1fr; } }
</style>

<div class="page-header">
    <div><a href="{{ route('survey-module.sdi.map-requests') }}" style="font-size:14px;">&larr; All map requests</a></div>
    <div style="display:flex;gap:8px;flex-wrap:wrap;">
        @if ($m->status === SdiMapRequest::PENDING)
            <a class="btn btn-secondary" href="{{ route('survey-module.sdi.map-requests.edit', $m) }}"><i class="fas fa-pen"></i> Edit</a>
        @endif
        @if ($m->isOpen())
            <a class="btn btn-primary" href="{{ route('survey-module.sdi.production') }}"><i class="fas fa-industry"></i> Production Unit</a>
        @endif
    </div>
</div>

<div class="mrs-grid">
    <div>
        <div class="mrs-card">
            <div style="display:flex;justify-content:space-between;gap:8px;align-items:center;margin-bottom:12px;">
                <h3 style="margin:0;">{{ $m->map_name }}</h3>
                <span>
                    <span class="sdi-chip {{ $m->priority === 'high' ? 'high' : '' }}">{{ $m->priorityLabel() }} priority</span>
                    <span class="status-badge {{ $badge[$m->status] ?? 'pending' }}"><span class="dot"></span>{{ $m->statusLabel() }}</span>
                </span>
            </div>
            <div class="mrs-fields">
                {!! $row('Request No', $m->request_ref) !!}
                {!! $row('Map Type', $m->mapType?->name) !!}
                {!! $row('Map Reference', $m->map_reference) !!}
                {!! $row('Formats', $m->formatsLabel()) !!}
                {!! $row('Date Received', optional($m->received_date)->format('d M Y')) !!}
                {!! $row('Due Date', optional($m->due_date)->format('d M Y') . ($m->isOverdue() ? ' (overdue)' : '')) !!}
                {!! $row('Logged By', $m->requester?->name) !!}
            </div>
            <div style="margin-top:12px;">{!! $row('Description', $m->map_description) !!}</div>
        </div>

        <div class="mrs-card">
            <h3><i class="fas fa-building" style="color:var(--primary);"></i> Requesting Body</h3>
            <div class="mrs-fields">
                {!! $row('MDA', $m->requesterName()) !!}
                {!! $row('Category', $m->mda?->category?->name) !!}
                {!! $row('Contact Person', $m->contact_name) !!}
                {!! $row('Phone', $m->contact_phone) !!}
                {!! $row('Email', $m->contact_email) !!}
            </div>
        </div>

        <div class="mrs-card">
            <h3><i class="fas fa-industry" style="color:var(--primary);"></i> Production</h3>
            <div class="mrs-fields">
                {!! $row('Production Officer', $m->production_officer) !!}
                {!! $row('Started', optional($m->started_at)->format('d M Y H:i')) !!}
                {!! $row($m->status === SdiMapRequest::REJECTED ? 'Rejected' : 'Completed', optional($m->actioned_at)->format('d M Y H:i') . ($m->actionedBy ? ' · ' . $m->actionedBy->name : '')) !!}
            </div>
            @if ($m->response_note)
                <div style="margin-top:12px;">{!! $row($m->status === SdiMapRequest::REJECTED ? 'Reason' : 'Note', $m->response_note) !!}</div>
            @endif
            @if ($m->deliverable_path)
                <a class="btn btn-success" style="margin-top:14px;" href="{{ route('survey-module.sdi.map-requests.deliverable', $m) }}">
                    <i class="fas fa-download"></i> {{ $m->deliverable_original_name ?: 'Download map' }}
                </a>
            @elseif ($m->isOpen())
                <p class="helper-text" style="margin-top:10px;">Not produced yet. The Production Unit uploads the map when it is fulfilled.</p>
            @endif
        </div>
    </div>

    <div class="mrs-card">
        <h3><i class="fas fa-draw-polygon" style="color:var(--primary);"></i> Requested Area</h3>
        @if ($m->hasGeometry())
            <div class="helper-text" style="font-family:ui-monospace,monospace;margin-bottom:6px;">{{ $m->boundsSummary() }}</div>
            <div id="mrsMap" class="sdi-map" style="height:420px;"></div>
            @php $pts = $m->boundaryPoints(); @endphp
            @if ($pts)
                <div style="font-size:13px;font-weight:600;margin-top:10px;">Boundary points (X / Y, WGS 84)</div>
                <ol class="sdi-points">
                    @foreach ($pts as $p)
                        <li>P{{ $loop->iteration }} X {{ number_format($p['x'], 6, '.', '') }} Y {{ number_format($p['y'], 6, '.', '') }}</li>
                    @endforeach
                </ol>
            @endif
            @if ($m->mapType?->isLandUse())
                <div id="mrsLandUse" style="margin-top:12px;padding-top:10px;border-top:1px solid var(--gray-200,#e5e7eb);"></div>
            @endif
        @else
            <p class="helper-text">No area was drawn for this request.</p>
        @endif
    </div>
</div>

@if ($m->hasGeometry())
    @push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var map = L.map('mrsMap', { scrollWheelZoom: false });
            SdiMapRequestBasemaps(map).show(@json($m->mapType?->previewBasemap() ?? 'streets'));
            var layer = L.geoJSON(@json(json_decode($m->geometry_geojson)), { style: { color: '#0277bd', weight: 2, fillOpacity: 0.15 } }).addTo(map);
            map.fitBounds(layer.getBounds(), { maxZoom: 17 });
            @if ($m->mapType?->isLandUse())
                var landuse = SdiLandUse(map, @json(route('survey-module.sdi.map-requests.land-use-points')), { summaryEl: document.getElementById('mrsLandUse') });
                landuse.summarize(@json(json_decode($m->geometry_geojson)));
                landuse.show(true);
                layer.bringToFront();
            @endif
        });
    </script>
    @endpush
@endif
@endsection
