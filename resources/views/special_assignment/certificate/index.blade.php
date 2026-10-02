@extends('layouts.app')
@section('page-title'){{ $PageTitle ?? 'Special Assignment – Change of Purpose Sheet' }}@endsection

@section('content')
<div class="flex-1 overflow-auto">
    @include('admin.header')
    <div class="p-6">
        <div class="flex items-center justify-between mb-6">
            <div>
                <h1 class="text-2xl font-bold text-gray-800">Change of Purpose Sheet</h1>
                <p class="text-sm text-gray-500 mt-1">{{ $PageDescription ?? '' }}</p>
            </div>
            <button id="btn-issue-cert"
                class="inline-flex items-center gap-2 px-4 py-2 bg-[rgb(186,191,12)] hover:opacity-90 text-white text-sm font-medium rounded-lg">
                <i data-lucide="file-badge" class="h-4 w-4"></i> Issue Sheet
            </button>
        </div>

        {{-- Stats --}}
        <div class="grid grid-cols-1 sm:grid-cols-4 gap-4 mb-6">
            <div class="bg-white rounded-xl border border-gray-200 p-4 flex items-center gap-4">
                <div class="p-3 rounded-full" style="background:rgba(186,191,12,.12)"><i data-lucide="file-badge" class="h-5 w-5 text-[rgb(186,191,12)]"></i></div>
                <div><p class="text-xs text-gray-500">Total</p><p class="text-xl font-bold text-gray-800">{{ $stats['total'] }}</p></div>
            </div>
            <div class="bg-white rounded-xl border border-gray-200 p-4 flex items-center gap-4">
                <div class="p-3 rounded-full bg-green-50"><i data-lucide="check-circle" class="h-5 w-5 text-green-600"></i></div>
                <div><p class="text-xs text-gray-500">Issued</p><p class="text-xl font-bold text-gray-800">{{ $stats['issued'] }}</p></div>
            </div>
            <div class="bg-white rounded-xl border border-gray-200 p-4 flex items-center gap-4">
                <div class="p-3 rounded-full bg-blue-50"><i data-lucide="inbox" class="h-5 w-5 text-blue-500"></i></div>
                <div><p class="text-xs text-gray-500">Collected</p><p class="text-xl font-bold text-gray-800">{{ $stats['collected'] }}</p></div>
            </div>
            <div class="bg-white rounded-xl border border-gray-200 p-4 flex items-center gap-4">
                <div class="p-3 rounded-full bg-red-50"><i data-lucide="x-circle" class="h-5 w-5 text-red-500"></i></div>
                <div><p class="text-xs text-gray-500">Revoked</p><p class="text-xl font-bold text-gray-800">{{ $stats['revoked'] }}</p></div>
            </div>
        </div>

        {{-- DataTable --}}
        <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-100"><h2 class="text-sm font-semibold text-gray-700">Change of Purpose Sheet Registry</h2></div>
            <div class="p-4">
                <table id="certificate-table" class="w-full text-sm" style="width:100%">
                    <thead>
                        <tr class="text-left text-xs text-gray-500 uppercase">
                            <th class="px-3 py-2">#</th>
                            <th class="px-3 py-2">Sheet No</th>
                            <th class="px-3 py-2">File No</th>
                            <th class="px-3 py-2">Holder Name</th>
                            <th class="px-3 py-2">From Use</th>
                            <th class="px-3 py-2">To Use</th>
                            <th class="px-3 py-2">Issue Date</th>
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

