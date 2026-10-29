@extends('survey_module.layouts.klaes')

@section('page-title', 'Map Request — SDI — KLAES')

@php
    use App\Models\Sdi\SdiMapRequest;

    $PageTitle = 'SDI · Map Request';
    $PageDescription = 'Maps requested by MDAs and other bodies; each request feeds the Production Unit.';
    $svItem = 'flex items-center gap-2 w-full px-3 py-2 text-sm text-left text-gray-700 hover:bg-gray-50';
    $inputStyle = 'padding:8px 14px;border:1.5px solid var(--gray-300);border-radius:var(--radius-sm);font-family:var(--font);font-size:14px;background:#fff;';
    $badge = [SdiMapRequest::PENDING => 'pending', SdiMapRequest::IN_PROGRESS => 'review', SdiMapRequest::FULFILLED => 'completed', SdiMapRequest::REJECTED => 'rejected'];
    $filters = ['q', 'status', 'mda', 'type'];
@endphp

@section('survey-content')
@include('survey_module.sdi._head')
@include('survey_module.partials._flash')

<div class="page-header">
    <div></div>
    <a class="btn btn-primary" href="{{ route('survey-module.sdi.map-requests.create') }}"><i class="fas fa-plus"></i> New Map Request</a>
</div>

<div class="kpi-grid">
    @foreach (SdiMapRequest::STATUS_LABELS as $s => $label)
        <a class="kpi-card" href="{{ route('survey-module.sdi.map-requests', ['status' => $s]) }}" style="text-decoration:none;color:inherit;">
            <div class="kpi-label">{{ $label }}</div>
            <div class="kpi-value">{{ number_format((int) ($counts[$s] ?? 0)) }}</div>
        </a>
    @endforeach
</div>

<form method="GET" class="table-toolbar">
    <div class="left" style="flex-wrap:wrap;">
        <input type="text" name="q" value="{{ request('q') }}" placeholder="Search ref, map, MDA, contact…" style="{{ $inputStyle }}width:260px;" />
        <select name="status" style="{{ $inputStyle }}">
            <option value="">All Status</option>
            @foreach (SdiMapRequest::STATUS_LABELS as $s => $label)
                <option value="{{ $s }}" @selected(request('status') === $s)>{{ $label }}</option>
            @endforeach
        </select>
        <select name="mda" style="{{ $inputStyle }}max-width:260px;">
            <option value="">All MDAs</option>
            @foreach ($mdas as $md)
                <option value="{{ $md->id }}" @selected((string) request('mda') === (string) $md->id)>{{ $md->abbreviation ?: $md->name }}</option>
            @endforeach
        </select>
        <select name="type" style="{{ $inputStyle }}">
            <option value="">All Map Types</option>
            @foreach ($mapTypes as $t)
                <option value="{{ $t->id }}" @selected((string) request('type') === (string) $t->id)>{{ $t->name }}</option>
            @endforeach
        </select>
        <button class="btn btn-secondary" type="submit"><i class="fas fa-filter"></i> Filter</button>
        @if (request()->hasAny($filters))
            <a class="btn btn-outline btn-sm" href="{{ route('survey-module.sdi.map-requests') }}">Clear</a>
        @endif
    </div>
</form>

<div class="table-wrapper">
    <div class="table-scroll">
        <table>
            <thead>
                <tr>
                    <th>S/N</th>
                    <th>Request No</th>
                    <th>Date Received</th>
                    <th>Requesting MDA</th>
                    <th>Map</th>
                    <th>Formats</th>
                    <th>Priority / Due</th>
                    <th>Status</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($requests as $m)
                    <tr>
                        <td>{{ $requests->firstItem() + $loop->index }}</td>
                        <td><strong>{{ $m->request_ref }}</strong></td>
                        <td>{{ optional($m->received_date)->format('d M Y') }}</td>
                        <td>
                            {{ $m->requesterName() }}
                            <div style="font-size:12px;color:var(--gray-500);">{{ $m->contact_name }} · {{ $m->contact_phone }}</div>
                        </td>
                        <td>
                            {{ $m->map_name }}
                            <div style="font-size:12px;color:var(--gray-500);">{{ $m->mapType?->name ?? '—' }}{{ $m->map_reference ? ' · ' . $m->map_reference : '' }}</div>
                        </td>
                        <td>{{ $m->formatsLabel() }}</td>
                        <td>
                            <span class="sdi-chip {{ $m->priority === 'high' ? 'high' : '' }}">{{ $m->priorityLabel() }}</span>
                            @if ($m->due_date)
                                <div style="font-size:12px;margin-top:3px;" @class(['sdi-chip late' => $m->isOverdue()])>Due {{ $m->due_date->format('d M Y') }}</div>
                            @endif
                        </td>
                        <td>
                            <span class="status-badge {{ $badge[$m->status] ?? 'pending' }}"><span class="dot"></span>{{ $m->statusLabel() }}</span>
                            @if ($m->production_officer)<div style="font-size:12px;color:var(--gray-500);">{{ $m->production_officer }}</div>@endif
                        </td>
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
                                    <a href="{{ route('survey-module.sdi.map-requests.show', $m) }}" class="{{ $svItem }}"><i data-lucide="eye" class="w-4 h-4 text-gray-500"></i> View</a>
                                    @if ($m->deliverable_path)
                                        <a href="{{ route('survey-module.sdi.map-requests.deliverable', $m) }}" class="{{ $svItem }}"><i data-lucide="download" class="w-4 h-4 text-gray-500"></i> Download Map</a>
                                    @endif
                                    @if ($m->status === SdiMapRequest::PENDING)
                                        <a href="{{ route('survey-module.sdi.map-requests.edit', $m) }}" class="{{ $svItem }}"><i data-lucide="pencil" class="w-4 h-4 text-gray-500"></i> Edit</a>
                                        <form method="POST" action="{{ route('survey-module.sdi.map-requests.destroy', $m) }}"
                                              onsubmit="return confirm('Delete {{ $m->request_ref }}? Only for a request logged in error.');">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="{{ $svItem }} text-red-600"><i data-lucide="trash-2" class="w-4 h-4"></i> Delete</button>
                                        </form>
                                    @endif
                                    @if ($m->isOpen())
                                        <a href="{{ route('survey-module.sdi.production') }}" class="{{ $svItem }}"><i data-lucide="factory" class="w-4 h-4 text-gray-500"></i> Open in Production Unit</a>
                                    @endif
                                </div>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" style="text-align:center;padding:28px;color:var(--gray-500);">
                            <i class="fas fa-map" style="font-size:24px;display:block;margin-bottom:8px;opacity:.4;"></i>
                            @if (request()->hasAny($filters))
                                No map requests match these filters.
                            @else
                                No map requests yet. Use <strong>New Map Request</strong> above.
                            @endif
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="table-footer">
        <span>Showing {{ $requests->firstItem() ?? 0 }}–{{ $requests->lastItem() ?? 0 }} of {{ number_format($requests->total()) }} requests</span>
        <div class="pagination">{{ $requests->links() }}</div>
    </div>
</div>
@endsection
