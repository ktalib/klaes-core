@extends('layouts.app')
@section('page-title'){{ $PageTitle ?? 'Special Assignment – Memo' }}@endsection

@section('content')
<div class="flex-1 overflow-auto">
    @include('admin.header')
    <div class="p-6">
        <div class="flex items-center justify-between mb-6">
            <div>
                <h1 class="text-2xl font-bold text-gray-800">Special Assignment – Memo</h1>
                <p class="text-sm text-gray-500 mt-1">{{ $PageDescription ?? '' }}</p>
            </div>
            <button id="btn-gen-memo"
                class="inline-flex items-center gap-2 px-4 py-2 bg-[rgb(186,191,12)] hover:opacity-90 text-white text-sm font-medium rounded-lg">
                <i data-lucide="file-plus" class="h-4 w-4"></i> Generate Memo
            </button>
        </div>

        {{-- Stats --}}
        <div class="grid grid-cols-1 sm:grid-cols-4 gap-4 mb-6">
            <div class="bg-white rounded-xl border border-gray-200 p-4 flex items-center gap-4">
                <div class="p-3 rounded-full" style="background:rgba(186,191,12,.12)"><i data-lucide="file-text" class="h-5 w-5 text-[rgb(186,191,12)]"></i></div>
                <div><p class="text-xs text-gray-500">Total Memos</p><p class="text-xl font-bold text-gray-800">{{ $stats['total'] }}</p></div>
            </div>
            <div class="bg-white rounded-xl border border-gray-200 p-4 flex items-center gap-4">
                <div class="p-3 rounded-full bg-amber-50"><i data-lucide="clock" class="h-5 w-5 text-amber-500"></i></div>
                <div><p class="text-xs text-gray-500">Pending</p><p class="text-xl font-bold text-gray-800">{{ $stats['pending'] }}</p></div>
            </div>
            <div class="bg-white rounded-xl border border-gray-200 p-4 flex items-center gap-4">
                <div class="p-3 rounded-full bg-green-50"><i data-lucide="check-circle" class="h-5 w-5 text-green-600"></i></div>
                <div><p class="text-xs text-gray-500">Approved</p><p class="text-xl font-bold text-gray-800">{{ $stats['approved'] }}</p></div>
            </div>
            <div class="bg-white rounded-xl border border-gray-200 p-4 flex items-center gap-4">
                <div class="p-3 rounded-full bg-red-50"><i data-lucide="x-circle" class="h-5 w-5 text-red-500"></i></div>
                <div><p class="text-xs text-gray-500">Rejected</p><p class="text-xl font-bold text-gray-800">{{ $stats['rejected'] }}</p></div>
            </div>
        </div>

        {{-- DataTable --}}
        <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-100"><h2 class="text-sm font-semibold text-gray-700">All Memos</h2></div>
            <div class="p-4">
                <table id="memo-table" class="w-full text-sm" style="width:100%">
                    <thead>
                        <tr class="text-left text-xs text-gray-500 uppercase">
                            <th class="px-3 py-2">#</th>
                            <th class="px-3 py-2">Memo No</th>
                            <th class="px-3 py-2">File No</th>
                            <th class="px-3 py-2">Forwarded To</th>
                            <th class="px-3 py-2">Forwarded Date</th>
                            <th class="px-3 py-2">Status</th>
                            <th class="px-3 py-2">Date Approved</th>
                            <th class="px-3 py-2">Action</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>
</div>

{{-- Generate Memo Modal --}}
<div id="modal-gen-memo" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/40">
    <div class="bg-white rounded-2xl shadow-xl w-full max-w-lg mx-4">
        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
            <h3 class="text-base font-semibold text-gray-800">Generate Commissioner Memo</h3>
            <button class="modal-close text-gray-400 hover:text-gray-600"><i data-lucide="x" class="h-5 w-5"></i></button>
        </div>
        <form id="form-gen-memo" class="p-6 space-y-4">
            @csrf
            <div class="grid grid-cols-1 gap-4">
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Application <span class="text-red-500">*</span></label>
                    <select name="spa_application_id" required
                        class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm outline-none focus:border-[rgb(186,191,12)]">
                        <option value="">Select application (no memo yet)…</option>
                        @foreach($applications as $app)
                            <option value="{{ $app->id }}">{{ $app->file_number }} – {{ $app->owner_name }}</option>
                        @endforeach
                    </select>
                </div>
                <input type="hidden" name="forwarded_to" value="Honourable Commissioner">
            </div>
            <div class="flex justify-end gap-3 pt-2">
                <button type="button" class="modal-close px-4 py-2 text-sm text-gray-600 border border-gray-200 rounded-lg hover:bg-gray-50">Cancel</button>
                <button type="submit" class="px-5 py-2 text-sm text-white bg-[rgb(186,191,12)] rounded-lg hover:opacity-90">Generate &amp; Forward</button>
            </div>
        </form>
    </div>
</div>

