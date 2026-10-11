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

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Payment Date <span class="text-red-500">*</span></label>
                    <input type="date" name="payment_date" required
                        class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm outline-none focus:border-[rgb(186,191,12)]">
                </div>
            </div>

            {{-- Bill items --}}
            <div class="border border-gray-200 rounded-xl overflow-hidden">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 text-xs text-gray-500 uppercase">
                        <tr>
                            <th class="px-3 py-2 text-left">Bill Item</th>
                            <th class="px-3 py-2 text-right w-28">Billed (₦)</th>
                            <th class="px-3 py-2 text-right w-28">Paid (₦)</th>
                            <th class="px-3 py-2 text-right w-28">Balance (₦)</th>
                            <th class="px-3 py-2 text-right w-36">Paying Now (₦)</th>
                        </tr>
                    </thead>
                    <tbody id="pay-lines">
                        <tr><td colspan="5" class="px-3 py-4 text-center text-xs text-gray-400">Loading…</td></tr>
                    </tbody>
                    <tfoot class="bg-gray-50">
                        <tr>
                            <td colspan="4" class="px-3 py-2 text-right text-xs font-semibold text-gray-600 uppercase">Total Paying Now</td>
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

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Payment Method</label>
                    <select name="payment_method"
                        class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm outline-none focus:border-[rgb(186,191,12)]">
                        <option value="bank_transfer">Bank Transfer</option>
                        <option value="pos">POS</option>
                        <option value="cheque">Cheque</option>
                    </select>
                </div>
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

<script src="{{ asset('js/spas-pipeline.js') }}?v={{ @filemtime(public_path('js/spas-pipeline.js')) }}"></script>
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
    $('#spa-bills-table').on('click', '.btn-pay', function () {
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
        payLines.innerHTML = '<tr><td colspan="5" class="px-3 py-4 text-center text-xs text-gray-400">Loading…</td></tr>';
        payTotalEl.textContent = money(0);
        payNote.textContent = '';
        renderAddable(billId, []);
        document.getElementById('pay-bill-meta').textContent = '—';

        const res  = await fetch(SHEET_URL.replace('__ID__', billId), { headers: { 'Accept': 'application/json' } });
        const data = await res.json().catch(() => ({}));

        if (!data.success) {
            payLines.innerHTML = '<tr><td colspan="5" class="px-3 py-4 text-center text-xs text-red-500">Could not load this bill.</td></tr>';
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
                <td class="px-3 py-2 text-right">${Number(b.balance).toFixed(2)}</td>
                <td class="px-3 py-2 text-right">
                    <input type="number" name="amount_paid" min="0" step="0.01" value="${Number(b.balance).toFixed(2)}"
                        class="pay-amount w-32 border border-gray-200 rounded-lg px-2 py-1 text-sm text-right outline-none focus:border-[rgb(186,191,12)]">
                </td></tr>`;
        } else {
            payLines.innerHTML = data.lines.map(l => `<tr class="border-t border-gray-100" data-line-id="${l.id}">
                <td class="px-3 py-2">${esc(l.name)}${l.removable
                    ? ` <button type="button" class="pay-remove-item ml-1 text-xs text-red-500 hover:text-red-700" data-line-id="${l.id}" data-name="${esc(l.name)}" title="Take ${esc(l.name)} off this bill">&times;</button>`
                    : ''}</td>
                <td class="px-3 py-2 text-right text-gray-600">${Number(l.amount).toFixed(2)}</td>
                <td class="px-3 py-2 text-right text-gray-500">${Number(l.paid).toFixed(2)}</td>
                <td class="px-3 py-2 text-right font-medium ${l.outstanding > 0 ? 'text-gray-800' : 'text-green-600'}">${Number(l.outstanding).toFixed(2)}</td>
                <td class="px-3 py-2 text-right">
                    <input type="number" min="0" step="0.01" data-outstanding="${l.outstanding}"
                        value="${Number(l.outstanding).toFixed(2)}"
                        class="pay-amount w-32 border border-gray-200 rounded-lg px-2 py-1 text-sm text-right outline-none focus:border-[rgb(186,191,12)]">
                </td></tr>`).join('');
        }

        renderAddable(billId, data.addable || []);

        payNote.innerHTML = `Bill total ${money(b.amount)} · already paid ${money(b.total_paid)} · <strong>outstanding ${money(b.balance)}</strong>`
            + (b.unallocated > 0 ? `<br><span class="text-amber-600">${money(b.unallocated)} of earlier payments was recorded against the bill as a whole, not against items.</span>` : '');

        recalcPayTotal();
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

    function recalcPayTotal() {
        let total = 0;
        payLines.querySelectorAll('.pay-amount').forEach(i => { total += parseFloat(i.value) || 0; });
        payTotalEl.textContent = money(total);
    }

    payLines.addEventListener('input', e => {
        if (!e.target.classList.contains('pay-amount')) return;
        // Never let an item collect more than it still owes: the server rejects
        // it anyway, and correcting it here says why without a round trip.
        const max = parseFloat(e.target.dataset.outstanding);
        if (!isNaN(max) && (parseFloat(e.target.value) || 0) > max) e.target.value = max.toFixed(2);
        recalcPayTotal();
    });

    // Record payment
    document.getElementById('form-pay').addEventListener('submit', async function(e) {
        e.preventDefault();
        const btn = this.querySelector('[type=submit]');

        // What was collected against each item. The server sums these itself —
        // it does not take a total from here — so the receipt's lines and its
        // total can never disagree.
        const lines = [];
        payLines.querySelectorAll('tr[data-line-id]').forEach(tr => {
            const amount = parseFloat(tr.querySelector('.pay-amount').value) || 0;
            if (amount > 0) lines.push({ id: Number(tr.dataset.lineId), amount_paid: amount });
        });

        const payload = { ...Object.fromEntries(new FormData(this)), lines };

        if (!lines.length && !(parseFloat(payload.amount_paid) > 0)) {
            Swal.fire({ icon:'warning', title:'Nothing to record', text:'Enter an amount against at least one bill item.' });
            return;
        }

        btn.disabled = true; btn.textContent = 'Saving…';
        const res  = await fetch(PAY_STORE, { method:'POST', headers:{'X-CSRF-TOKEN':CSRF,'Content-Type':'application/json'}, body: JSON.stringify(payload) });
        const data = await res.json().catch(() => ({}));
        if (data.success) {
            modalPay.classList.add('hidden'); modalPay.classList.remove('flex');
            this.reset(); table.ajax.reload();
            Swal.fire({
                icon: 'success',
                title: 'Recorded',
                text: data.message,
                showCancelButton: true,
                confirmButtonText: 'Print Receipt',
                cancelButtonText: 'Close',
                confirmButtonColor: 'rgb(186,191,12)',
            }).then(r => { if (r.isConfirmed) window.open(data.receipt_url, '_blank'); });
        } else {
            Swal.fire({ icon:'error', title:'Error', text:data.message||'Save failed.' });
        }
        btn.disabled=false; btn.textContent='GENERATE BILL';
    });
});
</script>
@endsection
