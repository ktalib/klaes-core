@extends('layouts.app')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/file-history.css') }}">
    <style>
        .source-tag {
            display: inline-flex;
            align-items: center;
            padding: 0.15rem 0.5rem;
            border-radius: 9999px;
            font-size: 0.6rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            white-space: nowrap;
            border: 1px solid transparent;
        }

        .source-tag-file_history_staging {
            background: #dbeafe;
            color: #1e40af;
            border-color: #bfdbfe;
        }

        .source-tag-CofO_staging {
            background: #d1fae5;
            color: #065f46;
            border-color: #a7f3d0;
        }

        .source-tag-pra {
            background: #fef3c7;
            color: #92400e;
            border-color: #fde68a;
        }

        .source-tag-deed_registrations {
            background: #ede9fe;
            color: #5b21b6;
            border-color: #ddd6fe;
        }
    </style>
@endpush

@section('page-title', $PageTitle ?? 'Property Transaction Timeline')

@section('content')
    <div class="flex-1 overflow-auto">
        @include('admin.header')

        <div class="file-history-page max-w-6xl mx-auto px-6 py-10 space-y-8">

            <header class="space-y-2">
                <div class="flex items-center gap-3">
                    <a href="{{ route('property-search.index') }}"
                       class="inline-flex items-center gap-1 text-sm text-blue-600 hover:text-blue-800 transition-colors">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="15 18 9 12 15 6"></polyline>
                        </svg>
                        Back to Property Records
                    </a>
                </div>
                <p class="text-sm uppercase tracking-widest text-blue-500 font-semibold">Property Search</p>
                <h1 class="text-3xl font-bold text-gray-900">{{ $PageTitle ?? 'Property Transaction Timeline' }}</h1>
                <p class="text-gray-500">
                    All transactions for
                    <span class="font-mono text-gray-800" id="fileNumberLabel"></span>
                    across File History, CofO, PRA, and Deed Registrations — sorted chronologically.
                </p>
            </header>

            <div class="space-y-6" id="property-timeline-root">

                {{-- Summary card --}}
                <div class="card gradient-card">
                    <div class="card-content">
                        <div class="header-grid" style="display:grid; grid-template-columns: repeat(4, 1fr); gap: 1.5rem;">
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
                            <div>
                                <p class="text-sm text-gray-600">Location</p>
                                <p class="text-lg font-semibold text-gray-900" id="locationDisplay">—</p>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Tabs --}}
                <div class="tabs card">
                    <div class="card-content space-y-6">
                        <div class="tabs-list">
                            <button class="tabs-trigger active" data-tab="timeline">Timeline View</button>
                            <button class="tabs-trigger" data-tab="details">Transaction Details</button>
                            <button class="tabs-trigger" data-tab="summary">Summary</button>
                        </div>

                        <div class="tabs-content">

                            {{-- Timeline tab --}}
                            <div id="timeline-tab" class="tab-panel active">
                                <div class="timeline-container">
                                    <div class="timeline-line"></div>
                                    <div class="space-y-8 relative z-10" id="timeline-items"></div>
                                    <div class="empty-state" id="timeline-empty" hidden>
                                        <p>No timeline data available.</p>
                                    </div>
                                </div>
                            </div>

                            {{-- Details tab --}}
                            <div id="details-tab" class="tab-panel">
                                <div class="grid gap-4" id="transaction-details"></div>
                                <div class="empty-state" id="details-empty" hidden>
                                    <p>No transaction details to show.</p>
                                </div>
                            </div>

                            {{-- Summary tab --}}
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

            </div>
        </div>

        @include('admin.footer')
    </div>
@endsection

@push('scripts')
    <script>
        window.__PROPERTY_TIMELINE__ = @json($historyPayload);
    </script>
    <script src="{{ asset('js/property-search-timeline.js') }}" defer></script>
@endpush
