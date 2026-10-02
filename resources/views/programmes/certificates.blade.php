@extends('layouts.app')

@section('page-title')
    {{ $PageTitle ?? __('KLAES') }}
@endsection

@section('content')
<style>
    /* Enhanced Badge Styles */
    .badge {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        padding: 0.375rem 0.75rem;
        border-radius: 0.5rem;
        font-size: 0.75rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.025em;
        transition: all 0.2s ease-in-out;
    }
    
    .badge-approved {
        background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%);
        color: #047857;
        border: 1px solid #10b981;
    }
    
    .badge-pending {
        background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
        color: #b45309;
        border: 1px solid #f59e0b;
    }
    
    .badge-declined {
        background: linear-gradient(135deg, #fee2e2 0%, #fecaca 100%);
        color: #b91c1c;
        border: 1px solid #ef4444;
    }
    
    .badge-issued {
        background: linear-gradient(135deg, #dbeafe 0%, #bfdbfe 100%);
        color: #1d4ed8;
        border: 1px solid #3b82f6;
    }
    
    .badge-blocked {
        background: linear-gradient(135deg, #fecaca 0%, #fca5a5 100%);
        color: #991b1b;
        border: 1px solid #dc2626;
    }

    /* Enhanced Table Styles */
    .table-header {
        background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
        font-weight: 600;
        color: #1e40af;
        text-align: left;
        padding: 1rem;
        border-bottom: 2px solid #e2e8f0;
        font-size: 0.875rem;
        text-transform: uppercase;
        letter-spacing: 0.025em;
        position: sticky;
        top: 0;
        z-index: 10;
    }
    
    .table-cell {
        padding: 1rem;
        border-bottom: 1px solid #f1f5f9;
        position: relative;
        vertical-align: middle;
        transition: background-color 0.2s ease-in-out;
    }
    
    .table-row:hover .table-cell {
        background-color: #f8fafc;
    }

    /* Simple dropdown */
    .dd {
        position: relative;
        display: inline-block;
    }

    .dd-btn {
        border: 1px solid #d1d5db;
        background: #ffffff;
        color: #374151;
        border-radius: 8px;
        padding: 6px;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: background-color 0.2s ease;
    }

    .dd-btn:hover {
        background: #f3f4f6;
    }

    .dd-menu {
        display: none;
        position: fixed;
        min-width: 190px;
        background: #ffffff;
        border: 1px solid #e5e7eb;
        border-radius: 10px;
        box-shadow: 0 10px 20px rgba(0, 0, 0, 0.08);
        z-index: 9999;
        overflow: hidden;
    }

    .dd-menu.show {
        display: block;
    }

    .dd-item {
        display: flex;
        align-items: center;
        gap: 8px;
        width: 100%;
        text-align: left;
        padding: 10px 14px;
        background: #ffffff;
        border: none;
        color: #374151;
        font-size: 0.875rem;
        text-decoration: none;
        cursor: pointer;
        transition: background-color 0.2s ease;
    }

    .dd-item i {
        margin-right: 0;
    }

    .dd-item:hover {
        background: #f3f4f6;
    }

    .dd-item.disabled {
        color: #9ca3af;
        cursor: default;
        pointer-events: none;
    }

    /* Enhanced Stats Cards */
    .stats-card {
        background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
        border: 1px solid #e2e8f0;
        border-radius: 1rem;
        padding: 1.5rem;
        transition: all 0.3s ease-in-out;
        position: relative;
        overflow: hidden;
    }

    .stats-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 4px;
        background: linear-gradient(90deg, #3b82f6, #1d4ed8);
        transform: scaleX(0);
        transition: transform 0.3s ease-in-out;
    }

    .stats-card:hover::before {
        transform: scaleX(1);
    }

    .stats-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
    }

    /* Enhanced Tab Styles */
    .controls {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        align-items: center;
        justify-content: space-between;
    }

    .controls-main {
        padding: 12px 16px;
        background: #f3f4f6;
        border-radius: 12px;
        border: 1px solid #e5e7eb;
    }

    .controls-sub {
        padding: 10px 14px;
        background: #f9fafb;
        border-radius: 10px;
        border: 1px solid #e5e7eb;
    }

    .tabs {
        display: flex;
        gap: 10px;
        position: relative;
    }

    .tab-btn {
        position: relative;
        padding: 10px 16px;
        border: none;
        background: #fff;
        color: #374151;
        border-radius: 9999px;
        cursor: pointer;
        font-weight: 600;
        box-shadow: inset 0 0 0 1px #d1d5db;
        transition: all 0.2s ease;
        font-size: 0.85rem;
    }

    .tab-btn:hover {
        box-shadow: inset 0 0 0 1px #2563eb;
        color: #1d4ed8;
    }

    .tab-btn.active {
        background: linear-gradient(135deg, #2563eb, #1d4ed8);
        color: #fff;
        box-shadow: none;
    }

    .tab-btn.active::after {
        content: '';
        position: absolute;
        left: 50%;
        bottom: -8px;
        transform: translateX(-50%);
        width: 36px;
        height: 3px;
        background: #1d4ed8;
        border-radius: 9999px;
    }

    /* Responsive Table */
    .table-container {
        overflow-x: auto;
        border-radius: 0.75rem;
        border: 1px solid #e2e8f0;
    }

    @media (max-width: 768px) {
        .table-cell {
            padding: 0.75rem 0.5rem;
            font-size: 0.875rem;
        }
        
        .dd-menu {
            min-width: 180px;
            right: -50px;
        }
        
        .stats-card {
            padding: 1rem;
        }
    }

    /* Loading Animation */
    .loading-shimmer {
        background: linear-gradient(90deg, #f1f5f9 25%, #e2e8f0 50%, #f1f5f9 75%);
        background-size: 200% 100%;
        animation: shimmer 2s infinite;
    }

    @keyframes shimmer {
        0% { background-position: -200% 0; }
        100% { background-position: 200% 0; }
    }

    /* Enhanced Info Box */
    .info-box {
        background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%);
        border-left: 4px solid #3b82f6;
        border-radius: 0.5rem;
        padding: 1.25rem;
        margin-bottom: 1.5rem;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
    }

    /* Ensure horizontal scroll so all columns (incl. Actions) are reachable */
    .tab-content {
        overflow-x: auto !important;
    }
    .tab-content table {
        width: max-content;   /* expand to fit columns */
        min-width: 100%;      /* at least full container width */
    }
    .tab-content th,
    .tab-content td {
        white-space: nowrap;  /* prevent wrapping so width can overflow and scroll */
    }
    /* Give Actions column some breathing room */
    .tab-content th:last-child,
    .tab-content td:last-child {
        min-width: 140px;
    }
</style>

<div class="flex-1 overflow-auto">
    <!-- Header -->
    @include($headerPartial ?? 'admin.header')
    
    <!-- Main Content -->
    <div class="p-6">
        <div class="bg-white rounded-md shadow-sm p-6">
            <h2 class="text-xl font-bold mb-6">ST Certificate of Occupancy Management</h2>
            
            <div class="bg-blue-50 border-l-4 border-blue-500 p-4 mb-6">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <i data-lucide="info" class="w-5 h-5 text-blue-500"></i>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm text-blue-700">
                            This dashboard shows all approved applications that are eligible for ST Certificate of Occupancy issuance.
                        </p>
                    </div>
                </div>
            </div>

            {{-- The workflow pipeline. This screen is stage 3 of six, and it is highlighted
                 as such: an officer here should be able to see what comes before and after
                 without opening the CofO screen. Same partial, same counts, both screens. --}}
            @include('cofo_workflow.partials.styles')
            <div class="border border-gray-200 rounded-lg mb-6 overflow-hidden">
                @include('cofo_workflow.partials.pipeline', [
                    'stages' => $stages ?? [],
                    'stageCounts' => $stageCounts ?? [],
                    'current' => $currentStage ?? 'front_page',
                ])
                <div class="px-4 pb-3 -mt-1 text-xs text-gray-500">
                    The front page is produced here. The Title Deed Plan comes from KANGIS/GIS and is paired with it on
                    <a href="{{ route('programmes.cofo_complete') }}" class="text-blue-600 hover:underline">CofO</a>.
                </div>
            </div>

            <!-- Stats Cards -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
                <div class="bg-white rounded-lg shadow p-6 border border-gray-200">
                    <div class="flex justify-between items-center">
                        <h3 class="text-gray-500 text-sm font-medium">PuA Primaries (Total)</h3>
                        <span class="text-blue-500 bg-blue-100 p-2 rounded-full">
                            <i data-lucide="file-text" class="w-5 h-5"></i>
                        </span>
                    </div>
                    <p class="text-3xl font-bold text-gray-800 mt-2">{{ $puaPrimaryStats['total'] }}</p>
                </div>

                <div class="bg-white rounded-lg shadow p-6 border border-gray-200">
                    <div class="flex justify-between items-center">
                        <h3 class="text-gray-500 text-sm font-medium">Ready For CofO Batch</h3>
                        <span class="text-green-500 bg-green-100 p-2 rounded-full">
                            <i data-lucide="check-circle" class="w-5 h-5"></i>
                        </span>
                    </div>
                    <p class="text-3xl font-bold text-gray-800 mt-2">{{ $puaPrimaryStats['ready'] }}</p>
                </div>

                <div class="bg-white rounded-lg shadow p-6 border border-gray-200">
                    <div class="flex justify-between items-center">
                        <h3 class="text-gray-500 text-sm font-medium">CofO Completed</h3>
                        <span class="text-sky-500 bg-sky-100 p-2 rounded-full">
                            <i data-lucide="badge-check" class="w-5 h-5"></i>
                        </span>
                    </div>
                    <p class="text-3xl font-bold text-gray-800 mt-2">{{ $puaPrimaryStats['completed'] }}</p>
                </div>

                <div class="bg-white rounded-lg shadow p-6 border border-gray-200">
                    <div class="flex justify-between items-center">
                        <h3 class="text-gray-500 text-sm font-medium">SUA Units (Preview)</h3>
                        <span class="text-purple-500 bg-purple-100 p-2 rounded-full">
                            <i data-lucide="hourglass" class="w-5 h-5"></i>
                        </span>
                    </div>
                    <p class="text-3xl font-bold text-gray-800 mt-2">{{ $suaStats['total'] }}</p>
                    <p class="text-xs text-gray-500 mt-2">Automation support coming soon.</p>
                </div>
            </div>

            <!-- Applications Table -->
            <div class="bg-white rounded-md shadow-sm border border-gray-200 overflow-hidden">
                <div class="flex justify-between items-center p-6 border-b border-gray-200">
                    <h3 class="text-lg font-medium">Approved Applications Eligible for Certificate</h3>
                </div>

                <!-- Primary Tabs Navigation -->
                <div class="border-b border-gray-200 bg-slate-50 px-4 pt-4 pb-3">
                    <div class="controls controls-main">
                        <div class="tabs">
                            <button class="tab-btn active" data-tab-group="main" data-tab="pua">PuA</button>
                            <button class="tab-btn" data-tab-group="main" data-tab="sua">SUA</button>
                        </div>
                    </div>
                </div>

                <!-- PuA Content -->
                <div class="pua-tab-panel" data-tab-group-content="main" data-tab-key="pua">
                    <div class="border-b border-cyan-100 bg-cyan-50/60 px-4 pt-4 pb-3">
                        <div class="controls controls-sub">
                            <div class="tabs">
                                <button id="tab-not-generated" class="tab-btn active" data-tab-group="pua" data-tab="not-generated">Not Generated</button>
                                <button id="tab-generated" class="tab-btn" data-tab-group="pua" data-tab="generated">Generated</button>
                            </div>
                        </div>
                    </div>

                    <!-- Not Generated Certificates Table -->
                    <div id="content-not-generated" class="tab-content overflow-x-auto" data-tab-group-content="pua" data-tab-key="not-generated">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead>
                                <tr class="text-xs">
                                    <th class="table-header">Primary File No</th>
                                    <th class="table-header">NP File No</th>
                                    <th class="table-header">Owner</th>
                                    <th class="table-header">Total Units</th>
                                    <th class="table-header">ST Memos</th>
                                    <th class="table-header">RoFO Details</th>
                                    <th class="table-header">RoFO Generated</th>
                                    <th class="table-header">CofO Generated</th>
                                    <th class="table-header">Status</th>
                                    <th class="table-header">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @php $notGeneratedCount = 0; @endphp
                                @foreach($puaPrimaryApplications as $primary)
                                    @php
                                        $summary = $primary->pua_summary;
                                        $totalUnits = (int) ($summary->total_units ?? 0);
                                        $memoGenerated = (int) ($summary->memo_generated ?? 0);
                                        $detailsCaptured = (int) ($summary->details_captured ?? 0);
                                        $rofoGenerated = (int) ($summary->rofo_generated ?? 0);
                                        $cofoGenerated = (int) ($summary->cofo_generated ?? 0);
                                        $pendingUnits = max(0, $totalUnits - $cofoGenerated);
                                        $prerequisitesMet = (bool) ($summary->prerequisites_met ?? false);
                                        $canBatch = $prerequisitesMet && $pendingUnits > 0;
                                        $hasUnits = (bool) ($summary->has_units ?? false);
                                        $rofoDetailsComplete = (bool) ($summary->rofo_details_complete ?? false);
                                        $cofoDetailsEntered = (bool) ($summary->cofo_details_entered ?? false);
                                        $rofoDisabled = ! $hasUnits || $rofoDetailsComplete;
                                        $cofoDisabled = ! $hasUnits || $cofoDetailsEntered;
                                        $statusLabel = '';
                                        $statusClass = '';
                                        if ($pendingUnits === 0 && $cofoGenerated > 0) {
                                            $statusLabel = 'Completed';
                                            $statusClass = 'badge-issued';
                                        } elseif ($prerequisitesMet) {
                                            $statusLabel = 'Ready for Batch';
                                            $statusClass = 'badge-approved';
                                        } else {
                                            $statusLabel = 'Prerequisites Pending';
                                            $statusClass = 'badge-blocked';
                                        }
                                        $batchDisableReason = '';
                                        if (! $prerequisitesMet) {
                                            $batchDisableReason = 'Complete ST Memo uploads and RoFO details for every PuA unit.';
                                        } elseif ($pendingUnits === 0) {
                                            $batchDisableReason = 'All CofO certificates are already generated.';
                                        }
                                        $rofoDisableReason = '';
                                        if (! $hasUnits) {
                                            $rofoDisableReason = 'Create PuA unit applications before capturing RoFO details.';
                                        } elseif ($rofoDetailsComplete) {
                                            $rofoDisableReason = 'RoFO details already captured for all PuA units.';
                                        }
                                        $cofoDetailsDisableReason = '';
                                        if (! $hasUnits) {
                                            $cofoDetailsDisableReason = 'Create PuA unit applications before capturing CofO details.';
                                        } elseif ($cofoDetailsEntered) {
                                            $cofoDetailsDisableReason = 'CofO details already captured for this primary.';
                                        }
                                        $rofoButtonClasses = 'dd-item' . ($rofoDisabled ? ' disabled' : '');
                                        $cofoButtonClasses = 'dd-item' . ($cofoDisabled ? ' disabled' : '');
                                        $rofoIconClass = $rofoDisabled ? 'text-gray-400' : 'text-indigo-600';
                                        $cofoIconClass = $cofoDisabled ? 'text-gray-400' : 'text-emerald-600';
                                        $rofoOnclick = $rofoDisabled
                                            ? 'return false;'
                                            : 'openRofoDetailsModal(' . $primary->id . ', ' . json_encode($primary->fileno ?? '') . ', ' . json_encode($primary->owner_name ?? '') . ')';
                                        $cofoOnclick = $cofoDisabled
                                            ? 'return false;'
                                            : 'openCofoDetailsModal(' . $primary->id . ', ' . json_encode($primary->fileno ?? '') . ', ' . json_encode($primary->owner_name ?? '') . ')';
                                    @endphp
                                    @if($pendingUnits > 0)
                                        @php $notGeneratedCount++; @endphp
                                        <tr class="text-sm text-gray-700">
                                            <td class="table-cell">{{ $primary->fileno ?? 'N/A' }}</td>
                                            <td class="table-cell">{{ $primary->np_fileno ?? 'N/A' }}</td>
                                            <td class="table-cell">{{ $primary->owner_name ?? 'N/A' }}</td>
                                            <td class="table-cell">{{ $totalUnits }}</td>
                                            <td class="table-cell">
                                                @if($summary->primary_memo_generated ?? false)
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800">
                                                        Primary Memo
                                                    </span>
                                                @else
                                                    {{ $memoGenerated }} / {{ $totalUnits }}
                                                @endif
                                            </td>
                                            <td class="table-cell">{{ $detailsCaptured }} / {{ $totalUnits }}</td>
                                            <td class="table-cell">{{ $rofoGenerated }} / {{ $totalUnits }}</td>
                                            <td class="table-cell">{{ $cofoGenerated }} / {{ $totalUnits }}</td>
                                            <td class="table-cell">
                                                <span class="badge {{ $statusClass }}">{{ $statusLabel }}</span>
                                            </td>
                                            <td class="table-cell">
                                                <div class="dd">
                                                    <button type="button" class="dd-btn" onclick="toggleDD(this)">
                                                        <i data-lucide="more-horizontal" class="w-5 h-5"></i>
                                                    </button>
                                                    <div class="dd-menu">
                                                        <button type="button"
                                                            class="{{ $rofoButtonClasses }}"
                                                            title="{{ $rofoDisableReason ?: 'Capture or update RoFO details for PuA units.' }}"
                                                            onclick="{{ $rofoOnclick }}"
                                                            @if($rofoDisabled) disabled @endif>
                                                            <i data-lucide="file-badge" class="w-4 h-4 {{ $rofoIconClass }}"></i>
                                                            <span>RoFO Details</span>
                                                        </button>
                                                        <button type="button"
                                                            class="{{ $cofoButtonClasses }}"
                                                            title="{{ $cofoDetailsDisableReason ?: 'Capture CofO readiness details for this primary.' }}"
                                                            onclick="{{ $cofoOnclick }}"
                                                            @if($cofoDisabled) disabled @endif>
                                                            <i data-lucide="file-signature" class="w-4 h-4 {{ $cofoIconClass }}"></i>
                                                            <span>CofO Details</span>
                                                        </button>
                                                        <button type="button"
                                                            class="dd-item customModal"
                                                            data-size="xl"
                                                            data-title="Primary Units"
                                                            data-url="{{ route('programmes.cofo.pua-units', $primary->id) }}">
                                                            <i data-lucide="layers" class="w-4 h-4 text-blue-600"></i>
                                                            <span>View PuA(s)</span>
                                                        </button>
                                                        @if($canBatch)
                                                            <button type="button"
                                                                class="dd-item"
                                                                data-batch-url="{{ route('sectionaltitling.primary.cofo.batch', $primary->id) }}"
                                                                data-primary="{{ $primary->id }}"
                                                                data-total-units="{{ $totalUnits }}"
                                                                data-pending-units="{{ $pendingUnits }}"
                                                                onclick="handlePrimaryCofOBatch(this)">
                                                                Generate CofO Batch
                                                            </button>
                                                        @else
                                                            <span class="dd-item disabled" title="{{ $batchDisableReason }}">Generate CofO Batch</span>
                                                        @endif
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                    @endif
                                @endforeach
                                @if($notGeneratedCount === 0)
                                    <tr>
                                        <td colspan="10" class="table-cell text-center py-4">No primary applications pending CofO generation</td>
                                    </tr>
                                @endif
                            </tbody>
                        </table>
                    </div>

                <!-- Generated Certificates Table -->
                <div id="content-generated" class="tab-content hidden overflow-x-auto" data-tab-group-content="pua" data-tab-key="generated">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead>
                            <tr class="text-xs">
                                <th class="table-header">Primary File No</th>
                                <th class="table-header">NP File No</th>
                                <th class="table-header">Owner</th>
                                <th class="table-header">Total Units</th>
                                <th class="table-header">ST Memos</th>
                                <th class="table-header">RoFO Details</th>
                                <th class="table-header">RoFO Generated</th>
                                <th class="table-header">CofO Generated</th>
                                <th class="table-header">Status</th>
                                <th class="table-header">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @php $generatedCount = 0; @endphp
                            @foreach($puaPrimaryApplications as $primary)
                                @php
                                    $summary = $primary->pua_summary;
                                    $totalUnits = (int) ($summary->total_units ?? 0);
                                    $memoGenerated = (int) ($summary->memo_generated ?? 0);
                                    $detailsCaptured = (int) ($summary->details_captured ?? 0);
                                    $rofoGenerated = (int) ($summary->rofo_generated ?? 0);
                                    $cofoGenerated = (int) ($summary->cofo_generated ?? 0);
                                    $pendingUnits = max(0, $totalUnits - $cofoGenerated);
                                    $prerequisitesMet = (bool) ($summary->prerequisites_met ?? false);
                                    $hasUnits = (bool) ($summary->has_units ?? false);
                                    $rofoDetailsComplete = (bool) ($summary->rofo_details_complete ?? false);
                                    $cofoDetailsEntered = (bool) ($summary->cofo_details_entered ?? false);
                                    $rofoDisabled = ! $hasUnits || $rofoDetailsComplete;
                                    $cofoDisabled = ! $hasUnits || $cofoDetailsEntered;
                                    $statusLabel = $pendingUnits === 0 && $cofoGenerated > 0 ? 'Completed' : ($prerequisitesMet ? 'Ready for Batch' : 'Prerequisites Pending');
                                    $statusClass = $pendingUnits === 0 && $cofoGenerated > 0 ? 'badge-issued' : ($prerequisitesMet ? 'badge-approved' : 'badge-blocked');
                                    $batchDisableReason = $pendingUnits === 0 ? 'All CofO certificates are already generated.' : 'Complete ST Memo uploads and RoFO details for every PuA unit.';
                                    $rofoDisableReason = '';
                                    if (! $hasUnits) {
                                        $rofoDisableReason = 'Create PuA unit applications before capturing RoFO details.';
                                    } elseif ($rofoDetailsComplete) {
                                        $rofoDisableReason = 'RoFO details already captured for all PuA units.';
                                    }
                                    $cofoDetailsDisableReason = '';
                                    if (! $hasUnits) {
                                        $cofoDetailsDisableReason = 'Create PuA unit applications before capturing CofO details.';
                                    } elseif ($cofoDetailsEntered) {
                                        $cofoDetailsDisableReason = 'CofO details already captured for this primary.';
                                    }
                                    $rofoButtonClasses = 'dd-item' . ($rofoDisabled ? ' disabled' : '');
                                    $cofoButtonClasses = 'dd-item' . ($cofoDisabled ? ' disabled' : '');
                                    $rofoIconClass = $rofoDisabled ? 'text-gray-400' : 'text-indigo-600';
                                    $cofoIconClass = $cofoDisabled ? 'text-gray-400' : 'text-emerald-600';
                                    $rofoOnclick = $rofoDisabled
                                        ? 'return false;'
                                        : 'openRofoDetailsModal(' . $primary->id . ', ' . json_encode($primary->fileno ?? '') . ', ' . json_encode($primary->owner_name ?? '') . ')';
                                    $cofoOnclick = $cofoDisabled
                                        ? 'return false;'
                                        : 'openCofoDetailsModal(' . $primary->id . ', ' . json_encode($primary->fileno ?? '') . ', ' . json_encode($primary->owner_name ?? '') . ')';
                                @endphp
                                @if($pendingUnits === 0 && $cofoGenerated > 0)
                                    @php $generatedCount++; @endphp
                                    <tr class="text-sm text-gray-700">
                                        <td class="table-cell">{{ $primary->fileno ?? 'N/A' }}</td>
                                        <td class="table-cell">{{ $primary->np_fileno ?? 'N/A' }}</td>
                                        <td class="table-cell">{{ $primary->owner_name ?? 'N/A' }}</td>
                                        <td class="table-cell">{{ $totalUnits }}</td>
                                        <td class="table-cell">{{ $memoGenerated }} / {{ $totalUnits }}</td>
                                        <td class="table-cell">{{ $detailsCaptured }} / {{ $totalUnits }}</td>
                                        <td class="table-cell">{{ $rofoGenerated }} / {{ $totalUnits }}</td>
                                        <td class="table-cell">{{ $cofoGenerated }} / {{ $totalUnits }}</td>
                                        <td class="table-cell">
                                            <span class="badge {{ $statusClass }}">{{ $statusLabel }}</span>
                                        </td>
                                            <td class="table-cell">
                                                <div class="dd">
                                                    <button type="button" class="dd-btn" onclick="toggleDD(this)">
                                                        <i data-lucide="more-horizontal" class="w-5 h-5"></i>
                                                    </button>
                                                    <div class="dd-menu">
                                                        <button type="button" class="dd-item"
                                                            onclick="SmartPrintManager.open(
                                                                '{{ $primary->fileno }}',
                                                                'ST CofO',
                                                                '{{ route('programmes.print_cofo_batch', $primary->id) }}'
                                                            )">
                                                            <i data-lucide="printer" class="w-4 h-4 text-purple-600"></i>
                                                            <span>Print Manager</span>
                                                        </button>
                                                        <button type="button"
                                                            class="dd-item customModal"
                                                            data-size="xl"
                                                            data-title="Primary Units - View Units"
                                                            data-url="{{ route('programmes.cofo.pua-units', $primary->id) }}">
                                                            <i data-lucide="layers" class="w-4 h-4 text-blue-600"></i>
                                                            <span>View PuA(s)</span>
                                                        </button>
                                                        
                                                    </div>
                                                </div>
                                            </td>
                                    </tr>
                                @endif
                            @endforeach
                            @if($generatedCount === 0)
                                <tr>
                                    <td colspan="10" class="table-cell text-center py-4">No primary applications have completed CofO generation</td>
                                </tr>
                            @endif
                        </tbody>
                    </table>
                </div>
                </div>

                <!-- SUA Content -->
                <div class="hidden" data-tab-group-content="main" data-tab-key="sua">
                    <div class="p-6 space-y-6">
                        <div class="bg-purple-50 border-l-4 border-purple-500 p-4 rounded-lg shadow-sm">
                            <p class="text-sm text-purple-700">Standalone (SUA) certificates are generated per unit. Confirm memo and RoFO details before issuing the CofO.</p>
                        </div>

                        @php
                            $suaPending = $suaUnitApplications->filter(fn ($application) => ! $application->certificate_issued);
                            $suaIssued = $suaUnitApplications->filter(fn ($application) => $application->certificate_issued);
                        @endphp

                        <div class="border-b border-purple-100 bg-purple-50/60 px-4 pt-4 pb-3 rounded-lg">
                            <div class="controls controls-sub">
                                <div class="tabs">
                                    <button class="tab-btn active" data-tab-group="sua" data-tab="not-generated">Not Generated</button>
                                    <button class="tab-btn" data-tab-group="sua" data-tab="generated">Generated</button>
                                </div>
                            </div>
                        </div>

                        <div class="space-y-6">
                            <div data-tab-group-content="sua" data-tab-key="not-generated">
                                <div class="rounded-xl border border-purple-100 shadow-sm overflow-hidden">
                                    <div class="px-6 py-4 bg-white border-b border-purple-100 flex flex-col md:flex-row md:items-center md:justify-between gap-3">
                                        <div>
                                            <h4 class="text-base font-semibold text-purple-900">Not Generated SUA Certificates</h4>
                                            <p class="text-sm text-purple-600">{{ $suaPending->count() }} unit(s) awaiting CofO issuance.</p>
                                        </div>
                                        <div class="flex flex-wrap items-center gap-2">
                                            <span class="badge badge-approved">Ready {{ $suaStats['ready'] }}</span>
                                            <span class="badge badge-pending">Awaiting {{ $suaPending->count() }}</span>
                                        </div>
                                    </div>

                                    <div class="tab-content overflow-x-auto">
                                        <table class="min-w-full divide-y divide-gray-200" id="sua-pending-table">
                                            <thead>
                                                <tr class="text-xs">
                                                    <th class="table-header">File No</th>
                                                    <th class="table-header">Unit Owner</th>
                                                    <th class="table-header">Land Use</th>
                                                    <th class="table-header">Memo</th>
                                                    <th class="table-header">RoFO</th>
                                                    <th class="table-header">Status</th>
                                                    <th class="table-header">Planning</th>
                                                    <th class="table-header">Application</th>
                                                    <th class="table-header">Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody class="bg-white divide-y divide-gray-200">
                                                @forelse($suaPending as $application)
                                                    <tr class="text-sm text-gray-700">
                                                        <td class="table-cell">{{ $application->fileno ?? 'N/A' }}</td>
                                                        <td class="table-cell">{{ $application->owner_name }}</td>
                                                        <td class="table-cell">{{ $application->land_use }}</td>
                                                        <td class="table-cell">
                                                            <div class="flex flex-col gap-1">
                                                                <span class="badge {{ $application->memo_generated ? 'badge-approved' : 'badge-blocked' }}">
                                                                    {{ $application->memo_generated ? 'Uploaded' : 'Pending' }}
                                                                </span>
                                                                @if($application->memo_generated_at)
                                                                    <span class="text-xs text-gray-500">{{ \Carbon\Carbon::parse($application->memo_generated_at)->format('d M Y') }}</span>
                                                                @endif
                                                            </div>
                                                        </td>
                                                        <td class="table-cell">
                                                            <div class="flex flex-col gap-1">
                                                                <span class="badge {{ $application->rofo_generated ? 'badge-approved' : 'badge-blocked' }}">
                                                                    {{ $application->rofo_generated ? 'Generated' : 'Pending' }}
                                                                </span>
                                                                @if($application->rofo_generated_at)
                                                                    <span class="text-xs text-gray-500">{{ \Carbon\Carbon::parse($application->rofo_generated_at)->format('d M Y') }}</span>
                                                                @endif
                                                            </div>
                                                        </td>
                                                        <td class="table-cell">
                                                            <div class="flex flex-col gap-1">
                                                                <span class="badge {{ $application->progress_class }}">{{ $application->progress_status }}</span>
                                                                <span class="text-xs text-gray-500">{{ $application->readiness_message }}</span>
                                                            </div>
                                                        </td>
                                                        <td class="table-cell">
                                                            <span class="text-xs text-gray-600">{{ $application->planning_status_label }}</span>
                                                        </td>
                                                        <td class="table-cell">
                                                            <span class="text-xs text-gray-600">{{ $application->application_status_label }}</span>
                                                        </td>
                                                        <td class="table-cell">
                                                            <div class="dd">
                                                                <button type="button" class="dd-btn" onclick="toggleDD(this)">
                                                                    <i data-lucide="more-horizontal" class="w-5 h-5"></i>
                                                                </button>
                                                                <div class="dd-menu">
                                                                    <a class="dd-item" href="{{ route('sectionaltitling.viewrecorddetail_sub', $application->id) }}">
                                                                        <i data-lucide="eye" class="w-4 h-4"></i>
                                                                        View Application
                                                                    </a>
                                                                    @if($application->ready_for_cofo)
                                                                        <a class="dd-item" href="{{ route('programmes.generate_cofo', $application->id) }}">
                                                                            <i data-lucide="file-plus" class="w-4 h-4"></i>
                                                                            Generate CofO
                                                                        </a>
                                                                    @else
                                                                        <span class="dd-item disabled" title="{{ $application->readiness_message }}">Generate CofO</span>
                                                                    @endif
                                                                </div>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                @empty
                                                    <tr>
                                                        <td colspan="9" class="table-cell text-center py-4">No SUA units are waiting for CofO issuance.</td>
                                                    </tr>
                                                @endforelse
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>

                            <div class="hidden" data-tab-group-content="sua" data-tab-key="generated">
                                <div class="rounded-xl border border-purple-100 shadow-sm overflow-hidden">
                                    <div class="px-6 py-4 bg-white border-b border-purple-100 flex flex-col md:flex-row md:items-center md:justify-between gap-3">
                                        <div>
                                            <h4 class="text-base font-semibold text-purple-900">Generated SUA Certificates</h4>
                                            <p class="text-sm text-purple-600">{{ $suaIssued->count() }} unit(s) with completed CofO.</p>
                                        </div>
                                        <div class="flex flex-wrap items-center gap-2">
                                            <span class="badge badge-issued">Generated {{ $suaIssued->count() }}</span>
                                        </div>
                                    </div>

                                    <div class="tab-content overflow-x-auto">
                                        <table class="min-w-full divide-y divide-gray-200" id="sua-issued-table">
                                            <thead>
                                                <tr class="text-xs">
                                                    <th class="table-header">File No</th>
                                                    <th class="table-header">Unit Owner</th>
                                                    <th class="table-header">Land Use</th>
                                                    <th class="table-header">Certificate No</th>
                                                    <th class="table-header">Status</th>
                                                    <th class="table-header">Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody class="bg-white divide-y divide-gray-200">
                                                @forelse($suaIssued as $application)
                                                    <tr class="text-sm text-gray-700">
                                                        <td class="table-cell">{{ $application->fileno ?? 'N/A' }}</td>
                                                        <td class="table-cell">{{ $application->owner_name }}</td>
                                                        <td class="table-cell">{{ $application->land_use }}</td>
                                                        <td class="table-cell">
                                                            <span class="inline-flex items-center gap-2">
                                                                <i data-lucide="award" class="w-4 h-4 text-purple-500"></i>
                                                                {{ $application->certificate_number ?? 'N/A' }}
                                                            </span>
                                                        </td>
                                                        <td class="table-cell">
                                                            <span class="badge badge-issued">Certificate Issued</span>
                                                        </td>
                                                        <td class="table-cell">
                                                            <div class="dd">
                                                                <button type="button" class="dd-btn" onclick="toggleDD(this)">
                                                                    <i data-lucide="more-horizontal" class="w-5 h-5"></i>
                                                                </button>
                                                                <div class="dd-menu">
                                                                    <a class="dd-item" href="{{ route('sectionaltitling.viewrecorddetail_sub', $application->id) }}">
                                                                        <i data-lucide="eye" class="w-4 h-4"></i>
                                                                        View Application
                                                                    </a>
                                                                    @if(!empty($application->cofo_id))
                                                                        <a class="dd-item" href="{{ route('programmes.view_cofo', $application->id) }}" target="_blank">
                                                                            <i data-lucide="file-text" class="w-4 h-4"></i>
                                                                            View CofO
                                                                        </a>
                                                                        <button type="button" class="dd-item"
                                                                            onclick="SmartPrintManager.open(
                                                                                '{{ $application->fileno }}',
                                                                                'ST CofO',
                                                                                '{{ route('programmes.print_cofo', $application->id) }}'
                                                                            )">
                                                                            <i data-lucide="printer" class="w-4 h-4 text-purple-600"></i>
                                                                            Print Manager
                                                                        </button>
                                                                    @else
                                                                        <span class="dd-item disabled">CofO record unavailable</span>
                                                                    @endif
                                                                </div>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                @empty
                                                    <tr>
                                                        <td colspan="6" class="table-cell text-center py-4">No SUA certificates have been generated yet.</td>
                                                    </tr>
                                                @endforelse
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Page Footer -->
    @include($footerPartial ?? 'admin.footer')
</div>

@include('sectionaltitling.action_modals.rofo_details_modal')
@include('sectionaltitling.action_modals.cofo_details_modal')

<script>
    function closeAllDD() {
        document.querySelectorAll('.dd-menu.show').forEach(menu => menu.classList.remove('show'));
    }

    function toggleDD(btn) {
        const menu = btn.nextElementSibling;
        if (!menu) return;

        const wasOpen = menu.classList.contains('show');
        closeAllDD();
        if (wasOpen) return;

        // Temporarily show menu to measure width/height accurately
        menu.style.visibility = 'hidden';
        menu.classList.add('show');
        const menuWidth = menu.offsetWidth || 190;
        const menuHeight = menu.offsetHeight || 150;
        menu.classList.remove('show');
        menu.style.visibility = '';

        const rect = btn.getBoundingClientRect();
        const modalContent = btn.closest('#modal-content');
        
        let topOffset = 0;
        let leftOffset = 0;
        
        if (modalContent) {
            const modalRect = modalContent.getBoundingClientRect();
            topOffset = modalRect.top;
            leftOffset = modalRect.left;
        }

        // Calculate Left
        let left = rect.right - menuWidth - leftOffset;
        if (left < 8) {
            left = (rect.left - leftOffset);
        }
        const maxLeft = (window.innerWidth - menuWidth - 8) - leftOffset;
        if (left > maxLeft) {
            left = maxLeft;
        }

        // Calculate Top (Smart positioning: open upward if not enough space below)
        let top = (rect.bottom + 6) - topOffset;
        const spaceBelow = window.innerHeight - rect.bottom;
        if (spaceBelow < menuHeight + 10) {
            top = (rect.top - menuHeight - 6) - topOffset;
        }

        menu.style.left = left + 'px';
        menu.style.top = top + 'px';
        menu.classList.add('show');
    }

    // Bridge to global security paper modal component
    function openSecurityPaperModal(id, fileno, currentCode) {
        openAssignSecurityPaperModal(
            id, fileno, currentCode,
            '{{ url("programmes/rofo/assign-security-paper") }}/' + id,
            { codesApiUrl: '{{ url("security-paper-codes/available") }}', fieldName: 'security_paper_code' }
        );
    }

    // Bridge to the global reset modal. A successful reset chains into the
    // picker so the correct code can be keyed in before the page reloads.
    function resetSecurityPaperModal(id, fileno, currentCode) {
        openResetSecurityPaperModal(
            id, fileno, currentCode,
            '{{ url("programmes/rofo/reset-security-paper") }}/' + id,
            {
                assignEndpoint: '{{ url("programmes/rofo/assign-security-paper") }}/' + id,
                codesApiUrl: '{{ url("security-paper-codes/available") }}',
                fieldName: 'security_paper_code'
            }
        );
    }

    document.addEventListener('click', function(event) {
        if (!event.target.closest('.dd')) {
            closeAllDD();
        }
    });

    window.addEventListener('scroll', closeAllDD, true);
    window.addEventListener('resize', closeAllDD);

    function setupTabGroup(groupName, defaultTab) {
        const buttons = document.querySelectorAll(`.tab-btn[data-tab-group="${groupName}"]`);
        const contents = document.querySelectorAll(`[data-tab-group-content="${groupName}"]`);

        if (!buttons.length || !contents.length) {
            return;
        }

        const activateTab = (tabKey) => {
            closeAllDD();

            buttons.forEach(button => {
                button.classList.toggle('active', button.getAttribute('data-tab') === tabKey);
            });

            contents.forEach(content => {
                if (content.getAttribute('data-tab-key') === tabKey) {
                    content.classList.remove('hidden');
                } else {
                    content.classList.add('hidden');
                }
            });
        };

        buttons.forEach(button => {
            button.addEventListener('click', function() {
                activateTab(this.getAttribute('data-tab'));
            });
        });

        const initialTab = defaultTab || (buttons[0] ? buttons[0].getAttribute('data-tab') : null);
        if (initialTab) {
            activateTab(initialTab);
        }
    }

    function handlePrimaryCofOBatch(button) {
        if (!button) {
            return;
        }

        closeAllDD();

        const url = button.getAttribute('data-batch-url');
        const primaryId = Number(button.getAttribute('data-primary') || '0');
        const pendingUnits = Number(button.getAttribute('data-pending-units') || '0');

        if (!url || !primaryId || pendingUnits <= 0) {
            return;
        }

        const message = `You are about to generate CofO certificates for ${pendingUnits} PuA unit(s).`;
        const reminder = 'Ensure all prerequisite documents are verified before continuing.';

        const proceed = () => submitPrimaryCofOBatch(url, primaryId);

        if (typeof Swal !== 'undefined') {
            Swal.fire({
                title: 'Generate CofO Batch',
                html: `${message}<br><br>${reminder}`,
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Yes, generate',
                cancelButtonText: 'Cancel'
            }).then(result => {
                if (result.isConfirmed) {
                    proceed();
                }
            });
        } else if (confirm(`${message}\n\n${reminder}`)) {
            proceed();
        }
    }

    function submitPrimaryCofOBatch(url, primaryId) {
        const tokenElement = document.querySelector('meta[name="csrf-token"]');
        const token = tokenElement ? tokenElement.getAttribute('content') : '';

        fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': token,
            },
            body: JSON.stringify({ primary_application_id: primaryId })
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const successMessage = data.message || 'CofO batch generated successfully.';

                    if (typeof Swal !== 'undefined') {
                        Swal.fire({
                            title: 'Batch Generated',
                            text: successMessage,
                            icon: 'success',
                            confirmButtonText: 'OK'
                        }).then(() => window.location.reload());
                    } else {
                        alert(successMessage);
                        window.location.reload();
                    }
                } else {
                    const errorMessage = data.message || 'Unable to generate CofO batch.';

                    if (typeof Swal !== 'undefined') {
                        Swal.fire({
                            title: 'Generation Failed',
                            text: errorMessage,
                            icon: 'error',
                            confirmButtonText: 'OK'
                        });
                    } else {
                        alert(errorMessage);
                    }
                }
            })
            .catch(() => {
                const fallback = 'An unexpected error occurred while generating the batch.';

                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        title: 'Generation Failed',
                        text: fallback,
                        icon: 'error',
                        confirmButtonText: 'OK'
                    });
                } else {
                    alert(fallback);
                }
            });
    }

    document.addEventListener('DOMContentLoaded', function() {
        setupTabGroup('main', 'pua');
        setupTabGroup('pua', 'not-generated');
        setupTabGroup('sua', 'not-generated');
    });
</script>
@endsection