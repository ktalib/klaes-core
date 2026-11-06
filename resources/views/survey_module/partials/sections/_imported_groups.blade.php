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
    @endphp
    <tr class="imp-group" data-tp-group="{{ $tpKey }}" aria-expanded="false" tabindex="0">
        <td class="nowrap"><i class="fas fa-chevron-right" data-chevron></i> {{ $groupNo }}</td>
        <td class="wide strong">{{ $names->implode(' · ') ?: '—' }}</td>
        <td class="nowrap">{!! $tpNo !== '' ? '<span class="tp">' . e($tpNo) . '</span>' : 'No TP No.' !!}</td>
        <td></td>
        <td class="nowrap">{{ number_format($lands->count()) }} {{ $lands->count() === 1 ? 'land' : 'lands' }}</td>
        <td></td>
        <td></td><td></td><td></td><td></td><td></td><td></td>
        <td class="num">{{ $lands->whereNotNull('area_ha')->isEmpty() ? '—' : number_format((float) $lands->sum('area_ha'), 4) }}</td>
        <td></td><td></td><td></td><td></td><td></td>
        <td class="ctr">{{ number_format($lands->sum('beneficiaries_count')) }}</td>
        <td></td>
        <td></td>
        <td></td>
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
        <td class="wide strong">Computation Sheet · {{ $g->sheet_name }}</td>
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
    function toggle(row, keep) {
        var on = row.getAttribute('aria-expanded') !== 'true';
        row.setAttribute('aria-expanded', on ? 'true' : 'false');
        if (row.dataset.tpGroup) {
            Array.prototype.forEach.call(body.querySelectorAll('tr[data-tp-of]'), function (r) {
                if (r.dataset.tpOf === row.dataset.tpGroup) r.hidden = !on;
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
    });
    // A search opens what it found; otherwise the groups left open stay open.
    var open = openList();
    Array.prototype.forEach.call(body.querySelectorAll('tr.imp-group'), function (row) {
        if (q !== '' || open.indexOf(keyOf(row)) !== -1) toggle(row, false);
    });
})();
</script>
@endpush
