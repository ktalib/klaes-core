@extends('layouts.app')
@section('page-title'){{ $PageTitle ?? 'Special Assignment – Land Records' }}@endsection

@section('content')
<div class="flex-1 overflow-auto">
    @include('admin.header')
    <div class="p-6">

        {{-- Page Header --}}
        <div class="flex items-center justify-between mb-6">
            <div>
                <h1 class="text-2xl font-bold text-gray-800">Land Records</h1>
                <p class="text-sm text-gray-500 mt-1">{{ $PageDescription ?? '' }}</p>
            </div>
            <div class="flex items-center gap-2">
                <button id="btn-field-template"
                    class="inline-flex items-center gap-2 px-4 py-2 border border-gray-200 hover:bg-gray-50 text-gray-700 text-sm font-medium rounded-lg transition-colors">
                    <i data-lucide="download" class="h-4 w-4"></i> Field Template
                </button>
            </div>
        </div>

        {{-- Stats --}}
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
            <div class="bg-white rounded-xl border border-gray-200 p-4 flex items-center gap-4">
                <div class="p-3 rounded-full" style="background:rgba(186,191,12,.12)">
                    <i data-lucide="land-plot" class="h-5 w-5 text-[rgb(186,191,12)]"></i>
                </div>
                <div><p class="text-xs text-gray-500">Total Records</p><p class="text-xl font-bold text-gray-800">{{ $stats['total'] }}</p></div>
            </div>
            <div class="bg-white rounded-xl border border-gray-200 p-4 flex items-center gap-4">
                <div class="p-3 rounded-full bg-blue-50"><i data-lucide="clock" class="h-5 w-5 text-blue-500"></i></div>
                <div><p class="text-xs text-gray-500">Open</p><p class="text-xl font-bold text-gray-800">{{ $stats['open'] }}</p></div>
            </div>
            <div class="bg-white rounded-xl border border-gray-200 p-4 flex items-center gap-4">
                <div class="p-3 rounded-full bg-amber-50"><i data-lucide="refresh-cw" class="h-5 w-5 text-amber-500"></i></div>
                <div><p class="text-xs text-gray-500">In Progress</p><p class="text-xl font-bold text-gray-800">{{ $stats['in_progress'] }}</p></div>
            </div>
        </div>

        {{-- DataTable --}}
        <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between gap-4 flex-wrap">
                <h2 class="text-sm font-semibold text-gray-700">All Land Records</h2>
                {{-- Registry filter: MLS, KANGIS, SLTR and ST files all live in
                     file_indexings, so this narrows the same list rather than
                     switching data sources. --}}
                <div class="flex items-center gap-2">
                    <label for="registry-filter" class="text-xs font-medium text-gray-500">Registry</label>
                    <select id="registry-filter" class="border border-gray-200 rounded-lg px-3 py-1.5 text-sm outline-none focus:border-[rgb(186,191,12)]">
                        <option value="">All registries</option>
                        <option value="MLS">MLS / Lands</option>
                        <option value="KANGIS">KANGIS</option>
                        <option value="SLTR">SLTR</option>
                        <option value="ST">ST (Sectional Titling)</option>
                    </select>
                </div>
            </div>
            <div class="p-4">
                <table id="land-records-table" class="w-full text-sm" style="width:100%">
                    <thead>
                        <tr class="text-left text-xs text-gray-500 uppercase tracking-wide">
                            <th class="px-3 py-2">#</th>
                            <th class="px-3 py-2">Registry</th>
                            <th class="px-3 py-2">File No</th>
                            <th class="px-3 py-2">Owner Name</th>
                            <th class="px-3 py-2">Phone</th>
                            <th class="px-3 py-2">Location</th>
                            <th class="px-3 py-2">Applied Land Use</th>
                            <th class="px-3 py-2">Prevailing Land Use</th>
                            <th class="px-3 py-2">Contravention</th>
                            <th class="px-3 py-2">SPAS Status</th>
                            <th class="px-3 py-2">Date</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>
</div>

