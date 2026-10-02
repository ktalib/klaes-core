@extends('online_legal_search.layout')

@section('title', 'Search History')

@section('body')
<div class="min-h-screen flex flex-col bg-slate-100 dark:bg-gray-900">
    @include('online_legal_search.partials.header')

    <div class="flex-1 w-full max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-6 shadow-sm">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">
                <i data-lucide="history" class="inline h-5 w-5 mr-2 text-cyan-600"></i>
                Search History
            </h2>

            @if($searches->isEmpty())
                <p class="text-sm text-gray-500 dark:text-gray-400">You haven't paid for any searches yet.</p>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-200 dark:border-gray-700">
                                <th class="text-left py-3 px-2 text-gray-500 dark:text-gray-400 font-medium">File Number</th>
                                <th class="text-left py-3 px-2 text-gray-500 dark:text-gray-400 font-medium">Amount</th>
                                <th class="text-left py-3 px-2 text-gray-500 dark:text-gray-400 font-medium">Date Paid</th>
                                <th class="text-right py-3 px-2 text-gray-500 dark:text-gray-400 font-medium">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($searches as $search)
                                <tr class="border-b border-gray-100 dark:border-gray-700/50">
                                    <td class="py-3 px-2 text-gray-900 dark:text-white font-medium">{{ $search->file_number ?: 'N/A' }}</td>
                                    <td class="py-3 px-2 text-gray-600 dark:text-gray-400">&#8358;{{ number_format(($search->amount ?? 0) / 100, 2) }}</td>
                                    <td class="py-3 px-2 text-gray-500 dark:text-gray-400">{{ $search->paid_at ? \Carbon\Carbon::parse($search->paid_at)->format('M d, Y H:i') : '—' }}</td>
                                    <td class="py-3 px-2 text-right">
                                        @if($search->file_number)
                                            <a href="{{ route('ols.result', ['query' => $search->file_number]) }}"
                                               class="inline-flex items-center gap-1 text-cyan-600 dark:text-cyan-400 hover:text-cyan-800 font-medium">
                                                <i data-lucide="file-text" class="h-4 w-4"></i> View Report
                                            </a>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="mt-4">
                    {{ $searches->links() }}
                </div>
            @endif
        </div>
    </div>

    @include('online_legal_search.partials.footer')
</div>
@endsection
