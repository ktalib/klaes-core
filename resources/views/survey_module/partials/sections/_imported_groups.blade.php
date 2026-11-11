{{-- Imported tab (user, 2026-11-06): the lands grouped by TP No., then the Computation Sheet's sheets, staged and amber,
     all in one table's columns. Columns from the sheet are empty on a land, and the land's on a staged line. --}}
@php
    $csSearch = trim((string) request('q', ''));
    $groupNo = 0;
    $sum = fn ($v, $d = 3) => $v === null ? '—' : number_format((float) $v, $d);
@endphp
@foreach ($tpGroups as $tpNo => $lands)
    @php
        $groupNo++;
        $tpKey = $tpNo === '' ? 'none' : $tpNo;
        $names = $lands->map(fn ($c) => $c->project?->display_name)->filter()->unique();
        // No blanks on the layout row (user, 2026-11-09): its first block's details, and totals over its blocks.
        $lead = $lands->first();
        $tot = fn ($k) => $lands->sum(fn ($c) => (float) ($lineSums[$c->id]->{$k} ?? 0));
        $hasTot = fn ($k) => $lands->contains(fn ($c) => ($lineSums[$c->id]->{$k} ?? null) !== null);
        $dash = '<span class="muted">—</span>';
    @endphp
    <tr class="imp-group" data-tp-group="{{ $tpKey }}" aria-expanded="false" tabindex="0">
        <td class="nowrap"><i class="fas fa-chevron-right" data-chevron></i> {{ $groupNo }}</td>
        <td class="wide strong">{{ $names->implode(' · ') ?: '—' }}
            <div style="font-size:11px;font-weight:400;color:var(--gray-500);">{{ number_format($lands->count()) }} {{ $lands->count() === 1 ? 'block' : 'blocks' }}</div></td>
        <td class="nowrap">{!! $tpNo !== '' ? '<span class="tp">' . e($tpNo) . '</span>' : 'No TP No.' !!}</td>
        <td>@if ($lead->isMonetary())<span class="status-badge active"><span class="dot"></span>Monetary Compensation</span>@else<span class="status-badge completed"><span class="dot"></span>Land to Land</span>@endif</td>
        @php $cdSurveyors = $lands->map(fn ($c) => $c->allocation?->surveyor_name ?: $c->survey_officer)->filter()->unique()->values(); @endphp
        <td class="mid" style="font-weight:400;" title="{{ $cdSurveyors->implode(', ') }}">{{ $cdSurveyors->isEmpty() ? 'Not stated' : $cdSurveyors->take(2)->implode(', ') . ($cdSurveyors->count() > 2 ? ' +' . ($cdSurveyors->count() - 2) . ' more' : '') }}</td>
        <td class="mid" style="font-weight:400;">{{ $lead->property_location ?: '—' }}</td>
        <td class="muted">—</td><td class="muted">—</td><td class="muted">—</td><td class="muted">—</td><td class="muted">—</td><td class="num muted">—</td>
        <td class="num">{{ $lands->whereNotNull('area_ha')->isEmpty() ? '—' : number_format((float) $lands->sum('area_ha'), 4) }}</td>
        <td class="num">{!! $hasTot('creatable') ? number_format($tot('creatable'), 2) : $dash !!}</td>
        <td class="num">{!! $hasTot('govt') ? number_format($tot('govt'), 2) : $dash !!}</td>
        <td class="num">{!! $hasTot('farm') ? number_format($tot('farm'), 2) : $dash !!}</td>
        <td class="nowrap" style="font-weight:400;">{!! $tot('plots') > 0 ? number_format($tot('plots')) . ' plots' : $dash !!}</td>
        <td class="muted">—</td>
        <td class="ctr">{{ number_format($lands->sum('beneficiaries_count')) }}</td>
        <td><span class="status-badge {{ $badgeClass($lead->status) }}"><span class="dot"></span>{{ $statusLabel($lead->status) }}</span></td>
        @include('survey_module.partials._created_cells', ['row' => $lead])
        <td class="col-actions"></td>
    </tr>
    @foreach ($lands as $c)
        @include('survey_module.partials.sections._compensation_case_row', ['rowSn' => $groupNo . '.' . $loop->iteration, 'rowOf' => $tpKey])
    @endforeach
@endforeach

