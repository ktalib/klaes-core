{{-- The three sections of the Surveyors Report table (recording, 2026-11-06), one table on GKN and on Examination.
     $part: bands (the section headings), heads (their columns), cells (a row's values), with $rec and $a, the row's
     ReportRecords::summary and anchor; no $rec shows dashes. $links (optional): page => url, for counts read from
     the registers rather than from the anchor's own pages. --}}
@php
    $fmt = fn ($v, $d = 2) => $v === null ? '—' : number_format((float) $v, $d);
    $open = fn ($page) => ($links[$page] ?? null) ?: ($a ? route($a['routes'] . '.' . $page . '.show', $a['key']) : null);
    $cnt = fn ($n, $url, $title) => $url
        ? '<a href="' . e($url) . '" title="' . e($title) . '"><strong>' . number_format((int) $n) . '</strong></a>'
        : '—';
@endphp
@if ($part === 'bands')
    <th colspan="3" class="rs-band rs-beacons rs-split">Beacon Nos</th>
    <th colspan="3" class="rs-band rs-coords rs-split">List of Coordinates</th>
    <th colspan="4" class="rs-band rs-obs rs-split">Computation &amp; Field Observations</th>
@elseif ($part === 'heads')
    <th class="rs-beacons rs-split" style="text-align:right;">Beacons</th>
    <th class="rs-beacons" style="text-align:right;">In Register</th>
    <th class="rs-beacons" style="text-align:right;" title="Not in the Beacon Register, or issued to another surveyor">Flagged</th>
    <th class="rs-coords rs-split" style="text-align:right;">Points</th>
    <th class="rs-coords" style="text-align:right;">Area (m²)</th>
    <th class="rs-coords" style="text-align:right;">Perimeter (m)</th>
    <th class="rs-obs rs-split" style="text-align:right;">Readings</th>
    <th class="rs-obs">Closure</th>
    <th class="rs-obs" style="text-align:right;">Misclosure (m)</th>
    <th class="rs-obs">Sketch Plan</th>
@else
    @php $rec = $rec ?? null; $a = $a ?? null; @endphp
    <td class="rs-split" style="text-align:right;">{!! $rec ? $cnt($rec['beacons'], $open('beacons'), 'Open Beacon Nos') : '—' !!}</td>
    <td style="text-align:right;">{{ $rec ? number_format($rec['in_register']) : '—' }}</td>
    <td style="text-align:right;@if ($rec && $rec['flagged']) color:#b91c1c;font-weight:600;@endif">{{ $rec ? number_format($rec['flagged']) : '—' }}</td>
    <td class="rs-split" style="text-align:right;">{!! $rec ? $cnt($rec['points'], $open('coordinates'), 'Open List of Coordinates') : '—' !!}</td>
    <td style="text-align:right;">{{ $rec ? $fmt($rec['area']) : '—' }}</td>
    <td style="text-align:right;">{{ $rec ? $fmt($rec['perimeter']) : '—' }}</td>
    @php
        // Readings open a pop-up card with the bearings and distances (user, 2026-11-19), not another page.
        // $readings (optional): ['lines' => PlanTraverseSheet lines, 'source' => label], e.g. a survey plan's
        // traverse; otherwise the report's own field readings.
        $rd = $readings ?? null;
        if (! $rd && $rec && $rec['readings'] > 0 && $a) {
            $obs = \App\Models\Survey\SurveyItsObservation::query()
                ->tap(fn ($q) => \App\Services\Survey\SurveyItsAnchors::scope($q, $a))
                ->orderBy('sort_order')->orderBy('id')->get();
            $rd = ['lines' => app(\App\Services\Survey\PlanTraverseSheet::class)->readingLines($obs), 'source' => 'Field readings on this report'];
        }
        $rdTitle = trim((string) ($fileNo ?? ''));
        if (trim((string) ($plotNo ?? '')) !== '') $rdTitle .= ($rdTitle !== '' ? ' · ' : '') . 'Plot ' . trim((string) $plotNo);
        $rdCard = $rd && $rd['lines'] ? [
            'title'      => $rdTitle,
            'source'     => $rd['source'],
            'link'       => $open('observations'),
            'lines'      => array_map(fn ($l) => array_intersect_key($l, array_flip(['line', 'from', 'bearing', 'bearing_wcb', 'distance', 'to'])), $rd['lines']),
            'closure'    => $rec['closed'] ? 'Closed' : 'Open',
            'misclosure' => $fmt($rec['misclosure'], 3),
        ] : null;
    @endphp
    <td class="rs-split" style="text-align:right;white-space:nowrap;">
        @if ($rdCard)
            <button type="button" class="rs-readings-btn" data-readings="{{ json_encode($rdCard) }}" onclick="rsShowReadings(this)" title="View the bearings and distances">
                <i class="fas fa-ruler-combined"></i> View readings ({{ number_format((int) $rec['readings']) }})
            </button>
        @else
            {!! $rec ? $cnt($rec['readings'], $open('observations'), 'Open Field Observations') : '—' !!}
        @endif
    </td>
    <td style="white-space:nowrap;">{{ ! $rec || ! $rec['readings'] ? '—' : ($rec['closed'] ? 'Closed' : 'Open') }}</td>
    <td style="text-align:right;">{{ $rec ? $fmt($rec['misclosure'], 3) : '—' }}</td>
    <td style="white-space:nowrap;">{{ $rec && $rec['sketch'] !== null ? ($rec['sketch'] ? 'Uploaded' : 'Not uploaded') : '—' }}</td>
@endif

@once
    @push('styles')
    <style>
        .rs-readings-btn { display:inline-flex; align-items:center; gap:6px; padding:3px 10px; border-radius:999px; border:1px solid #c4b5fd; background:#f5f3ff; color:#5b21b6; font-size:12px; font-weight:600; cursor:pointer; }
        .rs-readings-btn:hover { background:#ede9fe; }
        #rsReadingsModal { position:fixed; inset:0; z-index:1000; display:none; align-items:center; justify-content:center; background:rgba(17,24,39,.45); padding:16px; }
        #rsReadingsModal.open { display:flex; }
        #rsReadingsModal .rs-card { background:#fff; border-radius:12px; width:100%; max-width:760px; max-height:90vh; display:flex; flex-direction:column; box-shadow:0 20px 40px rgba(0,0,0,.2); }
        #rsReadingsModal .rs-hd { display:flex; justify-content:space-between; align-items:flex-start; gap:12px; padding:16px 20px; border-bottom:1px solid #ede9fe; background:#f5f3ff; border-radius:12px 12px 0 0; }
        #rsReadingsModal .rs-bd { padding:12px 20px; overflow:auto; }
        #rsReadingsModal table { width:100%; border-collapse:collapse; font-size:13px; }
        #rsReadingsModal th, #rsReadingsModal td { padding:7px 8px; border-bottom:1px solid #f3f4f6; text-align:left; white-space:nowrap; }
        #rsReadingsModal th { font-size:11px; text-transform:uppercase; letter-spacing:.03em; color:#6b7280; }
        #rsReadingsModal .num { text-align:right; font-variant-numeric:tabular-nums; }
        #rsReadingsModal .rs-ft { display:flex; flex-wrap:wrap; gap:16px; justify-content:space-between; align-items:center; padding:12px 20px; border-top:1px solid #f3f4f6; font-size:13px; }
    </style>
    @endpush
    @push('scripts')
    <div id="rsReadingsModal" role="dialog" aria-modal="true" aria-labelledby="rsReadingsTitle" onclick="if (event.target === this) rsCloseReadings()">
        <div class="rs-card">
            <div class="rs-hd">
                <div>
                    <div id="rsReadingsTitle" style="font-weight:700;color:#4c1d95;">Readings</div>
                    <div id="rsReadingsSub" style="font-size:12px;color:#6b7280;margin-top:2px;"></div>
                </div>
                <button type="button" onclick="rsCloseReadings()" aria-label="Close" style="border:0;background:none;font-size:22px;line-height:1;color:#6b7280;cursor:pointer;">&times;</button>
            </div>
            <div class="rs-bd">
                <table>
                    <thead><tr><th class="num">S/N</th><th>From</th><th>Bearing</th><th>Whole-circle bearing</th><th class="num">Distance (m)</th><th>To</th></tr></thead>
                    <tbody id="rsReadingsRows"></tbody>
                </table>
            </div>
            <div class="rs-ft">
                <div id="rsReadingsTotals"></div>
                <a id="rsReadingsLink" href="#" style="font-weight:600;">Open full page</a>
            </div>
        </div>
    </div>
    <script>
        function rsShowReadings(btn) {
            let d;
            try { d = JSON.parse(btn.dataset.readings || '{}'); } catch (e) { return; }
            const esc = (v) => String(v ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
            const lines = Array.isArray(d.lines) ? d.lines : [];
            document.getElementById('rsReadingsTitle').textContent = 'Readings' + (d.title ? ' — ' + d.title : '');
            document.getElementById('rsReadingsSub').textContent = (d.source || '') + ' · ' + lines.length + ' line' + (lines.length === 1 ? '' : 's');
            document.getElementById('rsReadingsRows').innerHTML = lines.map((l) =>
                '<tr><td class="num">' + esc(l.line) + '</td><td>' + esc(l.from) + '</td><td>' + esc(l.bearing) + '</td><td>' + esc(l.bearing_wcb)
                + '</td><td class="num">' + esc(l.distance) + '</td><td>' + esc(l.to) + '</td></tr>').join('');
            const total = lines.reduce((s, l) => s + (parseFloat(l.distance) || 0), 0);
            document.getElementById('rsReadingsTotals').innerHTML = 'Closure: <b>' + esc(d.closure) + '</b> &nbsp;·&nbsp; Misclosure: <b>' + esc(d.misclosure) + ' m</b> &nbsp;·&nbsp; Total distance: <b>' + total.toFixed(3) + ' m</b>';
            const link = document.getElementById('rsReadingsLink');
            link.style.display = d.link ? '' : 'none';
            if (d.link) link.href = d.link;
            document.getElementById('rsReadingsModal').classList.add('open');
        }
        function rsCloseReadings() { document.getElementById('rsReadingsModal').classList.remove('open'); }
        document.addEventListener('keydown', (e) => { if (e.key === 'Escape') rsCloseReadings(); });
    </script>
    @endpush
@endonce
