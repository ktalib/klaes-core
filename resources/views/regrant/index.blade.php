@extends('layouts.app')

@php
    // Super Admin only for now — must agree with RegrantController::canCorrect(), which
    // enforces it on the endpoints. Computed once here, above the pushes, so the styles,
    // the column header, the row cells, the empty-row colspan and the script all honour
    // the same decision and cannot drift apart.
    $canCorrect = auth()->check() && auth()->user()->isSuperAdmin();
@endphp

@if ($canCorrect)
@push('styles')
<style>
    /* Row action menu. Fixed rather than absolute so the register table's
       `overflow-x-auto` cannot clip it; the trigger positions it on open. */
    .regrant-action-dropdown {
        position: fixed;
        z-index: 9999;
        width: 11rem;
        border-radius: 0.75rem;
        border: 1px solid rgb(226 232 240);
        background: #fff;
        box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1), 0 8px 10px -6px rgba(0, 0, 0, 0.1);
        display: flex;
        flex-direction: column;
        white-space: nowrap;
        padding: 0.25rem 0;
    }

    .regrant-action-dropdown button {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        width: 100%;
        text-align: left;
        padding: 0.5rem 1rem;
        font-size: 0.8125rem;
        color: rgb(71 85 105);
        transition: all 0.15s;
    }

    .regrant-action-dropdown button:hover {
        background: rgb(238 242 255);
        color: rgb(67 56 202);
    }
</style>
@endpush
@endif

@section('content')
@php
    use App\Services\RegrantTermService;

    // An overdue span of decades is a records-cleanup case, not an urgent one; colour by
    // how far past the term the title has run.
    $overdueTone = fn ($years) => $years >= 30
        ? 'bg-red-50 text-red-700 border-red-200'
        : ($years >= 10 ? 'bg-orange-50 text-orange-700 border-orange-200'
                        : 'bg-amber-50 text-amber-700 border-amber-200');

    $dash = fn ($v) => filled($v) ? e($v) : '<span class="text-slate-300">—</span>';

@endphp

<div class="flex-1 overflow-auto bg-slate-50/60">
@include('admin.header', [
    'PageTitle'       => 'Re-grant Management',
    'PageDescription' => 'Every Re-grant on record, and the files whose statutory term has already run out.',
])

