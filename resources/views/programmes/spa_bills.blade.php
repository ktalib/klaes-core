@extends('layouts.app')
@section('page-title'){{ $PageTitle ?? 'Special Assignment – Bills' }}@endsection

@section('content')
<div class="flex-1 overflow-auto">
    @include('admin.header')
    <div class="p-6">
        <div class="flex items-center justify-between mb-6">
            <div>
                <h1 class="text-2xl font-bold text-gray-800">{{ $PageTitle }}</h1>
                <p class="text-sm text-gray-500 mt-1">{{ $PageDescription }}</p>
            </div>
            {{-- Bills are no longer typed by hand. A contravention raises its
                 own bill from the tariff, which is set on System Admin ->
                 Configurable Entries -> SPAS Bill Items. --}}
        </div>

        {{-- Where this page sits in the SPAS workflow. --}}
        @include('special_assignment.partials.workflow', ['current' => ['payment']])

        {{-- Stats --}}
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-6">
            <div class="bg-white rounded-xl border border-gray-200 p-4 flex items-center gap-3">
                <div class="p-2 rounded-full" style="background:rgba(186,191,12,.12)"><i data-lucide="receipt" class="h-5 w-5 text-[rgb(186,191,12)]"></i></div>
                <div><p class="text-xs text-gray-500">Total Bills</p><p class="text-xl font-bold text-gray-800">{{ $stats['total'] }}</p></div>
            </div>
            <div class="bg-white rounded-xl border border-gray-200 p-4 flex items-center gap-3">
                <div class="p-2 rounded-full bg-red-50"><i data-lucide="alert-circle" class="h-5 w-5 text-red-500"></i></div>
                <div><p class="text-xs text-gray-500">Unpaid</p><p class="text-xl font-bold text-gray-800">{{ $stats['unpaid'] }}</p></div>
            </div>
            <div class="bg-white rounded-xl border border-gray-200 p-4 flex items-center gap-3">
                <div class="p-2 rounded-full bg-amber-50"><i data-lucide="clock" class="h-5 w-5 text-amber-500"></i></div>
                <div><p class="text-xs text-gray-500">Partial</p><p class="text-xl font-bold text-gray-800">{{ $stats['partial'] }}</p></div>
            </div>
            <div class="bg-white rounded-xl border border-gray-200 p-4 flex items-center gap-3">
                <div class="p-2 rounded-full bg-green-50"><i data-lucide="check-circle" class="h-5 w-5 text-green-600"></i></div>
                <div><p class="text-xs text-gray-500">Paid</p><p class="text-xl font-bold text-gray-800">{{ $stats['paid'] }}</p></div>
            </div>
        </div>

        {{-- DataTable --}}
        <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-100"><h2 class="text-sm font-semibold text-gray-700">All SPAS Bills</h2></div>
            {{-- Every cell is kept on one line (see the CSS below), so the table
                 scrolls sideways rather than growing rows three lines tall. --}}
            <div class="p-4 overflow-x-auto">
                @include('special_assignment.partials.table_case')
                <table id="spa-bills-table" class="spas-table w-full text-sm" style="width:100%">
                    <thead>
                        <tr class="text-left text-xs text-gray-500 uppercase">
                            <th class="px-3 py-2">#</th>
                            <th class="px-3 py-2">Ref ID</th>
                            <th class="px-3 py-2">SPAS ID</th><th class="px-3 py-2">File No</th>
                            <th class="px-3 py-2">Owner</th>
                            <th class="px-3 py-2">Bill Type</th>
                            <th class="px-3 py-2">Description</th>
                            {{-- One column per bill item: what the bill is made of,
                                 which used to be readable only inside the
                                 description sentence. --}}
                            @foreach ($itemColumns as $name)
                                <th class="px-3 py-2 text-right">{{ $name }} (₦)</th>
                            @endforeach
                            <th class="px-3 py-2 text-right">Amount (₦)</th>
                            <th class="px-3 py-2 text-right">Paid (₦)</th>
                            <th class="px-3 py-2 text-right">Balance (₦)</th>
                            <th class="px-3 py-2">Due Date</th>
                            <th class="px-3 py-2">Status</th>
                            <th class="px-3 py-2">Action</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>
