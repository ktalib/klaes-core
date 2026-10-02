@extends('laas.layouts.portal')

@section('title', 'New application — LAAS Portal')

@section('content')
@php
    $v = fn ($field, $fallback = '') => old($field, $draft->{$field} ?? $fallback);

    $input = 'laas-input mt-2';
    $label = 'block text-sm font-medium text-[var(--ink)] dark:text-[var(--ink-soft)]';
@endphp

<div class="mx-auto max-w-3xl">

    <div class="mb-6 flex flex-wrap items-start justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold text-[var(--ink)] dark:text-[var(--ink)]">Land allocation application</h1>
            <p class="mt-1 text-sm text-[var(--ink-soft)] dark:text-[var(--ink-soft)]">
                Fill every required field, then submit. Your answers are saved as you type.
            </p>
        </div>
        <p id="laas-autosave" class="hidden items-center gap-1.5 text-xs text-[var(--ink-soft)] dark:text-[var(--ink-soft)]">
            <i data-lucide="cloud-check" class="h-3.5 w-3.5"></i><span></span>
        </p>
    </div>

    @if($errors->any())
        <div class="mb-6 rounded-xl border border-red-200 bg-red-50 p-4 dark:border-red-700 dark:bg-red-900/30">
            <p class="mb-2 text-sm font-semibold text-red-800 dark:text-red-300">Please correct the following:</p>
            <ul class="list-inside list-disc space-y-1">
                @foreach($errors->all() as $error)
                    <li class="text-sm text-red-700 dark:text-red-400">{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form id="laas-apply-form" method="POST" action="{{ route('laas.apply.store') }}" class="space-y-6">
        @csrf

        <!-- Applicant -->
        <section class="rounded-2xl border border-[var(--border)] bg-[var(--surface-card)] p-6 dark:border-[var(--border)] dark:bg-[var(--surface-card)]">
            <h2 class="mb-5 flex items-center gap-2 text-sm font-extrabold uppercase tracking-widest text-[var(--brand)] dark:text-[var(--brand)]">
                <i data-lucide="user" class="h-4 w-4"></i> Applicant details
            </h2>

            <div class="space-y-4">
                <div>
                    <label for="applicant_type" class="{{ $label }}">Applicant type <span class="text-red-500">*</span></label>
                    <select id="applicant_type" name="applicant_type" required class="{{ $input }}">
                        <option value="">Select…</option>
                        @foreach(['Individual', 'Corporate', 'Government', 'Religious body', 'Non-governmental organisation'] as $type)
                            <option value="{{ $type }}" @selected($v('applicant_type') === $type)>{{ $type }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="applicant_name" class="{{ $label }}">Full name / organisation name <span class="text-red-500">*</span></label>
                    <input id="applicant_name" type="text" name="applicant_name" required
                           value="{{ $v('applicant_name', $applicant->name) }}" class="{{ $input }}">
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label for="applicant_phone" class="{{ $label }}">Phone number <span class="text-red-500">*</span></label>
                        <input id="applicant_phone" type="tel" name="applicant_phone" required
                               value="{{ $v('applicant_phone', $applicant->phone) }}" class="{{ $input }}">
                        <p class="mt-1 text-xs text-[var(--ink-soft)] dark:text-[var(--ink-soft)]">Updates on this application go to this number.</p>
                    </div>
                    <div>
                        <label for="applicant_email" class="{{ $label }}">Email address</label>
                        <input id="applicant_email" type="email" name="applicant_email"
                               value="{{ $v('applicant_email', $applicant->email) }}" class="{{ $input }}">
                    </div>
                </div>

                <div>
                    <label for="applicant_nin" class="{{ $label }}">National Identification Number (NIN)</label>
                    <input id="applicant_nin" type="text" name="applicant_nin"
                           value="{{ $v('applicant_nin', $applicant->nin) }}" class="{{ $input }}">
                </div>

                <div>
                    <label for="applicant_address" class="{{ $label }}">Contact address <span class="text-red-500">*</span></label>
                    <textarea id="applicant_address" name="applicant_address" rows="2" required
                              class="{{ $input }}">{{ $v('applicant_address', $applicant->address) }}</textarea>
                </div>
            </div>
        </section>

        <!-- Land -->
        <section class="rounded-2xl border border-[var(--border)] bg-[var(--surface-card)] p-6 dark:border-[var(--border)] dark:bg-[var(--surface-card)]">
            <h2 class="mb-5 flex items-center gap-2 text-sm font-extrabold uppercase tracking-widest text-[var(--brand)] dark:text-[var(--brand)]">
                <i data-lucide="map-pin" class="h-4 w-4"></i> Land applied for
            </h2>

            <div class="space-y-4">
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label for="land_use" class="{{ $label }}">Land use <span class="text-red-500">*</span></label>
                        <select id="land_use" name="land_use" required class="{{ $input }}">
                            <option value="">Select…</option>
                            @foreach($landUses as $landUse)
                                <option value="{{ $landUse->landuse }}" data-id="{{ $landUse->id }}"
                                        @selected($v('land_use') === $landUse->landuse)>{{ $landUse->landuse }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="purpose_id" class="{{ $label }}">Purpose</label>
                        <select id="purpose_id" name="purpose_id" class="{{ $input }}"
                                data-selected="{{ $v('purpose_id') }}">
                            <option value="">Select a land use first…</option>
                        </select>
                    </div>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label for="lga_id" class="{{ $label }}">Local Government Area <span class="text-red-500">*</span></label>
                        <select id="lga_id" name="lga_id" required class="{{ $input }}">
                            <option value="">Select…</option>
                            @foreach($lgas as $lga)
                                <option value="{{ $lga->id }}" @selected((string) $v('lga_id') === (string) $lga->id)>{{ $lga->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="district_id" class="{{ $label }}">District</label>
                        <select id="district_id" name="district_id" class="{{ $input }}"
                                data-selected="{{ $v('district_id') }}">
                            <option value="">Select an LGA first…</option>
                        </select>
                    </div>
                </div>

                <div>
                    <label for="location" class="{{ $label }}">Location / address of the land <span class="text-red-500">*</span></label>
                    <textarea id="location" name="location" rows="2" required
                              placeholder="e.g. Behind Kwanar Dawaki Primary School, along Zaria Road"
                              class="{{ $input }}">{{ $v('location') }}</textarea>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label for="plot_no" class="{{ $label }}">Plot number (if known)</label>
                        <input id="plot_no" type="text" name="plot_no" value="{{ $v('plot_no') }}" class="{{ $input }}">
                    </div>
                    <div>
                        <label for="approx_size" class="{{ $label }}">Approximate size</label>
                        <input id="approx_size" type="text" name="approx_size" placeholder="e.g. 1,000 sqm"
                               value="{{ $v('approx_size') }}" class="{{ $input }}">
                    </div>
                </div>

                <div>
                    <label for="existing_allocation_ref" class="{{ $label }}">Existing allocation reference (if any)</label>
                    <input id="existing_allocation_ref" type="text" name="existing_allocation_ref"
                           value="{{ $v('existing_allocation_ref') }}" class="{{ $input }}">
                </div>

                <div>
                    <label for="applicant_remarks" class="{{ $label }}">Anything else the Ministry should know</label>
                    <textarea id="applicant_remarks" name="applicant_remarks" rows="3" class="{{ $input }}">{{ $v('applicant_remarks') }}</textarea>
                </div>
            </div>
        </section>

        <div class="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-[var(--border)] bg-[var(--surface-card)] p-5 dark:border-[var(--border)] dark:bg-[var(--surface-card)]">
            <p class="text-xs text-[var(--ink-soft)] dark:text-[var(--ink-soft)]">
                Supporting documents can be uploaded on the next screen, once your application has a reference.
            </p>
            <button type="submit" class="laas-btn inline-flex items-center gap-2 rounded-lg px-6 py-2.5 text-sm font-semibold transition">
                <i data-lucide="send" class="h-4 w-4"></i> Submit application
            </button>
        </div>
    </form>
</div>
@endsection

@push('scripts')
<script>
(function () {
    const form  = document.getElementById('laas-apply-form');
    const token = document.querySelector('meta[name="csrf-token"]').content;

    // ---- Dependent dropdowns ------------------------------------------------
    // Districts are fetched per-LGA rather than rendered into the page: the
    // table holds ~1,800 rows, and inlining that into a select is what makes
    // the OSS applications screen unusable on a modest machine.
    async function fill(select, url, placeholder) {
        const wanted = select.dataset.selected || '';
        select.innerHTML = `<option value="">Loading…</option>`;

        try {
            const res  = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            const json = await res.json();
            const rows = json.data || [];

            select.innerHTML = `<option value="">${placeholder}</option>` + rows.map(r =>
                `<option value="${r.id}" ${String(r.id) === String(wanted) ? 'selected' : ''}>${r.name}</option>`
            ).join('');
        } catch (e) {
            select.innerHTML = `<option value="">Could not load — please retry</option>`;
        }
    }

    const lgaSelect      = document.getElementById('lga_id');
    const districtSelect = document.getElementById('district_id');
    const landUseSelect  = document.getElementById('land_use');
    const purposeSelect  = document.getElementById('purpose_id');

    function loadDistricts() {
        if (!lgaSelect.value) {
            districtSelect.innerHTML = '<option value="">Select an LGA first…</option>';
            return;
        }
        fill(districtSelect, `{{ route('laas.api.reference.districts') }}?lga_id=${encodeURIComponent(lgaSelect.value)}`, 'Select a district…');
    }

    function loadPurposes() {
        const opt = landUseSelect.options[landUseSelect.selectedIndex];
        const id  = opt ? opt.dataset.id : '';

        if (!id) {
            purposeSelect.innerHTML = '<option value="">Select a land use first…</option>';
            return;
        }
        fill(purposeSelect, `{{ route('laas.api.reference.purposes') }}?landuseid=${encodeURIComponent(id)}`, 'Select a purpose…');
    }

    lgaSelect.addEventListener('change', () => { districtSelect.dataset.selected = ''; loadDistricts(); });
    landUseSelect.addEventListener('change', () => { purposeSelect.dataset.selected = ''; loadPurposes(); });

    // Repopulate whatever the draft already had selected.
    if (lgaSelect.value) loadDistricts();
    if (landUseSelect.value) loadPurposes();

    // ---- Autosave -----------------------------------------------------------
    const badge     = document.getElementById('laas-autosave');
    const badgeText = badge.querySelector('span');
    let timer = null;
    let dirty = false;

    function showSaved(at) {
        badge.classList.remove('hidden');
        badge.classList.add('inline-flex');
        badgeText.textContent = `Saved ${at}`;
    }

    async function save() {
        if (!dirty) return;
        dirty = false;

        const body = new FormData(form);
        body.delete('_token');

        try {
            const res = await fetch(`{{ route('laas.apply.draft') }}`, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': token, 'X-Requested-With': 'XMLHttpRequest' },
                body,
            });
            const json = await res.json();
            if (json.success) showSaved(json.saved_at);
        } catch (e) {
            // A failed autosave is not worth interrupting the applicant over —
            // the next keystroke schedules another attempt, and Submit is what
            // actually persists the application.
            dirty = true;
        }
    }

    form.addEventListener('input', () => {
        dirty = true;
        clearTimeout(timer);
        timer = setTimeout(save, 2000);
    });

    // Don't autosave a form the applicant is in the middle of submitting.
    form.addEventListener('submit', () => { clearTimeout(timer); dirty = false; });
})();
</script>
@endpush
