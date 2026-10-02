@extends('layouts.app')

@section('page-title')
    {{ __('Land Officers') }}
@endsection

@section('content')
<div class="flex-1 overflow-auto bg-slate-50/60">
    @include('admin.header', [
        'PageTitle'       => 'Land Officers',
        'PageDescription' => 'Manage land officers, their ranks, digital signatures and verification methods.'
    ])

    <div class="py-8 bg-slate-50 min-h-screen">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

            {{-- Page Header --}}
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                <div>
                    <p class="text-[10px] font-black text-emerald-600 uppercase tracking-widest">System Admin</p>
                    <h1 class="text-2xl font-extrabold text-slate-900 tracking-tight mt-1">Land Officers</h1>
                    <p class="text-slate-500 text-sm mt-1">Manage land officers, their ranks, digital signatures and verification methods.</p>
                </div>
                <button type="button" id="toggle-add-form-btn"
                    class="inline-flex items-center gap-2 px-5 py-2.5 bg-emerald-600 text-white text-sm font-bold rounded-xl hover:bg-emerald-700 transition shadow-sm">
                    <i data-lucide="plus" class="h-4 w-4"></i>
                    Add Land Officer
                </button>
            </div>

            {{-- Add Form (hidden by default) --}}
            <div id="add-land-officer-card" class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6 hidden">
                <div class="flex items-center justify-between mb-5">
                    <h2 class="text-base font-bold text-slate-800">Add Land Officer</h2>
                    <button type="button" id="close-add-form-btn" class="p-1.5 hover:bg-slate-100 rounded-lg transition">
                        <i data-lucide="x" class="h-4 w-4 text-slate-400"></i>
                    </button>
                </div>
                <form id="land-officer-form" enctype="multipart/form-data">
                    @csrf
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

                        {{-- Department --}}
                        <div class="space-y-1.5">
                            <label class="block text-xs font-semibold text-slate-600 uppercase tracking-wide">Department <span class="text-red-500">*</span></label>
                            <select id="add-department-id" name="department_id"
                                class="w-full rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm text-slate-900 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100 outline-none transition">
                                <option value="">— Select Department —</option>
                                @foreach($departments as $dept)
                                    <option value="{{ $dept->id }}">{{ $dept->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        {{-- User (dependent) --}}
                        <div class="space-y-1.5">
                            <label class="block text-xs font-semibold text-slate-600 uppercase tracking-wide">User <span class="text-red-500">*</span></label>
                            <div class="relative">
                                <select id="add-user-id" name="user_id" disabled
                                    class="w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-2.5 text-sm text-slate-900 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100 outline-none transition disabled:opacity-50">
                                    <option value="">— Select Department first —</option>
                                </select>
                                <span id="add-user-spinner" class="hidden absolute right-3 top-1/2 -translate-y-1/2">
                                    <svg class="animate-spin h-4 w-4 text-emerald-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                                </span>
                            </div>
                        </div>

                        {{-- Rank --}}
                        <div class="space-y-1.5">
                            <label class="block text-xs font-semibold text-slate-600 uppercase tracking-wide">Rank
                                <span class="text-slate-400 normal-case font-normal tracking-normal">(saved to user profile)</span>
                            </label>
                            <input type="text" id="add-rank" name="rank" placeholder="e.g. Assistant Director"
                                class="w-full rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm text-slate-900 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100 outline-none transition">
                        </div>

                        {{-- Verification Method --}}
                        <div class="space-y-1.5">
                            <label class="block text-xs font-semibold text-slate-600 uppercase tracking-wide">Default Verification Method <span class="text-red-500">*</span></label>
                            <select name="notification_type"
                                class="w-full rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm text-slate-900 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100 outline-none transition">
                                <option value="email">Email OTP</option>
                                <option value="sms">SMS OTP</option>
                                <option value="password">Password Confirmation</option>
                            </select>
                        </div>

                        {{-- Signature --}}
                        <div class="md:col-span-2 space-y-1.5">
                            <label class="block text-xs font-semibold text-slate-600 uppercase tracking-wide">Digital Signature File
                                <span class="text-slate-400 normal-case font-normal tracking-normal">(PNG / JPG, max 2 MB — saved to user profile)</span>
                            </label>
                            {{-- Preview of existing signature when user is selected --}}
                            <div id="add-existing-sig-wrap" class="hidden mb-2 flex items-center gap-3 bg-slate-50 rounded-xl px-4 py-2 border border-slate-200">
                                <img id="add-existing-sig-img" src="" alt="Existing Signature" class="h-10 object-contain">
                                <span class="text-xs text-slate-500">Current signature — upload a new file to replace</span>
                            </div>
                            <input type="file" name="signature_file" accept="image/png,image/jpeg"
                                class="w-full rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm cursor-pointer focus:border-emerald-500 outline-none transition">
                        </div>

                        <div class="md:col-span-2 flex items-center gap-3 pt-1">
                            <button type="submit" id="save-officer-btn"
                                class="inline-flex items-center gap-2 px-5 py-2.5 bg-emerald-600 text-white text-sm font-bold rounded-xl hover:bg-emerald-700 transition shadow-sm">
                                <i data-lucide="save" class="h-4 w-4"></i>
                                <span id="save-officer-btn-text">Save Land Officer</span>
                            </button>
                            <button type="button" id="cancel-add-btn"
                                class="inline-flex items-center gap-2 px-4 py-2.5 border border-slate-200 text-slate-600 text-sm font-semibold rounded-xl hover:bg-slate-50 transition">
                                Cancel
                            </button>
                        </div>
                    </div>
                </form>
            </div>

            {{-- Officers Table --}}
            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
                <div class="bg-slate-50 px-6 py-4 border-b border-slate-200 flex items-center gap-2">
                    <i data-lucide="users" class="h-4 w-4 text-emerald-600"></i>
                    <h3 class="font-bold text-slate-800">Land Officers</h3>
                    <span class="ml-auto inline-flex items-center justify-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-emerald-50 text-emerald-700 border border-emerald-100">
                        {{ $officers->count() }}
                    </span>
                </div>
                <div class="overflow-x-auto">

                    {{-- Edit modal --}}
                    <div id="edit-officer-modal" class="hidden fixed inset-0 bg-black/40 z-50 flex items-center justify-center p-4">
                        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-xl p-6">
                            <div class="flex items-center justify-between mb-5">
                                <h2 class="text-base font-bold text-slate-800">Edit Land Officer</h2>
                                <button type="button" id="close-edit-modal" class="p-1.5 hover:bg-slate-100 rounded-lg transition">
                                    <i data-lucide="x" class="h-4 w-4 text-slate-400"></i>
                                </button>
                            </div>
                            <form id="edit-officer-form" enctype="multipart/form-data">
                                @csrf
                                @method('PUT')
                                <input type="hidden" id="edit-officer-id">
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

                                    {{-- Department --}}
                                    <div class="space-y-1.5">
                                        <label class="block text-xs font-semibold text-slate-600 uppercase tracking-wide">Department <span class="text-red-500">*</span></label>
                                        <select id="edit-department-id"
                                            class="w-full rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm text-slate-900 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100 outline-none transition">
                                            <option value="">— Select Department —</option>
                                            @foreach($departments as $dept)
                                                <option value="{{ $dept->id }}">{{ $dept->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>

                                    {{-- User (dependent) --}}
                                    <div class="space-y-1.5">
                                        <label class="block text-xs font-semibold text-slate-600 uppercase tracking-wide">User <span class="text-red-500">*</span></label>
                                        <div class="relative">
                                            <select id="edit-user-id" name="user_id" disabled
                                                class="w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-2.5 text-sm text-slate-900 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100 outline-none transition disabled:opacity-50">
                                                <option value="">— Select Department first —</option>
                                            </select>
                                            <span id="edit-user-spinner" class="hidden absolute right-3 top-1/2 -translate-y-1/2">
                                                <svg class="animate-spin h-4 w-4 text-emerald-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                                            </span>
                                        </div>
                                    </div>

                                    {{-- Rank --}}
                                    <div class="space-y-1.5">
                                        <label class="block text-xs font-semibold text-slate-600 uppercase tracking-wide">Rank</label>
                                        <input type="text" name="rank" id="edit-rank" placeholder="e.g. Assistant Director"
                                            class="w-full rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm text-slate-900 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100 outline-none transition">
                                    </div>

                                    {{-- Verification Method --}}
                                    <div class="space-y-1.5">
                                        <label class="block text-xs font-semibold text-slate-600 uppercase tracking-wide">Default Verification Method <span class="text-red-500">*</span></label>
                                        <select name="notification_type" id="edit-notification-type"
                                            class="w-full rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm text-slate-900 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100 outline-none transition">
                                            <option value="email">Email OTP</option>
                                            <option value="sms">SMS OTP</option>
                                            <option value="password">Password Confirmation</option>
                                        </select>
                                    </div>

                                    {{-- Signature --}}
                                    <div class="md:col-span-2 space-y-1.5">
                                        <label class="block text-xs font-semibold text-slate-600 uppercase tracking-wide">Replace Signature File
                                            <span class="text-slate-400 normal-case font-normal tracking-normal">(leave empty to keep current)</span>
                                        </label>
                                        <div id="edit-current-sig" class="mb-2 hidden flex items-center gap-3 bg-slate-50 rounded-xl px-4 py-2 border border-slate-200">
                                            <img src="" alt="Current Signature" class="h-10 object-contain" id="edit-sig-preview">
                                            <span class="text-xs text-slate-500">Current signature</span>
                                        </div>
                                        <input type="file" name="signature_file" accept="image/png,image/jpeg"
                                            class="w-full rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm cursor-pointer focus:border-emerald-500 outline-none transition">
                                    </div>

                                    <div class="md:col-span-2 flex items-center gap-3 pt-1">
                                        <button type="submit" id="save-edit-btn"
                                            class="inline-flex items-center gap-2 px-5 py-2.5 bg-emerald-600 text-white text-sm font-bold rounded-xl hover:bg-emerald-700 transition shadow-sm">
                                            <i data-lucide="save" class="h-4 w-4"></i>
                                            <span id="save-edit-btn-text">Save Changes</span>
                                        </button>
                                        <button type="button" id="cancel-edit-btn"
                                            class="inline-flex items-center gap-2 px-4 py-2.5 border border-slate-200 text-slate-600 text-sm font-semibold rounded-xl hover:bg-slate-50 transition">
                                            Cancel
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>

                    <table class="w-full text-sm">
                        <thead>
                            <tr class="bg-slate-50/60 border-b border-slate-200 text-[10px] font-black text-slate-500 uppercase tracking-widest">
                                <th class="px-5 py-3 text-left">Officer</th>
                                <th class="px-5 py-3 text-left">Department</th>
                                <th class="px-5 py-3 text-left">Rank</th>
                                <th class="px-5 py-3 text-left">Method</th>
                                <th class="px-5 py-3 text-left">Signature</th>
                                <th class="px-5 py-3 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse($officers as $officer)
                                @php
                                    $linkedUser = $officer->user;
                                    $sigPath    = $officer->signature_file ?? ($linkedUser?->signature ?? null);
                                    $sigUrl     = $sigPath ? \Illuminate\Support\Facades\Storage::disk('public')->url($sigPath) : '';
                                @endphp
                                <tr class="hover:bg-slate-50/50 transition-colors"
                                    data-officer-id="{{ $officer->id }}"
                                    data-user-id="{{ $officer->user_id }}"
                                    data-department-id="{{ $linkedUser?->department_id ?? '' }}"
                                    data-rank="{{ $linkedUser?->rank ?? $officer->rank ?? '' }}"
                                    data-notification-type="{{ $officer->notification_type }}"
                                    data-signature-url="{{ $sigUrl }}">
                                    <td class="px-5 py-3">
                                        <div class="font-semibold text-slate-800">{{ $linkedUser?->name ?? $officer->name }}</div>
                                        <div class="text-xs text-slate-400">{{ $linkedUser?->email ?? '' }}</div>
                                    </td>
                                    <td class="px-5 py-3 text-slate-600 text-xs">
                                        {{ $linkedUser?->department?->name ?? '—' }}
                                    </td>
                                    <td class="px-5 py-3 text-slate-600">{{ $linkedUser?->rank ?? $officer->rank ?? '—' }}</td>
                                    <td class="px-5 py-3">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-black uppercase tracking-widest
                                            {{ $officer->notification_type === 'password' ? 'bg-amber-50 text-amber-700 border border-amber-100' : ($officer->notification_type === 'sms' ? 'bg-blue-50 text-blue-700 border border-blue-100' : 'bg-emerald-50 text-emerald-700 border border-emerald-100') }}">
                                            {{ strtoupper($officer->notification_type ?? 'email') }}
                                        </span>
                                    </td>
                                    <td class="px-5 py-3">
                                        @if($sigUrl)
                                            <img src="{{ $sigUrl }}" alt="Signature"
                                                class="h-10 object-contain border border-slate-200 rounded bg-white px-2 py-1">
                                        @else
                                            <span class="inline-flex items-center gap-1 text-[10px] font-bold text-slate-400 uppercase">
                                                <i data-lucide="image-off" class="h-3 w-3"></i> Not set
                                            </span>
                                        @endif
                                    </td>
                                    <td class="px-5 py-3 text-right">
                                        <button type="button"
                                            class="edit-officer-btn inline-flex items-center gap-1 px-3 py-1.5 text-xs font-bold text-indigo-600 border border-indigo-100 rounded-lg hover:bg-indigo-50 transition">
                                            <i data-lucide="pencil" class="h-3 w-3"></i> Edit
                                        </button>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-5 py-12 text-center">
                                        <div class="flex flex-col items-center gap-3">
                                            <i data-lucide="pen-tool" class="h-10 w-10 text-slate-200"></i>
                                            <p class="text-slate-400 text-sm font-medium">No land officers configured</p>
                                            <p class="text-slate-300 text-xs">Click "Add Land Officer" to get started.</p>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </div>

    @include('admin.footer')
</div>

@push('scripts')
<script>
(function () {
    'use strict';

    const csrfToken    = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    const usersApiBase = '{{ url("land-officers/users-by-department") }}';

    // ─── Cascading department → user helper ──────────────────────
    function loadUsersByDept(deptId, userSelect, spinner, preselect, rankInput) {
        if (!deptId) {
            userSelect.innerHTML = '<option value="">— Select Department first —</option>';
            userSelect.disabled  = true;
            return;
        }
        spinner?.classList.remove('hidden');
        userSelect.disabled = true;

        fetch(`${usersApiBase}/${deptId}`, {
            headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' }
        })
        .then(r => r.json())
        .then(users => {
            userSelect.innerHTML = '<option value="">— Select User —</option>';
            users.forEach(u => {
                const opt = document.createElement('option');
                opt.value             = u.id;
                opt.textContent       = u.name;
                opt.dataset.rank      = u.rank || '';
                opt.dataset.signature = u.signature || '';
                if (String(u.id) === String(preselect)) opt.selected = true;
                userSelect.appendChild(opt);
            });
            userSelect.disabled = false;

            // Pre-fill rank from the selected user (if not already set)
            if (preselect && rankInput) {
                const selOpt = userSelect.querySelector(`option[value="${preselect}"]`);
                if (selOpt && !rankInput.value) rankInput.value = selOpt.dataset.rank || '';
            }
        })
        .catch(() => {
            userSelect.innerHTML = '<option value="">Failed to load users</option>';
            userSelect.disabled  = true;
        })
        .finally(() => spinner?.classList.add('hidden'));
    }

    // ─── ADD FORM ─────────────────────────────────────────────────
    const addCard    = document.getElementById('add-land-officer-card');
    const toggleBtn  = document.getElementById('toggle-add-form-btn');
    const closeBtn   = document.getElementById('close-add-form-btn');
    const cancelBtn  = document.getElementById('cancel-add-btn');
    const addDeptSel = document.getElementById('add-department-id');
    const addUserSel = document.getElementById('add-user-id');
    const addSpinner = document.getElementById('add-user-spinner');
    const addRankInp = document.getElementById('add-rank');
    const addSigWrap = document.getElementById('add-existing-sig-wrap');
    const addSigImg  = document.getElementById('add-existing-sig-img');

    const showAddForm = () => { addCard?.classList.remove('hidden'); toggleBtn?.classList.add('hidden'); };
    const hideAddForm = () => { addCard?.classList.add('hidden');    toggleBtn?.classList.remove('hidden'); };

    toggleBtn?.addEventListener('click', showAddForm);
    closeBtn?.addEventListener('click',  hideAddForm);
    cancelBtn?.addEventListener('click', hideAddForm);

    addDeptSel?.addEventListener('change', function () {
        loadUsersByDept(this.value, addUserSel, addSpinner, null, addRankInp);
        addSigWrap?.classList.add('hidden');
    });

    addUserSel?.addEventListener('change', function () {
        const selected = this.options[this.selectedIndex];
        if (selected && selected.value) {
            if (addRankInp && selected.dataset.rank) addRankInp.value = selected.dataset.rank;
            if (selected.dataset.signature) {
                addSigImg.src = selected.dataset.signature;
                addSigWrap?.classList.remove('hidden');
            } else {
                addSigWrap?.classList.add('hidden');
            }
        } else {
            addSigWrap?.classList.add('hidden');
        }
    });

    document.getElementById('land-officer-form')?.addEventListener('submit', function (e) {
        e.preventDefault();
        if (!addUserSel?.value) {
            if (window.Swal) Swal.fire('Validation', 'Please select a user.', 'warning');
            return;
        }
        const btn     = document.getElementById('save-officer-btn');
        const btnText = document.getElementById('save-officer-btn-text');
        btn.disabled  = true;
        btnText.textContent = 'Saving…';

        fetch('{{ route('setting.land-officers.store') }}', {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
            body: new FormData(this)
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                if (window.Swal) Swal.fire('Saved', data.message, 'success').then(() => location.reload());
                else location.reload();
            } else {
                if (window.Swal) Swal.fire('Error', data.message || 'Request failed', 'error');
            }
        })
        .catch(() => { if (window.Swal) Swal.fire('Error', 'Request failed', 'error'); })
        .finally(() => { btn.disabled = false; btnText.textContent = 'Save Land Officer'; });
    });

    // ─── EDIT MODAL ───────────────────────────────────────────────
    const editModal     = document.getElementById('edit-officer-modal');
    const closeEditBtn  = document.getElementById('close-edit-modal');
    const cancelEditBtn = document.getElementById('cancel-edit-btn');
    const editDeptSel   = document.getElementById('edit-department-id');
    const editUserSel   = document.getElementById('edit-user-id');
    const editSpinner   = document.getElementById('edit-user-spinner');
    const editRankInp   = document.getElementById('edit-rank');
    const editSigWrap   = document.getElementById('edit-current-sig');
    const editSigImg    = document.getElementById('edit-sig-preview');

    const hideEditModal = () => editModal?.classList.add('hidden');
    closeEditBtn?.addEventListener('click',  hideEditModal);
    cancelEditBtn?.addEventListener('click', hideEditModal);
    editModal?.addEventListener('click', e => { if (e.target === editModal) hideEditModal(); });

    editDeptSel?.addEventListener('change', function () {
        loadUsersByDept(this.value, editUserSel, editSpinner, null, editRankInp);
    });

    editUserSel?.addEventListener('change', function () {
        const selected = this.options[this.selectedIndex];
        if (selected && selected.value) {
            if (editRankInp && selected.dataset.rank) editRankInp.value = selected.dataset.rank;
            if (selected.dataset.signature) {
                editSigImg.src = selected.dataset.signature;
                editSigWrap?.classList.remove('hidden');
            } else {
                editSigWrap?.classList.add('hidden');
            }
        }
    });

    document.querySelectorAll('.edit-officer-btn').forEach(btn => {
        btn.addEventListener('click', function () {
            const row            = this.closest('tr');
            const officerId      = row.dataset.officerId;
            const userId         = row.dataset.userId;
            const deptId         = row.dataset.departmentId;
            const rank           = row.dataset.rank;
            const notifType      = row.dataset.notificationType;
            const sigUrl         = row.dataset.signatureUrl;

            document.getElementById('edit-officer-id').value    = officerId;
            if (editRankInp)  editRankInp.value                  = rank || '';
            const notifSel = document.getElementById('edit-notification-type');
            if (notifSel) notifSel.value = notifType || 'email';

            if (sigUrl && editSigImg && editSigWrap) {
                editSigImg.src = sigUrl;
                editSigWrap.classList.remove('hidden');
            } else if (editSigWrap) {
                editSigWrap.classList.add('hidden');
            }

            // Pre-select department, then load users and pre-select user
            if (deptId && editDeptSel) {
                editDeptSel.value = deptId;
                loadUsersByDept(deptId, editUserSel, editSpinner, userId, null);
            }

            editModal?.classList.remove('hidden');
            if (typeof lucide !== 'undefined') lucide.createIcons();
        });
    });

    document.getElementById('edit-officer-form')?.addEventListener('submit', function (e) {
        e.preventDefault();
        const officerId = document.getElementById('edit-officer-id').value;
        if (!editUserSel?.value) {
            if (window.Swal) Swal.fire('Validation', 'Please select a user.', 'warning');
            return;
        }
        const btn     = document.getElementById('save-edit-btn');
        const btnText = document.getElementById('save-edit-btn-text');
        btn.disabled  = true;
        btnText.textContent = 'Saving…';

        const formData = new FormData(this);
        formData.append('_method', 'PUT');

        fetch(`{{ url('settings/land-officers') }}/${officerId}`, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
            body: formData
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                if (window.Swal) Swal.fire('Updated', data.message, 'success').then(() => location.reload());
                else location.reload();
            } else {
                if (window.Swal) Swal.fire('Error', data.message || 'Request failed', 'error');
            }
        })
        .catch(() => { if (window.Swal) Swal.fire('Error', 'Request failed', 'error'); })
        .finally(() => { btn.disabled = false; btnText.textContent = 'Save Changes'; });
    });

    if (typeof lucide !== 'undefined') lucide.createIcons();
})();
</script>
@endpush
@endsection