</div>

{{-- Record Payment --}}
{{--
    Payment is collected item by item. A bill is made of several fees, and a
    lump figure against the whole bill left nobody able to say WHICH parts had
    been settled — so each item gets its own field, and the total is the sum of
    them (computed again on the server, which is the figure that is stored).
--}}
{{-- Send to Change of Purpose: the officer confirms the record and picks the old file number. --}}
<div id="modal-send-cop" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/40">
    <div class="bg-white rounded-2xl shadow-xl w-full max-w-xl mx-4 max-h-[92vh] overflow-y-auto">
        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
            <div>
                <h3 class="text-base font-semibold text-gray-800">Send to Change of Purpose</h3>
                <p id="sc-meta" class="text-xs text-gray-500 mt-0.5">—</p>
            </div>
            <button type="button" class="sc-close text-gray-400 hover:text-gray-600"><i data-lucide="x" class="h-5 w-5"></i></button>
        </div>
        <form id="form-send-cop" class="p-6 space-y-4">
            <div class="rounded-xl border border-gray-200 bg-gray-50/60 p-3 grid grid-cols-2 gap-3 text-sm">
                <div><p class="text-xs text-gray-500">Owner</p><p id="sc-owner" class="font-medium text-gray-800">—</p></div>
                <div><p class="text-xs text-gray-500">Location</p><p id="sc-location" class="font-medium text-gray-800">—</p></div>
                <div><p class="text-xs text-gray-500">Approved land use</p><p id="sc-approved" class="font-medium text-gray-800">—</p></div>
                <div>
                    <p class="text-xs text-gray-500">New purpose (prevailing land use)</p>
                    <p id="sc-prevailing" class="font-semibold" style="color:#0d9488">—</p>
                </div>
            </div>

            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Type <span class="text-red-500">*</span></label>
                <div class="grid grid-cols-2 gap-2">
                    <label class="flex items-start gap-2 rounded-lg border border-gray-200 p-2.5 cursor-pointer">
                        <input type="radio" name="type" value="change_of_purpose" class="mt-0.5">
                        <span class="text-sm font-semibold text-gray-800">Change of Purpose</span>
                    </label>
                    <label class="flex items-start gap-2 rounded-lg border border-gray-200 p-2.5 cursor-pointer">
                        <input type="radio" name="type" value="conversion" class="mt-0.5">
                        <span class="text-sm font-semibold text-gray-800">Conversion</span>
                    </label>
                </div>
            </div>

            {{-- Hidden for a Conversion: it takes no old file number. --}}
            <div id="sc-old-block">
                <label class="block text-xs font-medium text-gray-600 mb-1">Old File Number <span id="sc-old-req" class="text-red-500">*</span></label>
                <div class="flex gap-2">
                    <input type="text" name="old_file_no" id="sc-old" readonly placeholder="Select the old file number"
                        class="flex-1 border border-gray-200 rounded-lg px-3 py-2 text-sm bg-gray-50 font-mono">
                    <button type="button" id="sc-pick" class="px-3 py-2 rounded-lg text-sm font-semibold text-white" style="background:rgb(186,191,12)">Select</button>
                    <button type="button" id="sc-clear-old" class="px-3 py-2 rounded-lg text-sm text-gray-600 border border-gray-200">Clear</button>
                </div>
                <p id="sc-old-hint" class="text-[11px] text-gray-400 mt-1"></p>
            </div>

            <p class="text-xs text-gray-500">It goes into the Change of Purpose register already <b>approved</b>, source <b>SPAS</b>, ready to commission the new file number.</p>

            <div class="flex justify-end gap-2 pt-2">
                <button type="button" class="sc-close px-4 py-2 rounded-lg border border-gray-200 text-sm text-gray-600">Cancel</button>
                <button type="submit" id="sc-submit" class="px-4 py-2 rounded-lg text-sm font-semibold text-white" style="background:#0d9488">Send to Change of Purpose</button>
            </div>
        </form>
    </div>
</div>

