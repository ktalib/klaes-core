@extends('layouts.app')
@section('page-title'){{ $PageTitle ?? 'Special Assignment – Payments' }}@endsection

@section('content')
<div class="flex-1 overflow-auto">
    @include('admin.header')
    <div class="p-6">
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-gray-800">{{ $PageTitle }}</h1>
            <p class="text-sm text-gray-500 mt-1">{{ $PageDescription }}</p>
        </div>

        {{-- Where this page sits in the SPAS workflow. --}}
        @include('special_assignment.partials.workflow', ['current' => ['payment']])

        {{-- Stats --}}
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-6">
            <div class="bg-white rounded-xl border border-gray-200 p-4 flex items-center gap-3">
                <div class="p-2 rounded-full" style="background:rgba(186,191,12,.12)"><i data-lucide="credit-card" class="h-5 w-5 text-[rgb(186,191,12)]"></i></div>
                <div><p class="text-xs text-gray-500">Total Payments</p><p class="text-xl font-bold text-gray-800">{{ $stats['total_payments'] }}</p></div>
            </div>
            <div class="bg-white rounded-xl border border-gray-200 p-4 flex items-center gap-3">
                <div class="p-2 rounded-full bg-green-50"><i data-lucide="trending-up" class="h-5 w-5 text-green-600"></i></div>
                <div><p class="text-xs text-gray-500">Total Collected</p><p class="text-lg font-bold text-gray-800">₦{{ $stats['total_amount'] }}</p></div>
            </div>
            <div class="bg-white rounded-xl border border-gray-200 p-4 flex items-center gap-3">
                <div class="p-2 rounded-full bg-blue-50"><i data-lucide="check-circle" class="h-5 w-5 text-blue-500"></i></div>
                <div><p class="text-xs text-gray-500">Bills Cleared</p><p class="text-xl font-bold text-gray-800">{{ $stats['bills_paid'] }}</p></div>
            </div>
            <div class="bg-white rounded-xl border border-gray-200 p-4 flex items-center gap-3">
                <div class="p-2 rounded-full bg-amber-50"><i data-lucide="clock" class="h-5 w-5 text-amber-500"></i></div>
                <div><p class="text-xs text-gray-500">Partial Payments</p><p class="text-xl font-bold text-gray-800">{{ $stats['bills_partial'] }}</p></div>
            </div>
        </div>

        {{-- DataTable --}}
        <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-100"><h2 class="text-sm font-semibold text-gray-700">Bills & Payment Status</h2></div>
            <div class="p-4">
                @include('special_assignment.partials.table_case')
                <table id="spa-payments-table" class="spas-table w-full text-sm" style="width:100%">
                    <thead>
                        <tr class="text-left text-xs text-gray-500 uppercase">
                            <th class="px-3 py-2">#</th>
                            <th class="px-3 py-2">SPAS ID</th><th class="px-3 py-2">File No</th>
                            <th class="px-3 py-2">Owner</th>
                            <th class="px-3 py-2">Bill Type</th>
                            <th class="px-3 py-2">Bill Amount (₦)</th>
                            <th class="px-3 py-2">Paid (₦)</th>
                            <th class="px-3 py-2">Balance (₦)</th>
                            <th class="px-3 py-2">Status</th>
                            <th class="px-3 py-2 w-10"></th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>
</div>

{{-- Record Payment Modal --}}
@if(!$isReport)
<div id="modal-record-payment" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/40">
    <div class="bg-white rounded-2xl shadow-xl w-full max-w-2xl mx-4 max-h-[92vh] overflow-y-auto">
        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
            <div>
                <h3 class="text-base font-semibold text-gray-800">Record Payment</h3>
                <p id="pay-meta" class="text-xs text-gray-500 mt-0.5">—</p>
            </div>
            <button class="modal-close text-gray-400 hover:text-gray-600"><i data-lucide="x" class="h-5 w-5"></i></button>
        </div>
        {{-- Item by item (2026-10-13): what was paid against each bill item. The
             server sums the items itself, so the receipt and its total agree. --}}
        <form id="form-record-payment" class="p-6 space-y-4">
            @csrf
            <input type="hidden" name="spa_bill_id" id="pay-bill-id">
            <div class="border border-gray-200 rounded-xl overflow-hidden">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 text-xs text-gray-500 uppercase">
                        <tr>
                            <th class="px-3 py-2 text-left">Bill Item</th>
                            <th class="px-3 py-2 text-right w-24">Billed (₦)</th>
                            <th class="px-3 py-2 text-right w-24">Paid (₦)</th>
                            <th class="px-3 py-2 text-right w-24">Balance (₦)</th>
                            <th class="px-3 py-2 text-right w-36">Paying Now (₦)</th>
                        </tr>
                    </thead>
                    <tbody id="pay-lines"><tr><td colspan="5" class="px-3 py-4 text-center text-xs text-gray-400">Loading…</td></tr></tbody>
                    <tfoot class="bg-gray-50">
                        <tr>
                            <td colspan="4" class="px-3 py-2 text-right text-xs font-semibold text-gray-600 uppercase">Total Paying Now</td>
                            <td class="px-3 py-2 text-right font-bold text-gray-800" id="pay-total">₦0.00</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
            <p id="pay-note" class="text-xs text-gray-500"></p>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Payment Date <span class="text-red-500">*</span></label>
                    <input type="date" name="payment_date" required
                        class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm outline-none focus:border-[rgb(186,191,12)]">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Receipt Number</label>
                    <input type="text" name="receipt_number"
                        class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm outline-none focus:border-[rgb(186,191,12)]">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Payment Method</label>
                    <select name="payment_method"
                        class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm outline-none focus:border-[rgb(186,191,12)]">
                        <option value="bank_transfer">Bank Transfer</option>
                        <option value="pos">POS</option>
                        <option value="cheque">Cheque</option>
                        <option value="cash">Cash</option>
                    </select>
                </div>
            </div>
            <div class="flex justify-end gap-3 pt-2">
                <button type="button" class="modal-close px-4 py-2 text-sm text-gray-600 border border-gray-200 rounded-lg hover:bg-gray-50">Cancel</button>
                <button type="submit" class="px-5 py-2 text-sm text-white bg-[rgb(186,191,12)] rounded-lg hover:opacity-90">Save Payment</button>
            </div>
        </form>
    </div>