<div class="p-4 sm:p-6 max-w-[1600px] mx-auto">

    {{-- Header --}}
    <div class="flex flex-wrap items-start justify-between gap-4 mb-5">
        <div>
            <h1 class="text-xl font-bold text-slate-800 flex items-center gap-2">
                <i data-lucide="refresh-cw" class="w-5 h-5 text-indigo-600"></i>
                Re-grant Management
            </h1>
            <p class="text-sm text-slate-500 mt-1">
                Every Re-grant on record, and the files whose statutory term has already run out.
            </p>
        </div>

        {{-- Two cards, mirroring the two tabs below. Each links to its own tab. --}}
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('regrant.index') }}?tab=register"
               class="px-4 py-2 rounded-lg bg-white border text-center min-w-[132px] transition hover:border-indigo-300 hover:shadow-sm {{ $tab === 'register' ? 'border-indigo-300 ring-1 ring-indigo-100' : 'border-slate-200' }}">
                <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Re-grant Register</p>
                <p class="text-lg font-bold text-slate-800">{{ number_format($stats['register_total']) }}</p>
            </a>
            <a href="{{ route('regrant.index') }}?tab=due"
               class="px-4 py-2 rounded-lg bg-white border text-center min-w-[132px] transition hover:border-red-300 hover:shadow-sm {{ $tab === 'due' ? 'border-red-300 ring-1 ring-red-100' : 'border-slate-200' }}">
                <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">To Be Re-granted</p>
                <p class="text-lg font-bold text-red-600">{{ number_format($stats['due_total']) }}</p>
            </a>
        </div>
    </div>

    {{-- Tabs --}}
    <div class="flex items-center gap-1 border-b border-slate-200 mb-4">
        @php
            $tabBase   = 'px-4 py-2.5 text-sm font-semibold border-b-2 -mb-px transition flex items-center gap-2';
            $tabOn     = 'border-indigo-600 text-indigo-700';
            $tabOff    = 'border-transparent text-slate-500 hover:text-slate-700';
        @endphp
        <a href="{{ route('regrant.index') }}?tab=register"
           class="{{ $tabBase }} {{ $tab === 'register' ? $tabOn : $tabOff }}">
            <i data-lucide="list" class="w-4 h-4"></i>
            Re-grant Register
            <span class="px-1.5 py-0.5 rounded text-[11px] bg-slate-100 text-slate-600">{{ number_format($stats['register_total']) }}</span>
        </a>
        <a href="{{ route('regrant.index') }}?tab=due"
           class="{{ $tabBase }} {{ $tab === 'due' ? $tabOn : $tabOff }}">
            <i data-lucide="alarm-clock" class="w-4 h-4"></i>
            To Be Re-granted
            <span class="px-1.5 py-0.5 rounded text-[11px] bg-red-100 text-red-700">{{ number_format($stats['due_total']) }}</span>
        </a>
    </div>

    {{-- Filters --}}
    <form method="GET" action="{{ route('regrant.index') }}" class="flex flex-wrap items-end gap-3 mb-4">
        <input type="hidden" name="tab" value="{{ $tab }}">

        <div class="flex-1 min-w-[220px]">
            <label class="block text-[11px] font-semibold text-slate-500 mb-1">Search</label>
            <input type="text" name="search" value="{{ $search }}"
                   placeholder="{{ $tab === 'due' ? 'File number, holder, land use…' : 'File number, title, holder…' }}"
                   class="w-full px-3 py-2 text-sm border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
        </div>

        @if ($tab === 'due')
            <div>
                <label class="block text-[11px] font-semibold text-slate-500 mb-1">Instrument</label>
                <select name="source" class="px-3 py-2 text-sm border border-slate-300 rounded-lg bg-white">
                    <option value="">All</option>
                    <option value="cofo" @selected($filters['source'] === 'cofo')>CofO ({{ number_format($stats['due_cofo']) }})</option>
                    <option value="rofo" @selected($filters['source'] === 'rofo')>RofO ({{ number_format($stats['due_rofo']) }})</option>
                </select>
            </div>
            <div>
                <label class="block text-[11px] font-semibold text-slate-500 mb-1">Term</label>
                <select name="term" class="px-3 py-2 text-sm border border-slate-300 rounded-lg bg-white">
                    <option value="">All</option>
                    <option value="99" @selected($filters['term'] === '99')>99 yrs — Res / Agric</option>
                    <option value="40" @selected($filters['term'] === '40')>40 yrs — Com / Ind</option>
                    <option value="actual" @selected($filters['term'] === 'actual')>Actual term on record</option>
                    <option value="standard" @selected($filters['term'] === 'standard')>Standard term assumed</option>
                </select>
            </div>
        @endif

        <button type="submit" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold rounded-lg flex items-center gap-1.5">
            <i data-lucide="search" class="w-4 h-4"></i> Filter
        </button>
        @if ($search || $filters['source'] || $filters['term'])
            <a href="{{ route('regrant.index') }}?tab={{ $tab }}" class="px-3 py-2 text-sm text-slate-500 hover:text-slate-700">Reset</a>
        @endif
    </form>

    {{-- ─────────────── Tab: Re-grant Register ─────────────── --}}
    @if ($tab === 'register')
        <div class="bg-white border border-slate-200 rounded-xl overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 border-b border-slate-200">
                        <tr class="text-left text-[11px] font-bold uppercase tracking-wider text-slate-500">
                            <th class="px-4 py-3">File No</th>
                            <th class="px-4 py-3">Re-granted From</th>
                            <th class="px-4 py-3">File Title / Holder</th>
                            <th class="px-4 py-3">Land Use</th>
                            <th class="px-4 py-3">Location</th>
                            <th class="px-4 py-3">Comment</th>
                            <th class="px-4 py-3">Date Created</th>
                            @if ($canCorrect)
                                <th class="px-4 py-3 text-center">Action</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($records as $r)
                            @php
                                // The stored link wins; otherwise fall back to the single related
                                // file resolved from the file's indexing row (land files only).
                                $linkedFrom = filled($r->see_fileno) ? $r->see_fileno : ($r->derived_see_fileno ?? null);
                                $isDerived  = blank($r->see_fileno) && filled($linkedFrom);
                            @endphp
                            <tr class="hover:bg-slate-50/70">
                                <td class="px-4 py-3 font-semibold text-slate-800 whitespace-nowrap">{{ $r->file_no }}</td>
                                <td class="px-4 py-3 whitespace-nowrap">
                                    @if (filled($linkedFrom))
                                        <span class="inline-flex items-center gap-1 {{ $isDerived ? 'text-slate-500' : 'text-indigo-700' }}"
                                              @if ($isDerived) title="Resolved from the file's Related File No. — not stored on the record" @endif>
                                            <i data-lucide="corner-down-right" class="w-3.5 h-3.5"></i>{{ $linkedFrom }}
                                            @if ($isDerived)
                                                <span class="text-[10px] uppercase tracking-wide text-slate-400 font-semibold">(related)</span>
                                            @endif
                                        </span>
                                    @else
                                        <span class="text-slate-300">not linked</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-slate-700 max-w-[260px] truncate"
                                    title="{{ $r->file_title ?: $r->applicant_name }}">{!! $dash($r->file_title ?: $r->applicant_name) !!}</td>
                                <td class="px-4 py-3 text-slate-600">{!! $dash($r->land_use) !!}</td>
                                <td class="px-4 py-3 text-slate-600 max-w-[300px] truncate" title="{{ $r->location }}">{!! $dash($r->location) !!}</td>
                                <td class="px-4 py-3 text-slate-600 whitespace-nowrap">
                                    @if (filled($linkedFrom))
                                        Re-grant from {{ $linkedFrom }}
                                    @else
                                        <span class="text-slate-300">—</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-slate-500 whitespace-nowrap">
                                    {{ $r->created_at ? \Carbon\Carbon::parse($r->created_at)->format('d/m/Y') : '—' }}
                                </td>
                                @if ($canCorrect)
                                    {{-- Row action menu. The panel is position:fixed and placed from the
                                         trigger's rect, because this table sits inside `overflow-x-auto`,
                                         which clips an absolutely positioned dropdown. Same approach the
                                         Lands One Stop Shop tables use. --}}
                                    <td class="px-4 py-3 text-center whitespace-nowrap">
                                        <div class="inline-block" x-data="{ open: false }" x-on:keydown.escape.window="open = false">
                                            <button type="button" x-ref="trigger"
                                                    title="Actions"
                                                    @click="if(open){ open=false; } else { $nextTick(()=>{ let r=$refs.trigger.getBoundingClientRect(); let dd=$refs.dropdown; dd.style.visibility='hidden'; dd.style.display='flex'; let dh=dd.offsetHeight; dd.style.display=''; dd.style.visibility=''; let spaceBelow=window.innerHeight-r.bottom; if(spaceBelow < dh+8){ dd.style.top=(r.top - dh - 4)+'px'; } else { dd.style.top=(r.bottom+4)+'px'; } dd.style.left=Math.max(8, r.right - dd.offsetWidth)+'px'; open=true; setTimeout(()=>{ if(window.lucide) window.lucide.createIcons(); },10); }); }"
                                                    @click.outside="open = false"
                                                    class="p-1.5 rounded-lg text-slate-400 hover:text-slate-700 hover:bg-slate-100 transition">
                                                <i data-lucide="more-vertical" class="w-4 h-4"></i>
                                            </button>

                                            <div x-ref="dropdown" x-cloak x-show="open" x-transition.opacity x-transition.scale.95
                                                 class="regrant-action-dropdown">
                                                <button type="button"
                                                        @click="open = false"
                                                        onclick="regrantOpenCorrect({{ $r->id }}, @js($r->file_no), @js((string) $linkedFrom), {{ filled($r->see_fileno) ? 'true' : 'false' }})">
                                                    <i data-lucide="replace" class="w-3.5 h-3.5 text-indigo-500"></i> Correct
                                                </button>
                                            </div>
                                        </div>
                                    </td>
                                @endif
                            </tr>
                        @empty
                            <tr><td colspan="{{ $canCorrect ? 8 : 7 }}" class="px-4 py-10 text-center text-slate-400">No Re-grant records found.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

    {{-- ─────────────── Tab: To Be Re-granted ─────────────── --}}
    @else
        <div class="mb-3 flex flex-wrap items-center gap-2 text-xs text-slate-500">
            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-slate-100">
                <i data-lucide="info" class="w-3.5 h-3.5"></i>
                Expired on or before today &middot; commencement date + term granted
            </span>
            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-slate-100">
                Term: recorded on the file, else on the certificate, else RES/AG = 99 &middot; COM/IND = 40
            </span>
        </div>

        <div class="bg-white border border-slate-200 rounded-xl overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 border-b border-slate-200">
                        <tr class="text-left text-[11px] font-bold uppercase tracking-wider text-slate-500">
                            <th class="px-4 py-3">File No</th>
                            <th class="px-4 py-3">Instrument</th>
                            <th class="px-4 py-3">Holder</th>
                            <th class="px-4 py-3">Commencement</th>
                            <th class="px-4 py-3 text-center">Term</th>
                            <th class="px-4 py-3 text-center">Expired</th>
                            <th class="px-4 py-3 text-center">Overdue</th>
                            <th class="px-4 py-3">Land Use</th>
                            <th class="px-4 py-3">Location</th>
                            {{-- Action column hidden; the Raise flow stays available via regrantRaise(). --}}
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($records as $r)
                            <tr class="hover:bg-slate-50/70" data-file-no="{{ $r->file_no }}">
                                <td class="px-4 py-3 font-semibold text-slate-800 whitespace-nowrap">{{ $r->file_no }}</td>
                                <td class="px-4 py-3">
                                    <span class="px-2 py-0.5 rounded text-[11px] font-bold uppercase
                                        {{ $r->source === 'cofo' ? 'bg-violet-50 text-violet-700' : 'bg-sky-50 text-sky-700' }}">
                                        {{ $r->source === 'cofo' ? 'CofO' : 'RofO' }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-slate-700 max-w-[220px] truncate" title="{{ $r->holder }}">{!! $dash($r->holder) !!}</td>
                                <td class="px-4 py-3 text-slate-600 whitespace-nowrap" title="stored as: {{ $r->grant_date_raw }}">
                                    {{ $r->grant_date_display ?: $r->grant_date_raw }}
                                    <span class="ml-1 px-1.5 py-0.5 rounded text-[10px] font-bold uppercase bg-slate-100 text-slate-500">{{ $r->date_source }}</span>
                                </td>
                                {{-- The term, and where it came from. A term read off the title and one
                                     assumed from the land use are very different claims, and an officer
                                     deciding whether to act on a re-grant needs to see which this is. --}}
                                <td class="px-4 py-3 text-center whitespace-nowrap">
                                    <span class="px-2 py-0.5 rounded text-[11px] font-semibold
                                        {{ $r->term_source === 'standard' ? 'bg-slate-100 text-slate-600' : 'bg-emerald-50 text-emerald-700' }}">
                                        {{ $r->term_years }} yrs
                                    </span>
                                    <span class="block mt-0.5 text-[10px] {{ $r->term_source === 'standard' ? 'text-slate-400' : 'text-emerald-600 font-semibold' }}"
                                          title="{{ $r->term_source === 'standard'
                                              ? 'No term is recorded for this file — the standard term for its land use was assumed.'
                                              : 'Read from the term actually granted on the record.' }}">
                                        {{ $r->term_source === 'standard' ? '○' : '●' }} {{ RegrantTermService::termSourceLabel($r->term_source) }}
                                    </span>
                                </td>
                                {{-- The exact expiry date where the commencement date parsed; the year
                                     alone where it did not, marked "~" rather than shown as a false
                                     precision the stored value does not support. --}}
                                <td class="px-4 py-3 text-center font-semibold text-slate-700 whitespace-nowrap">
                                    @if ($r->date_precision === 'date')
                                        {{ $r->expiry_date_display }}
                                    @else
                                        <span title="The stored commencement date could not be read as a calendar date, so only the year is known.">~{{ $r->expiry_year }}</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <span class="px-2 py-0.5 rounded-full text-[11px] font-bold border {{ $overdueTone((int) $r->years_overdue) }}">
                                        {{ $r->years_overdue }} yr{{ (int) $r->years_overdue === 1 ? '' : 's' }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-slate-600">{!! $dash($r->land_use) !!}</td>
                                <td class="px-4 py-3 text-slate-600 max-w-[300px] truncate" title="{{ $r->location }}">{!! $dash($r->location) !!}</td>
                            </tr>
                        @empty
                            <tr><td colspan="9" class="px-4 py-10 text-center text-slate-400">No files are currently due for re-grant.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Rows that could not be assessed, shown so the gap is visible rather than silent. --}}
        <div class="mt-4 p-4 rounded-xl bg-amber-50/60 border border-amber-200 text-xs text-amber-900 hidden" id="unassessable-info">
            <p class="font-bold mb-1.5 flex items-center gap-1.5">
                <i data-lucide="alert-triangle" class="w-4 h-4"></i> Not assessed
            </p>
            <p class="mb-1">
                Of {{ number_format($unassessable['with_instrument']) }} indexed files holding a CofO or RofO,
                <strong>{{ number_format($unassessable['no_date']) }}</strong> have no usable grant date
                (<code class="font-mono">cofo_date</code> / <code class="font-mono">transaction_date</code> empty or unparseable)
                and <strong>{{ number_format($unassessable['no_term']) }}</strong> have neither a recorded term nor a
                land use that carries a single one.
            </p>
            <p class="mb-1">
                <strong>{{ number_format($unassessable['actual_term']) }}</strong> are assessed against the term actually
                recorded on the file or its certificate; the rest fall back to the standard term for their land use.
            </p>
            <p class="mt-1.5 text-amber-700">
                Files with no usable date are excluded from the list above — without a commencement date there is nothing
                to add the term to. A mixed land use (e.g. "Residential/Commercial") carries two different terms, so it
                yields none unless the actual term is on record.
            </p>
        </div>
    @endif

    {{-- Pagination --}}
    <div class="mt-4">
        {{ $records->links() }}
    </div>