@foreach ($staged as $g)
    @php $groupNo++; @endphp
    <tr class="imp-group imp-staged" data-cs-sheet="{{ $g->sheet_name }}" data-group="{{ $groupNo }}" aria-expanded="false" tabindex="0">
        <td class="nowrap"><i class="fas fa-chevron-right" data-chevron></i> {{ $groupNo }}</td>
        {{-- The KMC Phase1 Access export's tables group beside the workbook's sheets (user, 2026-11-11). --}}
        <td class="wide strong">{{ ($g->source_file ?? '') === \App\Services\Survey\KmcAccessImport::SOURCE ? 'KMC Access' : 'Computation Sheet' }} · {{ $g->sheet_name }}
            @if ((int) ($g->photos ?? 0) > 0)<div style="font-size:11px;font-weight:400;color:var(--gray-500);"><i class="fas fa-camera"></i> {{ number_format($g->photos) }} with passport photo</div>@endif</td>
        <td class="muted">—</td>
        <td></td>
        <td class="nowrap">{{ number_format($g->lines) }} {{ (int) $g->lines === 1 ? 'line' : 'lines' }}</td>
        <td></td>
        <td></td><td></td><td></td><td></td>
        <td class="nowrap">{{ $g->sheet_name }}</td>
        <td class="num">{{ $sum($g->area_m2) }}</td>
        <td class="num">{{ $sum($g->area_ha) }}</td>
        <td class="num">{{ $sum($g->creatable) }}</td>
        <td class="num">{{ $sum($g->govt_50) }}</td>
        <td class="num">{{ $sum($g->farm_50) }}</td>
        <td class="nowrap">{{ number_format($g->with_plots) }} with plots</td>
        <td></td>
        <td class="ctr muted">—</td>
        <td><span class="status-badge pending"><span class="dot"></span>Staged</span></td>
        <td></td>
        <td></td>
        <td class="col-actions"></td>
    </tr>
@endforeach

@if ($groupNo === 0)
    <tr>
        <td colspan="23" style="text-align:center;padding:28px;color:var(--gray-500);">
            <i class="fas fa-folder-open" style="font-size:24px;display:block;margin-bottom:8px;opacity:.4;"></i>
            @if (request()->hasAny(['q', 'status', 'project', 'scheme']))
                Nothing matches these filters.
                <a href="{{ route('survey-module.compensation.cases', ['origin' => 'imported']) }}">Clear them</a>.
            @else
                No imported lands.
            @endif
        </td>
    </tr>
@endif

