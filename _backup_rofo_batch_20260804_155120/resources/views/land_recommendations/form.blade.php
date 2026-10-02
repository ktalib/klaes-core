@extends('layouts.app')

@section('content')
@php
    // The re-issuance flow renders this form with a prefilled (unsaved) model,
    // so "is there a \$recommendation" no longer means "this is an edit".
    $isEdit           = $isEdit ?? isset($recommendation);
    // Editing a saved re-issuance has no ?reissuance= in the URL, so fall back to
    // the record's own flag — otherwise the re-issuance fields vanish on edit.
    $reissuanceSource = $reissuanceSource
        ?? (($recommendation->is_reissuance ?? false) ? $recommendation->reissuance_source : null);
    $reissuedFromId   = $reissuedFromId ?? null;
@endphp
{{-- "relative" is load-bearing — see the .flex-1.overflow-auto rule in app-layout.css. --}}
<div class="flex-1 overflow-auto bg-slate-50/60 relative">
    @include('admin.header')
    
    <div class="py-8 px-4 sm:px-6 lg:px-8 max-w-7xl mx-auto">
        <div class="bg-white rounded-2xl shadow-xl border border-slate-200 overflow-hidden">
            <!-- Header -->
            <div class="bg-slate-50 px-8 py-6 flex justify-between items-center border-b border-slate-200">
                <div>
                    <h1 class="text-2xl font-bold text-slate-900 tracking-tight">Recommendation</h1>
                    <p class="text-slate-500 text-sm mt-1">Data entry form</p>
                </div>
                <div class="flex items-center gap-3">
                    <a href="{{ route('land-recommendations.index') }}?type=ROFO" class="px-4 py-2 text-sm font-medium text-slate-700 bg-white hover:bg-slate-50 rounded-lg transition border border-slate-200 shadow-sm">
                        View Records
                    </a>
                </div>
            </div>

            <form id="land-recommendation-form" action="{{ $isEdit ? route('land-recommendations.update', $recommendation->id) : route('land-recommendations.store') }}" method="POST" class="p-8 space-y-8"
                data-dupcheck-url="{{ route('land-recommendations.check-duplicate') }}"
                data-record-id="{{ $recommendation->id ?? '' }}">
                {{-- Hidden inputs live inside a [hidden] wrapper on purpose: space-y-8 uses
                     "> :not([hidden]) ~ :not([hidden])", and a bare <input type="hidden">
                     has no hidden ATTRIBUTE, so it counts as a sibling and pushes the first
                     visible block down by an extra 2rem on top of the form's p-8. --}}
                <div hidden>
                    @csrf
                    @if($isEdit)
                        @method('PUT')
                    @endif
                </div>

                {{-- RofO Re-issuance: this record replaces a letter already issued for the
                     same file number, so it is flagged and skips the duplicate guard. --}}
                @if($reissuanceSource)
                    <div hidden>
                        <input type="hidden" name="is_reissuance" value="1">
                        <input type="hidden" name="reissuance_source" value="{{ $reissuanceSource }}">
                        @if($reissuedFromId)
                            <input type="hidden" name="reissued_from_id" value="{{ $reissuedFromId }}">
                        @endif
                    </div>
                    <div class="bg-amber-50 border border-amber-300 rounded-xl p-4 flex items-start gap-3">
                        <i data-lucide="refresh-ccw" class="h-5 w-5 text-amber-600 mt-0.5"></i>
                        <div class="text-sm">
                            <p class="font-bold text-amber-900">
                                RofO Re-issuance &mdash;
                                {{ $reissuanceSource === 'klaes' ? 'KLAES-Generated RofO' : 'Pre-KLAES (Legacy) RofO' }}
                            </p>
                            <p class="text-amber-800 text-xs mt-0.5">
                                @if($reissuanceSource === 'klaes')
                                    Details were copied from the existing RofO record. Adjust them as needed —
                                    saving creates a new re-issued RofO for this file number.
                                @else
                                    The original letter pre-dates KLAES. Enter its details below —
                                    saving creates the re-issued RofO for this file number.
                                @endif
                                It goes straight to the RofO table, ready to print.
                            </p>
                        </div>
                    </div>
                    {{-- Legacy only: the original letter pre-dates KLAES, so nothing on record
                         holds its issue date — but the re-issued letter has to print
                         "supersedes the previous one issued on ...". The KLAES path already
                         has that date on the existing record. --}}
                    @if($reissuanceSource === 'legacy')
                        <div class="bg-white border border-amber-200 rounded-xl p-4">
                            <label for="reissuance_original_date" class="block text-xs font-bold text-amber-700 uppercase tracking-wider mb-2">
                                Date the Original RofO Was Issued <span class="text-red-500">*</span>
                            </label>
                            <input type="date" name="reissuance_original_date" id="reissuance_original_date" required
                                value="{{ old('reissuance_original_date', optional($recommendation->reissuance_original_date ?? null)->format('Y-m-d')) }}"
                                class="w-full md:w-64 border border-slate-300 rounded-lg px-4 py-2.5 focus:ring-2 focus:ring-amber-500 focus:border-amber-500 outline-none">
                            <p class="mt-1 text-xs text-slate-500">
                                Printed on the re-issued letter as &ldquo;supersedes the previous one issued on &hellip;&rdquo;.
                            </p>
                        </div>
                    @endif

                    <script>window._reissuanceMode = true;</script>
                @endif

                @if(request('edit_reason'))
                    <div class="bg-amber-50 border-l-4 border-amber-400 p-4 mb-6">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <i data-lucide="alert-circle" class="h-5 w-5 text-amber-400"></i>
                            </div>
                            <div class="ml-3">
                                <p class="text-sm text-amber-700">
                                    <span class="font-bold">Reason for Edit:</span> 
                                    {{ request('edit_reason') }}
                                </p>
                            </div>
                        </div>
                    </div>
                    <input type="hidden" name="edit_reason" value="{{ request('edit_reason') }}">
                @elseif($isEdit && $recommendation->edit_reason)
                    <div class="bg-slate-50 border-l-4 border-slate-300 p-4 mb-6">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <i data-lucide="history" class="h-5 w-5 text-slate-400"></i>
                            </div>
                            <div class="ml-3">
                                <p class="text-sm text-slate-600">
                                    <span class="font-bold">Last Edit Reason:</span> 
                                    {{ $recommendation->edit_reason }}
                                </p>
                            </div>
                        </div>
                    </div>
                @endif

                {{-- ── Batch Mode ────────────────────────────────────────────────
                     A Plot Subdivision produces many child files off one mother file.
                     Batch mode captures a recommendation for every child in one pass:
                     the common grant conditions are keyed once below, and only the
                     values that differ per child go in the table. Edit mode is a
                     single record by definition, so the switch is create-only. --}}
                @unless($isEdit)
                <div id="batch-mode-card" class="bg-violet-50/60 border border-violet-200 rounded-xl p-6">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <label class="block text-sm font-extrabold text-slate-900 uppercase tracking-wider">Batch Mode</label>
                            <span class="inline-flex items-center gap-1.5 mt-1.5 px-2.5 py-1 rounded-full bg-violet-50 border border-violet-200 text-violet-800">
                                <i data-lucide="layers" class="h-3.5 w-3.5 flex-shrink-0"></i>
                                <span class="text-xs font-semibold leading-none">Capture one recommendation per child of a subdivided mother file.</span>
                            </span>
                        </div>
                        <label class="inline-flex items-center gap-3 cursor-pointer select-none bg-white border-2 border-slate-300 hover:border-violet-500 rounded-full pl-4 pr-2 py-1.5 shadow-sm transition-colors">
                            <span class="text-sm font-extrabold text-slate-900 uppercase tracking-wider">Enable</span>
                            <div class="relative">
                                <input type="checkbox" id="batch-mode-toggle" class="sr-only peer">
                                <div class="w-14 h-7 bg-rose-500 peer-checked:bg-violet-600 rounded-full transition-colors shadow-inner"></div>
                                <span class="absolute inset-y-0 right-2 flex items-center text-[11px] font-extrabold text-white tracking-wider peer-checked:hidden">OFF</span>
                                <span class="absolute inset-y-0 left-2.5 hidden items-center text-[11px] font-extrabold text-white tracking-wider peer-checked:flex">ON</span>
                                <div class="absolute top-0.5 left-0.5 w-6 h-6 bg-white rounded-full shadow-md transition-transform peer-checked:translate-x-7"></div>
                            </div>
                        </label>
                    </div>
                    <p id="batch-mode-hint" class="hidden mt-3 text-xs text-violet-900 bg-white/70 border border-violet-200 rounded-lg px-3 py-2">
                        Pick <span class="font-bold">Plot Subdivision</span> below, then select the mother file &mdash;
                        its children load into a table where you key only what differs between them.
                    </p>
                </div>

                {{-- ── Batch: children of the mother file ────────────────────────────
                     Sits directly under the Batch Mode switch so the captured rows stay
                     at the top of the form. Only per-child values live here; everything
                     else on the form (dates, term, fees, premium, recommendation text)
                     is captured once and copied onto every child that is ticked. --}}
                <div id="batch-children-card" class="hidden bg-white border-2 border-violet-200 rounded-xl shadow-sm overflow-hidden">
                    {{-- The mother file the batch is keyed to. old_file_number carries the
                         same value, but the batch endpoint validates this one explicitly —
                         it is what groups the saved children together. Disabled outside
                         batch mode so the single-record post never sees it. --}}
                    <input type="hidden" name="batch_mother_file_no" id="batch-mother-file-no" disabled value="">
                    <div class="bg-violet-50 border-b border-violet-200 px-5 py-3.5">
                        <div class="flex flex-wrap items-center gap-x-3 gap-y-2">
                            <i data-lucide="git-fork" class="h-4 w-4 text-violet-600 flex-shrink-0"></i>
                            <h3 class="text-sm font-bold text-violet-900 uppercase tracking-tight">Children of</h3>
                            <span id="batch-mother-label" class="font-mono font-black text-slate-900 text-sm">&mdash;</span>
                            <span id="batch-children-count"
                                class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-violet-600 text-white">0 selected</span>

                            <div class="ml-auto flex items-center gap-2">
                                {{-- Apply-to-all lives in the toolbar rather than as a row wedged
                                     into the table body, which broke the row rhythm. --}}
                                <button type="button" id="batch-apply-all" disabled
                                    class="px-3 py-1.5 text-[11px] font-bold bg-violet-600 text-white rounded-lg hover:bg-violet-700 disabled:opacity-40 disabled:cursor-not-allowed transition inline-flex items-center gap-1.5">
                                    <i data-lucide="arrow-down-to-line" class="h-3.5 w-3.5"></i> Apply row 1 to all
                                </button>
                                <button type="button" id="batch-reload-children"
                                    class="px-3 py-1.5 text-[11px] font-semibold bg-white border border-violet-300 text-violet-700 rounded-lg hover:bg-violet-50 transition inline-flex items-center gap-1.5">
                                    <i data-lucide="refresh-cw" class="h-3.5 w-3.5"></i> Reload
                                </button>
                            </div>
                        </div>
                        <p class="mt-2 text-[11px] text-violet-800/80">
                            Land use, purpose and page numbers copy down. Applicant name, address and location only
                            fill rows left blank &mdash; subdivided plots often have different owners.
                        </p>
                    </div>

                    <div id="batch-children-status" class="hidden mx-5 mt-4 text-xs font-semibold rounded-lg px-3 py-2"></div>

                    <div class="overflow-x-auto max-h-[32rem] overflow-y-auto">
                        <table class="w-full text-left min-w-[1020px] border-collapse table-fixed">
                            <thead class="sticky top-0 z-10">
                                <tr class="bg-slate-50 border-b-2 border-slate-200 text-[10px] font-black text-slate-500 uppercase tracking-widest">
                                    <th class="px-2 py-3 text-center w-9">
                                        <input type="checkbox" id="batch-select-all" checked
                                            class="w-4 h-4 align-middle text-violet-600 border-slate-300 rounded focus:ring-violet-500 cursor-pointer">
                                    </th>
                                    <th class="px-1 py-3 text-center w-8">#</th>
                                    <th class="px-2 py-3 whitespace-nowrap w-[122px]">Child File No</th>
                                    <th class="px-2 py-3 whitespace-nowrap w-[140px]">Applicant Name</th>
                                    <th class="px-2 py-3 whitespace-nowrap w-[150px]">Applicant Address</th>
                                    <th class="px-2 py-3 whitespace-nowrap w-[72px]">Plot No</th>
                                    <th class="px-2 py-3 whitespace-nowrap w-[145px]">Location</th>
                                    <th class="px-2 py-3 whitespace-nowrap w-[108px]">Land Use</th>
                                    <th class="px-2 py-3 whitespace-nowrap w-[108px]">Purpose</th>
                                    <th class="px-2 py-3 whitespace-nowrap w-[140px]">
                                        Page Refs
                                        <span class="block font-medium normal-case tracking-normal text-[9px] text-slate-400">page &middot; memo &middot; plan</span>
                                    </th>
                                </tr>
                            </thead>
                            <tbody id="batch-children-rows" class="text-sm"></tbody>
                        </table>
                    </div>

                    <div class="bg-slate-50 border-t border-slate-200 px-5 py-2.5">
                        <p class="text-[11px] text-slate-500">
                            Untick any child that should not receive a recommendation. Every ticked row is saved as
                            its own RofO recommendation, grouped under one batch.
                        </p>
                    </div>
                </div>
                @endunless

                <!-- File Number Selector -->
                <div id="file-number-card" class="bg-blue-50/50 rounded-xl p-6 border border-blue-100/50">
                    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                        <div class="flex-1">
                            <label class="block text-xs font-bold text-blue-700 uppercase tracking-wider mb-2">Selected File Number</label>
                            <input type="text" name="file_number" id="file_number" readonly required
                                value="{{ old('file_number', $recommendation->file_number ?? '') }}"
                                placeholder="NO FILE SELECTED"
                                class="w-full bg-white border border-blue-200 rounded-lg px-4 py-3 text-slate-900 font-bold font-mono placeholder:text-slate-400 text-lg shadow-sm outline-none focus:ring-2 focus:ring-blue-500 transition">
                            <input type="hidden" name="tracking_id" id="tracking_id" value="{{ old('tracking_id', $recommendation->tracking_id ?? '') }}">
                            {{-- Set to 1 only when the user answers "Save Anyway" on the duplicate prompt;
                                 the server rejects a duplicate file number unless this is present. --}}
                            <input type="hidden" name="duplicate_confirmed" id="duplicate_confirmed" value="0">
                        </div>
                        <div class="flex flex-shrink-0 items-end">
                            <button type="button" id="select-fileno-btn"
                                class="px-6 py-3 bg-blue-600 text-white font-bold rounded-lg hover:bg-blue-700 transition flex items-center gap-2 shadow-lg shadow-blue-200">
                                <i data-lucide="search" class="h-5 w-5"></i>
                                Select File Number
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Form Grid -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    @php
                        $savedAppType = old('application_type', $recommendation->application_type ?? '');
                        $hasAppType   = $savedAppType !== '';
                        $savedRecType = old('type', $recommendation->type ?? 'Direct');
                        // "Standard template" override: the application type is still captured
                        // (extra fields, old file number) but printing uses the Direct /
                        // Conversion template instead of the application-type template.
                        $useStandardTemplate = (bool) old('use_standard_template', $recommendation->use_standard_template ?? false);
                        $lockRecType  = $hasAppType && !$useStandardTemplate;
                    @endphp

                    <!-- Recommendation Type Selection -->
                    <div id="recommendation-type-block" class="bg-blue-50/30 border border-blue-100 rounded-xl p-6 col-span-2 transition-opacity {{ $lockRecType ? 'opacity-40 pointer-events-none' : '' }}">
                        <label class="block text-xs font-bold text-blue-700 uppercase tracking-wider mb-3">Recommendation Type</label>
                        <div class="flex flex-col sm:flex-row gap-4">
                            <label class="flex items-center gap-3 cursor-pointer p-4 bg-white border border-blue-200 rounded-xl hover:border-blue-500 transition shadow-sm flex-1 group">
                                <input type="radio" id="rec-direct" name="type" value="Direct"
                                    {{ $savedRecType == 'Direct' && !$lockRecType ? 'checked' : '' }}
                                    {{ $lockRecType ? 'disabled' : '' }}
                                    class="w-5 h-5 text-blue-600 focus:ring-blue-500 border-slate-300">
                                <div>
                                    <span class="block text-sm font-bold text-slate-900 group-hover:text-blue-700 transition">Direct</span>
                                </div>
                            </label>
                            <label class="flex items-center gap-3 cursor-pointer p-4 bg-white border border-blue-200 rounded-xl hover:border-amber-500 transition shadow-sm flex-1 group">
                                <input type="radio" id="rec-conversion" name="type" value="Conversion"
                                    {{ $savedRecType == 'Conversion' && !$lockRecType ? 'checked' : '' }}
                                    {{ $lockRecType ? 'disabled' : '' }}
                                    class="w-5 h-5 text-amber-600 focus:ring-amber-500 border-slate-300">
                                <div>
                                    <span class="block text-sm font-bold text-slate-900 group-hover:text-amber-700 transition">Conversion</span>
                                </div>
                            </label>
                        </div>
                    </div>

                    <!-- Application Type -->
                    <div id="application-type-card" class="bg-slate-50/60 border border-slate-200 rounded-xl p-6 col-span-2">
                        <div class="flex flex-wrap items-center justify-between gap-3 mb-3">
                            <div>
                                <label class="block text-sm font-extrabold text-slate-900 uppercase tracking-wider">Application Type</label>
                                <span class="inline-flex items-center gap-1.5 mt-1.5 px-2.5 py-1 rounded-full bg-blue-50 border border-blue-200 text-blue-800">
                                    <i data-lucide="info" class="h-3.5 w-3.5 flex-shrink-0"></i>
                                    <span class="text-xs font-semibold leading-none">Turn on to pick a specific application type instead of the standard document.</span>
                                </span>
                            </div>
                            <!-- Toggle switch -->
                            <label class="inline-flex items-center gap-3 cursor-pointer select-none bg-white border-2 border-slate-300 hover:border-blue-500 rounded-full pl-4 pr-2 py-1.5 shadow-sm transition-colors">
                                <span class="text-sm font-extrabold text-slate-900 uppercase tracking-wider">Enable</span>
                                <div class="relative">
                                    <input type="checkbox" id="app-type-toggle" class="sr-only peer" {{ $hasAppType ? 'checked' : '' }}>
                                    <div class="w-14 h-7 bg-rose-500 peer-checked:bg-emerald-500 rounded-full transition-colors shadow-inner"></div>
                                    <span class="absolute inset-y-0 right-2 flex items-center text-[11px] font-extrabold text-white tracking-wider peer-checked:hidden">OFF</span>
                                    <span class="absolute inset-y-0 left-2.5 hidden items-center text-[11px] font-extrabold text-white tracking-wider peer-checked:flex">ON</span>
                                    <div class="absolute top-0.5 left-0.5 w-6 h-6 bg-white rounded-full shadow-md transition-transform peer-checked:translate-x-7"></div>
                                </div>
                            </label>
                        </div>
                        <input type="hidden" id="application-type-hidden" name="application_type" value="">
                        <div id="application-type-panel" class="{{ $hasAppType ? '' : 'hidden' }}">
                            <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                                @foreach([
                                    'Private Layout',
                                    'Plot Subdivision',
                                    'Plot Merger',
                                    'Plot Extension',
                                    'Temporary File No',
                                    'Ministry of Works',
                                    'Change of Purpose',
                                ] as $appType)
                                <label class="flex items-center gap-3 cursor-pointer p-3 bg-white border border-slate-200 rounded-xl hover:border-blue-400 transition shadow-sm group">
                                    <input type="radio" name="application_type_radio" value="{{ $appType }}"
                                        {{ $savedAppType === $appType ? 'checked' : '' }}
                                        class="app-type-radio w-4 h-4 text-blue-600 focus:ring-blue-500 border-slate-300">
                                    <span class="text-sm font-medium text-slate-700 group-hover:text-blue-700 transition leading-tight">{{ $appType }}</span>
                                </label>
                                @endforeach
                            </div>

                            {{-- Override: keep the application type (extra fields / old file
                                 number) but print the standard Direct / Conversion document. --}}
                            <label class="mt-4 flex items-start gap-3 cursor-pointer p-3 bg-white border border-slate-200 rounded-xl hover:border-blue-400 transition">
                                <input type="checkbox" name="use_standard_template" id="use-standard-template" value="1"
                                    {{ $useStandardTemplate ? 'checked' : '' }}
                                    class="mt-0.5 w-4 h-4 text-blue-600 focus:ring-blue-500 border-slate-300 rounded">
                                <span class="text-sm text-slate-700 leading-snug">
                                    <span class="font-semibold text-slate-900">Print the standard Recommendation Type document</span>
                                    <span class="block text-xs text-slate-500">
                                        Keeps the application type (and its Old File Number / extra fields) on the record,
                                        but prints the Direct / Conversion template instead of the application-type template.
                                    </span>
                                </span>
                            </label>
                        </div>
                    </div>

                    <script>
                    (function () {
                        const toggle   = document.getElementById('app-type-toggle');
                        const panel    = document.getElementById('application-type-panel');
                        const hidden   = document.getElementById('application-type-hidden');
                        const recBlock = document.getElementById('recommendation-type-block');
                        const recDirect = document.getElementById('rec-direct');
                        const recConv   = document.getElementById('rec-conversion');
                        const appRadios = document.querySelectorAll('.app-type-radio');

                        const stdTemplate = document.getElementById('use-standard-template');

                        // Recommendation Type is locked while an application type drives the
                        // printed document. The "standard template" override releases it, so
                        // the app type is still captured but Direct / Conversion is printed.
                        function syncRecType() {
                            const locked = toggle.checked && !(stdTemplate && stdTemplate.checked);
                            if (locked) {
                                recBlock.classList.add('opacity-40', 'pointer-events-none');
                                recDirect.checked  = false;
                                recConv.checked    = false;
                                recDirect.disabled = true;
                                recConv.disabled   = true;
                            } else {
                                recBlock.classList.remove('opacity-40', 'pointer-events-none');
                                recDirect.disabled = false;
                                recConv.disabled   = false;
                                if (!recDirect.checked && !recConv.checked) recDirect.checked = true;
                            }
                            // Programmatic checks don't fire `change`, so push the survey
                            // method sync manually.
                            if (window._syncSurveyMethod) window._syncSurveyMethod();
                        }

                        function applyState(enabled) {
                            if (enabled) {
                                panel.classList.remove('hidden');
                                const checked = document.querySelector('.app-type-radio:checked');
                                hidden.value = checked ? checked.value : '';
                            } else {
                                panel.classList.add('hidden');
                                appRadios.forEach(r => r.checked = false);
                                hidden.value = '';
                                if (stdTemplate) stdTemplate.checked = false;
                            }
                            syncRecType();
                            if (typeof window._calcResidualTerm === 'function') {
                                window._calcResidualTerm();
                            }
                        }

                        toggle.addEventListener('change', () => applyState(toggle.checked));
                        if (stdTemplate) stdTemplate.addEventListener('change', syncRecType);

                        appRadios.forEach(r => r.addEventListener('change', () => {
                            hidden.value = r.value;
                            if (typeof window._calcResidualTerm === 'function') {
                                window._calcResidualTerm();
                            }
                        }));

                        // Sync hidden field on load if toggle is already on
                        if (toggle.checked) {
                            const checked = document.querySelector('.app-type-radio:checked');
                            hidden.value = checked ? checked.value : '';
                        }
                    })();
                    </script>

                    {{-- Old File Number — its own section for application types that derive from an existing file --}}
                    <div id="atx-old-fileno-row" class="hidden col-span-2 bg-amber-50/40 border border-amber-200 rounded-xl p-6">
                        <div class="flex items-center gap-2 mb-3">
                            <i data-lucide="folder-input" class="h-4 w-4 text-amber-600"></i>
                            <h3 class="text-sm font-bold text-amber-900 uppercase tracking-tight">Old File Number <span class="text-red-500">*</span></h3>
                        </div>
                        <div class="flex gap-2 items-center" id="atx-old-fileno-manual">
                            <input type="text" name="old_file_number" id="old_file_number" readonly
                                value="{{ old('old_file_number', $recommendation->old_file_number ?? '') }}"
                                placeholder="No old file number selected"
                                class="w-64 border border-amber-200 rounded-lg px-3 py-2.5 bg-white font-mono text-sm outline-none shadow-sm">
                            <button type="button" id="atx-old-fileno-pick"
                                class="px-4 py-2.5 text-xs font-semibold bg-amber-600 text-white rounded-lg hover:bg-amber-700 transition whitespace-nowrap">
                                Select File Number
                            </button>
                        </div>

                        {{-- Batch mode: only a handful of files have commissioned subdivision
                             children, so the whole-register file picker is the wrong tool —
                             the mother is chosen from that short list instead. The readonly
                             input above stays in the DOM and is still what posts. --}}
                        <div id="batch-mother-picker" class="hidden">
                            <select id="batch-mother-select"
                                class="w-full md:w-96 border border-amber-300 rounded-lg px-3 py-2.5 bg-white text-sm font-mono outline-none shadow-sm focus:border-amber-500 focus:ring-2 focus:ring-amber-500/20 transition cursor-pointer">
                                <option value="">Loading subdivided files…</option>
                            </select>
                        </div>

                        <p class="mt-2 text-[11px] text-slate-500">
                            <span id="atx-old-fileno-help">The parent / previous file this <span id="atx-old-fileno-context">application</span> derives from.</span>
                            <span id="batch-mother-help" class="hidden">
                                Only files that already have commissioned subdivision children are listed.
                            </span>
                        </p>
                    </div>


                    {{-- ── Application Type: Conditional Extra Fields ── --}}
                    @php $savedAppType = old('application_type', $recommendation->application_type ?? ''); @endphp
                    {{-- Page numbers and the per-type panels are all per-child values in a
                         subdivision batch (the table below carries them), so this whole
                         block is stood down when batch mode is on. --}}
                    <div id="app-type-extra" data-batch-child class="{{ $savedAppType ? '' : 'hidden' }} col-span-2 bg-indigo-50/30 border border-indigo-100 rounded-xl p-6 space-y-5">
                        <div class="flex items-center gap-2 mb-1">
                            <i data-lucide="settings-2" class="h-4 w-4 text-indigo-600"></i>
                            <h3 class="text-sm font-bold text-indigo-900 uppercase tracking-tight">
                                Additional Fields &mdash; <span id="app-type-extra-label" class="text-indigo-600 normal-case font-semibold">{{ $savedAppType }}</span>
                            </h3>
                        </div>

                        {{-- Page No. (common to all application types) --}}
                        <div class="grid grid-cols-4 gap-4">
                            <div>
                                <label class="block text-xs font-semibold text-slate-500 uppercase mb-1.5">Page No.</label>
                                <input type="number" name="page" id="atx_page" min="1"
                                    value="{{ old('page', $recommendation->page ?? '') }}"
                                    placeholder="e.g. 4"
                                    class="w-full border border-slate-200 rounded-lg px-4 py-2.5 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none transition bg-white shadow-sm">
                            </div>
                        </div>

                        {{-- PANEL: Private Layout (with No. count) --}}
                        <div id="atx-panel-private-layout" class="atx-panel {{ $savedAppType === 'Private Layout' ? '' : 'hidden' }} space-y-4">
                            <div class="grid grid-cols-4 gap-4">
                                <div>
                                    <label class="block text-xs font-semibold text-slate-500 uppercase mb-1.5">Number of Plots / Portions</label>
                                    <input type="number" name="num_plots" id="num_plots" min="1"
                                        value="{{ old('num_plots', $recommendation->num_plots ?? '') }}"
                                        class="w-full border border-slate-200 rounded-lg px-4 py-2.5 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none transition bg-white shadow-sm">
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-slate-500 uppercase mb-1.5">Auth. Memo Page</label>
                                    <input type="number" name="page_2" min="1"
                                        value="{{ old('page_2', $recommendation->page_2 ?? '') }}"
                                        class="w-full border border-slate-200 rounded-lg px-4 py-2.5 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none transition bg-white shadow-sm">
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-slate-500 uppercase mb-1.5">Site Plan Page</label>
                                    <input type="number" name="page_3" min="1"
                                        value="{{ old('page_3', $recommendation->page_3 ?? '') }}"
                                        class="w-full border border-slate-200 rounded-lg px-4 py-2.5 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none transition bg-white shadow-sm">
                                </div>
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-500 uppercase mb-2">Plot Dimensions <span class="text-slate-400 normal-case font-normal">(Length × Width — No.)</span></label>
                                <div id="plot-sizes-rows-pl" class="space-y-2 mb-2"></div>
                                <button type="button" onclick="addPlotSizeRow('pl', null, true)"
                                    class="px-4 py-1.5 text-xs font-semibold bg-indigo-100 text-indigo-700 hover:bg-indigo-200 rounded-lg transition">
                                    + Add Dimension Row
                                </button>
                            </div>
                        </div>

                        {{-- PANEL: Plot Subdivision (no count) --}}
                        <div id="atx-panel-subdivision" class="atx-panel {{ $savedAppType === 'Plot Subdivision' ? '' : 'hidden' }} space-y-4">
                            <div class="grid grid-cols-4 gap-4">
                                <div>
                                    <label class="block text-xs font-semibold text-slate-500 uppercase mb-1.5">Number of Portions</label>
                                    <input type="number" name="num_plots" min="1"
                                        value="{{ old('num_plots', $recommendation->num_plots ?? '') }}"
                                        class="w-full border border-slate-200 rounded-lg px-4 py-2.5 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none transition bg-white shadow-sm">
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-slate-500 uppercase mb-1.5">Auth. Memo Page</label>
                                    <input type="number" name="page_2" min="1"
                                        value="{{ old('page_2', $recommendation->page_2 ?? '') }}"
                                        class="w-full border border-slate-200 rounded-lg px-4 py-2.5 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none transition bg-white shadow-sm">
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-slate-500 uppercase mb-1.5">Site Plan Page</label>
                                    <input type="number" name="page_3" min="1"
                                        value="{{ old('page_3', $recommendation->page_3 ?? '') }}"
                                        class="w-full border border-slate-200 rounded-lg px-4 py-2.5 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none transition bg-white shadow-sm">
                                </div>
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-500 uppercase mb-2">Plot Dimensions <span class="text-slate-400 normal-case font-normal">(Length × Width)</span></label>
                                <div id="plot-sizes-rows-sub" class="space-y-2 mb-2"></div>
                                <button type="button" onclick="addPlotSizeRow('sub', null, false)"
                                    class="px-4 py-1.5 text-xs font-semibold bg-indigo-100 text-indigo-700 hover:bg-indigo-200 rounded-lg transition">
                                    + Add Dimension Row
                                </button>
                            </div>
                        </div>

                        {{-- PANEL: Plot Merger (no count) --}}
                        <div id="atx-panel-merger" class="atx-panel {{ $savedAppType === 'Plot Merger' ? '' : 'hidden' }} space-y-4">
                            <div class="grid grid-cols-4 gap-4">
                                <div>
                                    <label class="block text-xs font-semibold text-slate-500 uppercase mb-1.5">Number of Plots Merged</label>
                                    <input type="number" name="num_plots" min="1"
                                        value="{{ old('num_plots', $recommendation->num_plots ?? '') }}"
                                        class="w-full border border-slate-200 rounded-lg px-4 py-2.5 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none transition bg-white shadow-sm">
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-slate-500 uppercase mb-1.5">Auth. Memo Page</label>
                                    <input type="number" name="page_2" min="1"
                                        value="{{ old('page_2', $recommendation->page_2 ?? '') }}"
                                        class="w-full border border-slate-200 rounded-lg px-4 py-2.5 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none transition bg-white shadow-sm">
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-slate-500 uppercase mb-1.5">Site Plan Page</label>
                                    <input type="number" name="page_3" min="1"
                                        value="{{ old('page_3', $recommendation->page_3 ?? '') }}"
                                        class="w-full border border-slate-200 rounded-lg px-4 py-2.5 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none transition bg-white shadow-sm">
                                </div>
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-500 uppercase mb-2">Merged Plot Dimensions <span class="text-slate-400 normal-case font-normal">(Length × Width)</span></label>
                                <div id="plot-sizes-rows-mrg" class="space-y-2 mb-2"></div>
                                <button type="button" onclick="addPlotSizeRow('mrg', null, false)"
                                    class="px-4 py-1.5 text-xs font-semibold bg-indigo-100 text-indigo-700 hover:bg-indigo-200 rounded-lg transition">
                                    + Add Dimension Row
                                </button>
                            </div>
                        </div>

                        {{-- PANEL: Plot Extension (no count) --}}
                        <div id="atx-panel-extension" class="atx-panel {{ $savedAppType === 'Plot Extension' ? '' : 'hidden' }} space-y-4">
                            <div class="grid grid-cols-4 gap-4 mb-3">
                                <div>
                                    <label class="block text-xs font-semibold text-slate-500 uppercase mb-1.5">Planning Views Page</label>
                                    <input type="number" name="page_2" min="1"
                                        value="{{ old('page_2', $recommendation->page_2 ?? '') }}"
                                        class="w-full border border-slate-200 rounded-lg px-4 py-2.5 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none transition bg-white shadow-sm">
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-slate-500 uppercase mb-1.5">KNUPDA Page</label>
                                    <input type="number" name="page_3" min="1"
                                        value="{{ old('page_3', $recommendation->page_3 ?? '') }}"
                                        class="w-full border border-slate-200 rounded-lg px-4 py-2.5 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none transition bg-white shadow-sm">
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-slate-500 uppercase mb-1.5">Site Plan Page</label>
                                    <input type="number" name="page_4" min="1"
                                        value="{{ old('page_4', $recommendation->page_4 ?? '') }}"
                                        class="w-full border border-slate-200 rounded-lg px-4 py-2.5 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none transition bg-white shadow-sm">
                                </div>
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-500 uppercase mb-2">Plot Dimensions <span class="text-slate-400 normal-case font-normal">(Length × Width)</span></label>
                                <div id="plot-sizes-rows-ext" class="space-y-2 mb-2"></div>
                                <button type="button" onclick="addPlotSizeRow('ext', null, false)"
                                    class="px-4 py-1.5 text-xs font-semibold bg-indigo-100 text-indigo-700 hover:bg-indigo-200 rounded-lg transition">
                                    + Add Dimension Row
                                </button>
                            </div>
                        </div>

                        {{-- PANEL: Temporary File No --}}
                        <div id="atx-panel-temp" class="atx-panel {{ $savedAppType === 'Temporary File No' ? '' : 'hidden' }}"></div>

                        {{-- PANEL: Ministry of Works (Premium / Purchase Price) --}}
                        <div id="atx-panel-premium" class="atx-panel {{ $savedAppType === 'Ministry of Works' ? '' : 'hidden' }} space-y-4">
                            <div class="grid grid-cols-4 gap-4 mb-2">
                                <div>
                                    <label class="block text-xs font-semibold text-slate-500 uppercase mb-1.5">Clearance Letter Page</label>
                                    <input type="number" name="page_2" min="1"
                                        value="{{ old('page_2', $recommendation->page_2 ?? '') }}"
                                        class="w-full border border-slate-200 rounded-lg px-4 py-2.5 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none transition bg-white shadow-sm">
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-slate-500 uppercase mb-1.5">Receipt Page</label>
                                    <input type="number" name="page_3" min="1"
                                        value="{{ old('page_3', $recommendation->page_3 ?? '') }}"
                                        class="w-full border border-slate-200 rounded-lg px-4 py-2.5 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none transition bg-white shadow-sm">
                                </div>
                            </div>
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-xs font-semibold text-slate-500 uppercase mb-1.5">Premium / Purchase Price (&#x20A6;)</label>
                                    <input type="number" step="0.01" name="premium" id="premium"
                                        value="{{ old('premium', $recommendation->premium ?? '') }}"
                                        class="w-full border border-slate-200 rounded-lg px-4 py-2.5 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none transition bg-white shadow-sm">
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-slate-500 uppercase mb-1.5">Premium in Words <span class="text-slate-400 normal-case font-normal">(auto)</span></label>
                                    <input type="text" name="premium_words" id="premium_words"
                                        value="{{ old('premium_words', $recommendation->premium_words ?? '') }}"
                                        class="w-full border border-blue-100 bg-blue-50 rounded-lg px-4 py-2.5 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none transition shadow-sm"
                                        placeholder="Auto-filled from amount above">
                                </div>
                            </div>
                        </div>

                        {{-- PANEL: Change of Purpose --}}
                        <div id="atx-panel-change-of-purpose" class="atx-panel {{ $savedAppType === 'Change of Purpose' ? '' : 'hidden' }} space-y-4">
                            <div class="grid grid-cols-2 gap-4">
                                <div class="col-span-2">
                                    <label class="block text-xs font-semibold text-slate-500 uppercase mb-1.5">Purpose Description <span class="text-slate-400 normal-case font-normal">(e.g. Commercial (Warehouse))</span></label>
                                    <input type="text" name="purpose_description" id="purpose_description"
                                        value="{{ old('purpose_description', $recommendation->purpose_description ?? '') }}"
                                        placeholder="e.g. Commercial (Warehouse)"
                                        class="w-full border border-slate-200 rounded-lg px-4 py-2.5 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none transition bg-white shadow-sm">
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-slate-500 uppercase mb-1.5">Planning Dept Page</label>
                                    <input type="number" name="page_2" id="cop_page_2" min="1"
                                        value="{{ old('page_2', $recommendation->page_2 ?? '') }}"
                                        class="w-full border border-slate-200 rounded-lg px-4 py-2.5 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none transition bg-white shadow-sm">
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-slate-500 uppercase mb-1.5">Conditions Page</label>
                                    <input type="number" name="page_3" id="cop_page_3" min="1"
                                        value="{{ old('page_3', $recommendation->page_3 ?? '') }}"
                                        class="w-full border border-slate-200 rounded-lg px-4 py-2.5 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none transition bg-white shadow-sm">
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-slate-500 uppercase mb-1.5">Acceptance Letter Page</label>
                                    <input type="number" name="page_4" id="cop_page_4" min="1"
                                        value="{{ old('page_4', $recommendation->page_4 ?? '') }}"
                                        class="w-full border border-slate-200 rounded-lg px-4 py-2.5 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none transition bg-white shadow-sm">
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-slate-500 uppercase mb-1.5">Site Plan Page</label>
                                    <input type="number" name="page_5" id="cop_page_5" min="1"
                                        value="{{ old('page_5', $recommendation->page_5 ?? '') }}"
                                        class="w-full border border-slate-200 rounded-lg px-4 py-2.5 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none transition bg-white shadow-sm">
                                </div>
                                <div class="col-span-2">
                                    <label class="block text-xs font-semibold text-slate-500 uppercase mb-1.5">Plot Dimensions <span class="text-slate-400 normal-case font-normal">(polygon measurements)</span></label>
                                    <textarea name="dimensions_text" rows="2"
                                        class="w-full border border-slate-200 rounded-lg px-4 py-2.5 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none transition bg-white shadow-sm"
                                        placeholder="e.g. 307m x 65.92 x 106.72m ... = 39,591m²/3.9591ha">{{ old('dimensions_text', $recommendation->dimensions_text ?? '') }}</textarea>
                                </div>
                            </div>
                        </div>

                        {{-- Hidden: serialized plot sizes (written by JS on submit) --}}
                        <input type="hidden" name="plot_sizes" id="plot_sizes_json"
                            value="{{ old('plot_sizes', $recommendation->plot_sizes ?? '') }}">
                    </div>

                    <script>
                    // Panel show/hide wired to application type radios
                    (function () {
                        var panelMap = {
                            'Private Layout':               'atx-panel-private-layout',
                            'Plot Subdivision':             'atx-panel-subdivision',
                            'Plot Merger':                  'atx-panel-merger',
                            'Plot Extension':               'atx-panel-extension',
                            'Temporary File No':            'atx-panel-temp',
                            'Ministry of Works':            'atx-panel-premium',
                            'Change of Purpose':            'atx-panel-change-of-purpose',
                        };

                        var extraContainer = document.getElementById('app-type-extra');
                        var extraLabel     = document.getElementById('app-type-extra-label');

                        function showExtraPanel(appType) {
                            document.querySelectorAll('.atx-panel').forEach(function (p) { p.classList.add('hidden'); });
                            var panelId = panelMap[appType];
                            if (panelId) {
                                document.getElementById(panelId).classList.remove('hidden');
                                extraContainer.classList.remove('hidden');
                                if (extraLabel) extraLabel.textContent = appType;
                            } else {
                                extraContainer.classList.add('hidden');
                            }
                        }

                        function hideExtra() {
                            extraContainer.classList.add('hidden');
                            document.querySelectorAll('.atx-panel').forEach(function (p) { p.classList.add('hidden'); });
                        }

                        window._showExtraPanel = showExtraPanel;
                        window._hideExtraPanel  = hideExtra;

                        document.querySelectorAll('.app-type-radio').forEach(function (r) {
                            r.addEventListener('change', function () {
                                if (this.checked) showExtraPanel(this.value);
                            });
                        });

                        document.getElementById('app-type-toggle').addEventListener('change', function () {
                            if (!this.checked) hideExtra();
                        });
                    })();

                    // Global helpers for plot size rows (called via onclick)
                    // showCount=true → Private Layout (has No. column); false → Subdivision/Extension
                    function addPlotSizeRow(suffix, data, showCount) {
                        if (showCount === undefined) showCount = true;
                        var container = document.getElementById('plot-sizes-rows-' + suffix);
                        if (!container) return;
                        var idx = container.children.length;
                        var labels = ['i.','ii.','iii.','iv.','v.','vi.','vii.','viii.'];
                        var lbl = labels[idx] !== undefined ? labels[idx] : (idx + 1) + '.';
                        var row = document.createElement('div');
                        row.className = 'plot-size-row flex items-center gap-2 flex-wrap';
                        var countHtml = showCount
                            ? '<input type="text" placeholder="No." class="plot-count w-20 border border-slate-200 rounded-lg px-3 py-2 text-sm focus:border-indigo-400 outline-none bg-white">' +
                              '<span class="text-slate-500 text-sm shrink-0">No.</span>'
                            : '<input type="hidden" class="plot-count" value="">';
                        row.innerHTML =
                            '<span class="text-xs font-semibold text-slate-500 w-6 shrink-0">' + lbl + '</span>' +
                            '<input type="text" placeholder="Length" class="plot-length w-24 border border-slate-200 rounded-lg px-3 py-2 text-sm focus:border-indigo-400 outline-none bg-white">' +
                            '<span class="text-slate-500 text-sm shrink-0">m \xd7</span>' +
                            '<input type="text" placeholder="Width" class="plot-width w-24 border border-slate-200 rounded-lg px-3 py-2 text-sm focus:border-indigo-400 outline-none bg-white">' +
                            '<span class="text-slate-500 text-sm shrink-0">m</span>' +
                            countHtml +
                            '<button type="button" onclick="removePlotSizeRow(this)" class="text-red-400 hover:text-red-600 text-sm px-2 shrink-0">\xd7</button>';
                        if (data) {
                            row.querySelector('.plot-length').value = data.length || '';
                            row.querySelector('.plot-width').value  = data.width  || '';
                            var cEl = row.querySelector('.plot-count');
                            if (cEl) cEl.value = data.count || '';
                        }
                        container.appendChild(row);
                    }

                    function removePlotSizeRow(btn) {
                        var row = btn.closest('.plot-size-row');
                        if (row) row.remove();
                    }
                    </script>

                    <!-- Section 1: Applicant & Property (Template a-e) -->
                    <div class="bg-slate-50 border border-slate-100 rounded-xl p-6 space-y-4">
                        <div class="flex items-center gap-2 mb-2">
                            <i data-lucide="user" class="h-4 w-4 text-blue-600"></i>
                            <h3 class="text-sm font-bold text-slate-900 uppercase tracking-tight">Applicant & Property</h3>
                        </div>
                        <div class="space-y-4">
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <div class="md:col-span-2" data-batch-child>
                                    <label class="block text-xs font-semibold text-slate-500 uppercase mb-1.5">Name of Applicant</label>
                                    <input type="text" name="applicant_name" id="applicant_name" required value="{{ old('applicant_name', $recommendation->applicant_name ?? '') }}"
                                        class="w-full border @error('applicant_name') border-red-500 @else border-slate-200 @enderror rounded-lg px-4 py-2.5 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none transition bg-white shadow-sm">
                                    @error('applicant_name')
                                        <p class="text-red-500 text-[10px] mt-1 font-semibold uppercase">{{ $message }}</p>
                                    @enderror
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-slate-500 uppercase mb-1.5">Application Date</label>
                                    <input type="date" name="application_date" id="application_date" required value="{{ old('application_date', ($isEdit && $recommendation->application_date) ? $recommendation->application_date->format('Y-m-d') : '') }}"
                                        class="w-full border @error('application_date') border-red-500 @else border-slate-200 @enderror rounded-lg px-4 py-2.5 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none transition bg-white shadow-sm">
                                    @error('application_date')
                                        <p class="text-red-500 text-[10px] mt-1 font-semibold uppercase">{{ $message }}</p>
                                    @enderror
                                </div>
                            </div>
                            <div data-batch-child>
                                <label class="block text-xs font-semibold text-slate-500 uppercase mb-1.5">Applicant Address</label>
                                <input type="text" name="applicant_address" id="applicant_address" required value="{{ old('applicant_address', $recommendation->applicant_address ?? '') }}"
                                    class="w-full border @error('applicant_address') border-red-500 @else border-slate-200 @enderror rounded-lg px-4 py-2.5 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none transition bg-white shadow-sm">
                                @error('applicant_address')
                                    <p class="text-red-500 text-[10px] mt-1 font-semibold uppercase">{{ $message }}</p>
                                @enderror
                            </div>
                            <div class="grid grid-cols-2 gap-4">
                       <div data-batch-child>
                                    <label class="block text-xs font-semibold text-slate-500 uppercase mb-1.5">Land Use</label>
                                    <select name="land_use_id" id="land_use_id" required
                                        class="w-full border @error('land_use_id') border-red-500 @else border-slate-200 @enderror rounded-lg px-4 py-2.5 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none transition bg-white shadow-sm text-slate-900">
                                        <option value="">Select Land Use</option>
                                        @if(isset($landUses))
                                            @foreach($landUses as $lu)
                                                <option value="{{ $lu->id }}" {{ (old('land_use_id', $recommendation->land_use_id ?? '') == $lu->id) ? 'selected' : '' }}>
                                                    {{ $lu->landuse }}
                                                </option>
                                            @endforeach
                                        @endif
                                    </select>
                                    @error('land_use_id')
                                        <p class="text-red-500 text-[10px] mt-1 font-semibold uppercase">{{ $message }}</p>
                                    @enderror
                                    <input type="hidden" name="land_use" id="land_use_text" value="{{ old('land_use', $recommendation->land_use ?? '') }}">
                                </div>

                                @php
                                    $defaultPurposeId = old('purpose_id', $recommendation->purpose_id ?? '');
                                    if (isset($recommendation) && $recommendation && !$recommendation->purpose_id && $recommendation->purpose_of_clause) {
                                        $defaultPurposeId = 'other';
                                    }
                                    if (old('purpose_id') === 'other') {
                                        $defaultPurposeId = 'other';
                                    }
                                @endphp
                                <div data-batch-child>
                                    <label class="block text-xs font-semibold text-slate-500 uppercase mb-1.5">(b) Purpose Clause</label>
                                    <select name="purpose_id" id="purpose_id"
                                        data-selected="{{ $defaultPurposeId }}"
                                        class="w-full border @error('purpose_id') border-red-500 @else border-slate-200 @enderror rounded-lg px-4 py-2.5 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none transition bg-white shadow-sm">
                                        <option value="">Select Purpose</option>
                                        @if(isset($purposes))
                                            @foreach($purposes as $p)
                                                <option value="{{ $p->id }}" {{ ($defaultPurposeId == $p->id) ? 'selected' : '' }}>
                                                    {{ $p->name }}
                                                </option>
                                            @endforeach
                                        @endif
                                        <option value="other" {{ ($defaultPurposeId == 'other') ? 'selected' : '' }}>Other</option>
                                    </select>
                                    <input type="text" id="purpose_id_other" name="purpose_id_other" placeholder="Specify Purpose..."
                                        value="{{ old('purpose_id_other', ($defaultPurposeId == 'other' ? ($recommendation->purpose_of_clause ?? '') : '')) }}"
                                        class="mt-2 w-full border border-amber-300 rounded-lg px-3 py-2 text-sm focus:border-amber-500 focus:ring-1 focus:ring-amber-500 outline-none transition bg-amber-50" style="display:none;">
                                    @error('purpose_id')
                                        <p class="text-red-500 text-[10px] mt-1 font-semibold uppercase">{{ $message }}</p>
                                    @enderror
                                    <input type="hidden" name="purpose_of_clause" id="purpose_of_clause_text" value="{{ old('purpose_of_clause', $recommendation->purpose_of_clause ?? '') }}">
                                </div>
                            </div>
                            {{-- TP No. — placed here after Land Use / Purpose --}}
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-xs font-semibold text-slate-500 uppercase mb-1.5">TP No.</label>
                                    <select name="layout_plan_no" id="layout_plan_no" style="width:100%;">
                                        @php $existingTp = old('layout_plan_no', $recommendation->layout_plan_no ?? ''); @endphp
                                        @if($existingTp)
                                            <option value="{{ $existingTp }}" selected>{{ $existingTp }}</option>
                                        @endif
                                    </select>
                                    <input type="text" id="layout_plan_no_other" placeholder="Specify TP No..."
                                        class="mt-2 w-full border border-amber-300 rounded-lg px-3 py-2 text-sm focus:border-amber-500 focus:ring-1 focus:ring-amber-500 outline-none transition bg-amber-50" style="display:none;">
                                </div>
                            </div>
                            {{-- Location structured fields --}}
                            <div class="grid grid-cols-3 gap-4" data-batch-child>
                                <div>
                                    <label class="block text-xs font-semibold text-slate-500 uppercase mb-1.5">House No</label>
                                    <input type="text" name="house_no" id="house_no" value="{{ old('house_no', $recommendation->house_no ?? '') }}"
                                        class="loc-part w-full border border-slate-200 rounded-lg px-4 py-2.5 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none transition bg-white shadow-sm" placeholder="e.g. 15">
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-slate-500 uppercase mb-1.5">Plot No</label>
                                    <input type="text" name="plot_number" id="plot_number" value="{{ old('plot_number', $recommendation->plot_number ?? '') }}"
                                        class="loc-part w-full border border-slate-200 rounded-lg px-4 py-2.5 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none transition bg-white shadow-sm" placeholder="e.g. 1002">
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-slate-500 uppercase mb-1.5">Street Name</label>
                                    @php $existingStreet = old('street_name', $recommendation->street_name ?? ''); @endphp
                                    <input type="hidden" name="street_name" id="street_name" value="{{ $existingStreet }}">
                                    <select id="street_name_select" style="width:100%;">
                                        @if($existingStreet)
                                            <option value="{{ $existingStreet }}" selected>{{ $existingStreet }}</option>
                                        @endif
                                    </select>
                                    <input type="text" id="street_name_other" placeholder="Specify street name..."
                                        class="mt-2 w-full border border-amber-300 rounded-lg px-3 py-2 text-sm focus:border-amber-500 focus:ring-1 focus:ring-amber-500 outline-none transition bg-amber-50" style="display:none;">
                                </div>
                            </div>
                            <div class="grid grid-cols-3 gap-4" data-batch-child>
                                <div>
                                    <label class="block text-xs font-semibold text-slate-500 uppercase mb-1.5">District</label>
                                    @php $existingDistrict = old('district', $recommendation->district ?? ''); @endphp
                                    <input type="hidden" name="district" id="district" value="{{ $existingDistrict }}">
                                    <select id="district_select" style="width:100%;">
                                        @if($existingDistrict)
                                            <option value="{{ $existingDistrict }}" selected>{{ $existingDistrict }}</option>
                                        @endif
                                    </select>
                                    <input type="text" id="district_other" placeholder="Specify district..."
                                        class="mt-2 w-full border border-amber-300 rounded-lg px-3 py-2 text-sm focus:border-amber-500 focus:ring-1 focus:ring-amber-500 outline-none transition bg-amber-50" style="display:none;">
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-slate-500 uppercase mb-1.5">LGA</label>
                                    @php $existingLga = old('lga', $recommendation->lga ?? ''); @endphp
                                    <input type="hidden" name="lga" id="lga" value="{{ $existingLga }}">
                                    <select id="lga_select" style="width:100%;">
                                        @if($existingLga)
                                            <option value="{{ $existingLga }}" selected>{{ $existingLga }}</option>
                                        @endif
                                    </select>
                                    <input type="text" id="lga_other" placeholder="Specify LGA..."
                                        class="mt-2 w-full border border-amber-300 rounded-lg px-3 py-2 text-sm focus:border-amber-500 focus:ring-1 focus:ring-amber-500 outline-none transition bg-amber-50" style="display:none;">
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-slate-500 uppercase mb-1.5">State</label>
                                    <input type="hidden" name="state" value="{{ old('state', $recommendation->state ?? 'Kano State') }}">
                                    <input type="text" id="state" value="{{ old('state', $recommendation->state ?? 'Kano State') }}"
                                        class="loc-part w-full border border-slate-200 rounded-lg px-4 py-2.5 outline-none bg-slate-100 text-slate-400 cursor-not-allowed" disabled>
                                </div>
                            </div>
                            <div data-batch-child>
                                <label class="block text-xs font-semibold text-slate-500 uppercase mb-1.5">Full Location <span class="text-slate-400 normal-case font-normal">(auto-generated)</span></label>
                                <input type="text" name="location" id="location" value="{{ old('location', $recommendation->location ?? '') }}"
                                    class="w-full border border-blue-200 rounded-lg px-4 py-2.5 bg-blue-50 focus:border-blue-500 outline-none transition shadow-sm font-medium" readonly>
                            </div>
                        </div>
                    </div>

                    <!-- Section 2: Grant Conditions (Financials) -->
                    <div class="bg-slate-50 border border-slate-100 rounded-xl p-6 space-y-4">
                        <div class="flex items-center gap-2 mb-2">
                            <i data-lucide="banknote" class="h-4 w-4 text-blue-600"></i>
                            <h3 class="text-sm font-bold text-slate-900 uppercase tracking-tight">Grant Conditions</h3>
                        </div>
                        <div class="space-y-4">
                            <input type="hidden" id="base_term" value="{{ old('term', $recommendation->term ?? '99') }}">
                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                                <div>
                                    <label class="block text-xs font-semibold text-slate-500 uppercase mb-1.5">Prevailing Year</label>
                                    <input type="number" name="cofo_year" id="cofo_year"
                                        value="{{ old('cofo_year', $recommendation->cofo_year ?? '') }}"
                                        min="1900" max="{{ date('Y') }}" placeholder="{{ date('Y') }}"
                                        class="w-full border border-slate-200 rounded-lg px-4 py-2.5 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none transition bg-white shadow-sm">
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-slate-500 uppercase mb-1.5">Year</label>
                                    @php
                                        $currentYear = (int) date('Y');
                                        $savedSelectedYear = old('selected_year', $recommendation->selected_year ?? $currentYear);
                                    @endphp
                                    <select name="selected_year" id="selected_year"
                                        class="w-full border border-slate-200 rounded-lg px-4 py-2.5 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none transition bg-white shadow-sm">
                                        <option value="">Select Year</option>
                                        @for($y = $currentYear + 10; $y >= 1990; $y--)
                                            <option value="{{ $y }}" {{ $savedSelectedYear == $y ? 'selected' : '' }}>{{ $y }}</option>
                                        @endfor
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-slate-500 uppercase mb-1.5">Term (Years):</label>
                                    <input type="number" name="term" id="term_input" min="0" max="999" step="1"
                                        value="{{ old('term', $recommendation->term ?? '99') }}"
                                        data-saved="{{ old('term', $recommendation->term ?? '') !== '' ? '1' : '0' }}"
                                        class="w-full border border-slate-200 rounded-lg px-4 py-2.5 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none transition bg-white shadow-sm">
                                    <p id="term_hint" class="mt-1 text-[11px] text-slate-400"></p>
                                </div>
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-500 uppercase mb-1.5">Value for Proposed Dev. (₦):</label>
                                <input type="number" step="0.01" name="development_value" value="{{ old('development_value', $recommendation->development_value ?? '') }}"
                                    class="w-full border border-slate-200 rounded-lg px-4 py-2.5 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none transition bg-white shadow-sm">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-500 uppercase mb-1.5">Time for completion of proposed development: </label>
                                <input type="text" name="development_period" value="{{ old('development_period', $recommendation->development_period ?? '2') }}"
                                    class="w-full border border-slate-200 rounded-lg px-4 py-2.5 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none transition bg-white shadow-sm">
                            </div>
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-xs font-semibold text-slate-500 uppercase mb-1.5">Ground Rent (₦):</label>
                                    <input type="number" step="0.01" name="ground_rent" value="{{ old('ground_rent', $recommendation->ground_rent ?? '') }}"
                                        class="w-full border border-slate-200 rounded-lg px-4 py-2.5 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none transition bg-white shadow-sm">
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-slate-500 uppercase mb-1.5">Dev. Charge: </label>
                                    <input type="text" name="development_charge" value="{{ old('development_charge', $recommendation->development_charge ?? 'To follow') }}"
                                        class="w-full border border-slate-200 rounded-lg px-4 py-2.5 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none transition bg-white shadow-sm">
                                </div>

                               
                            </div>
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-xs font-semibold text-slate-500 uppercase mb-1.5">Survey & Processing Fees (₦)</label>
                                    <input type="number" step="0.01" name="survey_fees" id="survey_fees" value="{{ old('survey_fees', $recommendation->survey_fees ?? '') }}"
                                        class="w-full border border-slate-200 rounded-lg px-4 py-2.5 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none transition bg-white shadow-sm">
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-slate-500 uppercase mb-1.5">Preparation Fees (₦)</label>
                                    <input type="number" step="0.01" name="preparation_fees" id="preparation_fees" value="{{ old('preparation_fees', $recommendation->preparation_fees ?? '') }}"
                                        class="w-full border border-slate-200 rounded-lg px-4 py-2.5 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none transition bg-white shadow-sm">
                                </div>
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-500 uppercase mb-1.5">Preparation Fees in Words <span class="text-slate-400 normal-case font-normal">(auto)</span></label>
                                <input type="text" name="preparation_fees_words" id="preparation_fees_words"
                                    value="{{ old('preparation_fees_words', $recommendation->preparation_fees_words ?? '') }}"
                                    class="w-full border border-blue-100 bg-blue-50 rounded-lg px-4 py-2.5 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none transition shadow-sm"
                                    placeholder="Auto-filled from Preparation Fees above">
                            </div>
                        </div>
                    </div>

              <!-- Section: Conversion Specific Fields (Conditional) -->
                    <div id="conversion-fields-section" class="{{ old('type', $recommendation->type ?? '') == 'Conversion' ? '' : 'hidden' }} bg-amber-50/50 border border-amber-200 rounded-xl p-6 space-y-4 col-span-2">
                        <div class="flex items-center gap-2 mb-2">
                            <i data-lucide="refresh-cw" class="h-4 w-4 text-amber-600"></i>
                            <h3 class="text-sm font-bold text-amber-900 uppercase tracking-tight">Page Number details</h3>
                        </div>
                        
                        <!-- Row 1 & 2: Page Numbers and Survey Report -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                            <div>
                                <label class="block text-xs font-semibold text-slate-500 uppercase mb-1.5 font-mono">Survey Report Page No</label>
                                <input type="text" name="page_survey_report" value="{{ old('page_survey_report', $recommendation->page_survey_report ?? '') }}"
                                    class="w-full border border-slate-200 rounded-lg px-4 py-2.5 bg-white shadow-sm outline-none focus:ring-1 focus:ring-amber-500 transition">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-500 uppercase mb-1.5 font-mono">Survey Report Detail</label>
                                <input type="text" name="survey_report" value="{{ old('survey_report', $recommendation->survey_report ?? '') }}"
                                    class="w-full border border-slate-200 rounded-lg px-4 py-2.5 bg-white shadow-sm outline-none focus:ring-1 focus:ring-amber-500 transition"
                                    placeholder="Reference/Description">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-500 uppercase mb-1.5 font-mono">Application Page No</label>
                                <input type="text" name="page" value="{{ old('page', $recommendation->page ?? '') }}"
                                    class="w-full border border-slate-200 rounded-lg px-4 py-2.5 bg-white shadow-sm outline-none focus:ring-1 focus:ring-amber-500 transition">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-500 uppercase mb-1.5 font-mono">Physical Planning Page No</label>
                                <input type="text" name="page_2" value="{{ old('page_2', $recommendation->page_2 ?? '') }}"
                                    class="w-full border border-slate-200 rounded-lg px-4 py-2.5 bg-white shadow-sm outline-none focus:ring-1 focus:ring-amber-500 transition">
                            </div>
                        </div>

                        <!-- Row 3: Other Metadata -->
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                            <div>
                                <label class="block text-xs font-semibold text-slate-500 uppercase mb-1.5 font-mono">Improvement</label>
                                <input type="text" name="improvement" value="{{ old('improvement', $recommendation->improvement ?? '') }}"
                                    class="w-full border border-slate-200 rounded-lg px-4 py-2.5 bg-white shadow-sm outline-none focus:ring-1 focus:ring-amber-500 transition">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-500 uppercase mb-1.5 font-mono">Revision Period</label>
                                <input type="text" name="revision_period" value="{{ old('revision_period', $recommendation->revision_period ?? '') }}"
                                    class="w-full border border-slate-200 rounded-lg px-4 py-2.5 bg-white shadow-sm outline-none focus:ring-1 focus:ring-amber-500 transition">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-500 uppercase mb-1.5 font-mono">Time of Erection</label>
                                <input type="text" name="time_of_erection" value="{{ old('time_of_erection', $recommendation->time_of_erection ?? '') }}"
                                    class="w-full border border-slate-200 rounded-lg px-4 py-2.5 bg-white shadow-sm outline-none focus:ring-1 focus:ring-amber-500 transition">
                            </div>
                        </div>
                    </div>

                    <!-- Section: RofO Generation Data -->
                    <div class="bg-green-50/40 border border-green-200 rounded-xl p-6 space-y-4 col-span-2">
                        <div class="flex items-center gap-2 mb-2">
                            <i data-lucide="zap" class="h-4 w-4 text-green-600"></i>
                            <h3 class="text-sm font-bold text-green-900 uppercase tracking-tight">RofO Generation Data</h3>
                        </div>
                        <div class="space-y-4">
                            <div class="p-4 bg-white rounded-xl border border-green-100">
                                <span class="block text-xs font-bold text-slate-500 uppercase mb-3">Survey Method (Select One)</span>
                                <div class="space-y-3">
                                    <label class="flex items-center gap-3 cursor-pointer p-3 border border-slate-200 rounded-lg hover:bg-slate-50 transition">
                                        <input type="radio" name="rofo_survey_method" value="DIRECTOR" id="rofo-survey-director"
                                            {{ old('rofo_survey_method', ($recommendation->rofo_director_survey ?? '') === 'YES' ? 'DIRECTOR' : (($recommendation->rofo_licensed_surveyor ?? '') === 'YES' ? 'LICENSED' : '')) === 'DIRECTOR' ? 'checked' : '' }}
                                            class="w-5 h-5 text-green-600 border-slate-300 focus:ring-green-500">
                                        <span class="text-sm font-medium text-slate-700">Require <strong>Director Survey</strong> to carry out survey</span>
                                    </label>
                                    <label class="flex items-center gap-3 cursor-pointer p-3 border border-slate-200 rounded-lg hover:bg-slate-50 transition">
                                        <input type="radio" name="rofo_survey_method" value="LICENSED" id="rofo-survey-licensed"
                                            {{ old('rofo_survey_method', ($recommendation->rofo_director_survey ?? '') === 'YES' ? 'DIRECTOR' : (($recommendation->rofo_licensed_surveyor ?? '') === 'YES' ? 'LICENSED' : '')) === 'LICENSED' ? 'checked' : '' }}
                                            class="w-5 h-5 text-green-600 border-slate-300 focus:ring-green-500">
                                        <span class="text-sm font-medium text-slate-700">Require <strong>Licensed Surveyor</strong> to carry out survey</span>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <script>
                    // Survey method follows the Recommendation Type:
                    //   Direct     → Director Survey
                    //   Conversion → Licensed Surveyor
                    // Still manually overridable after the fact.
                    (function () {
                        var director  = document.getElementById('rofo-survey-director');
                        var licensed  = document.getElementById('rofo-survey-licensed');
                        var recDirect = document.getElementById('rec-direct');
                        var recConv   = document.getElementById('rec-conversion');
                        if (!director || !licensed) return;

                        function syncSurveyMethod() {
                            if (recConv && recConv.checked) {
                                licensed.checked = true;
                            } else if (recDirect && recDirect.checked) {
                                director.checked = true;
                            }
                        }
                        window._syncSurveyMethod = syncSurveyMethod;

                        [recDirect, recConv].forEach(function (radio) {
                            if (radio) radio.addEventListener('change', syncSurveyMethod);
                        });

                        // Only derive on load when nothing was saved/selected yet
                        if (!director.checked && !licensed.checked) syncSurveyMethod();
                    })();
                    </script>

                    <!-- Section 3: Recommendation & Reasons -->
                    <div class="bg-slate-50 border border-slate-100 rounded-xl p-6 space-y-4 col-span-2">
                        <div class="flex items-center gap-2 mb-2">
                            <i data-lucide="check-circle" class="h-4 w-4 text-blue-600"></i>
                            <h3 class="text-sm font-bold text-slate-900 uppercase tracking-tight">Recommendation & Reasons</h3>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-500 uppercase mb-1.5">The Director of Land recommends/does not recommend for the following reasons:</label>
                            <textarea name="recommendation" rows="4" placeholder="Enter reasons for recommendation..."
                                class="w-full border border-slate-200 rounded-lg px-4 py-2.5 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none transition bg-white shadow-sm resize-none">{{ old('recommendation', $recommendation->recommendation ?? '') }}</textarea>
                        </div>
                    </div>

                      <!-- Section 4: System Audit metadata -->
                    <div class="bg-slate-50 border border-slate-100 rounded-xl p-6 space-y-4">
                        <div class="flex items-center gap-2 mb-2">
                            <i data-lucide="info" class="h-4 w-4 text-blue-600"></i>
                            <h3 class="text-sm font-bold text-slate-900 uppercase tracking-tight">Additional Data</h3>
                        </div>
                        <div class="grid grid-cols-2 gap-4">

                            <div>
                                <label class="block text-xs font-semibold text-slate-500 uppercase mb-1.5">Time Generated</label>
                                <input type="text" readonly value="{{ ($isEdit && $recommendation->created_at) ? $recommendation->created_at->format('h:i:s A') : now()->format('h:i:s A') }}"
                                    class="w-full border border-slate-200 rounded-lg px-4 py-2.5 bg-slate-100 text-slate-500 outline-none transition shadow-sm cursor-not-allowed">
                            </div>

                            
                            <div>
                                <label class="block text-xs font-semibold text-slate-500 uppercase mb-1.5">Date Generated</label>
                                <input type="text" readonly value="{{ ($isEdit && $recommendation->created_at) ? $recommendation->created_at->format('Y-m-d') : now()->format('Y-m-d') }}"
                                    class="w-full border border-slate-200 rounded-lg px-4 py-2.5 bg-slate-100 text-slate-500 outline-none transition shadow-sm cursor-not-allowed">
                            </div>
                          
                            <div class="col-span-2">
                                <label class="block text-xs font-semibold text-slate-500 uppercase mb-1.5">Generated By</label>
                                <input type="text" readonly value="{{ $isEdit ? ($recommendation->creator->name ?? 'System') : auth()->user()->name }}"
                                    class="w-full border border-slate-200 rounded-lg px-4 py-2.5 bg-slate-100 text-slate-500 outline-none transition shadow-sm cursor-not-allowed">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Action Footer -->
                <div class="pt-8 border-t border-slate-100 flex justify-end gap-3">
                    <button type="submit" class="px-8 py-3 bg-slate-900 text-white font-bold rounded-lg hover:bg-slate-800 transition shadow-lg">
                        {{ $isEdit ? 'Update' : ($reissuanceSource ? 'Save Re-issuance' : 'Generate Recommendation') }}
                    </button>
                </div>
            </form>
        </div>
    </div>
    @include('admin.footer')
</div>

{{-- Old File Number prompt — shown when an application type derives from an existing file --}}
<div id="atx-old-fileno-modal" class="fixed inset-0 bg-black/60 z-[1000000] hidden items-center justify-center p-4">
    <div class="bg-white rounded-xl shadow-2xl w-full max-w-lg overflow-hidden">
        <div class="px-6 py-4 bg-gradient-to-br from-amber-500 to-amber-600 text-white flex items-center gap-3">
            <i data-lucide="folder-input" class="w-5 h-5"></i>
            <h3 class="text-base font-bold">Old File Number Required</h3>
        </div>
        <div class="px-6 py-5 space-y-4">
            <p class="text-sm text-slate-600">
                <span id="atx-old-fileno-modal-type" class="font-semibold text-slate-900">This application</span>
                derives from an existing file. Select the old (parent) file number from the file number selector.
            </p>
            <div>
                <label class="block text-xs font-semibold text-slate-500 uppercase mb-1.5">Old File Number</label>
                <div class="flex gap-2">
                    <input type="text" id="atx-old-fileno-modal-value" readonly
                        placeholder="Nothing selected yet"
                        class="flex-1 border border-slate-200 rounded-lg px-4 py-2.5 bg-slate-50 font-mono text-sm outline-none">
                    <button type="button" id="atx-old-fileno-modal-pick"
                        class="px-4 py-2.5 text-xs font-semibold bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition whitespace-nowrap">
                        Open Selector
                    </button>
                </div>
            </div>
        </div>
        <div class="px-6 py-4 bg-slate-50 border-t border-slate-100 flex justify-end gap-3">
            <button type="button" id="atx-old-fileno-modal-cancel"
                class="px-4 py-2 text-sm border border-slate-300 text-slate-700 rounded-lg hover:bg-slate-100 transition">
                Cancel
            </button>
            <button type="button" id="atx-old-fileno-modal-confirm" disabled
                class="px-5 py-2 text-sm font-semibold bg-amber-600 text-white rounded-lg hover:bg-amber-700 transition disabled:opacity-50 disabled:cursor-not-allowed">
                Use This File Number
            </button>
        </div>
    </div>
</div>

@include('components.global-fileno-modal')

@push('scripts')
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet"/>
<style>
    /* Make Select2 match Tailwind form inputs */
    .select2-container--default .select2-selection--single {
        height: 42px;
        border: 1px solid #e2e8f0;
        border-radius: 0.5rem;
        background-color: #fff;
        box-shadow: 0 1px 2px 0 rgb(0 0 0 / .05);
        display: flex;
        align-items: center;
    }
    .select2-container--default .select2-selection--single .select2-selection__rendered {
        color: #0f172a;
        line-height: 42px;
        padding-left: 1rem;
        padding-right: 2rem;
        font-size: 0.875rem;
    }
    .select2-container--default .select2-selection--single .select2-selection__placeholder {
        color: #94a3b8;
    }
    .select2-container--default .select2-selection--single .select2-selection__arrow {
        height: 42px;
        right: 8px;
    }
    .select2-container--default.select2-container--focus .select2-selection--single,
    .select2-container--default.select2-container--open .select2-selection--single {
        border-color: #3b82f6;
        box-shadow: 0 0 0 1px #3b82f6;
        outline: none;
    }
    .select2-dropdown {
        border: 1px solid #e2e8f0;
        border-radius: 0.5rem;
        box-shadow: 0 4px 6px -1px rgb(0 0 0 / .1);
        font-size: 0.875rem;
    }
    .select2-container--default .select2-results__option--highlighted[aria-selected] {
        background-color: #3b82f6;
    }
    .select2-search--dropdown .select2-search__field {
        border: 1px solid #e2e8f0;
        border-radius: 0.375rem;
        padding: 0.375rem 0.75rem;
        font-size: 0.875rem;
        outline: none;
    }
    .select2-search--dropdown .select2-search__field:focus {
        border-color: #3b82f6;
    }
</style>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script src="{{ asset('js/global-fileno-modal.js') }}"></script>
<script src="{{ asset('js/land_recommendations.js') }}?v={{ time() + 1 }}"></script>
<script>
// ── Old File Number capture for derived application types ──────────────────
// Plot Subdivision / Plot Merger / Change of Purpose always come off an existing
// file, so selecting one of those types prompts for the parent file number.
document.addEventListener('DOMContentLoaded', function () {
    var TYPES_NEEDING_OLD_FILENO = ['Plot Subdivision', 'Plot Merger', 'Change of Purpose'];

    var row          = document.getElementById('atx-old-fileno-row');
    var field        = document.getElementById('old_file_number');
    var contextLabel = document.getElementById('atx-old-fileno-context');
    var rowPickBtn   = document.getElementById('atx-old-fileno-pick');

    var modal        = document.getElementById('atx-old-fileno-modal');
    var modalType    = document.getElementById('atx-old-fileno-modal-type');
    var modalValue   = document.getElementById('atx-old-fileno-modal-value');
    var modalPick    = document.getElementById('atx-old-fileno-modal-pick');
    var modalCancel  = document.getElementById('atx-old-fileno-modal-cancel');
    var modalConfirm = document.getElementById('atx-old-fileno-modal-confirm');

    if (!row || !field || !modal) return;

    function currentAppType() {
        var toggle  = document.getElementById('app-type-toggle');
        var checked = document.querySelector('.app-type-radio:checked');
        if (toggle && !toggle.checked) return '';
        return checked ? checked.value : '';
    }

    function requiresOldFileNo(appType) {
        return TYPES_NEEDING_OLD_FILENO.indexOf(appType) !== -1;
    }

    function openModal(appType) {
        if (modalType) modalType.textContent = appType;
        if (modalValue) modalValue.value = field.value || '';
        if (modalConfirm) modalConfirm.disabled = !field.value;
        modal.classList.remove('hidden');
        modal.classList.add('flex');
        if (window.lucide) window.lucide.createIcons();
    }

    function closeModal() {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
    }

    function openSelector(onPicked) {
        if (!window.GlobalFileNoModal) {
            alert('File number selector is not available on this page.');
            return;
        }
        window.GlobalFileNoModal.open({
            // The selector auto-fills any [name="file_number"] input by default, which
            // would clobber the recommendation's own file number — this picker must only
            // ever write to the old file number field.
            autoPopulateGenericFields: false,
            targetFields: [],
            callback: function (data) {
                onPicked((data && data.fileNumber) ? data.fileNumber : '');
            }
        });
    }

    function syncRow(appType) {
        if (requiresOldFileNo(appType)) {
            row.classList.remove('hidden');
            if (contextLabel) contextLabel.textContent = appType;
        } else {
            row.classList.add('hidden');
            if (field.value) setOldFileNo('');
        }
    }

    // Batch mode has its own dropdown of subdivided files sitting right in the
    // card, so the "pick a file number" modal would only be in the way.
    function batchModeOn() {
        return recFormEl && recFormEl.classList.contains('batch-mode');
    }
    var recFormEl = document.getElementById('land-recommendation-form');

    // Radio / toggle changes
    document.querySelectorAll('.app-type-radio').forEach(function (radio) {
        radio.addEventListener('change', function () {
            if (!this.checked) return;
            syncRow(this.value);
            if (requiresOldFileNo(this.value) && !field.value && !batchModeOn()) openModal(this.value);
        });
    });

    var appToggle = document.getElementById('app-type-toggle');
    if (appToggle) {
        appToggle.addEventListener('change', function () {
            syncRow(currentAppType());
        });
    }

    // The batch module listens on this field, and assigning .value never fires an
    // event on its own — so every write goes through here.
    function setOldFileNo(fileNumber) {
        field.value = fileNumber || '';
        field.dispatchEvent(new Event('change', { bubbles: true }));
    }

    if (rowPickBtn) {
        rowPickBtn.addEventListener('click', function () {
            openSelector(function (fileNumber) {
                if (fileNumber) setOldFileNo(fileNumber);
            });
        });
    }

    if (modalPick) {
        modalPick.addEventListener('click', function () {
            openSelector(function (fileNumber) {
                if (!fileNumber) return;
                modalValue.value = fileNumber;
                modalConfirm.disabled = false;
            });
        });
    }

    if (modalConfirm) {
        modalConfirm.addEventListener('click', function () {
            setOldFileNo(modalValue.value);
            closeModal();
        });
    }

    if (modalCancel) modalCancel.addEventListener('click', closeModal);
    modal.addEventListener('click', function (e) { if (e.target === modal) closeModal(); });

    // Block submission when a derived type has no old file number
    var recForm = document.getElementById('land-recommendation-form');
    if (recForm) {
        recForm.addEventListener('submit', function (e) {
            var appType = currentAppType();
            if (requiresOldFileNo(appType) && !field.value.trim()) {
                e.preventDefault();
                e.stopPropagation();
                // In batch mode the batch module reports this inline against its
                // own dropdown instead of opening the file-picker modal.
                if (!batchModeOn()) openModal(appType);
            }
        });
    }

    // Initial state (edit mode / validation redisplay)
    syncRow(currentAppType());
});
</script>

@unless($isEdit)
<style>
    /* Values the batch table owns are suppressed on the main form while batch
       mode is on. !important because the application-type script toggles the
       `hidden` class on #app-type-extra independently. */
    #land-recommendation-form.batch-mode [data-batch-child] { display: none !important; }
</style>
<script>
// ── Plot Subdivision batch capture ─────────────────────────────────────────
// One recommendation per child of the mother file. The rest of the form keeps
// capturing the values common to the whole batch; only what differs per child
// lives in the table, and the form posts to the batch endpoint instead.
document.addEventListener('DOMContentLoaded', function () {
    var BATCH_TYPE     = 'Plot Subdivision';
    var MOTHERS_URL    = '{{ route('land-recommendations.subdivision-mothers') }}';
    var CHILDREN_URL   = '{{ route('land-recommendations.subdivision-children') }}';
    var BATCH_ACTION   = '{{ route('land-recommendations.store-batch') }}';
    var PURPOSES_URL   = '{{ url('api/reference/purposes') }}';

    var toggle      = document.getElementById('batch-mode-toggle');
    var hint        = document.getElementById('batch-mode-hint');
    var fileNoCard  = document.getElementById('file-number-card');
    var fileNoInput = document.getElementById('file_number');
    var appToggle   = document.getElementById('app-type-toggle');
    var oldFileNo   = document.getElementById('old_file_number');
    var card        = document.getElementById('batch-children-card');
    var rowsBody    = document.getElementById('batch-children-rows');
    var motherLabel = document.getElementById('batch-mother-label');
    var countLabel  = document.getElementById('batch-children-count');
    var statusBox   = document.getElementById('batch-children-status');
    var selectAll   = document.getElementById('batch-select-all');
    var reloadBtn   = document.getElementById('batch-reload-children');
    var applyAllBtn = document.getElementById('batch-apply-all');
    var motherPick  = document.getElementById('batch-mother-picker');
    var motherSel   = document.getElementById('batch-mother-select');
    var manualPick  = document.getElementById('atx-old-fileno-manual');
    var motherHelp  = document.getElementById('batch-mother-help');
    var manualHelp  = document.getElementById('atx-old-fileno-help');
    var motherField = document.getElementById('batch-mother-file-no');
    var mothersLoaded = false;

    // Sections that move up under Batch Mode while it is on, in the order a batch is
    // actually filled in: pick the type, pick the mother, then work the children.
    // Outside a batch they belong where they are in the markup — Old File Number, for
    // one, only makes sense after Application Type, which is what decides whether an
    // old file number is needed at all — so each remembers its home and goes back.
    var relocatable = ['recommendation-type-block', 'application-type-card', 'atx-old-fileno-row']
        .map(function (id) { return { el: document.getElementById(id), home: null }; })
        .filter(function (r) { return r.el; });
    var recForm     = document.getElementById('land-recommendation-form');
    var singleAction = recForm ? recForm.getAttribute('action') : '';

    if (!toggle || !recForm) return;

    var LAND_USES = @json(($landUses ?? collect())->map(fn ($lu) => ['id' => $lu->id, 'name' => $lu->landuse])->values());
    var purposeCache = {};

    function esc(v) {
        return String(v == null ? '' : v)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    function setStatus(message, tone) {
        if (!statusBox) return;
        if (!message) { statusBox.classList.add('hidden'); statusBox.textContent = ''; return; }
        statusBox.className = 'mb-3 text-xs font-semibold rounded-lg px-3 py-2 ' + (
            tone === 'error' ? 'bg-rose-50 border border-rose-200 text-rose-700'
                             : 'bg-amber-50 border border-amber-200 text-amber-800'
        );
        statusBox.textContent = message;
    }

    function currentAppType() {
        var checked = document.querySelector('.app-type-radio:checked');
        return (appToggle && !appToggle.checked) ? '' : (checked ? checked.value : '');
    }

    // Fields the table now owns are hidden AND disabled — disabled inputs are not
    // submitted, which also stands down their `required` so the browser cannot
    // block the batch post on a field the user can no longer see.
    //
    // Hiding is done with a class on the form rather than per-element, because the
    // application-type script re-shows #app-type-extra whenever a type is picked
    // and would race a per-element toggle back open.
    function standDownPerChildFields(on) {
        recForm.classList.toggle('batch-mode', on);
        document.querySelectorAll('[data-batch-child]').forEach(function (wrap) {
            wrap.querySelectorAll('input, select, textarea').forEach(function (el) {
                if (on) {
                    if (el.dataset.batchWasDisabled === undefined) {
                        el.dataset.batchWasDisabled = el.disabled ? '1' : '0';
                    }
                    el.disabled = true;
                } else if (el.dataset.batchWasDisabled !== undefined) {
                    el.disabled = el.dataset.batchWasDisabled === '1';
                    delete el.dataset.batchWasDisabled;
                }
            });
        });
    }

    function applyBatchMode(on) {
        if (hint) hint.classList.toggle('hidden', !on);

        // The batch has no single file number — each child carries its own.
        if (fileNoCard) fileNoCard.classList.toggle('hidden', on);
        if (fileNoInput) {
            fileNoInput.disabled = on;
            fileNoInput.required = !on;
        }

        // Batch mode only exists for an application type, so the type panel is
        // forced open and held there.
        if (appToggle) {
            if (on && !appToggle.checked) {
                appToggle.checked = true;
                appToggle.dispatchEvent(new Event('change', { bubbles: true }));
            }
            appToggle.disabled = on;
        }

        standDownPerChildFields(on);
        recForm.setAttribute('action', on ? BATCH_ACTION : singleAction);

        // Only posted by the batch endpoint; a disabled input is never submitted.
        if (motherField) {
            motherField.disabled = !on;
            motherField.value = (on && oldFileNo) ? oldFileNo.value.trim() : '';
        }

        // Stack the three sections between Batch Mode and the children table, in
        // array order (inserting each before the table preserves it).
        if (card) {
            relocatable.forEach(function (r) {
                if (on) {
                    if (!r.home) {
                        r.home = { parent: r.el.parentNode, next: r.el.nextSibling };
                    }
                    card.parentNode.insertBefore(r.el, card);
                } else if (r.home) {
                    r.home.parent.insertBefore(r.el, r.home.next);
                }
            });
        }

        // Swap the whole-register file picker for the short list of files that
        // actually have subdivision children.
        if (motherPick) motherPick.classList.toggle('hidden', !on);
        if (manualPick) manualPick.classList.toggle('hidden', on);
        if (motherHelp) motherHelp.classList.toggle('hidden', !on);
        if (manualHelp) manualHelp.classList.toggle('hidden', on);
        if (on) loadMothers();

        if (!on) {
            card.classList.add('hidden');
            rowsBody.innerHTML = '';
            setStatus('');
        } else {
            syncForAppType();
        }
        if (window.lucide) window.lucide.createIcons();
    }

    // The table only makes sense for a subdivision; any other type in batch mode
    // falls back to an empty table and a note rather than silently doing nothing.
    function syncForAppType() {
        if (!toggle.checked) return;
        var type = currentAppType();

        if (type !== BATCH_TYPE) {
            card.classList.add('hidden');
            rowsBody.innerHTML = '';
            setStatus(type ? 'Batch mode currently covers ' + BATCH_TYPE + ' only.' : '', 'warn');
            return;
        }

        setStatus('');
        card.classList.remove('hidden');
        if (oldFileNo && oldFileNo.value.trim()) {
            loadChildren(oldFileNo.value.trim());
        } else {
            rowsBody.innerHTML = '';
            motherLabel.textContent = '—';
            updateCount();
            setStatus('Select the mother file number above to load its children.', 'warn');
        }
    }

    function landUseOptions(selectedId) {
        var out = '<option value="">Select</option>';
        LAND_USES.forEach(function (lu) {
            out += '<option value="' + lu.id + '"' + (String(lu.id) === String(selectedId) ? ' selected' : '') + '>' + esc(lu.name) + '</option>';
        });
        return out;
    }

    function rowHtml(child, index) {
        var i = index;
        var cell = 'w-full border border-slate-200 rounded-md px-2.5 py-2 text-xs bg-white hover:border-slate-300 focus:border-violet-500 focus:ring-2 focus:ring-violet-500/20 outline-none transition';
        var pageCell = 'w-full border border-slate-200 rounded-md px-1.5 py-2 text-xs text-center bg-white hover:border-slate-300 focus:border-violet-500 focus:ring-2 focus:ring-violet-500/20 outline-none transition';
        // Row 1 is the one Apply-to-all copies from, so it is marked as the source.
        var isSource = i === 0;

        // A child that already has a recommendation starts unticked: the server
        // rejects a batch containing one, so leaving it ticked would only produce a
        // failed save. Its fields still show what was captured.
        var alreadyDone = !!child.has_recommendation;

        return ''
            + '<tr class="batch-row border-b border-slate-100 transition ' + (isSource ? 'bg-violet-50/50' : 'odd:bg-white even:bg-slate-50/40') + ' hover:bg-violet-50/70" data-index="' + i + '">'
            + '<td class="px-2 py-2.5 text-center">'
            +   '<input type="checkbox" class="batch-row-check w-4 h-4 text-violet-600 border-slate-300 rounded focus:ring-violet-500 cursor-pointer"' + (alreadyDone ? '' : ' checked') + '>'
            + '</td>'
            + '<td class="px-2 py-2.5 text-center text-xs font-bold text-slate-400">' + (i + 1) + '</td>'
            + '<td class="px-2 py-2.5">'
            +   '<div class="font-mono font-bold text-slate-900 text-xs whitespace-nowrap">' + esc(child.file_number) + '</div>'
            +   (isSource
                    ? '<span class="mt-1 inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-[9px] font-bold bg-violet-100 text-violet-700 uppercase tracking-wide">Source row</span>'
                    : '')
            +   (alreadyDone
                    ? '<span class="mt-1 inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-[9px] font-bold bg-rose-100 text-rose-700" title="' + esc(child.existing_status || '') + ' — already captured, so this row is excluded from the batch">Has a RofO</span>'
                    : '')
            +   '<input type="hidden" name="children[' + i + '][file_number]" value="' + esc(child.file_number) + '">'
            +   '<input type="hidden" name="children[' + i + '][tracking_id]" value="' + esc(child.tracking_id) + '">'
            + '</td>'
            + '<td class="px-2 py-2.5"><input type="text" required class="' + cell + ' batch-f" data-f="applicant_name" name="children[' + i + '][applicant_name]" value="' + esc(child.applicant_name) + '" placeholder="Applicant name"></td>'
            + '<td class="px-2 py-2.5"><input type="text" required class="' + cell + ' batch-f" data-f="applicant_address" name="children[' + i + '][applicant_address]" value="' + esc(child.applicant_address) + '" placeholder="Applicant address"></td>'
            + '<td class="px-2 py-2.5"><input type="text" class="' + cell + ' batch-f font-mono" data-f="plot_number" name="children[' + i + '][plot_number]" value="' + esc(child.plot_number) + '" placeholder="Plot"></td>'
            + '<td class="px-2 py-2.5"><input type="text" class="' + cell + ' batch-f" data-f="location" name="children[' + i + '][location]" value="' + esc(child.location) + '" placeholder="Location"></td>'
            + '<td class="px-2 py-2.5"><select class="' + cell + ' batch-f batch-landuse cursor-pointer" data-f="land_use_id" name="children[' + i + '][land_use_id]">' + landUseOptions(child.land_use_id) + '</select></td>'
            + '<td class="px-2 py-2.5"><select class="' + cell + ' batch-f batch-purpose cursor-pointer" data-f="purpose_id" name="children[' + i + '][purpose_id]"><option value="">Select</option></select></td>'
            + '<td class="px-2 py-2.5">'
            +   '<div class="flex items-center gap-1">'
            +     '<input type="number" min="1" class="' + pageCell + ' batch-f" data-f="page"   name="children[' + i + '][page]"   value="' + esc(child.page) + '"   placeholder="Pg" title="Page No.">'
            +     '<input type="number" min="1" class="' + pageCell + ' batch-f" data-f="page_2" name="children[' + i + '][page_2]" value="' + esc(child.page_2) + '" placeholder="Memo" title="Auth. Memo Page">'
            +     '<input type="number" min="1" class="' + pageCell + ' batch-f" data-f="page_3" name="children[' + i + '][page_3]" value="' + esc(child.page_3) + '" placeholder="Plan" title="Site Plan Page">'
            +   '</div>'
            + '</td>'
            + '</tr>';
    }

    function updateCount() {
        var n = rowsBody.querySelectorAll('.batch-row-check:checked').length;
        var total = rowsBody.querySelectorAll('.batch-row').length;
        countLabel.textContent = n + ' of ' + total + ' selected';
        // Nothing to copy into with fewer than two rows.
        if (applyAllBtn) applyAllBtn.disabled = total < 2;

        // Keep the header box honest when rows arrive part-selected — children that
        // already have a recommendation come back unticked.
        if (selectAll) {
            selectAll.checked = total > 0 && n === total;
            selectAll.indeterminate = n > 0 && n < total;
        }
    }

    function syncRowEnabled(tr) {
        var on = tr.querySelector('.batch-row-check').checked;
        tr.querySelectorAll('input:not(.batch-row-check), select').forEach(function (el) { el.disabled = !on; });
        tr.classList.toggle('opacity-40', !on);
    }

    function loadPurposes(select, landUseId, selectedPurposeId) {
        select.innerHTML = '<option value="">Select</option>';
        if (!landUseId) return Promise.resolve();

        var fetchList = purposeCache[landUseId]
            ? Promise.resolve(purposeCache[landUseId])
            : fetch(PURPOSES_URL + '?landuseid=' + encodeURIComponent(landUseId), { headers: { 'Accept': 'application/json' } })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    var list = Array.isArray(data) ? data : (data.data || data.purposes || []);
                    purposeCache[landUseId] = list;
                    return list;
                })
                .catch(function () { return []; });

        return fetchList.then(function (list) {
            var html = '<option value="">Select</option>';
            list.forEach(function (p) {
                var id = p.id != null ? p.id : p.value;
                var name = p.name != null ? p.name : p.text;
                html += '<option value="' + esc(id) + '"' + (String(id) === String(selectedPurposeId) ? ' selected' : '') + '>' + esc(name) + '</option>';
            });
            html += '<option value="other">Other</option>';
            select.innerHTML = html;
        });
    }

    // Short list of files that already have commissioned subdivision children.
    // Fetched once per page — the set only changes when a subdivision is
    // commissioned, which cannot happen while this form is open.
    function motherLabel(m) {
        return m.file_number + ' — ' + m.children + ' ' + (m.children === 1 ? 'child' : 'children');
    }

    // Searchable picker over the subdivided files. Searching server-side (rather
    // than shipping the whole list and filtering in the browser) is what keeps this
    // usable as more files are subdivided — same approach as the TP No. field.
    function loadMothers() {
        if (mothersLoaded || !motherSel) return;
        mothersLoaded = true;

        // Select2 needs jQuery; without it the plain <select> still works, just
        // without a search box.
        if (!window.jQuery || !jQuery.fn || !jQuery.fn.select2) {
            loadMothersPlain();
            return;
        }

        var $sel = jQuery(motherSel);
        $sel.empty().append(new Option('', '', false, false));

        $sel.select2({
            placeholder: 'Search or select a subdivided file…',
            allowClear: true,
            width: '100%',
            minimumInputLength: 0,
            ajax: {
                url: MOTHERS_URL,
                dataType: 'json',
                delay: 250,
                data: function (params) { return { q: params.term || '' }; },
                processResults: function (data) {
                    var list = (data && data.mothers) || [];
                    return {
                        results: list.map(function (m) {
                            return { id: m.file_number, text: motherLabel(m) };
                        })
                    };
                },
                cache: true
            },
            language: {
                inputTooShort: function () { return 'Type to search…'; },
                noResults: function () { return 'No subdivided file matches'; }
            }
        });

        // Select2 fires jQuery events, which native addEventListener handlers never
        // see — so the pick is written through to the real field here.
        $sel.on('select2:select select2:clear', function () {
            if (!oldFileNo) return;
            oldFileNo.value = $sel.val() || '';
            oldFileNo.dispatchEvent(new Event('change', { bubbles: true }));
        });

        // Keep a selection made before batch mode was switched on (or restored on a
        // validation redisplay) — an AJAX Select2 has no option for it otherwise.
        var current = oldFileNo ? oldFileNo.value.trim() : '';
        if (current) {
            $sel.append(new Option(current, current, true, true)).trigger('change.select2');
        }
    }

    // No-jQuery fallback: one fetch, plain options, no search box.
    function loadMothersPlain() {
        fetch(MOTHERS_URL + '?limit=200', { headers: { 'Accept': 'application/json' } })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                var current = oldFileNo ? oldFileNo.value.trim() : '';
                var list = (data && data.mothers) || [];

                if (!list.length) {
                    motherSel.innerHTML = '<option value="">No subdivided files available</option>';
                    setStatus('No file has commissioned subdivision children yet, so there is nothing to batch.', 'warn');
                    return;
                }

                var html = '<option value="">Select a subdivided file…</option>';
                list.forEach(function (m) {
                    html += '<option value="' + esc(m.file_number) + '"'
                        + (m.file_number === current ? ' selected' : '') + '>' + esc(motherLabel(m)) + '</option>';
                });
                motherSel.innerHTML = html;
            })
            .catch(function () {
                mothersLoaded = false;   // let a retry happen on the next toggle
                motherSel.innerHTML = '<option value="">Could not load subdivided files</option>';
                setStatus('Network error while loading subdivided files.', 'error');
            });
    }

    function loadChildren(mother) {
        motherLabel.textContent = mother;
        if (motherField) motherField.value = mother;
        rowsBody.innerHTML = '<tr><td colspan="10" class="px-3 py-10 text-center text-xs text-slate-500">'
            + '<i data-lucide="loader-2" class="h-5 w-5 mx-auto mb-2 text-violet-400 animate-spin"></i>Loading children…</td></tr>';
        if (window.lucide) window.lucide.createIcons();
        setStatus('');

        fetch(CHILDREN_URL + '?mother_file_no=' + encodeURIComponent(mother), { headers: { 'Accept': 'application/json' } })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (!data.success) {
                    rowsBody.innerHTML = '';
                    setStatus(data.message || 'Could not load children.', 'error');
                    updateCount();
                    return;
                }
                if (!data.children.length) {
                    rowsBody.innerHTML = '<tr><td colspan="10" class="px-3 py-10 text-center text-xs text-slate-400">'
                        + '<i data-lucide="folder-open" class="h-5 w-5 mx-auto mb-2 text-slate-300"></i>'
                        + 'No commissioned subdivision children found for this file.</td></tr>';
                    if (window.lucide) window.lucide.createIcons();
                    setStatus(data.message || 'No subdivision children are linked to ' + mother + '.', 'warn');
                    updateCount();
                    return;
                }

                rowsBody.innerHTML = data.children.map(rowHtml).join('');

                // Purpose options depend on the row's land use, so they are filled
                // after render — passing the existing purpose so a row that already
                // has a recommendation shows the purpose that was captured.
                rowsBody.querySelectorAll('.batch-row').forEach(function (tr, i) {
                    var child      = data.children[i] || {};
                    var landUseSel = tr.querySelector('.batch-landuse');
                    var purposeSel = tr.querySelector('.batch-purpose');
                    if (landUseSel && landUseSel.value) {
                        loadPurposes(purposeSel, landUseSel.value, child.purpose_id || null);
                    }
                    // Rows that came back unticked must also come back disabled, or
                    // their inputs would still post.
                    syncRowEnabled(tr);
                });

                updateCount();
                if (window.lucide) window.lucide.createIcons();
            })
            .catch(function () {
                rowsBody.innerHTML = '';
                setStatus('Network error while loading children.', 'error');
                updateCount();
            });
    }

    // ── events ──
    toggle.addEventListener('change', function () { applyBatchMode(toggle.checked); });

    document.querySelectorAll('.app-type-radio').forEach(function (radio) {
        radio.addEventListener('change', function () { if (toggle.checked) syncForAppType(); });
    });

    if (oldFileNo) {
        oldFileNo.addEventListener('change', function () {
            if (!toggle.checked || currentAppType() !== BATCH_TYPE) return;
            var v = this.value.trim();
            if (motherField) motherField.value = v;
            if (v) { loadChildren(v); }
            else { rowsBody.innerHTML = ''; motherLabel.textContent = '—'; updateCount(); }
        });
    }

    // Picking a mother writes through to the real old_file_number field, whose
    // change event is what triggers the child load.
    if (motherSel) {
        motherSel.addEventListener('change', function () {
            if (!oldFileNo) return;
            oldFileNo.value = this.value;
            oldFileNo.dispatchEvent(new Event('change', { bubbles: true }));
        });
    }

    if (reloadBtn) {
        reloadBtn.addEventListener('click', function () {
            var v = oldFileNo ? oldFileNo.value.trim() : '';
            if (v) loadChildren(v);
        });
    }

    if (selectAll) {
        selectAll.addEventListener('change', function () {
            var on = this.checked;
            rowsBody.querySelectorAll('.batch-row').forEach(function (tr) {
                tr.querySelector('.batch-row-check').checked = on;
                syncRowEnabled(tr);
            });
            updateCount();
        });
    }

    rowsBody.addEventListener('change', function (e) {
        if (e.target.classList.contains('batch-row-check')) {
            syncRowEnabled(e.target.closest('tr'));
            updateCount();
            return;
        }
        if (e.target.classList.contains('batch-landuse')) {
            var purposeSel = e.target.closest('tr').querySelector('.batch-purpose');
            loadPurposes(purposeSel, e.target.value, null);
        }
    });

    if (applyAllBtn) applyAllBtn.addEventListener('click', function () {
        var rows = Array.prototype.slice.call(rowsBody.querySelectorAll('.batch-row'));
        if (rows.length < 2) return;
        var first = rows[0];

        // Values the user keys by hand are copied straight down. The child file
        // number and plot number are never copied — they identify the plot.
        var COPY = ['land_use_id', 'purpose_id', 'page', 'page_2', 'page_3'];

        // Applicant name, address and location are per-owner values (pre-filled from
        // the registry where it holds them). Subdivided plots routinely go to
        // different owners, so copying row 1 over a filled cell would silently put
        // the wrong name or address on a letter — these only fill blank rows.
        var FILL_IF_BLANK = ['applicant_name', 'applicant_address', 'location'];

        rows.slice(1).forEach(function (tr) {
            FILL_IF_BLANK.forEach(function (f) {
                var src = first.querySelector('[data-f="' + f + '"]');
                var dst = tr.querySelector('[data-f="' + f + '"]');
                if (src && dst && !dst.value.trim()) dst.value = src.value;
            });

            COPY.forEach(function (f) {
                var src = first.querySelector('[data-f="' + f + '"]');
                var dst = tr.querySelector('[data-f="' + f + '"]');
                if (!src || !dst) return;

                if (f === 'purpose_id') {
                    // Purpose options depend on the row's land use, so refill first.
                    var landUseId = tr.querySelector('[data-f="land_use_id"]').value;
                    loadPurposes(dst, landUseId, src.value);
                    return;
                }
                dst.value = src.value;
                if (f === 'land_use_id') dst.dispatchEvent(new Event('change', { bubbles: true }));
            });
        });
    });

    recForm.addEventListener('submit', function (e) {
        if (!toggle.checked) return;

        if (currentAppType() !== BATCH_TYPE) {
            e.preventDefault(); e.stopPropagation();
            setStatus('Batch mode covers ' + BATCH_TYPE + ' only — pick that type or switch batch mode off.', 'error');
            return;
        }
        if (!oldFileNo || !oldFileNo.value.trim()) {
            e.preventDefault(); e.stopPropagation();
            setStatus('Select the subdivided (mother) file first.', 'error');
            card.classList.remove('hidden');
            if (motherSel) motherSel.focus();
            return;
        }
        if (!rowsBody.querySelectorAll('.batch-row-check:checked').length) {
            e.preventDefault(); e.stopPropagation();
            setStatus('Tick at least one child to save a batch.', 'error');
            card.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    });
});
</script>
@endunless
<script>
    document.addEventListener('DOMContentLoaded', function () {
        // TP No Select2 — lazy search against tp_lookups (200k+ rows)
        $('#layout_plan_no').select2({
            placeholder: 'Type to search TP No...',
            allowClear: true,
            minimumInputLength: 1,
            ajax: {
                url: '{{ route("instruments.tpLookups.search") }}',
                dataType: 'json',
                delay: 300,
                data: function (params) { return { q: params.term }; },
                processResults: function (data) { return data; },
                cache: true
            }
        });

        // Backfill District/LGA from the selected TP No's known records, but only
        // when the applicant hasn't already picked a District/LGA themselves.
        function backfillDistrictLgaFromTpNo(tpNo) {
            if (!tpNo || tpNo === '__other__') return;

            var districtEmpty = !$('#district').val();
            var lgaEmpty = !$('#lga').val();
            if (!districtEmpty && !lgaEmpty) return;

            $.getJSON('{{ route("instruments.tpLookups.location") }}', { tp_no: tpNo })
                .done(function (data) {
                    if (districtEmpty && data.district) {
                        var distOpt = new Option(data.district, data.district, true, true);
                        $('#district_select').append(distOpt).trigger('change');
                    }
                    if (lgaEmpty && data.lga) {
                        var lgaOpt = new Option(data.lga, data.lga, true, true);
                        $('#lga_select').append(lgaOpt).trigger('change');
                    }
                });
        }

        // TP No "Other → specify": when the applicant can't find their TP No in the
        // list, let them type it in and use that as the submitted value.
        var $tpNoOther = $('#layout_plan_no_other');

        function isUnspecifiedTpNo(val) {
            val = (val || '').trim().toLowerCase();
            return val === '__other__' || val === 'other' || val === 'others';
        }

        $('#layout_plan_no').on('select2:select', function (e) {
            var tpNo = e.params.data.id;
            if (isUnspecifiedTpNo(tpNo)) {
                $tpNoOther.show().focus();
            } else {
                $tpNoOther.hide().val('');
                backfillDistrictLgaFromTpNo(tpNo);
            }
        });

        $tpNoOther.on('input', function () {
            var val = $(this).val().trim().toUpperCase();
            $(this).val(val);
            if (!val) return;
            var opt = new Option(val, val, true, true);
            $('#layout_plan_no').empty().append(opt).trigger('change');
        });

        $tpNoOther.on('blur', function () {
            var val = $(this).val().trim().toUpperCase();
            if (!val) return;
            backfillDistrictLgaFromTpNo(val);
            // Persist so this TP No shows up in future searches too.
            $.post('{{ route("instruments.tpLookups.store") }}', {
                _token: '{{ csrf_token() }}',
                tp_no: val
            });
        });

        // An edit page may render a TP No that was previously saved as the
        // literal "Other"/"__other__" placeholder (from before this specify
        // box existed) — reveal the input so it can be corrected.
        @if(in_array(strtolower(trim($existingTp ?? '')), ['__other__', 'other', 'others'], true))
            $tpNoOther.show();
        @endif

        // Helper: wire up a Select2 with an "Other → specify" pattern
        // selectId: jQuery selector for the <select>
        // hiddenId: jQuery selector for the hidden input (actual submitted value)
        // otherId:  jQuery selector for the specify text input
        function initOtherSelect2(selectId, hiddenId, otherId, ajaxUrl, searchParam) {
            var $sel    = $(selectId);
            var $hidden = $(hiddenId);
            var $other  = $(otherId);

            $sel.select2({
                placeholder: 'Type to search...',
                allowClear: true,
                minimumInputLength: 1,
                ajax: {
                    url: ajaxUrl,
                    dataType: 'json',
                    delay: 300,
                    data: function (params) {
                        var d = {};
                        d[searchParam] = params.term;
                        return d;
                    },
                    processResults: function (data) {
                        return {
                            results: (data.data || []).map(function (item) {
                                return { id: item.name, text: item.name };
                            })
                        };
                    },
                    cache: true
                }
            });

            $sel.on('change', function () {
                var val = $(this).val() || '';
                if (val.toLowerCase() === 'other') {
                    $other.show().focus();
                    $hidden.val('');
                } else {
                    $other.hide().val('');
                    $hidden.val(val);
                }
                if (window._buildLocation) window._buildLocation();
            });

            $other.on('input', function () {
                $hidden.val($(this).val());
                if (window._buildLocation) window._buildLocation();
            });
        }

        // Toggle custom purpose field when 'other' is selected
        var $purposeSelect = $('#purpose_id');
        var $purposeOther = $('#purpose_id_other');
        var $purposeText = $('#purpose_of_clause_text');

        function togglePurposeOther() {
            var val = $purposeSelect.val();
            if (val === 'other') {
                $purposeOther.show().focus();
            } else {
                $purposeOther.hide();
                if (val) {
                    $purposeText.val($purposeSelect.find('option:selected').text().trim());
                } else {
                    $purposeText.val('');
                }
            }
        }

        $purposeSelect.on('change', togglePurposeOther);

        // Run on load
        if ($purposeSelect.val() === 'other' || ($purposeSelect.val() === null && $purposeOther.val() !== '')) {
            $purposeOther.show();
        } else {
            $purposeOther.hide();
        }

        $purposeOther.on('input', function() {
            if ($purposeSelect.val() === 'other') {
                $purposeText.val($(this).val());
            }
        });

        initOtherSelect2('#street_name_select', '#street_name', '#street_name_other',
            '/api/reference/streets', 'search');

        initOtherSelect2('#district_select', '#district', '#district_other',
            '/api/reference/districts', 'search');

        initOtherSelect2('#lga_select', '#lga', '#lga_other',
            '/api/reference/lgas', 'search');

        // Run once on load: an edit page renders the TP No as already selected
        // (from the saved record), so select2:select never fires for it.
        @if($existingTp ?? false)
            backfillDistrictLgaFromTpNo(@json($existingTp));
        @endif

        // A District may already be known on load (either saved directly, or
        // backed into from a legacy `location` value by the edit() controller).
        // If so and LGA is still blank, resolve LGA from that District.
        if ($('#district').val() && !$('#lga').val()) {
            $.getJSON('{{ route("instruments.districtLookups.lga") }}', { district: $('#district').val() })
                .done(function (data) {
                    if (data.lga && !$('#lga').val()) {
                        var lgaOpt = new Option(data.lga, data.lga, true, true);
                        $('#lga_select').append(lgaOpt).trigger('change');
                    }
                });
        }

        // Rebuild Full Location from the structured fields on load, so a stale/legacy
        // value stored in the database (e.g. one that includes a "Plot" prefix) gets
        // replaced with the current auto-generated format.
        if (window._buildLocation) window._buildLocation();

        // ── Number to Naira Words ──
        function numberToNairaWords(num) {
            num = parseFloat(num);
            if (isNaN(num) || num < 0) return '';
            if (num === 0) return 'Zero Naira Only';
            var ones = ['','One','Two','Three','Four','Five','Six','Seven','Eight','Nine',
                        'Ten','Eleven','Twelve','Thirteen','Fourteen','Fifteen','Sixteen',
                        'Seventeen','Eighteen','Nineteen'];
            var tens = ['','','Twenty','Thirty','Forty','Fifty','Sixty','Seventy','Eighty','Ninety'];
            function h(n) {
                if (n === 0) return '';
                if (n < 20) return ones[n] + ' ';
                if (n < 100) return tens[Math.floor(n/10)] + (n%10 ? '-'+ones[n%10] : '') + ' ';
                return ones[Math.floor(n/100)] + ' Hundred ' + h(n % 100);
            }
            function convert(n) {
                if (n === 0) return '';
                if (n < 1000) return h(n);
                if (n < 1000000) return convert(Math.floor(n/1000)) + 'Thousand ' + h(n%1000);
                if (n < 1000000000) return convert(Math.floor(n/1000000)) + 'Million ' + convert(n%1000000);
                return convert(Math.floor(n/1000000000)) + 'Billion ' + convert(n%1000000000);
            }
            var intPart = Math.floor(Math.abs(num));
            var decPart = Math.round((Math.abs(num) - intPart) * 100);
            var result = convert(intPart).trim() + ' Naira';
            if (decPart > 0) result += ' and ' + h(decPart).trim() + ' Kobo';
            return result.trim() + ' Only';
        }

        // Auto-fill Premium in Words from Premium ₦
        var premiumEl = document.getElementById('premium');
        var premiumWordsEl = document.getElementById('premium_words');
        if (premiumEl && premiumWordsEl) {
            premiumEl.addEventListener('input', function () {
                premiumWordsEl.value = this.value ? numberToNairaWords(this.value) : '';
            });
        }

        // Auto-fill Preparation Fees in Words from Preparation Fees ₦
        var prepEl = document.getElementById('preparation_fees');
        var prepWordsEl = document.getElementById('preparation_fees_words');
        if (prepEl && prepWordsEl) {
            prepEl.addEventListener('input', function () {
                prepWordsEl.value = this.value ? numberToNairaWords(this.value) : '';
            });
        }

        // ── Plot sizes: serialize rows to JSON on form submit ──
        document.getElementById('land-recommendation-form').addEventListener('submit', function () {
            var activeSuffix = null;
            var plPanel  = document.getElementById('atx-panel-private-layout');
            var subPanel = document.getElementById('atx-panel-subdivision');
            var mrgPanel = document.getElementById('atx-panel-merger');
            var extPanel = document.getElementById('atx-panel-extension');
            if (plPanel  && !plPanel.classList.contains('hidden'))  activeSuffix = 'pl';
            if (subPanel && !subPanel.classList.contains('hidden')) activeSuffix = 'sub';
            if (mrgPanel && !mrgPanel.classList.contains('hidden')) activeSuffix = 'mrg';
            if (extPanel && !extPanel.classList.contains('hidden')) activeSuffix = 'ext';
            if (!activeSuffix) return;
            var rows  = document.querySelectorAll('#plot-sizes-rows-' + activeSuffix + ' .plot-size-row');
            var sizes = Array.from(rows).map(function (row) {
                var cEl = row.querySelector('.plot-count');
                return {
                    length: row.querySelector('.plot-length').value.trim(),
                    width:  row.querySelector('.plot-width').value.trim(),
                    count:  cEl ? cEl.value.trim() : '',
                };
            }).filter(function (s) { return s.length || s.width || s.count; });
            document.getElementById('plot_sizes_json').value = sizes.length ? JSON.stringify(sizes) : '';
        });

        // ── Plot sizes: load saved rows on page load (edit mode) ──
        (function () {
            var el = document.getElementById('plot_sizes_json');
            if (!el || !el.value) return;
            var savedAppType = @json($savedAppType);
            var suffix = null, showCount = true;
            if (savedAppType === 'Private Layout')    { suffix = 'pl';  showCount = true; }
            else if (savedAppType === 'Plot Subdivision') { suffix = 'sub'; showCount = false; }
            else if (savedAppType === 'Plot Merger')      { suffix = 'mrg'; showCount = false; }
            else if (savedAppType === 'Plot Extension')   { suffix = 'ext'; showCount = false; }
            if (!suffix) return;
            var sizes;
            try { sizes = JSON.parse(el.value); } catch (e) { return; }
            if (!Array.isArray(sizes)) return;
            sizes.forEach(function (s) { addPlotSizeRow(suffix, s, showCount); });
        })();

        @if($errors->any())
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    icon: 'error',
                    title: 'Validation Error',
                    html: `<ul style="text-align:left;padding-left:16px">{{ implode('', array_map(fn($e) => "<li>$e</li>", $errors->all())) }}</ul>`,
                    confirmButtonColor: '#dc2626',
                });
            }
        @endif

        @if(session('success'))
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    icon: 'success',
                    title: 'Success',
                    text: @json(session('success')),
                    confirmButtonColor: '#059669',
                    timer: 4000,
                    timerProgressBar: true,
                });
            }
        @endif
    });
</script>
@endpush
@endsection