</div>

@include('admin.footer')
</div>
@endsection

@push('scripts')
<script>
const REGRANT_RAISE_URL = '{{ route('regrant.raise') }}';
const REGRANT_CSRF      = '{{ csrf_token() }}';

function regrantRaise(fileNo, btn) {
    Swal.fire({
        title: 'Raise Re-grant',
        html: `Record a Re-grant for <b>${fileNo}</b>?`,
        input: 'textarea',
        inputLabel: 'Reason (optional)',
        inputPlaceholder: 'e.g. RE-GRANT OF EXPIRED RIGHT OF OCCUPANCY',
        showCancelButton: true,
        confirmButtonText: 'Yes, raise it',
        confirmButtonColor: '#4f46e5',
    }).then(result => {
        if (!result.isConfirmed) return;

        btn.disabled = true;
        btn.innerHTML = 'Saving…';

        fetch(REGRANT_RAISE_URL, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': REGRANT_CSRF,
            },
            body: JSON.stringify({ file_no: fileNo, reason: result.value || '' }),
        })
        .then(r => r.json())
        .then(res => {
            if (!res.success) {
                btn.disabled = false;
                btn.innerHTML = '<i data-lucide="plus" class="w-3.5 h-3.5"></i> Raise';
                if (typeof lucide !== 'undefined') lucide.createIcons();
                Swal.fire({ icon: 'warning', title: 'Not raised', text: res.message || 'Could not raise the Re-grant.' });
                return;
            }
            Swal.fire({ icon: 'success', title: 'Re-grant raised', text: res.message, timer: 1600, showConfirmButton: false })
                .then(() => window.location.reload());
        })
        .catch(() => {
            btn.disabled = false;
            btn.innerHTML = '<i data-lucide="plus" class="w-3.5 h-3.5"></i> Raise';
            if (typeof lucide !== 'undefined') lucide.createIcons();
            Swal.fire({ icon: 'error', title: 'Error', text: 'Request failed.' });
        });
    });
}