{{-- Issue Change of Purpose Sheet Modal --}}
<div id="modal-issue-cert" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/40">
    <div class="bg-white rounded-2xl shadow-xl w-full max-w-lg mx-4">
        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
            <h3 class="text-base font-semibold text-gray-800">Issue Change of Purpose Sheet</h3>
            <button class="modal-close text-gray-400 hover:text-gray-600"><i data-lucide="x" class="h-5 w-5"></i></button>
        </div>
        <form id="form-issue-cert" class="p-6 space-y-4">
            @csrf
            <div class="p-3 bg-amber-50 rounded-lg text-xs text-amber-700 border border-amber-200">
                <i data-lucide="info" class="h-3.5 w-3.5 inline mr-1"></i>
                A Change of Purpose Sheet can only be issued for applications with an approved Commissioner memo.
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="sm:col-span-2">
                    <label class="block text-xs font-medium text-gray-600 mb-1">Application <span class="text-red-500">*</span></label>
                    {{-- Every file on the Field Data page, rendered here rather
                         than fetched: the list is small and the page already
                         knows it, and the old fetch quietly filtered to
                         status = 'approved', which hid files whose memo was
                         approved but whose application status had not caught up.
                         Files without an approved memo are marked — they are
                         visible so they can be found, and the server still
                         refuses to issue a sheet for them. --}}
                    <select name="spa_application_id" id="cert-app-select" required
                        class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm outline-none focus:border-[rgb(186,191,12)]">
                        <option value="">Select application…</option>
                        @foreach ($certApplications as $a)
                            <option value="{{ $a->id }}"
                                data-owner="{{ $a->owner_name }}"
                                data-land-use="{{ $a->land_use_type }}"
                                data-existing-use="{{ $a->existing_use }}"
                                data-approved="{{ $a->has_approved_memo ? '1' : '0' }}">
                                {{ $a->file_number }} – {{ $a->owner_name }}{{ $a->has_approved_memo ? '' : '  (no approved memo)' }}
                            </option>
                        @endforeach
                    </select>
                    <p id="cert-app-warning" class="text-[11px] text-red-500 mt-1 hidden">
                        This file has no approved Commissioner memo, so a sheet cannot be issued for it yet.
                    </p>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Holder Name <span class="text-red-500">*</span></label>
                    <input type="text" name="holder_name" id="cert-holder" required
                        class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm outline-none focus:border-[rgb(186,191,12)]">
                </div>
                <div class="sm:col-span-2">
                    <label class="block text-xs font-medium text-gray-600 mb-1">New File No <span class="text-red-500">*</span></label>
                    <select name="new_file_number" id="cert-new-file" required
                        class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm outline-none focus:border-[rgb(186,191,12)] bg-white">
                        <option value="">Select new file number…</option>
                    </select>
                    <p class="text-[11px] text-gray-400 mt-1">The file number assigned after the change of purpose.</p>
                </div>
                {{-- The two land uses sit side by side: the change of purpose IS
                     the move from one to the other, so they read as a pair. --}}
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">From Land Use (Approved) <span class="text-red-500">*</span></label>
                    {{-- Backfilled from the selected application, so it is not
                         typed here. readonly rather than disabled on purpose: a
                         disabled field is left out of the submission entirely,
                         and from_use is required — the sheet would fail to issue
                         with a validation error about a field nobody can edit. --}}
                    <input type="text" name="from_use" id="cert-from-use" required readonly tabindex="-1"
                        placeholder="Select an application above"
                        class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm outline-none bg-gray-100 text-gray-500 cursor-not-allowed">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">To Use (New Purpose) <span class="text-red-500">*</span></label>
                    <select name="to_use" required
                        class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm outline-none focus:border-[rgb(186,191,12)] bg-white">
                        <option value="">Select new purpose…</option>
                        @foreach($landUseTypes as $lu)
                            <option value="{{ $lu }}">{{ $lu }}</option>
                        @endforeach
                    </select>
                    <p id="cert-same-purpose" class="text-[11px] text-red-500 mt-1 hidden">
                        Same as the approved land use — there is no change of purpose to issue.
                    </p>
                </div>

                {{-- Dates together, below the pair they qualify. --}}
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Issue Date <span class="text-red-500">*</span></label>
                    <input type="date" name="issue_date" required
                        class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm outline-none focus:border-[rgb(186,191,12)]">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Expiry Date (optional)</label>
                    <input type="date" name="expiry_date"
                        class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm outline-none focus:border-[rgb(186,191,12)]">
                </div>
            </div>
            <div class="flex justify-end gap-3 pt-2">
                <button type="button" class="modal-close px-4 py-2 text-sm text-gray-600 border border-gray-200 rounded-lg hover:bg-gray-50">Cancel</button>
                <button type="submit" class="px-5 py-2 text-sm text-white bg-[rgb(186,191,12)] rounded-lg hover:opacity-90">Issue Sheet</button>
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
const CSRF      = '{{ csrf_token() }}';
const STORE_CERT= '{{ route("special-assignment.certificate.issue") }}';
const MLS_FILES = '{{ route("api.get-existing-mls-files") }}';
const PRINT_BASE= '{{ url("special-assignment/certificate") }}';

const STATUS_COLORS = {
    draft    : 'bg-gray-100 text-gray-600',
    issued   : 'bg-green-100 text-green-700',
    collected: 'bg-blue-100 text-blue-700',
    revoked  : 'bg-red-100 text-red-700',
};

