@extends('layouts.app')
@section('page-title'){{ $PageTitle ?? 'Special Assignment – Other Departments' }}@endsection

@section('content')
<div class="flex-1 overflow-auto">
    @include('admin.header')
    <div class="p-6">
        <div class="flex items-center justify-between mb-6">
            <div>
                <h1 class="text-2xl font-bold text-gray-800">Other Departments</h1>
                <p class="text-sm text-gray-500 mt-1">{{ $PageDescription ?? '' }}</p>
            </div>
            <button id="btn-refer"
                class="inline-flex items-center gap-2 px-4 py-2 bg-[rgb(186,191,12)] hover:opacity-90 text-white text-sm font-medium rounded-lg">
                <i data-lucide="share-2" class="h-4 w-4"></i> Refer Case
            </button>
        </div>

        {{-- Stats --}}
        <div class="grid grid-cols-1 sm:grid-cols-4 gap-4 mb-6">
            <div class="bg-white rounded-xl border border-gray-200 p-4 flex items-center gap-4">
                <div class="p-3 rounded-full" style="background:rgba(186,191,12,.12)"><i data-lucide="share-2" class="h-5 w-5 text-[rgb(186,191,12)]"></i></div>
                <div><p class="text-xs text-gray-500">Total Referrals</p><p class="text-xl font-bold text-gray-800">{{ $stats['total'] }}</p></div>
            </div>
            <div class="bg-white rounded-xl border border-gray-200 p-4 flex items-center gap-4">
                <div class="p-3 rounded-full bg-amber-50"><i data-lucide="clock" class="h-5 w-5 text-amber-500"></i></div>
                <div><p class="text-xs text-gray-500">Pending</p><p class="text-xl font-bold text-gray-800">{{ $stats['pending'] }}</p></div>
            </div>
            <div class="bg-white rounded-xl border border-gray-200 p-4 flex items-center gap-4">
                <div class="p-3 rounded-full bg-blue-50"><i data-lucide="message-square" class="h-5 w-5 text-blue-500"></i></div>
                <div><p class="text-xs text-gray-500">Responded</p><p class="text-xl font-bold text-gray-800">{{ $stats['responded'] }}</p></div>
            </div>
            <div class="bg-white rounded-xl border border-gray-200 p-4 flex items-center gap-4">
                <div class="p-3 rounded-full bg-green-50"><i data-lucide="check-circle" class="h-5 w-5 text-green-500"></i></div>
                <div><p class="text-xs text-gray-500">Approved</p><p class="text-xl font-bold text-gray-800">{{ $stats['approved'] }}</p></div>
            </div>
        </div>

        {{-- Tabs --}}
        <div class="flex gap-1 bg-gray-100 rounded-lg p-1 mb-6 w-fit">
            <button data-tab="cadastral" class="tab-btn active px-5 py-2 text-sm font-medium rounded-md transition-all">
                <span class="flex items-center gap-2"><i data-lucide="map-pin" class="h-4 w-4"></i> Cadastral</span>
            </button>
            <button data-tab="physical_planning" class="tab-btn px-5 py-2 text-sm font-medium rounded-md text-gray-500 transition-all">
                <span class="flex items-center gap-2"><i data-lucide="layout" class="h-4 w-4"></i> Physical Planning / KAMMA</span>
            </button>
            <button data-tab="survey" class="tab-btn px-5 py-2 text-sm font-medium rounded-md text-gray-500 transition-all">
                <span class="flex items-center gap-2"><i data-lucide="compass" class="h-4 w-4"></i> Survey</span>
            </button>
        </div>

        {{-- Cadastral Tab --}}
        <div id="tab-cadastral" class="tab-content">
            <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
                <div class="px-5 py-4 border-b border-gray-100"><h2 class="text-sm font-semibold text-gray-700">Cadastral Referrals</h2></div>
                <div class="p-4">
                    <table id="table-cadastral" class="w-full text-sm" style="width:100%">
                        <thead><tr class="text-left text-xs text-gray-500 uppercase">
                            <th class="px-3 py-2">#</th><th class="px-3 py-2">File No</th><th class="px-3 py-2">Owner</th>
                            <th class="px-3 py-2">Referred By</th><th class="px-3 py-2">Referral Date</th>
                            <th class="px-3 py-2">Notes</th><th class="px-3 py-2">Response Date</th>
                            <th class="px-3 py-2">Status</th><th class="px-3 py-2">Action</th>
                        </tr></thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </div>

        {{-- Physical Planning Tab --}}
        <div id="tab-physical_planning" class="tab-content hidden">
            <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
                <div class="px-5 py-4 border-b border-gray-100"><h2 class="text-sm font-semibold text-gray-700">Physical Planning / KAMMA Referrals</h2></div>
                <div class="p-4">
                    <table id="table-physical_planning" class="w-full text-sm" style="width:100%">
                        <thead><tr class="text-left text-xs text-gray-500 uppercase">
                            <th class="px-3 py-2">#</th><th class="px-3 py-2">File No</th><th class="px-3 py-2">Owner</th>
                            <th class="px-3 py-2">Referred By</th><th class="px-3 py-2">Referral Date</th>
                            <th class="px-3 py-2">Notes</th><th class="px-3 py-2">Response Date</th>
                            <th class="px-3 py-2">Status</th><th class="px-3 py-2">Action</th>
                        </tr></thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </div>

        {{-- Survey Tab --}}
        <div id="tab-survey" class="tab-content hidden">
            <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
                <div class="px-5 py-4 border-b border-gray-100"><h2 class="text-sm font-semibold text-gray-700">Survey Referrals</h2></div>
                <div class="p-4">
                    <table id="table-survey" class="w-full text-sm" style="width:100%">
                        <thead><tr class="text-left text-xs text-gray-500 uppercase">
                            <th class="px-3 py-2">#</th><th class="px-3 py-2">File No</th><th class="px-3 py-2">Owner</th>
                            <th class="px-3 py-2">Referred By</th><th class="px-3 py-2">Referral Date</th>
                            <th class="px-3 py-2">Notes</th><th class="px-3 py-2">Response Date</th>
                            <th class="px-3 py-2">Status</th><th class="px-3 py-2">Action</th>
                        </tr></thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Refer Case Modal --}}
