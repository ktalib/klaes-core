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

    // Editing a whole saved batch. The form is the capture screen filled back in:
    // the common fields render from the first child (below, exactly as any edit
    // does) and $batchEdit seeds the table and the per-file steppers. It is an
    // edit AND a batch, which is why the batch markup can no longer hang off
    // "not an edit".
    $batchEdit    = $batchEdit ?? null;
    $batchCapture = !$isEdit || $batchEdit;
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
                    @if(request('return_to') === 'rofo')
                        <a href="{{ route('land-rofos.index', array_filter(['tab' => request('return_tab')])) }}" class="px-4 py-2 text-sm font-medium text-slate-700 bg-white hover:bg-slate-50 rounded-lg transition border border-slate-200 shadow-sm">
                            Back to RofO Table
                        </a>
                    @else
                        <a href="{{ route('land-recommendations.index') }}?type=ROFO" class="px-4 py-2 text-sm font-medium text-slate-700 bg-white hover:bg-slate-50 rounded-lg transition border border-slate-200 shadow-sm">
                            View Records
                        </a>
                    @endif
                </div>
            </div>

            <form id="land-recommendation-form" action="{{ $batchEdit ? route('land-recommendations.batch-update', $batchEdit['batch_id']) : ($isEdit ? route('land-recommendations.update', $recommendation->id) : route('land-recommendations.store')) }}" method="POST" class="p-8 space-y-8"
                data-dupcheck-url="{{ route('land-recommendations.check-duplicate') }}"
                data-opmatch-check-url="{{ route('land-recommendations.op-match.check') }}"
                data-opmatch-url="{{ route('land-recommendations.op-match.store') }}"
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
                    {{-- Where this edit was opened from. A RofO row is edited through this
                         same form, but its officer is working the RofO table, not the
                         recommendation register, so the save has to land them back on the
                         row they came from. Carried as a hidden field as well as in the
                         URL, so a validation bounce keeps it. --}}
                    @if(request('return_to') === 'rofo')
                        <input type="hidden" name="return_to" value="rofo">
                        <input type="hidden" name="return_tab" value="{{ request('return_tab') }}">
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
                    {{-- The re-issued letter prints "supersedes the previous one issued on ...".
                         A legacy original pre-dates KLAES, so nothing on record holds that
                         date and it is keyed in; a KLAES original's generation date is on
                         record and prefills the field. Also editable in the Print Manager. --}}
                    {{-- Shown for both sources now: a KLAES re-issue starts from the date
                         the original was generated (on record), a legacy one is keyed in. --}}
                    @if(in_array($reissuanceSource, ['legacy', 'klaes'], true))
                        <div class="bg-white border border-amber-200 rounded-xl p-4">
                            <label for="reissuance_original_date" class="block text-xs font-bold text-amber-700 uppercase tracking-wider mb-2">
                                Date the Original RofO Was Issued <span class="text-red-500">*</span>
                            </label>
                            <input type="date" name="reissuance_original_date" id="reissuance_original_date" required
                                value="{{ old('reissuance_original_date', optional(($recommendation->reissuance_original_date ?? null)
                                    ?? ($reissuanceSource === 'klaes' ? ($recommendation->rofo_generated_at ?? null) : null))->format('Y-m-d')) }}"
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

                {{-- This record was captured as part of a batch, and is being edited on
                     its own. Both are valid — a correction to one file belongs here —
                     but a correction that runs across the batch does not, so the way
                     to the whole batch is offered rather than left to be found. --}}
                @if($isEdit && !$batchEdit && !empty($recommendation->rofo_batch_id))
                    <div class="bg-violet-50/60 border border-violet-200 rounded-xl px-5 py-4 flex flex-wrap items-center justify-between gap-3">
                        <p class="text-xs text-violet-900">
                            <span class="font-bold">Part of batch</span>
                            <span class="font-mono font-bold">{{ $recommendation->rofo_batch_id }}</span>
                            &mdash; changes here affect this file only.
                        </p>
                        <a href="{{ route('land-recommendations.batch-edit', $recommendation->rofo_batch_id) }}"
                           class="px-4 py-2 text-xs font-bold text-white bg-violet-600 rounded-lg hover:bg-violet-700 transition">
                            Edit the whole batch
                        </a>
                    </div>
                @endif

                {{-- ── Batch Mode ────────────────────────────────────────────────
                     Two kinds of batch, one table. A Plot Subdivision produces many
                     child files off one mother file; a regular batch is any set of
                     files the officer picks. Either way the common grant conditions
                     are keyed once below and only the values that differ per file go
                     in the table. Present on capture, and again when a saved batch is
                     re-opened for editing — a single-record edit has no use for it. --}}
                @if($batchCapture)
                @if($batchEdit)
                    {{-- Editing a saved batch: the mode is not a choice here, so the
                         switch and the kind radios are replaced by what is being
                         edited. The switch itself still exists (hidden, and forced on
                         by the script) because every branch below reads it. --}}
                    <div class="bg-violet-50/60 border-2 border-violet-300 rounded-xl p-6">
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <div>
                                <label class="block text-sm font-extrabold text-slate-900 uppercase tracking-wider">Editing Batch</label>
                                <span class="inline-flex items-center gap-1.5 mt-1.5 px-2.5 py-1 rounded-full bg-white border border-violet-200 text-violet-800">
                                    <i data-lucide="layers" class="h-3.5 w-3.5 flex-shrink-0"></i>
                                    <span class="text-xs font-mono font-bold leading-none">{{ $batchEdit['batch_id'] }}</span>
                                </span>
                            </div>
                            <a href="{{ route('land-recommendations.index', ['type' => 'ROFO', 'tab' => 'batches']) }}"
                               class="px-4 py-2 text-xs font-bold text-slate-700 bg-white border border-slate-200 rounded-lg hover:bg-slate-50 transition">
                                Back to batches
                            </a>
                        </div>
                        <ul class="mt-3 space-y-1 text-[11px] text-violet-900 list-disc list-inside">
                            <li>Every value below is what was saved &mdash; change what needs changing and save the batch.</li>
                            <li>Use the <span class="font-semibold">1 of N</span> arrows on the stepped cards to move between the files.</li>
                            <li>A <span class="font-semibold">ticked</span> row is written back. An <span class="font-semibold">unticked</span> one is left exactly as it stands &mdash; nothing is ever deleted here.</li>
                        </ul>
                    </div>
                @endif
                <div id="batch-mode-card" class="bg-violet-50/60 border border-violet-200 rounded-xl p-6">
                    {{-- Editing a saved batch: the mode is not a choice, so the switch
                         is stood down. The checkbox itself stays in the DOM — every
                         branch of the batch script reads it. The kind chooser goes the
                         same way, but from the script, which owns its visibility.
                         Everything else in this card (the file picker, the regular-batch
                         note) is still live while editing. --}}
                    <div class="flex flex-wrap items-center justify-between gap-3 {{ $batchEdit ? 'hidden' : '' }}">
                        <div>
                            <label class="block text-sm font-extrabold text-slate-900 uppercase tracking-wider">Batch Mode</label>
                            <span class="inline-flex items-center gap-1.5 mt-1.5 px-2.5 py-1 rounded-full bg-violet-50 border border-violet-200 text-violet-800">
                                <i data-lucide="layers" class="h-3.5 w-3.5 flex-shrink-0"></i>
                                <span class="text-xs font-semibold leading-none">Capture one recommendation per file in a single pass.</span>
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
                    {{-- ── Which kind of batch ───────────────────────────────────────
                         The two differ only in where the table's rows come from: one
                         mother file's commissioned children, or a set of file numbers
                         the officer picks by hand. Everything downstream — the common
                         fields, the table, Apply-to-all, autosave, the save itself —
                         is the same, which is why this is a choice inside batch mode
                         rather than a second mode beside it. --}}
                    <div id="batch-kind-row" class="hidden mt-4 grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <label class="flex items-start gap-3 cursor-pointer p-3.5 bg-white border-2 border-slate-200 rounded-xl hover:border-violet-400 transition shadow-sm has-[:checked]:border-violet-500 has-[:checked]:bg-violet-50/60">
                            <input type="radio" name="batch_kind_ui" value="subdivision" checked
                                class="batch-kind-radio mt-0.5 w-4 h-4 text-violet-600 focus:ring-violet-500 border-slate-300">
                            <span class="leading-snug">
                                <span class="block text-sm font-bold text-slate-900">Subdivision batch</span>
                                <span class="block text-[11px] text-slate-500">
                                    Pick a subdivided mother file; every commissioned child loads into the table.
                                </span>
                            </span>
                        </label>
                        <label class="flex items-start gap-3 cursor-pointer p-3.5 bg-white border-2 border-slate-200 rounded-xl hover:border-violet-400 transition shadow-sm has-[:checked]:border-violet-500 has-[:checked]:bg-violet-50/60">
                            <input type="radio" name="batch_kind_ui" value="regular"
                                class="batch-kind-radio mt-0.5 w-4 h-4 text-violet-600 focus:ring-violet-500 border-slate-300">
                            <span class="leading-snug">
                                <span class="block text-sm font-bold text-slate-900">Regular files</span>
                                <span class="block text-[11px] text-slate-500">
                                    Select any number of file numbers yourself &mdash; no subdivision lineage needed.
                                </span>
                            </span>
                        </label>
                    </div>

                    <p id="batch-mode-hint" class="hidden mt-3 text-xs text-violet-900 bg-white/70 border border-violet-200 rounded-lg px-3 py-2">
                        Pick <span class="font-bold">Plot Subdivision</span> below, then select the mother file &mdash;
                        its children load into a table where you key only what differs between them.
                    </p>

                    {{-- What a regular batch does differently, said once and up front.
                         Without it the two steppers further down the form look like a
                         glitch rather than the point — and the locked Recommendation
                         Type and stood-down Application Type look like bugs. --}}
                    <div id="batch-regular-info" class="hidden mt-3 rounded-xl border border-sky-200 bg-sky-50 px-4 py-3">
                        <p class="text-xs font-bold text-sky-900 flex items-center gap-1.5">
                            <i data-lucide="info" class="h-4 w-4"></i>
                            Regular batch is on &mdash;
                            <span id="batch-regular-info-count" class="font-mono">no files picked yet</span>
                        </p>
                        <ul class="mt-2 space-y-1 text-[11px] text-sky-800 list-disc list-inside">
                            <li>Each file is saved as its own RofO recommendation, grouped under one batch.</li>
                            <li><span class="font-semibold">Grant Conditions</span> and <span class="font-semibold">TP No.</span>
                                are captured per file &mdash; use the <span class="font-semibold">1 of N</span> arrows on those
                                cards to move through the batch.</li>
                            <li><span class="font-semibold">Recommendation Type</span> is taken from the file numbers
                                (any <span class="font-mono">CON</span> file makes the batch a Conversion), and
                                <span class="font-semibold">Application Type</span> stays off.</li>
                           
                        </ul>
                    </div>

                    {{-- ── Regular batch: the file picker ────────────────────────────
                         Multi-select with tagging, so a file number that is not in the
                         registers yet can still be typed in — a paper file being
                         captured for the first time is exactly the case a fixed list
                         would lock out. Nothing loads until Apply is pressed: fetching
                         on every pick would repaint (and could discard) a table the
                         officer is part-way through keying. --}}
                    <div id="batch-files-picker" class="hidden mt-4 rounded-xl border border-violet-200 bg-white p-4">
                        <div class="flex items-center gap-2 mb-2">
                            <i data-lucide="files" class="h-4 w-4 text-violet-600"></i>
                            <label class="text-sm font-bold text-slate-900 uppercase tracking-tight">File Numbers</label>
                            <span id="batch-files-count"
                                class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-slate-100 text-slate-600">0 picked</span>
                        </div>
                        <div class="flex flex-col md:flex-row md:items-start gap-3">
                            <div class="flex-1 min-w-0">
                                <select id="batch-file-select" multiple
                                    class="w-full border border-violet-300 rounded-lg px-3 py-2.5 bg-white text-sm outline-none shadow-sm"></select>
                                <p class="mt-1.5 text-[11px] text-slate-500">
                                    Type to search, then pick as many as you need. A file number that is not found can be
                                    typed in and added as-is. Files that already carry a recommendation are marked and cannot be picked.
                                </p>
                            </div>
                            <div class="flex items-center gap-2 flex-shrink-0">
                                <button type="button" id="batch-files-apply"
                                    class="px-5 py-2.5 text-xs font-bold bg-violet-600 text-white rounded-lg hover:bg-violet-700 disabled:opacity-40 disabled:cursor-not-allowed transition inline-flex items-center gap-1.5">
                                    <i data-lucide="table" class="h-4 w-4"></i> Apply
                                </button>
                                <button type="button" id="batch-files-clear"
                                    class="px-3 py-2.5 text-xs font-semibold bg-white border border-slate-300 text-slate-600 rounded-lg hover:bg-slate-50 transition">
                                    Clear
                                </button>
                            </div>
                        </div>
                    </div>

                    {{-- ── Draft / autosave ──────────────────────────────────────────
                         A subdivision of 100+ children takes longer to key than a
                         session lives. Everything typed is written to a draft every
                         few seconds, so a timeout, a 419, or a closed tab costs
                         nothing — the capture is picked back up from the list below.
                         Only shown once batch mode is on; outside a batch the single
                         record form is short enough not to need it. --}}
                    <div id="batch-draft-bar" class="hidden mt-3 rounded-lg border border-violet-200 bg-white/80 px-3 py-2.5">
                        <div class="flex flex-wrap items-center gap-x-3 gap-y-2">
                            <span class="inline-flex items-center gap-1.5 text-[11px] font-bold text-violet-900 uppercase tracking-wider">
                                <i data-lucide="save" class="h-3.5 w-3.5"></i> Autosave
                            </span>

                            {{-- One line, three states: idle / saving / failed. The wording is
                                 deliberately explicit about where the work is, because that is
                                 the whole reassurance this bar exists to give. --}}
                            <span id="batch-draft-status" class="text-[11px] font-semibold text-slate-500">Not started &mdash; keying anything starts a draft.</span>

                            <div class="ml-auto flex items-center gap-2">
                                <button type="button" id="batch-draft-save-now"
                                    class="px-2.5 py-1 text-[11px] font-semibold bg-white border border-violet-300 text-violet-700 rounded-lg hover:bg-violet-50 transition inline-flex items-center gap-1.5">
                                    <i data-lucide="save" class="h-3.5 w-3.5"></i> Save draft now
                                </button>
                                <button type="button" id="batch-draft-resume"
                                    class="px-2.5 py-1 text-[11px] font-semibold bg-white border border-slate-300 text-slate-700 rounded-lg hover:bg-slate-50 transition inline-flex items-center gap-1.5">
                                    <i data-lucide="history" class="h-3.5 w-3.5"></i> Resume a draft
                                    <span id="batch-draft-count" class="hidden px-1.5 py-0.5 rounded-full bg-violet-600 text-white text-[9px] font-bold">0</span>
                                </button>
                                <button type="button" id="batch-draft-discard"
                                    class="hidden px-2.5 py-1 text-[11px] font-semibold bg-white border border-rose-300 text-rose-700 rounded-lg hover:bg-rose-50 transition inline-flex items-center gap-1.5">
                                    <i data-lucide="trash-2" class="h-3.5 w-3.5"></i> Discard draft
                                </button>
                            </div>
                        </div>

                        {{-- Raised when a save is rejected because the session is gone. The
                             work is still held in this tab (and in local storage), so the
                             fix is to sign in again in a second tab and retry — never to
                             reload, which is what would actually lose it. --}}
                        <div id="batch-draft-session-warning" class="hidden mt-2 rounded-lg border border-rose-300 bg-rose-50 px-3 py-2 text-[11px] text-rose-800">
                            <p class="font-bold flex items-center gap-1.5">
                                <i data-lucide="alert-triangle" class="h-3.5 w-3.5"></i>
                                Your session has expired &mdash; the draft could not be saved to the server.
                            </p>
                            <p class="mt-1">
                                Nothing is lost: this page still holds everything you keyed, and a copy is kept in this
                                browser. <span class="font-bold">Do not reload this page.</span>
                                <a href="{{ url('/login') }}" target="_blank" rel="noopener" class="underline font-bold">Sign in again in a new tab</a>,
                                come back here, then press
                                <button type="button" id="batch-draft-retry" class="underline font-bold">Retry save</button>.
                            </p>
                        </div>

                        {{-- Open drafts belonging to this user. Populated on demand — a list
                             fetched on every page load would be a query nobody reads. --}}
                        <div id="batch-draft-list" class="hidden mt-2 rounded-lg border border-slate-200 bg-white divide-y divide-slate-100 max-h-56 overflow-y-auto"></div>
                    </div>
                </div>

                {{-- Outside the children card on purpose. It used to sit inside it, which
                     meant every message explaining why there are no children — "Select the
                     mother file number above", "a subdivision batch covers Plot Subdivision
                     only" — was written into a box that the very same code path had just
                     hidden along with the card. The batch simply did nothing and said
                     nothing. It has to outlive the card to be able to explain it. --}}
                <div id="batch-children-status" class="hidden text-xs font-semibold rounded-lg px-3 py-2"></div>

                {{-- ── Batch: children of the mother file ────────────────────────────
                     Sits directly under the Batch Mode switch so the captured rows stay
                     at the top of the form. Only per-child values live here; everything
                     else on the form (dates, term, fees, premium, recommendation text)
                     is captured once and copied onto every child that is ticked. --}}
                {{-- A saved batch is edited as two sets: files already saved in the
                     batch remain below, while its other eligible subdivision children
                     stay above and can be added without rebuilding the batch. --}}
                <div id="batch-remaining-card" class="hidden mb-5 bg-amber-50/50 border-2 border-amber-200 rounded-xl shadow-sm overflow-hidden">
                    <div class="px-5 py-4 border-b border-amber-200 bg-amber-50 flex items-center justify-between gap-4">
                        <div>
                            <h3 class="font-extrabold text-amber-950 text-sm">Remaining Files</h3>
                            <p class="text-[11px] text-amber-800 mt-0.5">All children outside this batch. Available records can be added below; previously processed records stay visible for a complete count.</p>
                        </div>
                        <span id="batch-remaining-count" class="px-2.5 py-1 rounded-full bg-white border border-amber-200 text-xs font-bold text-amber-800">0 of 0 remaining</span>
                    </div>
                    <div class="max-h-64 overflow-auto">
                        <table class="w-full text-left text-xs">
                            <thead class="sticky top-0 bg-amber-50 border-b border-amber-200 text-[10px] uppercase tracking-wide text-amber-900">
                                <tr><th class="w-12 px-4 py-3 text-center"><input id="batch-remaining-select-all" type="checkbox" class="h-4 w-4 rounded border-amber-300 text-violet-600 focus:ring-violet-500" aria-label="Select all available remaining records"></th><th class="px-4 py-3">File No</th><th class="px-4 py-3">Plot No</th><th class="px-4 py-3">Applicant</th><th class="px-4 py-3">Location</th><th class="px-4 py-3 text-right">Status</th></tr>
                            </thead>
                            <tbody id="batch-remaining-rows"></tbody>
                        </table>
                    </div>
                    <div class="flex items-center justify-between gap-4 border-t border-amber-200 bg-amber-50 px-5 py-3">
                        <span id="batch-remaining-selected-count" class="text-xs font-semibold text-amber-900">No records selected</span>
                        <button id="batch-add-selected-remaining" type="button" disabled class="inline-flex items-center gap-2 rounded-lg bg-violet-600 px-4 py-2 text-xs font-bold text-white shadow-sm transition hover:bg-violet-700 disabled:cursor-not-allowed disabled:bg-slate-300 disabled:text-slate-500">
                            <i data-lucide="arrow-down" class="h-4 w-4"></i> Add to batch
                        </button>
                    </div>
                </div>

                {{-- The two tables are a two-way queue while editing: add a remaining
                     child downward, or untick a saved child to return it upward. --}}
                <div id="batch-table-transfer-arrows" class="hidden -my-2 relative z-10 flex justify-center items-center gap-3 text-[10px] font-bold">
                    <span class="inline-flex items-center gap-1.5 rounded-full border border-violet-200 bg-white px-3 py-1.5 text-violet-700 shadow-sm" title="Add to saved batch">
                        <i data-lucide="arrow-down" class="h-3.5 w-3.5"></i> Add
                    </span>
                    <span class="inline-flex items-center gap-1.5 rounded-full border border-amber-200 bg-white px-3 py-1.5 text-amber-800 shadow-sm" title="Untick to return to remaining records">
                        <i data-lucide="arrow-up" class="h-3.5 w-3.5"></i> Return
                    </span>
                </div>

                <div id="batch-children-card" class="hidden bg-white border-2 border-violet-200 rounded-xl shadow-sm overflow-hidden">
                    {{-- The mother file the batch is keyed to. old_file_number carries the
                         same value, but the batch endpoint validates this one explicitly —
                         it is what groups the saved children together. Disabled outside
                         batch mode so the single-record post never sees it. --}}
                    <input type="hidden" name="batch_mother_file_no" id="batch-mother-file-no" disabled value="">
                    {{-- Which kind of batch is being posted. storeBatch() branches on
                         it for the mother-file rules; absent (or disabled, outside
                         batch mode) it reads as a subdivision, which is what every
                         batch was before regular files existed. --}}
                    <input type="hidden" name="batch_kind" id="batch-kind" disabled value="subdivision">
                    {{-- Posted with the batch so storeBatch() can close the draft out once
                         the recommendations are committed. Disabled alongside the mother
                         field so the single-record post never carries it. --}}
                    <input type="hidden" name="draft_key" id="batch-draft-key" disabled value="">
                    {{-- How many children the browser actually posted. A 200-child batch is
                         over 2,000 form fields, and PHP drops everything past
                         max_input_vars without a word — the batch would save short and
                         look successful. storeBatch() compares this against what arrived. --}}
                    <input type="hidden" name="children_expected" id="batch-children-expected" disabled value="0">
                    <div class="bg-violet-50 border-b border-violet-200 px-5 py-3.5">
                        <div class="flex flex-wrap items-center gap-x-3 gap-y-2">
                            <i data-lucide="git-fork" class="h-4 w-4 text-violet-600 flex-shrink-0"></i>
                            <h3 id="batch-card-title" class="text-sm font-bold text-violet-900 uppercase tracking-tight">Children of</h3>
                            <span id="batch-mother-label" class="font-mono font-black text-slate-900 text-sm">&mdash;</span>
                            <span id="batch-children-count"
                                class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-violet-600 text-white">0 children</span>
                            <span id="batch-saved-remaining-count" class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-amber-100 text-amber-800">0 remaining</span>
                            <span id="batch-saved-rofo-count" class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-slate-200 text-slate-600">0 RoFO generated</span>

                            <div class="ml-auto flex items-center gap-2">
                                {{-- Apply-to-all lives in the toolbar rather than as a row wedged
                                     into the table body, which broke the row rhythm. --}}
                                <button type="button" id="batch-apply-all" disabled
                                    class="px-3 py-1.5 text-[11px] font-bold bg-violet-600 text-white rounded-lg hover:bg-violet-700 disabled:opacity-40 disabled:cursor-not-allowed transition inline-flex items-center gap-1.5">
                                    <i data-lucide="arrow-down-to-line" class="h-3.5 w-3.5"></i>
                                    Apply source row <span id="batch-apply-all-row" class="font-mono">#1</span> to all
                                </button>
                                {{-- Reload re-fetches from the mother file, so it belongs to the
                                     subdivision kind only — a regular batch is reloaded by
                                     pressing Apply on the picker above. --}}
                                <button type="button" id="batch-reload-children"
                                    class="px-3 py-1.5 text-[11px] font-semibold bg-white border border-violet-300 text-violet-700 rounded-lg hover:bg-violet-50 transition inline-flex items-center gap-1.5">
                                    <i data-lucide="refresh-cw" class="h-3.5 w-3.5"></i> Reload
                                </button>
                            </div>
                        </div>
                        {{-- The columns are colour-banded by what Apply-to-all does to them,
                             because the difference between "this gets overwritten" and "this
                             is yours alone" is not something the user should have to find out
                             by pressing the button. Same three colours on the headers and on
                             the cells. --}}
                        {{-- Source row and Apply-to-all are a subdivision idea: the children
                             of one mother share almost everything. A regular batch is a set
                             of unrelated files, so there is nothing to copy from and the
                             whole apparatus — this hint, the colour bands and the SRC
                             column — is hidden for it. --}}
                        <p id="batch-source-hint" class="mt-2 text-[11px] text-violet-800/80">
                            Pick the <span class="font-bold">source row</span> in the
                            <span class="font-mono font-bold">SRC</span> column, then use Apply to all.
                        </p>
                        {{-- Two colours for the two banks of columns. The columns marked
                             "if blank" under their heading are the exception within the
                             copied bank, which is said in words rather than a third tint. --}}
                        <div id="batch-copy-legend" class="mt-2 flex flex-wrap items-center gap-x-4 gap-y-1.5 text-[10px] font-semibold">
                            <span class="inline-flex items-center gap-1.5 text-amber-900">
                                <span class="w-3 h-3 rounded-sm bg-amber-100 border border-amber-300"></span>
                                Left of the line &mdash; never copied
                            </span>
                            <span class="inline-flex items-center gap-1.5 text-sky-900">
                                <span class="w-3 h-3 rounded-sm bg-sky-100 border border-sky-300"></span>
                                Right of the line &mdash; copied from the source row
                            </span>
                        </div>
                    </div>

                    {{-- data-column-picker="off": no shared "Columns" button on this grid. --}}
                    <div class="overflow-x-auto max-h-[32rem] overflow-y-auto" data-column-picker="off">
                        {{-- min-width must be at least the sum of the column widths below
                             (1376px). Any less and table-fixed squeezes the columns under
                             their declared widths on a narrow screen, which is what put
                             "Applicant Address" on top of "Land Use". --}}
                        <table class="w-full text-left min-w-[1376px] border-collapse table-fixed">
                            {{-- table-fixed uses the first row's colspans to size columns, so
                                 header utility widths alone can be ignored. Keep the actual
                                 grid explicit: file numbers remain fully visible on one line. --}}
                            <colgroup>
                                <col style="width: 36px">
                                <col style="width: 46px">
                                <col id="batch-colgroup-src" style="width: 50px">
                                <col style="width: 250px">
                                <col style="width: 120px">
                                <col style="width: 168px">
                                <col style="width: 116px">
                                <col style="width: 112px">
                                <col style="width: 146px">
                                <col style="width: 146px">
                                <col style="width: 186px">
                            </colgroup>
                            {{-- Two banks of columns, split down the middle: everything on the
                                 left belongs to that one plot and Apply-to-all never touches
                                 it; everything on the right is what Apply-to-all copies. The
                                 grouping is what makes the rule readable at a glance — the
                                 colours only reinforce it. --}}
                            <thead class="sticky top-0 z-10">
                                <tr id="batch-band-header" class="bg-slate-100 border-b border-slate-200 text-[9px] font-black uppercase tracking-widest">
                                    <th class="bg-slate-100" colspan="3"></th>
                                    <th class="px-2 py-2 bg-amber-100/80 text-amber-900 text-center whitespace-nowrap" colspan="5">
                                        <span class="inline-flex items-center gap-1.5">
                                            <i data-lucide="lock" class="h-3 w-3"></i> Never copied
                                        </span>
                                    </th>
                                    <th class="px-2 py-2 bg-sky-100/80 text-sky-900 text-center whitespace-nowrap batch-group-split" colspan="3">
                                        <span class="inline-flex items-center gap-1.5">
                                            <i data-lucide="arrow-down-to-line" class="h-3 w-3"></i> Copied from the source row
                                        </span>
                                    </th>
                                </tr>
                                {{-- The column labels do NOT use whitespace-nowrap: this is a
                                     table-fixed layout, so a label wider than its column does not
                                     widen it — it simply runs over the next one. "Applicant
                                     Address" did exactly that. Long labels wrap to a second line
                                     instead, and the tracking is normal rather than widest so
                                     they mostly do not need to. --}}
                                <tr class="bg-slate-50 border-b-2 border-slate-200 text-[10px] font-black text-slate-500 uppercase tracking-wide leading-tight">
                                    <th class="px-2 py-3 text-center w-9 bg-slate-50">
                                        <input type="checkbox" id="batch-select-all" checked
                                            class="w-4 h-4 align-middle text-violet-600 border-slate-300 rounded focus:ring-violet-500 cursor-pointer">
                                    </th>
                                    <th class="px-1 py-3 text-center w-8 bg-slate-50">#</th>
                                    <th id="batch-col-src" class="px-1 py-3 text-center w-10 bg-slate-50 text-violet-700" title="Which row Apply-to-all copies from">Src</th>

                                    {{-- Left bank: per-plot. Nothing here is copied by the
                                         toolbar Apply-to-all — but four of these columns do
                                         sometimes repeat across a whole batch (one address for
                                         a family allocation, one plot series, one land use and
                                         purpose for a whole layout), so each carries its own
                                         opt-in Apply button, sitting above its label so the
                                         labels themselves stay on one line together. It only
                                         ever fires on a deliberate click, one column at a
                                         time, and it asks before overwriting cells that
                                         already hold a different value. Child File No has
                                         none: a file number is unique by definition. --}}
                                    {{-- Every label below is a block of the same fixed height,
                                         two lines at this size. The cells are bottom-aligned,
                                         so without it a label that wraps ("Applicant Address")
                                         is taller than its neighbours and pushes its own Apply
                                         button a line higher than the rest — the buttons only
                                         sit on one level if the blocks beneath them do. --}}
                                    <th id="batch-col-file-no" class="px-2 py-3 align-bottom w-[250px] bg-amber-50/70 text-amber-900">
                                        <span class="block min-h-[26px]">Child File No</span>
                                    </th>
                                    <th class="px-2 py-3 align-bottom w-[120px] bg-amber-50/70 text-amber-900">
                                        @include('land_recommendations.partials.batch_col_apply', ['f' => 'plot_number', 'label' => 'Plot No'])
                                        <span class="block min-h-[26px]">Plot No</span>
                                    </th>
                                    <th class="px-2 py-3 align-bottom w-[168px] bg-amber-50/70 text-amber-900">
                                        @include('land_recommendations.partials.batch_col_apply', ['f' => 'applicant_address', 'label' => 'Applicant Address'])
                                        <span class="block min-h-[26px]">Applicant Address</span>
                                    </th>
                                    <th class="px-2 py-3 align-bottom w-[116px] bg-amber-50/70 text-amber-900">
                                        @include('land_recommendations.partials.batch_col_apply', ['f' => 'land_use_id', 'label' => 'Land Use'])
                                        <span class="block min-h-[26px]">Land Use</span>
                                    </th>
                                    <th class="px-2 py-3 align-bottom w-[112px] bg-amber-50/70 text-amber-900">
                                        @include('land_recommendations.partials.batch_col_apply', ['f' => 'purpose_id', 'label' => 'Purpose'])
                                        <span class="block min-h-[26px]">Purpose</span>
                                    </th>

                                    {{-- Right bank: copied. The two sky columns are copied only
                                         into rows left blank, which the tint carries. --}}
                                    <th class="px-2 py-3 align-bottom w-[146px] bg-sky-50/70 text-sky-900 batch-group-split">
                                        Applicant Name
                                        <span class="block font-medium normal-case tracking-normal text-[9px] text-sky-600">if blank</span>
                                    </th>
                                    <th class="px-2 py-3 align-bottom w-[146px] bg-sky-50/70 text-sky-900">
                                        Location
                                    </th>
                                    <th class="px-2 py-3 align-bottom w-[186px] bg-sky-50/70 text-sky-900">
                                        Page Refs
                                        <span class="block font-medium normal-case tracking-normal text-[9px] text-sky-600">page &middot; memo &middot; plan</span>
                                    </th>
                                </tr>
                            </thead>
                            <tbody id="batch-children-rows" class="text-sm"></tbody>
                        </table>
                    </div>

                    <div class="bg-slate-50 border-t border-slate-200 px-5 py-2.5">
                        <p id="batch-footer-note" class="text-[11px] text-slate-500">
                            Untick any child that should not receive a recommendation. Every ticked row is saved as
                            its own RofO recommendation, grouped under one batch.
                        </p>
                    </div>
                </div>
                @endif

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

                {{-- OP-holder Match.

                     A file whose Occupancy Permit was granted to one person while File
                     Indexing holds another, with no transfer explaining the change, is
                     a file whose chain is missing a dealing. The card shows that chain
                     and offers Match, which records the missing Transfer of Title.

                     It also appears — without a Match button — when a transfer IS on
                     file but its party is spelt differently from the file title. That
                     file needs a name correction, not a second transfer, and saying so
                     here is what stops one being created.

                     Rendered by public/js/land-recommendation-op-match.js. --}}
                <div id="op-match-card" class="hidden"></div>

                {{-- Set by that script once Match has run. The server records the
                     decision rather than re-deriving it: Match writes the very row
                     whose absence made the file qualify, so by submit time the file no
                     longer meets the condition. --}}
                <div hidden>
                    <input type="hidden" name="is_existing_recommendation" id="is_existing_recommendation"
                           value="{{ old('is_existing_recommendation', ($recommendation->is_existing_recommendation ?? false) ? 1 : 0) }}">
                    <input type="hidden" name="op_match_tot_pra_id" id="op_match_tot_pra_id"
                           value="{{ old('op_match_tot_pra_id', $recommendation->op_match_tot_pra_id ?? '') }}">
                </div>

                {{-- Everything below describes one file, and most of it is filled from the
                     file's own record, so nothing is shown until a file number is picked.
                     Batch Mode is the exception: it has no single file number — the table
                     carries one row per file — so it unlocks the form on its own. --}}
                @php
                    $formUnlocked = $isEdit || $batchEdit
                        || trim((string) old('file_number', $recommendation->file_number ?? '')) !== '';
                @endphp

                <!-- Shown until a file number is selected -->
                <div id="awaiting-file-notice" class="{{ $formUnlocked ? 'hidden' : '' }} rounded-xl border-2 border-dashed border-slate-200 bg-slate-50/60 px-6 py-12 text-center">
                    <i data-lucide="folder-search" class="h-8 w-8 text-slate-300 mx-auto"></i>
                    {{-- Both lines are rewritten by the gate script under Batch Mode, where
                         the question is which files are in the batch rather than which file
                         this letter is for. --}}
                    <p id="awaiting-file-title" class="mt-3 text-sm font-bold text-slate-700">Select a file number to begin</p>
                    <p id="awaiting-file-hint" class="mt-1 text-xs text-slate-500">
                        The rest of the form is filled from the selected file, so it stays hidden until one is chosen.
                    </p>
                </div>

                <!-- Form Grid -->
                <div id="form-body" class="{{ $formUnlocked ? '' : 'hidden' }} grid grid-cols-1 md:grid-cols-2 gap-6">
                    @php
                        $savedAppType = old('application_type', $recommendation->application_type ?? '');
                        $hasAppType   = $savedAppType !== '';
                        $savedRecType = old('type', $recommendation->type ?? 'Direct');
                        // "Standard template" override: the application type is still captured
                        // (extra fields, old file number) but printing uses the Direct /
                        // Conversion template instead of the application-type template.
                        $useStandardTemplate = (bool) old('use_standard_template', $recommendation->use_standard_template ?? false);
                        // An OSS record's type is its origin, not a choice: neither radio
                        // here can express it, so the block is locked rather than letting a
                        // save quietly turn the record into a Direct one.
                        $isOssRec     = strtoupper((string) ($recommendation->type ?? '')) === 'OSS';
                        $lockRecType  = ($hasAppType && !$useStandardTemplate) || $isOssRec;
                    @endphp

                    <!-- Recommendation Type Selection -->
                    <div id="recommendation-type-block" class="bg-blue-50/30 border border-blue-100 rounded-xl p-6 col-span-2 transition-opacity {{ $lockRecType ? 'opacity-40 pointer-events-none' : '' }}">
                        <label class="block text-xs font-bold text-blue-700 uppercase tracking-wider mb-3">
                            Recommendation Type
                            @if($isOssRec)
                                <span class="ml-2 inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-purple-100 text-purple-800 normal-case tracking-normal">OSS — origin cannot be changed</span>
                            @endif
                        </label>
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
                        {{-- Why the radios are locked, in a regular batch. Its own line
                             rather than the batch status box, which the file loader
                             overwrites with its own notes a moment later. --}}
                        <div id="batch-rec-type-note" class="hidden mt-3 rounded-lg px-3 py-2 text-[11px] font-semibold"></div>
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
                            {{-- Override: keep the application type (extra fields / old file
                                 number) but print the standard Direct / Conversion document. --}}
                            <label class="mb-4 flex items-start gap-3 cursor-pointer p-3 bg-white border border-slate-200 rounded-xl hover:border-blue-400 transition">
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

                            <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                                @foreach([
                                    'Private Layout',
                                    'Plot Subdivision',
                                    'Plot Merger',
                                    'Plot Extension',
                                    'Temporary File No',
                                    'Ministry of Works',
                                    'Change of Purpose',
                                    'Regrant',
                                ] as $appType)
                                <label class="flex items-center gap-3 cursor-pointer p-3 bg-white border border-slate-200 rounded-xl hover:border-blue-400 transition shadow-sm group">
                                    <input type="radio" name="application_type_radio" value="{{ $appType }}"
                                        {{ $savedAppType === $appType ? 'checked' : '' }}
                                        class="app-type-radio w-4 h-4 text-blue-600 focus:ring-blue-500 border-slate-300">
                                    <span class="text-sm font-medium text-slate-700 group-hover:text-blue-700 transition leading-tight">{{ $appType }}</span>
                                </label>
                                @endforeach
                            </div>
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

                    {{-- ── Use Subdivision Template ──────────────────────────────────
                         A child of a subdivision that could not be captured through Batch
                         Mode, because its mother/boundary file has never been indexed or
                         linked and so appears in no picker. Answering Yes makes THIS ONE
                         file print as a subdivision child; it joins no batch, and no
                         mother record is created or required.

                         `data-batch-child` is what stands the whole card down inside a
                         batch (the .batch-mode CSS rule hides it and
                         standDownPerChildFields() disables its inputs, so nothing here can
                         post from a batch save). A real batch already has a mother — this
                         option exists for the case where there is no batch at all. --}}
                    @php
                        $useSubdivisionTemplate = (bool) old('use_subdivision_template', $recommendation->use_subdivision_template ?? false);
                    @endphp
                    <div id="subdivision-template-card" data-batch-child class="col-span-2 bg-violet-50/40 border border-violet-200 rounded-xl p-6">
                        <div class="flex items-center gap-2 mb-1">
                            <i data-lucide="git-branch" class="h-4 w-4 text-violet-600"></i>
                            <h3 class="text-sm font-extrabold text-violet-900 uppercase tracking-tight">Use Subdivision Template</h3>
                        </div>
                        <p class="text-xs text-slate-500 mb-4">
                            For a single file that belongs to a subdivision but was not captured through a
                            subdivision batch &mdash; because the mother file is not indexed or linked.
                            The R of O prints as a subdivision child. No batch is created, and the mother
                            file does not have to exist in the register.
                        </p>

                        {{-- Yes first: it is the answer this card exists to collect, and the
                             one that changes anything. No is the default the record already
                             has, so it reads as the way back rather than the opening offer. --}}
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <label class="flex items-start gap-3 cursor-pointer p-3.5 bg-white border-2 border-slate-200 rounded-xl hover:border-violet-400 transition shadow-sm has-[:checked]:border-violet-500 has-[:checked]:bg-violet-50/60">
                                <input type="radio" name="use_subdivision_template" value="1"
                                    {{ $useSubdivisionTemplate ? 'checked' : '' }}
                                    class="subdivision-template-radio mt-0.5 w-4 h-4 text-violet-600 focus:ring-violet-500 border-slate-300">
                                <span class="leading-snug">
                                    <span class="block text-sm font-bold text-slate-900">Yes</span>
                                    <span class="block text-[11px] text-slate-500">Treat this file as though it came through a subdivision batch.</span>
                                </span>
                            </label>
                            <label class="flex items-start gap-3 cursor-pointer p-3.5 bg-white border-2 border-slate-200 rounded-xl hover:border-violet-400 transition shadow-sm has-[:checked]:border-violet-500 has-[:checked]:bg-violet-50/60">
                                <input type="radio" name="use_subdivision_template" value="0"
                                    {{ $useSubdivisionTemplate ? '' : 'checked' }}
                                    class="subdivision-template-radio mt-0.5 w-4 h-4 text-violet-600 focus:ring-violet-500 border-slate-300">
                                <span class="leading-snug">
                                    <span class="block text-sm font-bold text-slate-900">No</span>
                                    <span class="block text-[11px] text-slate-500">Ordinary record. The letter prints as it always has.</span>
                                </span>
                            </label>
                        </div>

                        {{-- No field of its own. Answering Yes relabels the Old File Number
                             card below to Mother File Number — see the old-fileno script.
                             A second box asking for the same file number is the thing this
                             option must not become. --}}
                        <p id="subdivision-template-note" class="{{ $useSubdivisionTemplate ? '' : 'hidden' }} mt-4 rounded-lg bg-violet-100/70 px-3 py-2 text-[11px] font-semibold text-violet-900">
                            Set the mother file in <span class="font-black uppercase tracking-wider">Mother File Number</span> below.
                            It does not have to be in the register &mdash; the file number selector's
                            <span class="font-black uppercase tracking-wider">Manual</span> method builds one from its parts.
                            It prints on the <span class="font-black uppercase tracking-wider">as per plan No.</span> line of the R of O.
                        </p>
                    </div>

                    {{-- Old File Number — its own section for application types that derive from an existing file --}}
                    <div id="atx-old-fileno-row" class="hidden col-span-2 bg-amber-50/40 border border-amber-200 rounded-xl p-6">
                        <div class="flex items-center gap-2 mb-3">
                            <i data-lucide="folder-input" class="h-4 w-4 text-amber-600"></i>
                            {{-- Under Use Subdivision Template this same field IS the mother
                                 file number, so the heading says so rather than making the
                                 officer know that "old file number" means "mother" here. --}}
                            <h3 class="text-sm font-bold text-amber-900 uppercase tracking-tight"><span id="atx-old-fileno-title">Old File Number</span> <span class="text-red-500">*</span></h3>
                        </div>
                        <div class="flex gap-2 items-center" id="atx-old-fileno-manual">
                            {{-- readonly by default: an old file number is picked from the
                                 register. The subdivision-template script lifts it, because a
                                 mother that is not indexed cannot be picked from anywhere. --}}
                            <input type="text" name="old_file_number" id="old_file_number" readonly
                                value="{{ old('old_file_number', $recommendation->old_file_number ?? '') }}"
                                placeholder="No old file number selected"
                                autocomplete="off"
                                class="w-64 border border-amber-200 rounded-lg px-3 py-2.5 bg-white font-mono text-sm outline-none shadow-sm read-only:cursor-default focus:border-amber-500 focus:ring-2 focus:ring-amber-500/20 transition">
                            <button type="button" id="atx-old-fileno-pick"
                                class="px-4 py-2.5 text-xs font-semibold bg-amber-600 text-white rounded-lg hover:bg-amber-700 transition whitespace-nowrap">
                                Select File Number
                            </button>
                        </div>

                        {{-- Batch mode: only a handful of files have commissioned subdivision
                             children, so the whole-register file picker is the wrong tool —
                             the mother is chosen from that short list instead. The readonly
                             input above stays in the DOM and is still what posts. --}}
                        <div id="batch-mother-picker" class="hidden flex flex-wrap gap-2 items-center">
                            <select id="batch-mother-select"
                                class="w-full md:w-96 border border-amber-300 rounded-lg px-3 py-2.5 bg-white text-sm font-mono outline-none shadow-sm focus:border-amber-500 focus:ring-2 focus:ring-amber-500/20 transition cursor-pointer">
                                <option value="">Loading subdivided files…</option>
                            </select>
                            {{-- Loading the children off the select's own change event alone left
                                 no way to say "go" — and no way back when the load was refused
                                 for a reason the user has since fixed. Apply is that trigger,
                                 and it is the same gesture the Regular files kind already uses. --}}
                            <button type="button" id="batch-mother-apply"
                                class="px-4 py-2.5 text-xs font-bold bg-amber-600 text-white rounded-lg hover:bg-amber-700 transition whitespace-nowrap inline-flex items-center gap-1.5">
                                <i data-lucide="check" class="h-3.5 w-3.5"></i>
                                Apply
                            </button>
                        </div>

                        <p class="mt-2 text-[11px] text-slate-500">
                            <span id="atx-old-fileno-help">The parent / previous file this <span id="atx-old-fileno-context">application</span> derives from.</span>
                            <span id="batch-mother-help" class="hidden">
                                Only files that already have commissioned subdivision children are listed.
                            </span>
                            {{-- Shown in place of the help above while Use Subdivision Template
                                 is on: there, the field is typed into rather than picked. --}}
                            <span id="subdivision-mother-help" class="hidden">
                                The mother file this plot was subdivided from. Use <span class="font-semibold">Select File Number</span> &mdash;
                                if it is not in the register, build it there with the selector's <span class="font-semibold">Manual</span> method.
                                It prints on the "as per plan No." line of the R of O.
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
                                    <label class="block text-xs font-semibold text-slate-500 uppercase mb-1.5">KAMMA Page</label>
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
                        <div class="flex flex-wrap items-center gap-2 mb-2">
                            <i data-lucide="user" class="h-4 w-4 text-blue-600"></i>
                            <h3 class="text-sm font-bold text-slate-900 uppercase tracking-tight">Applicant & Property</h3>

                            {{-- Regular batch only, and driven by the same index as the
                                 Grant Conditions stepper — both cards always show the
                                 same file, so there is one position in the batch rather
                                 than two that can disagree. --}}
                            <div class="per-file-step-nav hidden ml-auto flex items-center gap-2">
                                <span class="per-file-step-file font-mono text-[11px] font-bold text-slate-700 truncate max-w-[200px]"></span>
                                <span class="per-file-step-untick hidden px-1.5 py-0.5 rounded text-[9px] font-bold bg-slate-200 text-slate-600 uppercase tracking-wide"
                                      title="This file is unticked in the table, so these values will not be saved">Not in batch</span>
                                <button type="button" id="applicant-card-apply-all"
                                        data-card-fields="layout_plan_no"
                                        class="px-2.5 py-1.5 text-[11px] font-bold bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:opacity-40 disabled:cursor-not-allowed transition inline-flex items-center gap-1.5"
                                        title="Copy this card's per-file values onto every file in the batch">
                                    <i data-lucide="copy" class="h-3.5 w-3.5"></i>
                                    Apply to all
                                </button>
                                <div class="flex items-center gap-1 rounded-lg border border-blue-200 bg-white p-0.5">
                                    <button type="button" class="per-file-step-prev p-1.5 rounded-md text-blue-700 hover:bg-blue-50 disabled:opacity-30 disabled:cursor-not-allowed transition" title="Previous file">
                                        <i data-lucide="chevron-left" class="h-4 w-4"></i>
                                    </button>
                                    <span class="per-file-step-label px-2 text-[11px] font-bold text-slate-700 tabular-nums whitespace-nowrap">1 of 1</span>
                                    <button type="button" class="per-file-step-next p-1.5 rounded-md text-blue-700 hover:bg-blue-50 disabled:opacity-30 disabled:cursor-not-allowed transition" title="Next file">
                                        <i data-lucide="chevron-right" class="h-4 w-4"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                        <div class="space-y-4">
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <div class="md:col-span-2" data-batch-child>
                                    <label class="block text-xs font-semibold text-slate-500 uppercase mb-1.5">Name of Applicant</label>
                                    {{-- The name belongs to the file, not to this letter: it is filled from the
                                         record behind the selected file number and is not typed here. `readonly`
                                         rather than `disabled` — a disabled field posts nothing, and the name is
                                         required on save. --}}
                                    <input type="text" name="applicant_name" id="applicant_name" required readonly tabindex="-1"
                                        value="{{ old('applicant_name', $recommendation->applicant_name ?? '') }}"
                                        placeholder="Filled from the selected file number"
                                        class="w-full border @error('applicant_name') border-red-500 @else border-slate-200 @enderror rounded-lg px-4 py-2.5 bg-slate-100 text-slate-500 outline-none transition shadow-sm cursor-not-allowed">
                                    @error('applicant_name')
                                        <p class="text-red-500 text-[10px] mt-1 font-semibold uppercase">{{ $message }}</p>
                                    @enderror
                                    <p class="mt-1 text-[10px] text-slate-400">Taken from the selected file number.</p>
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
                                <label class="block text-xs font-semibold text-slate-500 uppercase mb-1.5">Applicant Address <span class="text-red-500">*</span></label>
                                <input type="text" name="applicant_address" id="applicant_address" required maxlength="500" value="{{ old('applicant_address', $recommendation->applicant_address ?? '') }}"
                                    placeholder="Enter the applicant's full address"
                                    class="w-full border @error('applicant_address') border-red-500 @else border-slate-200 @enderror rounded-lg px-4 py-2.5 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none transition bg-white shadow-sm">
                                @error('applicant_address')
                                    <p class="text-red-500 text-[10px] mt-1 font-semibold uppercase">{{ $message }}</p>
                                @enderror
                            </div>
                            <div data-batch-child>
                                <label class="block text-xs font-semibold text-slate-500 uppercase mb-1.5">Phone Number <span class="text-red-500">*</span></label>
                                <input type="tel" name="applicant_phone" id="applicant_phone" inputmode="numeric" required minlength="11" maxlength="11" pattern="0[0-9]{10}"
                                    value="{{ old('applicant_phone', $recommendation->applicant_phone ?? '') }}" placeholder="e.g. 08012345678"
                                    title="Enter an 11-digit Nigerian phone number beginning with 0, e.g. 08012345678."
                                    class="w-full border @error('applicant_phone') border-red-500 @else border-slate-200 @enderror rounded-lg px-4 py-2.5 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none transition bg-white shadow-sm">
                                @error('applicant_phone')
                                    <p class="text-red-500 text-[10px] mt-1 font-semibold uppercase">{{ $message }}</p>
                                @enderror
                                <p class="mt-1 text-[10px] text-slate-400">Required: 11 digits beginning with 0.</p>
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
                    <div id="grant-conditions-card" class="bg-slate-50 border border-slate-100 rounded-xl p-6 space-y-4">
                        <div class="flex flex-wrap items-center gap-2 mb-2">
                            <i data-lucide="banknote" class="h-4 w-4 text-blue-600"></i>
                            <h3 class="text-sm font-bold text-slate-900 uppercase tracking-tight">Grant Conditions</h3>

                            {{-- Regular batch only. A subdivision's children share one set of
                                 grant conditions, so the card stays a single capture there.
                                 Unrelated files do not: each one is its own grant, with its
                                 own term, fees and premium. Rather than widen the table by
                                 ten more columns, the card itself becomes the per-file step —
                                 the values are held in JS and swapped in and out as the
                                 officer moves through the batch. --}}
                            <div class="per-file-step-nav hidden ml-auto flex items-center gap-2">
                                <span class="per-file-step-file font-mono text-[11px] font-bold text-slate-700 truncate max-w-[200px]"></span>
                                <span class="per-file-step-untick hidden px-1.5 py-0.5 rounded text-[9px] font-bold bg-slate-200 text-slate-600 uppercase tracking-wide"
                                      title="This file is unticked in the table, so these values will not be saved">Not in batch</span>
                                <button type="button" id="grant-card-apply-all"
                                        data-card-fields="cofo_year,selected_year,term,development_value,development_period,ground_rent,ground_rent_unit,ground_rent_unit_other,area_sqm,development_charge,survey_fees,preparation_fees,preparation_fees_words"
                                        class="px-2.5 py-1.5 text-[11px] font-bold bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:opacity-40 disabled:cursor-not-allowed transition inline-flex items-center gap-1.5"
                                        title="Copy every value on this card onto every file in the batch">
                                    <i data-lucide="copy" class="h-3.5 w-3.5"></i>
                                    Apply to all
                                </button>
                                <div class="flex items-center gap-1 rounded-lg border border-blue-200 bg-white p-0.5">
                                    <button type="button" class="per-file-step-prev p-1.5 rounded-md text-blue-700 hover:bg-blue-50 disabled:opacity-30 disabled:cursor-not-allowed transition" title="Previous file">
                                        <i data-lucide="chevron-left" class="h-4 w-4"></i>
                                    </button>
                                    <span class="per-file-step-label px-2 text-[11px] font-bold text-slate-700 tabular-nums whitespace-nowrap">1 of 1</span>
                                    <button type="button" class="per-file-step-next p-1.5 rounded-md text-blue-700 hover:bg-blue-50 disabled:opacity-30 disabled:cursor-not-allowed transition" title="Next file">
                                        <i data-lucide="chevron-right" class="h-4 w-4"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                        {{-- Per-file grant conditions are posted from here as
                             children[i][term] and friends. Rebuilt on submit from the JS
                             store, so the fields on screen never have to be the whole
                             record. --}}
                        <div id="grant-per-child-inputs" class="hidden"></div>
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
                                @php
                                    // A number of years, entered as a number. Most records on file
                                    // were keyed through the old free-text box and hold "2 years"
                                    // rather than "2" — a number input renders those as EMPTY and
                                    // would quietly wipe them on the next save, so the leading
                                    // number is pulled out for editing. The unit is put back at
                                    // print time (see LandRecommendation::development_period_label).
                                    $devPeriodRaw = old('development_period', $recommendation->development_period ?? '2');
                                    $devPeriod = preg_match('/\d+/', (string) $devPeriodRaw, $m) ? $m[0] : '';
                                @endphp
                                <div class="relative">
                                    <input type="number" min="0" step="1" inputmode="numeric" name="development_period"
                                        value="{{ $devPeriod }}"
                                        class="w-full border border-slate-200 rounded-lg pl-4 pr-16 py-2.5 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none transition bg-white shadow-sm">
                                    <span class="absolute inset-y-0 right-3 flex items-center text-xs font-semibold text-slate-400 pointer-events-none">years</span>
                                </div>
                                @if($devPeriodRaw !== '' && $devPeriod === '')
                                    {{-- A legacy non-numeric value such as "NIL". Shown rather than
                                         silently dropped, so whoever edits it decides. --}}
                                    <p class="mt-1 text-[11px] text-amber-700">
                                        Previously recorded as &ldquo;{{ $devPeriodRaw }}&rdquo; &mdash; enter a number of years to replace it.
                                    </p>
                                @endif
                            </div>
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-xs font-semibold text-slate-500 uppercase mb-1.5">Ground Rent (₦):</label>
                                    @php
                                        // The unit is stored as the words that print, so an existing record
                                        // whose unit is not one of the fixed choices was an "Other" - the
                                        // select shows Other and the free-text box carries it back.
                                        //
                                        // A NEW recommendation opens on Per Square Meters, which is what every
                                        // record keyed before this field existed was charged by and what the
                                        // backfill stamped them with. Without it a new letter would print a
                                        // bare figure beside 2,791 older ones that all name their unit.
                                        $grIsNew = !isset($recommendation) || !$recommendation->exists;
                                        $grUnit = old(
                                            'ground_rent_unit',
                                            $recommendation->ground_rent_unit
                                                ?? ($grIsNew ? \App\Models\LandRecommendation::GROUND_RENT_UNITS[0] : '')
                                        );
                                        $grUnitIsOther = filled($grUnit)
                                            && !in_array($grUnit, \App\Models\LandRecommendation::GROUND_RENT_UNITS, true);
                                    @endphp
                                    <div class="flex gap-2">
                                        <input type="number" step="0.01" name="ground_rent" value="{{ old('ground_rent', $recommendation->ground_rent ?? '') }}"
                                            class="flex-1 min-w-0 border border-slate-200 rounded-lg px-4 py-2.5 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none transition bg-white shadow-sm">
                                        {{-- Charged by: printed straight after the figure on the
                                             Recommendation and the RofO. --}}
                                        <select name="ground_rent_unit" id="ground_rent_unit"
                                            class="w-40 shrink-0 border border-slate-200 rounded-lg px-2 py-2.5 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none transition bg-white shadow-sm">
                                            <option value="">— Unit —</option>
                                            @foreach(\App\Models\LandRecommendation::GROUND_RENT_UNITS as $unitOption)
                                                <option value="{{ $unitOption }}" @selected($grUnit === $unitOption)>{{ $unitOption }}</option>
                                            @endforeach
                                            <option value="Other" @selected($grUnitIsOther)>Other</option>
                                        </select>
                                    </div>
                                    <input type="text" name="ground_rent_unit_other" id="ground_rent_unit_other"
                                        value="{{ old('ground_rent_unit_other', $grUnitIsOther ? $grUnit : '') }}"
                                        placeholder="State the unit, e.g. Per Acre"
                                        class="mt-2 w-full border border-slate-200 rounded-lg px-4 py-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none transition bg-white shadow-sm {{ $grUnitIsOther ? '' : 'hidden' }}">
                                </div>
                                {{-- The rate above is per unit; what is owed is rate x plot size.
                                     Prefilled and locked when the file's indexing has a size, and
                                     re-read from the indexing on save. Stepped per file in a batch. --}}
                                <div>
                                    <label class="block text-xs font-semibold text-slate-500 uppercase mb-1.5">Plot Size (m²): <span class="text-red-500">*</span></label>
                                    <input type="number" step="0.01" min="0.01" name="area_sqm" id="area_sqm" required
                                        value="{{ old('area_sqm', $recommendation->area_sqm ?? '') }}" placeholder="e.g. 450"
                                        class="w-full border border-slate-200 rounded-lg px-4 py-2.5 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none transition bg-white shadow-sm">
                                    <p id="area_sqm_source" class="mt-1 text-[11px] text-slate-500 hidden">From file indexing</p>
                                    <p id="ground_rent_amount" class="mt-1 text-xs font-semibold text-blue-700"></p>
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
                        <div class="flex flex-wrap items-center gap-2 mb-2">
                            <i data-lucide="refresh-cw" class="h-4 w-4 text-amber-600"></i>
                            <h3 class="text-sm font-bold text-amber-900 uppercase tracking-tight">Page Number details</h3>

                            {{-- Stepped per file, on the same shared index as Grant Conditions
                                 and Applicant & Property, so all three cards always show the
                                 same file. The survey report and Physical Planning's comment
                                 are read off each file separately, which is what makes them
                                 per-file rather than batch-wide.

                                 Apply-to-all is here and not on the other two because these
                                 are the values a batch most often shares verbatim: one survey
                                 report reference, one Physical Planning comment, read once and
                                 carried across the set. --}}
                            <div class="per-file-step-nav hidden ml-auto flex items-center gap-2">
                                <span class="per-file-step-file font-mono text-[11px] font-bold text-slate-700 truncate max-w-[200px]"></span>
                                <span class="per-file-step-untick hidden px-1.5 py-0.5 rounded text-[9px] font-bold bg-slate-200 text-slate-600 uppercase tracking-wide"
                                      title="This file is unticked in the table, so these values will not be saved">Not in batch</span>
                                {{-- data-card-fields is the card's own share of PER_FILE_FIELDS.
                                     Anything added to this card and not listed here is a value
                                     Apply-to-all silently leaves behind on the other files. --}}
                                <button type="button" id="page-card-apply-all"
                                        data-card-fields="page_survey_report,survey_report,physical_planning_comment,improvement,revision_period,time_of_erection"
                                        class="px-2.5 py-1.5 text-[11px] font-bold bg-amber-600 text-white rounded-lg hover:bg-amber-700 disabled:opacity-40 disabled:cursor-not-allowed transition inline-flex items-center gap-1.5"
                                        title="Copy every value on this card onto every file in the batch">
                                    <i data-lucide="copy" class="h-3.5 w-3.5"></i>
                                    Apply to all
                                </button>
                                <div class="flex items-center gap-1 rounded-lg border border-amber-200 bg-white p-0.5">
                                    <button type="button" class="per-file-step-prev p-1.5 rounded-md text-amber-700 hover:bg-amber-50 disabled:opacity-30 disabled:cursor-not-allowed transition" title="Previous file">
                                        <i data-lucide="chevron-left" class="h-4 w-4"></i>
                                    </button>
                                    <span class="per-file-step-label px-2 text-[11px] font-bold text-slate-700 tabular-nums whitespace-nowrap">1 of 1</span>
                                    <button type="button" class="per-file-step-next p-1.5 rounded-md text-amber-700 hover:bg-amber-50 disabled:opacity-30 disabled:cursor-not-allowed transition" title="Next file">
                                        <i data-lucide="chevron-right" class="h-4 w-4"></i>
                                    </button>
                                </div>
                            </div>
                        </div>

                        {{-- The Application and Physical Planning page numbers belong to the
                             batch TABLE — its Pg / Memo columns are where they are stored and
                             posted from. Where there is a stepper there is also one current
                             file, so the card shows those two boxes for that file as a mirror
                             of its row: type in either, both change. Where there is no stepper
                             (a subdivision capture, whose children are keyed straight down the
                             table) there is no current file for them to mean, so they stay
                             stood down and the note below says where they went. --}}
                        <p data-batch-only data-batch-no-mirror class="text-[11px] font-semibold text-amber-900 bg-white/70 border border-amber-200 rounded-lg px-3 py-2">
                            Application Page No and Physical Planning Page No are keyed per file in the
                            table above &mdash; the <span class="font-mono">Pg</span> and
                            <span class="font-mono">Memo</span> boxes.
                            What is below is captured for the file named on the stepper.
                        </p>
                        <p data-batch-only data-batch-mirror-note class="hidden text-[11px] font-semibold text-amber-900 bg-white/70 border border-amber-200 rounded-lg px-3 py-2">
                            Everything on this card belongs to the file named on the stepper.
                            Application Page No and Physical Planning Page No are the same two boxes as
                            that file's <span class="font-mono">Pg</span> and <span class="font-mono">Memo</span>
                            in the table above &mdash; change either one.
                        </p>

                        {{-- Laid out the way the page numbers are read off the file: the
                             application page on its own, then each page number paired on
                             one line with the detail it belongs to (survey report, then
                             Physical Planning's comment). The page-number inputs are
                             narrow because they only ever hold a page number. --}}
                        <div class="space-y-4 mb-6">
                            {{-- data-batch-mirror: hidden in batch mode like any other
                                 [data-batch-child], EXCEPT while a stepper is on, where it
                                 comes back bound to the current file's table row (see
                                 writeMirrors / the mirror listeners). data-f names the table
                                 column it mirrors. --}}
                            <div class="grid grid-cols-12 gap-4" data-batch-child data-batch-mirror>
                                <div class="col-span-6 sm:col-span-4 md:col-span-3">
                                    <label class="block text-xs font-semibold text-slate-500 uppercase mb-1.5 font-mono whitespace-nowrap">Application Page No</label>
                                    <input type="text" name="page" data-mirror-f="page" value="{{ old('page', $recommendation->page ?? '') }}"
                                        title="Application Page No" placeholder="App pg"
                                        class="w-full max-w-[96px] border border-slate-200 rounded-lg px-3 py-2.5 bg-white shadow-sm outline-none focus:ring-1 focus:ring-amber-500 transition">
                                </div>
                            </div>

                            <div class="flex flex-wrap items-end gap-8">
                                <div class="shrink-0">
                                    <label class="block text-xs font-semibold text-slate-500 uppercase mb-1.5 font-mono whitespace-nowrap">Survey Report Page No</label>
                                    <input type="text" name="page_survey_report" value="{{ old('page_survey_report', $recommendation->page_survey_report ?? '') }}"
                                        title="Survey Report Page No" placeholder="Rpt pg"
                                        class="w-full max-w-[96px] border border-slate-200 rounded-lg px-3 py-2.5 bg-white shadow-sm outline-none focus:ring-1 focus:ring-amber-500 transition">
                                </div>
                                <div class="pp-detail-col flex-1 min-w-[240px]">
                                    <label class="block text-xs font-semibold text-slate-500 uppercase mb-1.5 font-mono">Survey Report Detail</label>
                                    <input type="text" name="survey_report" value="{{ old('survey_report', $recommendation->survey_report ?? '') }}"
                                        class="w-full border border-slate-200 rounded-lg px-4 py-2.5 bg-white shadow-sm outline-none focus:ring-1 focus:ring-amber-500 transition"
                                        placeholder="Surveyor's report reference / findings">
                                </div>
                            </div>

                            <div class="flex flex-wrap items-end gap-8">
                                <div class="shrink-0" data-batch-child data-batch-mirror>
                                    <label class="block text-xs font-semibold text-slate-500 uppercase mb-1.5 font-mono whitespace-nowrap">Physical Planning Page No</label>
                                    <input type="text" name="page_2" data-mirror-f="page_2" value="{{ old('page_2', $recommendation->page_2 ?? '') }}"
                                        title="Physical Planning Page No" placeholder="PP pg"
                                        class="w-full max-w-[96px] border border-slate-200 rounded-lg px-3 py-2.5 bg-white shadow-sm outline-none focus:ring-1 focus:ring-amber-500 transition">
                                </div>
                                <div class="pp-detail-col flex-1 min-w-[240px]">
                                    <label class="block text-xs font-semibold text-slate-500 uppercase mb-1.5 font-mono">Physical Planning Comment</label>
                                    <input type="text" name="physical_planning_comment" value="{{ old('physical_planning_comment', $recommendation->physical_planning_comment ?? '') }}"
                                        class="w-full border border-slate-200 rounded-lg px-4 py-2.5 bg-white shadow-sm outline-none focus:ring-1 focus:ring-amber-500 transition"
                                        placeholder="Physical Planning's comment / recommendation">
                                </div>
                            </div>
                        </div>

                        <!-- Row 3: Other Metadata -->
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                            <div>
                                <label class="block text-xs font-semibold text-slate-500 uppercase mb-1.5 font-mono">Improvement</label>
                                <input type="text" name="improvement" value="{{ old('improvement', $recommendation->improvement ?? '') }}"
                                    placeholder="Improvement on the land"
                                    class="w-full border border-slate-200 rounded-lg px-4 py-2.5 bg-white shadow-sm outline-none focus:ring-1 focus:ring-amber-500 transition">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-500 uppercase mb-1.5 font-mono">Revision Period</label>
                                <input type="text" name="revision_period" value="{{ old('revision_period', $recommendation->revision_period ?? '') }}"
                                    placeholder="Rent revision period"
                                    class="w-full border border-slate-200 rounded-lg px-4 py-2.5 bg-white shadow-sm outline-none focus:ring-1 focus:ring-amber-500 transition">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-500 uppercase mb-1.5 font-mono">Time of Erection</label>
                                <input type="text" name="time_of_erection" value="{{ old('time_of_erection', $recommendation->time_of_erection ?? '') }}"
                                    placeholder="When the building was erected"
                                    class="w-full border border-slate-200 rounded-lg px-4 py-2.5 bg-white shadow-sm outline-none focus:ring-1 focus:ring-amber-500 transition">
                            </div>
                        </div>
                    </div>

                    <!-- Section: RofO Generation Data -->
                    <div class="bg-green-50/40 border border-green-200 rounded-xl p-6 space-y-4 col-span-2">
                        <div class="flex items-center gap-2 mb-2">
                            <i data-lucide="zap" class="h-4 w-4 text-green-600"></i>
                            <h3 class="text-sm font-bold text-green-900 uppercase tracking-tight">RofO Generation Data</h3>

                            {{-- Per-file stepper, the same one the cards above carry:
                                 one position in the batch, shared by every card. --}}
                            <div class="per-file-step-nav hidden ml-auto flex items-center gap-2">
                                <span class="per-file-step-file font-mono text-[11px] font-bold text-slate-700 truncate max-w-[200px]"></span>
                                <span class="per-file-step-untick hidden px-1.5 py-0.5 rounded text-[9px] font-bold bg-slate-200 text-slate-600 uppercase tracking-wide"
                                      title="This file is unticked in the table, so these values will not be saved">Not in batch</span>
                                <button type="button" id="rofo-card-apply-all"
                                        data-card-fields="rofo_survey_method"
                                        class="px-2.5 py-1.5 text-[11px] font-bold bg-green-600 text-white rounded-lg hover:bg-green-700 disabled:opacity-40 disabled:cursor-not-allowed transition inline-flex items-center gap-1.5"
                                        title="Copy this card onto every file in the batch">
                                    <i data-lucide="copy" class="h-3.5 w-3.5"></i>
                                    Apply to all
                                </button>
                                <div class="flex items-center gap-1 rounded-lg border border-green-200 bg-white p-0.5">
                                    <button type="button" class="per-file-step-prev p-1.5 rounded-md text-green-700 hover:bg-green-50 disabled:opacity-30 disabled:cursor-not-allowed transition" title="Previous file">
                                        <i data-lucide="chevron-left" class="h-4 w-4"></i>
                                    </button>
                                    <span class="per-file-step-label px-2 text-[11px] font-bold text-slate-700 tabular-nums whitespace-nowrap">1 of 1</span>
                                    <button type="button" class="per-file-step-next p-1.5 rounded-md text-green-700 hover:bg-green-50 disabled:opacity-30 disabled:cursor-not-allowed transition" title="Next file">
                                        <i data-lucide="chevron-right" class="h-4 w-4"></i>
                                    </button>
                                </div>
                            </div>
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

                            {{-- Per-file stepper, the same one the cards above carry:
                                 one position in the batch, shared by every card. --}}
                            <div class="per-file-step-nav hidden ml-auto flex items-center gap-2">
                                <span class="per-file-step-file font-mono text-[11px] font-bold text-slate-700 truncate max-w-[200px]"></span>
                                <span class="per-file-step-untick hidden px-1.5 py-0.5 rounded text-[9px] font-bold bg-slate-200 text-slate-600 uppercase tracking-wide"
                                      title="This file is unticked in the table, so these values will not be saved">Not in batch</span>
                                <button type="button" id="reasons-card-apply-all"
                                        data-card-fields="recommendation"
                                        class="px-2.5 py-1.5 text-[11px] font-bold bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:opacity-40 disabled:cursor-not-allowed transition inline-flex items-center gap-1.5"
                                        title="Copy this card onto every file in the batch">
                                    <i data-lucide="copy" class="h-3.5 w-3.5"></i>
                                    Apply to all
                                </button>
                                <div class="flex items-center gap-1 rounded-lg border border-blue-200 bg-white p-0.5">
                                    <button type="button" class="per-file-step-prev p-1.5 rounded-md text-blue-700 hover:bg-blue-50 disabled:opacity-30 disabled:cursor-not-allowed transition" title="Previous file">
                                        <i data-lucide="chevron-left" class="h-4 w-4"></i>
                                    </button>
                                    <span class="per-file-step-label px-2 text-[11px] font-bold text-slate-700 tabular-nums whitespace-nowrap">1 of 1</span>
                                    <button type="button" class="per-file-step-next p-1.5 rounded-md text-blue-700 hover:bg-blue-50 disabled:opacity-30 disabled:cursor-not-allowed transition" title="Next file">
                                        <i data-lucide="chevron-right" class="h-4 w-4"></i>
                                    </button>
                                </div>
                            </div>
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
                <div id="form-action-footer" class="{{ $formUnlocked ? '' : 'hidden' }} pt-8 border-t border-slate-100 flex justify-end gap-3">
                    @if($isEdit && !$batchEdit)
                        <button type="button" id="recommendation-save-draft"
                            class="px-6 py-3 border border-violet-300 bg-white text-violet-700 font-bold rounded-lg hover:bg-violet-50 transition shadow-sm disabled:opacity-60">
                            Save Draft
                        </button>
                    @endif
                    <button type="submit" id="{{ $batchEdit ? 'batch-save-button' : 'recommendation-save-button' }}" class="px-8 py-3 bg-slate-900 text-white font-bold rounded-lg hover:bg-slate-800 transition shadow-lg disabled:cursor-wait disabled:opacity-70 disabled:hover:bg-slate-900">
                        <span class="save-button-label">{{ $batchEdit ? 'Save Batch Changes' : ($isEdit ? 'Update' : ($reissuanceSource ? 'Save Re-issuance' : 'Generate Recommendation')) }}</span>
                        @if($batchEdit)
                            <span class="save-button-loading hidden items-center gap-2" aria-live="polite">
                                <svg class="w-4 h-4 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
                                </svg>
                                Saving batch…
                            </span>
                        @endif
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
            {{-- Wording is swapped by openModal(): under Use Subdivision Template this
                 same prompt asks for the mother file instead. --}}
            <h3 id="atx-old-fileno-modal-title" class="text-base font-bold">Old File Number Required</h3>
        </div>
        <div class="px-6 py-5 space-y-4">
            <p class="text-sm text-slate-600">
                <span id="atx-old-fileno-modal-type" class="font-semibold text-slate-900">This application</span><span id="atx-old-fileno-modal-lead"> derives from an existing file. Select the old (parent) file number from the file number selector.</span>
            </p>
            <div>
                <label id="atx-old-fileno-modal-label" class="block text-xs font-semibold text-slate-500 uppercase mb-1.5">Old File Number</label>
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
<script src="{{ asset('js/land-recommendation-op-match.js') }}?v={{ filemtime(public_path('js/land-recommendation-op-match.js')) }}"></script>
{{-- The capture screen's own account of itself — batch mode, mother/children
     loads, draft saves and restores, submits, script errors, unloads. Posted to
     /land-recommendations/client-log and written to
     storage/logs/land_recommendation.log next to the server's own entries, so one
     capture reads as one timeline. It only observes; nothing here changes how the
     form saves. --}}