@push('scripts')
<style>
    .survey-proto .imp-group { cursor: pointer; font-weight: 600; background: var(--gray-50, #f9fafb); }
    .survey-proto .imp-group [data-chevron] { font-size: 11px; color: var(--gray-500); margin-right: 6px; transition: transform .15s; }
    .survey-proto .imp-group[aria-expanded="true"] [data-chevron] { transform: rotate(90deg); }
    /* Staged, not lands or farmers: coloured so they read apart (user, 2026-11-06). */
    .survey-proto .sv-dt tr.imp-staged > td, .survey-proto .sv-dt tr.imp-staged .col-actions { background: #fef3c7; }
    .survey-proto .sv-dt tr.imp-staged:hover > td { background: #fde68a; }
    .survey-proto .sv-dt tr.imp-line > td, .survey-proto .sv-dt tr.imp-line .col-actions { background: #fffbeb; }
    .survey-proto .sv-dt tr.imp-line:hover > td { background: #fef9c3; }
    .survey-proto .sv-dt tr.imp-line-total > td { font-weight: 600; }
    /* Blocks open to their farms (user, 2026-11-09). */
    .survey-proto .sv-dt tr.imp-land { cursor: pointer; }
    .survey-proto .sv-dt tr.imp-land [data-chevron] { font-size: 10px; color: var(--gray-500); margin-right: 4px; transition: transform .15s; }
    .survey-proto .sv-dt tr.imp-land[aria-expanded="true"] [data-chevron] { transform: rotate(90deg); }
    .survey-proto .sv-dt tr.imp-farm > td { background: #f8fafc; font-size: 13px; }
    .survey-proto .sv-dt tr.imp-farm > td:first-child { padding-left: 28px; }
</style>
<script>
(function () {
    var body = document.getElementById('impGroups');
    if (!body) return;
    var url = body.dataset.rowsUrl, q = body.dataset.q || '', KEY = 'svImportedOpen';
    function openList() { try { return JSON.parse(sessionStorage.getItem(KEY)) || []; } catch (e) { return []; } }
    function remember(list) { try { sessionStorage.setItem(KEY, JSON.stringify(list)); } catch (e) {} }
    function keyOf(row) { return row.dataset.tpGroup ? 'tp:' + row.dataset.tpGroup : 'cs:' + row.dataset.csSheet; }
    function linesOf(sheet) { return Array.prototype.filter.call(body.querySelectorAll('tr[data-of]'), function (r) { return r.dataset.of === sheet; }); }
    function load(sheet, group, page, after) {
        fetch(url + '?sheet=' + encodeURIComponent(sheet) + '&g=' + encodeURIComponent(group) + '&page=' + page + '&q=' + encodeURIComponent(q), { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (r) { if (!r.ok) throw new Error(r.status); return r.text(); })
            .then(function (html) { after.insertAdjacentHTML('afterend', html); })
            .catch(function () { after.insertAdjacentHTML('afterend', '<tr class="imp-staged"><td colspan="23" style="color:#b91c1c;">This sheet could not be loaded. Refresh the page and open it again.</td></tr>'); });
    }
    // A block's farms (user, 2026-11-09): loaded under the block the first time it opens, 50 at a time.
    var farmsUrl = body.dataset.farmsUrl;
    function farmsOf(land) { return Array.prototype.filter.call(body.querySelectorAll('tr[data-farm-of]'), function (r) { return r.dataset.farmOf === land; }); }
    function loadFarms(land, sn, page, after) {
        fetch(farmsUrl + '?case=' + encodeURIComponent(land) + '&sn=' + encodeURIComponent(sn) + '&page=' + page, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (r) { if (!r.ok) throw new Error(r.status); return r.text(); })
            .then(function (html) { after.insertAdjacentHTML('afterend', html); if (window.lucide) window.lucide.createIcons(); })
            .catch(function () { after.insertAdjacentHTML('afterend', '<tr class="imp-farm" data-farm-of="' + land + '"><td colspan="23" style="color:#b91c1c;">The farms could not be loaded. Refresh the page and open the block again.</td></tr>'); });
    }
    function toggleLand(row, force) {
        var on = force !== undefined ? force : row.getAttribute('aria-expanded') !== 'true';
        row.setAttribute('aria-expanded', on ? 'true' : 'false');
        var farms = farmsOf(row.dataset.land);
        if (farms.length) farms.forEach(function (r) { r.hidden = !on; });
        else if (on) loadFarms(row.dataset.land, row.dataset.sn, 1, row);
    }
    function toggle(row, keep) {
        var on = row.getAttribute('aria-expanded') !== 'true';
        row.setAttribute('aria-expanded', on ? 'true' : 'false');
        if (row.dataset.tpGroup) {
            Array.prototype.forEach.call(body.querySelectorAll('tr[data-tp-of]'), function (r) {
                if (r.dataset.tpOf !== row.dataset.tpGroup) return;
                r.hidden = !on;
                // A block's farms show with the layout only while the block itself is open.
                if (r.dataset.land) farmsOf(r.dataset.land).forEach(function (f) { f.hidden = !on || r.getAttribute('aria-expanded') !== 'true'; });
            });
        } else {
            var lines = linesOf(row.dataset.csSheet);
            if (lines.length) lines.forEach(function (r) { r.hidden = !on; });
            else if (on) load(row.dataset.csSheet, row.dataset.group, 1, row);
        }
        if (keep !== false) {
            var k = keyOf(row), list = openList().filter(function (s) { return s !== k; });
            if (on) list.push(k);
            remember(list);
        }
    }
    body.addEventListener('click', function (e) {
        var farmMore = e.target.closest('[data-farm-more-btn]');
        if (farmMore) {
            var ft = farmMore.closest('tr'), land = ft.dataset.farmOf, fpage = ft.dataset.farmMore, fprev = ft.previousElementSibling;
            var landRow = body.querySelector('tr[data-land="' + land + '"]');
            ft.remove();
            loadFarms(land, landRow ? landRow.dataset.sn : '', fpage, fprev);
            return;
        }
        // A block row opens its farms; its own action menu and links are left alone.
        var landTr = e.target.closest('tr.imp-land');
        if (landTr && !e.target.closest('.col-actions, a, button, form')) { toggleLand(landTr); return; }
        var more = e.target.closest('[data-cs-more]');
        if (more) {
            var tr = more.closest('tr'), sheet = tr.dataset.of, page = tr.dataset.more, prev = tr.previousElementSibling;
            var head = Array.prototype.filter.call(body.querySelectorAll('tr[data-cs-sheet]'), function (r) { return r.dataset.csSheet === sheet; })[0];
            tr.remove();
            load(sheet, head.dataset.group, page, prev);
            return;
        }
        var row = e.target.closest('tr.imp-group');
        if (row) toggle(row);
    });
    body.addEventListener('keydown', function (e) {
        if (e.target.matches && e.target.matches('tr.imp-group') && (e.key === 'Enter' || e.key === ' ')) { e.preventDefault(); toggle(e.target); }
        if (e.target.matches && e.target.matches('tr.imp-land') && (e.key === 'Enter' || e.key === ' ')) { e.preventDefault(); toggleLand(e.target); }
    });
    // A search opens what it found; otherwise the groups left open stay open.
    var open = openList();
    Array.prototype.forEach.call(body.querySelectorAll('tr.imp-group'), function (row) {
        if (q !== '' || open.indexOf(keyOf(row)) !== -1) toggle(row, false);
    });
})();
</script>
@endpush