@if ($canCorrect)
// --------------- Correcting a mis-captured Re-grant ---------------
//
// Two tools, both of which unwind the ORIGINAL entry before doing anything else:
// recording a Re-grant retires the parent file, so a record entered the wrong way
// round has the wrong file decommissioned. See RegrantCorrectionService.
//
// The menu is a SweetAlert chooser rather than a positioned dropdown on purpose -- the
// table sits inside `overflow-x-auto`, which clips an absolutely positioned panel.

const REGRANT_CORRECT_URL = '{{ url('regrant') }}';

function regrantEsc(value) {
    return String(value == null ? '' : value).replace(/[&<>"]/g, function (c) {
        return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c];
    });
}

function regrantOpenCorrect(id, fileNo, linkedFrom, hasStoredLink) {
    if (!hasStoredLink) {
        Swal.fire({
            icon: 'info',
            title: 'Nothing to correct here',
            html: '<p>No <b>Re-granted From</b> file is stored on this record'
                + (linkedFrom ? ' &mdash; <b>' + regrantEsc(linkedFrom) + '</b> is only inferred from the file&rsquo;s Related File No.' : '.')
                + '</p><p style="margin-top:8px">There is no direction to swap. Set the linked file in Title Status first.</p>',
        });
        return;
    }

    Swal.fire({
        title: 'Correct ' + regrantEsc(fileNo),
        html: '<p style="font-size:13px;color:#475569;margin-bottom:12px">Recorded as re-granted from <b>'
            + regrantEsc(linkedFrom) + '</b>.</p>'
            + '<div style="display:flex;flex-direction:column;gap:8px;text-align:left">'
            + '<button type="button" id="rg-swap" style="padding:10px 12px;border:1px solid #c7d2fe;border-radius:8px;background:#eef2ff;cursor:pointer">'
            + '<b>Swap the two file numbers</b>'
            + '<div style="font-size:12px;color:#475569;margin-top:2px">They were entered the wrong way round &mdash; '
            + regrantEsc(linkedFrom) + ' is really the new file.</div></button>'
            + '<button type="button" id="rg-undo" style="padding:10px 12px;border:1px solid #fecaca;border-radius:8px;background:#fef2f2;cursor:pointer">'
            + '<b>Not a Re-grant &mdash; undo it</b>'
            + '<div style="font-size:12px;color:#475569;margin-top:2px">Withdraw the record and put both files back as they were.</div></button>'
            + '</div>',
        showConfirmButton: false,
        showCancelButton: true,
        cancelButtonText: 'Close',
        didOpen: function () {
            document.getElementById('rg-swap').onclick = function () { regrantCorrection(id, 'swap'); };
            document.getElementById('rg-undo').onclick = function () { regrantCorrection(id, 'undo'); };
        },
    });
}

