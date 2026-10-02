@extends('layouts.app')

{{--
  Match OP — the OP-holder Match card on a page of its own.

  Same card, same rule, same writer as the one inside the Recommendation capture
  form; this page just strips everything else away. It exists so the two jobs can be
  done by two people at once: one clears the unmatched Occupancy Permits while
  another gets on with capturing recommendations, instead of the second waiting on
  the first.

  Reached at /land-recommendations/create?match-op. No role check — anyone who can
  reach the menu entry can open it. What the page can DO is still bounded by the rule
  itself: Match is offered only on a file whose chain carries no transfer explaining
  the change, and the officer confirms both names before a row is written.

  The card itself is drawn by public/js/land-recommendation-op-match.js, which keys
  off #land-recommendation-form, #file_number and #op-match-card. Those ids are kept
  here deliberately: the script is shared with the capture form, and this page
  differs only by data-opmatch-standalone, which turns on the "match another file?"
  prompt after a successful write.
--}}

@section('content')
{{-- Same shell as the capture form. Two things come from it, and the page is broken
     without either: admin.header loads Tailwind v3 (layouts/app only has v2, which
     has no arbitrary values like text-[10px] and none of the rose/slate/violet
     palette the card uses), and the "flex-1 overflow-auto relative" wrapper is what
     app-layout.css sizes the content pane with — without it the page renders in a
     half-width column beside the sidebar. --}}
