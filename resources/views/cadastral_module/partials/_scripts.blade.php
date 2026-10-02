{{--
    Cadastral Module scripts.

    Two small behaviours only: the live duplicate check on the reception form,
    and add-a-row on the coordinate and pillar grids. Everything else is a plain
    form post -- the module deliberately has no SPA layer.
--}}
<script>
(function () {
    'use strict';

    /* ---- Live duplicate / correspondence check on a file number field ---- */
    document.querySelectorAll('[data-fileno-check]').forEach(function (input) {
        var target = document.querySelector(input.getAttribute('data-fileno-check'));
        var url    = input.getAttribute('data-lookup-url');
        var timer  = null;

        if (!target || !url) return;

        function render(data) {
            if (!data || !data.ok) { target.innerHTML = ''; return; }

            var bits = [];

            if (data.format && data.format.class) {
                bits.push('<span class="status-badge ' + (data.format.class === 'conversion' ? 'review' : 'active') +
                          '"><span class="dot"></span>' + data.format.class + '</span>');
            }
            if (data.format && data.format.land_use) {
                bits.push('<span style="color:var(--gray-600)">' + data.format.land_use + '</span>');
            }
            if (data.indexed) {
                bits.push('<span style="color:var(--gray-600)">Indexed: ' +
                          (data.indexed.file_title || '(untitled)') + '</span>');
            }
            if (data.shelf_location) {
                bits.push('<span style="color:var(--gray-600)">Shelf ' + data.shelf_location + '</span>');
            }
            if (data.correspondence && data.correspondence.exists) {
                bits.push('<span class="status-badge completed"><span class="dot"></span>Correspondence file exists</span>');
            }

            var warn = '';
            if (data.duplicates && data.duplicates.length) {
                warn += data.duplicates.length + ' entry(ies) in the duplicate register. ';
            }
            if (data.doubles && data.doubles.length) {
                warn += data.doubles.length + ' other file(s) on the same plot number.';
            }
            if (warn) {
                bits.push('<span class="status-badge rejected"><span class="dot"></span>' + warn + '</span>');
            }

            target.innerHTML = '<div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:6px;font-size:12px;">' +
                               bits.join('') + '</div>';
        }

        input.addEventListener('input', function () {
            clearTimeout(timer);
            var value = input.value.trim();

            if (value.length < 4) { target.innerHTML = ''; return; }

            // Debounced: the lookup touches file_indexings (170k rows) and there
            // is no reason to hit it on every keystroke.
            timer = setTimeout(function () {
                fetch(url + '?file_number=' + encodeURIComponent(value), {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                })
                    .then(function (r) { return r.ok ? r.json() : null; })
                    .then(render)
                    .catch(function () { target.innerHTML = ''; });
            }, 400);
        });
    });

    /* ---- Add a row to a coordinate / pillar grid ---- */
    document.querySelectorAll('[data-add-row]').forEach(function (button) {
        button.addEventListener('click', function () {
            var body = document.querySelector(button.getAttribute('data-add-row'));
            if (!body) return;

            var template = body.querySelector('tr[data-row-template]');
            if (!template) return;

            var clone = template.cloneNode(true);
            clone.removeAttribute('data-row-template');
            clone.style.display = '';

            // Renumber so the posted array stays contiguous; PHP does not care,
            // but a sparse array reads badly in the request log.
            var index = body.querySelectorAll('tr:not([data-row-template])').length;

            clone.querySelectorAll('input, select').forEach(function (field) {
                if (field.name) field.name = field.name.replace(/\[\d*\]/, '[' + index + ']');
                if (field.tagName === 'SELECT') field.selectedIndex = 0; else field.value = '';
            });

            body.appendChild(clone);
        });
    });

    /* ---- Remove a row ---- */
    document.addEventListener('click', function (e) {
        var button = e.target.closest('[data-remove-row]');
        if (!button) return;

        var row = button.closest('tr');
        if (row && !row.hasAttribute('data-row-template')) row.remove();
    });
})();
</script>