<div id="modal-refer" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/40">
    <div class="bg-white rounded-2xl shadow-xl w-full max-w-lg mx-4">
        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
            <h3 class="text-base font-semibold text-gray-800">Refer Case to Department</h3>
            <button class="modal-close text-gray-400 hover:text-gray-600"><i data-lucide="x" class="h-5 w-5"></i></button>
        </div>
        <form id="form-refer" class="p-6 space-y-4">
            @csrf
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="sm:col-span-2">
                    <label class="block text-xs font-medium text-gray-600 mb-1">Application <span class="text-red-500">*</span></label>
                    <select name="spa_application_id" id="refer-app-select" required
                        class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm outline-none focus:border-[rgb(186,191,12)]">
                        <option value="">Select application…</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">File Number</label>
                    <input type="text" name="file_number" id="refer-file-no" readonly
                        class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm bg-gray-50 outline-none">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Department <span class="text-red-500">*</span></label>
                    <select name="department" required
                        class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm outline-none focus:border-[rgb(186,191,12)]">
                        <option value="">Select…</option>
                        <option value="cadastral">Cadastral</option>
                        <option value="physical_planning">Physical Planning / KAMMA</option>
                        <option value="survey">Survey</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Referral Date <span class="text-red-500">*</span></label>
                    <input type="date" name="referral_date" required
                        class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm outline-none focus:border-[rgb(186,191,12)]">
                </div>
                <div class="sm:col-span-2">
                    <label class="block text-xs font-medium text-gray-600 mb-1">Notes</label>
                    <textarea name="notes" rows="3"
                        class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm outline-none resize-none focus:border-[rgb(186,191,12)]"
                        placeholder="Describe the reason for referral…"></textarea>
                </div>
            </div>
            <div class="flex justify-end gap-3 pt-2">
                <button type="button" class="modal-close px-4 py-2 text-sm text-gray-600 border border-gray-200 rounded-lg hover:bg-gray-50">Cancel</button>
                <button type="submit" class="px-5 py-2 text-sm text-white bg-[rgb(186,191,12)] rounded-lg hover:opacity-90">Submit Referral</button>
            </div>
        </form>
    </div>
</div>