<div class="flex-1 overflow-auto bg-slate-50/60 relative">
  @include('admin.header')

  <div class="py-8 px-4 sm:px-6 lg:px-8 max-w-5xl mx-auto">
    <div class="bg-white rounded-2xl shadow-xl border border-slate-200 overflow-hidden">

      <div class="bg-slate-50 px-8 py-6 flex justify-between items-center border-b border-slate-200">
        <div>
          <h1 class="text-2xl font-bold text-slate-900 tracking-tight">Match OP (ToT)</h1>
          <p class="text-slate-500 text-sm mt-1">
            Record the missing Transfer of Title on files whose Occupancy Permit names a different holder
          </p>
        </div>
        <div class="flex items-center gap-3">
          <button type="button" id="match-op-refresh"
                  class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-slate-700 bg-white hover:bg-slate-50 rounded-lg transition border border-slate-200 shadow-sm">
            <i data-lucide="refresh-cw" class="h-4 w-4"></i> Refresh
          </button>
        </div>
      </div>

      {{-- The script needs a form element with the endpoints on it. Nothing is ever
           posted from here — Match writes through its own endpoint — so the form has
           no action and its submit is blocked below. --}}
      <form id="land-recommendation-form" onsubmit="return false;" class="p-8 space-y-6"
            data-opmatch-check-url="{{ route('land-recommendations.op-match.check') }}"
            data-opmatch-url="{{ route('land-recommendations.op-match.store') }}"
            data-opmatch-standalone="1">
        <div hidden>
          @csrf
          <input type="hidden" name="is_existing_recommendation" id="is_existing_recommendation" value="0">
          <input type="hidden" name="op_match_tot_pra_id" id="op_match_tot_pra_id" value="">
        </div>

        <div id="file-number-card" class="bg-blue-50/50 rounded-xl p-6 border border-blue-100/50">
          <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div class="flex-1">
              <label class="block text-xs font-bold text-blue-700 uppercase tracking-wider mb-2">Selected File Number</label>
              <input type="text" name="file_number" id="file_number" readonly
                     placeholder="NO FILE SELECTED"
                     class="w-full bg-white border border-blue-200 rounded-lg px-4 py-3 text-slate-900 font-bold font-mono placeholder:text-slate-400 text-lg shadow-sm outline-none focus:ring-2 focus:ring-blue-500 transition">
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

        {{-- Drawn by the shared script. Empty until a file is picked. --}}
        <div id="op-match-card" class="hidden"></div>

        {{-- Shown when a picked file needs nothing — the card stays hidden in that
             case, and silence would read as a page that failed to load. --}}
        <div id="match-op-idle" class="rounded-xl border-2 border-dashed border-slate-200 bg-slate-50/60 px-6 py-12 text-center">
          <i data-lucide="git-merge" class="h-8 w-8 text-slate-300 mx-auto"></i>
          <p class="mt-3 text-sm font-bold text-slate-700">Select a file number to check it</p>
          <p class="mt-1 text-xs text-slate-500">
            Files whose Occupancy Permit names a different holder than File Indexing, with no dealing explaining the change, can be matched here.
          </p>
        </div>
      </form>
    </div>

    {{-- The run's own record: every Transfer of Title Match has written, newest first.

         Scoped to what THIS flow wrote (system_source = OPHOLDERMATCH), not to every
         transfer in the register — the question an officer working this page has is
         "what have we matched", and the whole-register answer lives on the ToT
         dashboard, which this links out to. Server-rendered so it is on screen at
         first paint; a new match is prepended by the listener at the foot of the page
         rather than reloading, because "Yes, match another" deliberately keeps the
         officer on the page. --}}
    <div class="mt-8 bg-white rounded-2xl shadow-xl border border-slate-200 overflow-hidden">
      <div class="px-8 py-5 flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 bg-slate-50">
        <div>
          <h2 class="text-lg font-bold text-slate-900 flex items-center gap-2">
            <i data-lucide="git-merge" class="h-4 w-4 text-emerald-600"></i>
            Matched OPs
            <span id="match-op-count"
                  class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-emerald-100 text-emerald-800">{{ number_format($matchedTotal) }}</span>
          </h2>
          {{-- "Matched here" is now a claim the table can keep: rows written in bulk by
               op-match:backfill carry the same system_source but are filtered out, so
               everything listed is somebody's deliberate act on this page. --}}
          <p class="text-slate-500 text-xs mt-1">Transfers of Title matched on this page, newest first</p>
        </div>
        <a href="{{ route('maintenance.tot.index', ['filter' => 'system']) }}"
           class="inline-flex items-center gap-2 px-4 py-2 text-xs font-bold text-slate-700 bg-white hover:bg-slate-50 rounded-lg transition border border-slate-200 shadow-sm">
          <i data-lucide="table-2" class="h-4 w-4"></i> Open ToT dashboard
        </a>
      </div>

      {{-- No min-width and nothing truncated: the table fits the card and the names
           wrap. A party name is the whole point of the row — reading it should not
           cost a horizontal scroll and a hover, which is what "BALA RABIU and SUNUSI
           RA…" behind a scrollbar amounted to. overflow-x-auto is kept only as the
           safety net for a genuinely narrow screen. --}}
      <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse table-fixed">
          <thead>
            <tr class="text-slate-500 text-[10px] uppercase tracking-wider bg-white border-b border-slate-200">
              <th class="px-4 py-3 font-bold w-[19%]">File No</th>
              <th class="px-4 py-3 font-bold w-[24%]">Transferred From</th>
              <th class="px-4 py-3 font-bold w-[24%]">Transferred To</th>
              <th class="px-4 py-3 font-bold w-[18%]">Recorded</th>
              <th class="px-4 py-3 font-bold w-[11%] text-right">&nbsp;</th>
            </tr>
          </thead>
          <tbody id="match-op-tot-rows" class="divide-y divide-slate-100 text-sm">
            @forelse($matchedTots as $tot)
              @php
                $totFileNo = $tot->mlsFNo ?: ($tot->fileno ?: ($tot->kangisFileNo ?: $tot->NewKANGISFileno));
                $isMerger  = trim((string) $tot->merger_group_id) !== '';
              @endphp
              <tr class="hover:bg-slate-50/60 transition align-top">
                <td class="px-4 py-3">
                  <span class="font-mono text-xs font-bold text-blue-700 bg-blue-50 px-2 py-0.5 rounded-full inline-block whitespace-nowrap">{{ $totFileNo ?: '—' }}</span>
                  @if($isMerger)
                    {{-- "OP Merger", not "Merger": on this page a merger is always several
                         Occupancy Permits becoming one file, and the bare word is used
                         elsewhere in the register for the file-level merger workflow. --}}
                    <span class="mt-1 inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[9px] font-bold bg-violet-100 text-violet-700 whitespace-nowrap"
                          title="Several Occupancy Permits combined into one Transfer of Title">
                      <i data-lucide="git-merge" class="h-2.5 w-2.5"></i> OP Merger
                    </span>
                  @endif
                </td>
                <td class="px-4 py-3 text-slate-700 break-words" title="{{ $tot->party_1 }}">{{ $tot->party_1 ?: '—' }}</td>
                <td class="px-4 py-3 font-semibold text-slate-900 break-words" title="{{ $tot->party_2 }}">{{ $tot->party_2 ?: '—' }}</td>
                <td class="px-4 py-3 text-slate-500 text-xs">
                  @if($tot->created_at)
                    @php $recordedAt = \Carbon\Carbon::parse($tot->created_at); @endphp
                    <span class="block whitespace-nowrap">{{ $recordedAt->format('d M Y') }}</span>
                    <span class="block text-[10px] text-slate-400 whitespace-nowrap">{{ $recordedAt->format('g:i A') }}</span>
                  @else
                    —
                  @endif

                  {{-- WHO. "I wasn't the one that matched it" is a fair question to be
                       able to answer from the row itself, and every row here is
                       somebody's deliberate act — the backfill's are not listed. --}}
                  @if($tot->recorded_by)
                    <span class="block text-[10px] text-slate-500 mt-0.5">by {{ $tot->recorded_by }}</span>
                  @endif
                </td>
                <td class="px-4 py-3 text-right">
                  {{-- Loads the file back into the card above. Re-checking a file that has
                       already been matched is how an officer confirms what was written —
                       the card shows the transfer and says no action is needed. --}}
                  <button type="button" class="match-op-recheck inline-flex items-center gap-1.5 px-3 py-1.5 text-[11px] font-bold text-slate-600 bg-white hover:bg-slate-50 border border-slate-200 rounded-lg transition"
                          data-file="{{ $totFileNo }}">
                    <i data-lucide="search" class="h-3 w-3"></i> Check
                  </button>
                </td>
              </tr>
            @empty
              <tr id="match-op-tot-empty">
                <td colspan="5" class="px-6 py-14 text-center">
                  <i data-lucide="git-merge" class="h-7 w-7 text-slate-300 mx-auto"></i>
                  <p class="mt-3 text-sm font-bold text-slate-600">Nothing matched yet</p>
                  <p class="mt-1 text-xs text-slate-500">Transfers recorded from this page will appear here.</p>
                </td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>

      @if($matchedTotal > count($matchedTots))
        <div class="px-6 py-3 border-t border-slate-100 text-center text-[11px] text-slate-500">
          Showing the {{ count($matchedTots) }} most recent of {{ number_format($matchedTotal) }} —
          <a href="{{ route('maintenance.tot.index', ['filter' => 'system']) }}" class="font-bold text-blue-600 hover:underline">see them all</a>
        </div>
      @endif
    </div>
  </div>
</div>

@include('components.global-fileno-modal')
@endsection

@push('scripts')
<script src="{{ asset('js/global-fileno-modal.js') }}"></script>
<script src="{{ asset('js/land-recommendation-op-match.js') }}?v={{ filemtime(public_path('js/land-recommendation-op-match.js')) }}"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var fileNoInput = document.getElementById('file_number');
    var idle = document.getElementById('match-op-idle');
    var card = document.getElementById('op-match-card');

    // Open the shared file-number selector. It writes any [name="file_number"] input
    // and fires a jQuery change, which is what the match script listens for.
    var pick = document.getElementById('select-fileno-btn');
    if (pick) {
        pick.addEventListener('click', function () {
            if (!window.GlobalFileNoModal || typeof window.GlobalFileNoModal.open !== 'function') return;

            // Only the file number is wanted here — the capture form's callback also
            // fills applicant, location, plot and so on, none of which exist on this
            // page. targetFields writes #file_number and fires the change the match
            // script listens for.
            window.GlobalFileNoModal.open({ targetFields: ['#file_number'] });
        });
    }

    document.getElementById('match-op-refresh').addEventListener('click', function () {
        window.location.reload();
    });

    // ── The Matched OPs table ───────────────────────────────────────────────
    var rows = document.getElementById('match-op-tot-rows');

    // A row added here was matched by the person looking at the screen, so it is
    // attributed the same way the rendered rows are rather than left anonymous.
    var CURRENT_USER = @json(trim((auth()->user()->first_name ?? '') . ' ' . (auth()->user()->last_name ?? '')) ?: 'you');

    function esc(v) {
        return String(v === null || v === undefined ? '' : v)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#039;');
    }

    // A write leaves the officer on the page ("Yes, match another"), so the table has
    // to take the new row itself — a table that only updates on reload would show the
    // run one file behind for as long as the run lasts.
    document.addEventListener('opmatch:recorded', function (event) {
        if (!rows) return;

        var d = event.detail || {};
        var state = d.state || {};
        var m = state.match || {};
        var roots = state.roots || [];

        // The transfer's plots are the plots of the permits it was built from, which
        // is what `roots` holds — the response itself does not carry the written row.
        var empty = document.getElementById('match-op-tot-empty');
        if (empty) empty.remove();

        var tr = document.createElement('tr');
        // Held briefly in amber so the row the officer just created is findable at a
        // glance on a table where everything else looks alike.
        tr.className = 'bg-amber-50 transition-colors duration-1000 align-top';
        tr.innerHTML = ''
            + '<td class="px-4 py-3">'
            +   '<span class="font-mono text-xs font-bold text-blue-700 bg-blue-50 px-2 py-0.5 rounded-full inline-block whitespace-nowrap">' + esc(d.file_number || '—') + '</span>'
            +   (roots.length > 1
                    ? '<span class="mt-1 inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[9px] font-bold bg-violet-100 text-violet-700 whitespace-nowrap">OP Merger</span>'
                    : '')
            + '</td>'
            + '<td class="px-4 py-3 text-slate-700 break-words" title="' + esc(m.party_1) + '">' + esc(m.party_1 || '—') + '</td>'
            + '<td class="px-4 py-3 font-semibold text-slate-900 break-words" title="' + esc(m.party_2) + '">' + esc(m.party_2 || '—') + '</td>'
            + '<td class="px-4 py-3 text-xs">'
            +   '<span class="block font-bold text-emerald-700">Just now</span>'
            +   '<span class="block text-[10px] text-slate-500 mt-0.5">by ' + esc(CURRENT_USER) + '</span>'
            + '</td>'
            + '<td class="px-4 py-3 text-right">'
            +   '<button type="button" class="match-op-recheck inline-flex items-center gap-1.5 px-3 py-1.5 text-[11px] font-bold text-slate-600 bg-white hover:bg-slate-50 border border-slate-200 rounded-lg transition" data-file="' + esc(d.file_number) + '">'
            +     '<i data-lucide="search" class="h-3 w-3"></i> Check'
            +   '</button>'
            + '</td>';

        rows.insertBefore(tr, rows.firstChild);
        setTimeout(function () { tr.classList.remove('bg-amber-50'); }, 2500);

        var counter = document.getElementById('match-op-count');
        if (counter) {
            var n = parseInt(String(counter.textContent).replace(/[^0-9]/g, ''), 10);
            counter.textContent = (isNaN(n) ? 1 : n + 1).toLocaleString();
        }

        if (window.lucide && typeof window.lucide.createIcons === 'function') window.lucide.createIcons();
    });

    // Delegated, so a row added above answers to it as well as the rendered ones.
    // Re-checking a matched file is how the officer confirms what was written: the
    // card comes back green with the transfer on it.
    document.addEventListener('click', function (event) {
        var btn = event.target.closest ? event.target.closest('.match-op-recheck') : null;
        if (!btn || !fileNoInput) return;

        var file = btn.getAttribute('data-file') || '';
        if (!file) return;

        fileNoInput.value = file;
        if (window.jQuery) window.jQuery(fileNoInput).trigger('change');
        else fileNoInput.dispatchEvent(new Event('change'));

        document.getElementById('file-number-card').scrollIntoView({ behavior: 'smooth', block: 'center' });
    });

    // The idle notice and the card are mutually exclusive: whichever the check
    // produced is the one worth showing.
    function syncIdle() {
        if (!idle || !card) return;
        idle.classList.toggle('hidden', !card.classList.contains('hidden'));
    }

    if (window.MutationObserver && card) {
        new MutationObserver(syncIdle).observe(card, { attributes: true, attributeFilter: ['class'] });
    }
    if (fileNoInput) {
        fileNoInput.addEventListener('change', function () { setTimeout(syncIdle, 50); });
        if (window.jQuery) window.jQuery(fileNoInput).on('change', function () { setTimeout(syncIdle, 50); });
    }

    syncIdle();
});
</script>
@endpush
