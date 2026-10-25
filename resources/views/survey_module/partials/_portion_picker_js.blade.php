{{--
    Surveyor + portion picker for the case register (web and mobile).

    Expects #caseProject, #casePortionBox, #caseAllocation, #casePortionNo,
    #casePortionSize, and optionally #caseOfficer / #caseArea to prefill.
    $portions: project id => [{id, name, portions, size, free[]}], from CaseController::portionOptions().
    The portion size shown here is informational; the server copies it from the allocation.
--}}
<script>
    (function () {
        var DATA = @json($portions ?? []);
        var project = document.getElementById('caseProject');
        var box = document.getElementById('casePortionBox');
        var alloc = document.getElementById('caseAllocation');
        var portion = document.getElementById('casePortionNo');
        var size = document.getElementById('casePortionSize');
        if (!project || !box || !alloc || !portion) return;

        var initialAlloc = alloc.getAttribute('data-value') || '';
        var initialPortion = portion.getAttribute('data-value') || '';

        function current() {
            var list = DATA[project.value] || [];
            for (var i = 0; i < list.length; i++) if (String(list[i].id) === String(alloc.value)) return list[i];
            return null;
        }

        function fillPortions(keep) {
            var a = current();
            portion.innerHTML = '';
            var first = document.createElement('option');
            first.value = '';
            first.textContent = a ? (a.free.length ? '— Select portion —' : 'All portions have cases') : '— Select a surveyor first —';
            portion.appendChild(first);
            if (a) {
                a.free.forEach(function (n) {
                    var o = document.createElement('option');
                    o.value = n;
                    o.textContent = 'Portion ' + n + ' of ' + a.portions;
                    if (String(n) === String(keep)) o.selected = true;
                    portion.appendChild(o);
                });
            }
            if (size) size.value = a ? a.size : '';
        }

        function onAlloc(keep) {
            fillPortions(keep);
            var a = current();
            // Prefill, never overwrite what the officer typed.
            var officer = document.getElementById('caseOfficer');
            if (a && officer && !officer.dataset.touched) officer.value = a.name;
            var area = document.getElementById('caseArea');
            if (a && area && !area.value) { area.value = a.size; area.dispatchEvent(new Event('input')); }
        }

        function onProject(keepAlloc, keepPortion) {
            var list = DATA[project.value] || [];
            box.style.display = list.length ? '' : 'none';
            // A flag, not `required`: the web form has no novalidate, and a required
            // control on a hidden step would block the submit. The server enforces it.
            box.dataset.active = list.length ? '1' : '';
            alloc.innerHTML = '<option value="">— Select surveyor —</option>';
            list.forEach(function (a) {
                var o = document.createElement('option');
                o.value = a.id;
                o.textContent = a.name + ' · ' + a.free.length + ' of ' + a.portions + ' portion(s) free';
                if (String(a.id) === String(keepAlloc)) o.selected = true;
                alloc.appendChild(o);
            });
            fillPortions(keepPortion);
        }

        var officer = document.getElementById('caseOfficer');
        if (officer) officer.addEventListener('input', function () { officer.dataset.touched = '1'; });

        project.addEventListener('change', function () { onProject('', ''); });
        alloc.addEventListener('change', function () { onAlloc(''); });

        onProject(initialAlloc, initialPortion);
    })();
</script>
