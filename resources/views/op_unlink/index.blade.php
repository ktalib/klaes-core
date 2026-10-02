@extends('layouts.app')

{{--
  OP Unlink / Reassignment.

  Five sections, read top to bottom:

    1  Select Existing File Number   the file the permits are currently on
    2  Associated OPs                everything sitting on that file's Property ID
    3  Unmatch / Conversion          detach them, individually or as a Group OP
    4  Unmatched OPs                 what was just detached, plus anything added by hand
    5  Select New File Number        OPTIONAL — reassign, or stop here

  REASSIGNMENT IS OPTIONAL. Section 5 never gates section 3: unlinking a permit is a
  complete act, and where it belongs instead may not be known today. The page is laid
  out as a column rather than the Match page's two panes precisely so that stopping
  half way looks like finishing, not like abandoning a form.

  The shell is the one the Match page uses, for the same two reasons: admin.header
  loads Tailwind v3 (layouts/app carries only v2), and the "flex-1 overflow-auto
  relative" wrapper is what app-layout.css sizes the content pane with.

  Drawn and driven by public/js/op-unlink.js, which keys off the ids on #op-unlink and
  reads every endpoint from that element's data attributes. Note that three of those
  endpoints belong to the Match page — the file lookup, the OP search and the match
  itself are not duplicated here.
--}}

