{{--
  File History Summary card — shown inside the Property Transaction Details modal.

  Two layers, refreshed on different triggers, because they cost very different amounts:

    ON FILE   the file's real history, read from the CORE Legal Search report engine
              (legal_search.print.data -> LegalSearchService::buildPrintReport). That call
              takes ~3-5s, so it is fetched ONCE per file number — debounced, abortable and
              cached — never on keystroke. Same engine as the LS timeline, the Property
              Timeline, the PHS portal and the online report, so this card cannot disagree
              with them.

    CAPTURED  the transaction blocks in this form, recomputed locally on every edit. Instant.

  The card is never blank while the slow layer loads: the form blocks render immediately, so
  the fetch only ever ENRICHES an already-useful card. It is also fail-open — if the report
  call errors or is aborted, the captured rows still show with their destinations.

  Rows the report emits that are not transaction records (File Commissioning / Temporary File)
  are "derived": rendered muted and WITHOUT a destination chip, because Save writes nothing
  for them. The missing chip is the honest signal that there is nothing to capture there.
--}}
<div class="border border-slate-200 rounded-lg mb-4 bg-white overflow-hidden"
     x-show="fileIndexingData.file_number || fileIndexingData.temp_file_no"
     x-cloak>

    <button type="button"
            class="w-full flex items-center justify-between gap-3 px-4 py-3 bg-slate-50 hover:bg-slate-100 transition-colors text-left"
            @click="fhSummaryOpen = !fhSummaryOpen">
        <span class="flex items-center gap-2 min-w-0">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-slate-500 shrink-0" fill="none"
                 viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <span class="text-sm font-semibold text-slate-800 shrink-0">File History Summary</span>
            <span class="text-xs text-slate-500 truncate" x-text="fhSummaryHeadline()"></span>
        </span>
        <span class="flex items-center gap-2 shrink-0">
            <span x-show="fhSummaryLoading" class="text-[10px] text-slate-400">loading history…</span>
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-slate-400 transition-transform"
                 :class="fhSummaryOpen ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24"
                 stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
            </svg>
        </span>
    </button>

    <div x-show="fhSummaryOpen">
        <div class="px-4 py-3">

            {{-- Destination roll-up: where THIS submission is going. --}}
            <div class="flex flex-wrap items-center gap-1.5 mb-3" x-show="fhDestinationTally().length">
                <span class="text-[11px] text-slate-500 mr-1">This submission writes to:</span>
                <template x-for="d in fhDestinationTally()" :key="d.key">
                    <span class="fh-chip" :class="'fh-chip-' + d.key">
                        <span x-text="d.label"></span>
                        <span class="ml-1 opacity-70" x-text="d.count"></span>
                    </span>
                </template>
            </div>

            {{-- Fail-open notice: captured rows are still trustworthy, context is not available. --}}
            <div x-show="fhSummaryError"
                 class="mb-3 px-3 py-2 rounded-md bg-amber-50 border border-amber-200 text-[11px] text-amber-800">
                Could not load this file's existing history — the transactions below are still
                accurate for what will be saved.
            </div>

            <div x-show="!fhSummaryRows().length && !fhSummaryLoading"
                 class="text-center py-6 text-slate-400 text-xs">
                No transactions on this file yet.
            </div>

            <p class="text-[10px] text-slate-400 mb-2"
               x-show="fhSummaryRows().some(r => r.key.startsWith('form-'))">
                Listed in the file's date order, not the order captured &mdash; click a row to jump to it in the form.
            </p>

            <div class="fh-track">
                <template x-for="row in fhSummaryRows()" :key="row.key">
                    <div class="relative pl-7 pb-3" :class="row.derived ? 'opacity-60' : ''">
                        <span class="fh-dot" :style="'border-color:' + row.color"></span>
                        {{-- Rows backed by a form block jump to it on click: the form is in the
                             file's chronological order, so a newly added record is rarely the
                             last block and is easy to think missing. --}}
                        <div class="rounded-md border px-3 py-2"
                             :class="[
                                 row.status === 'NEW'
                                     ? 'border-emerald-200 bg-emerald-50/40'
                                     : (row.status === 'EDITING' ? 'border-blue-200 bg-blue-50/40' : 'border-slate-200 bg-white'),
                                 row.key.startsWith('form-') ? 'cursor-pointer hover:border-indigo-300 hover:shadow-sm' : ''
                             ]"
                             :title="row.key.startsWith('form-') ? 'Go to this transaction in the form' : ''"
                             @click="row.key.startsWith('form-') && fhJumpToTransaction(row.key)">
                            <div class="flex flex-wrap items-center gap-1.5 mb-1">
                                {{-- Derived rows carry no destination chip: nothing is written for them. --}}
                                <template x-if="row.destinationKey">
                                    <span class="fh-chip" :class="'fh-chip-' + row.destinationKey"
                                          x-text="row.destinationLabel"></span>
                                </template>
                                {{-- A derived row's status badge already reads "Derived", so it
                                     carries no second chip saying the same word. --}}
                                <span class="fh-badge" :class="'fh-badge-' + row.status.toLowerCase()"
                                      x-text="row.statusLabel"></span>
                                <span class="text-xs font-semibold text-slate-800" x-text="row.instrument"></span>
                                <template x-if="row.regNo">
                                    <span class="text-[10px] text-slate-400" x-text="'Reg: ' + row.regNo"></span>
                                </template>
                            </div>
                            <div class="flex items-center justify-between gap-3">
                                <p class="text-[11px] text-slate-600 truncate" x-text="row.parties"></p>
                                <span class="text-[10px] text-slate-400 whitespace-nowrap" x-text="row.date"></span>
                            </div>
                        </div>
                    </div>
                </template>
            </div>
        </div>
    </div>
