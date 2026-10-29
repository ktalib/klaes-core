@extends('survey_module.layouts.klaes')

@section('page-title', 'Production Unit — SDI — KLAES')

@php
    use App\Http\Controllers\Survey\SdiProductionController;
    use App\Models\Sdi\SdiMapRequest;

    $PageTitle = 'SDI · Production Unit';
    $PageDescription = 'The GIS unit\'s queue: take a map request, produce it, upload the map.';
    $svItem = 'flex items-center gap-2 w-full px-3 py-2 text-sm text-left text-gray-700 hover:bg-gray-50';
    $inputStyle = 'padding:8px 14px;border:1.5px solid var(--gray-300);border-radius:var(--radius-sm);font-family:var(--font);font-size:14px;background:#fff;';
    $badge = [SdiMapRequest::PENDING => 'pending', SdiMapRequest::IN_PROGRESS => 'review', SdiMapRequest::FULFILLED => 'completed', SdiMapRequest::REJECTED => 'rejected'];
    $tabCount = [
        'queue'    => (int) ($counts[SdiMapRequest::PENDING] ?? 0) + (int) ($counts[SdiMapRequest::IN_PROGRESS] ?? 0),
        'produced' => (int) ($counts[SdiMapRequest::FULFILLED] ?? 0),
        'rejected' => (int) ($counts[SdiMapRequest::REJECTED] ?? 0),
    ];
    $me = trim((string) (auth()->user()?->name ?? ''));
@endphp

@section('survey-content')
@include('survey_module.sdi._head')
@include('survey_module.partials._flash')

<div class="kpi-grid">
    <div class="kpi-card"><div class="kpi-label">Waiting (Pending)</div><div class="kpi-value">{{ number_format((int) ($counts[SdiMapRequest::PENDING] ?? 0)) }}</div></div>
    <div class="kpi-card"><div class="kpi-label">In Production</div><div class="kpi-value">{{ number_format((int) ($counts[SdiMapRequest::IN_PROGRESS] ?? 0)) }}</div></div>
    <div class="kpi-card"><div class="kpi-label">Overdue</div><div class="kpi-value" style="{{ $overdue ? 'color:#b91c1c;' : '' }}">{{ number_format($overdue) }}</div></div>
    <div class="kpi-card"><div class="kpi-label">Produced</div><div class="kpi-value">{{ number_format($tabCount['produced']) }}</div></div>
</div>

<div class="sdi-nav" style="margin-top:6px;">
    @foreach (SdiProductionController::TABS as $k => $label)
        <a href="{{ route('survey-module.sdi.production', ['tab' => $k]) }}" class="{{ $tab === $k ? 'on' : '' }}">{{ $label }} <span class="n">{{ number_format($tabCount[$k]) }}</span></a>
    @endforeach
</div>

<form method="GET" class="table-toolbar">
    <input type="hidden" name="tab" value="{{ $tab }}" />
    <div class="left">
        <input type="text" name="q" value="{{ request('q') }}" placeholder="Search ref, map, MDA, officer…" style="{{ $inputStyle }}width:260px;" />
        <button class="btn btn-secondary" type="submit"><i class="fas fa-search"></i> Search</button>
        @if (request('q'))<a class="btn btn-outline btn-sm" href="{{ route('survey-module.sdi.production', ['tab' => $tab]) }}">Clear</a>@endif
    </div>
</form>

