@extends('surveyor_portal.layouts.portal')

@section('title', "Surveyor's Report — MLPP-SG Portal")
@section('heading', "Surveyor's Report")

{{-- Its own sidebar page (user, 2026-11-09). One table, three sections like Survey's: Beacon Nos, List of
     Coordinates, Computation & Field Observations. --}}
@push('styles')
<style>
    .ks-reps-top { display:flex; flex-wrap:wrap; align-items:center; justify-content:space-between; gap:12px; margin-bottom:14px; }
    .ks-reps th.grp { text-align:center; border-bottom:0; font-size:11px; letter-spacing:.06em; }
    .ks-reps th.g1, .ks-reps td.g1 { background:#F2F6FC; }
    .ks-reps th.g2, .ks-reps td.g2 { background:#F1F8F3; }
    .ks-reps th.g3, .ks-reps td.g3 { background:#FDF8EC; }
    .ks-num { font-variant-numeric:tabular-nums; white-space:nowrap; }
    .ks-menu { position:relative; display:inline-block; }
    .ks-menu-btn { display:grid; place-items:center; width:32px; height:32px; border:1px solid var(--ks-line); border-radius:8px; background:#fff; cursor:pointer; }
    .ks-menu-list { position:absolute; right:0; top:36px; z-index:20; min-width:170px; padding:6px; border:1px solid var(--ks-line); border-radius:10px; background:#fff; box-shadow:0 12px 28px rgba(20,23,26,.14); }
    .ks-menu-list a { display:flex; align-items:center; gap:8px; padding:8px 10px; border-radius:7px; color:var(--ks-ink); font-size:13px; text-decoration:none; }
    .ks-menu-list a:hover { background:#F3F6FA; }
</style>
@endpush

@section('main')
    <div class="ks-reps-top">
        <p class="ks-help" style="margin:0;">Enter the beacons you used with their coordinates, readings and the report. Balance: <strong>{{ number_format($balance['left']) }}</strong> of {{ number_format($balance['issued']) }} issued beacons still to use.</p>
        <a class="ks-btn ks-btn-primary" href="{{ route('surveyor-portal.reports.create') }}"><i data-lucide="plus" class="w-4 h-4"></i> New Surveyor's Report</a>
    </div>

    @include('surveyor_portal.partials._unused_beacons')

    <div class="ks-card" style="overflow-x:auto;overflow-y:visible;">
        <table class="ks-table ks-reps" style="min-width:1300px;">
            <thead>
                <tr>
                    <th colspan="8"></th>
                    <th class="grp g1">Beacon Nos</th>
                    <th class="grp g2">List of Coordinates</th>
                    <th class="grp g3" colspan="4">Computation &amp; Field Observations</th>
                    <th colspan="2"></th>
                </tr>
                <tr>
                    <th>S/N</th><th>Report No.</th><th>Date</th><th>Started from</th><th>Plot No.</th><th>District</th><th>LGA</th><th>Location</th>
                    <th class="g1">Beacons</th>
                    <th class="g2">Points</th>
                    <th class="g3">Area (m²)</th><th class="g3">Perimeter (m)</th><th class="g3">Readings</th><th class="g3">Misclosure (m)</th>
                    <th>Status</th><th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($rows as $row)
                    @php
                        $r = $row['report']; $x = $row['rec'];
                        $status = $r->isIssued() ? 'Issued' : ($r->submitted_at ? 'Submitted' : 'Draft');
                    @endphp
                    <tr>
                        <td>{{ $loop->iteration }}</td>
                        <td class="ks-num"><a href="{{ route('surveyor-portal.reports.show', $r->id) }}" style="color:var(--ks-blue);font-weight:600;">{{ $r->report_ref }}</a></td>
                        <td class="ks-num">{{ $r->report_date ? $r->report_date->format('d/m/Y') : '—' }}</td>
                        <td>{{ $r->its_number ? 'I to S ' . $r->its_number : 'My beacons' }}</td>
                        <td>{{ $r->plot_no ?: '—' }}</td>
                        <td>{{ $r->district ?: '—' }}</td>
                        <td>{{ $r->lga ? mb_strtoupper($r->lga) : '—' }}</td>
                        <td>{{ $r->location ?: '—' }}</td>
                        <td class="g1 ks-num">{{ $row['beacons'] }}</td>
                        <td class="g2 ks-num">{{ $x['points'] }}</td>
                        <td class="g3 ks-num">{{ $x['area'] !== null ? number_format($x['area'], 2) : '—' }}</td>
                        <td class="g3 ks-num">{{ $x['perimeter'] !== null ? number_format($x['perimeter'], 2) : '—' }}</td>
                        <td class="g3 ks-num">{{ $x['readings'] }}</td>
                        <td class="g3 ks-num">{{ $x['misclosure'] !== null ? number_format($x['misclosure'], 3) : '—' }}</td>
                        <td><span class="ks-badge {{ ['Draft' => 'submitted', 'Submitted' => 'issued', 'Issued' => 'accepted'][$status] }}">{{ $status }}</span></td>
                        <td style="text-align:right;">
                            <div class="ks-menu">
                                <button type="button" class="ks-menu-btn" aria-label="Actions" aria-haspopup="menu" aria-expanded="false"><i data-lucide="more-vertical" class="w-4 h-4"></i></button>
                                <div class="ks-menu-list" role="menu" hidden>
                                    <a role="menuitem" href="{{ route('surveyor-portal.reports.show', $r->id) }}"><i data-lucide="{{ $status === 'Draft' ? 'pencil' : 'eye' }}" class="w-4 h-4"></i> {{ $status === 'Draft' ? 'Open and edit' : 'View' }}</a>
                                    <a role="menuitem" href="{{ route('surveyor-portal.reports.print', $r->id) }}" target="_blank" rel="noopener"><i data-lucide="printer" class="w-4 h-4"></i> Print</a>
                                </div>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="16" class="ks-help" style="text-align:center;padding:28px;">No Surveyor's Report yet. Start one with <strong>New Surveyor's Report</strong>.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
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
                list.style.position = "fixed";
                list.style.top = (rc.bottom + 4) + "px";
                list.style.left = Math.max(8, rc.right - list.offsetWidth) + "px";
                list.style.right = "auto";
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
