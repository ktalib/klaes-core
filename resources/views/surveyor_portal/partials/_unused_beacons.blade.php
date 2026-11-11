{{--
    The beacons still on the balance (user, 2026-11-09: "there should be where to select the beacon, and complete
    the update"). Tick the ones used and Complete update opens a Surveyor's Report with them picked. A beacon
    already picked on an open report links to that report instead. Needs $unused (PortalSurveyorReports::unusedBeacons).
--}}
@php
    $waitingRequests = \App\Models\Survey\SurveyBeaconRequest::where('surveyor_portal_account_id', auth('surveyor')->id())
        ->where('status', \App\Models\Survey\SurveyBeaconRequest::SUBMITTED)->orderBy('id')->pluck('request_no');
@endphp
@if ($unused->isEmpty())
    {{-- Shown even when empty, so the surveyor knows where beacons will appear and why there are none. --}}
    <section class="ks-card" style="margin-bottom:16px;padding:16px 18px;" aria-labelledby="ksUnusedTitle">
        <h2 id="ksUnusedTitle" style="display:flex;align-items:center;gap:8px;margin:0;font-size:16px;font-weight:800;">Beacons to update <span class="ks-badge">0</span></h2>
        <p class="ks-help" style="margin:4px 0 0;">
            @if ($waitingRequests->isNotEmpty())
                Your request {{ $waitingRequests->implode(', ') }} is waiting for the Surveyor General. Once the beacons are issued they appear here: select the ones you used and click Complete update.
            @else
                No beacons to update. Beacons issued to you appear here: select the ones you used and click Complete update.
            @endif
        </p>
    </section>
@else
    <section class="ks-card ks-unused" aria-labelledby="ksUnusedTitle">
        <form method="GET" action="{{ route('surveyor-portal.reports.create') }}" id="ksUnusedForm">
            <div class="ks-unused-head">
                <div>
                    <h2 id="ksUnusedTitle">Beacons to update <span class="ks-badge submitted">{{ $unused->count() }}</span></h2>
                    <p class="ks-help" style="margin:2px 0 0;">Select the beacons you used, then complete their update on a Surveyor's Report.</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <button type="button" class="ks-btn ks-btn-light" id="ksUnusedAll" style="padding:8px 12px;">Select all</button>
                    <button class="ks-btn ks-btn-primary" id="ksUnusedGo" disabled><i data-lucide="clipboard-check" class="w-4 h-4"></i> Complete update <span id="ksUnusedCount"></span></button>
                </div>
            </div>
            <div class="ks-unused-list">
                @foreach ($unused as $b)
                    @if ($b->draftReport)
                        <a class="ks-unused-item on-draft" href="{{ route('surveyor-portal.reports.show', $b->draftReport->id) }}" title="Already picked on {{ $b->draftReport->report_ref }}">
                            <i data-lucide="file-pen" class="w-4 h-4"></i>
                            <span><strong>{{ \App\Models\Survey\SurveyBeaconRegister::display($b->beacon_no) }}</strong><small>Continue {{ $b->draftReport->report_ref }}</small></span>
                        </a>
                    @else
                        <label class="ks-unused-item">
                            <input type="checkbox" name="beacons[]" value="{{ $b->beacon_no }}">
                            <span><strong>{{ \App\Models\Survey\SurveyBeaconRegister::display($b->beacon_no) }}</strong><small>{{ $b->register?->request?->request_no }}</small></span>
                        </label>
                    @endif
                @endforeach
            </div>
        </form>
    </section>
    @push('styles')
    <style>
        .ks-unused { margin-bottom:16px; padding:16px 18px; }
        .ks-unused-head { display:flex; flex-wrap:wrap; align-items:center; justify-content:space-between; gap:12px; margin-bottom:12px; }
        .ks-unused-head h2 { display:flex; align-items:center; gap:8px; margin:0; font-size:16px; font-weight:800; }
        .ks-unused-list { display:grid; grid-template-columns:repeat(auto-fill,minmax(160px,1fr)); gap:8px; max-height:300px; overflow:auto; padding:2px; }
        .ks-unused-item { display:flex; align-items:center; gap:9px; padding:9px 11px; border:1px solid var(--ks-line); border-radius:10px; background:#fff; color:var(--ks-ink); text-decoration:none; cursor:pointer; }
        .ks-unused-item:hover { border-color:var(--ks-blue); }
        .ks-unused-item:has(input:checked) { border-color:var(--ks-blue); background:#F3F6FB; box-shadow:0 0 0 2px rgba(11,58,126,.08); }
        .ks-unused-item input { width:16px; height:16px; accent-color:var(--ks-blue); }
        .ks-unused-item strong { display:block; font-size:13px; font-variant-numeric:tabular-nums; }
        .ks-unused-item small { display:block; color:var(--ks-soft); font-size:11px; }
        .ks-unused-item.on-draft { border-style:dashed; background:#FFFBEB; }
        .ks-unused-item.on-draft svg { color:#8A5A00; }
    </style>
    @endpush
    @push('scripts')
    <script>
    (function () {
        var form = document.getElementById('ksUnusedForm');
        if (!form) return;
        var boxes = form.querySelectorAll('input[name="beacons[]"]'), go = document.getElementById('ksUnusedGo'),
            count = document.getElementById('ksUnusedCount'), all = document.getElementById('ksUnusedAll');
        function sync() {
            var n = Array.prototype.filter.call(boxes, function (b) { return b.checked; }).length;
            go.disabled = n === 0;
            count.textContent = n ? '(' + n + ')' : '';
            all.textContent = n === boxes.length && n ? 'Clear' : 'Select all';
        }
        boxes.forEach(function (b) { b.addEventListener('change', sync); });
        all.addEventListener('click', function () {
            var on = Array.prototype.some.call(boxes, function (b) { return !b.checked; });
            boxes.forEach(function (b) { b.checked = on; });
            sync();
        });
        if (!boxes.length) { all.hidden = true; go.hidden = true; }
        sync();
    })();
    </script>
    @endpush
@endif