<div class="table-wrapper" x-data="{ act: { kind: '', url: '', ref: '' } }">
    <div class="table-scroll">
        <table>
            <thead>
                <tr>
                    <th>S/N</th>
                    <th>Request No</th>
                    <th>Requesting MDA</th>
                    <th>Map</th>
                    <th>Formats</th>
                    <th>{{ $tab === 'queue' ? 'Priority / Due' : 'Completed' }}</th>
                    <th>Officer</th>
                    <th>Status</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($requests as $m)
                    <tr>
                        <td>{{ $requests->firstItem() + $loop->index }}</td>
                        <td><a href="{{ route('survey-module.sdi.map-requests.show', $m) }}"><strong>{{ $m->request_ref }}</strong></a>
                            <div style="font-size:12px;color:var(--gray-500);">{{ optional($m->received_date)->format('d M Y') }}</div></td>
                        <td>{{ $m->requesterName() }}</td>
                        <td>{{ $m->map_name }}<div style="font-size:12px;color:var(--gray-500);">{{ $m->mapType?->name ?? '—' }}</div></td>
                        <td>{{ $m->formatsLabel() }}</td>
                        <td>
                            @if ($tab === 'queue')
                                <span class="sdi-chip {{ $m->priority === 'high' ? 'high' : '' }}">{{ $m->priorityLabel() }}</span>
                                @if ($m->due_date)<div style="font-size:12px;margin-top:3px;" @class(['sdi-chip late' => $m->isOverdue()])>Due {{ $m->due_date->format('d M Y') }}</div>@endif
                            @else
                                {{ optional($m->actioned_at)->format('d M Y') }}
                                @if ($m->response_note)<div style="font-size:12px;color:var(--gray-500);max-width:260px;">{{ \Illuminate\Support\Str::limit($m->response_note, 80) }}</div>@endif
                            @endif
                        </td>
                        <td>{{ $m->production_officer ?: '—' }}</td>
                        <td><span class="status-badge {{ $badge[$m->status] ?? 'pending' }}"><span class="dot"></span>{{ $m->statusLabel() }}</span></td>
                        <td>
                            <div class="inline-block text-left" x-data="{ open: false, top: 0, left: 0 }" @keydown.escape.window="open = false">
                                <button type="button" x-ref="btn" title="Actions" aria-label="Actions"
                                        @click.stop="const r = $refs.btn.getBoundingClientRect(); top = r.bottom + 4; left = Math.max(8, r.right - 224); open = !open"
                                        class="inline-flex items-center justify-center w-8 h-8 rounded-full text-gray-600 hover:bg-gray-100">
                                    <i data-lucide="more-vertical" class="w-5 h-5"></i>
                                </button>
                                <div x-show="open" x-cloak @click.outside="open = false" @scroll.window="open = false"
                                     :style="'top:' + top + 'px; left:' + left + 'px'"
                                     class="fixed z-50 w-56 rounded-lg border border-gray-200 bg-white shadow-lg py-1">
                                    <a href="{{ route('survey-module.sdi.map-requests.show', $m) }}" class="{{ $svItem }}"><i data-lucide="eye" class="w-4 h-4 text-gray-500"></i> View Request</a>
                                    @if ($m->status === SdiMapRequest::PENDING)
                                        <button type="button" class="{{ $svItem }}" @click.stop="open = false; act = { kind: 'start', url: @js(route('survey-module.sdi.production.start', $m)), ref: @js($m->request_ref) }">
                                            <i data-lucide="play-circle" class="w-4 h-4 text-gray-500"></i> Start Production
                                        </button>
                                    @endif
                                    @if ($m->isOpen())
                                        <button type="button" class="{{ $svItem }}" @click.stop="open = false; act = { kind: 'fulfil', url: @js(route('survey-module.sdi.production.fulfil', $m)), ref: @js($m->request_ref) }">
                                            <i data-lucide="upload" class="w-4 h-4 text-gray-500"></i> Upload Map &amp; Fulfil
                                        </button>
                                        <button type="button" class="{{ $svItem }} text-red-600" @click.stop="open = false; act = { kind: 'reject', url: @js(route('survey-module.sdi.production.reject', $m)), ref: @js($m->request_ref) }">
                                            <i data-lucide="x-circle" class="w-4 h-4"></i> Reject
                                        </button>
                                    @endif
                                    @if ($m->deliverable_path)
                                        <a href="{{ route('survey-module.sdi.map-requests.deliverable', $m) }}" class="{{ $svItem }}"><i data-lucide="download" class="w-4 h-4 text-gray-500"></i> Download Map</a>
                                    @endif
                                </div>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" style="text-align:center;padding:28px;color:var(--gray-500);">
                            <i class="fas fa-industry" style="font-size:24px;display:block;margin-bottom:8px;opacity:.4;"></i>
                            {{ $tab === 'queue' ? 'The queue is empty: no open map requests.' : 'Nothing here yet.' }}
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="table-footer">
        <span>Showing {{ $requests->firstItem() ?? 0 }}–{{ $requests->lastItem() ?? 0 }} of {{ number_format($requests->total()) }}</span>
        <div class="pagination">{{ $requests->links() }}</div>
    </div>

    {{-- Start / Fulfil / Reject --}}
    <div x-show="act.kind" x-cloak class="fixed inset-0 z-50 flex items-center justify-center" style="background:rgba(0,0,0,.4);" @keydown.escape.window="act.kind = ''">
        <form method="POST" :action="act.url" enctype="multipart/form-data" class="bg-white rounded-lg shadow-xl p-5" style="width:min(500px,92vw);" @click.outside="act.kind = ''">
            @csrf
            <h3 style="margin:0 0 10px;font-size:16px;font-weight:700;">
                <span x-text="act.ref"></span> →
                <span x-text="{ start: 'Start Production', fulfil: 'Fulfil (upload map)', reject: 'Reject' }[act.kind]"></span>
            </h3>

            <template x-if="act.kind === 'start'">
                <div>
                    <label style="display:block;font-size:13px;margin-bottom:4px;">Production Officer <span class="required">*</span></label>
                    <input type="text" name="production_officer" maxlength="200" required value="{{ $me }}"
                           style="width:100%;border:1.5px solid var(--gray-300);border-radius:6px;padding:8px;" />
                </div>
            </template>
            <template x-if="act.kind === 'fulfil'">
                <div>
                    <label style="display:block;font-size:13px;margin-bottom:4px;">Produced Map <span class="required">*</span></label>
                    <input type="file" name="deliverable" required accept=".pdf,.png,.jpg,.jpeg,.tif,.tiff,.dwg,.dxf,.zip,.kml,.kmz" />
                    <p class="helper-text">PDF, image, DWG/DXF, KML/KMZ, or a ZIP (e.g. a Shapefile). Up to 50 MB.</p>
                    <label style="display:block;font-size:13px;margin:10px 0 4px;">Note</label>
                    <textarea name="response_note" rows="3" maxlength="2000" style="width:100%;border:1.5px solid var(--gray-300);border-radius:6px;padding:8px;"></textarea>
                </div>
            </template>
            <template x-if="act.kind === 'reject'">
                <div>
                    <label style="display:block;font-size:13px;margin-bottom:4px;">Reason <span class="required">*</span></label>
                    <textarea name="response_note" rows="4" maxlength="2000" required style="width:100%;border:1.5px solid var(--gray-300);border-radius:6px;padding:8px;"></textarea>
                    <p class="helper-text">A rejected request is closed and cannot be reopened.</p>
                </div>
            </template>

            <div style="display:flex;justify-content:flex-end;gap:8px;margin-top:14px;">
                <button type="button" class="btn btn-secondary btn-sm" @click="act.kind = ''">Cancel</button>
                <button type="submit" class="btn btn-primary btn-sm">Confirm</button>
            </div>
        </form>
    </div>
</div>
@endsection
