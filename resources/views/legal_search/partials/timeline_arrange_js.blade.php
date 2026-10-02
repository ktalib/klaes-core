{{--
  Drag-and-drop timeline arrangement — SHARED.

  Included by:
    - system-admin/phs/edit_request_preview   (PHS "Correct Search Result")
    - legal_search_online/correct             (Online LS "Preview Report")

  Same storage as the on-premise Legal Search screen: POST to
  /legal_search/save-arrangement, which writes legal_search_timeline_arrangements
  keyed on prop_id. buildPrintReport() reads that table, so a saved order changes
  the printed report for everyone.

  Which is exactly why this asks first. On these two screens the person dragging
  is correcting someone else's search result, and the two intents are genuinely
  different:

    PERMANENT  written to the database. Every future search of this property —
               and the report the requester receives — uses this order.
    TEMPORARY  held in sessionStorage for this browser only. Nothing is written,
               nobody else sees it, and it is gone when the tab closes. For
               trying an order out before committing to it.

  Defaulting either way would be wrong: silently persisting makes a casual drag
  rewrite a shared record, and silently discarding loses deliberate work.

  Host page must provide:
    window.LS_ARRANGE = { propId, rowsSelector, containerId }
--}}
<script>
(function () {
    const cfg = window.LS_ARRANGE || {};
    const propId = String(cfg.propId || '').trim();
    const container = document.getElementById(cfg.containerId);
    const btn = document.getElementById('ls-arrange-btn');
    const statusEl = document.getElementById('ls-arrange-status');

    if (!container || !btn) return;

    const ROW_SELECTOR = cfg.rowsSelector || '[data-pc-row]';
    const STORAGE_KEY = 'ls-arrange-temp:' + propId;

    let active = false;
    let dragged = null;

    const setStatus = (text, tone) => {
        if (!statusEl) return;
        statusEl.textContent = text || '';
        statusEl.className = 'text-xs ' + (
            tone === 'error' ? 'text-rose-600' : tone === 'ok' ? 'text-emerald-600' : 'text-slate-400'
        );
    };

    const rows = () => Array.from(container.querySelectorAll(ROW_SELECTOR));

    /** The order as it currently stands on screen. */
    const currentOrder = () => rows()
        .map((row, index) => ({
            table: row.dataset.sourceTable,
            id: parseInt(row.dataset.recordId, 10),
            order: index + 1,
        }))
        .filter(i => i.table && Number.isInteger(i.id) && i.id > 0);

    // ---------------------------------------------------------------- persist

    const savePermanent = async (items) => {
        const res = await fetch(@json(route('legalsearch.saveArrangement')), {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
            },
            body: JSON.stringify({ prop_id: propId, items }),
        });
        const body = await res.json().catch(() => ({}));
        if (!res.ok || body.success === false) {
            throw new Error(body.message || ('Save failed (' + res.status + ')'));
        }
        // A permanent order supersedes any temporary one for this property,
        // otherwise the stale local copy would keep overriding it on reload.
        try { sessionStorage.removeItem(STORAGE_KEY); } catch (e) { /* private mode */ }
    };

    const saveTemporary = (items) => {
        try {
            sessionStorage.setItem(STORAGE_KEY, JSON.stringify(items));
        } catch (e) {
            // Private browsing or a full quota: say so rather than implying it stuck.
            throw new Error('This browser would not store the temporary order.');
        }
    };

    /** Re-apply a temporary order held from earlier in this session. */
    const restoreTemporary = () => {
        let stored = null;
        try { stored = sessionStorage.getItem(STORAGE_KEY); } catch (e) { return; }
        if (!stored) return;

        let items;
        try { items = JSON.parse(stored); } catch (e) { return; }
        if (!Array.isArray(items) || !items.length) return;

        const byKey = new Map(rows().map(r => [r.dataset.sourceTable + ':' + r.dataset.recordId, r]));
        items
            .slice()
            .sort((a, b) => a.order - b.order)
            .forEach(item => {
                const row = byKey.get(item.table + ':' + item.id);
                if (row) container.appendChild(row);
            });

        setStatus('Showing a temporary order held in this browser. Not saved.', 'ok');
    };

    // ------------------------------------------------------------ the prompt

    const askAndSave = async (items) => {
        if (!items.length) return;

        if (typeof window.Swal === 'undefined') {
            // No dialog library: never guess. Persisting without asking is the
            // one outcome that cannot be undone from here.
            const permanent = window.confirm(
                'Save this arrangement PERMANENTLY?\n\n'
                + 'OK    - save to the database. Everyone sees this order, including the report.\n'
                + 'Cancel - keep it for this browser only (temporary).'
            );
            if (permanent) { await savePermanent(items); setStatus('Saved permanently.', 'ok'); }
            else { saveTemporary(items); setStatus('Kept temporarily in this browser.', 'ok'); }
            return;
        }

        const result = await window.Swal.fire({
            title: 'Save this arrangement?',
            html:
                '<div style="text-align:left;font-size:13px;line-height:1.55;">'
                + '<p style="margin:0 0 8px;"><strong>Permanently</strong> &mdash; written to the database. '
                + 'Every future search of this property uses this order, and so does the report the '
                + 'requester receives.</p>'
                + '<p style="margin:0;"><strong>Temporarily</strong> &mdash; this browser only. Nothing is '
                + 'saved, nobody else sees it, and it is gone when you close the tab.</p>'
                + '</div>',
            icon: 'question',
            showDenyButton: true,
            showCancelButton: true,
            confirmButtonText: 'Save permanently',
            denyButtonText: 'Keep temporarily',
            cancelButtonText: 'Undo',
            confirmButtonColor: '#059669',
            denyButtonColor: '#475569',
            allowOutsideClick: false,
        });

        if (result.isConfirmed) {
            try {
                setStatus('Saving...');
                await savePermanent(items);
                setStatus('Saved permanently — this order now applies to the report.', 'ok');
            } catch (err) {
                setStatus(err.message || 'Could not save the arrangement.', 'error');
            }
            return;
        }

        if (result.isDenied) {
            try {
                saveTemporary(items);
                setStatus('Kept temporarily in this browser. Nothing was saved.', 'ok');
            } catch (err) {
                setStatus(err.message, 'error');
            }
            return;
        }

        // Cancelled: put the rows back the way they were.
        window.location.reload();
    };

    // ---------------------------------------------------------- drag and drop

    const onDragStart = function (e) {
        dragged = this;
        this.classList.add('opacity-50');
        e.dataTransfer.effectAllowed = 'move';
        // Firefox will not start a drag without data set.
        try { e.dataTransfer.setData('text/plain', this.dataset.recordId || ''); } catch (err) {}
    };

    const onDragOver = function (e) {
        e.preventDefault();
        e.dataTransfer.dropEffect = 'move';
    };

    const onDrop = async function (e) {
        e.preventDefault();
        if (!dragged || dragged === this) return;

        const list = rows();
        const from = list.indexOf(dragged);
        const to = list.indexOf(this);
        if (from === -1 || to === -1) return;

        if (from < to) {
            this.parentNode.insertBefore(dragged, this.nextSibling);
        } else {
            this.parentNode.insertBefore(dragged, this);
        }

        await askAndSave(currentOrder());
    };

    const onDragEnd = function () {
        this.classList.remove('opacity-50');
        dragged = null;
    };

    const setDraggable = (on) => {
        rows().forEach(row => {
            row.draggable = on;
            row.classList.toggle('cursor-move', on);
            if (on) {
                row.addEventListener('dragstart', onDragStart);
                row.addEventListener('dragover', onDragOver);
                row.addEventListener('drop', onDrop);
                row.addEventListener('dragend', onDragEnd);
            } else {
                row.removeEventListener('dragstart', onDragStart);
                row.removeEventListener('dragover', onDragOver);
                row.removeEventListener('drop', onDrop);
                row.removeEventListener('dragend', onDragEnd);
            }
        });
    };

    const toggle = (on) => {
        active = on;
        setDraggable(on);
        container.classList.toggle('ring-2', on);
        container.classList.toggle('ring-purple-300', on);

        if (on) {
            btn.classList.add('bg-purple-600', 'text-white', 'border-purple-600');
            btn.classList.remove('border-slate-200', 'text-slate-700');
            btn.textContent = 'Done arranging';
            setStatus('Drag a record to reorder it. You will be asked whether to save.');
        } else {
            btn.classList.remove('bg-purple-600', 'text-white', 'border-purple-600');
            btn.classList.add('border-slate-200', 'text-slate-700');
            btn.textContent = 'Arrange';
            setStatus('');
        }
    };

    if (!propId) {
        btn.disabled = true;
        btn.title = 'This file has no property id, so an order cannot be saved against it.';
        btn.classList.add('opacity-40', 'cursor-not-allowed');
        return;
    }

    btn.addEventListener('click', () => toggle(!active));
    restoreTemporary();
})();
</script>