<script src="{{ asset('js/land-recommendation-diagnostics.js') }}?v={{ filemtime(public_path('js/land-recommendation-diagnostics.js')) }}"></script>
<script src="{{ asset('js/land_recommendations.js') }}?v={{ time() + 1 }}"></script>
<script>
// ── Old File Number capture for derived application types ──────────────────
// Every application type comes off an existing file — Private Layout, Plot
// Subdivision, Plot Merger, Plot Extension, Temporary File No, Ministry of Works,
// Change of Purpose and Regrant alike — so picking any of them prompts for the
// parent file number. The rule is "an application type is selected" rather than a
// list of names, so a type added to the radio grid above cannot quietly slip
// through without one.
document.addEventListener('DOMContentLoaded', function () {

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

    var modalTitle   = document.getElementById('atx-old-fileno-modal-title');
    var modalLabel   = document.getElementById('atx-old-fileno-modal-label');
    var modalLead    = document.getElementById('atx-old-fileno-modal-lead');
    var titleLabel   = document.getElementById('atx-old-fileno-title');
    var manualHelpEl = document.getElementById('atx-old-fileno-help');
    var subdivHelp   = document.getElementById('subdivision-mother-help');
    var subdivNote   = document.getElementById('subdivision-template-note');

    if (!row || !field || !modal) return;

    // Use Subdivision Template turns this very field into the Mother File Number.
    // The radios are [data-batch-child], so batch mode DISABLES them while leaving
    // whatever was picked still `:checked` — the disabled test is what stops a
    // batch inheriting a single record's answer.
    function subdivisionTemplateOn() {
        var picked = document.querySelector('.subdivision-template-radio:checked');
        return !!picked && picked.value === '1' && !picked.disabled;
    }

    function currentAppType() {
        var toggle  = document.getElementById('app-type-toggle');
        var checked = document.querySelector('.app-type-radio:checked');
        if (toggle && !toggle.checked) return '';
        return checked ? checked.value : '';
    }

    // currentAppType() already returns '' when the Application Type toggle is off,
    // so this is false for a plain Direct / Conversion recommendation.
    //
    // Use Subdivision Template demands one too, under its own name: there the field
    // carries the mother file the plot was subdivided from, and the record is
    // pointless without it.
    function requiresOldFileNo(appType) {
        return !!appType || subdivisionTemplateOn();
    }

    function openModal(appType) {
        // Under Use Subdivision Template the prompt is asking for the mother file,
        // and may be doing so with no application type set at all — which would
        // otherwise leave the sentence starting on a blank.
        var subdivision = subdivisionTemplateOn();
        if (modalTitle) modalTitle.textContent = subdivision ? 'Mother File Number Required' : 'Old File Number Required';
        if (modalLabel) modalLabel.textContent = subdivision ? 'Mother File Number' : 'Old File Number';
        if (modalLead) {
            modalLead.textContent = subdivision
                ? ' was subdivided from a mother file. Pick it with the file number selector — or build it there with the Manual method if it is not in the register.'
                : ' derives from an existing file. Select the old (parent) file number from the file number selector.';
        }
        if (modalType) modalType.textContent = subdivision ? 'This plot' : appType;
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
        var subdivision = subdivisionTemplateOn();

        if (requiresOldFileNo(appType)) {
            row.classList.remove('hidden');
            if (contextLabel) contextLabel.textContent = appType || 'subdivision';
        } else {
            row.classList.add('hidden');
            if (field.value) setOldFileNo('');
        }

        // Batch mode owns this card outright — applyKind() swaps the whole-register
        // picker for the mother dropdown and drives the same help span. Standing
        // aside here is what stops the two scripts toggling it against each other;
        // applyKind calls back into this once batch mode lets go.
        if (batchModeOn()) return;

        // The field itself is never typed into — it stays readonly like every other
        // file number on this form. A mother that is not in the register is entered
        // through the global file-number selector's Manual method, which constructs
        // a number from its parts rather than searching for one, so "not indexed"
        // costs nothing here and there is no second way in to keep in step.
        field.placeholder = subdivision
            ? 'No mother file selected'
            : 'No old file number selected';
        if (titleLabel) titleLabel.textContent = subdivision ? 'Mother File Number' : 'Old File Number';
        if (manualHelpEl) manualHelpEl.classList.toggle('hidden', subdivision);
        if (subdivHelp) subdivHelp.classList.toggle('hidden', !subdivision);
        if (subdivNote) subdivNote.classList.toggle('hidden', !subdivision);
    }

    // Answering Yes/No repaints the card the app-type radios share. Answering it is
    // not yet a request to be asked for a file number, so no prompt opens here —
    // the submit gate below still refuses a save without one.
    document.querySelectorAll('.subdivision-template-radio').forEach(function (radio) {
        radio.addEventListener('change', function () { syncRow(currentAppType()); });
    });

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

    // Leaving batch mode hands this card back, and its state is decided here —
    // see the batchModeOn() guard in syncRow(). applyKind() calls this last.
    window._syncOldFileNoRow = function () { syncRow(currentAppType()); };

    // Initial state (edit mode / validation redisplay)
    syncRow(currentAppType());
});