<div id="modal-pay" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/40">
    <div class="bg-white rounded-2xl shadow-xl w-full max-w-2xl mx-4 max-h-[92vh] overflow-y-auto">
        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
            <div>
                <h3 class="text-base font-semibold text-gray-800">GENERATE BILL</h3>
                <p id="pay-bill-meta" class="text-xs text-gray-500 mt-0.5">—</p>
            </div>
            <button class="pay-close text-gray-400 hover:text-gray-600"><i data-lucide="x" class="h-5 w-5"></i></button>
        </div>
        <form id="form-pay" class="p-6 space-y-4">
            @csrf
            <div id="pay-pipeline" class="hidden rounded-xl border border-gray-200 bg-gray-50/60 px-4 py-3"></div>
            <input type="hidden" name="spa_bill_id" id="pay-bill-id">
            {{-- The BILL: what is owed. Payments are recorded on Special Assignment – Payments. --}}

            {{-- Bill items --}}
            <div class="border border-gray-200 rounded-xl overflow-hidden">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 text-xs text-gray-500 uppercase">
                        <tr>
                            <th class="px-3 py-2 text-left">Bill Item</th>
                            <th class="px-3 py-2 text-right w-28">Billed (₦)</th>
                            <th class="px-3 py-2 text-right w-28">Paid (₦)</th>
                            <th class="px-3 py-2 text-right w-28">Balance (₦)</th>
                        </tr>
                    </thead>
                    <tbody id="pay-lines">
                        <tr><td colspan="4" class="px-3 py-4 text-center text-xs text-gray-400">Loading…</td></tr>
                    </tbody>
                    <tfoot class="bg-gray-50">
                        <tr>
                            <td colspan="3" class="px-3 py-2 text-right text-xs font-semibold text-gray-600 uppercase">Bill Total</td>
                            <td class="px-3 py-2 text-right font-bold text-gray-800" id="pay-total">₦0.00</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
            {{-- "Add when needed" tariff items (a Penalty Fee): not on the bill until added. --}}
            <div id="pay-addable" class="hidden flex flex-wrap items-center gap-2"></div>
            <div class="flex items-center justify-between">
                <p id="pay-balance-note" class="text-xs text-gray-500"></p>
            </div>

            <div class="flex justify-end gap-3 pt-2">
                <button type="button" class="pay-close px-4 py-2 text-sm text-gray-600 border border-gray-200 rounded-lg hover:bg-gray-50">Cancel</button>
                <button type="submit" class="px-5 py-2 text-sm text-white bg-[rgb(186,191,12)] rounded-lg hover:opacity-90">GENERATE BILL</button>
            </div>
        </form>
    </div>
</div>