{{-- Commissioner Decision Modal --}}
<div id="modal-decision" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/40">
    <div class="bg-white rounded-2xl shadow-xl w-full max-w-lg mx-4">
        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
            <h3 class="text-base font-semibold text-gray-800">Record Commissioner Decision</h3>
            <button class="decision-close text-gray-400 hover:text-gray-600"><i data-lucide="x" class="h-5 w-5"></i></button>
        </div>
        <form id="form-decision" class="p-6 space-y-4">
            @csrf
            <input type="hidden" name="memo_id" id="dec-memo-id">
            <p class="text-sm text-gray-600">Memo: <strong id="dec-memo-no" class="text-gray-800">—</strong></p>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="sm:col-span-2">
                    <label class="block text-xs font-medium text-gray-600 mb-1">Decision <span class="text-red-500">*</span></label>
                    <div class="flex gap-3">
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="radio" name="decision" value="approved" required class="accent-green-600">
                            <span class="text-sm text-green-700 font-medium">Approve</span>
                        </label>
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="radio" name="decision" value="rejected" class="accent-red-600">
                            <span class="text-sm text-red-700 font-medium">Reject</span>
                        </label>
                    </div>
                </div>
                <div class="sm:col-span-2">
                    <label class="block text-xs font-medium text-gray-600 mb-1">Notes / Remarks</label>
                    <textarea name="notes" rows="3"
                        class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm outline-none resize-none focus:border-[rgb(186,191,12)]"
                        placeholder="Commissioner's remarks (optional)…"></textarea>
                </div>
            </div>
            <div class="flex justify-end gap-3 pt-2">
                <button type="button" class="decision-close px-4 py-2 text-sm text-gray-600 border border-gray-200 rounded-lg hover:bg-gray-50">Cancel</button>
                <button type="submit" class="px-5 py-2 text-sm text-white bg-[rgb(186,191,12)] rounded-lg hover:opacity-90">Save Decision</button>
            </div>
        </form>
    </div>
</div>

<style>
    table.dataTable thead th { background:#f9fafb; font-weight:600; }
    .dataTables_wrapper .dataTables_filter input { border:1px solid #e5e7eb; border-radius:.5rem; padding:.35rem .75rem; font-size:.85rem; }
    .dataTables_wrapper .dataTables_length select { border:1px solid #e5e7eb; border-radius:.5rem; padding:.25rem .5rem; }
</style>

<script>
const CSRF         = '{{ csrf_token() }}';
const STORE_MEMO   = '{{ route("special-assignment.memo.generate") }}';
const DECISION_BASE= '{{ url("special-assignment/memo") }}';

const DEC_COLORS = {
    pending : 'bg-amber-100 text-amber-700',
    approved: 'bg-green-100 text-green-700',
    rejected: 'bg-red-100 text-red-700',
};

$(document).ready(function () {
    const table = $('#memo-table').DataTable({
        processing: true, serverSide: true,
        ajax: { url: window.location.href, data: d => ({ ...d, ajax:1 }) },
        columns: [
            { data:'DT_RowIndex', orderable:false, searchable:false },
            { data:'memo_no' },
            { data:'file_number' },
            { data:'forwarded_to' },
            { data:'forwarded_at' },
            { data:'decision', render: d => `<span class="px-2 py-0.5 rounded-full text-xs font-medium ${DEC_COLORS[d]||'bg-gray-100 text-gray-600'}">${d||'—'}</span>` },
            { data:'decided_at', render: d => d||'—' },
            { data:'action', orderable:false, searchable:false },
        ],
    });

    // Decide button (delegated)
    $('#memo-table').on('click', '.btn-decide', function () {
        document.getElementById('dec-memo-id').value  = $(this).data('id');
        document.getElementById('dec-memo-no').textContent = $(this).data('memo') || '—';
        const m = document.getElementById('modal-decision');
        m.classList.remove('hidden'); m.classList.add('flex');
    });

    // Modal open/close
    const modal = document.getElementById('modal-gen-memo');
    document.getElementById('btn-gen-memo').addEventListener('click', () => { modal.classList.remove('hidden'); modal.classList.add('flex'); });
    document.querySelectorAll('.modal-close').forEach(b => b.addEventListener('click', () => { modal.classList.add('hidden'); modal.classList.remove('flex'); }));
    const modalDec = document.getElementById('modal-decision');
    document.querySelectorAll('.decision-close').forEach(b => b.addEventListener('click', () => { modalDec.classList.add('hidden'); modalDec.classList.remove('flex'); }));

    // Generate memo
    document.getElementById('form-gen-memo').addEventListener('submit', async function(e) {
        e.preventDefault();
        const btn = this.querySelector('[type=submit]');
        btn.disabled = true; btn.textContent = 'Generating…';
        const res  = await fetch(STORE_MEMO, { method:'POST', headers:{'X-CSRF-TOKEN':CSRF,'Content-Type':'application/json'}, body: JSON.stringify(Object.fromEntries(new FormData(this))) });
        const data = await res.json();
        if (data.success) {
            modal.classList.add('hidden'); modal.classList.remove('flex');
            this.reset();
            table.ajax.reload();
            Swal.fire({ icon:'success', title:'Memo Generated', text:data.message, timer:2500, showConfirmButton:false });
        } else {
            Swal.fire({ icon:'error', title:'Error', text:data.message||'Could not generate memo.' });
        }
        btn.disabled=false; btn.textContent='Generate & Forward';
    });

    // Record decision
    document.getElementById('form-decision').addEventListener('submit', async function(e) {
        e.preventDefault();
        const btn = this.querySelector('[type=submit]');
        btn.disabled = true; btn.textContent = 'Saving…';
        const body = Object.fromEntries(new FormData(this));
        const id   = body.memo_id;
        const res  = await fetch(`${DECISION_BASE}/${id}/decision`, { method:'POST', headers:{'X-CSRF-TOKEN':CSRF,'Content-Type':'application/json'}, body: JSON.stringify(body) });
        const data = await res.json();
        if (data.success) {
            modalDec.classList.add('hidden'); modalDec.classList.remove('flex');
            this.reset();
            table.ajax.reload();
            Swal.fire({ icon:'success', title:'Decision Recorded', text:data.message, timer:2500, showConfirmButton:false });
        } else {
            Swal.fire({ icon:'error', title:'Error', text:data.message||'Save failed.' });
        }
        btn.disabled=false; btn.textContent='Save Decision';
    });
});
</script>
@endsection