// ── Form gate: nothing below the file number card until a file is chosen ──────
// The fields describe one file and most are filled from its record, so an empty
// form is only an invitation to key a letter against no file at all.
//
// Batch Mode is gated too, but not by the same field — a batch has no single file
// number. What opens it there is the batch having files in it: the mother's
// children for a subdivision, or the picked set for a regular batch, both of which
// land as rows in the batch table. Recommendation Type, Application Type and Old
// File Number stay reachable throughout, because in a subdivision they are what you
// answer FIRST — pick Plot Subdivision, then the mother, and only then is there a
// batch to fill in. They are outside #form-body while batch mode is on (the batch
// module lifts them up under the batch card), so hiding the body never touches them.
document.addEventListener('DOMContentLoaded', function () {
    var recForm     = document.getElementById('land-recommendation-form');
    var fileNoInput = document.getElementById('file_number');
    var body        = document.getElementById('form-body');
    var footer      = document.getElementById('form-action-footer');
    var notice      = document.getElementById('awaiting-file-notice');
    var noticeTitle = document.getElementById('awaiting-file-title');
    var noticeHint  = document.getElementById('awaiting-file-hint');
    var batchToggle = document.getElementById('batch-mode-toggle');
    var batchRows   = document.getElementById('batch-children-rows');

    if (!recForm || !fileNoInput || !body) return;

    // An edit is never gated — the record already has its file, and a batch edit
    // turns Batch Mode on without a change event and seeds its rows a moment later,
    // which would otherwise blink the whole form out and back in on load.
    var ALWAYS_OPEN = @json((bool) ($isEdit || $batchEdit));

    function batchOn() {
        return !!(batchToggle && batchToggle.checked);
    }

    function unlocked() {
        if (ALWAYS_OPEN) return true;
        // .batch-row, not any <tr>: the loader and the "no children found" empty
        // state are rows too, and neither means the batch has files in it.
        if (batchOn()) return !!(batchRows && batchRows.querySelector('.batch-row'));
        return fileNoInput.value.trim() !== '';
    }

    function sync() {
        var on = unlocked();
        body.classList.toggle('hidden', !on);
        if (footer) footer.classList.toggle('hidden', !on);
        if (notice) notice.classList.toggle('hidden', on);

        // Which file the user is being asked for is not the same question in a batch.
        if (!on && noticeTitle && noticeHint) {
            if (batchOn()) {
                noticeTitle.textContent = 'Select the files for this batch to begin';
                noticeHint.textContent  = 'Pick the mother file above (or the files for a regular batch) — the rest of the form is filled once the batch has files in it.';
            } else {
                noticeTitle.textContent = 'Select a file number to begin';
                noticeHint.textContent  = 'The rest of the form is filled from the selected file, so it stays hidden until one is chosen.';
            }
        }

        if (on && window.lucide) window.lucide.createIcons();
    }

    // The file-number selector writes the field with jQuery's .val().trigger('change'),
    // which never reaches a native listener — so bind through jQuery as well.
    fileNoInput.addEventListener('change', sync);
    fileNoInput.addEventListener('input', sync);
    if (window.jQuery) window.jQuery(fileNoInput).on('change input', sync);

    // Batch Mode changes both the answer and the question, so its toggle re-runs the
    // gate; the rows arriving (or being cleared) is what opens and closes it after.
    if (batchToggle) batchToggle.addEventListener('change', sync);
    if (batchRows && window.MutationObserver) {
        new MutationObserver(sync).observe(batchRows, { childList: true });
    }

    sync();
});
</script>

@if($batchCapture)
<style>
    /* Values the batch table owns are suppressed on the main form while batch
       mode is on. !important because the application-type script toggles the
       `hidden` class on #app-type-extra independently. */
    #land-recommendation-form.batch-mode [data-batch-child] { display: none !important; }

    /* The mirror of the above: notes that only make sense while a batch is on. */
    #land-recommendation-form:not(.batch-mode) [data-batch-only] { display: none !important; }

    /* Page Number details: the detail rows are flex, so the page-number box sits
       only as wide as it needs and the detail beside it starts right after —
       and when the page number is stood down in batch mode (the table owns it),
       the detail simply takes the whole row. Once a stepper is on, the page
       numbers come back as a mirror of the current file's row, so the card reads
       exactly as it does outside a batch and this no longer applies. */
    #land-recommendation-form.batch-mode:not(.per-file-capture) #conversion-fields-section .pp-detail-col {
        min-width: 100%;
    }

    /* The two boxes the batch table owns, shown again for the file on the stepper.
       Beats the [data-batch-child] rule above on specificity, and only while there
       is a current file for them to belong to. */
    #land-recommendation-form.batch-mode.per-file-capture [data-batch-mirror] { display: block !important; }

    /* One note or the other, never both: which of the two applies follows the same
       switch as the mirror itself. */
    #land-recommendation-form.batch-mode.per-file-capture [data-batch-no-mirror] { display: none !important; }
    #land-recommendation-form.batch-mode.per-file-capture [data-batch-mirror-note] { display: block !important; }

    /* The row Apply-to-all copies from. A bar down the left edge rather than a row
       background, because each column carries its own tint for what Apply-to-all
       does to it and a row colour would sit under all of them. */
    #batch-children-rows tr.batch-source-row > td:first-child { box-shadow: inset 3px 0 0 #7c3aed; }
    #batch-children-rows tr.batch-row:hover > td { background-color: rgb(245 243 255 / 0.7); }

    /* The line between the two banks of columns — the per-plot ones on the left,
       the ones Apply-to-all copies on the right. Drawn on the first cell of the
       right bank so it runs the full height of the table, header included. */
    #batch-children-card .batch-group-split { border-left: 2px solid #7dd3fc; }

    /* Three page numbers share one column, and the browser's spinner arrows were
       taking most of the room from each — leaving a box too narrow to see what was
       typed in it. Nobody steps a page reference up one at a time, so the arrows go
       and the digits get the space. */
    #batch-children-card input[type="number"]::-webkit-outer-spin-button,
    #batch-children-card input[type="number"]::-webkit-inner-spin-button {
        -webkit-appearance: none;
        margin: 0;
    }
    #batch-children-card input[type="number"] {
        -moz-appearance: textfield;
        appearance: textfield;
    }