$(document).ready(function () {
    const table = $('#certificate-table').DataTable({
        processing: true, serverSide: true,
        ajax: { url: window.location.href, data: d => ({ ...d, ajax:1 }) },
        columns: [
            { data:'DT_RowIndex', orderable:false, searchable:false },
            { data:'cert_number' },
            { data:'file_number' },
            { data:'holder_name' },
            { data:'from_use' },
            { data:'to_use' },
            { data:'issue_date' },
            { data:'status', render: d => `<span class="px-2 py-0.5 rounded-full text-xs font-medium ${STATUS_COLORS[d]||'bg-gray-100 text-gray-600'}">${d||'—'}</span>` },
            { data:'action', orderable:false, searchable:false },
        ],
    });

    // Action menu toggle (delegated)
    $('#certificate-table').on('click', '.btn-cert-toggle', function (e) {
        e.stopPropagation();
        const dd = $(this).next('.cert-dropdown');
        $('.cert-dropdown').not(dd).addClass('hidden');
        dd.toggleClass('hidden');
    });
    $(document).on('click', () => $('.cert-dropdown').addClass('hidden'));

    // Print button (delegated)
    $('#certificate-table').on('click', '.btn-print-cert', function () {
        $('.cert-dropdown').addClass('hidden');
        window.open(`${PRINT_BASE}/${$(this).data('id')}/print`, '_blank');
    });

    // Modal
    const modal = document.getElementById('modal-issue-cert');
    document.getElementById('btn-issue-cert').addEventListener('click', () => { modal.classList.remove('hidden'); modal.classList.add('flex'); });
    document.querySelectorAll('.modal-close').forEach(b => b.addEventListener('click', () => { modal.classList.add('hidden'); modal.classList.remove('flex'); }));

    // The application list is rendered server-side (see the select markup), so
    // there is nothing to fetch — only the follow-on fields to fill in.
    document.getElementById('cert-app-select').addEventListener('change', function () {
        const opt = this.options[this.selectedIndex];
        document.getElementById('cert-holder').value   = opt.dataset.owner || '';
        document.getElementById('cert-from-use').value = opt.dataset.landUse || '';

        // Say why an issue will fail before the officer fills the rest of the
        // form, rather than after they press Issue Sheet.
        document.getElementById('cert-app-warning')
            .classList.toggle('hidden', !this.value || opt.dataset.approved === '1');

        // Changing the application changes From Use, so a purpose picked
        // earlier may now clash with it.
        checkPurposeChanged();
    });

    // A change of purpose that does not change the purpose is not a change of
    // purpose. Flagged as soon as it is chosen rather than on submit, so the
    // officer is not told after filling in the dates.
    const toUseSelect  = document.querySelector('select[name="to_use"]');
    const samePurposeMsg = document.getElementById('cert-same-purpose');

    function purposesClash() {
        const from = (document.getElementById('cert-from-use').value || '').trim().toUpperCase();
        const to   = (toUseSelect.value || '').trim().toUpperCase();
        return from !== '' && to !== '' && from === to;
    }

    function checkPurposeChanged() {
        const clash = purposesClash();
        samePurposeMsg.classList.toggle('hidden', !clash);
        toUseSelect.classList.toggle('border-red-400', clash);
        return !clash;
    }

    toUseSelect.addEventListener('change', checkPurposeChanged);

    // Load existing MLS file numbers into the New File No select
    fetch(MLS_FILES).then(r=>r.json()).then(d=>{
        const sel = document.getElementById('cert-new-file');
        (d.files||[]).forEach(f => {
            const fno = f.file_number || f.mlsFNo;
            if (!fno) return;
            const opt = document.createElement('option');
            opt.value = fno;
            opt.textContent = fno;
            sel.appendChild(opt);
        });
    }).catch(()=>{});

    // Form submit
    document.getElementById('form-issue-cert').addEventListener('submit', async function(e) {
        e.preventDefault();
        const btn = this.querySelector('[type=submit]');
        const body = Object.fromEntries(new FormData(this));

        // Guard: must have a real (numeric) SPAS application selected
        if (!body.spa_application_id || body.spa_application_id === 'null') {
            Swal.fire({ icon:'warning', title:'Select an application', text:'Please choose an approved Special Assignment application before issuing a certificate.' });
            return;
        }

        // Guard: the sheet records a move from one use to another. Issuing one
        // where both are the same would put a conversion that never happened on
        // the file, and flip the application to "certificate issued" on the
        // strength of it. The server refuses this too.
        if (!checkPurposeChanged()) {
            Swal.fire({
                icon: 'warning',
                title: 'No change of purpose',
                text: `The new purpose is the same as the approved land use (${body.from_use}). Choose a different purpose, or there is nothing to issue.`
            });
            return;
        }

        btn.disabled = true; btn.textContent = 'Issuing…';
        try {
            const res  = await fetch(STORE_CERT, { method:'POST', headers:{'X-CSRF-TOKEN':CSRF,'Content-Type':'application/json','Accept':'application/json'}, body: JSON.stringify(body) });
            let data = {};
            try { data = await res.json(); } catch (_) { data = {}; }

            if (res.ok && data.success) {
                modal.classList.add('hidden'); modal.classList.remove('flex');
                this.reset();
                table.ajax.reload();
                Swal.fire({ icon:'success', title:'Sheet Issued', text:`${data.cert_number} has been issued.`, timer:3000, showConfirmButton:false });
            } else {
                const errMsg = data.message
                    || (data.errors ? Object.values(data.errors).flat().join('\n') : '')
                    || 'Could not issue certificate.';
                Swal.fire({ icon:'error', title:'Error', text: errMsg });
            }
        } catch (err) {
            Swal.fire({ icon:'error', title:'Error', text:'Network error. Please try again.' });
        } finally {
            btn.disabled = false; btn.textContent = 'Issue Sheet';
        }
    });
});
</script>
@endsection
