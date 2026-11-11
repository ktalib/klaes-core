@extends('surveyor_portal.layouts.portal')

@section('title', 'Surveyors Report — MLPP-SG Portal')
@section('heading', 'Surveyors Report')

{{-- Our Beacons > Surveyors Report (user, 2026-11-10): the reports written by the company's surveyors. Read only. --}}
@push('styles')
    <style>
        .ks-co-top { display:flex; flex-wrap:wrap; align-items:center; justify-content:space-between; gap:12px; margin-bottom:14px; }
        .ks-co-search { display:flex; gap:8px; }
        .ks-co-search .ks-input { width:260px; max-width:60vw; }
        .ks-num { font-variant-numeric:tabular-nums; white-space:nowrap; }
        .ks-menu { position:relative; display:inline-block; }
        .ks-menu-btn { display:grid; place-items:center; width:32px; height:32px; border:1px solid var(--ks-line); border-radius:8px; background:#fff; cursor:pointer; }
        .ks-menu-list { position:absolute; right:0; top:36px; z-index:20; min-width:150px; padding:6px; border:1px solid var(--ks-line); border-radius:10px; background:#fff; box-shadow:0 12px 28px rgba(20,23,26,.14); }
        .ks-menu-list a { display:flex; align-items:center; gap:8px; padding:8px 10px; border-radius:7px; color:var(--ks-ink); font-size:13px; text-decoration:none; }
        .ks-menu-list a:hover { background:#F3F6FA; }
    </style>
@endpush

@section('main')
    <div class="ks-co-top">
        <p class="ks-help" style="margin:0;">Your surveyors write these reports on the portal; a beacon on a report is a used beacon.</p>
        <form method="GET" class="ks-co-search" role="search">
            <label class="sr-only" for="coSearch">Search reports</label>
            <input class="ks-input" id="coSearch" name="q" value="{{ $search }}" placeholder="Report, I to S, surveyor, place…">
            <button class="ks-btn ks-btn-light"><i data-lucide="search" class="w-4 h-4"></i><span class="sr-only">Search</span></button>
        </form>
    </div>

    <div class="ks-card overflow-x-auto">
        <table class="ks-table" style="min-width:1000px;">
            <thead><tr><th>S/N</th><th>Report No.</th><th>Date</th><th>Surveyor</th><th>I to S No.</th><th>Plot No.</th><th>District</th><th>LGA</th><th>Location</th><th>Status</th><th></th></tr></thead>
            <tbody>
                @forelse ($rows as $r)
                    @php $status = $r->isIssued() ? 'Issued' : ($r->submitted_at ? 'Submitted' : 'Draft'); @endphp
                    <tr>
                        <td>{{ $rows->firstItem() + $loop->index }}</td>
                        <td class="font-semibold ks-num">{{ $r->report_ref }}</td>
                        <td class="ks-num">{{ $r->report_date ? \Illuminate\Support\Carbon::parse($r->report_date)->format('d/m/Y') : '—' }}</td>
                        <td>{{ $r->surveyor_name ?: '—' }}</td>
                        <td class="ks-num">{{ $r->its_number ?: '—' }}</td>
                        <td>{{ $r->plot_no ?: '—' }}</td>
                        <td>{{ $r->district ?: '—' }}</td>
                        <td>{{ $r->lga ? mb_strtoupper($r->lga) : '—' }}</td>
                        <td>{{ $r->location ?: '—' }}</td>
                        <td><span class="ks-badge {{ ['Draft' => 'submitted', 'Submitted' => 'issued', 'Issued' => 'accepted'][$status] }}">{{ $status }}</span></td>
                        <td style="text-align:right;">
                            <div class="ks-menu">
                                <button type="button" class="ks-menu-btn" aria-label="Actions" aria-haspopup="menu" aria-expanded="false"><i data-lucide="more-vertical" class="w-4 h-4"></i></button>
                                <div class="ks-menu-list" role="menu" hidden>
                                    <a role="menuitem" href="{{ route('surveyor-portal.company.reports.print', $r->id) }}" target="_blank" rel="noopener"><i data-lucide="printer" class="w-4 h-4"></i> View / Print</a>
                                </div>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="11" class="ks-help" style="text-align:center;padding:28px;">
                        @if ($search !== '') No report matches “{{ $search }}”. @else Your surveyors have not written a Surveyor's Report yet. @endif
                    </td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $rows->links() }}</div>
@endsection

@push('scripts')
<script>
(function () {
    function closeAll(except) {
        document.querySelectorAll('.ks-menu-list').forEach(function (m) {
            if (m === except) return;
            m.hidden = true;
            var b = m.parentElement.querySelector('.ks-menu-btn');
            if (b) b.setAttribute('aria-expanded', 'false');
        });
    }
    document.addEventListener('click', function (e) {
        var btn = e.target.closest('.ks-menu-btn');
        if (btn) {
            e.stopPropagation();
            var list = btn.parentElement.querySelector('.ks-menu-list'), open = list.hidden;
            closeAll(list);
            list.hidden = !open;
            if (open) {
                // Placed against the window so a scrolling table never clips it.
                var rc = btn.getBoundingClientRect();
                list.style.position = 'fixed';
                list.style.top = (rc.bottom + 4) + 'px';
                list.style.left = Math.max(8, rc.right - list.offsetWidth) + 'px';
                list.style.right = 'auto';
            }
            btn.setAttribute('aria-expanded', open ? 'true' : 'false');
            return;
        }
        if (!e.target.closest('.ks-menu-list')) closeAll();
    });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') closeAll(); });
    window.addEventListener('scroll', function () { closeAll(); }, true);
})();
</script>
@endpush
