@extends('layouts.app')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/file-history.css') }}">
@endpush

@section('page-title', $PageTitle ?? 'File History & Transaction Timeline')

@section('content')
    <div class="flex-1 overflow-auto">
        @include('admin.header')

        <div class="file-history-page max-w-6xl mx-auto px-6 py-10 space-y-8">
            <header class="space-y-2">
                <p class="text-sm uppercase tracking-widest text-blue-500 font-semibold">File History</p>
                <h1 class="text-3xl font-bold text-gray-900">{{ $PageTitle ?? 'File History & Transaction Timeline' }}</h1>
                <p class="text-gray-500">
                    Trace every recorded transaction associated with
                    <span class="font-mono text-gray-800" id="fileNumberLabel"></span>.
                </p>
            </header>

            <div class="space-y-6" id="file-history-root">
                <div class="card gradient-card">
                    <div class="card-content">
                        <div class="header-grid">
                            <div>
                                <p class="text-sm text-gray-600">File Number</p>
                                <p class="text-2xl font-semibold text-gray-900" id="fileNumberDisplay">—</p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-600">Total Transactions</p>
                                <p class="text-2xl font-semibold text-gray-900" id="totalTransactions">0</p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-600">Land Use</p>
                                <p class="text-2xl font-semibold text-gray-900" id="landUse">—</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="tabs card">
                    <div class="card-content space-y-6">
                        <div class="tabs-list">
                            <button class="tabs-trigger active" data-tab="timeline">Timeline View</button>
                            <button class="tabs-trigger" data-tab="details">Transaction Details</button>
                            <button class="tabs-trigger" data-tab="summary">Summary</button>
                        </div>

                        <div class="tabs-content">
                            <div id="timeline-tab" class="tab-panel active">
                                <div class="timeline-container">
                                    <div class="timeline-line"></div>
                                    <div class="space-y-8 relative z-10" id="timeline-items"></div>
                                    <div class="empty-state" id="timeline-empty" hidden>
                                        <p>No timeline data available.</p>
                                    </div>
                                </div>
                            </div>

                            <div id="details-tab" class="tab-panel">
                                <div class="grid gap-4" id="transaction-details"></div>
                                <div class="empty-state" id="details-empty" hidden>
                                    <p>No transaction details to show.</p>
                                </div>
                            </div>

                            <div id="summary-tab" class="tab-panel">
                                <div class="card green-gradient-card">
                                    <div class="card-header">
                                        <h3 class="card-title">Summary</h3>
                                    </div>
                                    <div class="card-content space-y-4">
                                        <div class="grid grid-cols-2 md:grid-cols-3 gap-4" id="summary-grid"></div>
                                        <div class="border-t pt-4">
                                            <p class="text-sm font-semibold mb-3 text-gray-900">All Transactions</p>
                                            <ul class="space-y-2" id="transactions-list"></ul>
                                        </div>
                                    </div>
                                </div>
                                <div class="empty-state" id="summary-empty" hidden>
                                    <p>No summary information is available.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="flex gap-3 justify-end border-t pt-6">
                    <button class="button button-outline" type="button">
                        <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                            <polyline points="7 10 12 15 17 10"></polyline>
                            <line x1="12" y1="15" x2="12" y2="3"></line>
                        </svg>
                        Download Report
                    </button>
                    <button class="button button-outline" type="button">
                        <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M4 12v8a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-8"></path>
                            <polyline points="16 6 12 2 8 6"></polyline>
                            <line x1="12" y1="2" x2="12" y2="15"></line>
                        </svg>
                        Share History
                    </button>
                </div>
            </div>
        </div>

        @include('admin.footer')
    </div>
@endsection

@push('scripts')
    <script>
        window.__FILE_HISTORY__ = @json($historyPayload);
    </script>
    <script src="{{ asset('js/file-history.js') }}" defer></script>
@endpush