<style>
    table.dataTable thead th { background:#f9fafb; font-weight:600; }

    /* Values read straight across: no cell wraps onto a second line. The
       description is the one long field, and it is clipped to a fixed width
       with the full text one click away. */
    #spa-bills-table th,
    #spa-bills-table td { white-space: nowrap; }

    #spa-bills-table td.desc-cell { max-width: 320px; }
    #spa-bills-table td.desc-cell .desc-short {
        display: inline-block;
        max-width: 300px;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
        vertical-align: bottom;
        cursor: pointer;
    }
    /* Expanded: the row is allowed to grow, but only the row the officer asked
       to see. */
    #spa-bills-table td.desc-cell.is-open { white-space: normal; }
    #spa-bills-table td.desc-cell.is-open .desc-short {
        max-width: none;
        overflow: visible;
        white-space: normal;
    }
    .dataTables_wrapper .dataTables_filter input { border:1px solid #e5e7eb; border-radius:.5rem; padding:.35rem .75rem; font-size:.85rem; }
    .dataTables_wrapper .dataTables_length select { border:1px solid #e5e7eb; border-radius:.5rem; padding:.25rem .5rem; }
</style>

@include('components.global-fileno-modal')
<script src="{{ asset('js/global-fileno-modal.js') }}?v={{ @filemtime(public_path('js/global-fileno-modal.js')) }}"></script>
<script src="{{ asset('js/spas-pipeline.js') }}?v={{ @filemtime(public_path('js/spas-pipeline.js')) }}"></script>
<script>
// ── Send to Change of Purpose ──────────────────────────────────────────────
$(function () {
    const modal = document.getElementById('modal-send-cop');
    const form  = document.getElementById('form-send-cop');
    const old   = document.getElementById('sc-old');
    let rec = null;
    const show = (on) => { modal.classList.toggle('hidden', !on); modal.classList.toggle('flex', on); };

    // Old file no.: required for a Change of Purpose of a record that has one;
    // optional for a Conversion and for a record captured without a file number.
    function needsOld() {
        const type = form.querySelector('input[name=type]:checked')?.value;
        return type === 'change_of_purpose' && !!rec.file_number;
    }
    const isConversion = () => form.querySelector('input[name=type]:checked')?.value === 'conversion';
    function refreshOld() {
        const need = needsOld();
        document.getElementById('sc-old-block').classList.toggle('hidden', isConversion());
        document.getElementById('sc-old-req').classList.toggle('hidden', !need);
        document.getElementById('sc-old-hint').textContent = !rec.file_number
            ? 'Recorded without a file number — the new purpose is its prevailing land use.'
            : 'The file whose purpose changes.';
    }

    $(document).on('click', '.btn-send-cop', function () {
        rec = JSON.parse(this.dataset.send || '{}');
        document.getElementById('sc-meta').textContent = [rec.spa_id, rec.bill ? 'Bill ' + rec.bill : null].filter(Boolean).join(' · ');
        document.getElementById('sc-owner').textContent      = rec.owner || '—';
        document.getElementById('sc-location').textContent   = rec.location || '—';
        document.getElementById('sc-approved').textContent   = rec.approved_use || '—';
        document.getElementById('sc-prevailing').textContent = rec.prevailing || '—';
        old.value = rec.file_number || '';
        form.querySelectorAll('input[name=type]').forEach((r) => { r.checked = r.value === rec.type; });
        refreshOld();
        show(true);
    });
    form.addEventListener('change', (e) => { if (e.target.name === 'type') refreshOld(); });
    document.querySelectorAll('.sc-close').forEach((b) => b.addEventListener('click', () => show(false)));
    document.getElementById('sc-clear-old').addEventListener('click', () => { old.value = ''; });
    // File numbers come from the global selector, never typed.
    document.getElementById('sc-pick').addEventListener('click', () => {
        GlobalFileNoModal.open({ callback: (data) => { if (data && data.fileNumber) old.value = data.fileNumber; } });
    });

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        const type = form.querySelector('input[name=type]:checked')?.value;
        if (!type) return Swal.fire({ icon: 'warning', title: 'Choose a type', text: 'Change of Purpose or Conversion.' });
        if (needsOld() && !old.value.trim()) return Swal.fire({ icon: 'warning', title: 'Old file number', text: 'Select the old file number for a Change of Purpose.' });
        const btn = document.getElementById('sc-submit'); btn.disabled = true;
        try {
            const r = await fetch('{{ url('special-assignment/land-records') }}/' + rec.id + '/send-to-change-of-purpose', {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                // A Conversion sends no old file number, even one prefilled.
                body: JSON.stringify({ type, old_file_no: type === 'conversion' ? null : (old.value.trim() || null) }),
            });
            const d = await r.json().catch(() => ({}));
            if (!r.ok || !d.success) throw new Error(d.message || (d.errors ? Object.values(d.errors)[0][0] : 'Not sent'));
            show(false);
            Swal.fire({ icon: 'success', title: 'Sent', text: d.message });
            $.fn.DataTable.tables({ api: true }).ajax.reload(null, false);
        } catch (err) {
            Swal.fire({ icon: 'error', title: 'Not sent', text: err.message });
        } finally { btn.disabled = false; }
    });
});
</script>
<script>
const CSRF      = '{{ csrf_token() }}';
const BILLS_URL = window.location.href;
const PAY_STORE = '{{ route("special-assignment.bills.pay") }}';
// Built from the named route with a placeholder id: hand-writing the path here
// is what left the old form posting to a URL that did not exist, so the modal
// sat on "Saving…" and the bill was never marked paid.
const SHEET_URL = '{{ route("special-assignment.bills.sheet", ["id" => "__ID__"]) }}';
const ADD_ITEM_URL    = '{{ route("special-assignment.bills.add-item", ["id" => "__ID__"]) }}';
const REMOVE_ITEM_URL = '{{ route("special-assignment.bills.remove-item", ["id" => "__ID__"]) }}';