</style>
<script>
// ── Batch capture ──────────────────────────────────────────────────────────
// One recommendation per file, in a single pass. Two kinds share this module and
// the same table: a SUBDIVISION batch, whose rows are the commissioned children
// of one mother file, and a REGULAR batch, whose rows are file numbers the
// officer picked by hand. The rest of the form keeps capturing the values common
// to the whole batch; only what differs per file lives in the table, and the form
// posts to the batch endpoint instead.
document.addEventListener('DOMContentLoaded', function () {
    var BATCH_TYPE     = 'Plot Subdivision';
    var MOTHERS_URL    = '{{ route('land-recommendations.subdivision-mothers') }}';
    var CHILDREN_URL   = '{{ route('land-recommendations.subdivision-children') }}';
    // Where switching batch mode on points the form. On an edit that is the batch
    // it is editing, NOT the capture endpoint — applyBatchMode() writes this over
    // whatever the markup set, so a create URL here sends the edit's spoofed PUT
    // to /land-recommendations/batch, which the resource route then reads as a
    // record whose id is the word "batch".
    var BATCH_ACTION   = '{{ $batchEdit ? route('land-recommendations.batch-update', $batchEdit['batch_id']) : route('land-recommendations.store-batch') }}';
    var FILES_URL      = '{{ route('land-recommendations.batch-files') }}';
    var FILE_DETAIL_URL = '{{ route('land-recommendations.batch-file-details') }}';
    var PURPOSES_URL   = '{{ url('api/reference/purposes') }}';

    // Null on capture; the saved batch being re-opened otherwise. Everything it
    // changes is a variation on the capture screen rather than a second screen:
    // the mode and the kind stop being choices, the registry is never re-read over
    // what was saved, autosave is off (there is nothing to lose — the records
    // already exist), and every card that steps per file does so for BOTH kinds,
    // because in an edit each child is a record with values of its own.
    var BATCH_EDIT = @json($batchEdit);

    // Each saved child's per-file values exactly as loaded. An issued RoFO row
    // posts only the fields that differ from these (see syncGrantInputs).
    var savedGrant = {};
    ((BATCH_EDIT && BATCH_EDIT.children) || []).forEach(function (c) {
        if (c && c.file_number && c.grant) savedGrant[c.file_number] = Object.assign({}, c.grant);
    });

    var toggle      = document.getElementById('batch-mode-toggle');
    var hint        = document.getElementById('batch-mode-hint');
    var fileNoCard  = document.getElementById('file-number-card');
    var fileNoInput = document.getElementById('file_number');
    var appToggle   = document.getElementById('app-type-toggle');
    var oldFileNo   = document.getElementById('old_file_number');
    var card        = document.getElementById('batch-children-card');
    var rowsBody    = document.getElementById('batch-children-rows');
    var remainingCard  = document.getElementById('batch-remaining-card');
    var transferArrows = document.getElementById('batch-table-transfer-arrows');
    var remainingBody  = document.getElementById('batch-remaining-rows');
    var remainingCount = document.getElementById('batch-remaining-count');
    var savedRemainingCount = document.getElementById('batch-saved-remaining-count');
    var savedRofoCount = document.getElementById('batch-saved-rofo-count');
    var remainingSelectAll = document.getElementById('batch-remaining-select-all');
    var remainingSelectedCount = document.getElementById('batch-remaining-selected-count');
    var addSelectedRemainingBtn = document.getElementById('batch-add-selected-remaining');
    var remainingChildren = [];
    // New files belong at the top while this edit is open, so the officer can
    // immediately distinguish this session's additions from saved batch files.
    var newlyAddedBatchFiles = new Set();
    var motherLabel = document.getElementById('batch-mother-label');
    var countLabel  = document.getElementById('batch-children-count');
    var statusBox   = document.getElementById('batch-children-status');
    var selectAll   = document.getElementById('batch-select-all');
    var reloadBtn   = document.getElementById('batch-reload-children');
    var applyAllBtn = document.getElementById('batch-apply-all');
    var motherPick  = document.getElementById('batch-mother-picker');
    var motherSel   = document.getElementById('batch-mother-select');
    var motherApply = document.getElementById('batch-mother-apply');
    var manualPick  = document.getElementById('atx-old-fileno-manual');
    var motherHelp  = document.getElementById('batch-mother-help');
    var manualHelp  = document.getElementById('atx-old-fileno-help');
    var motherField = document.getElementById('batch-mother-file-no');
    var mothersLoaded = false;

    // Regular-files kind.
    var kindRow     = document.getElementById('batch-kind-row');
    var kindRadios  = document.querySelectorAll('.batch-kind-radio');
    var kindInput   = document.getElementById('batch-kind');
    var filesPicker = document.getElementById('batch-files-picker');
    var fileSel     = document.getElementById('batch-file-select');
    var filesApply  = document.getElementById('batch-files-apply');
    var filesClear  = document.getElementById('batch-files-clear');
    var filesCount  = document.getElementById('batch-files-count');
    var cardTitle   = document.getElementById('batch-card-title');
    var colFileNo   = document.getElementById('batch-col-file-no');
    var footerNote  = document.getElementById('batch-footer-note');
    var filesSelectReady = false;
    // File numbers (uppercased) from the most recent picker search. createTag is
    // only handed the typed term, and it has to be able to tell whether the
    // register already returned that exact number — see the note on createTag.
    var lastFileResults = [];

    // Everything that only makes sense when one row can be copied onto the others.
    var srcHint     = document.getElementById('batch-source-hint');
    var copyLegend  = document.getElementById('batch-copy-legend');
    var bandHeader  = document.getElementById('batch-band-header');
    var colSrc      = document.getElementById('batch-col-src');
    var colSrcCol   = document.getElementById('batch-colgroup-src');
    var colSrcAnchor = colSrcCol ? colSrcCol.previousElementSibling : null;

    // Per-file capture, regular batch only. Two cards carry a stepper — Grant
    // Conditions and Applicant & Property — and both are driven from one index, so
    // they always show the same file.
    var stepNavs    = document.querySelectorAll('.per-file-step-nav');
    var grantInputs = document.getElementById('grant-per-child-inputs');
    var batchInfo   = document.getElementById('batch-regular-info');
    var batchInfoCount = document.getElementById('batch-regular-info-count');

    function eachStep(selector, fn) {
        Array.prototype.forEach.call(stepNavs, function (nav) {
            var el = nav.querySelector(selector);
            if (el) fn(el, nav);
        });
    }
    var recTypeNote    = document.getElementById('batch-rec-type-note');
    // Set by applyRegularRecType so the submit handler can block a mixed batch
    // without re-deriving it. Up here with the rest of the state for the same
    // reason as PER_FILE_FIELDS below — applyKind() writes it and can run early.
    var regularTypeMixed = false;
    // Declared with the rest of the state rather than beside the functions that
    // use them: applyKind() runs from a change event that can fire while this
    // script is still executing, and a `var` initialised further down would be
    // undefined at that point.
    //
    // Every field a regular batch captures once per file, by input name, across
    // the stepped cards. Order matters on the way in — preparation_fees is
    // written before preparation_fees_words so the derived wording cannot
    // overwrite the stored one. Anything added to any stepped card must be listed
    // here, and in LandRecommendationController::PER_CHILD_GRANT_FIELDS, or it
    // will not travel per file. Each name must match exactly one input on the
    // form: the lookup is a querySelector by name and would otherwise step a
    // different card's field.
    var PER_FILE_FIELDS = [
        // Grant Conditions
        'cofo_year', 'selected_year', 'term', 'development_value',
        'development_period', 'ground_rent', 'ground_rent_unit', 'ground_rent_unit_other',
        'area_sqm',
        'development_charge',
        'survey_fees', 'preparation_fees', 'preparation_fees_words',
        // Applicant & Property. A hand-picked set spans layouts, so TP No. cannot
        // be captured once for the whole batch. Application Date stays batch-wide
        // — one date for the batch is fine.
        'layout_plan_no',
        // Page Number details — the whole card, not just the page numbers: once it
        // steps, a field left inside it that stayed batch-wide would read as
        // per-file and save as shared.
        // Application Page No (page) and Physical Planning Page No (page_2) are
        // the exception, and are NOT here: the
        // batch table already captures those per child in its Pg / Memo boxes, so
        // a second source would post two children[i][page] inputs for one value.
        'page_survey_report', 'survey_report', 'physical_planning_comment',
        'improvement', 'revision_period', 'time_of_erection',
        // RofO Generation Data — a radio group, not an input. See grantEls().
        'rofo_survey_method',
        // Recommendation & Reasons.
        'recommendation'
    ];
    var grantStore  = [];
    // Keeps a child's stepped values while it is temporarily unticked.  The
    // stepped store itself deliberately contains selected rows only.
    var grantCache  = {};
    var grantIndex  = 0;

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
    // A large batch has many rows with the same land use. Keep an in-flight
    // request as well as the completed-list cache; otherwise every row notices
    // an empty cache at once and floods /api/reference/purposes with identical
    // requests before the first response can populate it.
    var purposeRequests = {};

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

    // 'subdivision' | 'regular'. Subdivision is the default and the fallback: it is
    // what every batch was before regular files existed, so nothing that fails to
    // read the radios can silently change the meaning of a post.
    function currentKind() {
        var picked = document.querySelector('.batch-kind-radio:checked');
        return (picked && picked.value === 'regular') ? 'regular' : 'subdivision';
    }

    function isRegular() { return currentKind() === 'regular'; }

    // Whether the stepped cards (Grant Conditions, Applicant & Property, Page
    // Number details) capture one set of values per file rather than one for the
    // batch. This is now on for both kinds while batch mode is on, so subdivision
    // capture can key per-file values from the same 1-of-N controls.
    function perFileOn() { return !!toggle.checked; }

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
        if (kindRow) kindRow.classList.toggle('hidden', !on);

        // Autosave only exists for a batch being captured — a single record is
        // short enough that losing it to a timeout is an annoyance rather than a
        // day's work, and an edit of a saved batch has nothing to lose at all.
        if (draftBar) draftBar.classList.toggle('hidden', !on);
        if (draftKeyInput) draftKeyInput.disabled = !on;
        if (expectedInput) expectedInput.disabled = !on;
        if (on && !BATCH_EDIT) bootstrapDrafts();

        // The batch has no single file number — each child carries its own.
        if (fileNoCard) fileNoCard.classList.toggle('hidden', on);
        if (fileNoInput) {
            fileNoInput.disabled = on;
            fileNoInput.required = !on;
        }

        standDownPerChildFields(on);
        // Batch rows are submitted independently of the single-record fields.
        // Native constraint validation skips a disabled control, which made a row
        // that looked selected but had not yet been re-enabled reach Laravel with
        // no applicant address. Batch validation below checks the selected rows
        // after their controls are restored, so the user is taken to the exact
        // row instead of receiving a generic children.N error from the server.
        recForm.noValidate = on;
        recForm.setAttribute('action', on ? BATCH_ACTION : singleAction);

        // Only posted by the batch endpoint; a disabled input is never submitted.
        if (motherField) {
            motherField.disabled = !on;
            motherField.value = (on && oldFileNo) ? oldFileNo.value.trim() : '';
        }

        // Which sections belong to which kind, what the table calls its rows, and
        // whether the type panel is forced open all follow from the kind — so they
        // live in one place rather than being decided twice.
        applyKind(on);

        if (!on) {
            card.classList.add('hidden');
            rowsBody.innerHTML = '';
            setStatus('');
        }
        if (window.lucide) window.lucide.createIcons();
    }

    // Everything that differs between the two kinds of batch. Called when batch mode
    // is switched and whenever the kind itself changes; `on` is batch mode's state,
    // so switching batch mode off restores the single-record form regardless of kind.
    function applyKind(on) {
        var regular = on && isRegular();
        var subdivision = on && !regular;

        if (kindInput) {
            kindInput.disabled = !on;
            kindInput.value = regular ? 'regular' : 'subdivision';
        }

        // A regular batch has no mother. The field still posts (the endpoint accepts
        // it empty), but it must not carry the old file number across — that would
        // group an unrelated set of files under a file they have nothing to do with.
        if (motherField && regular) motherField.value = '';

        // Subdivision batch mode only exists for an application type, so the type
        // panel is forced open and held there. A regular batch has no such
        // requirement — any type, or none, is valid — so the toggle stays the
        // officer's to set.
        if (appToggle) {
            if (subdivision && !appToggle.checked) {
                appToggle.checked = true;
                appToggle.dispatchEvent(new Event('change', { bubbles: true }));
            }
            // A regular batch prints the standard Direct / Conversion document, and
            // which of the two it is comes from the files themselves. An application
            // type would take that decision over — so the toggle is forced off and
            // held there for the length of the batch.
            if (regular && appToggle.checked) {
                appToggle.checked = false;
                appToggle.dispatchEvent(new Event('change', { bubbles: true }));
            }
            appToggle.disabled = on;
        }

        // A subdivision batch letter is the standard Direct / Conversion document —
        // the Plot Subdivision template describes the mother's split, not an
        // individual child's grant. Ticking this also releases the Recommendation
        // Type lock, so Direct / Conversion becomes selectable. The prior state is
        // restored on the way out so leaving the kind (or batch mode) does not
        // leave the box changed. A regular batch prints whatever the form says.
        var stdTemplate = document.getElementById('use-standard-template');
        if (stdTemplate) {
            if (subdivision) {
                if (stdTemplate.dataset.batchPrevChecked === undefined) {
                    stdTemplate.dataset.batchPrevChecked = stdTemplate.checked ? '1' : '0';
                }
                if (!stdTemplate.checked) {
                    stdTemplate.checked = true;
                    stdTemplate.dispatchEvent(new Event('change', { bubbles: true }));
                }
            } else if (stdTemplate.dataset.batchPrevChecked !== undefined) {
                var was = stdTemplate.dataset.batchPrevChecked === '1';
                delete stdTemplate.dataset.batchPrevChecked;
                if (stdTemplate.checked !== was) {
                    stdTemplate.checked = was;
                    stdTemplate.dispatchEvent(new Event('change', { bubbles: true }));
                }
            }
        }

        // Stack the three sections between Batch Mode and the table, in array order
        // (inserting each before the table preserves it). Only the subdivision kind
        // needs them up there — it is filled in that order, type then mother then
        // children. A regular batch picks its files from the card above the table,
        // so the sections stay where the markup puts them.
        if (card) {
            relocatable.forEach(function (r) {
                if (subdivision) {
                    if (!r.home) {
                        r.home = { parent: r.el.parentNode, next: r.el.nextSibling };
                    }
                    card.parentNode.insertBefore(r.el, card);
                } else if (r.home) {
                    r.home.parent.insertBefore(r.el, r.home.next);
                    r.home = null;
                }
            });
        }

        // Swap the whole-register file picker for the short list of files that
        // actually have subdivision children.
        if (motherPick) motherPick.classList.toggle('hidden', !subdivision);
        if (manualPick) manualPick.classList.toggle('hidden', subdivision);
        if (motherHelp) motherHelp.classList.toggle('hidden', !subdivision);
        if (manualHelp) manualHelp.classList.toggle('hidden', subdivision);

        // Batch mode borrows this card; on the way back out the single-record form
        // decides it again — including whether Use Subdivision Template has turned
        // it into the Mother File Number. Called after the toggles above so it wins
        // on the way out and defers on the way in (it no-ops while batch mode is on).
        if (window._syncOldFileNoRow) window._syncOldFileNoRow();

        if (hint) hint.classList.toggle('hidden', !subdivision);
        if (filesPicker) filesPicker.classList.toggle('hidden', !regular);
        if (reloadBtn) reloadBtn.classList.toggle('hidden', !subdivision);

        // Apply-to-all copies one row onto the rest, which is a subdivision idea:
        // the children of one mother share an applicant, a location and a page set.
        // Unrelated files share none of that, so the button, the SRC column and the
        // two colour bands that explain them all stand down — a band headed
        // "copied from the source row" over columns nothing copies into is worse
        // than no band at all.
        if (applyAllBtn) applyAllBtn.classList.toggle('hidden', regular);
        // The per-column Apply buttons copy from the same source row, so they
        // stand down with it.
        document.querySelectorAll('.batch-col-apply').forEach(function (b) {
            b.classList.toggle('hidden', regular);
        });
        if (srcHint)     srcHint.classList.toggle('hidden', regular);
        if (copyLegend)  copyLegend.classList.toggle('hidden', regular);
        if (bandHeader)  bandHeader.classList.toggle('hidden', regular);
        if (colSrc)      colSrc.classList.toggle('hidden', regular);
        // The Src <col> must leave with its cells. Hidden cells drop out of the grid
        // but a <col> does not, so every later column slid one track left and the
        // File No landed in Src's 50px — "COM-…" instead of the whole number.
        if (colSrcCol) {
            if (regular && colSrcCol.parentNode) {
                colSrcCol.parentNode.removeChild(colSrcCol);
            } else if (!regular && !colSrcCol.parentNode && colSrcAnchor && colSrcAnchor.parentNode) {
                colSrcAnchor.parentNode.insertBefore(colSrcCol, colSrcAnchor.nextSibling);
            }
        }
        rowsBody.querySelectorAll('.batch-src-cell').forEach(function (td) {
            td.classList.toggle('hidden', regular);
        });
        // The left-edge bar and the "Source row" badge mark a row nothing copies
        // from once the picker is gone, so they go with it.
        rowsBody.querySelectorAll('.batch-row').forEach(function (tr) {
            if (regular) tr.classList.remove('batch-source-row');
        });
        rowsBody.querySelectorAll('.batch-source-badge').forEach(function (b) {
            if (regular) b.classList.add('hidden');
        });

        // Per-file steppers run for both kinds of batch.
        var stepped = on && perFileOn();
        Array.prototype.forEach.call(stepNavs, function (nav) {
            nav.classList.toggle('hidden', !stepped);
        });

        // With a stepper there is one current file, so the two columns the table
        // owns are shown on the Page Number card as a mirror of its row. The class
        // is what the stylesheet keys the mirror (and its note) off; the inputs are
        // re-enabled by hand because standDownPerChildFields() disabled them along
        // with every other [data-batch-child] on the way in.
        recForm.classList.toggle('per-file-capture', stepped);
        mirrorEls().forEach(function (el) {
            el.disabled = !stepped && on;
            if (el.dataset.batchWasDisabled !== undefined) el.dataset.batchWasDisabled = '0';
        });
        if (stepped) writeMirrors();
        if (batchInfo) batchInfo.classList.toggle('hidden', !regular);
        if (stepped) {
            rebuildGrantStore();
        } else {
            grantStore = [];
            grantCache = {};
            grantIndex = 0;
            regularTypeMixed = false;
            if (grantInputs) grantInputs.innerHTML = '';
        }

        // The Application Type card is off and untouchable for the length of a
        // batch either way — forced on for a subdivision, forced off for a regular
        // one — so it is dimmed to match the toggle rather than looking live.
        var appCard = document.getElementById('application-type-card');
        if (appCard) appCard.classList.toggle('opacity-60', on);

        // The rows mean different things, so the table says which it is holding.
        if (cardTitle)  cardTitle.textContent = regular ? 'Selected files' : 'Children of';
        if (motherLabel) motherLabel.classList.toggle('hidden', regular);
        if (colFileNo)  colFileNo.textContent = regular ? 'File No' : 'Child File No';
        if (footerNote) {
            footerNote.textContent = BATCH_EDIT
                ? 'Rows in this lower table are saved in the batch. Untick one to move it back to Remaining Records; '
                    + 'a file added from above joins this batch at the end.'
                : (regular
                    ? 'Untick any file that should not receive a recommendation. Every ticked row is saved as its own '
                        + 'RofO recommendation, grouped under one batch.'
                    : 'Untick any child that should not receive a recommendation. Every ticked row is saved as its own '
                        + 'RofO recommendation, grouped under one batch.');
        }

        if (!on) return;

        if (subdivision) {
            loadMothers();
            syncForAppType();
        } else {
            initFileSelect();
            setStatus('');
            // A kind switch must not carry the other kind's rows across — they are
            // a different set of files entirely.
            if (!restoring) {
                card.classList.toggle('hidden', rowsBody.querySelectorAll('.batch-row').length === 0);
            }
            // After the setStatus('') above, or the message it writes is cleared
            // the moment it is set.
            applyRegularRecType();
        }
    }

    // The subdivision table only makes sense for a subdivision; any other type in
    // that kind falls back to an empty table and a note rather than silently doing
    // nothing. A regular batch has no such constraint, so it never comes through here.
    function syncForAppType() {
        if (!toggle.checked || suppressChildLoad || isRegular()) return;
        var type = currentAppType();

        if (type !== BATCH_TYPE) {
            card.classList.add('hidden');
            rowsBody.innerHTML = '';
            setStatus(type ? 'A subdivision batch covers ' + BATCH_TYPE + ' only — switch to Regular files for any other type.' : '', 'warn');
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

    function rowHtml(child, index, sourceIndex) {
        var i = index;
        var cell = 'w-full border border-slate-200 rounded-md px-2.5 py-2 text-xs bg-white hover:border-slate-300 focus:border-violet-500 focus:ring-2 focus:ring-violet-500/20 outline-none transition';
        var pageCell = 'w-full min-w-0 border border-slate-200 rounded-md px-2 py-2 text-xs text-center bg-white hover:border-slate-300 focus:border-violet-500 focus:ring-2 focus:ring-violet-500/20 outline-none transition';

        // Two tints, matching the two banks of columns: what Apply-to-all copies and
        // what belongs to the plot alone. Whether a copied column overwrites or only
        // fills a blank is said in words under its header, not in a third colour.
        var copiedCell = 'px-2 py-2.5 bg-sky-50/40';
        var ownCell    = 'px-2 py-2.5 bg-amber-50/40';

        // The source row is the user's choice — row 1 only until they say otherwise.
        var isSource = i === (sourceIndex || 0);

        // A child that already has a recommendation starts unticked: the server
        // rejects a batch containing one, so leaving it ticked would only produce a
        // failed save. Its fields still show what was captured.
        var alreadyDone = !!child.has_recommendation;
        // Issued RoFO rows stay in the batch and cannot be removed from it, but
        // their fields can be corrected. Only what the officer actually changes is
        // saved for them: the stepper posts a field only when it differs from the
        // value loaded with the page, and the server ignores the batch-wide set
        // and any blank for these rows (see updateBatch()).
        var rofoLocked = !!child.rofo_locked;
        // A missing correspondence address is still called out on the row.
        var addressCorrection = rofoLocked && !String(child.applicant_address || '').trim();

        return ''
            // The source row is marked with a bar down its left edge rather than a row
            // background, which the column tints would sit on top of anyway.
            + '<tr class="batch-row border-b border-slate-100 transition' + (isSource ? ' batch-source-row' : '') + (rofoLocked ? ' bg-slate-50' : '') + '" data-index="' + i + '" data-rofo-locked="' + (rofoLocked ? '1' : '0') + '" data-address-correction="' + (addressCorrection ? '1' : '0') + '">'
            + '<td class="px-2 py-2.5 text-center">'
            +   '<input type="checkbox" class="batch-row-check w-4 h-4 text-violet-600 border-slate-300 rounded focus:ring-violet-500 cursor-pointer"'
            +     ' data-had-rec="' + (alreadyDone ? '1' : '0') + '"'
            +     ' data-rofo-locked="' + (rofoLocked ? '1' : '0') + '"'
            +     ' data-existing-status="' + esc(child.existing_status || '') + '"'
            +     ' data-unknown="' + (child.is_unknown ? '1' : '0') + '"'
            +     (alreadyDone ? '' : ' checked') + (rofoLocked ? ' disabled title="RoFO generated — this file is locked"' : '') + '>'
            + '</td>'
            + '<td class="px-2 py-2.5 text-center text-xs font-bold text-slate-400">' + (i + 1) + '</td>'
            + '<td class="batch-src-cell px-1 py-2.5 text-center">'
            +   '<input type="radio" class="batch-row-source w-4 h-4 text-violet-600 border-slate-300 focus:ring-violet-500 cursor-pointer"'
            +     ' name="batch_source_row" value="' + i + '"' + (isSource ? ' checked' : '') + (rofoLocked ? ' disabled' : '')
            +     ' title="Copy this row\'s values into the others">'
            + '</td>'
            + '<td class="' + ownCell + '">'
            +   '<div class="font-mono font-bold text-slate-900 text-xs whitespace-nowrap overflow-hidden text-ellipsis" title="' + esc(child.file_number) + '">' + esc(child.file_number) + '</div>'
            +   '<span class="batch-source-badge mt-1 ' + (isSource ? 'inline-flex' : 'hidden') + ' items-center gap-1 px-1.5 py-0.5 rounded text-[9px] font-bold bg-violet-100 text-violet-700 uppercase tracking-wide">Source row</span>'
            +   (alreadyDone
                    ? '<span class="mt-1 inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-[9px] font-bold bg-rose-100 text-rose-700" title="' + esc(child.existing_status || '') + ' — already captured, so this row is excluded from the batch">Has a RofO</span>'
                    : '')
            // No register has anything on this number, so the row came back blank.
            // Said on the row itself, or a blank line reads as a load that failed.
            +   (rofoLocked
                    ? '<span class="mt-1 inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-[9px] font-bold bg-slate-200 text-slate-600" title="' + (addressCorrection ? 'RoFO generated; the applicant address is missing' : 'RoFO has been generated. It stays in this batch; only fields you change are saved') + '">' + (addressCorrection ? 'RoFO generated - address required' : 'RoFO generated - changes only') + '</span>'
                    : '')
            +   (child.is_unknown
                    ? '<span class="mt-1 inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-[9px] font-bold bg-amber-100 text-amber-800" title="Nothing is on file for this number — key its details by hand">Not on file</span>'
                    : '')
            +   '<input type="hidden" name="children[' + i + '][file_number]" value="' + esc(child.file_number) + '">'
            +   '<input type="hidden" name="children[' + i + '][tracking_id]" value="' + esc(child.tracking_id) + '">'
            + '</td>'
            // Left bank — this plot alone.
            + '<td class="' + ownCell + '"><input type="text" class="' + cell + ' batch-f font-mono" data-f="plot_number" name="children[' + i + '][plot_number]" value="' + esc(child.plot_number) + '" placeholder="Plot"></td>'
            + '<td class="' + ownCell + '"><input type="text" required class="' + cell + ' batch-f' + (addressCorrection ? ' border-amber-400 bg-amber-50 focus:border-amber-500' : '') + '" data-f="applicant_address" name="children[' + i + '][applicant_address]" value="' + esc(child.applicant_address) + '" placeholder="Applicant address"' + (addressCorrection ? ' title="Enter the missing applicant address"' : '') + '></td>'
            + '<td class="' + ownCell + '"><select class="' + cell + ' batch-f batch-landuse cursor-pointer" data-f="land_use_id" name="children[' + i + '][land_use_id]">' + landUseOptions(child.land_use_id) + '</select></td>'
            + '<td class="' + ownCell + '">'
            +   '<div class="space-y-1">'
            +     '<select class="' + cell + ' batch-f batch-purpose cursor-pointer" data-f="purpose_id" name="children[' + i + '][purpose_id]"><option value="">Select</option></select>'
            +     '<input type="text" class="' + cell + ' batch-f batch-purpose-other' + (String(child.purpose_id || '') === 'other' ? '' : ' hidden') + '"'
            +       ' data-f="purpose_id_other" name="children[' + i + '][purpose_id_other]" value="' + esc(child.purpose_id_other || '') + '"'
            +       ' placeholder="Specify other purpose"' + (String(child.purpose_id || '') === 'other' ? '' : ' disabled') + '>'
            +   '</div>'
            + '</td>'
            // Right bank — copied from the source row.
            + '<td class="' + copiedCell + ' batch-group-split"><input type="text" required class="' + cell + ' batch-f" data-f="applicant_name" name="children[' + i + '][applicant_name]" value="' + esc(child.applicant_name) + '" placeholder="Applicant name"></td>'
            + '<td class="' + copiedCell + '"><input type="text" class="' + cell + ' batch-f" data-f="location" name="children[' + i + '][location]" value="' + esc(child.location) + '" placeholder="Location"></td>'
            + '<td class="' + copiedCell + '">'
            +   '<div class="flex items-center gap-1">'
            +     '<input type="number" min="1" class="' + pageCell + ' batch-f" data-f="page"   name="children[' + i + '][page]"   value="' + esc(child.page) + '"   placeholder="Pg" title="Page No.">'
            +     '<input type="number" min="1" class="' + pageCell + ' batch-f" data-f="page_2" name="children[' + i + '][page_2]" value="' + esc(child.page_2) + '" placeholder="Memo" title="Auth. Memo Page">'
            +     '<input type="number" min="1" class="' + pageCell + ' batch-f" data-f="page_3" name="children[' + i + '][page_3]" value="' + esc(child.page_3) + '" placeholder="Plan" title="Site Plan Page">'
            +   '</div>'
            + '</td>'
            + '</tr>';
    }

    // The chosen source row, or the first row if nothing is marked.
    function sourceRow() {
        var picked = rowsBody.querySelector('.batch-row-source:checked');
        return picked ? picked.closest('tr') : rowsBody.querySelector('.batch-row');
    }

    // Move the left-edge bar and the "Source row" badge to the picked row, and
    // name that row on the Apply button so it is unambiguous what will be copied.
    function syncSourceLabel() {
        var current = sourceRow();
        rowsBody.querySelectorAll('.batch-row').forEach(function (tr) {
            var isSrc = tr === current;
            tr.classList.toggle('batch-source-row', isSrc);
            var badge = tr.querySelector('.batch-source-badge');
            if (badge) {
                badge.classList.toggle('hidden', !isSrc);
                badge.classList.toggle('inline-flex', isSrc);
            }
        });

        var label = document.getElementById('batch-apply-all-row');
        if (label) {
            var idx = current ? (parseInt(current.dataset.index, 10) + 1) : 1;
            label.textContent = '#' + (isNaN(idx) ? 1 : idx);
        }
    }

    function updateCount() {
        var n = rowsBody.querySelectorAll('.batch-row-check:checked').length;
        var total = rowsBody.querySelectorAll('.batch-row').length;
        countLabel.textContent = BATCH_EDIT ? total + ' children' : n + ' of ' + total + ' selected';
        syncBatchEditCounts();
        // Copy actions work on selected files only.
        if (applyAllBtn) applyAllBtn.disabled = n < 2;
        document.querySelectorAll('.batch-col-apply').forEach(function (b) {
            b.disabled = n < 2;
        });

        // Keep the header box honest when rows arrive part-selected — children that
        // already have a recommendation come back unticked.
        if (selectAll) {
            selectAll.checked = total > 0 && n === total;
            selectAll.indeterminate = n > 0 && n < total;
        }

        // The "not in batch" flag on the steppers follows the tick boxes.
        renderGrantStep();

        if (batchInfoCount) {
            batchInfoCount.textContent = total
                ? n + ' of ' + total + ' file' + (total === 1 ? '' : 's') + ' ticked'
                : 'no files picked yet';
        }
    }

    function syncRowEnabled(tr) {
        var on = tr.querySelector('.batch-row-check').checked;
        var locked = tr.dataset.rofoLocked === '1';
        var addressCorrection = tr.dataset.addressCorrection === '1';
        // The source picker stays live on an unticked row: copying values out of a
        // row that is not itself being saved is a perfectly reasonable thing to do.
        tr.querySelectorAll('input:not(.batch-row-check):not(.batch-row-source), select').forEach(function (el) {
            // Keep values enabled for submission: disabled controls do not post.
            // Locked rows instead look and behave disabled in the editor while
            // still carrying their existing values through the batch save.
            el.disabled = !on;
            if (locked) {
                // Editable; the server keeps every field the officer leaves as loaded.
                el.disabled = false;
                el.readOnly = false;
                el.removeAttribute('tabindex');
                el.removeAttribute('aria-disabled');
                el.classList.remove('pointer-events-none', 'bg-slate-100', 'text-slate-500', 'cursor-not-allowed');
            } else {
                el.readOnly = false;
                el.removeAttribute('aria-disabled');
                el.classList.remove('pointer-events-none', 'bg-slate-100', 'text-slate-500', 'cursor-not-allowed');
            }
        });
        tr.classList.toggle('opacity-40', !on && !locked);
    }

    // A selected row is the only row that belongs in the batch post. Reconcile its
    // controls one last time before submit: a checkbox can be changed while an
    // async row/purpose refresh is settling, and disabled inputs are omitted from
    // FormData even when their visible value is filled in.
    function prepareSelectedBatchRows() {
        var missing = null;

        rowsBody.querySelectorAll('.batch-row').forEach(function (tr) {
            var checkbox = tr.querySelector('.batch-row-check');
            if (!checkbox || !checkbox.checked) return;

            syncRowEnabled(tr);

            ['file_number', 'applicant_name', 'applicant_address', 'land_use_id'].some(function (field) {
                var el = field === 'file_number'
                    ? tr.querySelector('input[name$="[file_number]"]')
                    : tr.querySelector('[data-f="' + field + '"]');
                if (!el || !String(el.value || '').trim()) {
                    missing = { element: el, field: field, fileNumber: (tr.querySelector('input[name$="[file_number]"]') || {}).value || 'this file' };
                    return true;
                }
                return false;
            });
        });

        // A stepped TP No. left on "Other" without the number typed in posts the
        // placeholder, which the server drops. Say which file, before posting.
        if (!missing && perFileOn() && grantStore.length) {
            commitGrant();
            var unspecified = grantStore.filter(function (g) {
                return g && /^(__other__|others?)$/i.test(String(g.layout_plan_no || '').trim());
            })[0];
            if (unspecified) {
                setStatus('TP No. for ' + (unspecified.__file || 'a file') + ' is set to Other but not typed in. '
                    + 'Step to that file and type it in "Specify TP No...", or clear it.', 'error');
                return false;
            }
        }

        if (!missing) return true;

        var label = {
            file_number: 'File Number',
            applicant_name: 'Applicant Name',
            applicant_address: 'Applicant Address',
            land_use_id: 'Land Use'
        }[missing.field] || missing.field;
        setStatus(label + ' is required for ' + missing.fileNumber + '. Please complete that selected row.', 'error');
        if (missing.element) {
            missing.element.closest('tr').scrollIntoView({ behavior: 'smooth', block: 'center' });
            window.setTimeout(function () { missing.element.focus(); }, 250);
        }
        return false;
    }

    function syncPurposeOther(tr) {
        if (!tr) return;

        var purpose = tr.querySelector('.batch-purpose');
        var other = tr.querySelector('.batch-purpose-other');
        if (!purpose || !other) return;

        var isOther = String(purpose.value) === 'other';
        other.classList.toggle('hidden', !isOther);
        var locked = tr.dataset.rofoLocked === '1';
        other.disabled = !isOther || !tr.querySelector('.batch-row-check').checked;
        // An issued row's checkbox is disabled but the row is in the batch, so its
        // Other text stays live; the server keeps it unless it was changed.
        if (locked && isOther) {
            other.disabled = false;
            other.readOnly = false;
        }

        // A value that belongs to a previous Other selection must never be
        // submitted with a normal catalog purpose.
        if (!isOther) other.value = '';
    }

    function loadPurposes(select, landUseId, selectedPurposeId) {
        select.innerHTML = '<option value="">Select</option>';
        if (!landUseId) return Promise.resolve();

        var cacheKey = String(landUseId);
        var fetchList;

        if (purposeCache[cacheKey]) {
            fetchList = Promise.resolve(purposeCache[cacheKey]);
        } else if (purposeRequests[cacheKey]) {
            fetchList = purposeRequests[cacheKey];
        } else {
            fetchList = fetch(PURPOSES_URL + '?landuseid=' + encodeURIComponent(landUseId), {
                headers: { 'Accept': 'application/json' }
            })
                .then(function (response) {
                    if (!response.ok) {
                        throw new Error('Purpose request failed with HTTP ' + response.status);
                    }
                    return response.json();
                })
                .then(function (data) {
                    var list = Array.isArray(data) ? data : (data.data || data.purposes || []);
                    purposeCache[cacheKey] = list;
                    return list;
                })
                .catch(function () {
                    // The next deliberate action may retry, but all rows in the
                    // current action share this one failed request rather than
                    // generating hundreds of 429 responses.
                    return [];
                });

            purposeRequests[cacheKey] = fetchList;
            fetchList.then(function () {
                delete purposeRequests[cacheKey];
            });
        }

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
    // Named for what it builds, not for the element it feeds — `motherLabel` is
    // already the card-header node above, and a var assignment beats a hoisted
    // function declaration, so reusing the name left this uncallable.
    // The count is children still to be captured, not children in total — a file
    // whose plots are half done should read as half done. Files with nothing left
    // do not reach the picker at all.
    function formatMotherOption(m) {
        var label = m.file_number + ' — ' + m.children + ' ' + (m.children === 1 ? 'child' : 'children');
        if (m.children_used) {
            label += ' left of ' + m.children_total + ' (' + m.children_used + ' already done)';
        }
        if (m.subdivided_as) {
            label += ' · subdivided as ' + m.subdivided_as;
        }
        return label;
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
                            return { id: m.file_number, text: formatMotherOption(m) };
                        })
                    };
                },
                cache: true
            }
            // No custom `language` block: Select2 4.x replaces the whole dictionary
            // rather than merging, so overriding one message loses `searching`,
            // `errorLoading` and the rest. The throw that follows is swallowed by
            // jQuery 3's promise chain and the dropdown sits on "Searching…" forever.
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
                        + (m.file_number === current ? ' selected' : '') + '>' + esc(formatMotherOption(m)) + '</option>';
                });
                motherSel.innerHTML = html;
            })
            .catch(function () {
                mothersLoaded = false;   // let a retry happen on the next toggle
                motherSel.innerHTML = '<option value="">Could not load subdivided files</option>';
                setStatus('Network error while loading subdivided files.', 'error');
            });
    }

    // ── Regular batch: the file picker ─────────────────────────────────────
    // Multi-select with tagging over the register. Searching server-side is the
    // only thing that scales here — the register is the whole point — and tagging
    // is what lets a file number that is not indexed yet still be captured.
    function initFileSelect() {
        if (filesSelectReady || !fileSel) return;

        // Without jQuery/Select2 the plain multiple <select> is useless (nothing
        // populates it), so the field degrades to a comma-separated text box
        // instead. Rare, but a batch that silently cannot be filled is worse.
        if (!window.jQuery || !jQuery.fn || !jQuery.fn.select2) {
            filesSelectReady = true;
            var box = document.createElement('textarea');
            box.id = 'batch-file-manual';
            box.rows = 3;
            box.placeholder = 'One file number per line, or separated by commas';
            box.className = 'w-full border border-violet-300 rounded-lg px-3 py-2.5 bg-white text-sm font-mono outline-none';
            fileSel.parentNode.insertBefore(box, fileSel);
            fileSel.classList.add('hidden');
            return;
        }

        filesSelectReady = true;
        var $sel = jQuery(fileSel);

        $sel.select2({
            placeholder: 'Search file numbers — pick as many as you need',
            width: '100%',
            multiple: true,
            // The chip renders from the <option>'s text, and an option can outlive
            // the code that labelled it — a resumed draft, or a tab that was open
            // before the plot suffix was dropped from the list. Stripped here as
            // well so nothing shows "· Plot …" whatever created it.
            templateSelection: function (data) {
                return String(data.text || data.id || '').replace(/\s*·\s*Plot\b.*$/i, '');
            },
            // Anything typed that the search did not return is still a valid pick:
            // paper files being captured for the first time have no register row yet.
            tags: true,
            minimumInputLength: 1,
            // No tag at all for a number the register just returned. Keeping the
            // check here rather than only in insertTag matters: Select2 appends the
            // tag's <option> to the underlying <select> before insertTag ever runs,
            // so a tag sharing a value with a real result left two options on the
            // same value — and the chip then rendered with the tag's label. That is
            // why picking "IND-2026-253 — SALISU UMAR" turned into
            // "IND-2026-253 (not in the register — added as typed)" once selected.
            createTag: function (params) {
                var term = jQuery.trim(params.term);
                if (!term) return null;
                if (lastFileResults.indexOf(term.toUpperCase()) !== -1) return null;
                // A typed number must not sneak past the same-type rule the list
                // below enforces — it would produce exactly the mixed batch the
                // dropdown is refusing to let you build.
                var lock = pickedTypeLock();
                if (lock !== null && isConversionFile(term) !== lock) return null;
                return { id: term.toUpperCase(), text: term.toUpperCase() + ' (not in the register — added as typed)', isNew: true };
            },
            // Select2 puts a created tag at the top of the list and highlights the
            // first row, so typing a file number in full offered "added as typed"
            // above the register's own match — and Enter took it. The file then
            // loaded blank as "Not on file" even though it exists. The tag now goes
            // last, and is dropped entirely when the register already has that exact
            // number.
            insertTag: function (data, tag) {
                var term = String(tag.id || '').trim().toUpperCase();
                var exact = data.some(function (item) {
                    return String(item.id || '').trim().toUpperCase() === term;
                });
                if (exact) return;
                // Before the "showing the first N" notice, which is always last.
                var noticeAt = data.findIndex
                    ? data.findIndex(function (item) { return item.isNotice; })
                    : -1;
                if (noticeAt >= 0) data.splice(noticeAt, 0, tag);
                else data.push(tag);
            },
            ajax: {
                url: FILES_URL,
                dataType: 'json',
                delay: 250,
                data: function (params) { return { q: params.term || '' }; },
                processResults: function (data) {
                    // One Recommendation Type is saved for the whole batch, so the
                    // first pick fixes it: once a CON file is in, only CON files can
                    // join, and vice versa. Enforced here rather than only at save —
                    // finding out at the end that a batch cannot be saved is the
                    // worst possible moment.
                    var lock = pickedTypeLock();

                    var results = ((data && data.files) || []).map(function (f) {
                        var label = f.file_number;
                        if (f.applicant_name) label += ' — ' + f.applicant_name;

                        var wrongType = lock !== null && isConversionFile(f.file_number) !== lock;
                        if (wrongType) {
                            label += lock
                                ? '  ⛔ not a conversion file — this batch is Conversion'
                                : '  ⛔ conversion file — this batch is Direct';
                        } else if (f.has_recommendation) {
                            label += '  ⛔ already has a recommendation';
                        }

                        return {
                            id: f.file_number,
                            text: label,
                            // A file that already carries one cannot go into a
                            // batch — storeBatch() rejects the whole post over it,
                            // so it is shown and blocked rather than picked and
                            // then failed at save.
                            disabled: !!f.has_recommendation || wrongType
                        };
                    });

                    // Remembered for createTag, which cannot see the results itself.
                    lastFileResults = results.map(function (r) {
                        return String(r.id || '').trim().toUpperCase();
                    });

                    // The search returned as many rows as it is allowed to, so there
                    // are almost certainly more. Said outright — a truncated list
                    // reads as "my file is not in the register".
                    if (data && data.capped) {
                        results.push({
                            id: '__more__',
                            text: 'Showing the first ' + (data.limit || results.length)
                                + ' matches — type more of the file number to narrow it down',
                            disabled: true,
                            isNotice: true
                        });
                    }

                    return { results: results };
                },
                cache: true
            }
        });

        $sel.on('change', function () { updateFilesCount(); });
        updateFilesCount();
    }

    // The file numbers currently picked, from whichever control is in play.
    function pickedFiles() {
        var manual = document.getElementById('batch-file-manual');
        if (manual) {
            // Never split on whitespace: KANGIS numbers contain spaces ("KNML 1").
            return manual.value.split(/[,;\n\r]+/).map(function (s) { return s.trim(); }).filter(Boolean);
        }
        if (!fileSel) return [];
        return Array.prototype.map.call(fileSel.selectedOptions || [], function (o) { return o.value.trim(); })
            .filter(Boolean);
    }

    function updateFilesCount() {
        if (!filesCount) return;
        var n = pickedFiles().length;
        filesCount.textContent = n + ' picked';
        filesCount.className = 'inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold '
            + (n ? 'bg-violet-600 text-white' : 'bg-slate-100 text-slate-600');
    }

    // Put a picked selection into the picker's control — used by a draft restore,
    // which has the file numbers but no Select2 options for them.
    function setPickedFiles(files) {
        files = (files || []).filter(Boolean);
        var manual = document.getElementById('batch-file-manual');
        if (manual) {
            manual.value = files.join('\n');
            updateFilesCount();
            return;
        }
        if (!fileSel) return;

        if (window.jQuery && jQuery.fn && jQuery.fn.select2 && filesSelectReady) {
            var $sel = jQuery(fileSel);
            $sel.empty();
            files.forEach(function (f) { $sel.append(new Option(f, f, true, true)); });
            $sel.trigger('change.select2');
        } else {
            fileSel.innerHTML = files.map(function (f) {
                return '<option value="' + esc(f) + '" selected>' + esc(f) + '</option>';
            }).join('');
        }
        updateFilesCount();
    }

    // Load the picked file numbers into the table. Same row shape and the same
    // renderer the subdivision children use, so everything downstream — Apply to
    // all, autosave, the save itself — cannot tell the two kinds apart.
    function loadPickedFiles(files) {
        var ticket = ++childLoadSeq;

        motherLabel.textContent = '';
        if (motherField) motherField.value = '';

        card.classList.remove('hidden');
        rowsBody.innerHTML = '<tr><td colspan="11" class="px-3 py-10 text-center text-xs text-slate-500">'
            + '<i data-lucide="loader-2" class="h-5 w-5 mx-auto mb-2 text-violet-400 animate-spin"></i>Loading files…</td></tr>';
        if (window.lucide) window.lucide.createIcons();
        setStatus('');

        return fetchFileDetails(files).then(function (result) {
            if (ticket !== childLoadSeq) return;

            if (!result.success) {
                rowsBody.innerHTML = '';
                setStatus(result.message || 'Could not load the selected files.', 'error');
                updateCount();
                return;
            }

            renderChildren(result.children);

            var alreadyDone = result.children.filter(function (c) { return c.has_recommendation; }).length;
            var notes = [];
            if (alreadyDone) {
                notes.push(alreadyDone + ' of these files already carry a recommendation and are unticked — they cannot be captured twice.');
            }
            if (result.unknown && result.unknown.length) {
                notes.push('Nothing is on file for: ' + result.unknown.join(', ') + ' — key their details by hand.');
            }
            setStatus(notes.join(' '), 'warn');
            scheduleSave();
        });
    }

    // The picked files as the registers have them right now, without touching the
    // table. Used by the Apply button and by a draft restore's backfill.
    function fetchFileDetails(files) {
        var body = new FormData();
        body.append('_token', csrfToken());
        files.forEach(function (f) { body.append('file_numbers[]', f); });
        // While a saved batch is being edited its own members must not come back
        // flagged "already has a recommendation" — they would arrive unticked and
        // pressing Apply would drop the whole batch out of its own edit.
        if (BATCH_EDIT) body.append('exclude_batch_id', BATCH_EDIT.batch_id);

        return fetch(FILE_DETAIL_URL, {
            method: 'POST',
            body: body,
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
        })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                return (data && data.success)
                    ? { success: true, children: data.children || [], unknown: data.unknown || [] }
                    : { success: false, message: (data && data.message) || 'Could not load the selected files.' };
            })
            .catch(function () {
                return { success: false, message: 'Network error while loading the selected files.' };
            });
    }

    // Paint a set of child rows into the table. Shared by the live load from the
    // server and by a draft restore, so a resumed capture is byte-for-byte the
    // table the user left — the draft simply supplies the same row objects the
    // children endpoint would have.
    /* ═══════════════════════════════════════════════════════════════════
       Per-file Grant Conditions — regular batch only
       ═══════════════════════════════════════════════════════════════════
       A subdivision's children are one grant split across plots, so they share
       one set of conditions. A regular batch is a set of unrelated files, and
       each of those is its own grant. Rather than bolt ten more columns onto an
       already 1184px-wide table, the Grant Conditions card becomes the step: the
       officer walks the batch with Prev / Next and the card shows one file at a
       time. The values live in `grantStore` — the inputs on screen are only ever
       the view onto the current index.

       The card's own inputs keep their original names, so outside a regular
       batch (single record, or a subdivision) nothing about this runs and the
       form posts exactly as it always did. */

    // PER_FILE_FIELDS / grantStore / grantIndex are declared with the other batch
    // state above, out of reach of the initialisation-order trap noted there.

    // One entry per per-file field. A radio group is several elements sharing a
    // name, so it carries its whole group and is read and written through the
    // checked one — querySelector alone would hand back the first radio, whose
    // .value is its own value whether or not it is the one selected.
    function grantEls() {
        return PER_FILE_FIELDS.map(function (f) {
            var el = recForm.querySelector('[name="' + f + '"]');
            if (!el) return { name: f, el: null };

            if (el.type === 'radio') {
                return {
                    name: f,
                    el: el,
                    radios: Array.prototype.slice.call(
                        recForm.querySelectorAll('[name="' + f + '"]')
                    )
                };
            }

            return { name: f, el: el };
        }).filter(function (p) { return p.el; });
    }

    // What the card currently shows, as a plain object.
    function readGrantCard() {
        var out = {};
        grantEls().forEach(function (p) {
            if (p.radios) {
                var picked = p.radios.filter(function (r) { return r.checked; })[0];
                out[p.name] = picked ? picked.value : '';
                return;
            }
            out[p.name] = p.el.value;
        });
        return out;
    }

    function writeGrantCard(values) {
        grantEls().forEach(function (p) {
            var next = (values && values[p.name] !== undefined) ? values[p.name] : '';
            var el = p.el;

            // A radio group: check the one that matches, clear the rest. A file with
            // no answer yet leaves every radio unchecked rather than inheriting the
            // previous file's method by default.
            if (p.radios) {
                p.radios.forEach(function (r) {
                    var want = (r.value === next);
                    if (r.checked !== want) {
                        r.checked = want;
                        r.dispatchEvent(new Event('change', { bubbles: true }));
                    }
                });
                return;
            }

            // TP No. is a Select2. Its value only sticks if an <option> for it
            // exists, and the rendered text only refreshes on change.select2 —
            // otherwise the box keeps showing the file before it.
            if (window.jQuery && jQuery.fn && jQuery.fn.select2 && jQuery(el).data('select2')) {
                var $el = jQuery(el);
                if (next) {
                    var known = Array.prototype.some.call(el.options || [], function (o) {
                        return o.value === next;
                    });
                    if (!known) $el.append(new Option(next, next, false, false));
                }
                $el.val(next || null).trigger('change.select2');

                // The "Specify TP No…" box belongs to whichever file was on screen
                // when it was opened. Left alone it follows you to the next file
                // still holding the previous one's text, and its input handler
                // would then overwrite that file's TP No on the next keystroke.
                var other = document.getElementById(el.id + '_other');
                if (other) { other.value = ''; other.style.display = 'none'; }
                return;
            }

            if (el.value === next) return;
            el.value = next;
            // preparation_fees_words is derived from preparation_fees by a change
            // listener elsewhere on this form; firing the event keeps it in step
            // instead of leaving the previous file's wording on screen.
            el.dispatchEvent(new Event('change', { bubbles: true }));
        });
    }

    function allGrantRows() {
        return Array.prototype.slice.call(rowsBody.querySelectorAll('.batch-row'));
    }

    function childKey(child) {
        return String((child && child.file_number) || '').trim().toUpperCase();
    }

    // The edit page is a complete reconciliation of the mother's children: the
    // upper table is outside this recommendation, the lower table is inside it,
    // and generated RoFO rows are the protected subset of the lower table.
    function syncBatchEditCounts() {
        if (!BATCH_EDIT) return;
        var inBatch = rowsBody ? rowsBody.querySelectorAll('.batch-row').length : 0;
        var rofoGenerated = rowsBody
            ? rowsBody.querySelectorAll('.batch-row[data-rofo-locked="1"]').length
            : 0;
        var outside = remainingChildren.length;
        var total = inBatch + outside;

        if (remainingCount) remainingCount.textContent = outside + ' of ' + total + ' remaining';
        if (countLabel) countLabel.textContent = inBatch + ' children';
        if (savedRemainingCount) savedRemainingCount.textContent = outside + ' remaining';
        if (savedRofoCount) savedRofoCount.textContent = rofoGenerated + ' RoFO generated';
    }

    // Saved-batch edit has a second, deliberately simpler table. These files are
    // eligible children but are not recommendations in this batch yet. Keeping
    // them outside the submitted children[] array is what makes "remove" real.
    function syncRemainingSelectionUi() {
        var available = remainingChildren.filter(function (child) { return !child.has_recommendation || child.is_batch_hidden; });
        var selected = available.filter(function (child) { return !!child.remaining_selected; });
        if (remainingSelectedCount) {
            remainingSelectedCount.textContent = selected.length
                ? selected.length + (selected.length === 1 ? ' record selected' : ' records selected')
                : 'No records selected';
        }
        if (addSelectedRemainingBtn) addSelectedRemainingBtn.disabled = selected.length === 0;
        if (remainingSelectAll) {
            remainingSelectAll.checked = available.length > 0 && selected.length === available.length;
            remainingSelectAll.indeterminate = selected.length > 0 && selected.length < available.length;
            remainingSelectAll.disabled = available.length === 0;
        }
    }

    function renderRemaining() {
        if (!BATCH_EDIT || !remainingCard || !remainingBody) return;
        remainingCard.classList.remove('hidden');
        syncBatchEditCounts();
        if (!remainingChildren.length) {
            remainingBody.innerHTML = '<tr><td colspan="6" class="px-4 py-7 text-center text-slate-400">Every eligible child is currently in this batch.</td></tr>';
            syncRemainingSelectionUi();
            return;
        }
        remainingBody.innerHTML = remainingChildren.map(function (child) {
            var returnedToRemaining = !!child.is_batch_hidden;
            var locked = !!child.has_recommendation && !returnedToRemaining;
            var status = locked
                ? '<span class="inline-flex px-3 py-1.5 rounded-md border border-slate-200 bg-slate-100 text-slate-500 font-bold">Already processed</span>'
                : (returnedToRemaining
                    ? '<span class="inline-flex px-3 py-1.5 rounded-md border border-violet-200 bg-violet-50 text-violet-700 font-bold">Add back to batch</span>'
                    : '<span class="inline-flex px-3 py-1.5 rounded-md border border-amber-200 bg-amber-50 text-amber-800 font-bold">Available</span>');
            return '<tr class="border-b border-amber-100 bg-white">'
                + '<td class="px-4 py-2.5 text-center"><input type="checkbox" class="batch-remaining-check h-4 w-4 rounded border-amber-300 text-violet-600 focus:ring-violet-500" data-file="' + esc(child.file_number) + '"' + (locked ? ' disabled' : '') + (child.remaining_selected && !locked ? ' checked' : '') + ' aria-label="Select ' + esc(child.file_number) + '"></td>'
                + '<td class="px-4 py-2.5 font-mono font-bold text-slate-800">' + esc(child.file_number) + '</td>'
                + '<td class="px-4 py-2.5">' + esc(child.plot_number || '—') + '</td>'
                + '<td class="px-4 py-2.5">' + esc(child.applicant_name || '—') + '</td>'
                + '<td class="px-4 py-2.5">' + esc(child.location || '—') + '</td>'
                + '<td class="px-4 py-2.5 text-right">' + status + '</td>'
                + '</tr>';
        }).join('');
        syncRemainingSelectionUi();
    }

    function moveRemainingToBatch(fileNumber) {
        var wanted = String(fileNumber || '').trim().toUpperCase();
        var pos = remainingChildren.findIndex(function (child) { return childKey(child) === wanted; });
        if (pos < 0) return;
        var child = remainingChildren.splice(pos, 1)[0];
        if (child.has_recommendation && !child.is_batch_hidden) return;
        var current = serializeChildren();
        child.remaining_selected = false;
        child.is_batch_hidden = false;
        child.has_recommendation = false;
        newlyAddedBatchFiles.add(childKey(child));
        child.checked = true;
        child.has_recommendation = false;
        child.is_source = current.length === 0;
        // A newly-added child inherits the current batch's per-file grant values
        // until the officer deliberately changes it in the lower table/stepper.
        if (!child.grant && current.length && current[0].grant) child.grant = current[0].grant;
        current.push(child);
        renderChildren(current);
        renderRemaining();
    }

    if (remainingBody) {
        remainingBody.addEventListener('change', function (e) {
            if (!e.target.classList.contains('batch-remaining-check')) return;
            var wanted = String(e.target.dataset.file || '').trim().toUpperCase();
            var child = remainingChildren.find(function (item) { return childKey(item) === wanted; });
            if (!child || (child.has_recommendation && !child.is_batch_hidden)) return;
            child.remaining_selected = e.target.checked;
            syncRemainingSelectionUi();
        });
    }

    if (remainingSelectAll) {
        remainingSelectAll.addEventListener('change', function () {
            remainingChildren.forEach(function (child) {
                if (!child.has_recommendation || child.is_batch_hidden) child.remaining_selected = remainingSelectAll.checked;
            });
            renderRemaining();
        });
    }

    if (addSelectedRemainingBtn) {
        addSelectedRemainingBtn.addEventListener('click', function () {
            var selected = remainingChildren.filter(function (child) {
                return (!child.has_recommendation || child.is_batch_hidden) && !!child.remaining_selected;
            });
            if (!selected.length) return;

            var selectedKeys = new Set(selected.map(childKey));
            remainingChildren = remainingChildren.filter(function (child) {
                return !selectedKeys.has(childKey(child));
            });
            var current = serializeChildren();
            selected.forEach(function (child) {
                child.checked = true;
                child.has_recommendation = false;
                child.is_batch_hidden = false;
                child.remaining_selected = false;
                newlyAddedBatchFiles.add(childKey(child));
                child.is_source = current.length === 0;
                if (!child.grant && current.length && current[0].grant) child.grant = current[0].grant;
                current.push(child);
            });
            renderChildren(current);
            renderRemaining();
        });
    }

    // Per-file cards and their Apply-to-all action apply only to children that
    // will actually be submitted. This keeps their count aligned with the
    // selected count shown above the table.
    function grantRows() {
        return allGrantRows().filter(function (tr) {
            var check = tr.querySelector('.batch-row-check');
            // Issued RoFO rows are stepped too, so a missing TP No. or other
            // condition can be completed. What protects them is syncGrantInputs():
            // for an issued row it posts only the fields changed from the value
            // loaded with the page, and the server applies nothing else.
            return !!(check && check.checked);
        });
    }

    function grantFileNumber(i) {
        var tr = grantRows()[i];
        if (!tr) return '';
        var hid = tr.querySelector('input[name$="[file_number]"]');
        return hid ? hid.value : '';
    }

    // Park whatever is on screen into the current slot. Called before every move
    // and before the batch is posted, so nothing keyed is left only in the DOM.
    function commitGrant() {
        if (!perFileOn() || !grantStore.length) return;
        if (grantIndex < 0 || grantIndex >= grantStore.length) return;
        var saved = readGrantCard();
        saved.__file = grantStore[grantIndex].__file;
        saved.__rowIndex = grantStore[grantIndex].__rowIndex;
        grantStore[grantIndex] = saved;
        if (saved.__file) grantCache[saved.__file] = saved;
    }

    function renderGrantStep() {
        if (!stepNavs.length) return;
        var total = grantStore.length;

        eachStep('.per-file-step-label', function (el) {
            el.textContent = total ? (grantIndex + 1) + ' of ' + total : 'No editable files';
        });
        eachStep('.per-file-step-file', function (el) {
            el.textContent = grantFileNumber(grantIndex);
        });
        eachStep('.per-file-step-prev', function (el) { el.disabled = grantIndex <= 0; });
        eachStep('.per-file-step-next', function (el) { el.disabled = grantIndex >= total - 1; });
        eachStep('.per-file-step-untick', function (el) {
            el.classList.add('hidden');
        });

        // Nothing to copy onto with fewer than two files in the batch.
        ['applicant-card-apply-all', 'grant-card-apply-all', 'page-card-apply-all'].forEach(function (id) {
            var btn = document.getElementById(id);
            if (btn) btn.disabled = total < 2;
        });

        writeMirrors();
    }

    /* ── Mirrored table columns on the Page Number card ──────────────────────
       Application Page No and Physical Planning Page No live in the batch table
       (its Pg / Memo columns) — that is where they are stored and what posts
       them. But the card they belong to steps one file at a time, and a card
       missing two of its boxes reads as a card with two fields lost rather than
       two fields moved. So while a stepper is on they are shown here too, bound
       to the current file's row: whichever one is typed into, both change and the
       row stays the single source. They are NOT in PER_FILE_FIELDS and never
       enter grantStore — the row is the store. The card's own inputs keep their
       names (`page`, `page_2`) and do post, but no batch rule accepts either at
       the top level, so the value is dropped and only children[i][page] counts. */

    function mirrorEls() {
        return Array.prototype.slice.call(
            document.querySelectorAll('#conversion-fields-section [data-mirror-f]')
        );
    }

    // The table cell a mirror is bound to, for the file currently on the stepper.
    function mirrorCell(field) {
        var tr = grantRows()[grantIndex];
        return tr ? tr.querySelector('[data-f="' + field + '"]') : null;
    }

    // Row → card. Called after every step and every re-render.
    function writeMirrors() {
        if (!perFileOn()) return;
        mirrorEls().forEach(function (el) {
            var cell = mirrorCell(el.dataset.mirrorF);
            var next = cell ? cell.value : '';
            if (el.value !== next) el.value = next;
        });
    }

    // Card → row.
    mirrorEls().forEach(function (el) {
        el.addEventListener('input', function () {
            if (!toggle.checked || !perFileOn()) return;
            var cell = mirrorCell(el.dataset.mirrorF);
            if (!cell || cell.value === el.value) return;
            cell.value = el.value;
            // The table's own change handling (and the draft) hang off this.
            cell.dispatchEvent(new Event('change', { bubbles: true }));
        });
    });

    function gotoGrant(i) {
        var total = grantStore.length;
        if (!total) return;
        commitGrant();
        grantIndex = Math.max(0, Math.min(total - 1, i));
        writeGrantCard(grantStore[grantIndex]);
        renderGrantStep();
    }

    // Rebuild the store to match the table. Rows keep their values across a
    // re-render by file number, so reordering or reloading the picked set does not
    // scramble conditions onto the wrong file. `seed` carries values restored from
    // a draft, keyed the same way.
    function rebuildGrantStore(seed) {
        var byFile = {};
        Object.keys(grantCache).forEach(function (fn) { byFile[fn] = grantCache[fn]; });
        grantStore.forEach(function (g, i) {
            var fn = (g && g.__file) || '';
            if (fn) byFile[fn] = g;
        });
        (seed || []).forEach(function (g) {
            if (g && g.__file) byFile[g.__file] = g;
        });

        // The card as it stands is the template for any file with nothing of its
        // own yet — a batch is usually keyed once and adjusted per file, not keyed
        // ten times from blank.
        var template = readGrantCard();

        grantStore = grantRows().map(function (tr, i) {
            var fn = grantFileNumber(i);
            var prev = fn ? byFile[fn] : null;
            var g = {};
            PER_FILE_FIELDS.forEach(function (f) {
                g[f] = (prev && prev[f] !== undefined) ? prev[f] : template[f];
            });
            g.__file = fn;
            g.__rowIndex = tr.dataset.index;
            if (fn) grantCache[fn] = g;
            return g;
        });

        if (grantIndex >= grantStore.length) grantIndex = Math.max(0, grantStore.length - 1);
        if (grantStore.length) writeGrantCard(grantStore[grantIndex]);
        renderGrantStep();
    }

    // Values captured per file, for the draft. Keyed by file number so a resumed
    // draft can put them back on the right rows even if the set changed.
    function serializeGrant() {
        commitGrant();
        return grantStore.map(function (g) { return Object.assign({}, g); });
    }

    // Write the store out as children[i][field] just before the post. Rebuilt from
    // scratch every time so a removed row never leaves a stale input behind.
    //
    // Only values that differ from what the card itself is posting are written. The
    // card posts as the batch-wide set, and the server falls back to it for any
    // field a child does not carry — so a batch where every file shares the
    // conditions adds no fields at all. That matters: a 200-file batch is already
    // near PHP's max_input_vars, and ten unconditional inputs per child would
    // double it (see the children_expected guard in storeBatch).
    function syncGrantInputs() {
        if (!grantInputs) return;
        grantInputs.innerHTML = '';
        if (!toggle.checked || !perFileOn()) return;

        commitGrant();
        var baseline = readGrantCard();
        var html = '';
        var str = function (x) { return (x === undefined || x === null) ? '' : String(x); };
        grantStore.forEach(function (g) {
            var rowIndex = g && g.__rowIndex;
            if (rowIndex === undefined || rowIndex === null || rowIndex === '') return;
            var tr = rowsBody.querySelector('.batch-row[data-index="' + rowIndex + '"]');
            // An issued row is compared with what it was saved with, not with the
            // card: a field left as loaded is not posted, so it is not touched.
            var original = (tr && tr.dataset.rofoLocked === '1') ? (savedGrant[g.__file] || {}) : null;
            PER_FILE_FIELDS.forEach(function (f) {
                var v = str(g && g[f]);
                if (original) {
                    if (v === str(original[f]) || v === '') return;
                } else if (v === str(baseline[f])) {
                    return;
                }
                html += '<input type="hidden" name="children[' + rowIndex + '][' + f + ']" value="' + esc(v) + '">';
            });
        });
        grantInputs.innerHTML = html;
    }

    eachStep('.per-file-step-prev', function (el) {
        el.addEventListener('click', function () { gotoGrant(grantIndex - 1); });
    });
    eachStep('.per-file-step-next', function (el) {
        el.addEventListener('click', function () { gotoGrant(grantIndex + 1); });
    });

    // Apply-to-all for a stepped card: the file on screen is copied onto every
    // other file in the batch, for that card's fields only.
    function bindCardApplyAll(buttonId, cardLabel) {
        var btn = document.getElementById(buttonId);
        if (!btn) return;

        btn.addEventListener('click', function () {
            var fields = (btn.dataset.cardFields || '').split(',')
                .map(function (f) { return f.trim(); })
                .filter(function (f) { return PER_FILE_FIELDS.indexOf(f) !== -1; });
            if (!fields.length) return;

            commitGrant();
            var source = grantStore[grantIndex];
            if (!source || grantStore.length < 2) return;

            if (!confirm('Copy ' + cardLabel + ' on screen onto all '
                    + grantStore.length + ' files in this batch? Whatever those files '
                    + 'currently hold in these fields is replaced.')) {
                return;
            }

            // Other cards retain the existing blank-only rule for issued files.
            // Grant Conditions applies every displayed value after confirmation.
            var kept = 0;
            grantStore.forEach(function (g, i) {
                if (i === grantIndex || !g) return;
                var tr = rowsBody.querySelector('.batch-row[data-index="' + g.__rowIndex + '"]');
                // Grant Conditions explicitly replaces the selected batch's values,
                // including issued files. syncGrantInputs posts those corrections per file.
                var saved = (buttonId !== 'grant-card-apply-all' && tr && tr.dataset.rofoLocked === '1')
                    ? (savedGrant[g.__file] || {}) : null;
                fields.forEach(function (f) {
                    if (saved && String(saved[f] == null ? '' : saved[f]).trim() !== '') { kept++; return; }
                    g[f] = source[f];
                });
            });

            if (BATCH_EDIT) editDirty = true;
            renderGrantStep();
            setStatus(cardLabel + ' copied onto ' + (grantStore.length - 1) + ' other file(s).'
                + (kept ? ' ' + kept + ' value(s) already saved on issued files were kept.' : ''), 'warn');
            // Real work, and a lot of it at once — drafted rather than left waiting on
            // the next keystroke. No-op while a saved batch is being edited.
            scheduleSave();
        });
    }

    bindCardApplyAll('applicant-card-apply-all', 'Applicant & Property');
    bindCardApplyAll('grant-card-apply-all', 'Grant Conditions');
    bindCardApplyAll('page-card-apply-all', 'Page Number details');
    bindCardApplyAll('rofo-card-apply-all', 'RofO Generation Data');
    bindCardApplyAll('reasons-card-apply-all', 'Recommendation & Reasons');

    /* ═══════════════════════════════════════════════════════════════════
       Recommendation Type from the picked files — regular batch only
       ═══════════════════════════════════════════════════════════════════
       Single capture reads Direct / Conversion off the one file number it has
       (autoDetectRecommendationType in land_recommendations.js). A regular batch
       has a set instead, so it applies the same rule across the set and locks the
       radios the same way — via the shared _lockRecommendationType, so the two
       screens cannot drift apart.

       The type is posted once for the whole batch, which means a set that mixes
       conversion and non-conversion files cannot be right for all of it. That is
       said plainly rather than resolved silently. */

    // regularTypeMixed is declared with the other batch state above.

    function isConversionFile(fileNo) {
        return typeof window._isConversionFileNo === 'function'
            ? window._isConversionFileNo(fileNo)
            : /CON/i.test(String(fileNo || ''));
    }

    // What the picked set has already committed the batch to:
    //   true  — Conversion, so only CON files may join
    //   false — Direct, so no CON file may join
    //   null  — nothing picked yet, or the set is already mixed (a draft keyed
    //           before this rule existed), in which case the picker stops
    //           policing and the submit guard reports it instead.
    function pickedTypeLock() {
        var picked = pickedFiles();
        if (!picked.length) return null;

        var anyConv  = picked.some(isConversionFile);
        var anyPlain = picked.some(function (f) { return !isConversionFile(f); });

        if (anyConv && !anyPlain) return true;
        if (anyPlain && !anyConv) return false;
        return null;
    }

    function setRecTypeNote(message, tone) {
        if (!recTypeNote) return;
        if (!message) { recTypeNote.classList.add('hidden'); recTypeNote.textContent = ''; return; }
        recTypeNote.className = 'mt-3 rounded-lg px-3 py-2 text-[11px] font-semibold ' + (
            tone === 'error' ? 'bg-rose-50 border border-rose-200 text-rose-700'
                             : 'bg-blue-50 border border-blue-200 text-blue-800'
        );
        recTypeNote.textContent = message;
    }

    function applyRegularRecType() {
        if (!toggle.checked || !isRegular()) {
            regularTypeMixed = false;
            setRecTypeNote('');
            return;
        }

        var files = grantRows().map(function (tr, i) { return grantFileNumber(i); })
                               .filter(Boolean);
        if (!files.length) { regularTypeMixed = false; setRecTypeNote(''); return; }

        var conv = files.filter(isConversionFile);
        var isConv = conv.length > 0;
        regularTypeMixed = conv.length > 0 && conv.length < files.length;

        if (typeof window._lockRecommendationType === 'function') {
            window._lockRecommendationType(isConv);
        }

        if (regularTypeMixed) {
            setRecTypeNote('This batch mixes ' + conv.length + ' conversion file'
                + (conv.length === 1 ? '' : 's') + ' with ' + (files.length - conv.length)
                + ' non-conversion. One Recommendation Type is saved for the whole batch, so '
                + 'they cannot both be right — split them into two batches.', 'error');
        } else {
            setRecTypeNote('Locked to ' + (isConv ? 'Conversion' : 'Direct')
                + ' — taken from the ' + files.length + ' file' + (files.length === 1 ? '' : 's')
                + ' picked. Change the selection to change the type.');
        }
    }

    function renderChildren(children) {
        if (BATCH_EDIT && newlyAddedBatchFiles.size) {
            children = children.slice().sort(function (a, b) {
                return Number(newlyAddedBatchFiles.has(childKey(b))) - Number(newlyAddedBatchFiles.has(childKey(a)));
            });
        }
        // The source row is the user's, and it survives a re-render and a draft
        // restore — falling back to the first row only when nothing is marked.
        var sourceIndex = 0;
        children.some(function (c, i) {
            if (c.is_source) { sourceIndex = i; return true; }
            return false;
        });

        rowsBody.innerHTML = children.map(function (child, i) {
            var startsSavedFiles = BATCH_EDIT && i > 0
                && newlyAddedBatchFiles.has(childKey(children[i - 1]))
                && !newlyAddedBatchFiles.has(childKey(child));
            var divider = i === 0 && BATCH_EDIT && newlyAddedBatchFiles.has(childKey(child))
                ? '<tr class="bg-violet-50"><td colspan="11" class="border-y-2 border-violet-200 px-4 py-2 text-[10px] font-extrabold uppercase tracking-wide text-violet-700">Newly added files</td></tr>'
                : (startsSavedFiles
                    ? '<tr class="bg-slate-50"><td colspan="11" class="border-y-2 border-slate-200 px-4 py-2 text-[10px] font-extrabold uppercase tracking-wide text-slate-600">Previously saved batch files</td></tr>'
                    : '');
            return divider + rowHtml(child, i, sourceIndex);
        }).join('');
        syncSourceLabel();

        // Purpose options depend on the row's land use, so they are filled after
        // render — passing the existing purpose so a row that already has a
        // recommendation (or a restored draft row) shows the purpose captured.
        rowsBody.querySelectorAll('.batch-row').forEach(function (tr, i) {
            var child      = children[i] || {};
            var landUseSel = tr.querySelector('.batch-landuse');
            var purposeSel = tr.querySelector('.batch-purpose');
            // A draft remembers which rows were ticked; a fresh load unticks the
            // children that already have a recommendation. Either way an unticked
            // row must come back disabled, or its inputs would still post.
            if (child.checked === false) {
                tr.querySelector('.batch-row-check').checked = false;
            }
            syncRowEnabled(tr);

            // Wait for the purpose list before deciding whether the saved value
            // is Other. Before the async list arrives the select contains only
            // its Select placeholder, which would otherwise hide and clear a
            // saved custom purpose during batch edit.
            if (landUseSel && landUseSel.value) {
                loadPurposes(purposeSel, landUseSel.value, child.purpose_id || null)
                    .then(function () { syncPurposeOther(tr); });
            } else {
                syncPurposeOther(tr);
            }
        });

        // The table is the batch, so the per-file grant store follows it. Values
        // already keyed survive by file number; a draft's — or, on an edit, the
        // saved record's — are passed in as the seed.
        if (perFileOn()) {
            rebuildGrantStore(children.map(function (c) {
                if (!c || !c.grant) return null;
                var g = Object.assign({}, c.grant);
                g.__file = c.file_number;
                return g;
            }).filter(Boolean));
        }

        if (isRegular()) {
            // Row markup is rebuilt from scratch, so the source column has to be
            // hidden again on the new cells.
            rowsBody.querySelectorAll('.batch-src-cell').forEach(function (td) { td.classList.add('hidden'); });
            rowsBody.querySelectorAll('.batch-row').forEach(function (tr) { tr.classList.remove('batch-source-row'); });
            rowsBody.querySelectorAll('.batch-source-badge').forEach(function (b) { b.classList.add('hidden'); });
            // The set decides the type, so re-derive it whenever the set changes.
            applyRegularRecType();
        }

        updateCount();
        if (window.lucide) window.lucide.createIcons();
    }

    // Every child load carries a ticket. A response only paints if its ticket is
    // still the current one — anything else is a load that has been overtaken and
    // whose rows would wipe whatever replaced them. That happens for real: a draft
    // restore writes the mother file number in, and the load that fires off the
    // back of it used to land a moment later and overwrite the whole restored
    // table with blank registry rows.
    var childLoadSeq = 0;

    function loadChildren(mother) {
        var ticket = ++childLoadSeq;

        motherLabel.textContent = mother;
        if (motherField) motherField.value = mother;
        rowsBody.innerHTML = '<tr><td colspan="11" class="px-3 py-10 text-center text-xs text-slate-500">'
            + '<i data-lucide="loader-2" class="h-5 w-5 mx-auto mb-2 text-violet-400 animate-spin"></i>Loading children…</td></tr>';
        if (window.lucide) window.lucide.createIcons();
        setStatus('');

        fetch(CHILDREN_URL + '?mother_file_no=' + encodeURIComponent(mother), { headers: { 'Accept': 'application/json' } })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (ticket !== childLoadSeq) return;
                if (!data.success) {
                    rowsBody.innerHTML = '';
                    setStatus(data.message || 'Could not load children.', 'error');
                    updateCount();
                    return;
                }
                if (!data.children.length) {
                    rowsBody.innerHTML = '<tr><td colspan="11" class="px-3 py-10 text-center text-xs text-slate-400">'
                        + '<i data-lucide="folder-open" class="h-5 w-5 mx-auto mb-2 text-slate-300"></i>'
                        + 'No commissioned subdivision children found for this file.</td></tr>';
                    if (window.lucide) window.lucide.createIcons();
                    setStatus(data.message || 'No subdivision children are linked to ' + mother + '.', 'warn');
                    updateCount();
                    return;
                }

                // Before the rows, not after: renderChildren rebuilds the per-file
                // grant store and takes the card as it stands as the template for
                // every file without values of its own.
                var inherited = applyMotherRecommendation(data.mother_recommendation);

                renderChildren(data.children);

                if (inherited) {
                    setStatus('Grant conditions inherited from the mother file ' + inherited
                        + '. Step through the files to adjust any of them before saving.', 'warn');
                }
            })
            .catch(function () {
                if (ticket !== childLoadSeq) return;
                rowsBody.innerHTML = '';
                setStatus('Network error while loading children.', 'error');
                updateCount();
            });
    }

    /* ── Inheritance from the mother file ────────────────────────────────────
       A subdivision is one grant split into plots, so its children start from the
       mother's recommendation rather than from blank — the same way a Sectional
       Titling unit starts from its primary application.

       The per-file half (term, rents, fees, page details) rides in on each child's
       `grant` and is seeded by renderChildren. This is the batch-wide half: fields
       captured once for the whole batch. Only blanks are filled, so anything the
       officer has already keyed stands, and every value stays editable after.

       Returns the mother's file number when something was actually inherited. */
    function applyMotherRecommendation(mother) {
        if (!mother || isRegular()) return '';

        // Deliberately short. `state` is already defaulted on the form and
        // `street_name` describes one plot rather than the grant, so neither is
        // the mother's to hand down.
        var BATCH_WIDE = ['recommendation', 'premium', 'premium_words'];

        BATCH_WIDE.forEach(function (name) {
            var value = mother[name];
            if (value === undefined || value === null || String(value) === '') return;

            var el = recForm.querySelector('[name="' + name + '"]');
            if (!el || el.disabled || String(el.value).trim() !== '') return;

            el.value = value;
            el.dispatchEvent(new Event('change', { bubbles: true }));
        });

        // Director / Licensed surveyor is a radio pair, so it is set by value
        // rather than written to — and only when neither side is already picked.
        var method = String(mother.rofo_survey_method || '');
        if (method) {
            var radios = recForm.querySelectorAll('[name="rofo_survey_method"]');
            var alreadyPicked = Array.prototype.some.call(radios, function (r) { return r.checked; });
            if (radios.length && !alreadyPicked) {
                Array.prototype.forEach.call(radios, function (r) {
                    if (r.value !== method) return;
                    r.checked = true;
                    r.dispatchEvent(new Event('change', { bubbles: true }));
                });
            }
        }

        // The per-file conditions land on the rows whether or not any batch-wide
        // field was blank, so the message is owed either way.
        return mother.file_number || '';
    }

    // The children as the registry has them right now, without touching the table.
    // Used by a draft restore to backfill blanks and to notice children that were
    // commissioned after the draft was started.
    function fetchChildren(mother) {
        return fetch(CHILDREN_URL + '?mother_file_no=' + encodeURIComponent(mother), { headers: { 'Accept': 'application/json' } })
            .then(function (r) { return r.json(); })
            .then(function (data) { return (data && data.success && data.children) ? data.children : []; })
            .catch(function () { return []; });
    }

    // ── events ──
    toggle.addEventListener('change', function () { applyBatchMode(toggle.checked); });

    // Switching kind mid-capture throws the table away, because the rows of one
    // kind are simply not the rows of the other. Worth a confirm once anything has
    // been loaded — on a long batch that is real work.
    kindRadios.forEach(function (radio) {
        radio.addEventListener('change', function () {
            if (!toggle.checked) return;

            if (rowsBody.querySelectorAll('.batch-row').length
                && !confirm('Switching the kind of batch clears the table and everything keyed into it. Continue?')) {
                // Put the radio back: the change already happened by the time this fires.
                var other = isRegular() ? 'subdivision' : 'regular';
                var back = document.querySelector('.batch-kind-radio[value="' + other + '"]');
                if (back) back.checked = true;
                return;
            }

            rowsBody.innerHTML = '';
            card.classList.add('hidden');
            updateCount();
            setStatus('');
            applyKind(true);
        });
    });

    if (filesApply) filesApply.addEventListener('click', function () {
        var files = pickedFiles();
        if (!files.length) {
            setStatus('Pick at least one file number first.', 'error');
            card.classList.remove('hidden');
            return;
        }
        // Same warning the subdivision Reload gives, for the same reason: this
        // repaints the table and discards whatever was keyed into it.
        if (rowsBody.querySelectorAll('.batch-row').length
            && !confirm('Applying reloads the table from the picked files and discards everything keyed into it. Continue?')) {
            return;
        }
        loadPickedFiles(files);
    });

    if (filesClear) filesClear.addEventListener('click', function () {
        setPickedFiles([]);
        rowsBody.innerHTML = '';
        card.classList.add('hidden');
        updateCount();
        setStatus('');
    });

    document.querySelectorAll('.app-type-radio').forEach(function (radio) {
        radio.addEventListener('change', function () { if (toggle.checked) syncForAppType(); });
    });

    if (oldFileNo) {
        oldFileNo.addEventListener('change', function () {
            // A restore writes the mother in directly; re-fetching the children here
            // would wipe the very values being restored. In a regular batch this
            // field is just the old file number — it says nothing about the rows.
            if (!toggle.checked || suppressChildLoad || isRegular()) return;

            // Picking the mother is the second half of the subdivision flow; picking
            // Plot Subdivision is the first. Returning quietly here is what made a
            // chosen mother look like a broken picker — the children never loaded and
            // nothing on the screen said why.
            if (currentAppType() !== BATCH_TYPE) {
                setStatus('Pick ' + BATCH_TYPE + ' as the Application Type above before choosing the mother file — a subdivision batch covers ' + BATCH_TYPE + ' only. For any other type, switch to Regular files.', 'warn');
                return;
            }

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

    // Apply: the explicit "load this mother's children" gesture, so the batch is
    // never one un-repeatable change event away from working. It also settles the
    // Application Type rather than refusing to go on — storeBatch() writes
    // application_type = Plot Subdivision onto every child of a subdivision batch
    // whatever the radio said, so anything else on screen was never going to be
    // what got saved.
    if (motherApply) {
        motherApply.addEventListener('click', function () {
            var v = motherSel ? motherSel.value.trim() : '';
            if (!v) {
                setStatus('Pick the mother file number first.', 'error');
                return;
            }
            // Same warning Reload and the regular batch's Apply give, for the same
            // reason: this repaints the table and discards whatever was keyed in.
            if (rowsBody.querySelectorAll('.batch-row').length
                && !confirm('Applying reloads the children from this mother file and discards everything keyed into the table. Continue?')) {
                return;
            }

            if (oldFileNo)   oldFileNo.value = v;
            if (motherField) motherField.value = v;

            var forced = false;
            if (currentAppType() !== BATCH_TYPE) {
                if (appToggle && !appToggle.checked) {
                    appToggle.checked = true;
                    appToggle.dispatchEvent(new Event('change', { bubbles: true }));
                }
                var subRadio = document.querySelector('.app-type-radio[value="' + BATCH_TYPE + '"]');
                if (subRadio) {
                    subRadio.checked = true;
                    // Everything that keys off the application type hangs on this event —
                    // including syncForAppType(), which loads the children itself now that
                    // old_file_number is already set above.
                    subRadio.dispatchEvent(new Event('change', { bubbles: true }));
                    forced = true;
                }
            }

            if (!forced) loadChildren(v);

            // After the load: loadChildren() clears the status box on its way in.
            if (forced) {
                setStatus('Application Type set to ' + BATCH_TYPE + ' — a subdivision batch saves every child as ' + BATCH_TYPE + '. Switch to Regular files for any other type.', 'warn');
            }
        });
    }

    if (reloadBtn) {
        reloadBtn.addEventListener('click', function () {
            var v = oldFileNo ? oldFileNo.value.trim() : '';
            if (!v) return;
            // Reload re-fetches from the registry and repaints the table, which
            // throws away everything keyed into it. On a 100-child batch that is
            // an afternoon, so it is worth asking.
            if (rowsBody.querySelectorAll('.batch-row').length
                && !confirm('Reloading fetches the children again and discards everything keyed into the table. Continue?')) {
                return;
            }
            loadChildren(v);
        });
    }

    if (selectAll) {
        selectAll.addEventListener('change', function () {
            commitGrant();
            var on = this.checked;
            if (BATCH_EDIT && !on) {
                var currentChildren = serializeChildren();
                var lockedChildren = currentChildren.filter(function (child) { return !!child.rofo_locked; });
                remainingChildren = remainingChildren.concat(currentChildren.filter(function (child) { return !child.rofo_locked; }).map(function (child) {
                    child.checked = false;
                    child.has_recommendation = false;
                    return child;
                }));
                renderChildren(lockedChildren);
                renderRemaining();
                return;
            }
            rowsBody.querySelectorAll('.batch-row').forEach(function (tr) {
                if (tr.dataset.rofoLocked === '1') return;
                tr.querySelector('.batch-row-check').checked = on;
                syncRowEnabled(tr);
            });
            rebuildGrantStore();
            updateCount();
        });
    }

    // The other half of the mirror: a page number typed into the table itself has
    // to reach the card, but only while the row being typed into is the one the
    // stepper is showing. `input`, not `change`, so it tracks keystroke by
    // keystroke the way the card → row direction does.
    rowsBody.addEventListener('input', function (e) {
        var f = e.target && e.target.dataset ? e.target.dataset.f : null;
        if (!f || !perFileOn()) return;
        if (e.target.closest('tr') !== grantRows()[grantIndex]) return;
        var el = document.querySelector('#conversion-fields-section [data-mirror-f="' + f + '"]');
        if (el && el.value !== e.target.value) el.value = e.target.value;
    });

    rowsBody.addEventListener('change', function (e) {
        if (e.target.classList.contains('batch-row-source')) {
            syncSourceLabel();
            return;
        }
        if (e.target.classList.contains('batch-row-check')) {
            if (e.target.dataset.rofoLocked === '1') {
                e.target.checked = true;
                return;
            }
            // In a saved-batch edit, unticking means remove this recommendation
            // from the batch. Move its current values to Remaining Records now;
            // it will no longer be posted, and can be added again later.
            if (BATCH_EDIT && !e.target.checked) {
                commitGrant();
                var removedFile = e.target.closest('tr').querySelector('input[name$="[file_number]"]').value;
                var currentChildren = serializeChildren();
                var removed = currentChildren.find(function (child) {
                    return childKey(child) === String(removedFile).trim().toUpperCase();
                });
                if (removed) {
                    removed.checked = false;
                    removed.has_recommendation = true;
                    removed.is_batch_hidden = true;
                    newlyAddedBatchFiles.delete(childKey(removed));
                    remainingChildren.push(removed);
                    renderChildren(currentChildren.filter(function (child) {
                        return childKey(child) !== childKey(removed);
                    }));
                    renderRemaining();
                }
                return;
            }
            commitGrant();
            syncRowEnabled(e.target.closest('tr'));
            rebuildGrantStore();
            updateCount();
            return;
        }
        if (e.target.classList.contains('batch-landuse')) {
            var purposeSel = e.target.closest('tr').querySelector('.batch-purpose');
            loadPurposes(purposeSel, e.target.value, null);
            syncPurposeOther(e.target.closest('tr'));
            return;
        }
        if (e.target.classList.contains('batch-purpose')) {
            syncPurposeOther(e.target.closest('tr'));
        }
    });

    if (applyAllBtn) applyAllBtn.addEventListener('click', function () {
        var rows = grantRows();
        if (rows.length < 2) return;

        var src = sourceRow();
        if (!src) return;

        // The page references describe the grant, not the plot, so they are copied
        // over whatever is in the other rows.
        var COPY = ['page', 'page_2', 'page_3'];

        // Applicant name and location are usually shared across a subdivision but
        // not always, so they only fill rows left blank — copying over a filled cell
        // would silently put the wrong name on a letter.
        var FILL_IF_BLANK = ['applicant_name', 'location'];

        // Deliberately copied by nothing: the child file number, applicant address,
        // plot number, land use and purpose. Each identifies or describes that one
        // plot — an address, a land use or a purpose carried across the batch is a
        // wrong letter that reads as a correct one, which is the worst kind.

        rows.forEach(function (tr) {
            if (tr === src) return;

            FILL_IF_BLANK.forEach(function (f) {
                var from = src.querySelector('[data-f="' + f + '"]');
                var dst  = tr.querySelector('[data-f="' + f + '"]');
                if (from && dst && !dst.value.trim()) dst.value = from.value;
            });

            COPY.forEach(function (f) {
                var from = src.querySelector('[data-f="' + f + '"]');
                var dst  = tr.querySelector('[data-f="' + f + '"]');
                if (from && dst) dst.value = from.value;
            });
        });

        // The page numbers this just copied are mirrored on the Page Number card,
        // which would otherwise keep showing the value it had before the copy.
        writeMirrors();

        // The copy is real work, so it is drafted rather than waiting on the next
        // keystroke — 100 rows changed at once is exactly what should not be lost.
        scheduleSave();
    });

    // ── Per-column Apply-to-all (the "never copied" bank) ───────────────────
    // Plot No, Applicant Address, Land Use and Purpose are per-plot by default
    // and the toolbar Apply deliberately never touches them. They do repeat
    // across a whole batch often enough to be worth keying once — one address
    // for a family allocation, one land use and purpose for a whole layout — so
    // each gets its own button. It is opt-in in both senses: nothing happens without the click,
    // and a click that would overwrite values already keyed asks first.
    document.querySelectorAll('.batch-col-apply').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var f     = btn.dataset.f;
            var label = btn.dataset.label || f;

            var rows = grantRows();
            if (rows.length < 2) return;

            var src = sourceRow();
            if (!src) return;

            var from = src.querySelector('[data-f="' + f + '"]');
            if (!from) return;
            var value = from.value;

            // A purpose is scoped to its land use. The previous implementation
            // wrote the purpose value directly into every select and relied on
            // whatever options happened to have loaded already. A select rejects
            // a value that has no matching option, so some rows silently became
            // "Select" (or appeared to offer only Select / Other). Apply Purpose
            // as one atomic operation: align the land use, load that list, then
            // choose the source purpose after its option exists.
            if (f === 'purpose_id') {
                var sourceLandUse = (src.querySelector('[data-f="land_use_id"]') || {}).value;
                var sourceOtherPurpose = (src.querySelector('[data-f="purpose_id_other"]') || {}).value.trim();
                if (!value) {
                    alert('Choose a Purpose on the source row before applying it to the batch.');
                    return;
                }
                if (value === 'other' && !sourceOtherPurpose) {
                    alert('Specify the Other Purpose on the source row before applying it to the batch.');
                    return;
                }
                if (!sourceLandUse) {
                    alert('Choose a Land Use on the source row before applying its Purpose to the batch.');
                    return;
                }

                var purposeTargets = rows.map(function (tr) {
                    return tr === src ? null : {
                        row: tr,
                        purpose: tr.querySelector('[data-f="purpose_id"]'),
                        landUse: tr.querySelector('[data-f="land_use_id"]'),
                        other: tr.querySelector('[data-f="purpose_id_other"]'),
                    };
                }).filter(function (target) {
                    return target && target.purpose && target.landUse && target.other;
                });

                if (!purposeTargets.length) return;

                var purposeClashes = purposeTargets.filter(function (target) {
                    return String(target.purpose.value).trim() !== ''
                        && String(target.purpose.value) !== String(value);
                }).length;
                var landUseChanges = purposeTargets.filter(function (target) {
                    return String(target.landUse.value || '') !== String(sourceLandUse);
                }).length;

                var purposeMessage = 'Copy ' + label + ' from the source row into '
                    + purposeTargets.length + ' row' + (purposeTargets.length === 1 ? '' : 's') + '?';
                if (landUseChanges) {
                    purposeMessage += String.fromCharCode(10, 10) + 'Their Land Use will also be set to the source row\'s Land Use so the selected Purpose is valid.';
                }
                if (purposeClashes) {
                    purposeMessage += String.fromCharCode(10, 10) + purposeClashes + ' existing purpose value'
                        + (purposeClashes === 1 ? ' will' : 's will') + ' be replaced.';
                }
                if ((purposeClashes || landUseChanges) && !confirm(purposeMessage)) return;

                Promise.all(purposeTargets.map(function (target) {
                    target.landUse.value = sourceLandUse;

                    return loadPurposes(target.purpose, sourceLandUse, value).then(function () {
                        var applied = String(target.purpose.value) === String(value);
                        if (applied) {
                            target.other.value = value === 'other' ? sourceOtherPurpose : '';
                            target.purpose.dispatchEvent(new Event('change', { bubbles: true }));
                        }
                        return applied;
                    });
                })).then(function (results) {
                    var failed = results.filter(function (applied) { return !applied; }).length;
                    if (failed) {
                        alert(failed + ' row' + (failed === 1 ? ' could not' : 's could not')
                            + ' receive the selected Purpose because it is not available in the current purpose list.');
                    }
                    scheduleSave();
                });

                return;
            }

            var targets = [];
            rows.forEach(function (tr) {
                if (tr === src) return;
                var dst = tr.querySelector('[data-f="' + f + '"]');
                if (!dst) return;
                targets.push(dst);
            });

            if (!targets.length) {
                alert('Nothing to apply.');
                return;
            }

            var clashes = targets.filter(function (dst) {
                return String(dst.value).trim() !== '' && String(dst.value) !== String(value);
            }).length;

            if (clashes && !confirm(
                'Copy ' + label + ' from the source row into ' + targets.length + ' row' + (targets.length === 1 ? '' : 's') + '?' + String.fromCharCode(10, 10)
                + clashes + ' row' + (clashes === 1 ? ' already holds a different value and it' : 's already hold a different value and they')
                + ' will be overwritten.')) {
                return;
            }

            targets.forEach(function (dst) {
                dst.value = value;
                // A value written straight onto a field does not fire change, and
                // the land use one has a listener behind it — the Purpose options
                // are fetched from it. Fire it so nothing is left stale.
                dst.dispatchEvent(new Event('change', { bubbles: true }));
            });

            // Purpose hangs off land use: its options are fetched for whatever land
            // use the row is on. Changing the land use therefore has to refetch
            // them, and a purpose that does not exist under the new land use is
            // gone — said out loud, because a purpose silently reset to "Select"
            // on twenty rows is exactly the kind of blank that reaches a letter.
            if (f === 'land_use_id') {
                var kept = targets.map(function (dst) {
                    var tr  = dst.closest('tr');
                    var sel = tr.querySelector('.batch-purpose');
                    if (!sel) return Promise.resolve(true);
                    var was = sel.value;
                    return loadPurposes(sel, value, was).then(function () {
                        return !was || sel.value === was;
                    });
                });

                Promise.all(kept).then(function (results) {
                    var lost = results.filter(function (ok) { return !ok; }).length;
                    if (lost) {
                        alert(lost + ' row' + (lost === 1 ? '' : 's') + ' lost '
                            + (lost === 1 ? 'its' : 'their') + ' purpose: it does not exist under '
                            + 'the land use just applied. Re-pick the purpose on '
                            + (lost === 1 ? 'that row' : 'those rows') + ', or use the Purpose '
                            + 'Apply to all above.');
                    }
                    scheduleSave();
                });
                return;
            }

            scheduleSave();
        });
    });

    // ── Draft autosave ─────────────────────────────────────────────────────
    // A subdivision batch can run past a hundred children. Keying that many rows
    // takes longer than the session lasts, and the whole capture used to die on a
    // 419 at submit. Everything is now written to a server-side draft on a
    // debounce, mirrored into local storage, and the periodic save doubles as the
    // thing that keeps the session (and its CSRF token) alive while typing.
    var DRAFT_STORE_URL = '{{ route('land-recommendations.batch-drafts.store') }}';
    var DRAFT_LIST_URL  = '{{ route('land-recommendations.batch-drafts.index') }}';
    var DRAFT_ONE_URL   = '{{ url('land-recommendations/batch-drafts') }}';

    var DEBOUNCE_MS  = 2500;    // quiet period after the last keystroke
    var HEARTBEAT_MS = 60000;   // ceiling on how much typing a crash can cost
    var KEEPALIVE_MS = 300000;  // re-save an idle draft purely to hold the session

    var draftBar     = document.getElementById('batch-draft-bar');
    var draftStatus  = document.getElementById('batch-draft-status');
    var draftKeyInput = document.getElementById('batch-draft-key');
    var expectedInput = document.getElementById('batch-children-expected');
    var draftSaveNow = document.getElementById('batch-draft-save-now');
    var draftResume  = document.getElementById('batch-draft-resume');
    var draftDiscard = document.getElementById('batch-draft-discard');
    var draftList    = document.getElementById('batch-draft-list');
    var draftCount   = document.getElementById('batch-draft-count');
    var draftWarning = document.getElementById('batch-draft-session-warning');
    var draftRetry   = document.getElementById('batch-draft-retry');

    var draftKey     = null;
    var draftDirty   = false;
    var draftSaving  = false;
    var sessionLost  = false;
    var lastSavedAt  = null;
    var debounceTimer = null;
    var restoring    = false;   // suppresses autosave while a draft is painted back
    var suppressChildLoad = false;

    var LS_PREFIX = 'lr-batch-draft:';

    function csrfToken() {
        var meta = document.querySelector('meta[name="csrf-token"]');
        var field = recForm.querySelector('input[name="_token"]');
        return (field && field.value) || (meta && meta.getAttribute('content')) || '';
    }

    // A save hands back a fresh token; writing it into both the form and the meta
    // tag is what keeps the eventual batch POST from 419-ing after a long capture.
    function adoptToken(token) {
        if (!token) return;
        var field = recForm.querySelector('input[name="_token"]');
        if (field) field.value = token;
        var meta = document.querySelector('meta[name="csrf-token"]');
        if (meta) meta.setAttribute('content', token);
    }

    function newDraftKey() {
        if (window.crypto && crypto.randomUUID) return crypto.randomUUID().replace(/-/g, '').slice(0, 32);
        return 'd' + Date.now().toString(36) + Math.random().toString(36).slice(2, 10);
    }

    function setDraftStatus(text, tone) {
        if (!draftStatus) return;
        draftStatus.textContent = text;
        draftStatus.className = 'text-[11px] font-semibold ' + (
            tone === 'error' ? 'text-rose-700'
            : tone === 'ok'  ? 'text-emerald-700'
            : tone === 'busy' ? 'text-violet-700'
            : 'text-slate-500'
        );
    }

    function clockLabel(date) {
        return date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit' });
    }

    // ── what a draft is ──
    // Common fields come off the form by name; children come off the table so
    // unticked rows are kept too (their values are real work — an unticked row is
    // one the user decided against, not one they never filled in).
    // Fields that describe the draft or the post rather than the capture. They are
    // set from the live state on every save and every submit, so carrying stale
    // copies through a restore could only ever point at the wrong draft.
    // batch_source_row is a table control, not a form value — it rides with the
    // children (as is_source) so it survives a re-render, and the batch endpoint
    // ignores it entirely.
    // batch_kind rides with the payload as `kind`, restored before anything else,
    // so it is not replayed through the generic common-field restore either.
    var NOT_CAPTURE = ['_token', '_method', 'draft_key', 'children_expected',
                       'batch_mother_file_no', 'batch_source_row', 'batch_kind', 'batch_kind_ui'];

    function serializeCommon() {
        var out = {};
        Array.prototype.forEach.call(recForm.elements, function (el) {
            var name = el.name;
            if (!name || NOT_CAPTURE.indexOf(name) !== -1) return;
            if (name.indexOf('children[') === 0) return;
            if (el.type === 'file' || el.type === 'submit' || el.type === 'button') return;

            if (el.type === 'checkbox' || el.type === 'radio') {
                out[name] = out[name] || { type: el.type, values: [] };
                if (el.checked) out[name].values.push(el.value);
            } else if (el.multiple) {
                out[name] = { type: 'multi', values: Array.prototype.map.call(el.selectedOptions, function (o) { return o.value; }) };
            } else {
                out[name] = { type: 'value', value: el.value };
            }
        });
        return out;
    }

    // Children are stored in the exact shape the children endpoint returns, so a
    // restore feeds renderChildren() the same objects a live load would.
    function serializeChildren() {
        // Park whatever is on the Grant Conditions stepper first, then key the store
        // by file number so each row picks up its own set below.
        var grantByFile = {};
        if (perFileOn()) {
            serializeGrant().forEach(function (g) {
                if (g && g.__file) grantByFile[g.__file] = g;
            });
        }

        return Array.prototype.map.call(rowsBody.querySelectorAll('.batch-row'), function (tr) {
            function v(f) {
                var el = tr.querySelector('[data-f="' + f + '"]');
                return el ? el.value : '';
            }
            function hid(f) {
                var el = tr.querySelector('input[name$="[' + f + ']"]');
                return el ? el.value : '';
            }
            return {
                file_number:       hid('file_number'),
                tracking_id:       hid('tracking_id'),
                applicant_name:    v('applicant_name'),
                applicant_address: v('applicant_address'),
                plot_number:       v('plot_number'),
                location:          v('location'),
                land_use_id:       v('land_use_id'),
                purpose_id:        v('purpose_id'),
                purpose_id_other:  v('purpose_id_other'),
                page:              v('page'),
                page_2:            v('page_2'),
                page_3:            v('page_3'),
                checked:           tr.querySelector('.batch-row-check').checked,
                // Which row Apply-to-all copies from is the user's choice, so it is
                // part of the capture and comes back with a resumed draft.
                is_source:         !!(tr.querySelector('.batch-row-source') || {}).checked,
                // Carried through so a restored row keeps its "Has a RofO" flag
                // without a second round trip to the children endpoint.
                has_recommendation: tr.querySelector('.batch-row-check').dataset.hadRec === '1',
                rofo_locked:       tr.dataset.rofoLocked === '1',
                existing_status:    tr.querySelector('.batch-row-check').dataset.existingStatus || '',
                is_unknown:         tr.querySelector('.batch-row-check').dataset.unknown === '1',
                // Regular batch only — the conditions keyed against this file on the
                // Grant Conditions stepper. Absent for a subdivision, whose children
                // all share the one set held in the card itself.
                grant:              grantByFile[hid('file_number')] || null
            };
        });
    }

    function buildPayload() {
        return {
            version:          1,
            // Identifies a snapshot taken while editing an existing batch. This
            // marker prevents it ever being resumed as a fresh recommendation.
            edit_batch_id:    BATCH_EDIT ? BATCH_EDIT.batch_id : null,
            // Which kind of batch this draft is, and — for a regular one — the files
            // that were picked, so resuming restores the picker as well as the table.
            kind:             currentKind(),
            picked_files:     isRegular() ? pickedFiles() : [],
            application_type: currentAppType(),
            mother_file_no:   isRegular() ? '' : (oldFileNo ? oldFileNo.value.trim() : ''),
            common:           serializeCommon(),
            children:         serializeChildren(),
            remaining_children: BATCH_EDIT ? remainingChildren.slice() : [],
            saved_at:         new Date().toISOString()
        };
    }

    // Local storage is the backstop for the one case the server cannot cover: the
    // session is gone, so no request of ours will be accepted at all. It is never
    // the source of truth — the server copy wins whenever there is one.
    function writeLocal(payload) {
        if (!draftKey) return;
        try {
            localStorage.setItem(LS_PREFIX + draftKey, JSON.stringify({
                draft_key: draftKey,
                payload:   payload,
                pushed:    false,
                stored_at: new Date().toISOString()
            }));
        } catch (e) { /* quota or private mode — the server copy still stands */ }
    }

    function markLocalPushed() {
        if (!draftKey) return;
        try {
            var raw = localStorage.getItem(LS_PREFIX + draftKey);
            if (!raw) return;
            var obj = JSON.parse(raw);
            obj.pushed = true;
            localStorage.setItem(LS_PREFIX + draftKey, JSON.stringify(obj));
        } catch (e) { /* nothing to do */ }
    }

    function clearLocal(key) {
        try { localStorage.removeItem(LS_PREFIX + (key || draftKey)); } catch (e) { /* ignore */ }
    }

    function showSessionWarning(on) {
        sessionLost = on;
        if (draftWarning) draftWarning.classList.toggle('hidden', !on);
    }

    function saveDraft(reason) {
        if (restoring || !toggle.checked) return Promise.resolve();
        if (draftSaving) { draftDirty = true; return Promise.resolve(); }

        // Nothing worth a row yet: nothing picked and nothing on screen. A regular
        // batch counts its picked files here — they are a choice already made, and
        // losing them to a timeout before Apply is pressed is still lost work.
        var childRows = rowsBody.querySelectorAll('.batch-row').length;
        var mother = isRegular() ? '' : (oldFileNo ? oldFileNo.value.trim() : '');
        var picked = isRegular() ? pickedFiles().length : 0;
        if (!draftKey && !mother && !childRows && !picked) return Promise.resolve();

        if (!draftKey) {
            draftKey = BATCH_EDIT ? BATCH_EDIT.draft_key : newDraftKey();
            if (draftKeyInput) draftKeyInput.value = draftKey;
            if (draftDiscard) draftDiscard.classList.remove('hidden');
        }

        var payload = buildPayload();
        writeLocal(payload);

        draftSaving = true;
        draftDirty = false;
        setDraftStatus('Saving draft…', 'busy');

        var body = new FormData();
        body.append('_token', csrfToken());
        body.append('draft_key', draftKey);
        body.append('application_type', BATCH_EDIT ? '__edit_batch__' : (payload.application_type || ''));
        body.append('mother_file_no', payload.mother_file_no || '');
        body.append('children_total', String(payload.children.length));
        body.append('children_selected', String(payload.children.filter(function (c) { return c.checked; }).length));
        // Posted as one JSON string, not as nested form fields: a 100-child batch
        // is thousands of inputs and PHP silently drops everything past
        // max_input_vars (1000 by default), which would truncate the draft.
        body.append('payload', JSON.stringify(payload));

        return fetch(DRAFT_STORE_URL, {
            method: 'POST',
            body: body,
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
        })
            .then(function (res) {
                // 419 (token expired) and 401/403 (logged out) both mean the same
                // thing to the user: the server will not take this work until they
                // sign in again. The tab and local storage still hold all of it.
                if (res.status === 419 || res.status === 401 || res.status === 403 || res.redirected) {
                    throw { sessionLost: true };
                }
                if (!res.ok) throw { status: res.status };
                return res.json();
            })
            .then(function (data) {
                draftSaving = false;
                if (!data || !data.success) throw { status: 'payload' };

                adoptToken(data.csrf);
                markLocalPushed();
                showSessionWarning(false);
                lastSavedAt = new Date();
                setDraftStatus('Draft saved ' + clockLabel(lastSavedAt)
                    + ' · ' + (data.draft ? data.draft.children_selected + ' of ' + data.draft.children_total + ' children' : ''), 'ok');

                // Anything typed while the request was in flight.
                if (draftDirty) scheduleSave();
            })
            .catch(function (err) {
                draftSaving = false;
                draftDirty = true;   // still unsaved, so keep trying

                if (err && err.sessionLost) {
                    showSessionWarning(true);
                    setDraftStatus('Not saved — session expired. Your work is still here.', 'error');
                    return;
                }
                setDraftStatus('Could not reach the server — retrying. Nothing lost.', 'error');
            });
    }

    function scheduleSave() {
        draftDirty = true;
        if (debounceTimer) clearTimeout(debounceTimer);
        debounceTimer = setTimeout(function () { saveDraft('debounce'); }, DEBOUNCE_MS);
        if (!lastSavedAt) setDraftStatus('Unsaved changes…', 'busy');
    }

    // Typing anywhere in the form (common fields or the children table) drafts it.
    // Capture phase, so it still fires for the table rows that are re-rendered.
    ['input', 'change'].forEach(function (evt) {
        recForm.addEventListener(evt, function (e) {
            if (restoring || !toggle.checked) return;
            if (e.target && (e.target.name === '_token' || e.target.id === 'batch-mode-toggle')) return;
            scheduleSave();
        }, true);
    });

    // Backstop for a browser or machine that dies between debounces, and the thing
    // that keeps the session alive across a long capture — an idle draft is
    // re-saved every few minutes purely so the session never ages out under it.
    setInterval(function () {
        if (!toggle.checked || restoring) return;
        if (draftDirty) { saveDraft('heartbeat'); }
    }, HEARTBEAT_MS);

    setInterval(function () {
        if (!toggle.checked || restoring || draftDirty || !draftKey) return;
        saveDraft('keepalive');
    }, KEEPALIVE_MS);

    if (draftSaveNow) draftSaveNow.addEventListener('click', function () { saveDraft('manual'); });
    if (draftRetry) draftRetry.addEventListener('click', function (e) {
        e.preventDefault();
        setDraftStatus('Retrying…', 'busy');
        saveDraft('retry');
    });

    if (draftDiscard) draftDiscard.addEventListener('click', function () {
        if (!draftKey) return;
        if (!confirm('Discard this draft? Everything keyed in this batch will be thrown away.')) return;

        var body = new FormData();
        body.append('_token', csrfToken());
        body.append('_method', 'DELETE');

        fetch(DRAFT_ONE_URL + '/' + encodeURIComponent(draftKey), {
            method: 'POST', body: body, credentials: 'same-origin',
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
        }).finally(function () {
            clearLocal(draftKey);
            draftKey = null;
            if (draftKeyInput) draftKeyInput.value = '';
            draftDiscard.classList.add('hidden');
            setDraftStatus('Draft discarded.', 'muted');
        });
    });

    // ── resuming ──
    function loadDraftList() {
        if (!draftList) return;
        draftList.innerHTML = '<div class="px-3 py-3 text-[11px] text-slate-500">Loading drafts…</div>';
        draftList.classList.remove('hidden');

        fetch(DRAFT_LIST_URL, {
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
        })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                var drafts = (data && data.drafts) || [];
                if (draftCount) {
                    draftCount.textContent = drafts.length;
                    draftCount.classList.toggle('hidden', !drafts.length);
                }
                if (!drafts.length) {
                    draftList.innerHTML = '<div class="px-3 py-3 text-[11px] text-slate-500">No saved drafts.</div>';
                    return;
                }
                draftList.innerHTML = drafts.map(function (d) {
                    return '<div class="flex items-center gap-3 px-3 py-2">'
                        + '<div class="min-w-0">'
                        {{-- A regular batch has no mother file to name it by, so it is
                             labelled by what it is rather than by an absence. --}}
                        +   '<div class="font-mono text-xs font-bold text-slate-900 truncate">' + esc(d.mother_file_no || 'Regular files') + '</div>'
                        +   '<div class="text-[10px] text-slate-500">' + d.children_selected + ' of ' + d.children_total
                        +     ' children · saved ' + esc(d.last_saved_human || '') + '</div>'
                        + '</div>'
                        + '<div class="ml-auto flex items-center gap-1.5">'
                        +   '<button type="button" class="batch-draft-open px-2 py-1 text-[10px] font-bold bg-violet-600 text-white rounded hover:bg-violet-700" data-key="' + esc(d.draft_key) + '">Resume</button>'
                        // Offered only when a save actually lost rows, which is the
                        // one case going back a version is the right move.
                        +   (d.has_previous
                                ? '<button type="button" class="batch-draft-prev px-2 py-1 text-[10px] font-semibold border border-amber-300 text-amber-700 rounded hover:bg-amber-50" data-key="' + esc(d.draft_key) + '"'
                                    + ' title="Go back to the copy saved ' + esc(d.previous_saved_human || '') + ', which had ' + (d.previous_children_total || 0) + ' children">Earlier version</button>'
                                : '')
                        +   '<button type="button" class="batch-draft-drop px-2 py-1 text-[10px] font-semibold border border-rose-300 text-rose-700 rounded hover:bg-rose-50" data-key="' + esc(d.draft_key) + '">Delete</button>'
                        + '</div>'
                        + '</div>';
                }).join('');
            })
            .catch(function () {
                draftList.innerHTML = '<div class="px-3 py-3 text-[11px] text-rose-700">Could not load drafts.</div>';
            });
    }

    if (draftResume) draftResume.addEventListener('click', function () {
        if (draftList && !draftList.classList.contains('hidden') && draftList.innerHTML.indexOf('Loading') === -1) {
            draftList.classList.add('hidden');
            return;
        }
        loadDraftList();
    });

    if (draftList) draftList.addEventListener('click', function (e) {
        var open = e.target.closest('.batch-draft-open');
        var prev = e.target.closest('.batch-draft-prev');
        var drop = e.target.closest('.batch-draft-drop');

        if (open || prev) {
            if (draftDirty && !confirm('Resuming replaces what is on screen. Continue?')) return;
            restoreDraft((open || prev).dataset.key, !!prev);
            draftList.classList.add('hidden');
            return;
        }
        if (drop) {
            if (!confirm('Delete this draft?')) return;
            var body = new FormData();
            body.append('_token', csrfToken());
            body.append('_method', 'DELETE');
            fetch(DRAFT_ONE_URL + '/' + encodeURIComponent(drop.dataset.key), {
                method: 'POST', body: body, credentials: 'same-origin',
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
            }).finally(function () { clearLocal(drop.dataset.key); loadDraftList(); });
        }
    });

    function restoreDraft(key, wantPrevious) {
        setDraftStatus(wantPrevious ? 'Loading the earlier version…' : 'Loading draft…', 'busy');

        fetch(DRAFT_ONE_URL + '/' + encodeURIComponent(key) + (wantPrevious ? '?previous=1' : ''), {
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
        })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (!data || !data.success) throw new Error('missing');
                applyDraftPayload(key, (data.draft && data.draft.payload) || {});
            })
            .catch(function () {
                // The local mirror only ever holds the current version, so it is no
                // answer to a request for the earlier one.
                if (wantPrevious) {
                    setDraftStatus('That draft has no earlier version to go back to.', 'error');
                    return;
                }

                // Fall back to the copy this browser kept — the reason it exists.
                var local = null;
                try { local = JSON.parse(localStorage.getItem(LS_PREFIX + key) || 'null'); } catch (e) { /* ignore */ }
                if (local && local.payload) {
                    applyDraftPayload(key, local.payload);
                    setDraftStatus('Restored from this browser — save the draft to push it back.', 'error');
                    draftDirty = true;
                    return;
                }
                setDraftStatus('Could not load that draft.', 'error');
            });
    }

    function applyDraftPayload(key, payload) {
        // Held for the whole restore, not just the part that writes the mother
        // file number. Restoring the common fields dispatches a change on
        // Application Type and on Old File Number, and both of those are wired to
        // reload the children — which is exactly what used to land a second later
        // and overwrite every restored row with blank registry values.
        restoring = true;
        suppressChildLoad = true;
        // Retire any child load already in flight, so one started before the
        // restore cannot paint over it either.
        childLoadSeq++;

        draftKey = key;
        if (draftKeyInput) draftKeyInput.value = key;
        if (draftDiscard) draftDiscard.classList.remove('hidden');

        var kind   = payload.kind === 'regular' ? 'regular' : 'subdivision';
        var mother = kind === 'regular' ? '' : (payload.mother_file_no || '');

        try {
            // The kind decides which sections are on screen and which are stood
            // down, so it is set before batch mode paints any of them.
            var kindRadio = document.querySelector('.batch-kind-radio[value="' + kind + '"]');
            if (kindRadio) kindRadio.checked = true;

            // Batch mode first: it relocates sections and stands the per-child
            // fields down, so restoring values before it would fight with it.
            if (!toggle.checked) {
                toggle.checked = true;
                applyBatchMode(true);
            } else {
                applyKind(true);
            }

            restoreCommon(payload.common || {});

            if (kind === 'regular') {
                initFileSelect();
                // Fall back to the file numbers in the table when the draft pre-dates
                // picked_files, so an older regular draft still restores its picker.
                setPickedFiles(payload.picked_files && payload.picked_files.length
                    ? payload.picked_files
                    : (payload.children || []).map(function (c) { return c.file_number; }));
            }

            if (oldFileNo) oldFileNo.value = mother;
            if (motherField) motherField.value = mother;
            motherLabel.textContent = mother || '—';
            if (mother && motherSel && window.jQuery && jQuery.fn.select2) {
                jQuery(motherSel).append(new Option(mother, mother, true, true)).trigger('change.select2');
            } else if (mother && motherSel) {
                if (!motherSel.querySelector('option[value="' + mother.replace(/"/g, '\\"') + '"]')) {
                    motherSel.appendChild(new Option(mother, mother, true, true));
                }
                motherSel.value = mother;
            }

            if (card) card.classList.remove('hidden');
            // Painted immediately from the draft alone, so the work is on screen
            // without waiting on the registry.
            renderChildren(payload.children || []);
            setStatus('');
        } catch (e) {
            restoring = false;
            suppressChildLoad = false;
            setDraftStatus('Draft could not be restored cleanly.', 'error');
            throw e;
        }

        lastSavedAt = new Date();
        setDraftStatus('Draft restored — backfilling from the registry…', 'busy');
        if (window.lucide) window.lucide.createIcons();

        // Autosave must not resume until the backfill has settled, or a save could
        // capture the half-merged table. The timer is the safety net: if the
        // registry never answers, autosave still comes back on.
        var released = false;
        function releaseRestore(message, tone) {
            if (released) return;
            released = true;
            restoring = false;
            suppressChildLoad = false;
            setDraftStatus(message, tone);
        }
        setTimeout(function () { releaseRestore('Draft restored — autosave is on.', 'ok'); }, 15000);

        // Where the registry copy comes from is the one thing the two kinds do
        // differently on restore: a subdivision re-reads the mother's children, a
        // regular batch re-reads the files that were picked.
        var freshRows;
        if (kind === 'regular') {
            var files = (payload.children || []).map(function (c) { return c.file_number; }).filter(Boolean);
            if (!files.length) {
                releaseRestore('Draft restored — autosave is on.', 'ok');
                return;
            }
            freshRows = fetchFileDetails(files).then(function (r) { return r.success ? r.children : []; });
        } else {
            if (!mother) {
                releaseRestore('Draft restored — autosave is on.', 'ok');
                return;
            }
            freshRows = fetchChildren(mother);
        }

        // Backfill: the draft is authoritative for everything the user keyed, and
        // the registry fills the gaps around it — blank cells, plus any child
        // commissioned since the draft was started.
        freshRows.then(function (fresh) {
            var merged = mergeDraftWithRegistry(payload.children || [], fresh);
            renderChildren(merged.children);
            releaseRestore(
                'Draft restored' + (merged.filled ? ' · ' + merged.filled + ' blank field(s) backfilled' : '')
                    + (merged.added ? ' · ' + merged.added + ' new child file(s) added' : '')
                    + ' — autosave is on.',
                'ok'
            );
            // The merge is real work the draft does not yet hold, so it is pushed
            // back rather than waiting for the next keystroke.
            if (merged.filled || merged.added) saveDraft('post-restore-backfill');
        });
    }

    // Draft wins over registry, field by field: anything the user keyed stays
    // exactly as they left it, and only a blank takes the registry's value. Rows
    // are keyed by child file number, so registry order changing between sessions
    // cannot shuffle anybody's work onto the wrong plot.
    function mergeDraftWithRegistry(draftChildren, freshChildren) {
        var FIELDS = ['applicant_name', 'applicant_address', 'plot_number', 'location',
                      'land_use_id', 'purpose_id', 'page', 'page_2', 'page_3', 'tracking_id'];

        var freshByFile = {};
        freshChildren.forEach(function (c) { freshByFile[String(c.file_number).trim()] = c; });

        var filled = 0;
        var seen = {};

        var out = draftChildren.map(function (row) {
            var merged = {};
            Object.keys(row).forEach(function (k) { merged[k] = row[k]; });

            var fresh = freshByFile[String(row.file_number).trim()];
            seen[String(row.file_number).trim()] = true;
            if (!fresh) return merged;

            FIELDS.forEach(function (f) {
                var have = merged[f];
                var isBlank = have === '' || have === null || have === undefined;
                var incoming = fresh[f];
                if (isBlank && incoming !== '' && incoming !== null && incoming !== undefined) {
                    merged[f] = incoming;
                    filled++;
                }
            });

            // Whether a child already carries a recommendation is the registry's
            // call, never the draft's — one may have been captured elsewhere while
            // this draft sat waiting.
            merged.has_recommendation = !!fresh.has_recommendation;
            merged.existing_status = fresh.existing_status || '';
            // Whether anything is on file is the register's call too — a number that
            // was unknown when the draft was started may have been indexed since.
            merged.is_unknown = !!fresh.is_unknown;
            if (merged.has_recommendation) merged.checked = false;

            return merged;
        });

        // Children commissioned after the draft was started. Appended at the end
        // and left ticked, so they are visible as new rather than folded silently
        // into the middle of rows the user has already worked through.
        var added = 0;
        freshChildren.forEach(function (c) {
            if (seen[String(c.file_number).trim()]) return;
            var row = {};
            Object.keys(c).forEach(function (k) { row[k] = c[k]; });
            row.checked = !c.has_recommendation;
            out.push(row);
            added++;
        });

        return { children: out, filled: filled, added: added };
    }

    function restoreCommon(common) {
        Object.keys(common).forEach(function (name) {
            if (NOT_CAPTURE.indexOf(name) !== -1) return;
            var entry = common[name];
            var nodes = recForm.querySelectorAll('[name="' + name.replace(/"/g, '\\"') + '"]');
            if (!nodes.length) return;

            if (entry.type === 'checkbox' || entry.type === 'radio') {
                Array.prototype.forEach.call(nodes, function (el) {
                    var want = entry.values.indexOf(el.value) !== -1;
                    if (el.checked !== want) {
                        el.checked = want;
                        el.dispatchEvent(new Event('change', { bubbles: true }));
                    }
                });
                return;
            }
            if (entry.type === 'multi') {
                Array.prototype.forEach.call(nodes[0].options, function (o) {
                    o.selected = entry.values.indexOf(o.value) !== -1;
                });
                nodes[0].dispatchEvent(new Event('change', { bubbles: true }));
                return;
            }

            var el = nodes[0];
            // flatpickr wraps every date input on this layout; writing .value on one
            // leaves the visible field showing the old date (see admin/header).
            if (el._flatpickr) {
                el._flatpickr.setDate(entry.value || null, false);
            } else {
                el.value = entry.value;
            }
            el.dispatchEvent(new Event('change', { bubbles: true }));
        });

        // Select2 fields submit through a hidden input, so the value is already
        // back — but the visible picker has no option for it and would read blank.
        if (window.jQuery && jQuery.fn && jQuery.fn.select2) {
            [['#district', '#district_select'], ['#lga', '#lga_select'],
             ['#street_name', '#street_name_select'], ['#layout_plan_no', null]].forEach(function (pair) {
                var hidden = document.querySelector(pair[0]);
                if (!hidden) return;
                var val = pair[1] ? hidden.value : hidden.value;
                var sel = pair[1] ? document.querySelector(pair[1]) : hidden;
                if (!sel || !val) return;
                jQuery(sel).append(new Option(val, val, true, true)).trigger('change.select2');
            });
        }
    }

    // Draft count for the Resume button, plus a sweep of local copies whose draft
    // was submitted or deleted elsewhere, so this browser does not accumulate dead
    // payloads. Runs the first time batch mode is opened rather than on every form
    // load — the single-record form has no use for either.
    var draftBootstrapped = false;
    function bootstrapDrafts() {
        if (draftBootstrapped) return;
        draftBootstrapped = true;

        fetch(DRAFT_LIST_URL, {
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
        })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                var live = ((data && data.drafts) || []).map(function (d) { return d.draft_key; });
                if (draftCount) {
                    draftCount.textContent = live.length;
                    draftCount.classList.toggle('hidden', !live.length);
                }
                for (var i = localStorage.length - 1; i >= 0; i--) {
                    var k = localStorage.key(i);
                    if (!k || k.indexOf(LS_PREFIX) !== 0) continue;
                    var thisKey = k.slice(LS_PREFIX.length);
                    if (live.indexOf(thisKey) === -1 && thisKey !== draftKey) localStorage.removeItem(k);
                }
            })
            .catch(function () { /* offline — leave local copies exactly where they are */ });
    }

    // A draft can be opened straight from a link (?draft=KEY), which is how the
    // "Resume" action on the RofO table hands one back to this form.
    (function () {
        if (BATCH_EDIT) return;
        var params = new URLSearchParams(window.location.search);
        var key = params.get('draft');
        if (!key) return;
        bootstrapDrafts();
        restoreDraft(key);
    })();

    // An edit is not drafted, so draftDirty never rises for one. It still has
    // unsaved work to lose, so it keeps its own flag for the unload guard.
    var editDirty = false;
    if (BATCH_EDIT) {
        ['input', 'change'].forEach(function (evt) {
            recForm.addEventListener(evt, function (e) {
                // Painting the batch back onto the form fires change on every field
                // it writes (writeGrantCard does so deliberately, to keep the derived
                // wording in step). That is the page loading, not the user typing.
                if (restoring) return;
                if (e.target && e.target.name === '_token') return;
                editDirty = true;
            }, true);
        });
    }

    // Leaving with work the server has not accepted is the one thing this feature
    // exists to prevent, so it is worth the browser's confirm dialog.
    window.addEventListener('beforeunload', function (e) {
        if (!toggle.checked) return;
        if (!draftDirty && !sessionLost && !editDirty) return;
        e.preventDefault();
        e.returnValue = '';
    });

    recForm.addEventListener('submit', function (e) {
        if (!toggle.checked) return;

        // Kept in step here as well as on every kind change: this is the value the
        // server branches on, and it must match what is actually on screen.
        if (kindInput) kindInput.value = currentKind();

        if (!isRegular()) {
            if (currentAppType() !== BATCH_TYPE) {
                e.preventDefault(); e.stopPropagation();
                setStatus('A subdivision batch covers ' + BATCH_TYPE + ' only — pick that type, or switch to Regular files.', 'error');
                return;
            }
            if (!oldFileNo || !oldFileNo.value.trim()) {
                e.preventDefault(); e.stopPropagation();
                setStatus('Select the subdivided (mother) file first.', 'error');
                card.classList.remove('hidden');
                if (motherSel) motherSel.focus();
                return;
            }
        } else if (!rowsBody.querySelectorAll('.batch-row').length) {
            e.preventDefault(); e.stopPropagation();
            setStatus('Pick the file numbers and press Apply to load them into the table first.', 'error');
            if (filesPicker) filesPicker.scrollIntoView({ behavior: 'smooth', block: 'center' });
            return;
        }

        if (!rowsBody.querySelectorAll('.batch-row-check:checked').length) {
            e.preventDefault(); e.stopPropagation();
            setStatus('Tick at least one child to save a batch.', 'error');
            card.scrollIntoView({ behavior: 'smooth', block: 'center' });
            return;
        }

        if (!prepareSelectedBatchRows()) {
            e.preventDefault(); e.stopPropagation();
            return;
        }

        // One Recommendation Type is saved for every file in the batch, so a mixed
        // set would print the wrong document for one half of it. Blocked rather
        // than warned: nothing on the saved record would show which half was wrong.
        if (isRegular()) {
            applyRegularRecType();
            if (regularTypeMixed) {
                e.preventDefault(); e.stopPropagation();
                setStatus('This batch mixes conversion and non-conversion files — see Recommendation Type. '
                    + 'Split them into two batches and save each on its own.', 'error');
                var recBlock = document.getElementById('recommendation-type-block');
                (recBlock || card).scrollIntoView({ behavior: 'smooth', block: 'center' });
                return;
            }
        }

        // Grant conditions are per file in a regular batch, and only the file
        // currently on the stepper is in the DOM — the rest have to be written out
        // as children[i][…] before the post or they would be lost.
        syncGrantInputs();
    });

    // Last write before the page goes away. Registered after the validation
    // handler above so it is skipped when that one blocks the submit.
    //
    // sendBeacon rather than fetch: the browser is about to navigate, and a normal
    // request in flight at that moment is cancelled. This is the save that matters
    // most — if the POST itself comes back 419, this draft is exactly what was on
    // screen when the user pressed Save.
    recForm.addEventListener('submit', function (e) {
        if (!toggle.checked || e.defaultPrevented) return;

        // Declare the row count so the server can tell a short POST from a short
        // batch — see the note on #batch-children-expected.
        if (expectedInput) {
            expectedInput.value = String(rowsBody.querySelectorAll('.batch-row-check:checked').length);
        }

        if (draftKey && navigator.sendBeacon) {
            var payload = buildPayload();
            writeLocal(payload);

            var body = new FormData();
            body.append('_token', csrfToken());
            body.append('draft_key', draftKey);
            body.append('application_type', payload.application_type || '');
            body.append('mother_file_no', payload.mother_file_no || '');
            body.append('children_total', String(payload.children.length));
            body.append('children_selected', String(payload.children.filter(function (c) { return c.checked; }).length));
            body.append('payload', JSON.stringify(payload));
            navigator.sendBeacon(DRAFT_STORE_URL, body);
        }

        // The submit is the intended way off this page, so the unload guard stands
        // down — storeBatch() closes the draft out once the rows are committed.
        draftDirty = false;
        sessionLost = false;
        editDirty = false;
    });

    // ── Editing a saved batch ──────────────────────────────────────────────
    // Paint the batch back onto the capture screen. Everything here runs once,
    // last, so it lands on top of the state applyBatchMode()/applyKind() leave
    // behind rather than being overwritten by it.
    //
    // The registry is never consulted: these rows are saved recommendations, and
    // a backfill would put registry values back over details that were keyed by
    // hand when the batch was captured. suppressChildLoad therefore stays on for
    // good — it is what stops the mother-file listener from re-loading children
    // over the table.
    if (BATCH_EDIT) (function () {
        suppressChildLoad = true;
        restoring = true;

        // An edit draft belongs only to this batch and this officer. Unlike a
        // capture draft it restores directly here, never through the Resume list.
        draftKey = BATCH_EDIT.draft_key || null;
        if (draftKeyInput) draftKeyInput.value = draftKey || '';
        if (draftResume) draftResume.classList.add('hidden');
        if (draftDiscard) draftDiscard.classList.add('hidden');
        if (draftSaveNow) draftSaveNow.innerHTML = '<i data-lucide="save" class="h-3.5 w-3.5"></i> Save edit draft';

        var kindRadio = document.querySelector('.batch-kind-radio[value="' + BATCH_EDIT.kind + '"]');
        if (kindRadio) kindRadio.checked = true;

        toggle.checked = true;
        applyBatchMode(true);

        // Neither the mode nor the kind is a choice once a batch exists: changing
        // either would mean a different set of files, which is a new batch.
        toggle.disabled = true;
        kindRadios.forEach(function (r) { r.disabled = true; });
        if (kindRow)    kindRow.classList.add('hidden');
        if (hint)       hint.classList.add('hidden');
        // Reload re-reads the mother's children from the registry — on an edit
        // that would discard every saved value in the table.
        if (reloadBtn)  reloadBtn.classList.add('hidden');
        // The mother is what the batch is grouped under, so it is shown and not
        // editable. A different mother is a different batch.
        if (motherPick) motherPick.classList.add('hidden');
        if (motherSel)  motherSel.disabled = true;

        var mother = BATCH_EDIT.mother_file_no || '';
        if (oldFileNo)   oldFileNo.value = mother;
        if (motherField) motherField.value = mother;
        if (motherLabel) motherLabel.textContent = mother || '—';

        if (BATCH_EDIT.kind === 'regular') {
            // The picker still works: files can be added to a saved batch, and
            // batchFileDetails is told which batch this is so its own members do
            // not come back flagged as files that already have a recommendation.
            initFileSelect();
            setPickedFiles(BATCH_EDIT.picked_files || []);
        }

        if (card) card.classList.remove('hidden');
        // Keep the two edit tables as one unit.  The Remaining Records list is
        // always immediately before the saved batch table, even if another form
        // script has rearranged the surrounding sections while restoring the page.
        if (remainingCard && card && card.parentNode) {
            card.parentNode.insertBefore(remainingCard, card);
        }
        if (transferArrows && card && card.parentNode) {
            card.parentNode.insertBefore(transferArrows, card);
        }
        if (transferArrows) transferArrows.classList.remove('hidden');
        if (cardTitle) cardTitle.textContent = 'Saved Batch Records';
        var editDraftPayload = BATCH_EDIT.draft_payload || null;
        if (editDraftPayload) restoreCommon(editDraftPayload.common || {});

        // A draft is an officer's unsaved changes, not a snapshot allowed to
        // replace the batch.  Older edit drafts could contain only the rows that
        // were visible when the page was first opened (for example, generated
        // rows), which made later pending-approval rows disappear from Saved Batch
        // Records.  Start with every current server member, layer draft values over
        // matching files, then retain genuinely new draft rows.
        var serverBatchChildren = Array.isArray(BATCH_EDIT.children) ? BATCH_EDIT.children : [];
        var draftBatchChildren = editDraftPayload && Array.isArray(editDraftPayload.children)
            ? editDraftPayload.children : [];
        var draftChildrenByFile = new Map(draftBatchChildren.map(function (child) {
            return [childKey(child), child];
        }));
        var initialBatchChildren = serverBatchChildren.map(function (child) {
            return draftChildrenByFile.get(childKey(child)) || child;
        });
        draftBatchChildren.forEach(function (child) {
            if (!serverBatchChildren.some(function (serverChild) {
                return childKey(serverChild) === childKey(child);
            })) {
                initialBatchChildren.push(child);
            }
        });
        renderChildren(initialBatchChildren);
        // These are recommendations the officer previously returned from the
        // lower table. They belong in Remaining Files and can be added back.
        remainingChildren = (editDraftPayload && editDraftPayload.remaining_children)
            ? editDraftPayload.remaining_children.slice()
            : (BATCH_EDIT.hidden_children || []).slice();
        setStatus('');

        // Fetch the mother's full lineage once, then place every eligible child
        // that is not already saved in this batch into the upper table. A child
        // recommended elsewhere is deliberately kept visible and locked: this
        // makes the upper and lower counts reconcile to the full lineage without
        // permitting a duplicate recommendation.
        var savedKeys = initialBatchChildren.map(childKey);
        fetch(CHILDREN_URL + '?mother_file_no=' + encodeURIComponent(mother)
            + '&exclude_batch_id=' + encodeURIComponent(BATCH_EDIT.batch_id), {
                headers: { 'Accept': 'application/json' }, credentials: 'same-origin'
            })
            .then(function (response) { return response.json(); })
            .then(function (data) {
                if (!data.success) return;
                var fetchedRemaining = (data.children || []).filter(function (child) {
                    return savedKeys.indexOf(childKey(child)) === -1;
                });
                var locallyReturned = new Map(remainingChildren.map(function (child) {
                    return [childKey(child), child];
                }));
                fetchedRemaining = fetchedRemaining.map(function (child) {
                    return locallyReturned.get(childKey(child)) || child;
                });
                // A fast user may already have moved a lower row upward while
                // this lineage request was in flight. Preserve that local move.
                remainingChildren.forEach(function (child) {
                    if (!fetchedRemaining.some(function (candidate) { return childKey(candidate) === childKey(child); })) {
                        fetchedRemaining.push(child);
                    }
                });
                remainingChildren = fetchedRemaining;
                renderRemaining();
            })
            .catch(function () {
                // Keep locally returned recommendations visible even if the
                // optional lineage lookup is unavailable.
                renderRemaining();
            });

        restoring = false;
        if (editDraftPayload) {
            lastSavedAt = new Date();
            setDraftStatus('Edit draft restored — save changes when ready.', 'ok');
        } else {
            setDraftStatus('Changes are saved as an edit draft automatically.', 'muted');
        }
        if (window.lucide) window.lucide.createIcons();
    })();

    // A 900+ child batch can take long enough to save that a second click is an
    // easy mistake. This listener is registered after the validation handlers,
    // so a blocked/invalid submit keeps the button usable; a valid batch edit
    // immediately shows progress and can only be posted once.
    if (BATCH_EDIT) {
        var batchSaveButton = document.getElementById('batch-save-button');
        var batchSaveStarted = false;
        recForm.addEventListener('submit', function (e) {
            if (e.defaultPrevented || batchSaveStarted || !batchSaveButton) return;

            batchSaveStarted = true;
            batchSaveButton.disabled = true;
            var label = batchSaveButton.querySelector('.save-button-label');
            var loading = batchSaveButton.querySelector('.save-button-loading');
            if (label) label.classList.add('hidden');
            if (loading) loading.classList.remove('hidden');
            if (loading) loading.classList.add('inline-flex');
        });
    }
});
</script>
@endif
@if($isEdit && !$batchEdit)
<script>
document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('land-recommendation-form');
    const button = document.getElementById('recommendation-save-draft');
    const draftKey = @json($editDraftKey ?? null);
    const recordId = @json($recommendation->id ?? null);
    if (!form || !button || !draftKey || !recordId) return;

    button.addEventListener('click', async function () {
        const values = {};
        Array.from(form.elements).forEach(function (field) {
            if (!field.name || field.name === '_token' || field.name === '_method'
                || field.type === 'file' || field.type === 'submit' || field.type === 'button') return;
            if (field.type === 'radio' && !field.checked) return;
            if (field.type === 'checkbox') {
                values[field.name] = field.checked ? field.value : false;
                return;
            }
            values[field.name] = field.value;
        });

        const payload = JSON.stringify({ edit_record_id: recordId, values: values, saved_at: new Date().toISOString() });
        const body = new FormData();
        body.append('_token', form.querySelector('[name="_token"]').value);
        body.append('draft_key', draftKey);
        body.append('application_type', '__edit_record__');
        body.append('mother_file_no', '');
        body.append('children_total', '0');
        body.append('children_selected', '0');
        body.append('payload', payload);

        const original = button.textContent;
        button.disabled = true;
        button.textContent = 'Saving Draft…';
        try {
            const response = await fetch(@json(route('land-recommendations.batch-drafts.store')), {
                method: 'POST', body: body, credentials: 'same-origin',
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
            });
            const data = await response.json();
            if (!response.ok || !data.success) throw new Error();
            button.textContent = 'Draft Saved';
            setTimeout(function () { button.textContent = original; }, 1800);
        } catch (error) {
            button.textContent = 'Could Not Save Draft';
            setTimeout(function () { button.textContent = original; }, 2500);
        } finally {
            button.disabled = false;
        }
    });
});
</script>
@endif
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

        {{-- Set by the GET guard on /land-recommendations/batch: someone reached the
             batch save endpoint by GET (a refresh of the page a save posted to, or
             the URL out of history) and was sent back here. Not an error — the work
             is in the draft — so it is said rather than alarmed about. --}}
        @if(session('warning'))
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    icon: 'info',
                    title: 'Batch capture reopened',
                    text: @json(session('warning')),
                    confirmButtonColor: '#2563eb',
                });
            }
        @endif

        /*
         * Ground rent unit: the free-text box belongs to the "Other" choice only.
         *
         * It is a real posted field rather than a value smuggled into the select,
         * because the batch stepper copies inputs by name one file at a time and a
         * select can only carry a value it has an <option> for. Both travel per
         * file; the server resolves Other to this text before saving, so the column
         * always holds the words that print.
         *
         * Stepping is covered without any extra hook: writeGrantCard() fires a
         * change event on every field whose value it alters, which is the same way
         * preparation_fees_words keeps up.
         */
        (function () {
            var unitSelect = document.getElementById('ground_rent_unit');
            var unitOther  = document.getElementById('ground_rent_unit_other');
            if (!unitSelect || !unitOther) return;

            unitSelect.addEventListener('change', function () {
                var isOther = unitSelect.value === 'Other';
                unitOther.classList.toggle('hidden', !isOther);
                // A unit left over from "Other" must not travel to a fixed choice:
                // the server would have nothing to resolve, but the box would sit
                // there hidden holding a stale value for the next file.
                if (!isOther) unitOther.value = '';
            });
        })();
    });