{{-- Record Response Modal --}}
<div id="modal-response" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/40">
    <div class="bg-white rounded-2xl shadow-xl w-full max-w-lg mx-4">
        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
            <h3 class="text-base font-semibold text-gray-800">Record Department Response</h3>
            <button class="resp-close text-gray-400 hover:text-gray-600"><i data-lucide="x" class="h-5 w-5"></i></button>
        </div>
        <form id="form-response" class="p-6 space-y-4">
            @csrf
            <input type="hidden" name="referral_id" id="resp-id">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Response Date <span class="text-red-500">*</span></label>
                    <input type="date" name="response_date" required
                        class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm outline-none focus:border-[rgb(186,191,12)]">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Status <span class="text-red-500">*</span></label>
                    <select name="status" required
                        class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm outline-none focus:border-[rgb(186,191,12)]">
                        <option value="responded">Responded</option>
                        <option value="approved">Approved</option>
                        <option value="rejected">Rejected</option>
                    </select>
                </div>
                <div class="sm:col-span-2">
                    <label class="block text-xs font-medium text-gray-600 mb-1">Response Notes</label>
                    <textarea name="response_notes" rows="3"
                        class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm outline-none resize-none focus:border-[rgb(186,191,12)]"
                        placeholder="Department's response / findings…"></textarea>
                </div>
            </div>
            <div class="flex justify-end gap-3 pt-2">
                <button type="button" class="resp-close px-4 py-2 text-sm text-gray-600 border border-gray-200 rounded-lg hover:bg-gray-50">Cancel</button>
                <button type="submit" class="px-5 py-2 text-sm text-white bg-[rgb(186,191,12)] rounded-lg hover:opacity-90">Save Response</button>
            </div>
        </form>
    </div>
</div>

