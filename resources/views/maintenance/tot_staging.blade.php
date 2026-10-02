@extends('layouts.app')

@section('page-title')
    {{ __('ToT Intelligence Dashboard') }}
@endsection

@section('content')
<div class="flex-1 overflow-auto bg-slate-50/60">
    @include('admin.header', [
        'PageTitle' => 'ToT Dashboard',
        'PageDescription' => 'Every Transfer of Title on record'
    ])

    <div class="p-6 space-y-8 max-w-7xl mx-auto">
        <!-- Header & Actions -->
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
            <div>
                <h1 class="text-3xl font-bold text-slate-900"></h1>
                <p class="text-slate-500 mt-1">Every Transfer of Title recorded in the deeds register</p>
            </div>
            <div class="flex gap-3">
                {{-- Match OP — the same job one file at a time, and where the records
                     this dashboard lists come from. Kept beside the bulk actions so an
                     officer who arrives here to find "All Caught Up" can go and match
                     the files nobody has reached yet, instead of leaving empty-handed. --}}
                {{-- `from=tot` marks the origin so the DMS sidebar entry does NOT light
                     up: the officer came from here, and a sidebar highlighting a
                     section they did not open reads as having navigated somewhere
                     they did not go. See edms.blade.php, which checks for it. --}}
                <a href="{{ route('land-recommendations.create') }}?match-op&from=tot"
                   class="inline-flex items-center gap-2 px-6 py-3 rounded-xl bg-white border border-amber-300 text-amber-700 font-semibold shadow-sm hover:bg-amber-50 transition">
                    <i data-lucide="git-merge" class="h-5 w-5"></i>
                    <span>Match OP</span>
                </a>
                {{-- Both bulk actions are disabled. Transfers are written one file at a
                     time through Match OP, where the officer sees the file's whole
                     chain and confirms the two names before anything is recorded — a
                     dealing on somebody's title is not something to generate in bulk
                     off a staging list. The buttons are kept, disabled, rather than
                     removed: they say plainly that the capability exists and is shut,
                     and the JS that binds them still finds them. --}}
                <button id="btnIgnore" disabled
                        title="Archiving in bulk is disabled — use Match OP to work these files one at a time."
                        class="inline-flex items-center gap-2 px-6 py-3 rounded-xl bg-white border border-slate-200 text-slate-400 font-semibold shadow-sm opacity-60 cursor-not-allowed">
                    <i data-lucide="eye-off" class="h-5 w-5"></i>
                    <span>Archive Selected</span>
                </button>
                <button id="btnGenerate" disabled
                        title="Bulk ToT generation is disabled — use Match OP, which shows the file's chain and confirms both names before writing."
                        class="inline-flex items-center gap-2 px-6 py-3 rounded-xl bg-slate-300 text-white font-semibold shadow opacity-60 cursor-not-allowed">
                    <i data-lucide="zap" class="h-5 w-5"></i>
                    <span>Execute ToT Generation</span>
                </button>
            </div>
        </div>

        <!-- Stats Grid -->
        {{-- Counted off the same query the table lists from, so the cards and the rows
             can never disagree about what a Transfer of Title is. --}}
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <a href="{{ route('maintenance.tot.index') }}"
               class="bg-white p-6 rounded-xl shadow-sm border {{ $filter ? 'border-slate-100' : 'border-blue-200 ring-1 ring-blue-100' }} flex items-center gap-4 hover:border-blue-200 transition">
                <div class="h-12 w-12 bg-blue-50 text-blue-600 rounded-lg flex items-center justify-center">
                    <i data-lucide="file-symlink" class="h-6 w-6"></i>
                </div>
                <div>
                    <p class="text-sm text-slate-500 font-medium uppercase tracking-wider">Transfers of Title</p>
                    <h3 class="text-2xl font-bold text-slate-800">{{ number_format($total) }}</h3>
                </div>
            </a>

            <a href="{{ route('maintenance.tot.index', ['filter' => 'system']) }}"
               class="bg-white p-6 rounded-xl shadow-sm border hidden {{ $filter === 'system' ? 'border-blue-200 ring-1 ring-blue-100' : 'border-slate-100' }} flex items-center gap-4 hover:border-blue-200 transition">
                <div class="h-12 w-12 bg-blue-50 text-blue-600 rounded-lg flex items-center justify-center">
                    <i data-lucide="git-merge" class="h-6 w-6"></i>
                </div>
                <div>
                    <p class="text-sm text-slate-500 font-medium uppercase tracking-wider">System Generated</p>
                    <h3 class="text-2xl font-bold text-slate-800">{{ number_format($systemGenerated) }}</h3>
                    <p class="text-[11px] text-slate-400 mt-0.5">Written by Match OP</p>
                </div>
            </a>

            <a href="{{ route('maintenance.tot.index', ['filter' => 'captured']) }}"
               class="bg-white p-6 rounded-xl shadow-sm border {{ $filter === 'captured' ? 'border-blue-200 ring-1 ring-blue-100' : 'border-slate-100' }} flex items-center gap-4 hover:border-blue-200 transition">
                <div class="h-12 w-12 bg-amber-50 text-amber-600 rounded-lg flex items-center justify-center">
                    <i data-lucide="edit-3" class="h-6 w-6"></i>
                </div>
                <div>
                    <p class="text-sm text-slate-500 font-medium uppercase tracking-wider">Captured</p>
                    <h3 class="text-2xl font-bold text-slate-800">{{ number_format($captured) }}</h3>
                    <p class="text-[11px] text-slate-400 mt-0.5">Keyed from a document</p>
                </div>
            </a>
        </div>

        <!-- Data Table Section -->
        <div class="bg-white rounded-xl shadow-sm border border-slate-100 overflow-hidden">
            <div class="p-6 border-b border-slate-100 bg-slate-50/50 flex flex-col md:flex-row md:justify-between md:items-center gap-4">
                <h2 class="text-lg font-semibold text-slate-800">
                    Transfers of Title
                    @if($filter === 'system') <span class="text-sm font-normal text-slate-500">— system generated</span>
                    @elseif($filter === 'captured') <span class="text-sm font-normal text-slate-500">— captured</span>
                    @endif
                </h2>
                <div class="flex items-center gap-3">
                    {{-- 1,300+ rows: a register this size is unusable without a way in. --}}
                    <form method="GET" action="{{ route('maintenance.tot.index') }}" class="flex items-center gap-2">
                        @if($filter)<input type="hidden" name="filter" value="{{ $filter }}">@endif
                        <div class="relative">
                            <i data-lucide="search" class="h-4 w-4 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2"></i>
                            <input type="text" name="q" value="{{ $search }}" placeholder="File number or party name"
                                   class="pl-9 pr-3 py-2 w-64 rounded-lg border border-slate-200 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none">
                        </div>
                        <button type="submit" class="px-3 py-2 rounded-lg bg-slate-800 text-white text-sm font-semibold hover:bg-slate-900 transition">Search</button>
                        @if($search !== '')
                            <a href="{{ route('maintenance.tot.index', $filter ? ['filter' => $filter] : []) }}"
                               class="px-3 py-2 rounded-lg border border-slate-200 text-slate-600 text-sm hover:bg-slate-50 transition">Clear</a>
                        @endif
                    </form>
                    <span class="text-sm text-slate-600 whitespace-nowrap">{{ $records->count() }} of {{ number_format($records->total()) }}</span>
                </div>
            </div>
            <div class="p-6  S">
                <div class="overflow-x-auto -mx-6 px-6">
                    <table id="totStagingTable" class="w-full text-left border-collapse" style="min-width: 1000px;">
                        <thead>
                            <tr class="text-slate-500 text-xs uppercase tracking-wider border-b border-slate-200">
                                <th class="pb-4 font-semibold whitespace-nowrap">File No</th>
                                <th class="pb-4 font-semibold whitespace-nowrap">Transferred From</th>
                                <th class="pb-4 font-semibold whitespace-nowrap">Transferred To</th>
                                <th class="pb-4 font-semibold whitespace-nowrap">Instrument</th>
                                <th class="pb-4 font-semibold whitespace-nowrap">Date</th>
                                <th class="pb-4 font-semibold whitespace-nowrap">Reg. Particulars</th>
                                <th class="pb-4 font-semibold w-10"></th>
                            </tr>
                        </thead>
                        <tbody class="text-slate-600 text-sm">
                            @forelse($records->items() as $record)
                            @php
                                $fileNo = $record->mlsFNo ?: ($record->fileno ?: ($record->kangisFileNo ?: $record->NewKANGISFileno));
                                $isSystem = trim((string) $record->system_source) === 'OPHOLDERMATCH';
                            @endphp
                            <tr class="tot-row hover:bg-slate-50 transition-colors border-b border-slate-100 align-top cursor-pointer"
                                data-file="{{ $fileNo }}" data-row="{{ $record->id }}">
                                <td class="py-4">
                                    <span class="font-mono text-xs font-bold text-blue-700 bg-blue-50 px-2 py-0.5 rounded-full inline-block whitespace-nowrap">{{ $fileNo ?: '—' }}</span>
                                    @if($record->prop_id)
                                        <div class="text-[10px] text-slate-400 mt-1">prop {{ $record->prop_id }}</div>
                                    @endif
                                </td>
                                <td class="py-4">
                                    <span class="text-slate-800 block truncate max-w-[220px]" title="{{ $record->party_1 }}">{{ $record->party_1 ?: '—' }}</span>
                                </td>
                                <td class="py-4">
                                    <div class="flex items-start gap-1.5">
                                        <i data-lucide="move-right" class="h-4 w-4 text-slate-300 shrink-0 mt-0.5"></i>
                                        <span class="font-semibold text-slate-900 block truncate max-w-[220px]" title="{{ $record->party_2 }}">{{ $record->party_2 ?: '—' }}</span>
                                    </div>
                                </td>
                                <td class="py-4 whitespace-nowrap">{{ $record->transaction_type ?: $record->instrument_type }}</td>
                                <td class="py-4 whitespace-nowrap text-slate-500">{{ $record->transaction_date ?: '—' }}</td>
                                <td class="py-4 whitespace-nowrap font-mono text-xs text-slate-500">{{ $record->regNo ?: '—' }}</td>
                                <td class="py-4 text-right">
                                    <i data-lucide="chevron-down" class="h-4 w-4 text-slate-400 inline-block transition-transform" data-chev="{{ $record->id }}"></i>
                                </td>
                            </tr>

                            {{-- The file's whole chain, fetched the first time the row is opened.
                                 It comes from the same endpoint the Match card uses, so this page
                                 and that one cannot describe a file differently — and it is loaded
                                 on demand because reading a chain costs 3-5 seconds, which is not
                                 something to spend 25 times over on a page nobody has expanded. --}}
                            <tr class="tot-detail hidden bg-slate-50/60" data-detail="{{ $record->id }}">
                                <td colspan="7" class="px-4 py-4">
                                    <div class="flex flex-wrap items-center gap-x-6 gap-y-1 mb-3 text-[11px] text-slate-500">
                                        <span><span class="font-semibold text-slate-400 uppercase tracking-wider">Origin</span>
                                            @if($isSystem)
                                                <span class="ml-1 px-2 py-0.5 rounded-full bg-blue-600 text-white text-[10px] font-black uppercase">System Generated</span>
                                            @else
                                                <span class="ml-1 font-medium text-slate-700">{{ $record->source ?: 'Captured' }}</span>
                                            @endif
                                        </span>
                                        <span><span class="font-semibold text-slate-400 uppercase tracking-wider">pra id</span>
                                            <span class="ml-1 font-mono text-slate-700">{{ $record->id }}</span></span>
                                        @if($record->prop_id)
                                            <span><span class="font-semibold text-slate-400 uppercase tracking-wider">prop id</span>
                                                <span class="ml-1 font-mono text-slate-700">{{ $record->prop_id }}</span></span>
                                        @endif
                                        @if($record->created_at)
                                            <span><span class="font-semibold text-slate-400 uppercase tracking-wider">recorded</span>
                                                <span class="ml-1 text-slate-700">{{ $record->created_at }}</span></span>
                                        @endif
                                    </div>
                                    <div data-timeline="{{ $record->id }}"></div>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="7" class="py-20 text-center">
                                    <div class="flex flex-col items-center gap-3">
                                        <div class="h-16 w-16 bg-slate-50 text-slate-400 rounded-full flex items-center justify-center">
                                            <i data-lucide="file-search" class="h-8 w-8"></i>
                                        </div>
                                        <h3 class="text-xl font-bold text-slate-800">Nothing to show</h3>
                                        <p class="text-slate-500">
                                            @if($search !== '')
                                                No Transfer of Title matches “{{ $search }}”.
                                            @else
                                                No Transfer of Title is recorded yet.
                                            @endif
                                        </p>
                                    </div>
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Pagination -->
        <div class="flex justify-between items-center mt-6">
            <p class="text-sm text-slate-600">Total: {{ number_format($records->total()) }} transfer(s) of title</p>
            <div>
                {{ $records->links() }}
            </div>
        </div>

