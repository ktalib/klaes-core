@extends('layouts.app')
@section('page-title'){{ $PageTitle ?? 'Special Assignment – Notice Response' }}@endsection

{{--
    Allottee's Response Form to Notice of Contravention of Land Use, keyed in
    from the Ministry's paper form (docs/templates/SPAS_Notice_Response.html).
    A step-by-step wizard over the form's sections (A–E); one <form>, so the
    field names and the save route are the same as before. Prefilled from the
    notice and its land record.
--}}
@php
    use App\Models\SpaNoticeResponse;

    $r      = $response;
    $saved  = $r->exists;
    $d      = fn ($v) => $v ? \Carbon\Carbon::parse($v)->format('Y-m-d') : '';
    $val    = fn ($k) => old($k, $r->{$k});
    $dval   = fn ($k) => old($k, $d($r->{$k}));
    $opt    = old('response_option', $r->response_option);
    $dec    = old('director_decision', $r->director_decision);
    $atts   = (array) old('attachments', (array) $r->attachments);
    $app    = $notice->application;
    $spaId  = optional($app)->spa_id;
    $fileNo = $app ? ($app->file_number ?: 'Not known') : $notice->file_number;
    $input  = 'w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:border-yellow-500';
    $label  = 'block text-xs font-semibold text-gray-600 mb-1';
    $steps  = [
        1 => ['Notice & Allottee', 'Section A'],
        2 => ['Property',          'Section B'],
        3 => ['Response',          'Section C'],
        4 => ['Undertaking',       'Section D & attachments'],
        5 => ['Official Use',      'Section E'],
        6 => ['Review & Save',     'Check, then save'],
    ];
    // After a failed save, open the step holding the first field in error.
    $stepOf = [
        'reference_no' => 1, 'original_notice_date' => 1, 'response_date' => 1, 'allottee_name' => 1, 'allottee_address' => 1,
        'phone' => 1, 'email' => 1, 'representative_name' => 1,
        'plot_number' => 2, 'location' => 2, 'approved_land_use' => 2, 'current_land_use' => 2,
        'response_option' => 3, 'appeal_grounds' => 3, 'other_details' => 3,
        'declaration_signed' => 4, 'allottee_signed_date' => 4, 'attachments' => 4,
        'received_by' => 5, 'receiver_designation' => 5, 'date_received' => 5, 'time_received' => 5,
        'action_remarks' => 5, 'director_decision' => 5, 'referred_to' => 5, 'director_date' => 5,
    ];
    $startStep = 1;
    foreach (array_keys($errors->getMessages()) as $field) {
        $startStep = $stepOf[explode('.', $field)[0]] ?? 1;
        break;
    }
@endphp