/** Fetch the plan, show exactly what will change, then apply it only on confirmation. */
function regrantCorrection(id, mode) {
    Swal.fire({ title: 'Checking...', didOpen: function () { Swal.showLoading(); }, allowOutsideClick: false });

    fetch(REGRANT_CORRECT_URL + '/' + id + '/correction-preview?mode=' + mode, {
        headers: { 'Accept': 'application/json' },
    })
    .then(function (r) { return r.json(); })
    .then(function (res) {
        if (!res.success) {
            Swal.fire({ icon: 'warning', title: 'Cannot correct this record', text: res.message });
            return;
        }
        regrantConfirm(id, mode, res.plan);
    })
    .catch(function () { Swal.fire({ icon: 'error', title: 'Error', text: 'Could not read the record.' }); });
}

function regrantConfirm(id, mode, plan) {
    var rows = plan.rows.map(function (r) {
        return '<tr><td style="padding:3px 8px;color:#64748b">' + regrantEsc(r.title_type) + '</td>'
             + '<td style="padding:3px 8px">' + regrantEsc(r.before) + '</td>'
             + '<td style="padding:3px 8px;color:#4f46e5"><b>' + regrantEsc(r.after) + '</b></td></tr>';
    }).join('');

    var warnings = (plan.warnings || []).map(function (w) {
        return '<li style="margin-bottom:4px">' + regrantEsc(w) + '</li>';
    }).join('');

    var headline = mode === 'swap'
        ? '<p style="margin-bottom:10px"><b>' + regrantEsc(plan.resulting.successor)
            + '</b> becomes the new file, re-granted from <b>' + regrantEsc(plan.resulting.parent) + '</b>.</p>'
        : '<p style="margin-bottom:10px">The Re-grant linking <b>' + regrantEsc(plan.current.successor)
            + '</b> and <b>' + regrantEsc(plan.current.parent)
            + '</b> is withdrawn. Neither file keeps a Re-grant status.</p>';

    // What happens to the retirement is the part an officer most needs to see: the
    // original entry decommissioned a file, and this says which file that was.
    var retirement = '<p style="margin-top:10px"><b>Retirement:</b> ';
    if (plan.release.clears_live_row) {
        retirement += '<b>' + regrantEsc(plan.release.file) + '</b> is brought back out of decommissioning'
            + (mode === 'swap' ? ', and <b>' + regrantEsc(plan.retire) + '</b> is retired in its place.' : '.');
    } else if (plan.release.releasable.length) {
        retirement += '<b>' + regrantEsc(plan.release.file)
            + '</b> keeps its decommissioning: the Re-grant retirement is released, but another workflow also retired it.';
    } else {
        retirement += 'no Re-grant retirement was found to release.';
    }
    retirement += '</p>';

    Swal.fire({
        icon: mode === 'undo' ? 'warning' : 'question',
        title: mode === 'swap' ? 'Swap the direction?' : 'Withdraw this Re-grant?',
        width: 640,
        html: '<div style="text-align:left;font-size:13px">'
            + headline
            + '<table style="width:100%;border-collapse:collapse;background:#f8fafc;border-radius:6px">'
            + '<tr style="font-size:11px;text-transform:uppercase;color:#94a3b8">'
            + '<td style="padding:3px 8px">Record</td><td style="padding:3px 8px">Now</td><td style="padding:3px 8px">After</td></tr>'
            + rows + '</table>'
            + retirement
            + (warnings ? '<ul style="margin-top:10px;padding-left:18px;color:#b45309">' + warnings + '</ul>' : '')
            + '</div>',
        input: 'text',
        inputLabel: 'Note for the audit trail (optional)',
        inputPlaceholder: 'e.g. confirmed against the physical file',
        showCancelButton: true,
        confirmButtonText: mode === 'swap' ? 'Yes, swap them' : 'Yes, withdraw it',
        confirmButtonColor: mode === 'swap' ? '#4f46e5' : '#dc2626',
    }).then(function (result) {
        if (!result.isConfirmed) return;

        Swal.fire({ title: 'Applying...', didOpen: function () { Swal.showLoading(); }, allowOutsideClick: false });

        fetch(REGRANT_CORRECT_URL + '/' + id + '/correct', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': REGRANT_CSRF,
            },
            body: JSON.stringify({ mode: mode, note: result.value || '' }),
        })
        .then(function (r) { return r.json(); })
        .then(function (res) {
            if (!res.success) {
                Swal.fire({ icon: 'error', title: 'Not applied', text: res.message || 'The correction failed.' });
                return;
            }
            var extra = (res.warnings || []).length
                ? '<ul style="text-align:left;margin-top:8px;padding-left:18px;color:#b45309;font-size:12px">'
                    + res.warnings.map(function (w) { return '<li>' + regrantEsc(w) + '</li>'; }).join('') + '</ul>'
                : '';
            Swal.fire({ icon: 'success', title: 'Corrected', html: '<p>' + regrantEsc(res.message) + '</p>' + extra })
                .then(function () { window.location.reload(); });
        })
        .catch(function () { Swal.fire({ icon: 'error', title: 'Error', text: 'Request failed.' }); });
    });
}

@endif

document.addEventListener('DOMContentLoaded', () => {
    if (typeof lucide !== 'undefined') lucide.createIcons();
});
</script>
@endpush
