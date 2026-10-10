/**
 * SearchableSelect — one dropdown with its search box inside it.
 *
 * Built for the SPAS District picker (1,818 districts): a plain <select> is too
 * long to scroll, and a separate search field beside it read as two controls.
 *
 * The native <select> stays in the page, hidden, and remains the source of
 * truth: its value is what the form submits, and picking an item fires its
 * normal `change` event, so existing listeners keep working. Repopulating the
 * <select> (innerHTML) or toggling `disabled` is picked up automatically.
 *
 *   SearchableSelect(selectEl, { placeholder, searchPlaceholder, pinned: ['Other'] })
 *
 * `pinned` values are always listed first, whatever is typed.
 */
(function () {
    if (window.SearchableSelect) return;

    const MAX_SHOWN = 300;

    function injectStyles() {
        if (document.getElementById('ss-styles')) return;
        const css = `
            .ss-wrap { position: relative; width: 100%; }
            .ss-trigger { width: 100%; display: flex; align-items: center; justify-content: space-between; gap: 8px;
                text-align: left; background: #fff; border: 1px solid #e5e7eb; border-radius: 8px; padding: 8px 12px;
                font-size: 14px; line-height: 1.4; color: #111827; cursor: pointer; min-height: 38px; }
            .ss-trigger:focus { outline: none; border-color: rgb(186,191,12); }
            .ss-trigger.ss-empty .ss-label { color: #9ca3af; }
            .ss-trigger[disabled] { background: #f9fafb; color: #9ca3af; cursor: not-allowed; }
            .ss-label { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
            .ss-caret { flex: none; width: 0; height: 0; border-left: 5px solid transparent; border-right: 5px solid transparent; border-top: 6px solid #6b7280; }
            .ss-panel { position: absolute; left: 0; right: 0; top: calc(100% + 4px); z-index: 30000; background: #fff;
                border: 1px solid #e5e7eb; border-radius: 10px; box-shadow: 0 10px 25px rgba(17,24,39,.15); overflow: hidden; }
            .ss-panel.ss-up { top: auto; bottom: calc(100% + 4px); }
            .ss-search { display: block; width: 100%; box-sizing: border-box; border: 0; border-bottom: 1px solid #f3f4f6;
                padding: 10px 12px; font-size: 14px; outline: none; }
            .ss-list { list-style: none; margin: 0; padding: 4px 0; max-height: 260px; overflow-y: auto; }
            .ss-item { padding: 8px 12px; font-size: 14px; cursor: pointer; color: #111827; }
            .ss-item:hover, .ss-item.ss-active { background: rgba(186,191,12,.12); }
            .ss-item.ss-selected { font-weight: 600; }
            .ss-item.ss-pinned { color: #4b5563; border-bottom: 1px solid #f3f4f6; }
            .ss-note { padding: 8px 12px; font-size: 12px; color: #6b7280; }`;
        const style = document.createElement('style');
        style.id = 'ss-styles';
        style.textContent = css;
        document.head.appendChild(style);
    }

    window.SearchableSelect = function (select, opts = {}) {
        if (!select || select.dataset.ssReady) return select && select._ss;
        select.dataset.ssReady = '1';
        injectStyles();

        const placeholder = opts.placeholder || 'Select…';
        const pinned = (opts.pinned || []).map(v => String(v).toLowerCase());

        const wrap = document.createElement('div');
        wrap.className = 'ss-wrap';
        const trigger = document.createElement('button');
        trigger.type = 'button';
        trigger.className = 'ss-trigger';
        trigger.innerHTML = '<span class="ss-label"></span><span class="ss-caret"></span>';
        const panel = document.createElement('div');
        panel.className = 'ss-panel';
        panel.hidden = true;
        const search = document.createElement('input');
        search.type = 'search';
        search.className = 'ss-search';
        search.placeholder = opts.searchPlaceholder || 'Type to search…';
        search.autocomplete = 'off';
        const list = document.createElement('ul');
        list.className = 'ss-list';
        panel.append(search, list);

        select.parentNode.insertBefore(wrap, select);
        wrap.append(trigger, panel, select);
        select.style.display = 'none';

        let shown = [];
        let active = -1;

        const options = () => Array.from(select.options).filter(o => o.value !== '');

        function syncLabel() {
            const opt = select.selectedOptions[0];
            const has = opt && opt.value !== '';
            trigger.querySelector('.ss-label').textContent = has ? opt.text : (select.options[0]?.text || placeholder);
            trigger.classList.toggle('ss-empty', !has);
            trigger.disabled = select.disabled;
        }

        function render() {
            const q = search.value.trim().toLowerCase();
            const all = options();
            const pins = all.filter(o => pinned.includes(o.value.toLowerCase()));
            const rest = all.filter(o => !pinned.includes(o.value.toLowerCase()) && (!q || o.text.toLowerCase().includes(q)));
            shown = pins.concat(rest.slice(0, MAX_SHOWN));
            active = q && rest.length ? pins.length : -1;

            list.innerHTML = '';
            shown.forEach((o, i) => {
                const li = document.createElement('li');
                li.className = 'ss-item' + (pins.includes(o) ? ' ss-pinned' : '') + (o.value === select.value ? ' ss-selected' : '') + (i === active ? ' ss-active' : '');
                li.textContent = o.text;
                li.addEventListener('mousedown', e => { e.preventDefault(); choose(o.value); });
                list.appendChild(li);
            });

            const note = document.createElement('li');
            note.className = 'ss-note';
            if (!rest.length) note.textContent = q ? 'No match.' + (pins.length ? ' Choose ' + pins[0].text + '.' : '') : 'Nothing to choose.';
            else if (rest.length > MAX_SHOWN) note.textContent = `Showing ${MAX_SHOWN} of ${rest.length} — keep typing to narrow.`;
            else if (q) note.textContent = `${rest.length} match${rest.length === 1 ? '' : 'es'}.`;
            if (note.textContent) list.appendChild(note);
        }

        function highlight() {
            list.querySelectorAll('.ss-item').forEach((li, i) => li.classList.toggle('ss-active', i === active));
            list.querySelectorAll('.ss-item')[active]?.scrollIntoView({ block: 'nearest' });
        }

        function open() {
            if (select.disabled) return;
            panel.hidden = false;
            search.value = '';
            render();
            // Open upwards when there is no room below (e.g. inside a modal's bottom).
            const r = wrap.getBoundingClientRect();
            panel.classList.toggle('ss-up', window.innerHeight - r.bottom < 320 && r.top > 320);
            setTimeout(() => search.focus(), 0);
            list.querySelector('.ss-selected')?.scrollIntoView({ block: 'nearest' });
        }

        function close() { panel.hidden = true; }

        function choose(value) {
            close();
            if (select.value !== value) {
                select.value = value;
                select.dispatchEvent(new Event('change', { bubbles: true }));
            }
            syncLabel();
            trigger.focus();
        }

        trigger.addEventListener('click', () => (panel.hidden ? open() : close()));
        search.addEventListener('input', render);
        search.addEventListener('keydown', e => {
            if (e.key === 'ArrowDown') { e.preventDefault(); active = Math.min(active + 1, shown.length - 1); highlight(); }
            else if (e.key === 'ArrowUp') { e.preventDefault(); active = Math.max(active - 1, 0); highlight(); }
            else if (e.key === 'Enter') { e.preventDefault(); const o = shown[active >= 0 ? active : 0]; if (o) choose(o.value); }
            else if (e.key === 'Escape') { e.preventDefault(); close(); trigger.focus(); }
        });
        document.addEventListener('mousedown', e => { if (!wrap.contains(e.target)) close(); });

        // Follow the hidden select: options replaced, value set in code, disabled toggled.
        new MutationObserver(syncLabel).observe(select, { childList: true, attributes: true, attributeFilter: ['disabled'] });
        select.addEventListener('change', syncLabel);
        // form.reset() changes the value without any event.
        if (select.form) select.form.addEventListener('reset', () => setTimeout(syncLabel, 0));

        syncLabel();
        select._ss = { open, close, refresh: syncLabel };
        return select._ss;
    };
})();