</div>
@endif

<style>
    table.dataTable thead th { background:#f9fafb; font-weight:600; }
    .dataTables_wrapper .dataTables_filter input { border:1px solid #e5e7eb; border-radius:.5rem; padding:.35rem .75rem; font-size:.85rem; }
    .dataTables_wrapper .dataTables_length select { border:1px solid #e5e7eb; border-radius:.5rem; padding:.25rem .5rem; }
</style>

<script src="{{ asset('js/spas-pipeline.js') }}?v={{ @filemtime(public_path('js/spas-pipeline.js')) }}"></script>
<script>
const CSRF      = '{{ csrf_token() }}';
const PAY_URL   = window.location.href;
const PAY_STORE = '{{ route("special-assignment.bills.pay") }}';
const STATUS_COLORS = { unpaid:'bg-red-100 text-red-700', partial:'bg-amber-100 text-amber-700', paid:'bg-green-100 text-green-700' };

$(document).ready(function () {
    const table = $('#spa-payments-table').DataTable({
        processing: true, serverSide: true,
        ajax: { url: PAY_URL, data: d => ({ ...d, ajax:1 }) },
        columns: [
            { data:'DT_RowIndex', orderable:false, searchable:false },
            { data:'spa_id', render: d => `<span style="white-space:nowrap;font-weight:600">${d||'—'}</span>` }, { data:'file_number' },
            { data:'owner_name'  },
            { data:'bill_type'   },
            { data:'bill_amount' },
            { data:'amount_paid' },
            { data:'balance'     },
            { data:'status', render: d => `<span class="px-2 py-0.5 rounded-full text-xs font-medium ${STATUS_COLORS[d]||'bg-gray-100 text-gray-600'}">${d||'—'}</span>` },
            { data:'action', orderable:false, searchable:false },
        ],
    });

    // ── Dropdown toggle ────────────────────────────────────────────────────
    $(document).on('click', '.btn-action-toggle', function(e) {
        e.stopPropagation();
        const $dd = $(this).siblings('.action-dropdown');
        $('.action-dropdown').not($dd).addClass('hidden');
        $dd.toggleClass('hidden');
    });
    $(document).on('click', function() { $('.action-dropdown').addClass('hidden'); });

    @if(!$isReport)
    const modal = document.getElementById('modal-record-payment');

    const SHEET_URL = '{{ route("special-assignment.bills.sheet", ["id" => "__ID__"]) }}';
    const payLines = document.getElementById('pay-lines');
    const payTotal = document.getElementById('pay-total');
    const money = (n) => '₦' + Number(n || 0).toLocaleString('en-NG', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const esc = (v) => String(v ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    const recalc = () => { let t = 0; payLines.querySelectorAll('.pay-amount').forEach((i) => { t += parseFloat(i.value) || 0; }); payTotal.textContent = money(t); };

    // ── Open: load the bill's items ────────────────────────────────────────
    $(document).on('click', '.btn-record-payment', async function () {
        $('.action-dropdown').addClass('hidden');
        const id = this.dataset.id;
        document.getElementById('pay-bill-id').value = id;
        document.getElementById('form-record-payment').reset();
        document.getElementById('pay-bill-id').value = id;
        payLines.innerHTML = '<tr><td colspan="5" class="px-3 py-4 text-center text-xs text-gray-400">Loading…</td></tr>';
        payTotal.textContent = money(0);
        modal.classList.remove('hidden'); modal.classList.add('flex');
        lucide.createIcons();

        const data = await fetch(SHEET_URL.replace('__ID__', id), { headers: { 'Accept': 'application/json' } }).then((r) => r.json()).catch(() => ({}));
        if (!data.success) { payLines.innerHTML = '<tr><td colspan="5" class="px-3 py-4 text-center text-xs text-red-500">Could not load this bill.</td></tr>'; return; }
        const b = data.bill;
        document.getElementById('pay-meta').textContent = [b.reference_id, b.file_number, b.owner_name].filter(Boolean).join(' · ');
        payLines.innerHTML = data.lines.length
            ? data.lines.map((l) => `<tr class="border-t border-gray-100" data-line-id="${l.id}">
                <td class="px-3 py-2">${esc(l.name)}</td>
                <td class="px-3 py-2 text-right text-gray-600">${Number(l.amount).toFixed(2)}</td>
                <td class="px-3 py-2 text-right text-gray-500">${Number(l.paid).toFixed(2)}</td>
                <td class="px-3 py-2 text-right font-medium ${l.outstanding > 0 ? 'text-gray-800' : 'text-green-600'}">${Number(l.outstanding).toFixed(2)}</td>
                <td class="px-3 py-2 text-right"><input type="number" min="0" step="0.01" data-outstanding="${l.outstanding}" value="${Number(l.outstanding).toFixed(2)}"
                    class="pay-amount w-32 border border-gray-200 rounded-lg px-2 py-1 text-sm text-right outline-none focus:border-[rgb(186,191,12)]"></td></tr>`).join('')
            // A bill raised before itemised tariffs: one amount against the whole bill.
            : `<tr><td class="px-3 py-2">${esc(b.reference_id || 'Bill')} <span class="text-xs text-gray-400">(not itemised)</span></td>
                <td class="px-3 py-2 text-right">${Number(b.amount).toFixed(2)}</td><td class="px-3 py-2 text-right">${Number(b.total_paid).toFixed(2)}</td>
                <td class="px-3 py-2 text-right">${Number(b.balance).toFixed(2)}</td>
                <td class="px-3 py-2 text-right"><input type="number" name="amount_paid" min="0" step="0.01" value="${Number(b.balance).toFixed(2)}"
                    class="pay-amount w-32 border border-gray-200 rounded-lg px-2 py-1 text-sm text-right outline-none focus:border-[rgb(186,191,12)]"></td></tr>`;
        document.getElementById('pay-note').innerHTML = `Bill total ${money(b.amount)} · already paid ${money(b.total_paid)} · <strong>outstanding ${money(b.balance)}</strong>`;
        recalc();
    });
    payLines.addEventListener('input', (e) => {
        if (!e.target.classList.contains('pay-amount')) return;
        // An item cannot collect more than it still owes (the server refuses it too).
        const max = parseFloat(e.target.dataset.outstanding);
        if (!isNaN(max) && (parseFloat(e.target.value) || 0) > max) e.target.value = max.toFixed(2);
        recalc();
    });

    // ── Close modal ────────────────────────────────────────────────────────
    document.querySelectorAll('.modal-close').forEach(b => b.addEventListener('click', () => {
        modal.classList.add('hidden'); modal.classList.remove('flex');
    }));

    // ── Save payment → Payment Receipt ─────────────────────────────────────
    document.getElementById('form-record-payment').addEventListener('submit', async function(e) {
        e.preventDefault();
        const lines = [];
        payLines.querySelectorAll('tr[data-line-id]').forEach((tr) => {
            const amount = parseFloat(tr.querySelector('.pay-amount').value) || 0;
            if (amount > 0) lines.push({ id: Number(tr.dataset.lineId), amount_paid: amount });
        });
        const payload = { ...Object.fromEntries(new FormData(this)), lines };
        if (!lines.length && !(parseFloat(payload.amount_paid) > 0)) {
            return Swal.fire({ icon: 'warning', title: 'Nothing to record', text: 'Enter an amount against at least one bill item.' });
        }
        const btn = this.querySelector('[type=submit]');
        btn.disabled = true; btn.textContent = 'Saving…';
        try {
            const res  = await fetch(PAY_STORE, { method:'POST', headers:{'X-CSRF-TOKEN':CSRF,'Content-Type':'application/json','Accept':'application/json'}, body: JSON.stringify(payload) });
            const data = await res.json();
            if (data.success) {
                modal.classList.add('hidden'); modal.classList.remove('flex');
                this.reset(); table.ajax.reload();
                Swal.fire({ icon: 'success', title: 'Recorded', text: data.message, showCancelButton: true,
                    confirmButtonText: 'Print Payment Receipt', cancelButtonText: 'Close', confirmButtonColor: 'rgb(186,191,12)' })
                    .then((r) => { if (r.isConfirmed) window.open(data.receipt_url, '_blank'); });
            } else {
                Swal.fire({ icon:'error', title:'Error', text:data.message||'Save failed.' });
            }
        } catch(err) { Swal.fire({ icon:'error', title:'Error', text:'Unexpected error.' }); }
        btn.disabled=false; btn.textContent='Save Payment';
    });
    @endif
});
</script>
@endsection