<style>
    .tab-btn.active { background:white; color:#1f2937; box-shadow:0 1px 3px rgba(0,0,0,.1); }
    table.dataTable thead th { background:#f9fafb; font-weight:600; }
    .dataTables_wrapper .dataTables_filter input { border:1px solid #e5e7eb; border-radius:.5rem; padding:.35rem .75rem; font-size:.85rem; }
    .dataTables_wrapper .dataTables_length select { border:1px solid #e5e7eb; border-radius:.5rem; padding:.25rem .5rem; }
</style>

<script>
const CSRF       = '{{ csrf_token() }}';
const STORE_REF  = '{{ route("special-assignment.departments.store") }}';
const BASE_URL   = '{{ url("special-assignment/other-departments") }}';
const APP_AJAX   = '{{ route("special-assignment.land-records") }}';

const STATUS_MAP = {
    pending  : 'bg-amber-100 text-amber-700',
    responded: 'bg-blue-100 text-blue-700',
    approved : 'bg-green-100 text-green-700',
    rejected : 'bg-red-100 text-red-700',
};

const COLS = [
    { data:'DT_RowIndex', orderable:false, searchable:false },
    { data:'file_number', render: d => `<span style="white-space:nowrap">${d||'—'}</span>` },
    { data:'owner_name' },
    { data:'referred_by' },
    { data:'referral_date' },
    { data:'notes', render: d => d ? `<span title="${d}">${d.length>45?d.slice(0,45)+'…':d}</span>` : '—' },
    { data:'response_date', render: d => d||'—' },
    { data:'status', render: d => `<span class="px-2 py-0.5 rounded-full text-xs font-medium ${STATUS_MAP[d]||'bg-gray-100 text-gray-600'}">${d||'—'}</span>` },
    { data:'action', orderable:false, searchable:false },
];

$(document).ready(function () {
    const tables = {};

    function initTable(dept) {
        if (tables[dept]) return;
        tables[dept] = $(`#table-${dept}`).DataTable({
            processing: true, serverSide: true, scrollX: true,
            ajax: { url: window.location.href, headers:{'X-Requested-With':'XMLHttpRequest'}, data: d => ({ ...d, ajax:1, department: dept }) },
            columns: COLS,
        });
    }

    // Hash → tab key mapping
    const HASH_MAP = { '#cadastral': 'cadastral', '#physical-planning': 'physical_planning', '#survey': 'survey' };

    function activateTab(tabKey) {
        document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
        document.querySelectorAll('.tab-content').forEach(p => p.classList.add('hidden'));
        const btn = document.querySelector(`.tab-btn[data-tab="${tabKey}"]`);
        if (btn) btn.classList.add('active');
        const content = document.getElementById('tab-' + tabKey);
        if (content) content.classList.remove('hidden');
        initTable(tabKey);
        setTimeout(() => $.fn.DataTable.tables({ visible:true, api:true }).columns.adjust(), 50);
    }

    // Activate tab from URL hash on load
    const initTab = HASH_MAP[window.location.hash] || 'cadastral';
    activateTab(initTab);

    // Listen for hash changes (sidebar link clicks)
    window.addEventListener('hashchange', () => {
        const tab = HASH_MAP[window.location.hash];
        if (tab) activateTab(tab);
    });

    document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            const tab = btn.dataset.tab;
            const hashKey = Object.keys(HASH_MAP).find(k => HASH_MAP[k] === tab) || '#' + tab;
            history.replaceState(null, '', hashKey);
            activateTab(tab);
        });
    });

    // Dropdown toggle
    $('body').on('click', '.btn-dep-toggle', function (e) {
        e.stopPropagation();
        const dd = $(this).next('.dep-dropdown');
        $('.dep-dropdown').not(dd).addClass('hidden');
        dd.toggleClass('hidden');
    });
    $(document).on('click', () => $('.dep-dropdown').addClass('hidden'));

    // Record response (delegated)
    $('body').on('click', '.btn-record-response', function () {
        document.getElementById('resp-id').value = $(this).data('id');
        const m = document.getElementById('modal-response');
        m.classList.remove('hidden'); m.classList.add('flex');
    });

    // Delete referral
    $('body').on('click', '.btn-dep-delete', function () {
        const id   = $(this).data('id');
        const file = $(this).data('file');
        Swal.fire({
            icon:'warning', title:'Delete Referral?',
            text:`Remove the referral for ${file||'this record'}?`,
            showCancelButton:true, confirmButtonText:'Yes, Delete', confirmButtonColor:'#ef4444',
        }).then(async r => {
            if (!r.isConfirmed) return;
            const res  = await fetch(`${BASE_URL}/${id}/delete`, { method:'POST', headers:{'X-CSRF-TOKEN':CSRF,'Content-Type':'application/json'}, body:'{}' });
            const data = await res.json();
            if (data.success) {
                Object.values(tables).forEach(t => t.ajax.reload());
                Swal.fire({ icon:'success', title:'Deleted', text:data.message, timer:2000, showConfirmButton:false });
            } else {
                Swal.fire({ icon:'error', title:'Error', text:data.message||'Delete failed.' });
            }
        });
    });

    // Modal open/close
    const modal = document.getElementById('modal-refer');
    document.getElementById('btn-refer').addEventListener('click', () => { modal.classList.remove('hidden'); modal.classList.add('flex'); });
    document.querySelectorAll('.modal-close').forEach(b => b.addEventListener('click', () => { modal.classList.add('hidden'); modal.classList.remove('flex'); }));
    const modalResp = document.getElementById('modal-response');
    document.querySelectorAll('.resp-close').forEach(b => b.addEventListener('click', () => { modalResp.classList.add('hidden'); modalResp.classList.remove('flex'); }));

    // Load applications
    fetch(APP_AJAX + '?ajax=1&length=500&start=0').then(r=>r.json()).then(d=>{
        const sel = document.getElementById('refer-app-select');
        (d.data||[]).forEach(a => {
            const opt = document.createElement('option');
            opt.value = a.id;
            opt.dataset.file = a.file_number;
            opt.textContent = `${a.file_number} – ${a.owner_name}`;
            sel.appendChild(opt);
        });
        sel.addEventListener('change', function() {
            const opt = this.options[this.selectedIndex];
            document.getElementById('refer-file-no').value = opt.dataset.file || '';
        });
    }).catch(()=>{});

    // Submit referral
    document.getElementById('form-refer').addEventListener('submit', async function(e) {
        e.preventDefault();
        const btn = this.querySelector('[type=submit]');
        btn.disabled = true; btn.textContent = 'Saving…';
        const res  = await fetch(STORE_REF, { method:'POST', headers:{'X-CSRF-TOKEN':CSRF,'Content-Type':'application/json'}, body: JSON.stringify(Object.fromEntries(new FormData(this))) });
        const data = await res.json();
        if (data.success) {
            modal.classList.add('hidden'); modal.classList.remove('flex');
            this.reset();
            Object.values(tables).forEach(t => t.ajax.reload());
            Swal.fire({ icon:'success', title:'Referred', text:data.message, timer:2000, showConfirmButton:false });
        } else {
            Swal.fire({ icon:'error', title:'Error', text:data.message||'Save failed.' });
        }
        btn.disabled=false; btn.textContent='Submit Referral';
    });

    // Submit response
    document.getElementById('form-response').addEventListener('submit', async function(e) {
        e.preventDefault();
        const btn = this.querySelector('[type=submit]');
        btn.disabled = true; btn.textContent = 'Saving…';
        const body = Object.fromEntries(new FormData(this));
        const id   = body.referral_id;
        const res  = await fetch(`${BASE_URL}/${id}/response`, { method:'POST', headers:{'X-CSRF-TOKEN':CSRF,'Content-Type':'application/json'}, body: JSON.stringify(body) });
        const data = await res.json();
        if (data.success) {
            modalResp.classList.add('hidden'); modalResp.classList.remove('flex');
            this.reset();
            Object.values(tables).forEach(t => t.ajax.reload());
            Swal.fire({ icon:'success', title:'Saved', text:data.message, timer:2000, showConfirmButton:false });
        } else {
            Swal.fire({ icon:'error', title:'Error', text:data.message||'Save failed.' });
        }
        btn.disabled=false; btn.textContent='Save Response';
    });
});
</script>
@endsection
