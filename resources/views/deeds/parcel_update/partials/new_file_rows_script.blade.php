{{-- The arrow in front of an SPU / APU row whose New FileNo(s) cell holds more than one
     file (partials/new_file_nos_cell). Opening it inserts one row per further file number
     directly under the row, New FileNo in its own column and the File Title beside it;
     closing removes them. Shared by every register that uses the cell. --}}
@once
@push('scripts')
<style>
    .pu-newfiles-toggle { display:inline-flex; align-items:center; justify-content:center; width:20px; height:20px; margin-right:4px; vertical-align:middle; border-radius:4px; color:#475569; }
    .pu-newfiles-toggle:hover { background:#e2e8f0; }
    .pu-newfiles-toggle svg { transition: transform .15s ease; }
    .pu-newfiles-toggle[aria-expanded="true"] svg { transform: rotate(90deg); }
    tr.pu-newfiles-child > td { background:#f8fafc; }
</style>
<script>
(function () {
    var CHEVRON = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="9 18 15 12 9 6"></polyline></svg>';
    var seq = 0;

    function esc(v) {
        return String(v == null ? '' : v).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }

    function addArrows(root) {
        (root || document).querySelectorAll('td.pu-newfiles-cell[data-more-files]').forEach(function (cell) {
            var row = cell.parentElement;
            if (!row || row.dataset.puNewfilesId) return;
            var first = row.cells[0];
            if (!first) return;
            row.dataset.puNewfilesId = 'pu-nf-' + (++seq);
            var more = Number(cell.dataset.total || 0) - 1;
            var btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'pu-newfiles-toggle';
            btn.setAttribute('aria-expanded', 'false');
            btn.setAttribute('aria-label', 'Show ' + more + ' more new file number' + (more === 1 ? '' : 's'));
            btn.title = more + ' more new file number' + (more === 1 ? '' : 's');
            btn.innerHTML = CHEVRON;
            first.insertBefore(btn, first.firstChild);
        });
    }

    // The column heading each body cell sits under, lower-cased ("new fileno(s)", "plots").
    function headings(table) {
        var head = table && table.tHead && table.tHead.rows[table.tHead.rows.length - 1];
        var out = [];
        if (!head) return out;
        Array.prototype.forEach.call(head.cells, function (th) {
            var label = (th.textContent || '').replace(/\s+/g, ' ').trim().toLowerCase();
            for (var i = 0; i < (th.colSpan || 1); i++) out.push(label);
        });
        return out;
    }

    var DASH = '<span class="text-slate-300">&mdash;</span>';
    function val(v) { return v ? esc(v) : DASH; }

    // One cell of an opened row: the new file's own value where the column is about the
    // file, the application's (the row above) where it is about the application.
    function cellFor(label, parentCell, f, n, parentSn) {
        if (label === 's/n' || label === '#') return '<span class="font-mono text-xs text-slate-400 pl-7">' + esc(parentSn ? parentSn + '.' + n : n) + '</span>';
        if (label.indexOf('new fileno') === 0 || label.indexOf('new file no') === 0) return '<span class="font-mono font-bold text-emerald-700 whitespace-nowrap">' + esc(f.f) + '</span>';
        if (label.indexOf('title') !== -1 || label.indexOf('applicant') === 0) return val(f.t);
        if (label === 'plots' || label.indexOf('plot no') === 0) return val(f.p);
        if (label === 'location') return val(f.l);
        if (label === 'date' || label === 'date created') return '<span class="whitespace-nowrap">' + val(f.d) + '</span>';
        if (label === 'created by') return val(f.c);
        if (label === 'status') return '<span class="inline-flex rounded-md bg-emerald-50 px-1.5 py-0.5 text-[10px] font-bold text-emerald-700">Commissioned</span>';
        if (label === 'actions') return '';
        // Application-level (File No, Source, Land Use, Occupation …): the parent's value.
        var text = parentCell ? (parentCell.textContent || '').replace(/\s+/g, ' ').trim() : '';
        return text ? '<span class="text-slate-500">' + esc(text) + '</span>' : DASH;
    }

    function open(row, cell) {
        var files;
        try { files = JSON.parse(cell.dataset.moreFiles || '[]'); } catch (e) { files = []; }
        var labels = headings(row.closest('table'));
        var cells = Array.prototype.slice.call(row.cells);
        var snCell = cells[0];
        var parentSn = snCell ? ((snCell.textContent || '').match(/\d+/) || [''])[0] : '';
        var pos = 0;
        var columns = cells.map(function (c) { var at = pos; pos += (c.colSpan || 1); return { cell: c, label: labels[at] || '' }; });
        var html = files.map(function (f, i) {
            return '<tr class="pu-newfiles-child" data-pu-newfiles-of="' + row.dataset.puNewfilesId + '">'
                + columns.map(function (col) {
                    // Same classes as the cell above, so padding, alignment and any column
                    // hidden through Customize columns carry over.
                    return '<td class="' + esc(col.cell.className) + '"' + (col.cell.colSpan > 1 ? ' colspan="' + col.cell.colSpan + '"' : '') + '>'
                        + cellFor(col.label, col.cell, f, i + 2, parentSn) + '</td>';
                }).join('')
                + '</tr>';
        }).join('');
        row.insertAdjacentHTML('afterend', html);
    }
    function close(row) {
        document.querySelectorAll('tr[data-pu-newfiles-of="' + row.dataset.puNewfilesId + '"]').forEach(function (tr) { tr.remove(); });
    }

    document.addEventListener('click', function (e) {
        var btn = e.target.closest('.pu-newfiles-toggle');
        if (!btn) return;
        e.preventDefault();
        e.stopPropagation();
        var row = btn.closest('tr');
        var cell = row && row.querySelector('td.pu-newfiles-cell[data-more-files]');
        if (!cell) return;
        var opening = btn.getAttribute('aria-expanded') !== 'true';
        btn.setAttribute('aria-expanded', opening ? 'true' : 'false');
        if (opening) open(row, cell); else close(row);
    });

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', function () { addArrows(); });
    else addArrows();
    // Listings that refresh their table in place call this again.
    window.puNewFilesArrows = addArrows;
})();
</script>
@endpush
@endonce
