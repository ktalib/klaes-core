@extends('layouts.app')

@section('page-title')
    File Indexing Activity Log
@endsection

@php
    // Weights may be fractional (e.g. 1.5) — show without trailing ".0".
    $fmtW = fn ($v) => rtrim(rtrim(number_format((float) $v, 1), '0'), '.');
@endphp

@section('content')
    <div class="flex-1 overflow-auto bg-gray-50">
        @include('admin.header')

        <div class="p-6" x-data="{ open: {}, showLeaderboard: false, leaderboardData: [] }">
            <!-- Leaderboard Modal -->
            <div x-show="showLeaderboard" x-cloak class="fixed inset-0 z-50 overflow-y-auto" @keydown.escape.window="showLeaderboard = false">
                <div class="flex min-h-screen items-center justify-center p-4">
                    <div class="fixed inset-0 bg-black bg-opacity-50 transition-opacity" @click="showLeaderboard = false"></div>
                    <div class="relative bg-white rounded-xl shadow-2xl max-w-4xl w-full max-h-[90vh] overflow-hidden">
                        <!-- Modal Header -->
                        <div class="flex items-center justify-between p-6 border-b border-gray-200">
                            <div class="flex items-center gap-3">
                                <span class="inline-flex h-10 w-10 items-center justify-center rounded-lg bg-gradient-to-r from-amber-500 to-orange-500 text-white">
                                    <i data-lucide="trophy" class="h-6 w-6"></i>
                                </span>
                                <h3 class="text-xl font-bold text-gray-800">Top 10 Leaderboard</h3>
                            </div>
                            <button @click="showLeaderboard = false" class="text-gray-400 hover:text-gray-600 transition-colors">
                                <i data-lucide="x" class="h-6 w-6"></i>
                            </button>
                        </div>
                        <!-- Modal Content -->
                        <div class="p-6 overflow-y-auto max-h-[70vh]">
                            <div class="space-y-4" x-show="leaderboardData.length > 0">
                                <template x-for="(user, index) in leaderboardData" :key="index">
                                    <div class="flex items-center justify-between p-4 bg-gray-50 rounded-lg hover:bg-gray-100 transition-colors">
                                        <div class="flex items-center gap-4">
                                            <span class="inline-flex h-8 w-8 items-center justify-center rounded-full text-sm font-bold"
                                                :class="{
                                                    'bg-gradient-to-r from-yellow-400 to-yellow-500 text-white': index === 0,
                                                    'bg-gradient-to-r from-gray-300 to-gray-400 text-white': index === 1,
                                                    'bg-gradient-to-r from-orange-400 to-orange-500 text-white': index === 2,
                                                    'bg-gray-200 text-gray-700': index > 2
                                                }"
                                                x-text="'#' + (index + 1)"></span>
                                            <div>
                                                <div class="font-semibold text-gray-800" x-text="user.name"></div>
                                                <div class="text-sm text-gray-500">
                                                    <span x-text="user.files"></span> files • 
                                                    <span x-text="user.indexed"></span> indexed • 
                                                    <span x-text="user.transactions"></span> transactions
                                                </div>
                                            </div>
                                        </div>
                                        <div class="text-right">
                                            <div class="text-lg font-bold text-indigo-600" x-text="user.weight"></div>
                                            <div class="text-xs text-gray-500">Total Weight</div>
                                        </div>
                                    </div>
                                </template>
                            </div>
                            <div x-show="leaderboardData.length === 0" class="text-center py-12">
                                <div class="flex flex-col items-center gap-3 text-gray-400">
                                    <i data-lucide="loader-2" class="h-8 w-8 animate-spin"></i>
                                    <p>Loading leaderboard...</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <!-- Page heading -->
            <div class="mb-6 flex flex-wrap items-start justify-between gap-3">
                <div>
                    <div class="flex items-center gap-3 mb-1">
                        <span class="inline-flex h-9 w-9 items-center justify-center rounded-lg bg-indigo-100 text-indigo-600">
                            <i data-lucide="activity" class="h-5 w-5"></i>
                        </span>
                        <h2 class="text-xl font-bold text-gray-800">File Indexing Activity Log</h2>
                    </div>
                    <p class="text-sm text-gray-500 ml-12">Per-user file indexing productivity & weighted scoring.</p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <button type="button" @click="showLeaderboard = true; fetchLeaderboard()"
                        class="inline-flex items-center gap-2 rounded-lg bg-gradient-to-r from-amber-500 to-orange-500 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:from-amber-600 hover:to-orange-600 transition-all">
                        <i data-lucide="trophy" class="h-4 w-4"></i> Top 10 Leaderboard
                    </button>
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-blue-50 px-3 py-1 text-xs font-semibold text-blue-700 ring-1 ring-inset ring-blue-200">
                        <i data-lucide="files" class="h-3.5 w-3.5"></i> Files &times;{{ $weights['files'] }}
                    </span>
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-700 ring-1 ring-inset ring-emerald-200">
                        <i data-lucide="file-check-2" class="h-3.5 w-3.5"></i> Indexed &times;{{ $fmtW($weights['indexed']) }}
                    </span>
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-amber-50 px-3 py-1 text-xs font-semibold text-amber-700 ring-1 ring-inset ring-amber-200">
                        <i data-lucide="receipt-text" class="h-3.5 w-3.5"></i> Txns &times;{{ $weights['transactions'] }}
                    </span>
                </div>
            </div>

            <!-- Filters -->
            <form method="GET" action="{{ route('fileindexing.activity-log') }}"
                class="bg-white rounded-xl border border-gray-200 shadow-sm p-4 mb-6 grid grid-cols-1 md:grid-cols-4 gap-4">
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500 mb-1.5">User</label>
                    <select name="user"
                        class="block w-full px-3 py-2 border border-gray-300 rounded-lg shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                        <option value="">All users</option>
                        @foreach($users as $id => $name)
                            <option value="{{ $id }}" {{ (string) $filters['user'] === (string) $id ? 'selected' : '' }}>{{ $name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500 mb-1.5">From</label>
                    <input type="date" name="date_from" value="{{ $filters['date_from'] }}"
                        class="block w-full px-3 py-2 border border-gray-300 rounded-lg shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                </div>
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500 mb-1.5">To</label>
                    <input type="date" name="date_to" value="{{ $filters['date_to'] }}"
                        class="block w-full px-3 py-2 border border-gray-300 rounded-lg shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                </div>
                <div class="flex items-end gap-2">
                    <button type="submit"
                        class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-700 transition-colors">
                        <i data-lucide="filter" class="h-4 w-4"></i> Apply
                    </button>
                    <a href="{{ route('fileindexing.activity-log') }}"
                        class="inline-flex items-center gap-2 rounded-lg border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50 transition-colors">
                        <i data-lucide="rotate-ccw" class="h-4 w-4"></i> Reset
                    </a>
                </div>
            </form>

            <!-- Summary cards -->
            @php
                $cards = [
                    ['Number of Indexers', count($groups), 'users', 'blue'],
                    ['Total Files Indexed', $totals['indexed'], 'file-check-2', 'emerald'],
                    ['Related Files', $totals['related'], 'git-fork', 'violet'],
                    ['Transactions', $totals['transactions'], 'receipt-text', 'amber'],
                    ['Total Weight', $totals['weight'], 'scale', 'indigo'],
                ];
                $palette = [
                    'blue'    => ['bg' => 'bg-blue-50',    'text' => 'text-blue-600',    'bar' => 'from-blue-500 to-blue-400'],
                    'emerald' => ['bg' => 'bg-emerald-50', 'text' => 'text-emerald-600', 'bar' => 'from-emerald-500 to-emerald-400'],
                    'violet'  => ['bg' => 'bg-violet-50',  'text' => 'text-violet-600',  'bar' => 'from-violet-500 to-violet-400'],
                    'amber'   => ['bg' => 'bg-amber-50',   'text' => 'text-amber-600',   'bar' => 'from-amber-500 to-amber-400'],
                    'indigo'  => ['bg' => 'bg-indigo-50',  'text' => 'text-indigo-600',  'bar' => 'from-indigo-500 to-indigo-400'],
                ];
            @endphp
            <div class="grid grid-cols-2 md:grid-cols-5 gap-4 mb-6">
                @foreach($cards as [$label, $value, $icon, $color])
                    @php $c = $palette[$color]; @endphp
                    <div class="relative bg-white rounded-xl border border-gray-200 shadow-sm p-4 overflow-hidden">
                        <div class="flex items-center justify-between mb-2">
                            <span class="text-[11px] font-semibold uppercase tracking-wide text-gray-400">{{ $label }}</span>
                            <span class="inline-flex h-8 w-8 items-center justify-center rounded-lg {{ $c['bg'] }} {{ $c['text'] }}">
                                <i data-lucide="{{ $icon }}" class="h-4 w-4"></i>
                            </span>
                        </div>
                        <div class="text-3xl font-extrabold text-gray-800 leading-none">{{ $label === 'Total Weight' ? $fmtW($value) : number_format($value) }}</div>
                        <div class="mt-3 h-1 w-full rounded-full bg-gray-100 overflow-hidden">
                            <div class="h-full rounded-full bg-gradient-to-r {{ $c['bar'] }}" style="width: {{ $totals['weight'] > 0 ? min(100, round($value / max($totals['weight'],1) * 100)) : 0 }}%"></div>
                        </div>
                    </div>
                @endforeach
            </div>

            <!-- Grouped table -->
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="bg-gray-50 text-left text-[11px] uppercase tracking-wide text-gray-500 border-b border-gray-200">
                            <th class="px-4 py-3 font-semibold text-center w-12">S/N</th>
                            <th class="px-5 py-3 font-semibold">User / File Number</th>
                            <th class="px-4 py-3 font-semibold text-center">Number of Files</th>
                            <th class="px-4 py-3 font-semibold text-center">Related Files</th>
                            <th class="px-4 py-3 font-semibold text-center">File History / Transactions</th>
                            <th class="px-4 py-3 font-semibold">Registry</th>
                            <th class="px-5 py-3 font-semibold text-right">Total Weight</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($groups as $i => $group)
                            @php
                                $rank = $i + 1;
                                $parts = preg_split('/\s+/', trim($group['user']));
                                $initials = strtoupper(substr($parts[0] ?? 'U', 0, 1) . (count($parts) > 1 ? substr(end($parts), 0, 1) : ''));
                                $avatarColors = ['bg-rose-500','bg-orange-500','bg-amber-500','bg-emerald-500','bg-teal-500','bg-sky-500','bg-indigo-500','bg-violet-500','bg-fuchsia-500'];
                                $avatarBg = $avatarColors[crc32($group['user']) % count($avatarColors)];
                                $rankBadge = match($rank) {
                                    1 => 'bg-amber-100 text-amber-700 ring-amber-300',
                                    2 => 'bg-slate-100 text-slate-600 ring-slate-300',
                                    3 => 'bg-orange-100 text-orange-700 ring-orange-300',
                                    default => 'bg-gray-50 text-gray-400 ring-gray-200',
                                };
                            @endphp
                            <!-- User group header -->
                            <tr class="border-b border-gray-100 bg-indigo-50/40 hover:bg-indigo-50 cursor-pointer transition-colors"
                                @click="open[{{ $i }}] = !open[{{ $i }}]">
                                <td class="px-4 py-3 text-center">
                                    <span class="inline-flex h-6 w-6 items-center justify-center rounded-md text-[11px] font-bold ring-1 ring-inset {{ $rankBadge }}">{{ $rank }}</span>
                                </td>
                                <td class="px-5 py-3">
                                    <div class="flex items-center gap-3">
                                        <i data-lucide="chevron-right" class="h-4 w-4 text-gray-400 transition-transform shrink-0"
                                            :class="open[{{ $i }}] ? 'rotate-90' : ''"></i>
                                        <span class="inline-flex h-7 w-7 items-center justify-center rounded-full text-[10px] font-bold text-white {{ $avatarBg }} shrink-0">{{ $initials }}</span>
                                        <span class="font-semibold text-gray-800">{{ $group['user'] }}</span>
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <span class="inline-flex items-center rounded-full bg-blue-50 px-2.5 py-1 text-xs font-bold text-blue-700 ring-1 ring-inset ring-blue-200">{{ number_format($group['files']) }}</span>
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <span class="inline-flex items-center rounded-full bg-sky-50 px-2.5 py-1 text-xs font-bold text-sky-700 ring-1 ring-inset ring-sky-200">{{ number_format($group['related']) }}</span>
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <span class="inline-flex items-center rounded-full bg-amber-50 px-2.5 py-1 text-xs font-bold text-amber-700 ring-1 ring-inset ring-amber-200">{{ number_format($group['transactions']) }}</span>
                                </td>
                                <td class="px-4 py-3">
                                    @php $regs = $group['registries'] ?? []; @endphp
                                    <div class="flex flex-wrap gap-1">
                                        @forelse(array_slice($regs, 0, 2) as $reg)
                                            <span class="inline-flex items-center rounded-md bg-violet-50 px-2 py-0.5 text-[11px] font-semibold text-violet-700 ring-1 ring-inset ring-violet-200">{{ $reg }}</span>
                                        @empty
                                            <span class="text-xs text-gray-300">&mdash;</span>
                                        @endforelse
                                        @if(count($regs) > 2)
                                            <span class="inline-flex items-center rounded-md bg-gray-100 px-2 py-0.5 text-[11px] font-semibold text-gray-500">+{{ count($regs) - 2 }}</span>
                                        @endif
                                    </div>
                                </td>
                                <td class="px-5 py-3 text-right">
                                    <span class="inline-flex items-center gap-1 rounded-lg bg-gradient-to-r from-indigo-600 to-violet-600 px-3 py-1 text-sm font-bold text-white shadow-sm">
                                        <i data-lucide="scale" class="h-3.5 w-3.5"></i> {{ $fmtW($group['weight']) }}
                                    </span>
                                </td>
                            </tr>
                            <!-- File rows (nested) -->
                            @foreach($group['rows'] as $rowIndex => $row)
                                <tr x-show="open[{{ $i }}]" x-cloak class="border-b border-gray-50 hover:bg-gray-50/70 transition-colors">
                                    <td class="px-4 py-2.5 text-center">
                                        <span class="text-xs font-medium text-gray-400">{{ $rank }}.{{ $rowIndex + 1 }}</span>
                                    </td>
                                    <td class="px-5 py-2.5">
                                        <div class="flex items-center gap-2 pl-9">
                                            <span class="h-1.5 w-1.5 rounded-full bg-indigo-300 shrink-0"></span>
                                            <div class="min-w-0">
                                                <span class="font-medium text-gray-700">{{ $row['file_number'] }}</span>
                                                @if($row['file_title'])
                                                    <span class="block text-xs text-gray-400 truncate max-w-md">{{ $row['file_title'] }}</span>
                                                @endif
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-4 py-2.5 text-center">
                                        <span class="inline-flex items-center rounded-md bg-gray-100 px-2 py-0.5 text-xs font-semibold text-gray-700">{{ number_format($row['number_of_files']) }}</span>
                                    </td>
                                    <td class="px-4 py-2.5 text-center">
                                        @if($row['related_files'] > 0)
                                            <span class="inline-flex items-center rounded-md bg-sky-50 px-2 py-0.5 text-xs font-semibold text-sky-700"
                                                title="{{ implode(', ', $row['related_file_numbers']) }}">{{ number_format($row['related_files']) }}</span>
                                        @else
                                            <span class="text-xs text-gray-300">&mdash;</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-2.5 text-center">
                                        @if($row['transactions'] > 0)
                                            <span class="inline-flex items-center rounded-md bg-amber-50 px-2 py-0.5 text-xs font-semibold text-amber-700">{{ number_format($row['transactions']) }}</span>
                                        @else
                                            <span class="text-xs text-gray-300">&mdash;</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-2.5">
                                        @if($row['registry'])
                                            <span class="inline-flex items-center rounded-md bg-violet-50 px-2 py-0.5 text-[11px] font-semibold text-violet-700">{{ $row['registry'] }}</span>
                                        @else
                                            <span class="text-xs text-gray-300">&mdash;</span>
                                        @endif
                                    </td>
                                    <td class="px-5 py-2.5 text-right">
                                        <span class="inline-flex items-center rounded-md bg-indigo-50 px-2 py-0.5 text-xs font-bold text-indigo-700">{{ $fmtW($row['weight']) }}</span>
                                    </td>
                                </tr>
                            @endforeach
                        @empty
                            <tr>
                                <td colspan="7" class="px-4 py-16 text-center">
                                    <div class="flex flex-col items-center gap-2 text-gray-400">
                                        <i data-lucide="inbox" class="h-10 w-10"></i>
                                        <p class="text-sm">No file indexing activity found for the selected filters.</p>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                    @if(count($groups))
                        <tfoot>
                            <tr class="bg-gray-50 border-t-2 border-gray-200 font-bold text-gray-800">
                                <td class="px-4 py-3.5"></td>
                                <td class="px-5 py-3.5">
                                    <span class="inline-flex items-center gap-2">
                                        <i data-lucide="sigma" class="h-4 w-4 text-gray-500"></i> Grand Total
                                    </span>
                                </td>
                                <td class="px-4 py-3.5 text-center">{{ number_format($totals['files']) }}</td>
                                <td class="px-4 py-3.5 text-center">{{ number_format($totals['related']) }}</td>
                                <td class="px-4 py-3.5 text-center">{{ number_format($totals['transactions']) }}</td>
                                <td class="px-4 py-3.5"></td>
                                <td class="px-5 py-3.5 text-right">
                                    <span class="inline-flex items-center gap-1 rounded-lg bg-gradient-to-r from-indigo-600 to-violet-600 px-3 py-1 text-sm text-white shadow-sm">
                                        <i data-lucide="scale" class="h-3.5 w-3.5"></i> {{ $fmtW($totals['weight']) }}
                                    </span>
                                </td>
                            </tr>
                        </tfoot>
                    @endif
                </table>
            </div>
        </div>
    </div>

    <style>[x-cloak]{display:none !important;}</style>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            if (window.lucide && typeof window.lucide.createIcons === 'function') {
                window.lucide.createIcons();
            }
        });

        function fetchLeaderboard() {
            // Get Alpine.js component data
            const component = Alpine.$data(document.querySelector('[x-data]'));
            
            // Reset data
            component.leaderboardData = [];

            // Pass the same filters the page is using so the leaderboard matches
            // the table (and inherits the fast "today" default when unfiltered).
            const params = new URLSearchParams({
                user: @json($filters['user']),
                date_from: @json($filters['date_from']),
                date_to: @json($filters['date_to']),
            });

            // Fetch leaderboard data
            fetch('{{ route('fileindexing.leaderboard-data') }}?' + params.toString())
                .then(response => response.json())
                .then(data => {
                    component.leaderboardData = data.slice(0, 10); // Top 10
                    // Re-create icons for new content
                    setTimeout(() => {
                        if (window.lucide && typeof window.lucide.createIcons === 'function') {
                            window.lucide.createIcons();
                        }
                    }, 100);
                })
                .catch(error => {
                    console.error('Error fetching leaderboard:', error);
                    component.leaderboardData = [];
                });
        }
    </script>
@endsection
