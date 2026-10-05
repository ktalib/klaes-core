{{--
    Land use follows the picked file, on the Area & Pillars wizard and the
    register form.

    The shared picker fills and locks the address fields itself; land use is
    this unit's own select, so it is handled here. The file's land-use label
    (the index's land-use type, else the file number's code) is mapped onto
    CadastralPlanDescription::LAND_USES exactly as
    PlanDescriptionController::landUseFromLabel maps it on save: a match locks
    the select (disabled, greyed, "from file"), no match leaves it open.

    The select opts in with data-land-use-lock. The server-rendered state (a
    re-display, an edit form) is the controller's; only a fresh pick changes it.
--}}
@once
<script>
(function () {
    'use strict';

    var USES  = @json(\App\Models\Cadastral\CadastralPlanDescription::LAND_USES);
    var CODES = @json(\App\Http\Controllers\Cadastral\PlanDescriptionController::LAND_USE_CODES);

    function fromLabel(label) {
        label = String(label == null ? '' : label).trim();
        if (!label) return null;
        var plain = label.replace(/\s+use$/i, '');
        for (var i = 0; i < USES.length; i++) {
            var u = USES[i].toLowerCase();
            if (u === label.toLowerCase() || u === plain.toLowerCase()) return USES[i];
        }
        return CODES[label.toUpperCase()] || null;
    }

    document.addEventListener('cadastral:file-picked', function (e) {
        var p = e.detail;
        if (p && p.initial) return;

        var select = e.target.querySelector ? e.target.querySelector('select[data-land-use-lock]') : null;
        if (!select) return;

        var use   = p && p.status === 'ok' && p.file ? fromLabel(p.file.land_use) : null;
        var group = select.closest('.form-group');

        if (use) {
            select.value = use;
            select.dataset.fromFile = '1';
        } else if (select.dataset.fromFile === '1') {
            // The last file's value goes with it.
            select.value = '';
            delete select.dataset.fromFile;
        }
        select.disabled = !!use;
        select.classList.toggle('cad-locked', !!use);
        if (group) group.classList.toggle('cad-fp-filled', !!use);
    });
})();
</script>
@endonce
