@extends('layouts.app')

@section('page-title')
    Grouping Analytics Dashboard
@endsection

 
 

@section('content')
    <link rel="stylesheet" href="{{ asset('css/grouping-analytics/dashboard.css') }}">

    <script src="{{ asset('js/grouping-analytics/dashboard.js') }}"  ></script>
    <script>
        window.__GROUPING_FILTERS__ = @json($filters ?? []);
        window.__GROUPING_HAS_FILTERS__ = @json($hasActiveFilters ?? false);
        window.__GROUPING_PREVIEW_TOTAL__ = @json($previewTotal ?? null);
    </script>

    <div class="flex-1 overflow-auto">
        @include($headerPartial ?? 'admin.header')

        <div class="p-6">
            <div class="grouping-analytics space-y-6" x-data="groupingAnalyticsDashboard()" x-init="window.groupingDashboard = $data; init()">
                <div class="page-header">
                <div>
                    <h1 class="page-title">Grouping Analytics</h1>
                    <p class="page-subtitle">Live overview of matching performance and activity in the grouping module.</p>
                </div>
                <div class="refresh-controls">
                    <button type="button" class="refresh-button" @click="refreshStats({ includePreview: hasLoadedPreview })" :disabled="isRefreshing">
                        <span class="icon" :class="{ 'animate-spin': isRefreshing }">
                            <i class="fas fa-sync-alt"></i>
                        </span>
                        <span class="label" x-text="isRefreshing ? 'Refreshing…' : 'Refresh data'"></span>
                    </button>
                    <span class="timestamp" x-show="lastUpdated">
                        Updated <span x-text="lastUpdated"></span>
                    </span>
                </div>
            </div>

                @include('grouping.analytics.partials.analytics-section.summary', [
                    'analytics' => $analytics,
                    'landUseStats' => $landUseStats,
                    'groupStatus' => $groupStatus,
                    'recentActivity' => $recentActivity,
                ])

                @include('grouping.analytics.partials.data-table.preview', [
                    'tablePreview' => $tablePreview,
                ])
            </div>
        </div>
        @include($footerPartial ?? 'admin.footer')
    </div>
@endsection
