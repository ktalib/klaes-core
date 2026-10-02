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
                <table id="spa-payments-table" class="w-full text-sm" style="width:100%">
                    <thead>
                        <tr class="text-left text-xs text-gray-500 uppercase">
                            <th class="px-3 py-2">#</th>
                            <th class="px-3 py-2">File No</th>
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
    <div class="bg-white rounded-2xl shadow-xl w-full max-w-lg mx-4">
        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
            <h3 class="text-base font-semibold text-gray-800">Record Payment</h3>
            <button class="modal-close text-gray-400 hover:text-gray-600"><i data-lucide="x" class="h-5 w-5"></i></button>
        </div>
        <form id="form-record-payment" class="p-6 space-y-4">
            @csrf
            <input type="hidden" name="spa_bill_id" id="pay-bill-id">
            {{-- Bill context (read-only) --}}
            <div class="px-3 py-2 bg-blue-50 border border-blue-100 rounded-lg text-xs text-blue-800 space-y-0.5">
                <div><span class="font-medium">File No:</span> <span id="pay-file-no">—</span></div>
                <div><span class="font-medium">Bill Type:</span> <span id="pay-bill-type">—</span></div>
                <div><span class="font-medium">Outstanding Balance:</span> ₦<span id="pay-balance">—</span></div>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Amount Paid (₦) <span class="text-red-500">*</span></label>
                    <input type="number" name="amount_paid" id="pay-amount" required min="0.01" step="0.01"
                        class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm outline-none focus:border-[rgb(186,191,12)]">
                </div>
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
                        <option value="cash">Cash</option>
                        <option value="bank_transfer">Bank Transfer</option>
                        <option value="pos">POS</option>
                        <option value="cheque">Cheque</option>
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
            { data:'file_number' },
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

    // ── Open modal from action row ─────────────────────────────────────────
    $(document).on('click', '.btn-record-payment', function() {
        $('.action-dropdown').addClass('hidden');
        const btn = this;
        document.getElementById('pay-bill-id').value      = btn.dataset.id;
        document.getElementById('pay-file-no').textContent   = btn.dataset.file   || '—';
        document.getElementById('pay-bill-type').textContent = btn.dataset.type   || '—';
        document.getElementById('pay-balance').textContent   = btn.dataset.balance|| '—';
        document.getElementById('pay-amount').value = '';
        modal.classList.remove('hidden'); modal.classList.add('flex');
        lucide.createIcons();
    });

    // ── Close modal ────────────────────────────────────────────────────────
    document.querySelectorAll('.modal-close').forEach(b => b.addEventListener('click', () => {
        modal.classList.add('hidden'); modal.classList.remove('flex');
    }));

    // ── Save payment ───────────────────────────────────────────────────────
    document.getElementById('form-record-payment').addEventListener('submit', async function(e) {
        e.preventDefault();
        const btn = this.querySelector('[type=submit]');
        btn.disabled = true; btn.textContent = 'Saving…';
        try {
            const res  = await fetch(PAY_STORE, { method:'POST', headers:{'X-CSRF-TOKEN':CSRF,'Content-Type':'application/json'}, body: JSON.stringify(Object.fromEntries(new FormData(this))) });
            const data = await res.json();
            if (data.success) {
                modal.classList.add('hidden'); modal.classList.remove('flex');
                this.reset(); table.ajax.reload();
                Swal.fire({ icon:'success', title:'Recorded', text:data.message, timer:2000, showConfirmButton:false });
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
