@extends('online_legal_search.layout')

@section('title', 'Dashboard')

@section('body')
<div class="min-h-screen flex flex-col bg-slate-100 dark:bg-gray-900">
    @include('online_legal_search.partials.header')

    <div class="flex-1 max-w-7xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-8">
        @if(session('status'))
            <div class="mb-6 rounded-lg border border-green-200 dark:border-green-700 bg-green-50 dark:bg-green-900/30 p-4">
                <p class="text-sm text-green-700 dark:text-green-400">{{ session('status') }}</p>
            </div>
        @endif

        <div class="grid gap-6 lg:grid-cols-3">
            <!-- Start a new search -->
            <div class="lg:col-span-2">
                <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-8 shadow-sm text-center">
                    <div class="mx-auto mb-5 flex h-16 w-16 items-center justify-center rounded-2xl bg-cyan-100 dark:bg-cyan-900/30">
                        <i data-lucide="search" class="h-8 w-8 text-cyan-600 dark:text-cyan-400"></i>
                    </div>
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-2">Conduct a Legal Search</h2>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mb-6 max-w-md mx-auto">
                        Search official land records by file number, owner, plot or plan number. Preview results for free — unlock the full report for ₦10,000.
                    </p>
                    <a href="{{ route('ols.landing') }}" class="inline-flex items-center justify-center rounded-lg bg-cyan-600 px-6 py-3 font-semibold text-white shadow hover:bg-cyan-700">
                        <i data-lucide="search" class="inline h-4 w-4 mr-2"></i>
                        Start a New Search
                    </a>
                </div>
            </div>

            <!-- Sidebar -->
            <div class="space-y-6">
                <!-- Account Info -->
                <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-6 shadow-sm">
                    <h3 class="text-sm font-semibold text-gray-900 dark:text-white mb-3">Account</h3>
                    <div class="space-y-2 text-sm">
                        <div class="flex justify-between">
                            <span class="text-gray-500 dark:text-gray-400">Name</span>
                            <span class="text-gray-900 dark:text-white font-medium">{{ $user->name }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-500 dark:text-gray-400">Email</span>
                            <span class="text-gray-900 dark:text-white font-medium">{{ $user->email }}</span>
                        </div>
                        @if($user->organization)
                        <div class="flex justify-between">
                            <span class="text-gray-500 dark:text-gray-400">Organization</span>
                            <span class="text-gray-900 dark:text-white font-medium">{{ $user->organization }}</span>
                        </div>
                        @endif
                    </div>
                </div>

                <!-- Recent Searches -->
                <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-6 shadow-sm">
                    <h3 class="text-sm font-semibold text-gray-900 dark:text-white mb-3">Recent Searches</h3>
                    @if($recentSearches->isEmpty())
                        <p class="text-sm text-gray-500 dark:text-gray-400">No searches yet.</p>
                    @else
                        <div class="space-y-2">
                            @foreach($recentSearches as $search)
                                <div class="flex items-center justify-between text-sm">
                                    <span class="text-gray-900 dark:text-white font-medium">{{ $search->file_number ?? 'N/A' }}</span>
                                    <span class="text-gray-500 dark:text-gray-400">{{ \Carbon\Carbon::parse($search->paid_at)->diffForHumans() }}</span>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    @include('online_legal_search.partials.footer')
</div>

@endsection
