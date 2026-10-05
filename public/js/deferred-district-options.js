(function () {
    'use strict';

    // Keep one catalogue on the page; expand only the form being used.
    window.populateDistrictOptions = function (root) {
        if (!root) return;
        const selects = root.matches?.('select[data-deferred-district]')
            ? [root] : Array.from(root.querySelectorAll('select[data-deferred-district]'));
        selects.forEach(function (select) {
            const selected = Array.from(select.selectedOptions).map(option => option.value.toLowerCase());
            const existing = new Set(Array.from(select.options).map(option => option.value.toLowerCase()));
            const fragment = document.createDocumentFragment();
            (window.ossDistrictNames || []).forEach(function (name) {
                const key = String(name).toLowerCase();
                if (existing.has(key) || key === 'other' || key === 'others') return;
                existing.add(key);
                const option = new Option(name, name, false, selected.includes(key));
                fragment.appendChild(option);
            });
            const other = Array.from(select.options).find(option => /^(other|others)$/i.test(option.value));
            select.insertBefore(fragment, other || null);
            select.removeAttribute('data-deferred-district');
        });
    };

    ['focusin', 'pointerdown'].forEach(function (event) {
        document.addEventListener(event, function (e) {
            if (e.target.matches?.('select[data-deferred-district]')) {
                window.populateDistrictOptions(e.target);
            }
        }, true);
    });
})();