@section('content')
<div class="flex-1 overflow-auto flex flex-col min-h-full">
    @include('admin.header')

    <div class="p-6 flex-1">
        {{-- Heading --}}
        <div class="flex flex-wrap items-start justify-between gap-3 mb-5">
            <div>
                <a href="{{ route('special-assignment.notice') }}" class="text-xs text-gray-500 hover:text-gray-700">&larr; Back to Notice</a>
                <h1 class="text-2xl font-bold text-gray-800 mt-1">Allottee's Response</h1>
                <p class="text-sm text-gray-500 mt-1">
                    Response to the First Serve on
                    <b class="text-gray-700">{{ $spaId ?: $notice->file_number }}</b>@if($spaId && $fileNo && $fileNo !== $spaId && $fileNo !== 'Not known') · File {{ $fileNo }}@endif
                    — served {{ optional($notice->served_date)->format('d/m/Y') ?? '—' }}.
                    @if($saved)<span class="ml-1 px-2 py-0.5 rounded-full text-xs bg-blue-100 text-blue-700">Recorded</span>@endif
                </p>
            </div>
            <div class="flex gap-2">
                <a href="{{ route('special-assignment.notice.response.print', [$notice->id, 'blank' => 1]) }}" target="_blank" rel="noopener"
                   class="inline-flex items-center gap-1.5 px-4 py-2 border border-gray-300 rounded-lg text-sm text-gray-700 bg-white hover:bg-gray-50">
                    <i data-lucide="printer" class="h-4 w-4"></i> Print blank form</a>
                @if($saved)
                <a href="{{ route('special-assignment.notice.response.print', $notice->id) }}" target="_blank" rel="noopener"
                   class="inline-flex items-center gap-1.5 px-4 py-2 border border-gray-300 rounded-lg text-sm text-gray-700 bg-white hover:bg-gray-50">
                    <i data-lucide="file-text" class="h-4 w-4"></i> Print response</a>
                @endif
            </div>
        </div>

        @if(session('success'))
            <div class="mb-4 p-3 rounded-lg bg-green-50 border border-green-200 text-sm text-green-800">{{ session('success') }}</div>
        @endif
        @if($errors->any())
            <div class="mb-4 p-3 rounded-lg bg-red-50 border border-red-200 text-sm text-red-700">
                <ul class="list-disc pl-5">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
            </div>
        @endif

        @if($notice->spa_application_id)
            {{-- Where this record stands: Record → … → Certificate. --}}
            <div id="resp-pipeline" class="bg-white rounded-xl border border-gray-200 shadow-sm px-5 py-4 mb-4"></div>
        @endif

        <div class="bg-white rounded-xl border border-gray-200 shadow-sm">
            {{-- Stepper --}}
            <ol id="resp-steps" class="flex flex-wrap border-b border-gray-200">
                @foreach($steps as $n => [$title, $sub])
                    <li class="flex-1" style="min-width:140px">
                        <button type="button" data-goto="{{ $n }}"
                            class="resp-step w-full flex items-center gap-3 px-4 py-3 text-left border-b-2 border-transparent">
                            <span class="resp-step-num flex-none w-8 h-8 rounded-full flex items-center justify-center text-sm font-bold">{{ $n }}</span>
                            <span class="min-w-0">
                                <span class="block text-sm font-semibold resp-step-title">{{ $title }}</span>
                                <span class="block text-xs text-gray-400 truncate">{{ $sub }}</span>
                            </span>
                        </button>
                    </li>
                @endforeach
            </ol>

            <form id="resp-form" method="POST" action="{{ route('special-assignment.notice.response.save', $notice->id) }}" novalidate>
                @csrf

                <div class="p-6">
                    {{-- ── Step 1: Notice & Allottee (Section A) ── --}}
                    <section class="resp-panel" data-step="1">
                        <h2 class="text-base font-bold text-gray-800">Notice &amp; Allottee</h2>
                        <p class="text-xs text-gray-500 mb-5">The notice being answered, and Section A: who is responding.</p>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                            <div><label class="{{ $label }}">Reference No</label><input name="reference_no" value="{{ $val('reference_no') }}" class="{{ $input }}"></div>
                            <div><label class="{{ $label }}">Date of Original Notice</label><input type="date" name="original_notice_date" value="{{ $dval('original_notice_date') }}" class="{{ $input }}"></div>
                            <div><label class="{{ $label }}">Date of This Response <span class="text-red-500">*</span></label><input type="date" name="response_date" value="{{ $dval('response_date') }}" data-required="Enter the date of this response." class="{{ $input }}"></div>
                        </div>
                        <h3 class="text-xs font-bold text-gray-500 uppercase tracking-wide border-b pb-2 mb-4">Section A: Allottee Details</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="md:col-span-2"><label class="{{ $label }}">Full Name of Allottee / Company Name <span class="text-red-500">*</span></label><input name="allottee_name" value="{{ $val('allottee_name') }}" data-required="Enter the allottee's or company's name." class="{{ $input }}"></div>
                            <div class="md:col-span-2"><label class="{{ $label }}">Address of Allottee</label><input name="allottee_address" value="{{ $val('allottee_address') }}" class="{{ $input }}"></div>
                            <div><label class="{{ $label }}">Phone Number</label><input name="phone" value="{{ $val('phone') }}" class="{{ $input }}"></div>
                            <div><label class="{{ $label }}">Email</label><input type="email" name="email" value="{{ $val('email') }}" class="{{ $input }}"></div>
                            <div class="md:col-span-2"><label class="{{ $label }}">Representative Name (if Company/Organization)</label><input name="representative_name" value="{{ $val('representative_name') }}" class="{{ $input }}"></div>
                        </div>
                    </section>

                    {{-- ── Step 2: Property (Section B) ── --}}
                    <section class="resp-panel hidden" data-step="2">
                        <h2 class="text-base font-bold text-gray-800">Property</h2>
                        <p class="text-xs text-gray-500 mb-5">Section B: the property the notice is about.</p>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div><label class="{{ $label }}">SPAS ID</label><input value="{{ $spaId ?? '—' }}" readonly class="{{ $input }} bg-gray-100 text-gray-600 font-semibold"></div>
                            <div><label class="{{ $label }}">File Number</label><input value="{{ $fileNo }}" readonly class="{{ $input }} bg-gray-100 text-gray-600"></div>
                            <div><label class="{{ $label }}">Plot Number</label><input name="plot_number" value="{{ $val('plot_number') }}" class="{{ $input }}"></div>
                            <div class="md:col-span-3"><label class="{{ $label }}">Location / Layout Name</label><input name="location" value="{{ $val('location') }}" class="{{ $input }}"></div>
                            <div><label class="{{ $label }}">Approved Land Use</label><input name="approved_land_use" value="{{ $val('approved_land_use') }}" class="{{ $input }}"></div>
                            <div class="md:col-span-2"><label class="{{ $label }}">Current Land Use (as observed by Ministry)</label><input name="current_land_use" value="{{ $val('current_land_use') }}" class="{{ $input }}"></div>
                        </div>
                    </section>

                    {{-- ── Step 3: Response (Section C) ── --}}
                    <section class="resp-panel hidden" data-step="3">
                        <h2 class="text-base font-bold text-gray-800">Acknowledgement &amp; Response</h2>
                        <p class="text-xs text-gray-500 mb-5">Section C: tick the box that reflects the allottee's response.</p>
                        <div class="grid grid-cols-1 lg:grid-cols-2 gap-3">
                            @foreach(SpaNoticeResponse::OPTIONS as $key => [$title, $text])
                                <label class="resp-opt-box flex gap-3 p-4 border rounded-xl cursor-pointer hover:bg-gray-50 {{ $opt === $key ? 'border-yellow-500 bg-yellow-50' : 'border-gray-200' }}">
                                    <input type="radio" name="response_option" value="{{ $key }}" {{ $opt === $key ? 'checked' : '' }} class="mt-1 resp-opt">
                                    <span>
                                        <span class="block text-sm font-semibold text-gray-800">{{ $title }}</span>
                                        @if($text)<span class="block text-xs text-gray-600 mt-1 leading-relaxed">{{ $text }}</span>@endif
                                    </span>
                                </label>
                            @endforeach
                        </div>
                        <div id="wrap-appeal" class="mt-4 {{ $opt === 'appeal' ? '' : 'hidden' }}">
                            <label class="{{ $label }}">Grounds of explanation / appeal <span class="text-red-500">*</span></label>
                            <textarea name="appeal_grounds" rows="4" class="{{ $input }}">{{ $val('appeal_grounds') }}</textarea>
                        </div>
                        <div id="wrap-other" class="mt-4 {{ $opt === 'other' ? '' : 'hidden' }}">
                            <label class="{{ $label }}">Please specify <span class="text-red-500">*</span></label>
                            <textarea name="other_details" rows="4" class="{{ $input }}">{{ $val('other_details') }}</textarea>
                        </div>
                    </section>

                    {{-- ── Step 4: Undertaking + attachments (Section D) ── --}}
                    <section class="resp-panel hidden" data-step="4">
                        <h2 class="text-base font-bold text-gray-800">Statement of Facts &amp; Undertaking</h2>
                        <p class="text-xs text-gray-500 mb-5">Section D, and the documents attached to the response.</p>
                        <div class="p-4 rounded-lg bg-gray-50 border border-gray-200 text-xs text-gray-600 leading-relaxed mb-4">
                            The allottee declares the information is true and correct, understands that failure to respond or false information may lead to the
                            invocation of the Ministry's powers under Section 9(4) or 28(5) of the Land Use Act, and undertakes to comply with the Ministry's
                            directives within the stipulated timeframe.
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 items-end mb-6">
                            <label class="flex items-center gap-2 text-sm text-gray-700">
                                <input type="hidden" name="declaration_signed" value="0">
                                <input type="checkbox" name="declaration_signed" value="1" {{ old('declaration_signed', $r->declaration_signed) ? 'checked' : '' }}>
                                Signed by the allottee
                            </label>
                            <div><label class="{{ $label }}">Date signed</label><input type="date" name="allottee_signed_date" value="{{ $dval('allottee_signed_date') }}" class="{{ $input }}"></div>
                        </div>
                        <h3 class="text-xs font-bold text-gray-500 uppercase tracking-wide border-b pb-2 mb-3">Checklist of Attachments</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
                            @foreach(SpaNoticeResponse::ATTACHMENTS as $key => $text)
                                <label class="flex items-start gap-2 text-sm text-gray-700 p-2 rounded hover:bg-gray-50">
                                    <input type="checkbox" name="attachments[]" value="{{ $key }}" class="mt-0.5" {{ in_array($key, $atts, true) ? 'checked' : '' }}> {{ $text }}
                                </label>
                            @endforeach
                        </div>
                    </section>

                    {{-- ── Step 5: Official use (Section E) ── --}}
                    <section class="resp-panel hidden" data-step="5">
                        <h2 class="text-base font-bold text-gray-800">For Official Use Only (Ministry)</h2>
                        <p class="text-xs text-gray-500 mb-5">Section E: receipt of the response and the Director's decision.</p>
                        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                            <div class="md:col-span-2"><label class="{{ $label }}">Received by (Name)</label><input name="received_by" value="{{ $val('received_by') }}" class="{{ $input }}"></div>
                            <div class="md:col-span-2"><label class="{{ $label }}">Designation</label><input name="receiver_designation" value="{{ $val('receiver_designation') }}" class="{{ $input }}"></div>
                            <div><label class="{{ $label }}">Date Received</label><input type="date" name="date_received" value="{{ $dval('date_received') }}" class="{{ $input }}"></div>
                            <div><label class="{{ $label }}">Time</label><input type="time" name="time_received" value="{{ $val('time_received') }}" class="{{ $input }}"></div>
                            <div class="md:col-span-4"><label class="{{ $label }}">Action Taken / Remarks</label><textarea name="action_remarks" rows="3" class="{{ $input }}">{{ $val('action_remarks') }}</textarea></div>
                            <div class="md:col-span-2">
                                <label class="{{ $label }}">Director's Approval</label>
                                <div class="flex flex-wrap items-center gap-4 pt-1">
                                    @foreach(SpaNoticeResponse::DECISIONS as $key => $text)
                                        <label class="flex items-center gap-2 text-sm text-gray-700">
                                            <input type="radio" name="director_decision" value="{{ $key }}" class="dir-dec" {{ $dec === $key ? 'checked' : '' }}> {{ $text }}
                                        </label>
                                    @endforeach
                                    <button type="button" id="dec-clear" class="text-xs text-gray-400 hover:text-gray-600 underline">clear</button>
                                </div>
                            </div>
                            <div class="md:col-span-2"><label class="{{ $label }}">Director's date</label><input type="date" name="director_date" value="{{ $dval('director_date') }}" class="{{ $input }}"></div>
                            <div id="wrap-referred" class="md:col-span-4 {{ $dec === 'referred' ? '' : 'hidden' }}">
                                <label class="{{ $label }}">Referred to <span class="text-red-500">*</span></label>
                                <input name="referred_to" value="{{ $val('referred_to') }}" class="{{ $input }}">
                            </div>
                        </div>
                    </section>

                    {{-- ── Step 6: Review ── --}}
                    <section class="resp-panel hidden" data-step="6">
                        <h2 class="text-base font-bold text-gray-800">Review &amp; Save</h2>
                        <p class="text-xs text-gray-500 mb-5">Check what will be recorded. Click a section to change it.</p>
                        <div id="resp-review" class="grid grid-cols-1 lg:grid-cols-2 gap-4"></div>
                        <p class="text-xs text-gray-500 mt-5">
                            @if($saved) Recorded by {{ $r->created_by ?? '—' }} on {{ optional($r->created_at)->format('d/m/Y H:i') }}@if($r->updated_by && $r->updated_at != $r->created_at); last updated by {{ $r->updated_by }} on {{ optional($r->updated_at)->format('d/m/Y H:i') }}@endif. @endif
                            A recorded response stops the automatic Second Serve for this notice.
                        </p>
                    </section>
                </div>

                {{-- Wizard navigation --}}
                <div class="flex items-center justify-between gap-3 px-6 py-4 border-t border-gray-200 bg-gray-50 rounded-b-xl">
                    <button type="button" id="resp-back" class="px-5 py-2 text-sm text-gray-700 border border-gray-300 rounded-lg bg-white hover:bg-gray-50">&larr; Back</button>
                    <span id="resp-progress" class="text-xs text-gray-500"></span>
                    <div class="flex gap-2">
                        <button type="button" id="resp-next" class="px-6 py-2 text-sm font-semibold text-white rounded-lg hover:opacity-90" style="background:rgb(186,191,12)">Next &rarr;</button>
                        <button type="submit" id="resp-save" class="hidden px-6 py-2 text-sm font-semibold text-white rounded-lg hover:opacity-90" style="background:rgb(186,191,12)">
                            {{ $saved ? 'Update response' : 'Save response' }}
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    @include('admin.footer')
</div>