@push('scripts')
<script>
{{-- The bulk generate / archive handlers lived here and have been removed with the
     work queue they drove: this page is now a register of what was written, and the
     two buttons above are disabled. Transfers are written one file at a time through
     Match OP, which confirms both names against the file's chain before recording a
     dealing on somebody's title.

     The POST endpoints (maintenance.tot.generate / .ignore) and the artisan command
     tot:generate-from-staging still exist and still work — nothing here closes them. --}}
document.addEventListener('DOMContentLoaded', function () {
    if (window.lucide) window.lucide.createIcons();

    var CHECK_URL = '{{ route('land-recommendations.op-match.check') }}';
    var loaded = {};

    function esc(v) {
        return String(v === null || v === undefined ? '' : v)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#039;');
    }

    // Same visual language as the Match card: amber for the grant, blue for a
    // transfer, muted for everything else, with the source register named.
    function timelineHtml(rows) {
        if (!rows || !rows.length) {
            return '<p class="text-xs text-slate-500 italic">No history is recorded on this file.</p>';
        }

        return '<ul class="border-l border-slate-200 ml-1">' + rows.map(function (r) {
            var tone = r.is_op ? 'border-amber-300 bg-amber-50'
                : (r.is_tot ? 'border-blue-500 bg-blue-100' : 'border-slate-200 bg-white');
            var dot = r.is_op ? 'bg-amber-500' : (r.is_tot ? 'bg-blue-500' : 'bg-slate-300');
            var rot = r.root_of_title
                ? '<span class="ml-1.5 text-[10px] font-bold italic text-violet-700">-RoT</span>' : '';
            var src = r.source
                ? '<span class="ml-2 rounded px-1.5 py-0.5 text-[9px] font-bold uppercase tracking-wide bg-slate-200/80 text-slate-600">'
                    + esc(String(r.source).replace(/_staging$/, '').replace(/_/g, ' ')) + '</span>' : '';
            var sysgen = r.system_generated
                ? '<span class="ml-2 rounded px-1.5 py-0.5 text-[9px] font-bold uppercase tracking-wide bg-blue-600 text-white">New (System Generated)</span>' : '';

            return '<li class="relative pl-6 pb-3 last:pb-0">'
                + '<span class="absolute left-0 top-1.5 h-2.5 w-2.5 rounded-full ' + dot + '"></span>'
                + '<div class="rounded-lg border ' + tone + ' px-3 py-2">'
                +   '<div class="flex items-center justify-between gap-3">'
                +     '<span class="text-xs font-bold text-slate-800">' + esc(r.type) + rot + src + sysgen + '</span>'
                +     '<span class="text-[10px] text-slate-500 whitespace-nowrap">' + esc(r.date || '—') + '</span>'
                +   '</div>'
                +   '<div class="mt-1 text-[11px] text-slate-600">'
                +     '<span class="font-medium">' + esc(r.party_1 || '—') + '</span>'
                +     ' <span class="text-slate-400">&rarr;</span> '
                +     '<span class="font-medium">' + esc(r.party_2 || '—') + '</span>'
                +   '</div>'
                + '</div>'
                + '</li>';
        }).join('') + '</ul>';
    }

    document.querySelectorAll('.tot-row').forEach(function (row) {
        row.addEventListener('click', function () {
            var id = row.getAttribute('data-row');
            var file = (row.getAttribute('data-file') || '').trim();
            var detail = document.querySelector('[data-detail="' + id + '"]');
            var chev = document.querySelector('[data-chev="' + id + '"]');
            if (!detail) return;

            var opening = detail.classList.contains('hidden');
            detail.classList.toggle('hidden', !opening);
            if (chev) chev.style.transform = opening ? 'rotate(180deg)' : '';

            if (!opening || loaded[id]) return;
            loaded[id] = true;

            var host = detail.querySelector('[data-timeline="' + id + '"]');
            if (!file) {
                host.innerHTML = '<p class="text-xs text-slate-500 italic">This row carries no file number to read a history from.</p>';
                return;
            }

            host.innerHTML = '<div class="flex items-center gap-2 text-xs text-slate-500">'
                + '<i data-lucide="loader" class="h-4 w-4 animate-spin text-blue-600"></i>'
                + 'Reading the file history…</div>';
            if (window.lucide) window.lucide.createIcons();

            fetch(CHECK_URL + '?file_number=' + encodeURIComponent(file), {
                credentials: 'same-origin',
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
            })
                .then(function (r) { return r.ok ? r.json() : null; })
                .then(function (payload) {
                    if (!payload || !payload.success) throw new Error('no data');
                    host.innerHTML = timelineHtml((payload.data || {}).timeline);
                })
                .catch(function () {
                    // Retryable: clear the cache flag so closing and reopening tries again.
                    loaded[id] = false;
                    host.innerHTML = '<p class="text-xs text-rose-700">The file history could not be read. Close and open the row to try again.</p>';
                });
        });
    });
});
</script>
@endpush
@endsection
