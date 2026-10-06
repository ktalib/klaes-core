@extends('layouts.app')

@php
    /**
     * ?mode=land is the same register under the Land sidebar, not a lesser one: Land
     * raises parcel updates of its own, so it gets the New Duplex button and the row
     * actions exactly as Deeds does. The mode now only labels the page (see the
     * "Land · Parcel Update" eyebrow) — commissioning from the MLS file number modal
     * remains available, it is no longer the only way in.
     */

    /**
     * The planning clearance, resolved for the whole page in two queries.
     *
     * It used to be the KAMMA/Physical Planning handshake — a four-field modal on
     * this menu. It is now an approved Master JSI, captured by Physical Planning on
     * their own register. MasterJsiGate answers the question, so the legacy arm
     * (a duplex already cleared through the handshake) is applied here too and
     * pre-cutover records do not deadlock behind a modal that no longer exists.
     *
     * Asked per page rather than per row: the register renders 25 rows and each row
     * asks three times over, which would be this page's slowest query by far.
     */
    $jsiCleared = \App\Support\MasterJsiGate::clearedMap('duplex', $records);
    $jsiReports = \App\Support\MasterJsiGate::reportMap('duplex', $records);
@endphp

@section('styles')
<style>
    .swal2-container { z-index: 20000 !important; }

    /* Rank badge colours follow the ORDER OF SELECTION, not the type. The officer's
       tick order is the execution order, so the badge has to be the thing that moves. */
    .rank-badge { min-width: 26px; }
    .rank-1 { background:#fee2e2; color:#b91c1c; border-color:#fecaca; }
    .rank-2 { background:#dbeafe; color:#1d4ed8; border-color:#bfdbfe; }
    .rank-3 { background:#dcfce7; color:#15803d; border-color:#bbf7d0; }
    .rank-4 { background:#fef3c7; color:#b45309; border-color:#fde68a; }
    .rank-5 { background:#e2e8f0; color:#334155; border-color:#cbd5e1; }

    .type-row { transition: all .18s ease; }
    .type-row.picked { border-color:#3b82f6; background:#f8fbff; box-shadow:0 1px 2px rgba(59,130,246,.08); }
    .type-row.picked .type-icon { background:#dbeafe; color:#1d4ed8; }

    .wizard-step { display:none; }
    .wizard-step.active { display:block; animation: fadeIn .25s ease-out; }
    @keyframes fadeIn { from { opacity:0; transform:translateY(5px);} to {opacity:1; transform:translateY(0);} }

    /* Stepper tabs. Three states: done (behind you), active (here), ahead (greyed).
       Colour alone would not separate done from active, so the underline moves too. */
    .dx-step-tab .dx-step-dot  { background:#f1f5f9; color:#94a3b8; }
    .dx-step-tab .dx-step-text { color:#94a3b8; }
    .dx-step-tab.is-done .dx-step-dot  { background:#dcfce7; color:#15803d; }
    .dx-step-tab.is-done .dx-step-text { color:#475569; }
    /* The tab is a <button> now (steps are clickable), so this must not target > div. */
    .dx-step-tab.is-active > button      { border-bottom-color:#2563eb; }
    .dx-step-tab.is-active .dx-step-dot  { background:#2563eb; color:#fff; }
    .dx-step-tab.is-active .dx-step-text { color:#1e293b; }

    /* Reachable steps read as controls; a step you cannot get to yet must not. */
    .dx-step-tab > button { cursor:pointer; background:none; transition:background .12s ease; }
    .dx-step-tab > button:hover:not(:disabled) { background:#f8fafc; }
    .dx-step-tab > button:focus-visible { outline:2px solid #2563eb; outline-offset:-2px; }
    .dx-step-tab.is-locked > button { cursor:not-allowed; opacity:.55; }

    .stage-pill.done { background:#dcfce7; color:#15803d; }
    .stage-pill.current { background:#dbeafe; color:#1d4ed8; }
    .stage-pill.locked { background:#f1f5f9; color:#94a3b8; cursor:default; }

    /* A stage you can return to reads as a control; a locked one must not. */
    .stage-pill.clickable { cursor:pointer; border:0; font:inherit; transition:transform .12s ease, box-shadow .12s ease; }
    .stage-pill.clickable:hover { transform:translateY(-1px); box-shadow:0 4px 12px rgb(15 23 42 / .10); }
    .stage-pill.clickable:focus-visible { outline:2px solid #2563eb; outline-offset:2px; }

    /* Apply-to chips: ticked reads as active, unticked greys back so the selection
       is obvious without reading each checkbox. */
    .applies-chip { background:#eff6ff; border-color:#bfdbfe; color:#1d4ed8; transition:all .12s ease; }
    .applies-chip.is-off { background:#f8fafc; border-color:#e2e8f0; color:#94a3b8; }
    .applies-chip.is-off .holding-no { text-decoration:line-through; text-decoration-color:#cbd5e1; }
    .applies-chip:hover { border-color:#93c5fd; }

    .holding-no { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; letter-spacing:-.01em; }
</style>
@endsection

@section('content')
<div class="flex-1 overflow-auto bg-slate-50/60">
    @include('admin.header', [
        'PageTitle' => 'APU - Advance Parcel Update (Duplex) Workflow Manager',
        'PageDescription' => 'Several parcel updates carried as one instruction.'
    ])

    <div class="py-10 bg-slate-50 min-h-screen">
        <div class="max-w-[95%] mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

            {{-- Header --}}
            <div class="bg-white rounded-3xl border border-slate-100 shadow-sm overflow-hidden">
                <div class="px-7 py-6 flex flex-col lg:flex-row lg:items-center lg:justify-between gap-5">
                    <div class="flex items-start gap-4 min-w-0">
                        <span class="inline-flex items-center justify-center w-12 h-12 rounded-2xl bg-blue-600/10 text-blue-600 shrink-0">
                            <i data-lucide="layers" class="w-5 h-5"></i>
                        </span>
                        <div class="min-w-0">
                            <p class="text-[10px] font-black uppercase tracking-[0.3em] text-slate-400">{{ request('mode') === 'land' ? 'Land' : 'Deeds' }} · Parcel Update</p>
                            <h1 class="text-2xl font-black text-slate-800 mt-1 leading-tight">APU - Advance Parcel Update (Duplex) Workflow Manager</h1>
                            <p class="text-sm text-slate-500 mt-1.5 max-w-2xl leading-relaxed">
                                Subdivision, Merger, Change of Purpose, Extension and Separation in one
                                instruction — one approval, one memo, one commissioning. A single update on
                                its own is fine too.
                            </p>
                        </div>
                    </div>
                    <button onclick="openDuplexWizard()"
                        class="shrink-0 inline-flex items-center gap-2 px-6 py-3 bg-blue-600 text-white rounded-xl text-sm font-bold shadow-sm shadow-blue-600/20 hover:bg-blue-700 transition">
                        <i data-lucide="plus" class="w-4 h-4"></i>
                        New Duplex
                    </button>
                </div>

                {{-- The pipeline, spelled out. New officers cannot infer these steps from
                     the row menu alone, and skipping one is the usual support call. --}}
                <div class="px-7 py-3.5 border-t border-slate-100 bg-slate-50/70 flex flex-wrap items-center gap-x-2 gap-y-2 text-[11px] font-bold text-slate-400">
                    @foreach (['Capture stages', 'Master JSI', 'Recommendation', 'Approve', 'Conveyance', 'Send to Land', 'Commission'] as $i => $step)
                        @if ($i)<i data-lucide="chevron-right" class="w-3.5 h-3.5 text-slate-300"></i>@endif
                        {{-- $loop->last, not a fixed index: this list has changed length now
                             and a hard-coded 5 leaves the highlight on the wrong step. --}}
                        <span class="{{ $loop->last ? 'text-indigo-600' : '' }}">{{ $step }}</span>
                    @endforeach
                </div>
            </div>

            {{-- Stats --}}
            <div class="grid grid-cols-2 md:grid-cols-6 gap-3">
                @foreach ([
                    ['Total', $stats['total'], 'layers', 'text-slate-500'],
                    ['Today', $stats['daily'], 'calendar', 'text-blue-500'],
                    ['Draft', $stats['draft'], 'pencil', 'text-amber-500'],
                    ['Pending', $stats['pending'], 'clock', 'text-orange-500'],
                    ['Approved', $stats['approved'], 'check-circle', 'text-emerald-500'],
                    ['Commissioned', $stats['committed'], 'landmark', 'text-indigo-500'],
                ] as [$label, $value, $icon, $tone])
                <div class="bg-white rounded-2xl border border-slate-100 p-4 hover:border-slate-200 transition">
                    <div class="flex items-center justify-between">
                        <p class="text-[10px] uppercase tracking-wider text-slate-400 font-black">{{ $label }}</p>
                        <i data-lucide="{{ $icon }}" class="w-4 h-4 {{ $tone }}"></i>
                    </div>
                    <p class="text-2xl font-black text-slate-800 mt-2 leading-none">{{ $value }}</p>
                </div>
                @endforeach
            </div>

            {{-- Register --}}
            <div class="bg-white rounded-3xl border border-slate-100 shadow-sm overflow-hidden">
                <div class="px-6 py-4 border-b border-slate-100 flex flex-wrap items-center justify-between gap-4">
                    <div class="flex items-baseline gap-2.5">
                        <h2 class="text-sm font-black uppercase tracking-wider text-slate-600">Duplex Register</h2>
                        <span class="text-xs font-bold text-slate-400">{{ $records->total() }} record{{ $records->total() === 1 ? '' : 's' }}</span>
                    </div>
                    <form method="GET" class="flex items-center gap-2">
                        <div class="relative">
                            <i data-lucide="search" class="w-4 h-4 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none"></i>
                            <input type="text" name="search" value="{{ $search }}" placeholder="Duplex ID, applicant, title"
                                class="pl-9 pr-3 py-2 rounded-xl border border-slate-200 text-sm w-72 focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition">
                        </div>
                        <button class="px-4 py-2 rounded-xl bg-slate-800 text-white text-sm font-bold hover:bg-slate-900 transition">Search</button>
                        @if ($search)
                            <a href="{{ route('duplex-parcel-update.index') }}" class="text-xs font-bold text-slate-400 hover:text-slate-600 px-2">Clear</a>
                        @endif
                    </form>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-slate-50 text-[11px] uppercase tracking-wider text-slate-500">
                            <tr>
                                <th class="px-4 py-3 text-left">S/N</th>
                                <th class="px-4 py-3 text-left">Duplex ID</th>
                                <th class="px-4 py-3 text-left">Applicant</th>
                                <th class="px-4 py-3 text-left">Source File(s)</th>
                                <th class="px-4 py-3 text-left">Stages (execution order)</th>
                                <th class="px-4 py-3 text-left">Source</th>
                                <th class="px-4 py-3 text-left">Date</th>
                                <th class="px-4 py-3 text-left">Created By</th>
                                <th class="px-4 py-3 text-left">Status</th>
                                <th class="px-4 py-3 text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse ($records as $record)
                            <tr class="hover:bg-slate-50/60">
                                <td class="px-4 py-3 font-mono text-xs text-slate-400">{{ $records->firstItem() + $loop->index }}</td>
                                <td class="px-4 py-3 font-black text-slate-700 whitespace-nowrap holding-no">{{ $record->duplex_id }}</td>
                                {{-- One case for the column: names arrive as the officer typed
                                     them, so a register listing "IBRAHIM DANLADI" beside
                                     "Murtala Muhammad Illallah" reads as though the difference
                                     means something. Display only — the stored value is what
                                     every lookup and comparison runs against. --}}
                                <td class="px-4 py-3 text-slate-700 font-bold">{{ \App\Support\PersonName::display($record->applicant_name) ?: '—' }}</td>
                                <td class="px-4 py-3">
                                    <div class="flex flex-wrap gap-1">
                                        @forelse ((array) ($record->source_file_nos ?? []) as $src)
                                            <span class="holding-no text-[10px] font-bold px-2 py-0.5 rounded-md bg-slate-100 text-slate-600 border border-slate-200">{{ $src }}</span>
                                        @empty
                                            <span class="text-xs text-slate-300">—</span>
                                        @endforelse
                                    </div>
                                </td>
                                <td class="px-4 py-3">
                                    {{-- Ranked badges, in execution order — the same colours the
                                         wizard uses, so the two screens read as one thing. --}}
                                    <div class="flex flex-wrap items-center gap-1.5">
                                        @foreach (collect($record->stages ?? [])->sortBy('rank') as $stage)
                                            <span class="inline-flex items-center gap-1.5 pl-1 pr-2 py-1 rounded-lg border border-slate-200 bg-white">
                                                <span class="rank-badge rank-{{ min($stage['rank'] ?? 1, 5) }} w-4 h-4 rounded flex items-center justify-center text-[9px] font-black border">{{ $stage['rank'] ?? '?' }}</span>
                                                <span class="text-[11px] font-bold text-slate-600">{{ $types[$stage['type'] ?? ''] ?? '?' }}</span>
                                            </span>
                                        @endforeach
                                    </div>
                                </td>
                                {{-- Which sidebar raised it. Both open this same register, so without
                                     this the two modules' work is indistinguishable in the list. A row
                                     captured before the column existed reads "—" rather than being
                                     assigned an origin nobody recorded. --}}
                                <td class="px-4 py-3">
                                    <span class="px-2 py-0.5 rounded-lg border text-[11px] font-bold uppercase {{ \App\Support\ParcelUpdateSource::chipClass($record->source_module) }}">
                                        {{ \App\Support\ParcelUpdateSource::label($record->source_module) }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-slate-500 text-xs">{{ $record->created_at?->format('M d, Y') }}</td>
                                {{-- captured_by is the user id; a row whose user no longer
                                     exists shows "—" rather than a bare number. --}}
                                @php
                                    $creatorName = $record->capturedBy
                                        ? trim($record->capturedBy->first_name . ' ' . $record->capturedBy->last_name)
                                        : '';
                                @endphp
                                <td class="px-4 py-3 text-slate-600 text-xs font-semibold whitespace-nowrap">
                                    {{ $creatorName !== '' ? \App\Support\PersonName::display($creatorName) : '—' }}
                                </td>
                                <td class="px-4 py-3">
                                    @php
                                        $tone = match ($record->status) {
                                            'committed' => 'bg-indigo-50 text-indigo-700 border-indigo-200',
                                            'approved', 'in_land' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
                                            'rejected' => 'bg-red-50 text-red-700 border-red-200',
                                            'draft' => 'bg-amber-50 text-amber-700 border-amber-200',
                                            default => 'bg-slate-50 text-slate-600 border-slate-200',
                                        };
                                    @endphp
                                    {{-- `committed` is the stored value; COMMISSIONED is what it
                                         means to an officer, and what the summary card has always
                                         called it. The column is left alone - renaming a status in
                                         the database would touch every guard that reads it. --}}
                                    <span class="px-2 py-0.5 rounded-lg border text-[11px] font-bold uppercase {{ $tone }}">
                                        {{ $record->status === 'committed'
                                            ? 'commissioned'
                                            : str_replace('_', ' ', $record->status) }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-center">
                                    {{-- The menu is positioned FIXED and moved to <body> on open: the
                                         table scrolls horizontally, so an absolutely-positioned menu is
                                         clipped by the scroll container and drops inside the table. --}}
                                    <div class="inline-block text-left">
                                        <button onclick="toggleRowMenu({{ $record->id }}, this)" title="Actions"
                                            class="w-8 h-8 rounded-lg flex items-center justify-center text-slate-400 hover:text-slate-700 hover:bg-slate-100 transition">
                                            <i data-lucide="more-vertical" class="w-4 h-4"></i>
                                        </button>
                                        @php
                                            /**
                                             * The menu walks the duplex through its pipeline: each action
                                             * unlocks the next and closes behind itself, so the officer is
                                             * never offered a step that would be refused by the server or
                                             * one that has already been done.
                                             *
                                             * Rejected and committed duplexes are read-only — there is
                                             * nothing left to do to either.
                                             */
                                            $isRejected  = $record->status === 'rejected';
                                            $isCommitted = $record->status === 'committed';
                                            $frozen      = $isRejected || $isCommitted;

                                            /**
                                             * A draft is still being written — the stages can change under
                                             * anything decided on it — so the steps that answer a settled
                                             * instruction (the planning clearance, and the decision itself)
                                             * stay greyed until capture is finished.
                                             */
                                            $isDraft     = $record->status === 'draft';

                                            $isApproved  = in_array($record->status, ['approved', 'in_land', 'committed'], true);
                                            $jsiOk       = $jsiCleared[$record->id] ?? false;
                                            $jsiReport   = $jsiReports[$record->id] ?? null;
                                            $allCaptured = $record->stageRows->every(fn ($st) => $st->status === 'done');

                                            /**
                                             * Approval rests on the recommendation, and the conveyance follows it.
                                             *
                                             * The conveyance used to be a precondition of approval, which put the
                                             * letter to the applicant BEFORE the decision that letter reports. The
                                             * order of business is the Ministry's: the recommendation goes up, the
                                             * Commissioner answers, and only then is the conveyance drawn. So the
                                             * conveyance is no longer required here - it is gated on the approval
                                             * instead, further down.
                                             *
                                             * What the approval still rests on, each a document the approver is
                                             * meant to have read:
                                             *
                                             *   every stage captured   — there is something to approve
                                             *   Master JSI approved    — Physical Planning has cleared it
                                             *   recommendation drawn   — the memo carrying it up exists
                                             *   site plan attached     — the drawing the memo is read against
                                             *
                                             * approve() enforces the same set, so this is the reminder and that
                                             * is the rule.
                                             */
                                            $hasSitePlan   = trim((string) $record->site_plan) !== '';

                                            // The memo has to have been PRINTED, not merely drawn. The two are
                                            // different events: a recommendation generated and never printed has
                                            // been read by nobody, and the duplex was approvable in that state.
                                            $recPrinted    = (bool) $record->recommendation_printed_at;

                                            $canApprove    = !$frozen && !$isApproved && $allCaptured
                                                             && $jsiOk
                                                             && $record->recommendation_generated_at
                                                             && $recPrinted
                                                             && $hasSitePlan;

                                            // Naming the FIRST thing still missing, in the order it is done.
                                            $approvalBlocker = !$allCaptured ? 'Capture every stage first'
                                                : (!$jsiOk ? \App\Support\MasterJsiGate::blocker($jsiReport)
                                                : (!$record->recommendation_generated_at ? 'Generate the recommendation first'
                                                : (!$recPrinted ? 'Print the recommendation first'
                                                : (!$hasSitePlan ? 'Attach the site plan first'
                                                : 'Already approved'))));

                                            // Rejecting is a decision on the instruction, so it waits for one
                                            // to exist. $canApprove already excludes a draft through
                                            // $allCaptured; without this the menu item stayed live on a draft
                                            // as a reject-only action.
                                            //
                                            // It also waits for the recommendation to be drawn and printed.
                                            // Approve and Reject are two answers to ONE question asked by ONE
                                            // menu item, so an item live on the strength of Reject alone left
                                            // the decision step open above the two paper steps it is taken
                                            // from. Either answer is a decision on the memo, and the memo has
                                            // to be in the room.
                                            //
                                            // The item asks ONE question — approve or reject — and it closes
                                            // the moment that question has been answered. An approved duplex is
                                            // decided; so is one sent to Land or commissioned. Leaving it live
                                            // as a withdraw-the-approval route put a decision already taken back
                                            // on the menu, above the steps that follow from it.
                                            //
                                            // Reversing an approval is not a menu item: reject the stage from
                                            // the wizard, or roll a commissioned duplex back.
                                            $sentToLand    = in_array($record->status, ['in_land', 'committed'], true);

                                            $canReject     = !$frozen && !$isDraft && !$isApproved
                                                             && $record->recommendation_generated_at && $recPrinted;

                                            $knupdaDone    = strcasecmp(trim((string) ($record->knupda_status ?? '')), 'Approved') === 0;
                                            $canKnupda     = !$knupdaDone && !$isDraft;

                                            $canGenApp     = !$frozen && $jsiOk && !$record->application_generated_at;
                                            $canPrintApp   = (bool) $record->application_generated_at;

                                            // The recommendation is the first paper drawn: it opens straight off
                                            // the KAMMA clearance, and it is what the approval is given on. It
                                            // used to wait for the conveyance, which put the two the wrong way
                                            // round.
                                            $canGenRec     = !$frozen && $jsiOk && !$record->recommendation_generated_at;

                                            // Print closes behind itself the way Generate does. The memo has to
                                            // reach paper once, because the approval is given on it — and once
                                            // it has, the step is done and the menu says so rather than leaving
                                            // a finished step looking outstanding.
                                            $canPrintRec   = (bool) $record->recommendation_generated_at && !$recPrinted;

                                            // The conveyance reports a decision, so it cannot be drawn before
                                            // there is one. generateConveyance() enforces the same rule.
                                            $canGenConv    = !$frozen && $isApproved && !$record->conveyance_generated_at;
                                            $canPrintConv  = (bool) $record->conveyance_generated_at;
                                            $canDelete     = !$isCommitted;

                                            // Why an action is unavailable, so a greyed row is not a mystery.
                                            $why = function ($enabled, $reason) use ($isRejected, $isCommitted) {
                                                if ($enabled) return '';
                                                if ($isRejected)  return 'This duplex was rejected';
                                                if ($isCommitted) return 'This duplex has been commissioned';
                                                return $reason;
                                            };
                                        @endphp

                                        <div id="row-menu-{{ $record->id }}"
                                            class="hidden fixed z-[999] w-60 rounded-xl bg-white shadow-xl ring-1 ring-black/5 divide-y divide-slate-100 text-left">
                                            {{-- Reading is always allowed, whatever state the duplex is in. --}}
                                            <div class="py-1">
                                                @if ($record->status === 'draft')
                                                    <button onclick="resumeDuplex({{ $record->id }})" class="menu-item font-bold text-blue-700">Continue capture</button>
                                                @endif
                                                <button onclick="openDuplexSummary({{ $record->id }})" class="menu-item font-bold">Summary sheet</button>
                                                <button onclick="viewDuplex({{ $record->id }})" class="menu-item">View stages</button>
                                            </div>
                                            <div class="py-1 border-t border-slate-100">
                                                @if ($canKnupda)
                                                <button onclick="openKnupda({{ $record->id }})" class="menu-item font-bold text-blue-700">KAMMA / Physical Planning</button>
                                                @else
                                                <button disabled
                                                    title="{{ $knupdaDone ? 'Already approved by KAMMA / Physical Planning' : 'Finish capturing the stages first' }}"
                                                    class="menu-item text-slate-400 cursor-not-allowed bg-slate-50/50">KAMMA / Physical Planning</button>
                                                @endif
                                            </div>

                                            {{-- The planning clearance is NOT on this menu.

                                                 It used to be, as the KAMMA/Physical Planning handshake — a
                                                 four-field modal recording a fee and a status, standing in
                                                 for a site inspection that had nowhere to live. The
                                                 inspection now exists as the Master JSI, and capturing,
                                                 approving and sending it are all Physical Planning's work on
                                                 their own register.

                                                 This page only READS the answer: $jsiOk, via MasterJsiGate,
                                                 decides what may be generated and approved, and
                                                 $approvalBlocker says in words when the sheet is what is
                                                 still missing. --}}

                                            {{-- The menu now runs in the order the work is done: the
                                                 recommendation is drawn and printed, the Commissioner
                                                 answers, and the conveyance reporting that answer follows.
                                                 It used to open with the conveyance, which asked the
                                                 officer to write out the decision before it was taken.

                                                 Generate / Print Application are OFF THE MENU — not needed
                                                 at this stage. Nothing else was removed: the routes, the
                                                 controller actions and print/application.blade.php all
                                                 still work, and $canGenApp / $canPrintApp are still
                                                 computed above. Delete this comment wrapper to bring them
                                                 back.

                                                <button onclick="generateDoc({{ $record->id }}, 'application')" @disabled(!$canGenApp)
                                                    title="{{ $why($canGenApp, $record->application_generated_at ? 'Already generated' : 'KAMMA approval required') }}"
                                                    class="menu-item">Generate Application</button>
                                                <a href="{{ route('duplex-parcel-update.print-application', $record->id) }}" target="_blank"
                                                    title="{{ $canPrintApp ? '' : 'Generate the application first' }}"
                                                    class="menu-item block {{ $canPrintApp ? '' : 'is-disabled' }}">Print Application</a>
                                                --}}
                                            <div class="py-1">
                                                <button onclick="generateDoc({{ $record->id }}, 'recommendation')" @disabled(!$canGenRec)
                                                    title="{{ $why($canGenRec, $record->recommendation_generated_at ? 'Already generated' : 'KAMMA approval required') }}"
                                                    class="menu-item">Generate Recommendation</button>
                                                <a href="{{ route('duplex-parcel-update.print-recommendation', $record->id) }}" target="_blank"
                                                    title="{{ $canPrintRec ? '' : ($recPrinted ? 'Already printed' : 'Generate the recommendation first') }}"
                                                    class="menu-item block {{ $canPrintRec ? '' : 'is-disabled' }}">Print Recommendation</a>
                                            </div>

                                            {{-- Approve and Reject are two answers to ONE question, so the menu
                                                 asks it once and the card carries both.

                                                 They were split into separate items for a reason worth keeping:
                                                 a single item enabled whenever EITHER answer was possible read
                                                 as an approval invitation on a draft — a draft can always be
                                                 rejected, so the item sat live and bold above four greyed steps
                                                 it is supposed to come after. That is answered in the card
                                                 rather than by splitting the menu: the item is only green and
                                                 bold once the duplex can actually be approved, and inside the
                                                 card the Approve option is offered only when it is really
                                                 available — otherwise it is greyed and says, in words, the
                                                 first thing still missing. --}}
                                            <div class="py-1">
                                                <button onclick="openApprovalDecision({{ $record->id }}, @js([
                                                        'label'      => $record->duplex_id,
                                                        'canApprove' => $canApprove,
                                                        'canReject'  => $canReject,
                                                        'blocker'    => $approvalBlocker,
                                                    ]))"
                                                    @disabled(!$canApprove && !$canReject)
                                                    title="{{ $why($canApprove || $canReject, $sentToLand ? 'Already sent to Land — the decision is behind it' : ($isApproved ? 'Nothing left to decide' : $approvalBlocker)) }}"
                                                    class="menu-item {{ $canApprove ? 'text-emerald-700 font-bold' : '' }}">Approve / Reject</button>
                                            </div>

                                            <div class="py-1">
                                                <button onclick="generateDoc({{ $record->id }}, 'conveyance')" @disabled(!$canGenConv)
                                                    title="{{ $why($canGenConv, $record->conveyance_generated_at ? 'Already generated' : 'Approve the duplex first') }}"
                                                    class="menu-item">Generate Conveyance</button>
                                                <a href="{{ route('duplex-parcel-update.print-conveyance', $record->id) }}" target="_blank"
                                                    title="{{ $canPrintConv ? '' : 'Generate the conveyance first' }}"
                                                    class="menu-item block {{ $canPrintConv ? '' : 'is-disabled' }}">Print Conveyance</a>
                                            </div>

                                            <div class="py-1">
                                                <button onclick="deleteDuplex({{ $record->id }})" @disabled(!$canDelete)
                                                    title="{{ $canDelete ? '' : 'A commissioned duplex cannot be deleted' }}"
                                                    class="menu-item text-red-600">Delete</button>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="10" class="px-4 py-16 text-center">
                                    <i data-lucide="layers" class="w-8 h-8 text-slate-300 mx-auto"></i>
                                    <p class="text-sm font-bold text-slate-500 mt-3">No duplex records yet</p>
                                    <p class="text-xs text-slate-400 mt-1">Start one to carry several parcel updates as a single instruction.</p>
                                    <button onclick="openDuplexWizard()" class="mt-4 px-4 py-2 rounded-xl bg-blue-600 text-white text-xs font-bold hover:bg-blue-700 transition">
                                        New Duplex
                                    </button>
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="px-4 py-3 bg-slate-50/50 border-t border-slate-100">
                    {{ $records->withQueryString()->links() }}
                </div>
            </div>
        </div>
    </div>

    <script src="{{ asset('js/shared/record-summary-card.js') }}?v={{ @filemtime(public_path('js/shared/record-summary-card.js')) }}"></script>
<script src="{{ asset('js/duplex-summary-card.js') }}?v={{ @filemtime(public_path('js/duplex-summary-card.js')) }}"></script>
@include('deeds.parcel_update.duplex.partials.wizard')
    {{-- The KAMMA/Physical Planning modal is gone: the planning clearance is now
         the Master JSI, captured on the Physical Planning register. The partial,
         the route and the knupda_* columns all still exist as history — see
         App\Support\MasterJsiGate for the legacy pass they feed. --}}
@include('deeds.parcel_update.duplex.partials.view_modal')
@include('deeds.parcel_update.duplex.partials.knupda_modal')
</div>

@include('components.global-fileno-modal')
@endsection

@section('footer-scripts')
<script src="{{ asset('js/global-fileno-modal.js') }}"></script>
@include('deeds.parcel_update.duplex.js', [
    'types' => $types,
    'streetNames' => $streetNames,
    'lgas' => $lgas,
    'districts' => $districts,
])
<style>
    .menu-item { display:block; width:100%; text-align:left; padding:.55rem 1rem; font-size:.8rem; color:#334155; }
    .menu-item:hover:not(:disabled):not(.is-disabled) { background:#f8fafc; }

    /* A step that is not yet reachable, or already done, greys out rather than
       disappearing — the officer can still see where they are in the sequence. */
    .menu-item:disabled,
    .menu-item.is-disabled { color:#cbd5e1; cursor:not-allowed; pointer-events:none; }

    /* The two answers on the approval card. Each states what it does underneath
       itself, so the decision is read rather than guessed at; an answer that is
       not available greys out in place and says why, instead of vanishing and
       leaving the officer to wonder where it went. */
    .dx-decision {
        display:block; width:100%; text-align:left; margin-top:.6rem;
        padding:.85rem 1rem; border:1px solid #e2e8f0; border-radius:.9rem;
        background:#fff; cursor:pointer; transition:border-color .12s ease, background .12s ease, box-shadow .12s ease;
    }
    .dx-decision:hover:not(:disabled) { box-shadow:0 4px 14px rgb(15 23 42 / .08); }
    .dx-decision:focus-visible { outline:2px solid #2563eb; outline-offset:2px; }
    .dx-decision-title { display:block; font-size:.85rem; font-weight:800; }
    .dx-decision-note  { display:block; font-size:.72rem; line-height:1.5; color:#64748b; margin-top:.2rem; }

    .dx-decision-approve .dx-decision-title { color:#047857; }
    .dx-decision-approve:hover:not(:disabled) { border-color:#6ee7b7; background:#f0fdf4; }
    .dx-decision-reject  .dx-decision-title { color:#b91c1c; }
    .dx-decision-reject:hover:not(:disabled) { border-color:#fca5a5; background:#fef2f2; }

    .dx-decision:disabled { cursor:not-allowed; background:#f8fafc; border-style:dashed; }
    .dx-decision:disabled .dx-decision-title { color:#94a3b8; }
    .dx-decision:disabled .dx-decision-note  { color:#94a3b8; }
</style>
@endsection
