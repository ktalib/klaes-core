@extends('laas.layouts.portal')

@section('title', 'New application — LAAS Portal')

@section('content')
@php
    use App\Support\Laas\SroFormSchema;

    /** Current value for a field: what was posted, else the draft, else blank. */
    $v = fn ($key, $fallback = '') => old($key, $answers[$key] ?? $fallback);

    /**
     * Grid spans as literal class strings. Tailwind's play CDN reads the
     * rendered DOM, so an interpolated "sm:col-span-{{ $n }}" would work — but
     * a lookup table cannot silently produce a class that does not exist.
     */
    $span = [
        1 => 'md:col-span-1',  2 => 'md:col-span-2',  3 => 'md:col-span-3',
        4 => 'md:col-span-4',  5 => 'md:col-span-5',  6 => 'md:col-span-6',
        7 => 'md:col-span-7',  8 => 'md:col-span-8',  9 => 'md:col-span-9',
        10 => 'md:col-span-10', 11 => 'md:col-span-11', 12 => 'md:col-span-12',
    ];
    $col = fn ($n) => 'col-span-12 ' . ($span[$n] ?? $span[12]);
@endphp

<div class="mx-auto max-w-6xl">

    <div class="mb-6 flex flex-wrap items-start justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold" style="color: var(--ink);">
                Application for Statutory Right of Occupancy
            </h1>
            <p class="mt-1 text-sm" style="color: var(--ink-soft);">
                Answer every required field, then submit. Your answers are saved as you type.
            </p>
        </div>
        <p id="laas-autosave" class="hidden items-center gap-1.5 text-xs" style="color: var(--ink-soft);">
            <i data-lucide="cloud-check" class="h-3.5 w-3.5" aria-hidden="true"></i><span></span>
        </p>
    </div>

    {{-- ---------- Land type + statutory heading, on one line ---------- --}}
    <div class="laas-card mb-6 p-5">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div class="flex flex-wrap items-center gap-2" role="tablist">
                <span class="laas-eyebrow mr-1" style="color: var(--ink-faint);">Land type</span>
                @foreach(SroFormSchema::TYPES as $key => $meta)
                    @php $active = $key === $type; @endphp
                    <a href="{{ route('laas.apply.form', ['type' => $key]) }}"
                       role="tab" aria-selected="{{ $active ? 'true' : 'false' }}"
                       class="inline-flex items-center gap-2 rounded-lg border px-3.5 py-2 text-sm font-bold transition"
                       style="{{ $active
                            ? 'background: var(--brand); border-color: var(--brand); color: var(--on-brand);'
                            : 'background: transparent; border-color: var(--border); color: var(--ink-soft);' }}">
                        <i data-lucide="{{ $meta['icon'] }}" class="h-4 w-4" aria-hidden="true"></i>
                        {{ $meta['label'] }}
                    </a>
                @endforeach
            </div>

            <p class="text-xs" style="color: var(--ink-faint);">Switching keeps what you have typed.</p>
        </div>
    </div>

    @if($errors->any())
        <div role="alert" class="mb-6 rounded-xl border p-4"
             style="border-color: var(--danger); background: rgba(159,18,57,.07);">
            <p class="mb-2 text-sm font-bold" style="color: var(--danger);">Please correct the following:</p>
            <ul class="list-inside list-disc space-y-1">
                @foreach($errors->all() as $error)
                    <li class="text-sm" style="color: var(--danger);">{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form id="laas-apply-form" method="POST" action="{{ route('laas.apply.store') }}"
          enctype="multipart/form-data" class="space-y-5">
        @csrf
        <input type="hidden" name="land_type" value="{{ $type }}">

        @foreach($sections as $section)
            <section class="laas-card p-6">
                <div class="mb-5 flex flex-wrap items-baseline gap-x-3 gap-y-1 border-b pb-3"
                     style="border-color: var(--border);">
                    <h2 class="flex items-center gap-2 text-sm font-extrabold uppercase tracking-widest"
                        style="color: var(--ink);">
                        <i data-lucide="{{ $section['icon'] }}" class="h-4 w-4" style="color: var(--brand);" aria-hidden="true"></i>
                        {{ $section['title'] }}
                    </h2>
                    @if(!empty($section['note']))
                        <p class="text-xs" style="color: var(--ink-faint);">{{ $section['note'] }}</p>
                    @endif
                </div>

                <div class="grid grid-cols-12 gap-x-5 gap-y-4">
                    @foreach($section['fields'] as $field)
                        @php
                            $key      = $field['key'];
                            $required = $field['required'] ?? false;
                            $showIf   = $field['show_if'] ?? null;
                            $showWhen = $showIf['field'] ?? null;
                            $showVal  = $showIf['value'] ?? null;
                            $initialVisible = !$showWhen || (string) old($showWhen, $answers[$showWhen] ?? '') === (string) $showVal;
                        @endphp

                        {{-- ---- Address block: its own 12-column row ---- --}}
                        @if($field['type'] === 'address')
                            <fieldset class="col-span-12 rounded-xl border p-4"
                                      data-address-prefix="{{ $field['prefix'] }}"
                                      data-address-output="{{ $key }}"
                                      style="border-color: var(--border);">
                                <legend class="px-2 text-xs font-bold" style="color: var(--ink-soft);">
                                    {!! $field['label'] !!}
                                </legend>

                                <div class="grid grid-cols-12 gap-x-4 gap-y-3">
                                    @foreach(SroFormSchema::addressParts() as $part => $meta)
                                        @php
                                            $name = $field['prefix'] . $part;
                                            // The LGA list cascades off the state select in this same block.
                                            $parent = $part === 'lga' ? $field['prefix'] . 'state' : null;
                                        @endphp
                                        <div class="{{ $col($meta['col']) }}">
                                            <label for="{{ $name }}" class="block text-xs font-semibold"
                                                   style="color: var(--ink-soft);">{{ $meta['label'] }}</label>

                                            @if($meta['type'] === 'select')
                                                @php
                                                    // Street and district have real *_other columns; a value
                                                    // there is how a resumed draft remembers it was an "Other".
                                                    $companion   = isset($meta['other_key']) ? $field['prefix'] . $meta['other_key'] : null;
                                                    $specifyName = SroFormSchema::specifyKey($name);
                                                @endphp
                                                <select id="{{ $name }}" name="{{ $name }}" class="laas-input mt-1.5"
                                                        data-lookup="{{ $meta['lookup'] }}"
                                                        @if($parent) data-parent="{{ $parent }}" @endif
                                                        @if(!empty($meta['other'])) data-allow-other="1" @endif
                                                        data-selected="{{ $v($name) }}">
                                                    <option value="">-- Select --</option>
                                                </select>
                                                @if(!empty($meta['other']))
                                                    <input type="text" id="{{ $specifyName }}" name="{{ $specifyName }}"
                                                           value="{{ old($specifyName, $companion ? $v($companion) : '') }}"
                                                           placeholder="Specify {{ strip_tags($meta['label']) }}"
                                                           data-specify-for="{{ $name }}"
                                                           class="laas-input mt-1.5 hidden">
                                                @endif
                                            @elseif($meta['type'] === 'combobox')
                                                <input id="{{ $name }}" type="text" name="{{ $name }}"
                                                       value="{{ $v($name) }}" list="dl-{{ $name }}"
                                                       autocomplete="off" data-lookup="{{ $meta['lookup'] }}"
                                                       class="laas-input mt-1.5">
                                                <datalist id="dl-{{ $name }}"></datalist>
                                            @else
                                                <input id="{{ $name }}" type="text" name="{{ $name }}"
                                                       value="{{ $v($name) }}" class="laas-input mt-1.5">
                                            @endif
                                        </div>
                                    @endforeach
                                </div>

                                <div class="mt-3">
                                    <label for="{{ $key }}" class="block text-xs font-semibold" style="color: var(--ink-soft);">
                                        Full address
                                    </label>
                                    <textarea id="{{ $key }}" name="{{ $key }}" rows="2" readonly
                                              class="laas-input mt-1.5"
                                              style="background: rgba(2, 8, 23, 0.03); color: var(--ink-soft);">{{ $v($key) }}</textarea>
                                </div>
                            </fieldset>

                        {{-- ---- Prior allocations (residential) ---- --}}
                        @elseif($field['type'] === 'prev_allocations')
                            @php $rows = json_decode((string) ($answers['prev_allocation_details'] ?? ''), true) ?: []; @endphp
                            <fieldset class="col-span-12 rounded-xl border p-4 {{ $initialVisible ? '' : 'hidden' }}"
                                      @if($showWhen) data-show-if-field="{{ $showWhen }}" data-show-if-value="{{ $showVal }}" @endif
                                      style="border-color: var(--border);">
                                <legend class="px-2 text-xs font-bold" style="color: var(--ink-soft);">
                                    {!! $field['label'] !!}
                                </legend>
                                <div class="space-y-3">
                                    @for($i = 1; $i <= SroFormSchema::PREV_ALLOCATION_ROWS; $i++)
                                        @php $row = $rows[$i - 1] ?? []; @endphp
                                        <div class="grid grid-cols-12 gap-x-4 gap-y-2">
                                            <div class="col-span-12 md:col-span-3">
                                                <label for="prev_plot_{{ $i }}" class="block text-[11px] font-semibold"
                                                       style="color: var(--ink-faint);">({{ $i }}) a. Plot No.</label>
                                                <input id="prev_plot_{{ $i }}" type="text" name="prev_plot_{{ $i }}"
                                                       value="{{ old('prev_plot_' . $i, $row['plot_no'] ?? '') }}"
                                                       class="laas-input mt-1.5">
                                            </div>
                                            <div class="col-span-12 md:col-span-5">
                                                <label for="prev_location_{{ $i }}" class="block text-[11px] font-semibold"
                                                       style="color: var(--ink-faint);">b. Location</label>
                                                <input id="prev_location_{{ $i }}" type="text" name="prev_location_{{ $i }}"
                                                       value="{{ old('prev_location_' . $i, $row['location'] ?? '') }}"
                                                       class="laas-input mt-1.5">
                                            </div>
                                            <div class="col-span-12 md:col-span-4">
                                                <label for="prev_cert_{{ $i }}" class="block text-[11px] font-semibold"
                                                       style="color: var(--ink-faint);">c. Cert. of Occupancy No.</label>
                                                <input id="prev_cert_{{ $i }}" type="text" name="prev_cert_{{ $i }}"
                                                       value="{{ old('prev_cert_' . $i, $row['cert_no'] ?? '') }}"
                                                       class="laas-input mt-1.5">
                                            </div>
                                        </div>
                                    @endfor
                                </div>
                            </fieldset>

                        {{-- ---- Select ---- --}}
                        @elseif($field['type'] === 'select')
                            <div class="{{ $col($field['col']) }} {{ $initialVisible ? '' : 'hidden' }}"
                                 @if($showWhen) data-show-if-field="{{ $showWhen }}" data-show-if-value="{{ $showVal }}" @endif>
                                <label for="{{ $key }}" class="block text-sm font-bold" style="color: var(--ink);">
                                    {!! $field['label'] !!}
                                    @if($required)<span style="color: var(--danger);">*</span>@endif
                                </label>
                                @php
                                    $options     = $field['options'] ?? [];
                                    $cur         = $v($key);
                                    $specifyName = SroFormSchema::specifyKey($key);
                                    // A stored answer that is not on the list can only have come from
                                    // "Other" — reopen it that way so a resumed draft keeps the answer.
                                    $isOther = !empty($field['other']) && $options
                                               && $cur !== '' && !in_array($cur, $options, true);
                                @endphp
                                <select id="{{ $key }}" name="{{ $key }}" class="laas-input mt-1.5"
                                        @if($required) required @endif
                                        @if(!empty($field['other'])) data-allow-other="1" @endif
                                        @if(!empty($field['lookup'])) data-lookup="{{ $field['lookup'] }}" data-selected="{{ $cur }}" @endif
                                        @if(!empty($field['parent'])) data-parent="{{ $field['parent'] }}" @endif>
                                    <option value="">-- Select --</option>
                                    @foreach($options as $option)
                                        <option value="{{ $option }}"
                                                @selected($isOther ? $option === SroFormSchema::OTHER : $cur === $option)>{{ $option }}</option>
                                    @endforeach
                                </select>
                                @if(!empty($field['other']))
                                    <input type="text" id="{{ $specifyName }}" name="{{ $specifyName }}"
                                           value="{{ old($specifyName, $isOther ? $cur : '') }}"
                                           placeholder="Please specify"
                                           data-specify-for="{{ $key }}"
                                           class="laas-input mt-1.5 hidden">
                                @endif
                                @if(!empty($field['help']))
                                    <p class="mt-1 text-xs" style="color: var(--ink-faint);">{{ $field['help'] }}</p>
                                @endif
                            </div>

                        {{-- ---- Textarea ---- --}}
                        @elseif($field['type'] === 'textarea')
                            <div class="{{ $col($field['col']) }} {{ $initialVisible ? '' : 'hidden' }}"
                                 @if($showWhen) data-show-if-field="{{ $showWhen }}" data-show-if-value="{{ $showVal }}" @endif>
                                <label for="{{ $key }}" class="block text-sm font-bold" style="color: var(--ink);">
                                    {!! $field['label'] !!}
                                    @if($required)<span style="color: var(--danger);">*</span>@endif
                                </label>
                                <textarea id="{{ $key }}" name="{{ $key }}" rows="2" class="laas-input mt-1.5"
                                          @if($required) required @endif>{{ $v($key) }}</textarea>
                                @if(!empty($field['help']))
                                    <p class="mt-1 text-xs" style="color: var(--ink-faint);">{{ $field['help'] }}</p>
                                @endif
                            </div>

                        {{-- ---- Text / number ---- --}}
                        @else
                            <div class="{{ $col($field['col']) }} {{ $initialVisible ? '' : 'hidden' }}"
                                 @if($showWhen) data-show-if-field="{{ $showWhen }}" data-show-if-value="{{ $showVal }}" @endif>
                                <label for="{{ $key }}" class="block text-sm font-bold" style="color: var(--ink);">
                                    {!! $field['label'] !!}
                                    @if($required)<span style="color: var(--danger);">*</span>@endif
                                </label>
                                <input id="{{ $key }}" name="{{ $key }}" value="{{ $v($key) }}"
                                       type="{{ $field['type'] === 'number' ? 'number' : 'text' }}"
                                       @if($field['type'] === 'number') min="0" @endif
                                       @if($required) required @endif
                                       @if(!empty($field['help'])) aria-describedby="{{ $key }}-help" @endif
                                       class="laas-input mt-1.5">
                                @if(!empty($field['help']))
                                    <p id="{{ $key }}-help" class="mt-1 text-xs" style="color: var(--ink-faint);">{{ $field['help'] }}</p>
                                @endif
                            </div>
                        @endif
                    @endforeach

                    {{-- The passport photograph sits beside the applicant's name on
                         the paper form, so it belongs in the first section. --}}
                    @if($loop->first)
                        <div class="col-span-12 md:col-span-6">
                            <label for="passport_photo" class="block text-sm font-bold" style="color: var(--ink);">
                                Passport photograph
                            </label>
                            <input id="passport_photo" type="file" name="passport_photo"
                                   accept=".jpg,.jpeg,.png" aria-describedby="passport-help"
                                   class="laas-input mt-1.5 file:mr-3 file:rounded file:border-0 file:px-3 file:py-1.5 file:text-xs file:font-semibold">
                            <p id="passport-help" class="mt-1 text-xs" style="color: var(--ink-faint);">
                                JPG or PNG, up to 5&nbsp;MB. You can also add it later.
                            </p>
                        </div>
                    @endif
                </div>
            </section>
        @endforeach

        <div class="laas-card flex flex-wrap items-center justify-between gap-3 p-5">
            <p class="text-xs" style="color: var(--ink-soft);">
                Other documents can be uploaded from the application page once you have submitted.
            </p>
            <button type="submit" class="laas-btn px-6 py-3 text-sm">
                <i data-lucide="send" class="h-4 w-4" aria-hidden="true"></i> Submit application
            </button>
        </div>
    </form>
</div>
@endsection

@push('scripts')
<script>
(function () {
    var form  = document.getElementById('laas-apply-form');
    var token = document.querySelector('meta[name="csrf-token"]').content;

    var URLS = {
        states:   '{{ route('laas.api.reference.states') }}',
        lgas:     '{{ route('laas.api.reference.lgas') }}',
        district: '{{ route('laas.api.reference.districts') }}',
        street:   '{{ route('laas.api.reference.streets') }}'
    };

    function getJson(url) {
        return fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (r) { return r.json(); })
            .then(function (j) { return j.data || []; })
            .catch(function () { return []; });
    }

    var OTHER = @json(\App\Support\Laas\SroFormSchema::OTHER);

    /** Show a select's "Specify" box only while it is on "Other". */
    function toggleSpecify(select) {
        var box = document.querySelector('[data-specify-for="' + select.id + '"]');
        if (!box) { return; }

        var on = select.value === OTHER;
        box.classList.toggle('hidden', !on);
        if (!on) { box.value = ''; }
    }

    function fillSelect(select, rows, placeholder) {
        var wanted  = select.dataset.selected || select.value || '';
        var matched = false;

        var html = '<option value="">' + placeholder + '</option>' + rows.map(function (r) {
            var name = String(r.name);
            // Values are stored as NAMES, not ids: oss_applications keeps
            // addresses as text, and a numeric id there would be meaningless
            // to the officer reading the file.
            var hit = name.toLowerCase() === String(wanted).toLowerCase();
            if (hit) { matched = true; }
            return '<option value="' + name + '"' + (hit ? ' selected' : '') + '>' + name + '</option>';
        }).join('');

        if (select.dataset.allowOther) {
            html += '<option value="' + OTHER + '">' + OTHER + '</option>';
        }

        select.innerHTML = html;

        // A stored answer the reference table does not contain can only have
        // come from "Other". Reopen it that way, or a resumed draft would
        // silently lose the answer.
        if (!matched && wanted && select.dataset.allowOther) {
            select.value = OTHER;
            var box = document.querySelector('[data-specify-for="' + select.id + '"]');
            if (box && !box.value) { box.value = wanted; }
        }

        toggleSpecify(select);
    }

    // Every Other-capable select, including the plain ones fillSelect never
    // touches (nationality, occupation), gets the reveal behaviour.
    form.querySelectorAll('select[data-allow-other]').forEach(function (sel) {
        sel.addEventListener('change', function () {
            toggleSpecify(sel);
            var box = document.querySelector('[data-specify-for="' + sel.id + '"]');
            if (box && sel.value === OTHER) { box.focus(); }
        });
        toggleSpecify(sel);   // server-rendered selects start in the right state
    });

    // ---- States, and the LGA lists that cascade from them -------------------
    var stateSelects = form.querySelectorAll('select[data-lookup="state"]');
    var lgaSelects   = form.querySelectorAll('select[data-lookup="lga"]');
    var districtSelects = form.querySelectorAll('select[data-lookup="district"]');
    var streetSelects   = form.querySelectorAll('select[data-lookup="street"]');

    var statesPromise = stateSelects.length ? getJson(URLS.states) : Promise.resolve([]);

    statesPromise.then(function (states) {
        stateSelects.forEach(function (sel) { fillSelect(sel, states, '-- Select state --'); });

        lgaSelects.forEach(function (lgaSel) {
            var parentId = lgaSel.dataset.parent;
            var stateSel = parentId ? document.getElementById(parentId) : null;

            function loadLgas() {
                if (!stateSel || !stateSel.value) {
                    // Kano LGAs are the sensible default: this is the Kano State
                    // portal, and most applicants never change the state.
                    getJson(URLS.lgas).then(function (rows) {
                        fillSelect(lgaSel, rows, '-- Select L.G.A. --');
                    });
                    return;
                }
                var match = states.filter(function (s) {
                    return String(s.name).toLowerCase() === stateSel.value.toLowerCase();
                })[0];
                if (!match) { return; }
                getJson(URLS.lgas + '?state_id=' + encodeURIComponent(match.id)).then(function (rows) {
                    fillSelect(lgaSel, rows, '-- Select L.G.A. --');
                });
            }

            if (stateSel) {
                stateSel.addEventListener('change', function () {
                    lgaSel.dataset.selected = '';   // the old LGA belongs to the old state
                    loadLgas();
                });
            }
            loadLgas();
        });
    });

    if (districtSelects.length) {
        getJson(URLS.district + '?limit=5000').then(function (rows) {
            districtSelects.forEach(function (sel) { fillSelect(sel, rows, '-- Select district --'); });
        });
    }

    if (streetSelects.length) {
        getJson(URLS.street + '?limit=5000').then(function (rows) {
            streetSelects.forEach(function (sel) { fillSelect(sel, rows, '-- Select street --'); });
        });
    }

    function buildAddress(prefix) {
        var order = ['plot', 'street', 'district', 'lga', 'state'];
        var parts = [];
        order.forEach(function (part) {
            var el = document.getElementById(prefix + part);
            var value = String(el ? (el.value || '') : '').trim();
            if (value) parts.push(value);
        });
        return parts.join(', ');
    }

    function bindAddressBuilders() {
        form.querySelectorAll('[data-address-prefix][data-address-output]').forEach(function (fieldSet) {
            var prefix = fieldSet.dataset.addressPrefix;
            var outputId = fieldSet.dataset.addressOutput;
            var output = document.getElementById(outputId);
            if (!prefix || !outputId || !output) return;

            function sync() {
                output.value = buildAddress(prefix);
            }

            ['plot', 'street', 'district', 'lga', 'state'].forEach(function (part) {
                var el = document.getElementById(prefix + part);
                if (!el || el.dataset.addressWired === '1') return;
                el.dataset.addressWired = '1';
                el.addEventListener('input', sync);
                el.addEventListener('change', sync);
            });

            sync();
        });
    }

    function applyConditionalVisibility() {
        form.querySelectorAll('[data-show-if-field]').forEach(function (el) {
            var sourceId = el.dataset.showIfField;
            var expected = String(el.dataset.showIfValue || '').toLowerCase();
            var source = sourceId ? document.getElementById(sourceId) : null;
            var actual = String(source ? source.value : '').toLowerCase();
            var visible = source && actual === expected;
            el.classList.toggle('hidden', !visible);
        });
    }

    var wiredConditions = {};
    form.querySelectorAll('[data-show-if-field]').forEach(function (el) {
        var sourceId = el.dataset.showIfField;
        if (!sourceId || wiredConditions[sourceId]) return;
        var source = document.getElementById(sourceId);
        if (!source) return;
        wiredConditions[sourceId] = true;
        source.addEventListener('change', applyConditionalVisibility);
    });

    applyConditionalVisibility();
    bindAddressBuilders();

    // ---- Autosave ----------------------------------------------------------
    var badge     = document.getElementById('laas-autosave');
    var badgeText = badge.querySelector('span');
    var timer = null;
    var dirty = false;

    function payload() {
        var body = new FormData(form);
        body.delete('_token');
        body.delete('passport_photo');   // files are stored on submit, not autosave
        return body;
    }

    function save() {
        if (!dirty) return;
        dirty = false;

        fetch('{{ route('laas.apply.draft') }}', {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': token, 'X-Requested-With': 'XMLHttpRequest' },
            body: payload()
        })
        .then(function (r) { return r.json(); })
        .then(function (json) {
            if (!json.success) return;
            badge.classList.remove('hidden');
            badge.classList.add('inline-flex');
            badgeText.textContent = 'Saved ' + json.saved_at;
        })
        .catch(function () {
            // A failed autosave is not worth interrupting the applicant over —
            // the next keystroke schedules another attempt, and Submit is what
            // actually persists the application.
            dirty = true;
        });
    }

    form.addEventListener('input', function () {
        dirty = true;
        clearTimeout(timer);
        timer = setTimeout(save, 2000);
    });
    form.addEventListener('change', function () { dirty = true; });

    form.addEventListener('submit', function () { clearTimeout(timer); dirty = false; });

    // Switching land type is a plain link, so flush pending answers first —
    // otherwise the last two seconds of typing would be lost on the hop.
    document.querySelectorAll('[role="tab"]').forEach(function (tab) {
        tab.addEventListener('click', function (e) {
            if (!dirty) return;
            e.preventDefault();
            var href = tab.getAttribute('href');
            fetch('{{ route('laas.apply.draft') }}', {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': token, 'X-Requested-With': 'XMLHttpRequest' },
                body: payload()
            }).finally(function () { window.location.href = href; });
        });
    });
})();
</script>
@endpush