</script>
<script>
// ── Plot size & ground rent amount ───────────────────────────────────
// Ground Rent is a RATE; the amount is rate x plot size. Mirrors
// App\Support\PlotSize::amount() on the server. The plot size is prefilled and
// locked (readonly, so it still posts and still steps per file) when the file's
// indexing carries one; the server re-reads it on save either way.
document.addEventListener('DOMContentLoaded', function () {
    var sizeEl = document.getElementById('area_sqm');
    var tagEl  = document.getElementById('area_sqm_source');
    var outEl  = document.getElementById('ground_rent_amount');
    var rateEl = document.querySelector('input[name="ground_rent"]');
    var unitEl = document.getElementById('ground_rent_unit');
    var fileEl = document.getElementById('file_number');
    var formEl = document.getElementById('land-recommendation-form');
    if (!sizeEl) return;

    var inBatch = function () { return !!(formEl && formEl.classList.contains('batch-mode')); };
    var ngn = function (n) { return n.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }); };

    function updateAmount() {
        if (!outEl) return;
        var rate = parseFloat(rateEl ? rateEl.value : '');
        var size = parseFloat(sizeEl.value);
        var unit = ((unitEl && unitEl.value) || '').trim().toLowerCase();
        var qty = null;
        if (unit === '' || unit === 'per square meters') qty = size;
        else if (unit === 'ha') qty = size / 10000;
        if (isNaN(rate) || isNaN(size) || qty === null) { outEl.textContent = ''; return; }
        outEl.textContent = 'Ground rent amount: ₦' + ngn(rate) + ' × ' + ngn(size) + ' m²'
            + (unit === 'ha' ? ' (÷ 10,000)' : '') + ' = ₦' + ngn(rate * qty);
    }

    function setLock(sqm) {
        var locked = sqm !== null && sqm !== undefined && !inBatch();
        if (locked) sizeEl.value = sqm;
        sizeEl.readOnly = locked;
        // Required on single capture and edit; a batch steps it per file and
        // saves through its own endpoint, so the browser must not block there.
        sizeEl.required = !inBatch();
        sizeEl.style.backgroundColor = locked ? '#e2e8f0' : '';
        sizeEl.style.color = locked ? '#64748b' : '';
        sizeEl.style.cursor = locked ? 'not-allowed' : '';
        if (tagEl) tagEl.classList.toggle('hidden', !locked);
        updateAmount();
    }

    var lastFile = null;
    function lookup() {
        var fileNo = ((fileEl && fileEl.value) || '').trim();
        if (fileNo === lastFile) return;
        lastFile = fileNo;
        if (!fileNo || inBatch()) { setLock(null); return; }
        fetch('{{ route("land-recommendations.plot-size") }}?file_number=' + encodeURIComponent(fileNo), {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
        })
            .then(function (r) { return r.ok ? r.json() : { plot_size: null }; })
            .then(function (res) { setLock(res && res.plot_size !== null ? res.plot_size : null); })
            .catch(function () { setLock(null); });
    }

    [rateEl, unitEl, sizeEl].forEach(function (el) {
        if (el) { el.addEventListener('input', updateAmount); el.addEventListener('change', updateAmount); }
    });
    if (fileEl) { fileEl.addEventListener('change', lookup); fileEl.addEventListener('input', lookup); }
    var batchToggle = document.getElementById('batch-mode-toggle');
    if (batchToggle) batchToggle.addEventListener('change', function () { lastFile = null; lookup(); });
    // The batch-mode class may land after the toggle's change listeners run, so
    // follow the class itself for the required flag.
    if (formEl && window.MutationObserver) {
        new MutationObserver(function () { sizeEl.required = !inBatch(); })
            .observe(formEl, { attributes: true, attributeFilter: ['class'] });
    }

    lookup();
    updateAmount();
});
</script>
@endpush
@endsection