<style>
    .resp-step .resp-step-num { background: #f3f4f6; color: #6b7280; }
    .resp-step .resp-step-title { color: #6b7280; }
    .resp-step.is-active { border-bottom-color: rgb(186,191,12); background: rgba(186,191,12,.06); }
    .resp-step.is-active .resp-step-num { background: rgb(186,191,12); color: #fff; }
    .resp-step.is-active .resp-step-title { color: #1f2937; }
    .resp-step.is-done .resp-step-num { background: #dcfce7; color: #15803d; }
    .resp-step.is-done .resp-step-title { color: #374151; }
    .resp-field-error { border-color: #ef4444 !important; background: #fef2f2; }
</style>
@endsection

@push('scripts')
<script src="{{ asset('js/spas-pipeline.js') }}?v={{ @filemtime(public_path('js/spas-pipeline.js')) }}"></script>
<script>
    window.SpasPipeline && SpasPipeline.mount(document.getElementById('resp-pipeline'), @json($notice->spa_application_id), { current: 'response' });
</script>
<script>
(function () {
    const form   = document.getElementById('resp-form');
    const panels = [...document.querySelectorAll('.resp-panel')];
    const tabs   = [...document.querySelectorAll('.resp-step')];
    const LAST   = panels.length;
    let step = {{ (int) $startStep }};
    let reached = step;

    const $ = (sel) => form.querySelector(sel);
    const val = (name) => (form.elements[name] && form.elements[name].value || '').trim();
    const checked = (name) => form.querySelector(`[name="${name}"]:checked`);
    const esc = (v) => String(v ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));

    // What each step must have before moving on. The server checks the same.
    function problems(n) {
        const out = [];
        panels[n - 1].querySelectorAll('[data-required]').forEach(el => { if (!el.value.trim()) out.push([el, el.dataset.required]); });
        if (n === 1 && val('email') && !/^\S+@\S+\.\S+$/.test(val('email'))) out.push([form.elements.email, 'Check the email address.']);
        if (n === 3) {
            const o = checked('response_option');
            if (!o) out.push([form.querySelector('.resp-opt'), "Choose the allottee's response."]);
            else if (o.value === 'appeal' && !val('appeal_grounds')) out.push([form.elements.appeal_grounds, 'Give the grounds of the explanation / appeal.']);
            else if (o.value === 'other' && !val('other_details')) out.push([form.elements.other_details, 'Specify the response under Option 5: Other.']);
        }
        if (n === 5 && checked('director_decision')?.value === 'referred' && !val('referred_to')) {
            out.push([form.elements.referred_to, 'Say who the file was referred to.']);
        }
        return out;
    }

    function flag(list) {
        form.querySelectorAll('.resp-field-error').forEach(el => el.classList.remove('resp-field-error'));
        list.forEach(([el]) => el && el.type !== 'radio' && el.classList.add('resp-field-error'));
        if (list.length) {
            Swal.fire({ icon: 'warning', title: 'Check this step', html: list.map(([, m]) => esc(m)).join('<br>') });
            list[0][0]?.focus?.();
        }
        return !list.length;
    }

    function show(n) {
        step = Math.max(1, Math.min(LAST, n));
        reached = Math.max(reached, step);
        panels.forEach((p, i) => p.classList.toggle('hidden', i !== step - 1));
        tabs.forEach((t, i) => {
            t.classList.toggle('is-active', i === step - 1);
            // Done: steps passed on the way here (each was checked before moving on).
            t.classList.toggle('is-done', i < step - 1 || (i > step - 1 && i < reached - 1));
        });
        document.getElementById('resp-back').style.visibility = step === 1 ? 'hidden' : 'visible';
        document.getElementById('resp-next').classList.toggle('hidden', step === LAST);
        document.getElementById('resp-save').classList.toggle('hidden', step !== LAST);
        document.getElementById('resp-progress').textContent = `Step ${step} of ${LAST}`;
        if (step === LAST) renderReview();
        document.getElementById('resp-steps').scrollIntoView({ block: 'nearest' });
        if (window.lucide) lucide.createIcons();
    }

    // Forward only through valid steps; backward (or to a step already passed) freely.
    function go(n) {
        if (n > step) {
            for (let s = step; s < n; s++) {
                if (!flag(problems(s))) { show(s); return; }
            }
        }
        show(n);
    }

    document.getElementById('resp-next').addEventListener('click', () => go(step + 1));
    document.getElementById('resp-back').addEventListener('click', () => show(step - 1));
    tabs.forEach(t => t.addEventListener('click', () => go(+t.dataset.goto)));

    form.addEventListener('submit', (e) => {
        for (let s = 1; s < LAST; s++) {
            if (!flag(problems(s))) { e.preventDefault(); show(s); return; }
        }
        const btn = document.getElementById('resp-save');
        btn.disabled = true; btn.textContent = 'Saving…';
    });

    // Review: everything that will be recorded, grouped like the paper form.
    function renderReview() {
        const fmt = (v) => v ? v.split('-').reverse().join('/') : '';
        const opt = checked('response_option');
        const optTitle = opt ? opt.closest('label').querySelector('.font-semibold').textContent.trim() : '';
        const dec = checked('director_decision');
        const atts = [...form.querySelectorAll('[name="attachments[]"]:checked')].map(a => a.parentElement.textContent.trim());
        const card = (n, title, rows) => `
            <div role="button" tabindex="0" data-goto-review="${n}" class="block text-left p-4 rounded-xl border border-gray-200 hover:border-yellow-500 hover:bg-yellow-50 cursor-pointer">
                <div class="flex justify-between mb-2"><span class="text-sm font-bold text-gray-800">${title}</span><span class="text-xs text-gray-400">Edit</span></div>
                <dl class="text-xs space-y-1">${rows.map(([k, v]) => `<div class="flex gap-2"><dt class="w-40 flex-none text-gray-500">${k}</dt><dd class="text-gray-800 font-medium break-words">${esc(v) || '<span class="text-gray-300">—</span>'}</dd></div>`).join('')}</dl>
            </div>`;
        document.getElementById('resp-review').innerHTML = [
            card(1, 'Notice & Allottee', [['Reference No', val('reference_no')], ['Original notice', fmt(val('original_notice_date'))], ['This response', fmt(val('response_date'))],
                ['Allottee', val('allottee_name')], ['Address', val('allottee_address')], ['Phone', val('phone')], ['Email', val('email')], ['Representative', val('representative_name')]]),
            card(2, 'Property', [['SPAS ID', @json($spaId ?? '')], ['File Number', @json($fileNo ?? '')], ['Plot Number', val('plot_number')], ['Location', val('location')],
                ['Approved use', val('approved_land_use')], ['Current use', val('current_land_use')]]),
            card(3, 'Response', [['Option', optTitle], ...(opt?.value === 'appeal' ? [['Grounds', val('appeal_grounds')]] : []), ...(opt?.value === 'other' ? [['Details', val('other_details')]] : [])]),
            card(4, 'Undertaking & Attachments', [['Signed by allottee', form.querySelector('[name="declaration_signed"][type="checkbox"]').checked ? 'Yes' : 'No'],
                ['Date signed', fmt(val('allottee_signed_date'))], ['Attachments', atts.join('; ')]]),
            card(5, 'Official Use', [['Received by', val('received_by')], ['Designation', val('receiver_designation')], ['Received', [fmt(val('date_received')), val('time_received')].filter(Boolean).join(' ')],
                ['Remarks', val('action_remarks')], ['Director', dec ? dec.parentElement.textContent.trim() + (dec.value === 'referred' && val('referred_to') ? ': ' + val('referred_to') : '') : ''],
                ['Director date', fmt(val('director_date'))]]),
        ].join('');
        document.querySelectorAll('[data-goto-review]').forEach(b => b.addEventListener('click', () => show(+b.dataset.gotoReview)));
    }

    // Option boxes, conditional fields, decision.
    const toggle = (id, on) => document.getElementById(id)?.classList.toggle('hidden', !on);
    form.querySelectorAll('.resp-opt').forEach(r => r.addEventListener('change', () => {
        toggle('wrap-appeal', r.value === 'appeal' && r.checked);
        toggle('wrap-other', r.value === 'other' && r.checked);
        form.querySelectorAll('.resp-opt').forEach(o => {
            const box = o.closest('label');
            box.classList.toggle('bg-yellow-50', o.checked);
            box.classList.toggle('border-yellow-500', o.checked);
            box.classList.toggle('border-gray-200', !o.checked);
        });
    }));
    form.querySelectorAll('.dir-dec').forEach(r => r.addEventListener('change', () => toggle('wrap-referred', r.value === 'referred' && r.checked)));
    document.getElementById('dec-clear')?.addEventListener('click', () => {
        form.querySelectorAll('.dir-dec').forEach(r => { r.checked = false; });
        toggle('wrap-referred', false);
    });
    form.addEventListener('input', (e) => e.target.classList.remove('resp-field-error'));

    // Enter in a text box moves on rather than submitting from the middle.
    form.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' && e.target.tagName === 'INPUT' && step < LAST) { e.preventDefault(); go(step + 1); }
    });

    show(step);
})();
</script>
@endpush