</div>

<style>
    .fh-track { position: relative; }
    .fh-track::before {
        content: ''; position: absolute; left: 0.53rem; top: 0.35rem; bottom: 0.6rem;
        width: 2px; background: #e2e8f0;
    }
    .fh-dot {
        position: absolute; left: 0; top: 0.55rem; width: 1.1rem; height: 1.1rem;
        border-radius: 9999px; border: 2px solid #94a3b8; background: #fff; z-index: 1;
    }
    .fh-chip, .fh-badge {
        display: inline-flex; align-items: center; padding: 0.05rem 0.4rem; border-radius: 9999px;
        font-size: 0.58rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.04em;
        white-space: nowrap; border: 1px solid transparent;
    }
    .fh-chip-file_history_staging { background:#dbeafe; color:#1e40af; border-color:#bfdbfe; }
    .fh-chip-cofo_staging         { background:#d1fae5; color:#065f46; border-color:#a7f3d0; }
    .fh-chip-pra                  { background:#fef3c7; color:#92400e; border-color:#fde68a; }
    .fh-chip-deed_registrations   { background:#ede9fe; color:#5b21b6; border-color:#ddd6fe; }
    .fh-chip-derived              { background:#f1f5f9; color:#475569; border-color:#e2e8f0; }

    .fh-badge-new       { background:#059669; color:#fff; }
    .fh-badge-editing   { background:#2563eb; color:#fff; }
    .fh-badge-on_file   { background:#f1f5f9; color:#475569; border-color:#e2e8f0; }
    .fh-badge-saved     { background:#047857; color:#fff; }
    .fh-badge-updated   { background:#1d4ed8; color:#fff; }
    .fh-badge-held_back { background:#b45309; color:#fff; }

    /* Flash applied to a transaction block jumped to from the summary. */
    @keyframes fhTxnFlash {
        0%   { box-shadow: 0 0 0 0 rgba(99,102,241,.55); border-color:#6366f1; }
        100% { box-shadow: 0 0 0 14px rgba(99,102,241,0); border-color:#d1d5db; }
    }
    .fh-txn-flash { animation: fhTxnFlash 1.4s ease-out 1; }
</style>
