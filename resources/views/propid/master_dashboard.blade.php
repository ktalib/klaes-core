@extends('layouts.app')

@section('page-title', $PageTitle ?? 'PropID Master Dashboard')

@section('content')
    <div class="flex-1 overflow-auto bg-gray-50 min-h-screen">
        @include('admin.header')

        <div class="max-w-7xl mx-auto px-6 py-10 space-y-8">
            <header class="space-y-2">
                <p class="text-sm uppercase tracking-wide text-indigo-500 font-semibold">PropID Master</p>
                <h1 class="text-3xl font-bold text-gray-900">File Number &amp; Prop ID Dashboard</h1>
                <p class="text-gray-600">
                    Review every Prop ID in the master registry, see the file-number variants we know about,
                    and track how each identifier connects to PRA, PIC, File History, and CofO staging.
                </p>
            </header>

            <section class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                <article class="bg-white border rounded-2xl shadow-sm p-5">
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Master Rows</p>
                    <p class="mt-2 text-3xl font-bold text-gray-900">{{ number_format($metrics['total_master']) }}</p>
                    <p class="text-sm text-gray-500 mt-1">Total entries in PropID_Master</p>
                </article>

                <article class="bg-white border rounded-2xl shadow-sm p-5">
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Official File Numbers</p>
                    <p class="mt-2 text-3xl font-bold text-emerald-600">{{ number_format($metrics['with_official_file']) }}</p>
                    <p class="text-sm text-gray-500 mt-1">Rows with MLPP/KANGIS/ST file numbers</p>
                </article>

                <article class="bg-white border rounded-2xl shadow-sm p-5">
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Temp-Only Records</p>
                    <p class="mt-2 text-3xl font-bold text-amber-500">{{ number_format($metrics['temp_only']) }}</p>
                    <p class="text-sm text-gray-500 mt-1">Waiting for official file linkage</p>
                </article>

                <article class="bg-white border rounded-2xl shadow-sm p-5">
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Conflict Watch</p>
                    <p class="mt-2 text-3xl font-bold text-rose-600">{{ number_format($metrics['conflicts']) }}</p>
                    <p class="text-sm text-gray-500 mt-1">File numbers mapped to multiple prop IDs</p>
                </article>
            </section>

            <section class="bg-white border rounded-2xl shadow-sm">
                <form method="GET" action="{{ route('propid-master.index') }}"
                      class="p-5 border-b flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                    <div class="flex flex-1 gap-3">
                        <div class="flex-1">
                            <label for="propid-search" class="sr-only">Search</label>
                            <input id="propid-search"
                                   type="text"
                                   name="search"
                                   value="{{ $search }}"
                                   placeholder="Search prop id, file number, or source table"
                                   class="w-full rounded-xl border-gray-300 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 text-sm px-4 py-2.5">
                        </div>
                        <div>
                            <label for="propid-per-page" class="sr-only">Per page</label>
                            <select id="propid-per-page"
                                    name="per_page"
                                    class="rounded-xl border-gray-300 text-sm px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                                @foreach ([25, 50, 75, 100] as $size)
                                    <option value="{{ $size }}" @selected($perPage == $size)>{{ $size }} / page</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="flex items-center gap-3">
                        @if ($search !== '')
                            <a href="{{ route('propid-master.index') }}"
                               class="text-sm text-gray-600 hover:text-gray-900 font-medium">Reset</a>
                        @endif
                        <button type="submit"
                                class="inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 text-white text-sm font-semibold rounded-xl shadow hover:bg-indigo-500">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24"
                                 stroke="currentColor" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                      d="M21 21l-4.35-4.35M11 19a8 8 0 100-16 8 8 0 000 16z"/>
                            </svg>
                            Search
                        </button>
                    </div>
                </form>

                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm text-left">
                        <thead class="bg-gray-50 text-gray-500 uppercase text-xs tracking-wide">
                        <tr>
                            <th class="px-5 py-3 font-semibold">Prop ID</th>
                            <th class="px-5 py-3 font-semibold">File Numbers</th>
                            <th class="px-5 py-3 font-semibold">Linked Tables</th>
                            <th class="px-5 py-3 font-semibold">Source</th>
                            <th class="px-5 py-3 font-semibold">Last Updated</th>
                        </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                        @forelse ($records as $record)
                            @php
                                $fileNumbers = array_filter([
                                    'Primary' => $record->primary_file_number,
                                    'MLS' => $record->mlsFNo,
                                    'KANGIS' => $record->kangisFileNo,
                                    'New KANGIS' => $record->NewKANGISFileno,
                                    'Temp' => $record->temp_fileno,
                                ]);

                                $linkMap = [
                                    'File History' => (int) $record->file_history_links,
                                    'PRA' => (int) $record->pra_links,
                                    'PIC' => (int) $record->pic_links,
                                    'CofO' => (int) $record->cofo_links,
                                ];
                            @endphp
                            <tr class="hover:bg-gray-50/70">
                                <td class="px-5 py-4 align-top">
                                    <div class="text-lg font-semibold text-gray-900">#{{ $record->prop_id }}</div>
                                    <p class="text-xs uppercase tracking-wide text-gray-500">Master ID {{ $record->id }}</p>
                                </td>
                                <td class="px-5 py-4 align-top">
                                    @if (empty($fileNumbers))
                                        <span class="inline-flex text-xs text-gray-500">No file numbers yet</span>
                                    @else
                                        <div class="flex flex-wrap gap-2">
                                            @foreach ($fileNumbers as $label => $value)
                                                <span class="inline-flex flex-col rounded-xl border border-gray-200 px-3 py-1.5">
                                                    <span class="text-[0.65rem] uppercase tracking-wide text-gray-500 font-semibold">{{ $label }}</span>
                                                    <span class="text-sm font-semibold text-gray-900">{{ $value }}</span>
                                                </span>
                                            @endforeach
                                        </div>
                                    @endif
                                </td>
                                <td class="px-5 py-4 align-top">
                                    <div class="flex flex-wrap gap-2">
                                        @foreach ($linkMap as $label => $count)
                                            <span class="inline-flex items-center gap-1 rounded-full px-3 py-1 text-xs font-semibold
                                                {{ $count > 0 ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-gray-100 text-gray-500 border border-gray-200' }}">
                                                <span>{{ $label }}</span>
                                                <span class="text-[0.7rem] font-bold">{{ $count }}</span>
                                            </span>
                                        @endforeach
                                    </div>
                                </td>
                                <td class="px-5 py-4 align-top">
                                    <div class="text-sm font-semibold text-gray-900">{{ $record->source_table ?? 'unknown' }}</div>
                                    <p class="text-xs text-gray-500">Status: <span class="font-semibold">{{ $record->status ?? 'active' }}</span></p>
                                </td>
                                <td class="px-5 py-4 align-top">
                                    <div class="text-sm text-gray-900">
                                        {{ $record->updated_at ? \Carbon\Carbon::parse($record->updated_at)->format('d M Y, h:i a') : '—' }}
                                    </div>
                                    <p class="text-xs text-gray-500">
                                        Created {{ $record->created_at ? \Carbon\Carbon::parse($record->created_at)->format('d M Y, h:i a') : '—' }}
                                    </p>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-5 py-8 text-center text-gray-500">
                                    No Prop ID records found for this filter.
                                </td>
                            </tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="px-5 py-4 border-t">
                    {{ $records->onEachSide(1)->links() }}
                </div>
            </section>
        </div>

        @include('admin.footer')
    </div>
@endsection