{{-- Add Record Modal --}}
<div id="modal-add-record" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/40">
    <div class="bg-white rounded-2xl shadow-xl w-full max-w-2xl mx-4 overflow-hidden">
        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
            <h3 class="text-base font-semibold text-gray-800">Add Land Record</h3>
            <button id="btn-close-modal" class="text-gray-400 hover:text-gray-600"><i data-lucide="x" class="h-5 w-5"></i></button>
        </div>
        <form id="form-add-record" class="p-6 space-y-4" enctype="multipart/form-data">
            @csrf

            {{-- File Number picker --}}
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">File Number <span class="text-red-500">*</span></label>
                <div class="flex gap-2">
                    <input type="text" id="display-file-number" readonly placeholder="No file number selected"
                        class="flex-1 border border-gray-200 rounded-lg px-3 py-2 text-sm bg-gray-50 text-gray-700 cursor-default outline-none">
                    <button type="button" id="btn-pick-fileno"
                        class="inline-flex items-center gap-1.5 px-4 py-2 bg-[rgb(186,191,12)] hover:opacity-90 text-white text-sm font-medium rounded-lg transition-opacity">
                        <i data-lucide="search" class="h-4 w-4"></i> Select
                    </button>
                </div>
                <p id="lookup-msg" class="text-xs mt-1 hidden"></p>
            </div>

            {{-- Owner / File Title – readonly, filled from file_title --}}
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Owner / File Title <span class="text-red-500">*</span></label>
                <input type="text" name="owner_name" id="f-owner_name" required
                    placeholder="Auto-filled from file number — type if not on record"
                    class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm text-gray-700 outline-none focus:border-[rgb(186,191,12)]">
            </div>

            {{-- Location badge (shown after file lookup) --}}
            <div id="location-badge-wrap" class="hidden">
                <div class="flex items-start gap-2 px-3 py-2 bg-blue-50 border border-blue-100 rounded-lg text-xs text-blue-800">
                    <i data-lucide="map-pin" class="h-3.5 w-3.5 mt-0.5 shrink-0 text-blue-500"></i>
                    <span id="location-badge-text" class="leading-relaxed"></span>
                </div>
            </div>

            {{-- Hidden fields --}}
            <input type="hidden" name="file_number"      id="h-file_number">
            <input type="hidden" name="file_indexing_id" id="h-file_indexing_id">
            <input type="hidden" name="tracking_id"      id="h-tracking_id">
            <input type="hidden" name="is_indexed"       id="h-is_indexed" value="0">
            <input type="hidden" name="location"         id="h-location">
            <input type="hidden" name="lga"              id="h-lga">

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                {{-- Applied Land Use – readonly auto-fill --}}
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Applied Land Use</label>
                    <input type="text" name="land_use_type" id="f-land_use_type" readonly
                        class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm bg-gray-50 outline-none"
                        placeholder="Auto-filled from file">
                </div>

                {{-- Phone --}}
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Phone</label>
                    <input type="text" name="phone" id="f-phone"
                        class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm outline-none focus:border-[rgb(186,191,12)]">
                </div>


                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Prevailing Land Use <span class="text-red-500">*</span></label>
                    <select name="existing_use" id="f-existing_use" required
                        class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm outline-none focus:border-[rgb(186,191,12)]">
                        <option value="">Select…</option>
                        @foreach($landUseTypes as $lut)
                            <option value="{{ $lut }}">{{ $lut }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Contravention badge – same row as Prevailing Land Use --}}
                <div class="flex items-end justify-center">
                    <div id="contravention-badge" class="hidden items-center gap-2 px-6 py-2 rounded-lg bg-red-100 text-red-700 border-2 border-red-400" style="font-size:15px;font-weight:900;letter-spacing:.07em;animation:contraventionPulse 1.2s ease-in-out infinite;">
                        <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v4m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/></svg>
                         CONTRAVENTION
                    </div>
                </div>

            </div>

            {{-- Property Photos --}}
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Property Photos</label>
                <label for="f-photos" class="flex flex-col items-center justify-center w-full h-24 border-2 border-dashed border-gray-200 rounded-lg cursor-pointer hover:border-[rgb(186,191,12)] hover:bg-[rgba(186,191,12,0.03)] transition-colors">
                    <div class="flex flex-col items-center gap-1 pointer-events-none">
                        <i data-lucide="image-plus" class="h-6 w-6 text-gray-400"></i>
                        <span class="text-xs text-gray-400">Click to upload photos <span class="text-gray-300">(JPG, PNG — multiple allowed)</span></span>
                    </div>
                    <input type="file" id="f-photos" name="photos[]" multiple accept="image/*" class="hidden">
                </label>
                <div id="photo-preview" class="flex flex-wrap gap-2 mt-2"></div>
            </div>

            <div class="flex justify-end gap-3 pt-2">
                <button type="button" id="btn-cancel-modal" class="px-4 py-2 text-sm text-gray-600 border border-gray-200 rounded-lg hover:bg-gray-50">Cancel</button>
                <button type="submit" id="btn-save-record" class="px-5 py-2 text-sm text-white bg-[rgb(186,191,12)] rounded-lg hover:opacity-90">Save Record</button>
            </div>
        </form>
    </div>
</div>

{{-- Global File Number Modal --}}
@include('components.global-fileno-modal')

