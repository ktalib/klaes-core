{{-- KSSGOF Admin > Map Requests (user, 2026-11-06): what surveyors asked for on the portal. The Production Unit works them. --}}
@php
    $mapBadge = ['pending' => 'pending', 'in_progress' => 'review', 'fulfilled' => 'completed', 'rejected' => 'rejected'];
@endphp
<div class="table-wrapper">
    <div class="table-scroll">
        <table>
            <thead>
                <tr>
                    <th>S/N</th><th>Request No.</th><th>Date</th><th>Type</th><th>Company</th><th>Surveyor's Name</th><th>Phone</th><th>Map</th><th>Map Type</th><th>Formats</th><th>Officer</th><th>Status</th><th style="width:48px;"></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($maps as $m)
                    <tr>
                        <td>{{ $maps->firstItem() + $loop->index }}</td>
                        <td style="white-space:nowrap;"><strong>{{ $m->request_ref }}</strong></td>
                        <td style="white-space:nowrap;">{{ optional($m->received_date)->format('d/m/Y') }}</td>
                        <td>{{ $m->portalAccount?->type_label ?: '—' }}</td>
                        <td>{{ $m->portalAccount?->company_name ?: '—' }}</td>
                        <td>{{ $m->contact_name }}</td>
                        <td style="white-space:nowrap;">{{ $m->contact_phone }}</td>
                        <td>{{ $m->map_name }}</td>
                        <td>{{ $m->mapTypeLabel() ?: '—' }}</td>
                        <td>{{ $m->formatsLabel() }}</td>
                        <td>{{ $m->production_officer ?: '—' }}</td>
                        <td style="white-space:nowrap;">
                            <span class="status-badge {{ $mapBadge[$m->status] ?? 'pending' }}"><span class="dot"></span>{{ $m->statusLabel() }}</span>
                            @if ($m->response_note)<div class="helper-text" style="margin:2px 0 0;white-space:normal;">{{ $m->response_note }}</div>@endif
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
                                    <a href="{{ route('survey-module.sdi.map-requests.show', ['mapRequest' => $m, 'via' => 'kssgof']) }}" class="{{ $item }}"><i data-lucide="eye" class="w-4 h-4 text-gray-500"></i> View Request</a>
                                    @if ($m->isOpen())
                                        <a href="{{ route('survey-module.sdi.production') }}" class="{{ $item }}"><i data-lucide="factory" class="w-4 h-4 text-gray-500"></i> Production Unit</a>
                                    @endif
                                    @if ($m->request_file_path)
                                        <a href="{{ route('survey-module.sdi.map-requests.request-file', $m) }}" class="{{ $item }}"><i data-lucide="file-down" class="w-4 h-4 text-gray-500"></i> Download Scanned Request</a>
                                    @endif
                                    @if ($m->deliverable_path)
                                        <a href="{{ route('survey-module.sdi.map-requests.deliverable', $m) }}" class="{{ $item }}"><i data-lucide="download" class="w-4 h-4 text-gray-500"></i> Download Map</a>
                                    @endif
                                </div>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="13" style="text-align:center;padding:28px;color:var(--gray-500);">No map requests{{ $filtered ? ' for this selection' : ' yet' }}.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="table-footer">
        <span>Showing {{ $maps->firstItem() ?? 0 }}–{{ $maps->lastItem() ?? 0 }} of {{ number_format($maps->total()) }} requests</span>
        <div class="pagination">{{ $maps->links() }}</div>
    </div>
</div>