@section('content')
<div class="flex-1 overflow-auto bg-slate-50/60 relative">
  @include('admin.header')

  <div id="op-unlink"
       class="py-8 px-4 sm:px-6 lg:px-8 max-w-5xl mx-auto"
       data-file-url="{{ route('op-propid-match.file') }}"
       data-ops-url="{{ route('op-propid-match.ops') }}"
       data-match-url="{{ route('op-propid-match.batch') }}"
       data-associated-url="{{ route('op-unlink.associated') }}"
       data-unlink-url="{{ route('op-unlink.unlink') }}"
       data-batches-url="{{ route('op-unlink.batches') }}"
       data-undo-url="{{ route('op-propid-match.undo') }}"
       data-csrf="{{ csrf_token() }}">

    {{-- Page header --}}
    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 px-6 py-5 mb-5">
      <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
          <h1 class="text-2xl font-bold text-slate-900 tracking-tight flex items-center gap-2">
            <i data-lucide="unlink" class="h-6 w-6 text-rose-600"></i>
            OP Unlink / Reassignment
          </h1>
          <p class="text-slate-500 text-sm mt-1 max-w-3xl">
            Take Occupancy Permits off a file's Property ID and give them their own — individually, or
            together as a Group OP. Reassigning them to another file afterwards is optional.
          </p>
        </div>
        <button type="button" id="opu-reset"
                class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-slate-700 bg-white hover:bg-slate-50 rounded-lg transition border border-slate-200 shadow-sm">
          <i data-lucide="rotate-ccw" class="h-4 w-4"></i> Start over
        </button>
      </div>
    </div>

    <div id="opu-banner" class="hidden mb-5"></div>

    {{-- ==================== 1: the file they are on now ==================== --}}
    <section class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden mb-5">
      <header class="px-6 py-4 border-b border-slate-200 bg-gradient-to-r from-slate-50 to-white">
        <div class="flex items-center gap-3">
          <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-slate-800 text-white text-sm font-bold">1</span>
          <div>
            <h2 class="text-base font-semibold text-slate-900">Select Existing File Number</h2>
            <p class="text-xs text-slate-500">The file the permits are currently associated with.</p>
          </div>
        </div>
      </header>

      <div class="p-6 space-y-4">
        <div class="flex gap-2">
          <input type="text" id="opu-file-display" readonly
                 placeholder="Click to choose the current file number"
                 class="flex-1 px-4 py-2.5 rounded-lg border border-slate-300 bg-slate-50 text-sm font-medium cursor-pointer focus:ring-2 focus:ring-slate-400 focus:border-slate-400 transition">
          <button type="button" id="opu-file-pick-btn"
                  class="inline-flex items-center gap-2 px-4 py-2.5 text-sm font-semibold text-white bg-slate-800 hover:bg-slate-900 rounded-lg transition shadow-sm whitespace-nowrap">
            <i data-lucide="folder-search" class="h-4 w-4"></i> Select File Number
          </button>
        </div>
        <div id="opu-file-selected" class="hidden"></div>
      </div>
    </section>

    {{-- ==================== 2: what is on it ==================== --}}
    <section class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden mb-5">
      <header class="px-6 py-4 border-b border-slate-200 bg-gradient-to-r from-amber-50 to-white">
        <div class="flex items-center justify-between gap-3">
          <div class="flex items-center gap-3">
            <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-amber-500 text-white text-sm font-bold">2</span>
            <div>
              {{-- "Associated", not "Matched": a permit can be on this parcel because it
                   was matched here, captured here, or has always been here. The page is
                   not asserting how it arrived. --}}
              <h2 class="text-base font-semibold text-slate-900">Associated OPs</h2>
              <p class="text-xs text-slate-500">Everything currently on this file's Property ID. Select what should come off.</p>
            </div>
          </div>
          <span id="opu-associated-count" class="text-xs text-slate-500"></span>
        </div>
      </header>

      <div class="p-6">
        <div id="opu-associated">
          <div class="rounded-lg border border-dashed border-slate-300 px-4 py-8 text-center text-sm text-slate-400">
            Select a file number above to see the permits associated with it.
          </div>
        </div>
      </div>
    </section>

    {{-- ==================== 3: the unmatch ==================== --}}
    <section class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden mb-5">
      <header class="px-6 py-4 border-b border-slate-200 bg-gradient-to-r from-rose-50 to-white">
        <div class="flex items-center gap-3">
          <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-rose-600 text-white text-sm font-bold">3</span>
          <div>
            <h2 class="text-base font-semibold text-slate-900">Unmatch / Conversion</h2>
            <p class="text-xs text-slate-500">Detach the selected permits and give them their own Property ID.</p>
          </div>
        </div>
      </header>

      <div class="p-6 space-y-4">
        <div id="opu-unmatch-summary" class="text-sm text-slate-500">
          Select one or more permits above.
        </div>

        <label class="inline-flex items-start gap-2 text-xs text-slate-600 cursor-pointer">
          <input type="checkbox" id="opu-move-companions" checked
                 class="mt-0.5 h-3.5 w-3.5 rounded border-slate-300 text-rose-600 focus:ring-rose-500">
          <span>
            <span class="font-medium text-slate-700">Take each permit's own Transfer of Title with it</span><br>
            <span class="text-slate-400">
              Only transfers written explicitly against the permit are moved — never the file's own transfers,
              which stay with the file.
            </span>
          </span>
        </label>

        <div class="flex flex-wrap items-center gap-3">
          <button type="button" id="opu-unmatch-btn" disabled
                  class="inline-flex items-center gap-2 px-6 py-3 text-sm font-semibold text-white bg-rose-600 hover:bg-rose-700 disabled:bg-slate-300 disabled:cursor-not-allowed rounded-lg transition shadow-sm">
            <i data-lucide="unlink" class="h-4 w-4"></i>
            <span id="opu-unmatch-label">Unmatch Selected</span>
          </button>
          <span class="text-xs text-slate-400">You will be asked how to separate them. This can be undone below.</span>
        </div>
      </div>
    </section>

    {{-- ==================== 4: the pool ==================== --}}
    <section class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden mb-5">
      <header class="px-6 py-4 border-b border-slate-200 bg-gradient-to-r from-emerald-50 to-white">
        <div class="flex items-center justify-between gap-3">
          <div class="flex items-center gap-3">
            <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-emerald-600 text-white text-sm font-bold">4</span>
            <div>
              <h2 class="text-base font-semibold text-slate-900">Unmatched OPs</h2>
              <p class="text-xs text-slate-500">Filled by the unmatch above. You can also search for other permits to add.</p>
            </div>
          </div>
          <button type="button" id="opu-pool-search-toggle"
                  class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium text-slate-600 bg-white hover:bg-slate-50 border border-slate-200 rounded-lg transition">
            <i data-lucide="search" class="h-3.5 w-3.5"></i>
            <span id="opu-pool-search-label">Add other permits</span>
          </button>
        </div>
      </header>

      <div class="p-6 space-y-4">
        {{-- The manual route into the pool. Hidden until asked for: the ordinary path is
             that the unmatch above fills this section, and a search box sitting open
             suggests the officer is expected to go looking for something. --}}
        <div id="opu-pool-search" class="hidden space-y-3 pb-4 border-b border-slate-100">
          <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
            <div class="sm:col-span-2">
              <label for="opu-serial" class="block text-xs font-semibold text-slate-600 uppercase tracking-wide mb-1.5">
                OP serial number(s)
              </label>
              <input type="text" id="opu-serial" autocomplete="off"
                     placeholder="e.g. 989 — or a list: 989, 990, 991"
                     class="w-full px-3 py-2 text-sm border border-slate-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500">
            </div>
            <div>
              <label for="opu-op-file" class="block text-xs font-semibold text-slate-600 uppercase tracking-wide mb-1.5">
                File / Temp no.
              </label>
              <input type="text" id="opu-op-file" autocomplete="off" placeholder="e.g. TEMP-57816"
                     class="w-full px-3 py-2 text-sm border border-slate-300 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500">
            </div>
          </div>
          <div class="flex items-center gap-2">
            <button type="button" id="opu-pool-search-btn"
                    class="inline-flex items-center gap-2 px-4 py-2 text-sm font-semibold text-white bg-emerald-600 hover:bg-emerald-700 rounded-lg transition shadow-sm">
              <i data-lucide="search" class="h-4 w-4"></i> Search
            </button>
            <span id="opu-pool-search-count" class="text-xs text-slate-500"></span>
          </div>
          <div id="opu-pool-results" class="hidden"></div>
        </div>

        <div id="opu-pool">
          <div class="rounded-lg border border-dashed border-slate-300 px-4 py-8 text-center text-sm text-slate-400">
            Nothing here yet. Unmatch permits above, or add other permits by searching.
          </div>
        </div>
      </div>
    </section>

    {{-- ==================== 5: the optional destination ==================== --}}
    <section class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden mb-5">
      <header class="px-6 py-4 border-b border-slate-200 bg-gradient-to-r from-violet-50 to-white">
        <div class="flex items-center gap-3">
          <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-violet-600 text-white text-sm font-bold">5</span>
          <div>
            <h2 class="text-base font-semibold text-slate-900">Select New File Number</h2>
            {{-- Said on the card itself, not only in the docblock. An officer who has
                 unlinked and is done should be able to read that they are done. --}}
            <p class="text-xs text-slate-500">
              <span class="font-semibold text-violet-700">Optional.</span>
              Unmatching is complete on its own — only continue if you know where these permits belong.
            </p>
          </div>
        </div>
      </header>

      <div class="p-6 space-y-4">
        <div class="flex gap-2">
          <input type="text" id="opu-new-file-display" readonly
                 placeholder="Optional — click to choose a destination file"
                 class="flex-1 px-4 py-2.5 rounded-lg border border-slate-300 bg-slate-50 text-sm font-medium cursor-pointer focus:ring-2 focus:ring-violet-500 focus:border-violet-500 transition">
          <button type="button" id="opu-new-file-pick-btn"
                  class="inline-flex items-center gap-2 px-4 py-2.5 text-sm font-semibold text-white bg-violet-600 hover:bg-violet-700 rounded-lg transition shadow-sm whitespace-nowrap">
            <i data-lucide="folder-search" class="h-4 w-4"></i> Select File Number
          </button>
          <button type="button" id="opu-new-file-clear"
                  class="hidden inline-flex items-center px-3 py-2.5 text-sm font-medium text-slate-500 hover:text-rose-600 border border-slate-200 rounded-lg transition">
            Clear
          </button>
        </div>

        <div id="opu-new-file-selected" class="hidden"></div>

        <div id="opu-match-summary" class="text-sm text-slate-500">
          Choose a destination file only if these permits should be reassigned.
        </div>

        <div class="flex flex-wrap items-center gap-3">
          <button type="button" id="opu-match-btn" disabled
                  class="inline-flex items-center gap-2 px-6 py-3 text-sm font-semibold text-white bg-violet-600 hover:bg-violet-700 disabled:bg-slate-300 disabled:cursor-not-allowed rounded-lg transition shadow-sm">
            <i data-lucide="git-merge" class="h-4 w-4"></i>
            <span id="opu-match-label">Match to New File</span>
          </button>
        </div>
      </div>
    </section>

    {{-- Recent unlinks, with Undo. Same ledger as the Match page, filtered to OPU-. --}}
    <section class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
      <header class="px-6 py-3.5 border-b border-slate-200 flex items-center justify-between">
        <h2 class="text-sm font-semibold text-slate-900 flex items-center gap-2">
          <i data-lucide="history" class="h-4 w-4 text-slate-400"></i> Recent unlinks
        </h2>
        <button type="button" id="opu-batches-refresh" class="text-xs font-medium text-slate-500 hover:text-slate-700 transition">
          Refresh
        </button>
      </header>
      <div id="opu-batches"></div>
    </section>

  </div>
</div>

{{-- The shared file picker's markup, used by sections 1 and 5. Without it
     GlobalFileNoModal.open() finds no #global-fileno-modal and bails. --}}
@include('components.global-fileno-modal')
@endsection

@push('scripts')
{{-- select2 is NOT loaded here: the picker component above carries its own copy inside
     an @once, parsed before this stack. jQuery comes from layouts/app. --}}
<script src="{{ asset('js/global-fileno-modal.js') }}"></script>
<script src="{{ asset('js/op-unlink.js') }}?v={{ @filemtime(public_path('js/op-unlink.js')) ?: time() }}"></script>
@endpush
