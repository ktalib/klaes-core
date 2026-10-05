<style>
    /* Keep SweetAlert / dialogs above the instrument capture modal (z-index: 1000020) */
    .swal2-container { z-index: 2000000 !important; }
    .swal2-backdrop-show { z-index: 1999999 !important; }

    /* Specific scrollbar styling and flex constraints for registration-form */
    #registration-form {
        overflow-y: auto !important;
        min-height: 0 !important;
        flex: 1 1 0% !important;
    }

    /* SLTR CofO capture hand-off. This intentionally follows the same numbered
       rail language as the SLTR CofO Workflow screens, while remaining compact
       enough for the capture modal. */
    .sltr-capture-flow { border: 1px solid #d9f99d; border-radius: 12px; background: #f7fee7; padding: 16px 18px 10px; }
    .sltr-capture-flow__head { display: flex; align-items: flex-start; justify-content: space-between; gap: 12px; flex-wrap: wrap; }
    .sltr-capture-flow__kicker { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .06em; color: #4d7c0f; }
    .sltr-capture-flow__copy { margin-top: 3px; font-size: 12px; color: #3f6212; }
    .sltr-capture-flow__current { display: inline-flex; align-items: center; border: 1px solid #a3e635; border-radius: 99px; background: #fff; padding: 4px 9px; font-size: 11px; font-weight: 700; color: #3f6212; white-space: nowrap; }
    .sltr-capture-flow__steps { display: grid; grid-template-columns: repeat(6, minmax(0, 1fr)); gap: 0; margin: 15px 0 0; padding: 4px 4px 10px; list-style: none; }
    .sltr-capture-flow__step { position: relative; display: flex; min-width: 0; flex-direction: column; align-items: center; padding: 0 3px; text-align: center; }
    .sltr-capture-flow__step::before { position: absolute; z-index: 0; top: 13px; left: -50%; width: 100%; height: 2px; background: #d1d5db; content: ''; }
    .sltr-capture-flow__step:first-child::before { display: none; }
    .sltr-capture-flow__dot { position: relative; z-index: 1; display: flex; width: 28px; height: 28px; align-items: center; justify-content: center; border: 2px solid #d1d5db; border-radius: 999px; background: #fff; color: #6b7280; font-size: 11px; font-weight: 700; }
    .sltr-capture-flow__name { margin-top: 6px; color: #6b7280; font-size: 10px; font-weight: 600; line-height: 1.2; }
    .sltr-capture-flow__step.is-current::before { background: #bef264; }
    .sltr-capture-flow__step.is-current .sltr-capture-flow__dot { border-color: #65a30d; background: #65a30d; box-shadow: 0 0 0 4px rgba(101, 163, 13, .18); color: #fff; }
    .sltr-capture-flow__step.is-current .sltr-capture-flow__name { color: #1f2937; }
    @media (max-width: 700px) { .sltr-capture-flow__steps { grid-template-columns: repeat(3, minmax(0, 1fr)); row-gap: 18px; } .sltr-capture-flow__step:nth-child(4)::before { display: none; } }
</style>
<div id="registration-dialog"
    class="dialog-backdrop hidden transition-opacity duration-300 ease-in-out z-50 fixed inset-0 flex items-center justify-center bg-black/50">
    <div class="dialog-content bg-white rounded-xl shadow-2xl w-full flex flex-col m-4 animate-scale-in overflow-hidden"
        style="max-width: 65vw; max-height: 98vh;">
        <!-- Header -->
        <div
            class="px-6 py-4 border-b border-gray-100 flex items-center justify-between bg-white sticky top-0 z-10">
            <div>
                <h2 id="dialog-title" class="text-xl font-bold text-gray-800">Capture Instrument</h2>
                <p id="dialog-subtitle" class="text-sm text-gray-500 mt-0.5">Enter the details for the new instrument</p>
            </div>
            <button type="button" id="dialog-close-btn"
                class="p-2 hover:bg-gray-100 rounded-lg transition-colors text-gray-400 hover:text-gray-600"
                aria-label="Close">
                <i data-lucide="x" class="h-6 w-6"></i>
            </button>
        </div>

        <form id="registration-form" class="flex-1 overflow-y-auto custom-scrollbar">
            <div class="p-6 space-y-6">
                @csrf
                <input type="hidden" id="instrument_id" name="instrument_id" value="{{ $record->id ?? '' }}">

                {{-- Kept directly below the capture header, rather than inside the
                     registration summary. It appears only for SLTR and mirrors the
                     numbered rail on the SLTR CofO Workflow screens. --}}
                @if ($showCaptureBanner ?? false)
                <section id="sltr-cofo-workflow-card" class="sltr-capture-flow hidden" aria-label="SLTR Certificate of Occupancy workflow" aria-hidden="true">
                    <div class="sltr-capture-flow__head">
                        <div>
                            <p class="sltr-capture-flow__kicker">SLTR CofO workflow</p>
                            <p class="sltr-capture-flow__copy">This capture is the CofO Registration stage. Continue the remaining stages from SLTR.</p>
                        </div>
                        <span class="sltr-capture-flow__current">Current: CofO Registration</span>
                    </div>
                    <ol class="sltr-capture-flow__steps" aria-label="SLTR CofO stages">
                        @foreach (['RofO', 'CofO Registration', 'Front Page', 'Title Deed Plan', 'Merge', 'Original'] as $stage)
                            <li class="sltr-capture-flow__step" data-sltr-cofo-stage="{{ $stage }}">
                                <span class="sltr-capture-flow__dot">{{ $loop->iteration }}</span>
                                <span class="sltr-capture-flow__name">{{ $stage }}</span>
                            </li>
                        @endforeach
                    </ol>
                </section>
                @endif
                
                <!-- Subtype Selection (Initially hidden) -->
                <div id="subtype-selector-container" class="hidden space-y-4 mb-6 p-4 bg-blue-50/50 rounded-xl border border-blue-100/50">
                    <div class="flex items-start gap-3">
                        <div class="mt-1 bg-blue-500/10 p-2 rounded-lg">
                            <i data-lucide="info" class="h-4 w-4 text-blue-600"></i>
                        </div>
                        <div class="flex-1">
                            <label class="text-sm font-semibold text-blue-900 mb-1 block">Select Specific Instrument Subtype</label>
                            <p class="text-xs text-blue-700/80 mb-3 leading-relaxed">
                                Some instruments share the same legal foundation but differ in structure or parties involved. 
                                Please select the correct subtype to ensure accurate documentation and processing.
                            </p>
                            <div class="relative group">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <i data-lucide="layers" class="h-4 w-4 text-blue-400 group-focus-within:text-blue-500 transition-colors"></i>
                                </div>
                                <select id="instrument-subtype-select" 
                                    class="w-full pl-9 pr-3 py-2.5 bg-white border border-blue-200 rounded-lg text-sm text-gray-700 focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 outline-none transition-all cursor-pointer">
                                    <!-- Options injected via JS -->
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- PRA History Alert Placeholder -->
                <div id="pra-history-alert"
                    class="hidden bg-gradient-to-r from-indigo-50 to-blue-50 border border-indigo-100 p-4 mb-6 rounded-2xl shadow-sm animate-pulse-subtle flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <div class="bg-indigo-500/10 p-2 rounded-xl">
                            <i data-lucide="history" class="h-5 w-5 text-indigo-600"></i>
                        </div>
                        <div>
                            <p class="text-sm text-indigo-900 font-bold leading-none">History Found</p>
                            <p class="text-[11px] text-indigo-600/70 mt-1 font-medium italic">This file number has associated PRA & Instrument Registration record(s).</p>
                        </div>
                    </div>
                    <button type="button" id="btn-view-pra-history"
                        class="px-4 py-2 bg-indigo-600 text-white rounded-xl text-xs font-bold hover:bg-indigo-700 transition-all duration-200 shadow-sm flex items-center gap-2 hover:scale-105 active:scale-95">
                        <i data-lucide="eye" class="h-3.5 w-3.5"></i>
                        Explore History
                    </button>
                </div>
                
                {{-- Commissioned-file mode. Shown only when this dialog was opened to capture the
                     OP behind a file number that was already commissioned without one: submit
                     then writes the OP and its Transfer of Title instead of registering an
                     instrument. Driven by window.opCommissionedFileMode in instruments-capture.js. --}}
                <div id="opcf-band" class="hidden bg-blue-50/60 border border-blue-200 rounded-2xl p-4 mb-6">
                    <div class="flex items-start gap-3 mb-3">
                        <div class="bg-blue-500/10 p-2 rounded-lg">
                            <i data-lucide="file-check-2" class="h-4 w-4 text-blue-600"></i>
                        </div>
                        <div>
                            <p class="text-sm font-bold text-blue-900 leading-none">Already-Commissioned File</p>
                            <p class="text-[11px] text-blue-700/80 mt-1">
                                Pick the commissioned file this OP belongs to. Saving writes one PRA row —
                                the Occupancy Permit itself, on this file number. No Transfer of Title is
                                created: the permit is simply being recorded, the holder is not changing.
                            </p>
                        </div>
                    </div>
                    <div class="flex flex-wrap items-end gap-3">
                        <div class="flex-1 min-w-[16rem]">
                            <label for="opcf-file-no" class="text-xs font-semibold text-gray-800 mb-1 block">
                                Commissioned File Number <span class="text-rose-500">*</span>
                            </label>
                            {{-- Disabled: the number is only ever set by the Select picker, which
                                 runs the lookup itself — there is nothing to type or confirm. --}}
                            <div class="flex items-center gap-2">
                                <input type="text" id="opcf-file-no" placeholder="No file selected" disabled
                                    class="flex-1 px-3 py-2 bg-gray-100 border border-gray-300 rounded-lg text-gray-700 font-mono text-sm uppercase">
                                <button type="button" id="opcf-select-btn"
                                    class="px-3 py-2 bg-white text-blue-600 font-medium rounded-lg border border-blue-200 hover:bg-blue-50 text-sm whitespace-nowrap">
                                    Select
                                </button>
                            </div>
                        </div>
                    </div>

                    <div id="opcf-result" class="hidden mt-3 grid grid-cols-1 md:grid-cols-3 gap-4 text-xs">
                        <div>
                            <div class="text-gray-400 font-semibold uppercase tracking-wide text-[10px]">Commissioned To</div>
                            <div id="opcf-file-name" class="font-semibold text-gray-800 mt-0.5">—</div>
                        </div>
                        <div>
                            <div class="text-gray-400 font-semibold uppercase tracking-wide text-[10px]">Prop ID</div>
                            <div id="opcf-prop-id" class="font-mono text-gray-700 mt-0.5">—</div>
                        </div>
                        <div>
                            <div class="text-gray-400 font-semibold uppercase tracking-wide text-[10px]">Plot / Location</div>
                            <div id="opcf-plot" class="text-gray-700 mt-0.5">—</div>
                        </div>
                    </div>
                    <div id="opcf-error" class="hidden mt-3 rounded-lg border border-rose-300 bg-rose-50 px-3 py-2 text-xs text-rose-800"></div>
                </div>

                @php
                    $storeRoute = isset($isEdit) && $isEdit ? route('instruments.update', $record->id) : route('instruments.store');
                    $method = isset($isEdit) && $isEdit ? 'PUT' : 'POST';
                @endphp

                <input type="hidden" id="storeUrl" value="{{ $storeRoute }}" />
                <input type="hidden" id="formMethod" value="{{ $method }}" />
                <input type="hidden" id="generateParticularsUrl"
                    value="{{ route('instruments.generateParticulars') }}" />

                <!-- Hidden fields for file number storage -->
                <input type="hidden" id="hidden_mlsFNo" name="mlsFNo" value="{{ $record->mlsFNo ?? '' }}">
                <input type="hidden" id="hidden_kangisFileNo" name="kangisFileNo" value="{{ $record->kangisFileNo ?? '' }}">
                <input type="hidden" id="hidden_NewKANGISFileno" name="NewKANGISFileno" value="{{ $record->NewKANGISFileno ?? '' }}">
                <input type="hidden" id="hidden_fileno" name="fileno" value="{{ $record->fileno ?? '' }}">

                {{-- Deeds workflow for the selected file. Keep it ahead of the
                     registration summary so the officer sees the required
                     Valuation -> Consent -> Registration path before the card
                     that confirms what is about to be registered. Filled by
                     instruments-capture.js when the file or instrument type
                     changes; an outstanding consent also disables Capture &
                     Register, matching InstrumentController's save gate. --}}
                <div id="deeds-pipeline-strip"
                     class="mb-5 p-5 rounded-xl border border-gray-200 bg-white shadow-sm"
                     style="display:none"></div>


                {{--
                    OP Type is no longer picked inside the form: it is asked once, as a prompt,
                    the moment "Occupancy Permit (OP)" is chosen on the instrument-type screen
                    (promptForOpType() in instruments-capture.js). The radios stay in the DOM —
                    permanently hidden — because they remain the value the whole capture reads
                    from (input[name="op_type"]:checked) and what the form posts. The banner
                    below shows the answer and offers "Change" to re-open the prompt.
                --}}
                <div id="op-type-container" class="hidden">
                    <input type="radio" id="op_type_resettlement" name="op_type" value="OP Resettlement" class="hidden">
                    <input type="radio" id="op_type_direct_allocation" name="op_type" value="OP Direct Allocation" class="hidden">
                </div>

                {{-- Rendered only on the dedicated capture page. The OSS Applications and
                     Land/MLPP pages include this same modal for a quick side capture and
                     pass showCaptureBanner=false (the default), which also silences the
                     matching popup in instruments-capture.js. --}}
                @if ($showCaptureBanner ?? false)
                <div id="op-type-summary" class="capture-banner hidden mb-6 px-4 py-3 rounded-lg">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p id="op-type-summary-lead" class="capture-banner__lead">
                                You are about to register
                            </p>
                            <p class="capture-banner__line">
                                <span id="op-type-summary-icon" class="capture-banner__icon">
                                    <i data-lucide="badge-check" class="h-4 w-4"></i>
                                </span>
                                <span>
                                    Occupancy Permit (OP) Type:
                                    <strong id="op-type-summary-value" class="font-semibold">Not selected</strong>
                                </span>
                            </p>
                            {{-- Read-only peek at the vault (instruments.nextRegistrationParticulars):
                                 the number is only consumed when the instrument is registered.
                                 showOpTypeNotice() in instruments-capture.js says the same thing
                                 in a popup before the form opens; this card is the reminder that
                                 stays on screen while the capture is filled in. --}}
                            <p class="capture-banner__meta capture-banner__meta--volume">
                                Volume:
                                <strong id="op-type-reg-particulars" class="capture-banner__volume font-mono">&mdash;</strong>
                            </p>
                        </div>
                        <button type="button" id="op-type-change-btn" class="capture-banner__change">
                            Change
                        </button>
                    </div>
                </div>
                @endif

                {{--
                    Certificate of Occupancy variant + type.

                    The three tabs pick which KIND of CofO is being captured. Each
                    carries the exact instrument_type string the backend stores;
                    InstrumentCaptureService::syncCofoStaging() maps those onto the
                    CofO_staging title types (C of O / SLTR C of O / ST C of O), and
                    everything else that branches on the type — registration
                    numbering, the shared vault, isCofoInstrument() — matches on the
                    "Certificate of Occupancy" substring, so all three keep working.

                    "Certificate of Occupancy Type" is per-variant. Regular offers the
                    canonical issuing-authority list shared with File Indexing and the
                    property card (Land CofO / KANGIS CofO - Old / KANGIS CofO - New,
                    i.e. partials/cofo_type_options minus the SLTR/ST entries, which are
                    the other two variants here); SLTR and ST offer New / Old C of O.
                    The list is rebuilt on every variant switch and the value cleared
                    when the new variant does not offer it, so a type picked on Regular
                    can never ride along with an SLTR or ST capture. It posts as
                    cofo_type into instrument_capture.cofo_type and CofO_staging.cofo_type.
                --}}
                {{-- Tailwind 2 does not ship a focus-visible variant, so the keyboard
                     focus ring is plain CSS. A mouse click leaves no ring; tabbing does. --}}
                <style>
                    .cofo-variant-tab:focus { outline: none; }
                    .cofo-variant-tab:focus-visible { outline: 2px solid currentColor; outline-offset: 2px; }
                </style>

                {{--
                    The variant tabs and the C of O Type below are no longer operated here: both
                    are asked in one prompt the moment "Certificate of Occupancy" is chosen
                    (promptForCofoSelection() in instruments-capture.js). The markup stays in the
                    DOM — permanently hidden — because setCofoVariant() drives it and #cofoType is
                    the field the form posts; #cofo-summary shows the answer with a "Change" link.
                --}}
                @if ($showCaptureBanner ?? false)
                <div id="cofo-summary" class="capture-banner hidden mb-6 px-4 py-3 rounded-lg">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p id="cofo-summary-lead" class="capture-banner__lead">
                                You are about to register
                            </p>
                            <p class="capture-banner__line">
                                <span id="cofo-summary-icon" class="capture-banner__icon">
                                    <i data-lucide="award" class="h-4 w-4"></i>
                                </span>
                                <span>
                                    Certificate of Occupancy:
                                    <strong id="cofo-summary-value" class="font-semibold">Regular</strong>
                                </span>
                            </p>
                            <p class="capture-banner__meta capture-banner__meta--volume">
                                Volume:
                                <strong id="cofo-reg-particulars" class="capture-banner__volume font-mono">&mdash;</strong>
                            </p>
                        </div>
                        <button type="button" id="cofo-change-btn" class="capture-banner__change">
                            Change
                        </button>
                    </div>
                </div>
                @endif

                {{--
                    The grouped instrument cards - "Deed of Assignment/Gift", "Deed of
                    Mortgage/Tripartite Mortgage" and "Deed of Surrender and Release/Power of
                    Attorney/Irrevocable Power of Attorney" - each stand for more than one
                    instrument. Which one is being captured is asked in a prompt the moment the
                    card is clicked (promptForInstrumentChoice() in instruments-capture.js), the
                    same as OP and CofO; this banner is what shows the answer afterwards, with
                    "Change" to re-open the prompt.

                    Unlike OP and CofO the answer is not a sub-field: each member is its own
                    instrument type, so it is instrument_type itself that the choice sets.
                    #instrument-subtype-select above stays hidden wherever this banner renders.
                --}}
                @if ($showCaptureBanner ?? false)
                <div id="instrument-choice-summary" class="capture-banner hidden mb-6 px-4 py-3 rounded-lg">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="capture-banner__lead">
                                You are about to register
                            </p>
                            <p class="capture-banner__line">
                                <span class="capture-banner__icon">
                                    <i data-lucide="file-signature" class="h-4 w-4"></i>
                                </span>
                                <span>
                                    <span id="instrument-choice-summary-label">Instrument:</span>
                                    <strong id="instrument-choice-summary-value" class="font-semibold">Not selected</strong>
                                </span>
                            </p>
                            {{-- Read-only peek at the vault, exactly as the OP and CofO banners
                                 do: the number is only consumed when the instrument is registered. --}}
                            <p class="capture-banner__meta capture-banner__meta--volume">
                                Volume:
                                <strong id="instrument-choice-reg-particulars" class="capture-banner__volume font-mono">&mdash;</strong>
                            </p>
                        </div>
                        <button type="button" id="instrument-choice-change-btn" class="capture-banner__change">
                            Change
                        </button>
                    </div>
                </div>
                @endif

                {{--
                    Fill a missing registration number.

                    A registration deleted outright leaves a serial in the paper book that
                    nothing in KLAES accounts for. Recapturing that instrument the normal way
                    burns the NEXT number and makes the hole permanent, so this panel offers
                    the hole instead — and the vault stays exactly where it is.

                    The list is not "everything from 1": each register was taken over
                    part-way through the manual workflow (Deed of Assignment picks up at
                    121/121/73, Certificate of Occupancy at 266/266/173), so the numbers on
                    offer run from the first number that register ever issued in KLAES up to
                    the one its vault stands on now. MissingRegistrationParticularsService
                    works that out, per vault — which is what makes the answer follow the
                    instrument type AND its sub-type, since the two Occupancy Permit
                    registers paginate separately.

                    The answer is given in the "You are about to register" popup, next to the
                    volume it is overriding — that is the moment the officer is deciding which
                    number this instrument takes, and it is the only screen every instrument
                    with a vault passes through. The panel below is what remains in the form:
                    a standing reminder of the number chosen, and — for the few types that
                    open no popup (Devolution Order) — the picker itself.

                    Rendered only on the dedicated capture page: the side-capture pages
                    (OSS Applications, Land/MLPP) hand-type their particulars already.
                --}}
                @if ($showCaptureBanner ?? false)
                {{-- What the capture actually posts. Hidden inputs rather than the checkbox
                     itself, because the answer now arrives from the popup as often as from
                     the panel; writeBackfillState() in instruments-capture.js is the one
                     place that sets all three. --}}
                <input type="hidden" id="backfill_registration" name="backfill_registration" value="0">
                <input type="hidden" id="backfill_volume_no" name="backfill_volume_no" value="">
                <input type="hidden" id="backfill_serial_no" name="backfill_serial_no" value="">

                <div id="backfill-panel" class="backfill-panel hidden mb-6">
                    {{-- Hidden whenever the popup owns the answer, leaving only the chosen
                         line below — two live copies of one question is how they disagree. --}}
                    <div id="backfill-controls">
                        <label for="backfill_toggle" class="backfill-panel__toggle">
                            <input type="checkbox" id="backfill_toggle"
                                class="mt-0.5 h-4 w-4 rounded border-gray-300 text-amber-600 focus:ring-amber-500">
                            <span>
                                <span class="backfill-panel__title">
                                    Fill a missing registration number
                                </span>
                                {{-- Held back for now. Restore by uncommenting: the JS that fills
                                     both lines is still in place and simply skips whichever
                                     element is absent, so nothing else has to change.

                                <span id="backfill-panel-hint" class="backfill-panel__hint">
                                    Use this only to re-enter an instrument whose registration was deleted.
                                </span>
                                --}}
                            </span>
                        </label>

                        {{-- Where this register starts and where it stands: the two facts that
                             decide which numbers can be missing at all. Held back with the hint
                             above; uncomment to bring it back.

                        <p id="backfill-range" class="backfill-panel__range hidden"></p>
                        --}}

                        <div id="backfill-fields" class="backfill-panel__fields hidden">
                            <div>
                                <label for="backfill_volume_select" class="backfill-panel__label">Volume</label>
                                <select id="backfill_volume_select"
                                    class="w-full px-3 py-2 bg-white border border-gray-300 rounded-lg text-sm focus:ring-1 focus:ring-amber-500 outline-none">
                                </select>
                            </div>
                            <div>
                                <label for="backfill_serial_select" class="backfill-panel__label">Missing number</label>
                                <select id="backfill_serial_select"
                                    class="w-full px-3 py-2 bg-white border border-gray-300 rounded-lg text-sm font-mono focus:ring-1 focus:ring-amber-500 outline-none">
                                </select>
                            </div>
                        </div>
                    </div>

                    {{-- What the survey found, in words, whether or not there is anything to
                         fill. A register with no gaps used to render nothing at all, which
                         reads as a broken feature rather than as the answer it is: "no
                         missing numbers between 89/89/22 and 92/92/22" is the answer. It
                         also says when the register's starting point is only INFERRED —
                         then a number deleted from the very start of the book cannot be
                         seen at all, and the officer should not read silence as proof. --}}
                    <p id="backfill-note" class="backfill-panel__note"></p>

                    <p id="backfill-chosen" class="backfill-panel__chosen hidden"></p>
                </div>
                @endif

                <div id="cofo-variant-container" class="hidden mb-6">
                    <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">
                        Certificate of Occupancy Variant
                    </label>

                    <div class="flex flex-wrap items-center gap-x-3 gap-y-2">
                        {{-- A segmented control rather than folder tabs: it stays legible at
                             three short labels and needs no border-merging with the panel
                             below, which is what made the first version look detached.

                             Each variant carries its own accent so the three are told apart by
                             colour as well as position. The accent classes are built from
                             $accent — safe here because Tailwind is loaded as the full CDN
                             build (no purge step to miss a dynamically composed name). --}}
                        <div class="inline-flex items-center gap-1 p-1 bg-gray-100 rounded-lg border border-gray-200"
                            role="tablist" aria-label="Certificate of Occupancy variant">
                            {{-- ST is deliberately absent. An ST Certificate of Occupancy is
                                 produced through Sectional Titling → Certificate → CofO Workflow,
                                 where the front page and the TDP are paired and registered
                                 together. Registering one by hand here would mint a number for a
                                 certificate that has no back page and no workflow record.

                                 Historical ST registrations are untouched; only new ones are
                                 refused. The server side agrees — see InstrumentRegistrationController. --}}
                            @foreach ([
                                ['regular', 'Regular', 'Certificate of Occupancy', 'red'],
                                ['sltr', 'SLTR', 'SLTR Certificate of Occupancy', 'green'],
                            ] as [$variant, $tabLabel, $instrumentType, $accent])
                                {{-- Regular carries the active classes on first paint;
                                     setCofoVariant() in instruments-capture.js owns them after that. --}}
                                <button type="button"
                                    class="cofo-variant-tab px-5 py-2 text-sm font-semibold rounded-md transition-all {{ $variant === 'regular' ? "bg-{$accent}-600 text-white shadow-sm" : "text-gray-600 hover:text-{$accent}-700 hover:bg-white" }}"
                                    data-variant="{{ $variant }}" data-instrument-type="{{ $instrumentType }}"
                                    data-accent="{{ $accent }}"
                                    role="tab" aria-selected="{{ $variant === 'regular' ? 'true' : 'false' }}"
                                    aria-controls="cofo-variant-panel">{{ $tabLabel }}</button>
                            @endforeach
                        </div>

                        {{-- Shown for SLTR / ST, where the panel below is empty — it explains
                             the absence of the C of O Type instead of leaving a blank box.
                             setCofoVariant() writes into the inner span, so the icon survives. --}}
                        <p id="cofo-variant-note" class="hidden items-center gap-1.5 text-xs text-gray-500">
                            <i data-lucide="info" class="h-3.5 w-3.5 flex-shrink-0 text-gray-400"></i>
                            <span id="cofo-variant-note-text"></span>
                        </p>
                    </div>

                    <div id="cofo-variant-panel" role="tabpanel">
                        <!-- Certificate of Occupancy Type Selection (Regular only) -->
                        {{-- Neutral, not tinted with the Regular accent. This panel only ever
                             shows on Regular, and a red-tinted box around a required dropdown
                             reads as a validation error. --}}
                        <div id="coo-type-container" class="mt-3 bg-gray-50 p-3 rounded-lg border border-gray-200">
                            <label for="cofoType" class="block text-xs font-semibold text-gray-600 uppercase tracking-wide mb-1.5">Certificate of Occupancy Type</label>
                            {{-- First paint carries the Regular options; setCofoVariant() rebuilds
                                 the list for whichever variant is chosen (SLTR and ST offer
                                 New C of O / Old C of O instead). --}}
                            <select id="cofoType" name="cofo_type"
                                class="w-full max-w-sm px-3 py-2 bg-white border border-gray-300 rounded-lg text-sm focus:ring-1 focus:ring-gray-400 outline-none">
                                <option value="">Select C of O Type</option>
                                <option value="Land CofO">Land CofO</option>
                                <option value="KANGIS CofO - Old">KANGIS CofO - Old</option>
                                <option value="KANGIS CofO - New">KANGIS CofO - New</option>
                            </select>
                        </div>
                    </div>
                </div>

                @if(!(isset($isView) && $isView))
                    <div class="mb-4 flex justify-end gap-2">
                        <button type="button" id="hard-refresh-registration-form-btn"
                            class="hidden px-3 py-2 bg-white text-blue-700 font-medium rounded-lg border border-blue-300 hover:bg-blue-50 text-sm whitespace-nowrap">
                            Hard Fresh
                        </button>
                        <button type="button" id="reset-registration-form-btn"
                            class="px-3 py-2 bg-white text-amber-700 font-medium rounded-lg border border-amber-300 hover:bg-amber-50 text-sm whitespace-nowrap">
                            Reset Form
                        </button>
                    </div>
                @endif

                <!-- File Number, Registration & Instrument Settings -->
                <div class="grid grid-cols-3 gap-6">
                    <!-- File Number Section -->
                    <div class="border-l-4 border-blue-500 pl-4">
                        <h3 id="fileno-label" class="text-sm font-semibold text-gray-800 mb-4">File Number</h3>

                        {{--
                            File Number is picked straight from a Select2 dropdown backed by the
                            INDEXING table (api.file-numbers.indexing-search) — no modal, no manual
                            entry. The dropdown searches every registry column server-side and pages
                            as you scroll, so it reaches every indexed file without ever putting tens
                            of thousands of <option> nodes in the DOM.

                            #display_fileno stays as the hidden source of truth: the whole capture
                            reads and writes it (temp-number allocation, lookups, duplicate check),
                            and syncFileNumberSelect() mirrors those writes back into the dropdown.
                        --}}
                        @php
                            $seedFileNo = ($record->mlsFNo ?? $record->kangisFileNo ?? $record->NewKANGISFileno ?? $record->fileno ?? '');
                        @endphp
                        <div class="relative">
                            <select id="file_number_select" class="w-full text-sm"
                                    data-placeholder="Search a file number…"
                                    @if(isset($isView) && $isView) disabled @endif>
                                @if($seedFileNo)
                                    <option value="{{ $seedFileNo }}" selected>{{ $seedFileNo }}</option>
                                @endif
                            </select>
                        </div>
                        <input type="hidden" id="display_fileno" value="{{ $seedFileNo }}">
                        <p class="mt-2 text-xs text-gray-400 truncate" id="file_source_info">
                            @if(isset($record))
                                Linked to property records.
                            @else
                                Select a file to associate.
                            @endif
                        </p>

                        <!-- OP Serial Number (Hidden by default) -->
                        <div id="op_serial_number_container" class="mt-4 hidden">
                            <label for="op_serial_number" class="text-xs font-semibold text-gray-800 mb-1 block">OP SerialNo <span class="text-red-500">*</span></label>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <i data-lucide="hash" class="h-4 w-4 text-gray-400"></i>
                                </div>
                                <input type="text" id="op_serial_number" name="op_serial_number"
                                    class="w-full pl-9 pr-3 py-2 bg-white border border-gray-300 rounded-lg text-gray-700 text-sm focus:ring-1 focus:ring-blue-500"
                                    oninput="this.value = this.value.replace(/[^0-9]/g, '').replace(/^0+/, '')"
                                    placeholder="Enter OP SerialNo" value="{{ $record->op_serial_number ?? '' }}">
                            </div>
                        </div>

                        {{-- OP Type picker for the side-capture pages (OSS Applications and
                             Land/MLPP). Those pages do not render the capture banner, so without
                             this select the chosen OP type is invisible once the opening prompt
                             is dismissed. The hidden radios above stay the source of truth and
                             the posted value; this select only mirrors them (both ways). --}}
                        @if (!($showCaptureBanner ?? false))
                        <div id="op_type_select_container" class="mt-4 hidden">
                            <label for="op_type_select" class="text-xs font-semibold text-gray-800 mb-1 block">OP Type</label>
                            <select id="op_type_select"
                                class="w-full px-3 py-2 bg-white border border-gray-300 rounded-lg text-sm focus:ring-1 focus:ring-blue-500 outline-none"
                                @if(isset($isView) && $isView) disabled @endif>
                                <option value="">Select OP Type</option>
                                <option value="OP Resettlement">Resettlement</option>
                                <option value="OP Direct Allocation">Direct Allocation</option>
                            </select>
                        </div>
                        @endif

                        <!-- Land Use (Purpose) Split Dropdowns -->
                        <div id="land_use_section" class="mt-4 grid grid-cols-2 gap-3">
                            <div>
                                <label for="land_use_id" class="text-xs font-semibold text-gray-800 mb-1 block" required>Land Use</label>
                                <select id="land_use_id" name="land_use_id" class="w-full px-3 py-2 bg-white border border-gray-300 rounded-lg text-sm focus:ring-1 focus:ring-blue-500 outline-none">
                                    <option value="">Select Land Use</option>
                                    <!-- Populated via JS -->
                                </select>
                            </div>
                            <div>
                                <label for="purpose_id" class="text-xs font-semibold text-gray-800 mb-1 block">Purpose</label>
                                <select id="purpose_id" name="purpose_id" class="w-full px-3 py-2 bg-white border border-gray-300 rounded-lg text-sm focus:ring-1 focus:ring-blue-500 outline-none" required>
                                    <option value="">Select Purpose</option>
                                    <!-- Populated via JS based on Land Use -->
                                </select>
                            </div>
                        </div>

                        <!-- Keep hidden inputs for backward compatibility and string values -->
                        <input type="hidden" id="land_use" name="land_use" value="{{ $record->land_use ?? '' }}">
                        <input type="hidden" id="purpose" name="purpose" value="{{ $record->purpose ?? '' }}">
                        <input type="hidden" id="initial_land_use_id" value="{{ $record->land_use_id ?? '' }}">
                        <input type="hidden" id="initial_purpose_id" value="{{ $record->purpose_id ?? '' }}">
                        <div style="display:none">
                        <span id="land_use_display" class="hidden"></span> 
                        </div> 
                    </div>

                    <!-- Entry Date Section -->
                    <div class="border-l-4 border-gray-400 pl-4">
                        <h3 class="text-sm font-semibold text-gray-800 mb-4">Transaction Date</h3>
                        <div class="relative mb-4">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <i data-lucide="calendar-days" class="h-4 w-4 text-gray-400"></i>
                            </div>
                            <input
                                type="date"
                                id="transactionDate"
                                name="transactionDate"
                                class="w-full pl-10 px-4 py-2.5 bg-white border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-100 focus:border-blue-400 outline-none transition-all text-sm"
                                value="{{ isset($record->transaction_date) ? \Carbon\Carbon::parse($record->transaction_date)->format('Y-m-d') : '' }}">
                        </div>

                        <h3 class="text-sm font-semibold text-gray-800 mb-4">Entry Date</h3>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <i data-lucide="calendar" class="h-4 w-4 text-gray-400"></i>
                            </div>
                            <input
                                type="date"
                                id="entryDate"
                                name="entryDate"
                                readonly
                                tabindex="-1"
                                aria-disabled="true"
                                class="w-full pl-10 px-4 py-2.5 bg-gray-100 border border-gray-300 rounded-lg text-gray-500 cursor-not-allowed outline-none text-sm"
                                value="{{ isset($record->instrument_date) ? \Carbon\Carbon::parse($record->instrument_date)->format('Y-m-d') : '' }}">
                        </div>

                        {{-- OP Registration Particulars. Rendered ONLY on the side-capture pages
                             (OSS Applications and Land/MLPP), where an existing paper permit is
                             being recorded and its Serial / Page / Volume are hand-typed. The
                             dedicated capture page registers the instrument, so the vault
                             allocates the particulars there and these inputs must not show. --}}
                        @if (!($showCaptureBanner ?? false))
                        <div id="op-registration-details" class="mt-4 hidden">
                            <p class="text-xs font-semibold text-gray-800 mb-2">Registration Details</p>
                            <div class="grid grid-cols-3 gap-3">
                                <div>
                                    <label for="serial_no" class="text-[11px] font-semibold text-gray-500 mb-1 block">Serial No</label>
                                    <input type="text" id="serial_no" name="serial_no" inputmode="numeric"
                                        class="w-full px-3 py-2 bg-white border border-gray-300 rounded-lg text-sm focus:ring-1 focus:ring-blue-500"
                                        placeholder="Serial No">
                                </div>
                                <div>
                                    <label for="reg_page_no_display" class="text-[11px] font-semibold text-gray-500 mb-1 block">Page No</label>
                                    <input type="text" id="reg_page_no_display" inputmode="numeric"
                                        class="w-full px-3 py-2 bg-gray-100 border border-gray-300 rounded-lg text-sm text-gray-500 cursor-not-allowed"
                                        placeholder="Auto-filled" readonly aria-disabled="true">
                                    <input type="hidden" id="reg_page_no" name="page_no">
                                </div>
                                <div>
                                    <label for="volume_no" class="text-[11px] font-semibold text-gray-500 mb-1 block">Vol No</label>
                                    <input type="text" id="volume_no" name="volume_no" inputmode="numeric"
                                        class="w-full px-3 py-2 bg-white border border-gray-300 rounded-lg text-sm focus:ring-1 focus:ring-blue-500"
                                        placeholder="Vol No">
                                </div>
                            </div>

                            <div class="mt-3">
                                <label for="registration_number" class="text-[11px] font-semibold text-gray-500 mb-1 block">Registration Particulars</label>
                                <input type="text" id="registration_number" name="registration_number" readonly
                                    class="w-full px-3 py-2 bg-gray-100 border border-gray-300 rounded-lg text-sm text-gray-600"
                                    placeholder="Serial/Page/Vol">
                            </div>

                            <div class="mt-3 grid grid-cols-2 gap-3">
                                <div>
                                    <label for="registrationDate" class="text-[11px] font-semibold text-gray-500 mb-1 block">Reg Date</label>
                                    <input type="date" id="registrationDate" name="registrationDate"
                                        class="w-full px-3 py-2 bg-white border border-gray-300 rounded-lg text-sm focus:ring-1 focus:ring-blue-500"
                                        value="{{ old('registrationDate', isset($record->reg_date) ? substr((string) $record->reg_date, 0, 10) : '') }}">
                                </div>
                                <div>
                                    <label for="registrationTime" class="text-[11px] font-semibold text-gray-500 mb-1 block">Reg Time</label>
                                    <input type="time" id="registrationTime" name="registrationTime"
                                        class="w-full px-3 py-2 bg-white border border-gray-300 rounded-lg text-sm focus:ring-1 focus:ring-blue-500"
                                        value="{{ old('registrationTime', $record->reg_time ?? '') }}">
                                </div>
                            </div>
                        </div>
                        @endif
                    </div>

                    <!-- Instrument Info Display Section -->
                    <div id="instrument-type-info-display"
                        class="border-l-4 border-gray-200 pl-4 h-full flex flex-col justify-center bg-gray-50 rounded-r-lg pr-2">
                        @if(isset($record))
                            <div class="flex items-center gap-2">
                                <div class="w-8 h-8 rounded-full bg-blue-100 flex items-center justify-center text-blue-600">
                                    <i data-lucide="file-text" class="h-4 w-4"></i>
                                </div>
                                <div>
                                    <h4 class="text-sm font-bold text-gray-800">{{ $record->instrument_type }}</h4>
                                    <p class="text-[10px] text-gray-500 uppercase tracking-wider">Selected Instrument</p>
                                </div>
                            </div>
                        @else
                            <p class="text-xs text-gray-400 italic">Select an instrument type to view details.</p>
                        @endif
                    </div>
                </div>

                <hr class="my-6 border-gray-200">

                <!-- Parties & Instrument Details (3 columns) -->
                <div class="grid grid-cols-3 gap-6">
                    <!-- First Party -->
                    <div class="border-l-4 border-green-500 pl-4">
                        <h3 id="first-party-title" class="text-sm font-semibold text-gray-800 mb-4">{{ isset($record) ? 'Party 1' : 'Grantor' }}</h3>

                        <div class="space-y-3">
                            <x-instrument-input id="firstPartyName" label="" icon="user" placeholder="Name"
                                value="{{ $record->party_1_name ?? '' }}" />

                            <div class="grid grid-cols-2 gap-3">
                                <x-instrument-input id="firstPartyHouseNo" label="" icon="home" placeholder="House No"
                                    value="{{ $record->party_1_house_no ?? '' }}" />
                                <x-instrument-input id="firstPartyPlotNo" label="" icon="hash" placeholder="Plot No"
                                    value="{{ $record->party_1_plot_no ?? '' }}" />
                            </div>

                            <div id="first-party-row-1" class="grid grid-cols-2 gap-3">
                                <div>
                                    <x-instrument-select id="firstPartyStreet" label="" icon="map-pin"
                                        :options="$streetNames->merge([(object)['name' => 'Others']])"
                                        placeholder="Select Street"
                                        value="{{ old('firstPartyStreet', $record->party_1_address ?? '') }}"
                                        optionValue="name" optionLabel="name" />
                                    <div id="manual_firstPartyStreet_container" class="mt-2 hidden">
                                        <x-instrument-input id="manual_firstPartyStreet" name="manual_firstPartyStreet" label="" icon="edit-3"
                                            placeholder="Specify Street" />
                                    </div>
                                </div>
                                <div id="district-container">
                                    <x-instrument-select id="firstPartyDistrict" label="" icon="map-pin"
                                        :defer-options="$deferDistrictOptions ?? false" :options="$districts->merge([(object)['name' => 'Others']])"
                                        placeholder="Select District" value="{{ $record->party_1_district ?? '' }}"
                                        optionValue="name" optionLabel="name" />
                                    <div id="manual_firstPartyDistrict_container" class="mt-2 hidden">
                                        <x-instrument-input id="manual_firstPartyDistrict" name="manual_firstPartyDistrict" label="" icon="edit-3"
                                            placeholder="Specify District" />
                                    </div>
                                </div>
                            </div>

                            <div id="kano-grantor-fields" class="space-y-3">
                                <div id="first-party-row-2" class="grid grid-cols-2 gap-3">
                                    <x-instrument-select id="firstPartyLga" label="" icon="map"
                                        :options="[]" placeholder="LGA" value="{{ $record->party_1_lga ?? $record->party_1_city ?? '' }}" />
                                    <x-instrument-select id="firstPartyState" label="" icon="map-pin"
                                        :options="$states" placeholder="State" value="{{ $record->party_1_state ?? 'Kano' }}" />
                                </div>
                                <x-instrument-input id="firstPartyPhone" label="" icon="phone" :phone="true"
                                    placeholder="Phone Number" value="{{ $record->party_1_phone ?? '' }}" />
                            </div>
                        </div>
                    </div>

                    <!-- Second Party -->
                    <div class="border-l-4 border-amber-500 pl-4">
                        <h3 id="second-party-title" class="text-sm font-semibold text-gray-800 mb-4">{{ isset($record) ? 'Party 2' : 'Grantee' }}</h3>

                        <div class="space-y-3">
                            <x-instrument-input id="secondPartyName" label="" icon="user-plus"
                                placeholder="Name" value="{{ $record->party_2_name ?? '' }}" />

                            <div class="grid grid-cols-2 gap-3">
                                <x-instrument-input id="secondPartyHouseNo" label="" icon="home" placeholder="House No"
                                    value="{{ $record->party_2_house_no ?? '' }}" />
                                <x-instrument-input id="secondPartyPlotNo" label="" icon="hash" placeholder="Plot No"
                                    value="{{ $record->party_2_plot_no ?? '' }}" />
                            </div>

                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <x-instrument-select id="secondPartyStreet" label="" icon="map-pin"
                                        :options="$streetNames->merge([(object)['name' => 'Others']])"
                                        placeholder="Select Street"
                                        value="{{ old('secondPartyStreet', $record->party_2_address ?? '') }}"
                                        optionValue="name" optionLabel="name" />
                                    <div id="manual_secondPartyStreet_container" class="mt-2 hidden">
                                        <x-instrument-input id="manual_secondPartyStreet" name="manual_secondPartyStreet" label="" icon="edit-3"
                                            placeholder="Specify Street" />
                                    </div>
                                </div>
                                <div>
                                    <x-instrument-select id="secondPartyDistrict" label="" icon="map-pin"
                                        :defer-options="$deferDistrictOptions ?? false" :options="$districts->merge([(object)['name' => 'Others']])"
                                        placeholder="Select District" value="{{ $record->party_2_district ?? '' }}"
                                        optionValue="name" optionLabel="name" />
                                    <div id="manual_secondPartyDistrict_container" class="mt-2 hidden">
                                        <x-instrument-input id="manual_secondPartyDistrict" name="manual_secondPartyDistrict" label="" icon="edit-3"
                                            placeholder="Specify District" />
                                    </div>
                                </div>
                            </div>
                            <div class="grid grid-cols-2 gap-3">
                                <x-instrument-select id="secondPartyLga" label="" icon="map"
                                    :options="[]" placeholder="LGA" value="{{ $record->party_2_lga ?? $record->party_2_city ?? '' }}" />
                                <x-instrument-select id="secondPartyState" label="" icon="map-pin"
                                    :options="$states" placeholder="State" value="{{ $record->party_2_state ?? 'Kano' }}" />
                            </div>
                            <div class="grid grid-cols-2 gap-3">
                                <x-instrument-input id="secondPartyPhone" label="" icon="phone" :phone="true" :phone-required="true"
                                    placeholder="Phone Number" value="{{ $record->party_2_phone ?? '' }}" />
                                {{-- Options come from the `genders` lookup table, never hard-coded — see
                                     App\Models\Gender::options() and the <x-gender-select> component. The
                                     stored value is the NAME, not the id, to match file_indexings.gender.
                                     Backfilled from the selected file's indexing record: see
                                     backfillPropertyFromFile() in public/js/instruments-capture.js. --}}
                                <x-instrument-select id="secondPartyGender" label="" icon="users"
                                    :options="\App\Models\Gender::options()" placeholder="Select Gender *"
                                    value="{{ app(\App\Services\GenderNormalizer::class)->normalize($record->party_2_gender ?? null) }}" />
                            </div>
                        </div>
                    </div>

                    <!-- Instrument Sidebar -->
                    <div class="border-l-4 border-rose-500 pl-4 h-full">
                        <div id="instrument-sidebar" class="h-full">
                            <!-- Sidebar toggles will be inserted here -->
                        </div>
                    </div>
                </div>

                <hr class="my-6 border-gray-200">

                {{-- Row 2b: instrument-specific fields.

                     Sits above Property Details because on the registries that
                     show it these are the terms of the dealing itself (for a
                     Deed of Purchase: the amount paid and the receipt), which
                     belong with the parties rather than after the description
                     of the land.

                     Hidden for every Deeds instrument, so the order change is
                     invisible there. Revealed per host page via
                     InstrumentCaptureConfig.showAdditionalDetails; the heading
                     is overridden by additionalDetailsTitle. --}}
                <div id="additional-details-section" class="hidden border-l-4 border-sky-500 pl-4">
                    <h3 class="text-sm font-semibold text-gray-800 mb-4">Additional Details</h3>
                    <div id="instrument-fields" class="space-y-4">
                        @if(isset($record))
                            <!-- Pre-populate some fields if we have a record -->
                             <div class="grid grid-cols-3 gap-4">
                                <x-instrument-input id="rootRegNo" label="Registration Number" icon="file-text" value="{{ $record->root_reg_number ?? '' }}" />
                             </div>
                        @else
                            <div class="text-sm text-gray-500 italic py-2">Select an instrument type to view specific fields</div>
                        @endif
                    </div>
                </div>

                <hr class="my-6 border-gray-200">

                <!-- Row 2c: Property Details -->
                <div class="border-l-4 border-teal-500 pl-4">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-sm font-semibold text-gray-800">Property Details</h3>
                        <div class="flex items-center gap-2">
                            <input type="checkbox" id="surveyInfo" class="checkbox h-3.5 w-3.5 accent-teal-600" 
                                {{ (isset($record->survey_plan_no) || isset($record->lga) || isset($record->district)) ? 'checked' : '' }}>
                            <label for="surveyInfo"
                                class="text-xs font-medium text-teal-700 cursor-pointer">Include Survey
                                Info</label>
                        </div>
                    </div> 

                    <div class="grid grid-cols-2 gap-4">
                        <div class="space-y-3">
                            <div class="grid grid-cols-3 gap-3">
                                <x-instrument-input id="house_no" label="" icon="home" placeholder="House No"
                                    value="{{ old('house_no', $record->house_no ?? '') }}" />
                                <x-instrument-input id="plotNumber" label="" icon="hash"
                                    placeholder="Plot Number" value="{{ $record->plot_number ?? '' }}" />
                                <div>
                                    <div class="relative">
                                        <select id="tp_no" name="tp_no" class="tp-lookup-select w-full px-3 py-2.5 bg-white border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-100 focus:border-blue-400 outline-none transition-all text-sm">
                                            <option value="">TP No</option>
                                            @if(!empty($record->tp_no))
                                                <option value="{{ $record->tp_no }}" selected>{{ $record->tp_no }}</option>
                                            @endif
                                        </select>
                                    </div>
                                    <div id="manual_tp_no_container" class="mt-2 hidden">
                                        <x-instrument-input id="manual_tp_no" name="manual_tp_no" label="" icon="edit-3"
                                            placeholder="Enter TP No" value="{{ old('manual_tp_no') }}" />
                                    </div>
                                </div>
                            </div>
                            
                            <div class="grid grid-cols-3 gap-3">
                                <x-instrument-input id="size" label="" icon="maximize" placeholder="Size"
                                    value="{{ $record->size ?? '' }}" />
                                <x-instrument-input id="lpkn_no" label="" icon="file-text" placeholder="LPKN No" 
                                    value="{{ $record->lpkn_no ?? '' }}" />
                            </div>

                            <div>
                                <x-instrument-select id="streetName" label="" icon="map-pin" :options="$streetNames->merge([(object)['name' => 'Others']])"
                                    placeholder="Select Street Name" value="{{ old('streetName', $record->property_street_name ?? '') }}"
                                    optionValue="name" optionLabel="name" />
                                <div id="manual_street_container" class="mt-2 hidden">
                                    <x-instrument-input id="manual_street_name" name="manual_street_name" label="" icon="edit-3"
                                        placeholder="Enter Street Name" value="{{ old('manual_street_name') }}" />
                                </div>
                            </div>
                            
                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <x-instrument-select id="desc_district" label="" icon="map-pin" :defer-options="$deferDistrictOptions ?? false" :options="$districts->merge([(object)['name' => 'Others']])"
                                        placeholder="Select District " value="" optionValue="name" optionLabel="name" />
                                    <!-- Manual District Input -->
                                    <div id="manual_district_container" class="mt-2 hidden">
                                        <x-instrument-input id="manual_district" name="manual_district" label="" icon="edit-3" placeholder="Enter District Name" />
                                    </div>
                                </div>
                                {{-- Multi-select: one property/layout can straddle more than one LGA.
                                     Read back as a comma-separated string (see getSelectValueString). --}}
                                <x-instrument-select id="desc_lga" label="" icon="map" :options="$lgas"
                                    placeholder="Select LGA " value="{{ $record->lga ?? '' }}"
                                    optionValue="name" optionLabel="name" :multiple="true" />
                            </div>

                            <div>
                                <label id="propertyState-label" for="propertyState" class="block text-sm font-medium text-gray-700 mb-1">State</label>
                                <div class="relative">
                                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                        <i data-lucide="map-pin" class="h-4 w-4 text-gray-400"></i>
                                    </div>
                                    <input id="propertyState" name="property_location" type="text"
                                        class="w-full pl-10 px-4 py-2.5 bg-gray-100 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-100 focus:border-blue-400 outline-none transition-all text-sm cursor-not-allowed"
                                        placeholder="State" value="Kano" readonly>
                                </div>
                            </div>
                        </div>

                        <div>
                            <label id="propertyDescription-label" for="propertyDescription"
                                class="block text-sm font-medium text-gray-700 mb-1">Property Description / Address</label>
                            <div class="relative">
                                <div class="absolute top-3 left-3 pointer-events-none">
                                    <i data-lucide="map" class="h-4 w-4 text-gray-400"></i>
                                </div>
                                <textarea id="propertyDescription" name="propertyDescription" rows="5"
                                    class="w-full pl-10 px-4 py-2.5 bg-gray-100 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-100 focus:border-blue-400 outline-none transition-all text-sm cursor-not-allowed"
                                    placeholder="Property Description / Address" readonly>{{ $record->property_description ?? '' }}</textarea>
                            </div>
                        </div>
                    </div>

                    <div id="survey-info-section"
                        class="mt-4 {{ (isset($record->survey_plan_no) || isset($record->lga) || isset($record->district)) ? '' : 'hidden' }} p-3 bg-gray-50 rounded-lg border border-gray-100">
                        <div class="grid grid-cols-3 gap-4">
                            <x-instrument-input id="surveyPlanNo" label="" icon="file-text"
                                placeholder="Survey Plan No" value="{{ $record->survey_plan_no ?? '' }}" />
                            <div>
                                <x-instrument-select id="district" label="" icon="map-pin" :defer-options="$deferDistrictOptions ?? false" :options="$districts->merge([(object)['name' => 'Others']])"
                                    placeholder="District" value="{{ $record->district ?? '' }}" optionValue="name" optionLabel="name" />
                                <div id="manual_survey_district_container" class="mt-2 hidden">
                                    <x-instrument-input id="manual_survey_district" name="manual_survey_district" label="" icon="edit-3" placeholder="Specify District" />
                                </div>
                            </div>
                            <x-instrument-select id="lga" label="" icon="map" :options="$lgas"
                                placeholder="LGA" value="{{ $record->lga ?? '' }}" optionValue="name" optionLabel="name" required/>
                        </div>
                    </div>
                </div>

                <hr class="my-6 border-gray-200">

                <!-- Row 3: Solicitor Details -->
                <div id="solicitor-section" class="border-l-4 border-indigo-500 pl-4">
                    {{-- <div class="flex items-center justify-between mb-4">
                        <h3 class="text-sm font-semibold text-gray-800">Solicitor</h3>
                        <div class="flex items-center gap-2">
                            <input type="checkbox" id="includeSolicitor"
                                class="checkbox h-3.5 w-3.5 accent-indigo-600"
                                {{ isset($record->solicitor_name) ? 'checked' : '' }}>
                            <label for="includeSolicitor"
                                class="text-xs font-medium text-indigo-700 cursor-pointer">Include
                                Solicitor</label>
                        </div>
                    </div> --}}

                    {{-- Hidden checkbox to maintain functionality for JS if needed, or we'll rely on the sidebar one --}}
                    <input type="checkbox" id="includeSolicitor" class="hidden" checked>


                    <div id="solicitor-container" class="{{ isset($record->solicitor_name) ? '' : 'hidden' }} space-y-3">
                        {{-- Fields relocated to sidebar partial --}}
                    </div>
                </div>

            </div>
        </form>

        <!-- Footer -->
        <div
            class="px-6 py-4 bg-gray-50 border-t border-gray-100 flex items-center justify-end gap-3 sticky bottom-0 z-10">
            <button type="button" id="cancel-btn"
                class="px-5 py-2.5 text-gray-700 font-medium bg-white border border-gray-300 rounded-lg hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-gray-200 transition-all shadow-sm">
                {{ (isset($isView) && $isView) ? 'Close' : 'Cancel' }}
            </button>
            @if(!(isset($isView) && $isView))
                <button type="button" id="submit-btn"
                    class="px-6 py-2.5 text-white font-medium bg-blue-600 rounded-lg hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition-all shadow-md flex items-center gap-2">
                    <i data-lucide="save" class="h-4 w-4"></i>
                    {{ isset($isEdit) && $isEdit ? 'Update Instrument' : 'Capture Instrument' }}
                </button>
            @endif
        </div>
    </div>
</div>

@include('instruments.partials.pra_history_modal')