const STATUS_COLORS = { unpaid:'bg-red-100 text-red-700', partial:'bg-amber-100 text-amber-700', paid:'bg-green-100 text-green-700' };

// The bill-item columns, in the same order the table header was built from.
const ITEM_COLS = @json($itemColumns);

// Declared out here because the table's render callbacks use it, and they are
// defined before the ready() body that used to hold it.
const esc = v => String(v ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));

$(document).ready(function () {
    const table = $('#spa-bills-table').DataTable({
        processing: true, serverSide: true,
        ajax: { url: BILLS_URL, data: d => ({ ...d, ajax:1 }) },
        // The item columns are generated from the same list the header is built
        // from, in the same order — a mismatch here silently shifts every value
        // one column sideways.
        columns: [
            { data:'DT_RowIndex', orderable:false, searchable:false },
            { data:'reference_id', render: d => d && d!=='—' ? `<span class="font-semibold text-red-600">${d}</span>` : '<span class="text-gray-400">—</span>' },
            { data:'spa_id', render: d => `<span style="white-space:nowrap;font-weight:600">${d||'—'}</span>` }, { data:'file_number' }, { data:'owner_name' }, { data:'bill_type' },
            {
                data: 'description',
                className: 'desc-cell',
                // Clipped by CSS rather than cut in PHP, so the whole sentence is
                // still there to search, to read on hover, and to expand.
                render: (d, type) => {
                    if (type !== 'display') return d || '';
                    if (!d || d === '—') return '<span class="text-gray-400">—</span>';
                    return `<span class="desc-short" title="${esc(d)}">${esc(d)}</span>`;
                },
            },
            ...ITEM_COLS.map((name, i) => ({
                data: 'item_' + i,
                className: 'text-right text-gray-600',
                orderable: false,
                render: d => d === '—' ? '<span class="text-gray-300">—</span>' : d,
            })),
            { data:'amount',  className:'text-right font-medium' },
            { data:'paid',    className:'text-right text-gray-600' },
            { data:'balance', className:'text-right font-medium' },
            { data:'due_date' },
            { data:'status', render: d => `<span class="px-2 py-0.5 rounded-full text-xs font-medium ${STATUS_COLORS[d]||'bg-gray-100 text-gray-600'}">${d||'—'}</span>` },
            { data:'action', orderable:false, searchable:false },
        ],
    });

    // Description is clipped to keep rows one line tall; clicking it opens that
    // one row rather than making every row three lines high.
    $('#spa-bills-table').on('click', '.desc-short', function () {
        $(this).closest('td').toggleClass('is-open');
    });

    // Pay button — the form is built from the bill's own items, so it has to be
    // loaded before the modal is any use.
    // ── Row ⋮ menu ─────────────────────────────────────────────────────────
    // Opened FIXED and lifted to <body> so the table's scroll box never clips
    // it (the last rows especially); its items are bound to the document.
    function closeBillMenus() {
        document.querySelectorAll('.bill-menu.is-open').forEach((m) => {
            m.classList.add('hidden'); m.classList.remove('is-open');
            if (m._home) m._home.appendChild(m);
        });
    }
    $(document).on('click', '.btn-bill-menu', function (e) {
        e.stopPropagation();
        const menu = this.parentElement.querySelector('.bill-menu') || this._menu;
        const wasOpen = menu && menu.classList.contains('is-open');
        closeBillMenus();
        if (!menu || wasOpen) return;
        this._menu = menu; menu._home = this.parentElement;
        document.body.appendChild(menu);
        menu.classList.remove('hidden'); menu.classList.add('is-open');
        Object.assign(menu.style, { position: 'fixed', zIndex: 2000 });
        const r = this.getBoundingClientRect(), h = menu.offsetHeight, w = menu.offsetWidth;
        const top = (window.innerHeight - r.bottom < h + 8 && r.top > h + 8) ? r.top - h - 4 : r.bottom + 4;
        menu.style.top  = Math.max(8, top) + 'px';
        menu.style.left = Math.max(8, Math.min(r.right - w, window.innerWidth - w - 8)) + 'px';
    });
    $(document).on('click', (e) => { if (!e.target.closest('.bill-menu')) closeBillMenus(); });
    $(document).on('click', '.bill-menu a, .bill-menu button', () => closeBillMenus());
    window.addEventListener('scroll', (e) => { if (!e.target.closest || !e.target.closest('.bill-menu')) closeBillMenus(); }, true);
    window.addEventListener('resize', closeBillMenus);
    $('#spa-bills-table').on('preDraw.dt', closeBillMenus);

    // Bound to the document, not the table: the menu is lifted out of it.
    $(document).on('click', '.btn-pay', function () {
        const id = $(this).data('id');
        document.getElementById('pay-bill-id').value = id;
        window.SpasPipeline && SpasPipeline.mount(document.getElementById('pay-pipeline'), $(this).data('app'), { current: 'payment', force: true });
        const m = document.getElementById('modal-pay');
        m.classList.remove('hidden'); m.classList.add('flex');
        loadPaySheet(id);
    });

    // Modals
    const modalPay = document.getElementById('modal-pay');
    document.querySelectorAll('.pay-close').forEach(b => b.addEventListener('click', () => { modalPay.classList.add('hidden'); modalPay.classList.remove('flex'); }));

    // ---- Payment sheet (the items of ONE bill) --------------------------
    const payLines   = document.getElementById('pay-lines');
    const payTotalEl = document.getElementById('pay-total');
    const payNote    = document.getElementById('pay-balance-note');
    const money      = n => '₦' + Number(n || 0).toLocaleString('en-NG', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

    async function loadPaySheet(billId) {
        payLines.innerHTML = '<tr><td colspan="4" class="px-3 py-4 text-center text-xs text-gray-400">Loading…</td></tr>';
        payTotalEl.textContent = money(0);
        payNote.textContent = '';
        renderAddable(billId, []);
        document.getElementById('pay-bill-meta').textContent = '—';

        const res  = await fetch(SHEET_URL.replace('__ID__', billId), { headers: { 'Accept': 'application/json' } });
        const data = await res.json().catch(() => ({}));

        if (!data.success) {
            payLines.innerHTML = '<tr><td colspan="4" class="px-3 py-4 text-center text-xs text-red-500">Could not load this bill.</td></tr>';
            return;
        }

        const b = data.bill;
        const meta = document.getElementById('pay-bill-meta');
        const ref  = document.createElement('span');
        ref.className   = 'text-red-600 font-semibold';
        ref.textContent = b.reference_id || '—';
        meta.replaceChildren(ref, ` · ${b.file_number || '—'} · ${b.owner_name || '—'}`);

        // A bill raised before the tariff existed has no items. Rather than show
        // an empty table, fall back to a single field against the whole bill —
        // the server accepts that shape too.
        if (!data.lines.length) {
            payLines.innerHTML = `<tr>
                <td class="px-3 py-2">${esc(b.reference_id || 'Bill')} <span class="text-xs text-gray-400">(not itemised)</span></td>
                <td class="px-3 py-2 text-right">${Number(b.amount).toFixed(2)}</td>
                <td class="px-3 py-2 text-right">${Number(b.total_paid).toFixed(2)}</td>
                <td class="px-3 py-2 text-right">${Number(b.balance).toFixed(2)}</td></tr>`;
        } else {
            payLines.innerHTML = data.lines.map(l => `<tr class="border-t border-gray-100" data-line-id="${l.id}">
                <td class="px-3 py-2">${esc(l.name)}${l.removable
                    ? ` <button type="button" class="pay-remove-item ml-1 text-xs text-red-500 hover:text-red-700" data-line-id="${l.id}" data-name="${esc(l.name)}" title="Take ${esc(l.name)} off this bill">&times;</button>`
                    : ''}</td>
                <td class="px-3 py-2 text-right text-gray-600">${Number(l.amount).toFixed(2)}</td>
                <td class="px-3 py-2 text-right text-gray-500">${Number(l.paid).toFixed(2)}</td>
                <td class="px-3 py-2 text-right font-medium ${l.outstanding > 0 ? 'text-gray-800' : 'text-green-600'}">${Number(l.outstanding).toFixed(2)}</td></tr>`).join('');
        }

        renderAddable(billId, data.addable || []);

        payNote.innerHTML = `Bill total ${money(b.amount)} · already paid ${money(b.total_paid)} · <strong>outstanding ${money(b.balance)}</strong>`
            + (b.unallocated > 0 ? `<br><span class="text-amber-600">${money(b.unallocated)} of earlier payments was recorded against the bill as a whole, not against items.</span>` : '');

        payTotalEl.textContent = money(b.amount);
    }

    // Buttons for the "add when needed" items not yet on this bill, each at its
    // tariff amount. One still at 0 on the tariff says where to set it instead.
    const payAddable = document.getElementById('pay-addable');
    function renderAddable(billId, items) {
        payAddable.dataset.billId = billId;
        payAddable.classList.toggle('hidden', !items.length);
        payAddable.innerHTML = items.map(it => it.amount > 0
            ? `<button type="button" class="pay-add-item inline-flex items-center gap-1 px-3 py-1.5 text-xs font-medium rounded-lg border border-red-200 text-red-700 bg-red-50 hover:bg-red-100"
                   data-item-id="${it.id}" data-name="${esc(it.name)}" data-amount="${it.amount}">+ Add ${esc(it.name)} (${money(it.amount)})</button>`
            : `<span class="text-xs text-gray-400">${esc(it.name)} has no amount yet — set it in Configurable Entries → SPAS Bill Items.</span>`
        ).join('');
    }

    async function changeBillItem(url, body) {
        const res  = await fetch(url, { method:'POST', headers:{'X-CSRF-TOKEN':CSRF,'Content-Type':'application/json','Accept':'application/json'}, body: JSON.stringify(body) });
        const data = await res.json().catch(() => ({}));
        if (!data.success) {
            Swal.fire({ icon:'error', title:'Not changed', text: data.message || 'Could not update the bill.' });
            return;
        }
        const billId = document.getElementById('pay-bill-id').value;
        await loadPaySheet(billId);
        table.ajax.reload(null, false);
    }

    payAddable.addEventListener('click', async e => {
        const btn = e.target.closest('.pay-add-item');
        if (!btn) return;
        const ok = await Swal.fire({
            icon: 'question',
            title: `Add ${btn.dataset.name}?`,
            text: `${money(btn.dataset.amount)} will be added to this bill's total.`,
            showCancelButton: true, confirmButtonText: 'Add', confirmButtonColor: 'rgb(186,191,12)',
        });
        if (!ok.isConfirmed) return;
        changeBillItem(ADD_ITEM_URL.replace('__ID__', payAddable.dataset.billId), { item_id: Number(btn.dataset.itemId) });
    });

    payLines.addEventListener('click', async e => {
        const btn = e.target.closest('.pay-remove-item');
        if (!btn) return;
        const ok = await Swal.fire({
            icon: 'warning',
            title: `Take ${btn.dataset.name} off this bill?`,
            showCancelButton: true, confirmButtonText: 'Take off', confirmButtonColor: '#dc2626',
        });
        if (!ok.isConfirmed) return;
        changeBillItem(REMOVE_ITEM_URL.replace('__ID__', document.getElementById('pay-bill-id').value), { line_id: Number(btn.dataset.lineId) });
    });


    // Generate Bill: print the BILL for what is owed. Nothing is recorded —
    // payments are taken on Special Assignment – Payments.
    const BILL_PRINT = '{{ route("special-assignment.bills.print", ["id" => "__ID__"]) }}';
    document.getElementById('form-pay').addEventListener('submit', function (e) {
        e.preventDefault();
        window.open(BILL_PRINT.replace('__ID__', document.getElementById('pay-bill-id').value), '_blank');
        modalPay.classList.add('hidden'); modalPay.classList.remove('flex');
    });
});
</script>
@endsection