<style>
    table.dataTable thead th { background:#f9fafb; font-weight:600; }
    table.dataTable tbody tr:hover { background:#fafaf0; }
    .dataTables_wrapper .dataTables_filter input { border:1px solid #e5e7eb; border-radius:.5rem; padding:.35rem .75rem; font-size:.85rem; }
    .dataTables_wrapper .dataTables_length select { border:1px solid #e5e7eb; border-radius:.5rem; padding:.25rem .5rem; }
    @keyframes contraventionPulse {
        0%, 100% { background-color:#fee2e2; border-color:#fca5a5; transform:scale(1);   box-shadow:none; }
        50%       { background-color:#fecaca; border-color:#ef4444; transform:scale(1.04); box-shadow:0 0 10px rgba(239,68,68,.35); }
    }
</style>

<script src="{{ asset('js/global-fileno-modal.js') }}"></script>
<script>
const CSRF   = '{{ csrf_token() }}';
const STORE  = '{{ route("special-assignment.land-records.store") }}';
const LOOKUP = '{{ route("special-assignment.check-file") }}';

function luBadge(d) {
    const m = { Residential:'bg-blue-100 text-blue-700', Commercial:'bg-orange-100 text-orange-700', Industrial:'bg-purple-100 text-purple-700', Agricultural:'bg-green-100 text-green-700' };
    const k = d ? Object.keys(m).find(k => d.toLowerCase().includes(k.toLowerCase())) : null;
    const cls = (k && m[k]) || 'bg-gray-100 text-gray-600';
    return d ? `<span class="px-2 py-0.5 rounded-full text-xs font-medium ${cls}">${d}</span>` : '—';
}

$(document).ready(function () {
    // ── DataTable ──────────────────────────────────────────────────────────
    const REGISTRY_CLS = {
        MLPP:    'bg-slate-100 text-slate-700',
        KANGIS: 'bg-indigo-100 text-indigo-700',
        SLTR:   'bg-teal-100 text-teal-700',
        ST:     'bg-fuchsia-100 text-fuchsia-700',
    };

    const table = $('#land-records-table').DataTable({
        processing : true,
        serverSide : true,
        scrollX    : true,
        pageLength : 25,
        lengthMenu : [[10, 25, 50, 100], [10, 25, 50, 100]],
        ajax       : {
            url: window.location.href,
            type: 'GET',
            data: d => ({ ...d, ajax: 1, registry: $('#registry-filter').val() || '' }),
        },
        columns: [
            { data: 'DT_RowIndex', orderable: false, searchable: false },
            { data: 'registry', orderable: false, searchable: false, render: d => {
                const key = (d || 'MLS').toUpperCase();
                return `<span class="px-2 py-0.5 rounded-full text-xs font-semibold ${REGISTRY_CLS[key] || 'bg-gray-100 text-gray-600'}">${key}</span>`;
            }},
            { data: 'file_number', render: d => `<span style="white-space:nowrap">${d||'—'}</span>` },
            { data: 'owner_name'   },
            { data: 'phone'        },
            { data: 'location'     },
            { data: 'land_use_type', render: d => luBadge(d) },
            { data: 'existing_use',  render: d => luBadge(d) },
            { data: null, orderable: false, searchable: false, render: (d, t, row) => {
                const approved   = (row.land_use_type || '').trim().toUpperCase();
                const prevailing = (row.existing_use  || '').trim().toUpperCase();
                if (!approved || !prevailing) return '<span class="text-gray-400 text-xs">—</span>';
                return approved !== prevailing
                    ? `<span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-semibold bg-red-100 text-red-700 border border-red-200"><svg class="w-3 h-3 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>Yes</span>`
                    : `<span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-semibold bg-green-100 text-green-700 border border-green-200"><svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>No</span>`;
            }},
            { data: 'status', orderable: false },
            { data: 'created_at'   },
        ],
    });

    // Registry filter → reload from the server (the list is server-side paged)
    $('#registry-filter').on('change', () => table.ajax.reload());

    // ── SPAS modal open / close ─────────────────────────────────────────────
    const spaModal = document.getElementById('modal-add-record');

    function openSpaModal() {
        spaModal.classList.remove('hidden');
        spaModal.classList.add('flex');
        lucide.createIcons();
    }

    ['btn-close-modal', 'btn-cancel-modal'].forEach(id => {
        document.getElementById(id).addEventListener('click', closeSpaModal);
    });

    function closeSpaModal() {
        spaModal.classList.add('hidden');
        spaModal.classList.remove('flex');
        document.getElementById('contravention-badge').classList.add('hidden');
        document.getElementById('contravention-badge').style.display = 'none';
    }

    // ── "Add to SPAS" per-row button ────────────────────────────────────────
    $(document).on('click', '.btn-lr-add-spa', async function () {
        $('.lr-dropdown').addClass('hidden');
        const p = JSON.parse($(this).attr('data-prefill'));

        // Reset form first
        document.getElementById('form-add-record').reset();
        document.getElementById('photo-preview').innerHTML = '';
        document.getElementById('lookup-msg').classList.add('hidden');
        document.getElementById('location-badge-wrap').classList.add('hidden');

        // Set file number immediately and open modal
        document.getElementById('display-file-number').value = p.file_number;
        document.getElementById('h-file_number').value       = p.file_number;
        document.getElementById('h-file_indexing_id').value  = p.file_indexing_id || '';
        document.getElementById('h-tracking_id').value       = p.tracking_id || '';
        document.getElementById('h-is_indexed').value        = '1';

        const msg = document.getElementById('lookup-msg');
        msg.className = 'text-xs mt-1 text-gray-400';
        msg.textContent = 'Loading file details…';
        msg.classList.remove('hidden');
        openSpaModal();

        // Full lookup via checkFile — gets properly parsed owner name
        try {
            const ctrl = new AbortController();
            const t    = setTimeout(() => ctrl.abort(), 8000);
            const res  = await fetch(`${LOOKUP}?file_number=${encodeURIComponent(p.file_number)}`, { signal: ctrl.signal });
            clearTimeout(t);
            const d = await res.json();
            if (d.found) {
                const owner = (d.file_title || d.owner_name || '').trim();
                document.getElementById('f-owner_name').value    = owner;
                document.getElementById('f-phone').value         = d.phone || '';
                document.getElementById('f-land_use_type').value = d.land_use_type || '';
                document.getElementById('h-location').value      = d.location || '';
                document.getElementById('h-lga').value           = d.lga || '';
                document.getElementById('h-file_indexing_id').value = d.file_indexing_id || p.file_indexing_id || '';
                document.getElementById('h-tracking_id').value      = d.tracking_id || p.tracking_id || '';
                const loc = [d.location, d.district, d.lga].filter(Boolean).join(', ');
                if (loc) {
                    document.getElementById('location-badge-text').textContent = loc;
                    document.getElementById('location-badge-wrap').classList.remove('hidden');
                }
                msg.className = 'text-xs mt-1 text-green-600';
                msg.textContent = '✓ Pre-filled from file indexing.';
            } else {
                // Fallback to prefill data
                document.getElementById('f-owner_name').value    = (p.owner_name && p.owner_name !== '—') ? p.owner_name : '';
                document.getElementById('f-land_use_type').value = p.land_use_type || '';
                document.getElementById('h-location').value      = p.location || '';
                if (p.location) {
                    document.getElementById('location-badge-text').textContent = p.location;
                    document.getElementById('location-badge-wrap').classList.remove('hidden');
                }
                msg.className = 'text-xs mt-1 text-amber-600';
                msg.textContent = 'File not in index — fill in details manually.';
            }
        } catch(err) {
            // Fallback silently
            document.getElementById('f-owner_name').value    = (p.owner_name && p.owner_name !== '—') ? p.owner_name : '';
            document.getElementById('f-land_use_type').value = p.land_use_type || '';
            msg.className = 'text-xs mt-1 text-red-500';
            msg.textContent = err.name === 'AbortError' ? 'Lookup timed out.' : 'Could not load details.';
        }
    });

    // ── Contravention check (Approved vs Prevailing) ──────────────────────
    function checkContravention() {
        const approved   = (document.getElementById('f-land_use_type').value || '').trim().toUpperCase();
        const prevailing = (document.getElementById('f-existing_use').value   || '').trim().toUpperCase();
        const badge      = document.getElementById('contravention-badge');
        if (approved && prevailing && approved !== prevailing) {
            badge.classList.remove('hidden');
            badge.style.display = 'flex';
        } else {
            badge.classList.add('hidden');
            badge.style.display = 'none';
        }
    }
    document.getElementById('f-existing_use').addEventListener('change', checkContravention);

    // ── Photo preview ──────────────────────────────────────────────────────
    document.getElementById('f-photos').addEventListener('change', function () {
        const preview = document.getElementById('photo-preview');
        preview.innerHTML = '';
        Array.from(this.files).forEach(file => {
            const url = URL.createObjectURL(file);
            preview.innerHTML += `
                <div class="relative w-20 h-20 rounded-lg overflow-hidden border border-gray-200 flex-shrink-0">
                    <img src="${url}" class="w-full h-full object-cover">
                </div>`;
        });
    });

    // ── File number picker ─────────────────────────────────────────────────
    document.getElementById('btn-pick-fileno').addEventListener('click', function () {
        if (!window.GlobalFileNoModal) {
            alert('File number selector not loaded. Please refresh the page.');
            return;
        }
        window.GlobalFileNoModal.open({
            callback: async function (data) {
                if (!data || !data.fileNumber) return;

                const fileNo = data.fileNumber;
                document.getElementById('display-file-number').value = fileNo;
                document.getElementById('h-file_number').value       = fileNo;

                const msg = document.getElementById('lookup-msg');
                msg.className   = 'text-xs mt-1 text-gray-400';
                msg.textContent = 'Loading file details…';
                msg.classList.remove('hidden');

                try {
                    const ctrl = new AbortController();
                    const timer = setTimeout(() => ctrl.abort(), 8000);
                    const res  = await fetch(`${LOOKUP}?file_number=${encodeURIComponent(fileNo)}`, { signal: ctrl.signal });
                    clearTimeout(timer);
                    const resp = await res.json();

                    if (resp.found) {
                        // Owner name from file_title (FileName), fallback to owner_name
                        const title = (resp.file_title || resp.owner_name || '').trim();
                        document.getElementById('f-owner_name').value       = title;
                        document.getElementById('f-phone').value            = resp.phone         || '';
                        document.getElementById('f-land_use_type').value    = resp.land_use_type || '';
                        document.getElementById('h-file_indexing_id').value = resp.file_indexing_id || '';
                        document.getElementById('h-tracking_id').value      = resp.tracking_id   || '';
                        document.getElementById('h-is_indexed').value       = '1';

                        // Location as hidden field + info badge
                        const locParts = [resp.location, resp.district, resp.lga].filter(Boolean);
                        const locStr   = locParts.join(', ');
                        document.getElementById('h-location').value = resp.location || '';
                        document.getElementById('h-lga').value      = resp.lga      || '';

                        if (locStr) {
                            document.getElementById('location-badge-text').textContent = locStr;
                            document.getElementById('location-badge-wrap').classList.remove('hidden');
                        } else {
                            document.getElementById('location-badge-wrap').classList.add('hidden');
                        }

                        msg.className   = 'text-xs mt-1 text-green-600';
                        msg.textContent = '✓ File found — details pre-filled.';
                    } else {
                        document.getElementById('h-is_indexed').value = '0';
                        document.getElementById('location-badge-wrap').classList.add('hidden');
                        msg.className   = 'text-xs mt-1 text-amber-600';
                        msg.textContent = 'File not in index — please fill in details manually.';
                    }
                } catch (err) {
                    msg.className   = 'text-xs mt-1 text-red-500';
                    msg.textContent = err.name === 'AbortError'
                        ? 'Lookup timed out — please fill in details manually.'
                        : 'Could not load file details. Please fill in manually.';
                }
            }
        });
    });

    // ── Form submit ────────────────────────────────────────────────────────
    document.getElementById('form-add-record').addEventListener('submit', async function (e) {
        e.preventDefault();

        if (!document.getElementById('h-file_number').value.trim()) {
            Swal.fire({ icon: 'warning', title: 'Required', text: 'Please select a file number first.' });
            return;
        }

        const btn = document.getElementById('btn-save-record');
        btn.disabled = true; btn.textContent = 'Saving…';

        try {
            const fd = new FormData(this);
            const res  = await fetch(STORE, {
                method : 'POST',
                headers: { 'X-CSRF-TOKEN': CSRF },
                body   : fd
            });
            const data = await res.json();

            if (data.success) {
                closeSpaModal();
                this.reset();
                document.getElementById('display-file-number').value = '';
                document.getElementById('location-badge-wrap').classList.add('hidden');
                document.getElementById('lookup-msg').classList.add('hidden');
                document.getElementById('photo-preview').innerHTML = '';
                table.ajax.reload();
                Swal.fire({ icon: 'success', title: 'Saved', text: data.message, timer: 2000, showConfirmButton: false });
            } else {
                Swal.fire({ icon: 'error', title: 'Error', text: data.message || 'Save failed.' });
            }
        } catch (err) {
            Swal.fire({ icon: 'error', title: 'Error', text: 'An unexpected error occurred.' });
        }

        btn.disabled = false; btn.textContent = 'Save Record';
    });
    // ── Land Record dropdown (three-dot) ──────────────────────────────────
    const UPDATE_URL = id => `{{ url('special-assignment/land-records') }}/${id}/update`;
    const DELETE_URL = id => `{{ url('special-assignment/land-records') }}/${id}/delete`;

    // Toggle open/close
    $('#land-records-table').on('click', '.btn-lr-toggle', function (e) {
        e.stopPropagation();
        const dd = $(this).next('.lr-dropdown');
        $('.lr-dropdown').not(dd).addClass('hidden');
        dd.toggleClass('hidden');
    });
    $(document).on('click', () => $('.lr-dropdown').addClass('hidden'));

    // View
    $('#land-records-table').on('click', '.btn-lr-view', function () {
        const id = $(this).data('id');
        window.location.href = `{{ url('special-assignment/land-records') }}/${id}`;
    });

    // Edit – open modal pre-filled
    const editModal = document.getElementById('modal-edit-record');
    $('#land-records-table').on('click', '.btn-lr-edit', function () {
        const id  = $(this).data('id');
        const rec = $(this).data('record');
        document.getElementById('edit-id').value            = id;
        document.getElementById('edit-owner_name').value    = rec.owner_name   || '';
        document.getElementById('edit-phone').value         = rec.phone        || '';
        document.getElementById('edit-location').value      = rec.location     || '';
        document.getElementById('edit-land_use_type').value = rec.land_use_type|| '';
        document.getElementById('edit-existing_use').value  = rec.existing_use || '';
        document.getElementById('edit-status').value        = rec.status       || 'open';
        editModal.classList.remove('hidden'); editModal.classList.add('flex');
    });
    document.querySelectorAll('.btn-close-edit').forEach(b => b.addEventListener('click', () => {
        editModal.classList.add('hidden'); editModal.classList.remove('flex');
    }));

    document.getElementById('form-edit-record').addEventListener('submit', async function (e) {
        e.preventDefault();
        const id  = document.getElementById('edit-id').value;
        const btn = this.querySelector('[type=submit]');
        btn.disabled = true; btn.textContent = 'Saving…';
        const payload = {
            owner_name:    document.getElementById('edit-owner_name').value,
            phone:         document.getElementById('edit-phone').value,
            location:      document.getElementById('edit-location').value,
            land_use_type: document.getElementById('edit-land_use_type').value,
            existing_use:  document.getElementById('edit-existing_use').value,
            status:        document.getElementById('edit-status').value,
        };
        const res  = await fetch(UPDATE_URL(id), { method:'POST', headers:{'X-CSRF-TOKEN':CSRF,'Content-Type':'application/json'}, body:JSON.stringify(payload) });
        const data = await res.json();
        if (data.success) {
            editModal.classList.add('hidden'); editModal.classList.remove('flex');
            table.ajax.reload();
            Swal.fire({ icon:'success', title:'Updated', text:data.message, timer:2000, showConfirmButton:false });
        } else {
            Swal.fire({ icon:'error', title:'Error', text:data.message||'Update failed.' });
        }
        btn.disabled = false; btn.textContent = 'Save Changes';
    });

    // Delete
    $('#land-records-table').on('click', '.btn-lr-delete', function () {
        const id   = $(this).data('id');
        const file = $(this).data('file');
        Swal.fire({
            icon:'warning', title:'Delete Record?',
            text:`This will permanently delete the land record for ${file}.`,
            showCancelButton:true, confirmButtonText:'Yes, Delete', confirmButtonColor:'#ef4444',
        }).then(async r => {
            if (!r.isConfirmed) return;
            const res  = await fetch(DELETE_URL(id), { method:'POST', headers:{'X-CSRF-TOKEN':CSRF,'Content-Type':'application/json'}, body:'{}' });
            const data = await res.json();
            if (data.success) {
                table.ajax.reload();
                Swal.fire({ icon:'success', title:'Deleted', text:data.message, timer:2000, showConfirmButton:false });
            } else {
                Swal.fire({ icon:'error', title:'Error', text:data.message||'Delete failed.' });
            }
        });
    });
});
</script>

{{-- Edit Record Modal --}}
<div id="modal-edit-record" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/40">
    <div class="bg-white rounded-2xl shadow-xl w-full max-w-lg mx-4 overflow-hidden">
        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
            <h3 class="text-base font-semibold text-gray-800">Edit Land Record</h3>
            <button class="btn-close-edit text-gray-400 hover:text-gray-600"><i data-lucide="x" class="h-5 w-5"></i></button>
        </div>
        <form id="form-edit-record" class="p-6 space-y-4">
            @csrf
            <input type="hidden" id="edit-id">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="sm:col-span-2">
                    <label class="block text-xs font-medium text-gray-600 mb-1">Owner / File Title <span class="text-red-500">*</span></label>
                    <input type="text" id="edit-owner_name" required class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm outline-none focus:border-[rgb(186,191,12)]">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Phone</label>
                    <input type="text" id="edit-phone" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm outline-none focus:border-[rgb(186,191,12)]">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Location</label>
                    <input type="text" id="edit-location" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm outline-none focus:border-[rgb(186,191,12)]">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Applied Land Use</label>
                    <input type="text" id="edit-land_use_type" readonly class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm bg-gray-50 outline-none">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Prevailing Land Use <span class="text-red-500">*</span></label>
                    <select id="edit-existing_use" required class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm outline-none focus:border-[rgb(186,191,12)]">
                        <option value="">Select…</option>
                        @foreach($landUseTypes as $lut)
                            <option value="{{ $lut }}">{{ $lut }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Status <span class="text-red-500">*</span></label>
                    <select id="edit-status" required class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm outline-none focus:border-[rgb(186,191,12)]">
                        <option value="open">Open</option>
                        <option value="in_progress">In Progress</option>
                        <option value="approved">Approved</option>
                        <option value="certificate_issued">Certificate Issued</option>
                        <option value="closed">Closed</option>
                    </select>
                </div>
            </div>
            <div class="flex justify-end gap-3 pt-2">
                <button type="button" class="btn-close-edit px-4 py-2 text-sm text-gray-600 border border-gray-200 rounded-lg hover:bg-gray-50">Cancel</button>
                <button type="submit" class="px-5 py-2 text-sm text-white bg-[rgb(186,191,12)] rounded-lg hover:opacity-90">Save Changes</button>
            </div>
        </form>
    </div>
</div>

{{-- Field Work Print Template --}}
<div id="field-template-print" style="display:none;">
<style>
@media print {
    body > *:not(#field-template-print) { display:none !important; }
    #field-template-print { display:block !important; }
    @page { size:A4 portrait; margin:14mm; }
}
#field-template-print { font-family:'Segoe UI',sans-serif; font-size:13px; color:#1f2937; background:#fff; width:100%; min-height:269mm; display:flex; flex-direction:column; }
.tpl-hdr { display:flex; align-items:flex-start; justify-content:space-between; border-bottom:2px solid rgb(186,191,12); padding-bottom:10px; margin-bottom:18px; flex-shrink:0; }
.tpl-hdr h1 { font-size:16px; font-weight:700; margin:0; }
.tpl-hdr .meta { font-size:11px; color:#6b7280; text-align:right; }
.tpl-hdr .meta b { color:#1f2937; font-size:12px; }
.tpl-box-wrap { border:1.5px solid #d1d5db; border-radius:8px; overflow:hidden; margin-bottom:18px; flex-shrink:0; }
.tpl-box-wrap.grow { flex:1; display:flex; flex-direction:column; margin-bottom:0; }
.tpl-box-wrap.grow .tpl-box-body { flex:1; display:flex; flex-direction:column; }
.tpl-box-wrap.grow .tf.findings { flex:1; display:flex; flex-direction:column; }
.tpl-box-wrap.grow .tf.findings .lines-wrap { flex:1; display:flex; flex-direction:column; justify-content:space-evenly; }
.tpl-box-title { display:flex; align-items:center; gap:6px; background:#fafafa; border-bottom:1px solid #e5e7eb; padding:9px 15px; font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.07em; color:rgb(186,191,12); flex-shrink:0; }
.tpl-box-body { padding:17px; }
.tpl-row2 { display:grid; grid-template-columns:1fr 1fr; gap:14px 20px; }
.tf { margin-bottom:14px; }
.tf label { display:block; font-size:10px; color:#9ca3af; font-weight:600; text-transform:uppercase; letter-spacing:.04em; margin-bottom:4px; }
.tf .pre { font-size:14px; font-weight:700; color:#111827; border-bottom:2px solid #374151; padding-bottom:5px; min-height:24px; }
.tf .line { border:none; border-bottom:1px solid #9ca3af; height:32px; width:100%; display:block; }
.tf .line-lg { border:none; border-bottom:1px solid #9ca3af; width:100%; display:block; flex:1; min-height:36px; }
.chk { display:flex; gap:22px; margin-top:6px; }
.chk span { font-size:13px; font-weight:700; display:flex; align-items:center; gap:5px; }
.chk-box { width:15px; height:15px; border:1.5px solid #374151; border-radius:2px; display:inline-block; flex-shrink:0; }
.sig-box { border:1px solid #d1d5db; border-radius:6px; height:60px; margin-top:4px; }
</style>

<div class="tpl-hdr">
    <h1>Special Assignment – Field Inspection Template</h1>
    <div class="meta">Printed: <span id="tpl-print-date"></span><br><b id="tpl-header-fileno"></b></div>
</div>

{{-- PROPERTY DETAILS box --}}
<div class="tpl-box-wrap">
    <div class="tpl-box-title">
        <svg width="10" height="10" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M3 9h18M9 21V9"/></svg>
        Property Details
    </div>
    <div class="tpl-box-body">
        <div class="tpl-row2">
            <div class="tf"><label>File Number</label><div class="pre" id="tpl-file"></div></div>
            <div class="tf"><label>Owner / File Title</label><div class="pre" id="tpl-owner"></div></div>
        </div>
        <div class="tf"><label>Location</label><div class="pre" id="tpl-location"></div></div>
        <div class="tpl-row2">
            <div class="tf"><label>Applied Land Use <span style="color:#d1d5db;font-weight:400;">(from file)</span></label><div class="pre" id="tpl-applied"></div></div>
            <div class="tf"><label>Approved Land Use <span style="color:#d1d5db;font-weight:400;">(official)</span></label><div class="line"></div></div>
        </div>
        <div class="tpl-row2">
            <div class="tf"><label>Prevailing Land Use <span style="color:#d1d5db;font-weight:400;">(on ground)</span></label><div class="line"></div></div>
            <div class="tf">
                <label><b>Contravention?</b></label>
                <div class="chk"><span><span class="chk-box"></span> Yes</span><span><span class="chk-box"></span> No</span></div>
            </div>
        </div>
    </div>
</div>

{{-- FIELD INSPECTION DATA box (grows to fill remaining page) --}}
<div class="tpl-box-wrap grow">
    <div class="tpl-box-title">
        <svg width="10" height="10" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9 5H7a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-2"/><rect x="9" y="3" width="6" height="4" rx="1"/></svg>
        Field Inspection Data <span style="font-weight:400;color:#9ca3af;">(officer completes)</span>
    </div>
    <div class="tpl-box-body">
        <div class="tpl-row2" style="flex-shrink:0;">
            <div class="tf"><label>Inspection Date</label><div class="line"></div></div>
            <div class="tf"><label>GPS Coordinates (Lat, Lng)</label><div class="line"></div></div>
        </div>
        <div class="tf findings">
            <label>Observations / Findings</label>
            <div class="lines-wrap">
                <div class="line-lg"></div>
                <div class="line-lg"></div>
                <div class="line-lg"></div>
                <div class="line-lg"></div>
                <div class="line-lg"></div>
            </div>
        </div>
    </div>
</div>

</div>

<script>
// Shared: fill and print template from a data object
function printFieldTemplate(d) {
    const set = (id, val) => { const el = document.getElementById(id); if (el) el.textContent = val; };
    set('tpl-print-date',    new Date().toLocaleDateString('en-GB'));
    set('tpl-header-fileno', d.file_number   || '');
    set('tpl-file',          d.file_number   || '—');
    set('tpl-owner',         d.owner_name    || '—');
    set('tpl-location',      d.location      || '—');
    set('tpl-applied',       d.land_use_type || '—');
    const tpl = document.getElementById('field-template-print');
    tpl.style.display = 'block'; window.print(); tpl.style.display = 'none';
}

// Page-header "Field Template" button → open select modal
let tplCurrentData = null;

document.addEventListener('DOMContentLoaded', function () {
    const tplModal   = document.getElementById('modal-select-template');
    const tplInput   = document.getElementById('tpl-fileno-input');
    const tplMsg     = document.getElementById('tpl-lookup-msg');
    const tplPreview = document.getElementById('tpl-preview-area');
    const tplPrint   = document.getElementById('btn-print-template');

    function resetTplModal() {
        tplInput.value = '';
        tplMsg.className = 'text-xs mt-1 hidden'; tplMsg.textContent = '';
        tplPreview.classList.add('hidden');
        tplPrint.disabled = true;
        tplCurrentData = null;
    }

    document.getElementById('btn-field-template').addEventListener('click', function () {
        resetTplModal();
        tplModal.classList.remove('hidden'); tplModal.classList.add('flex');
        lucide.createIcons();
    });

    // Lookup from file indexing
    document.getElementById('btn-tpl-lookup').addEventListener('click', async function () {
        const fileNo = tplInput.value.trim();
        if (!fileNo) return;
        tplMsg.className = 'text-xs mt-1 text-gray-400'; tplMsg.textContent = 'Looking up…'; tplMsg.classList.remove('hidden');
        tplPreview.classList.add('hidden'); tplPrint.disabled = true;
        try {
            const ctrl = new AbortController();
            const t = setTimeout(() => ctrl.abort(), 8000);
            const res  = await fetch(`{{ route('special-assignment.check-file') }}?file_number=${encodeURIComponent(fileNo)}`, { signal: ctrl.signal });
            clearTimeout(t);
            const d = await res.json();
            if (d.found) {
                tplCurrentData = {
                    file_number:   fileNo,
                    owner_name:    (d.file_title || d.owner_name || '').trim(),
                    location:      d.location || '',
                    land_use_type: d.land_use_type || '',
                };
                document.getElementById('tpl-pv-file').textContent    = tplCurrentData.file_number;
                document.getElementById('tpl-pv-owner').textContent   = tplCurrentData.owner_name;
                document.getElementById('tpl-pv-location').textContent= tplCurrentData.location || '—';
                document.getElementById('tpl-pv-applied').textContent = tplCurrentData.land_use_type || '—';
                tplPreview.classList.remove('hidden');
                tplPrint.disabled = false;
                tplMsg.className = 'text-xs mt-1 text-green-600'; tplMsg.textContent = '✓ File found — details pre-filled.';
            } else {
                tplMsg.className = 'text-xs mt-1 text-red-500'; tplMsg.textContent = 'File not found in indexing records.';
            }
        } catch (err) {
            tplMsg.className = 'text-xs mt-1 text-red-500';
            tplMsg.textContent = err.name === 'AbortError' ? 'Lookup timed out.' : 'Lookup failed.';
        }
    });

    // Allow Enter key in input
    tplInput.addEventListener('keydown', e => { if (e.key === 'Enter') document.getElementById('btn-tpl-lookup').click(); });

    document.getElementById('btn-print-template').addEventListener('click', function () {
        if (!tplCurrentData) return;
        tplModal.classList.add('hidden'); tplModal.classList.remove('flex');
        printFieldTemplate(tplCurrentData);
    });

    document.querySelectorAll('.tpl-modal-close').forEach(b => b.addEventListener('click', () => {
        tplModal.classList.add('hidden'); tplModal.classList.remove('flex');
    }));
});

// Row action: Field Template (pre-filled directly)
$(document).on('click', '.btn-lr-template', function () {
    $('.lr-dropdown').addClass('hidden');
    printFieldTemplate(JSON.parse($(this).attr('data-tpl')));
});
</script>

{{-- Select File for Template Modal --}}
<div id="modal-select-template" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/40">
    <div class="bg-white rounded-2xl shadow-xl w-full max-w-md mx-4">
        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
            <h3 class="text-base font-semibold text-gray-800">Field Inspection Template</h3>
            <button class="tpl-modal-close text-gray-400 hover:text-gray-600"><i data-lucide="x" class="h-5 w-5"></i></button>
        </div>
        <div class="p-6 space-y-4">
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">File Number <span class="text-red-500">*</span></label>
                <div class="flex gap-2">
                    <input type="text" id="tpl-fileno-input" placeholder="Enter file number…"
                        class="flex-1 border border-gray-200 rounded-lg px-3 py-2 text-sm outline-none focus:border-[rgb(186,191,12)] uppercase">
                    <button type="button" id="btn-tpl-lookup"
                        class="inline-flex items-center gap-1.5 px-4 py-2 bg-[rgb(186,191,12)] text-white text-sm font-medium rounded-lg hover:opacity-90">
                        <i data-lucide="search" class="h-4 w-4"></i> Lookup
                    </button>
                </div>
                <p id="tpl-lookup-msg" class="text-xs mt-1 hidden"></p>
            </div>

            {{-- Auto-filled preview --}}
            <div id="tpl-preview-area" class="hidden bg-gray-50 border border-gray-200 rounded-lg p-4 space-y-2 text-sm">
                <div class="grid grid-cols-2 gap-x-4 gap-y-2">
                    <div><p class="text-xs text-gray-400 mb-0.5">File Number</p><p id="tpl-pv-file" class="font-semibold text-gray-800 text-xs"></p></div>
                    <div><p class="text-xs text-gray-400 mb-0.5">File Title</p><p id="tpl-pv-owner" class="font-semibold text-gray-800 text-xs"></p></div>
                    <div class="col-span-2"><p class="text-xs text-gray-400 mb-0.5">Location</p><p id="tpl-pv-location" class="text-gray-700 text-xs"></p></div>
                    <div class="col-span-2"><p class="text-xs text-gray-400 mb-0.5">Applied Land Use</p><p id="tpl-pv-applied" class="text-gray-700 text-xs font-medium"></p></div>
                </div>
            </div>

            <div class="flex justify-end gap-3 pt-1">
                <button type="button" class="tpl-modal-close px-4 py-2 text-sm text-gray-600 border border-gray-200 rounded-lg hover:bg-gray-50">Cancel</button>
                <button type="button" id="btn-print-template" disabled
                    class="inline-flex items-center gap-2 px-5 py-2 text-sm text-white bg-[rgb(186,191,12)] rounded-lg hover:opacity-90 disabled:opacity-40">
                    <i data-lucide="printer" class="h-4 w-4"></i> Print Template
                </button>
            </div>
        </div>
    </div>
</div>
@endsection
