@extends('layouts.app')

@section('page-title')
    {{ $PageTitle }}
@endsection

@section('content')
    <div class="flex-1 overflow-auto">
        @include('admin.header')

        <style>
            .upload-modal-backdrop {
                backdrop-filter: blur(2px);
                -webkit-backdrop-filter: blur(2px);
            }

            .drag-highlight {
                border-color: #10b981 !important;
                background-color: #f0fdf4 !important;
            }
        </style>

        <div class="p-6">
            <div class="bg-white rounded-md shadow-sm border border-gray-200">
                <div class="p-6">
                    <div class="flex flex-col space-y-6">
                        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                            <div>
                                <h1 class="text-2xl font-bold text-gray-800">{{ $PageTitle }}</h1>
                                <p class="text-gray-600">{{ $PageDescription }}</p>
                            </div>
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-3 w-full md:w-auto">
                                <div class="bg-blue-50 border border-blue-200 rounded-lg px-4 py-3">
                                    <p class="text-xs font-medium text-blue-500 uppercase tracking-wide">Total SUA Units</p>
                                    <p class="mt-1 text-2xl font-bold text-blue-700">{{ $totalUnits }}</p>
                                </div>
                                <div class="bg-amber-50 border border-amber-200 rounded-lg px-4 py-3">
                                    <p class="text-xs font-medium text-amber-500 uppercase tracking-wide">Pending Memos</p>
                                    <p class="mt-1 text-2xl font-bold text-amber-700">{{ $pendingCount }}</p>
                                </div>
                                <div class="bg-green-50 border border-green-200 rounded-lg px-4 py-3">
                                    <p class="text-xs font-medium text-green-500 uppercase tracking-wide">Generated Memos
                                    </p>
                                    <p class="mt-1 text-2xl font-bold text-green-700">{{ $generatedCount }}</p>
                                </div>
                            </div>
                        </div>

                        <div class="bg-blue-50 border border-blue-200 rounded-lg px-4 py-4">
                            <div class="flex items-start gap-3">
                                <i data-lucide="info" class="w-5 h-5 text-blue-500 mt-0.5"></i>
                                <div>
                                    <h3 class="text-sm font-semibold text-blue-900 uppercase tracking-wide">Prerequisites
                                        for Generating SUA Memos</h3>
                                    <p class="mt-1 text-sm text-blue-800">
                                        Ensure the following are approved before generating a memo:
                                        <span class="font-medium">Planning Recommendation Approval</span> and
                                        <span class="font-medium">ST Director's Approval</span>.
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div x-data="{ activeTab: 'pending' }" x-cloak>
                            <div class="flex flex-wrap border-b border-gray-200">
                                <button type="button" @click="activeTab = 'pending'"
                                    :class="activeTab === 'pending' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700'"
                                    class="px-4 py-2 text-sm font-medium border-b-2 focus:outline-none">
                                    Not Generated ({{ $pendingCount }})
                                </button>
                                <button type="button" @click="activeTab = 'generated'"
                                    :class="activeTab === 'generated' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700'"
                                    class="px-4 py-2 text-sm font-medium border-b-2 focus:outline-none">
                                    Generated ({{ $generatedCount }})
                                </button>
                            </div>

                            <div x-show="activeTab === 'pending'" x-transition class="mt-6">
                                <div class="mb-6">
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Search Pending SUA
                                        Applications</label>
                                    <div class="flex gap-2">
                                        <div class="relative flex-1">
                                            <div
                                                class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                                <i data-lucide="search" class="h-5 w-5 text-gray-400"></i>
                                            </div>
                                            <input type="text" id="pendingSearch"
                                                oninput="filterTable('pendingSearch','pendingTableBody','pendingResults', totals.pending)"
                                                placeholder="Search by file number, owner name, land use, LGA..."
                                                class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                        </div>
                                        <button type="button"
                                            onclick="clearSearch('pendingSearch','pendingTableBody','pendingResults', totals.pending)"
                                            class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-500">
                                            Clear
                                        </button>
                                    </div>
                                    <div class="mt-2">
                                        <span id="pendingResults" class="text-sm text-gray-500"></span>
                                    </div>
                                </div>

                                <div class="overflow-x-auto">
                                    <table class="min-w-full divide-y divide-gray-200" id="pendingTable">
                                        <thead class="bg-gray-50">
                                            <tr>
                                                <th
                                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                    File Number</th>
                                                <th
                                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                    Unit Owner</th>
                                                <th
                                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                    Unit Size</th>
                                                <th
                                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                    LGA</th>
                                                <th
                                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                    Land Use</th>
                                                <th
                                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                    Prerequisites</th>
                                                <th
                                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                    Status</th>
                                                <th
                                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                    Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody id="pendingTableBody" class="bg-white divide-y divide-gray-200">
                                            @forelse($pendingApplications as $application)
                                                                                @php
                                                                                    $prereqs = $application->prerequisites ?? [];
                                                                                    $missing = $application->missing_prerequisites ?? [];
                                                                                    $ownerLabel = $application->owner_name ?? 'N/A';
                                                                                    $planningDate = $application->planning_approval_date
                                                                                        ? \Carbon\Carbon::parse($application->planning_approval_date)->format('d M Y')
                                                                                        : null;
                                                                                    $planningPrereq = $prereqs['planning_recommendation'] ?? null;
                                                                                    $planningStatusText = $planningPrereq['status'] ?? 'Pending';
                                                                                @endphp
                                                                                <tr class="hover:bg-gray-50">
                                                                                    <td class="px-6 py-4 whitespace-nowrap">
                                                                                        <div class="text-sm font-medium text-gray-900">
                                                                                            {{ $application->fileno ?? 'N/A' }}</div>
                                                                                    </td>
                                                                                    <td class="px-6 py-4 whitespace-nowrap">
                                                                                        <div class="text-sm font-semibold text-gray-900">{{ $ownerLabel }}</div>
                                                                                        @if(!empty($application->owner_names_list) && count($application->owner_names_list) > 1)
                                                                                            <div class="text-xs text-gray-500 mt-1">
                                                                                                +{{ count($application->owner_names_list) - 1 }} additional
                                                                                                owner{{ count($application->owner_names_list) - 1 === 1 ? '' : 's' }}
                                                                                            </div>
                                                                                        @endif
                                                                                    </td>
                                                                                    <td class="px-6 py-4 whitespace-nowrap">
                                                                                        <span
                                                                                            class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                                                                                            {{ $application->unit_size ?? 'N/A' }}
                                                                                        </span>
                                                                                    </td>
                                                                                    <td class="px-6 py-4 whitespace-nowrap">
                                                                                        <div class="text-sm text-gray-900">{{ $application->unit_lga ?? 'N/A' }}
                                                                                        </div>
                                                                                    </td>
                                                                                    <td class="px-6 py-4 whitespace-nowrap">
                                                                                        <span
                                                                                            class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                                                                                    {{ ($application->land_use ?? '') === 'Residential' ? 'bg-green-100 text-green-800' :
                                                (($application->land_use ?? '') === 'Commercial' ? 'bg-blue-100 text-blue-800' : 'bg-yellow-100 text-yellow-800') }}">
                                                                                            {{ $application->land_use ?? 'N/A' }}
                                                                                        </span>
                                                                                    </td>
                                                                                    <td class="px-6 py-4">
                                                                                        <div class="space-y-1 text-xs">
                                                                                            <div
                                                                                                class="flex items-center gap-2 {{ ($planningPrereq['met'] ?? false) ? 'text-green-600' : 'text-amber-600' }}">
                                                                                                <i data-lucide="{{ ($planningPrereq['met'] ?? false) ? 'check-circle' : 'clock' }}"
                                                                                                    class="w-3 h-3"></i>
                                                                                                <span>
                                                                                                    Planning Recommendation Approval: {{ $planningStatusText }}
                                                                                                    @if($planningDate)
                                                                                                        ({{ $planningDate }})
                                                                                                    @endif
                                                                                                </span>
                                                                                            </div>

                                                                                            <div
                                                                                                class="flex items-center gap-2 {{ ($prereqs['director']['met'] ?? false) ? 'text-green-600' : 'text-amber-600' }}">
                                                                                                <i data-lucide="{{ ($prereqs['director']['met'] ?? false) ? 'check-circle' : 'clock' }}"
                                                                                                    class="w-3 h-3"></i>
                                                                                                <span>ST Director's Approval:
                                                                                                    {{ $application->application_status ?? 'Pending' }}</span>
                                                                                            </div>

                                                                                        </div>
                                                                                    </td>
                                                                                    <td class="px-6 py-4 whitespace-nowrap">
                                                                                        <span
                                                                                            class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold {{ $application->status_style }}">
                                                                                            {{ $application->status_label }}
                                                                                        </span>
                                                                                        @if(!empty($missing))
                                                                                            <div class="mt-1 text-xs text-amber-600">
                                                                                                Waiting on {{ implode(', ', $missing) }}
                                                                                            </div>
                                                                                        @elseif($application->status_label === 'Ready')
                                                                                            <div class="mt-1 text-xs text-blue-600">
                                                                                                All prerequisites satisfied
                                                                                            </div>
                                                                                        @endif
                                                                                    </td>
                                                                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                                                                        <div class="relative" x-data="{
                                                                                                    open: false,
                                                                                                    toggleDropdown() {
                                                                                                        this.open = !this.open;
                                                                                                        if (this.open) {
                                                                                                            this.$nextTick(() => this.positionDropdown());
                                                                                                        }
                                                                                                    },
                                                                                                    positionDropdown() {
                                                                                                        const button = this.$refs.button;
                                                                                                        const dropdown = this.$refs.dropdown;
                                                                                                        const rect = button.getBoundingClientRect();
                                                                                                        dropdown.style.position = 'fixed';
                                                                                                        dropdown.style.top = (rect.bottom + 5) + 'px';
                                                                                                        dropdown.style.right = (window.innerWidth - rect.right) + 'px';
                                                                                                        dropdown.style.zIndex = '99999';
                                                                                                        const dropdownRect = dropdown.getBoundingClientRect();
                                                                                                        if (dropdownRect.bottom > window.innerHeight - 10) {
                                                                                                            dropdown.style.top = (rect.top - dropdown.offsetHeight - 5) + 'px';
                                                                                                        }
                                                                                                    }
                                                                                                }">
                                                                                            <button @click="toggleDropdown()" @click.away="open = false"
                                                                                                x-ref="button"
                                                                                                class="inline-flex items-center px-3 py-2 border border-gray-300 rounded-md bg-white text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                                                                                <i data-lucide="more-horizontal" class="w-4 h-4"></i>
                                                                                                <span class="sr-only">Open actions menu</span>
                                                                                            </button>
                                                                                            <div x-show="open" x-ref="dropdown" x-cloak style="display: none;"
                                                                                                x-transition:enter="transition ease-out duration-100"
                                                                                                x-transition:enter-start="transform opacity-0 scale-95"
                                                                                                x-transition:enter-end="transform opacity-100 scale-100"
                                                                                                x-transition:leave="transition ease-in duration-75"
                                                                                                x-transition:leave-start="transform opacity-100 scale-100"
                                                                                                x-transition:leave-end="transform opacity-0 scale-95"
                                                                                                class="w-52 rounded-md shadow-xl bg-white ring-1 ring-black ring-opacity-5 origin-top-right">
                                                                                                <div class="py-1" role="menu">
                                                                                                    <a href="{{ url('/sectionaltitling/edit_sub/' . $application->id) }}"
                                                                                                        class="group flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 hover:text-gray-900">
                                                                                                        <i data-lucide="eye"
                                                                                                            class="mr-3 h-4 w-4 text-blue-500 group-hover:text-blue-600"></i>
                                                                                                        View Application
                                                                                                    </a>
                                                                                                    @if($application->can_generate_memo)
                                                                                                        <a href="{{ route('programmes.generate_sua_memo_form', $application->id) }}"
                                                                                                            class="group flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 hover:text-gray-900">
                                                                                                            <i data-lucide="file-plus"
                                                                                                                class="mr-3 h-4 w-4 text-green-500 group-hover:text-green-600"></i>
                                                                                                            Generate Memo
                                                                                                        </a>
                                                                                                    @else
                                                                                                        <span
                                                                                                            class="group flex items-center px-4 py-2 text-sm text-gray-400 cursor-not-allowed"
                                                                                                            title="Waiting on: {{ implode(', ', $missing) ?: 'Prerequisites' }}">
                                                                                                            <i data-lucide="file-plus"
                                                                                                                class="mr-3 h-4 w-4 text-gray-300"></i>
                                                                                                            Generate Memo
                                                                                                        </span>
                                                                                                    @endif
                                                                                                    <span
                                                                                                        class="group flex items-center px-4 py-2 text-sm text-gray-400 cursor-not-allowed">
                                                                                                        <i data-lucide="file-text"
                                                                                                            class="mr-3 h-4 w-4 text-gray-300"></i>
                                                                                                        View Memo
                                                                                                    </span>
                                                                                                </div>
                                                                                            </div>
                                                                                        </div>
                                                                                    </td>
                                                                                </tr>
                                            @empty
                                                <tr>
                                                    <td colspan="8" class="px-6 py-8 text-center">
                                                        <div class="flex flex-col items-center">
                                                            <i data-lucide="clipboard-check"
                                                                class="w-12 h-12 text-gray-300 mb-3"></i>
                                                            <h3 class="text-lg font-medium text-gray-900 mb-1">All SUA units
                                                                have generated memos</h3>
                                                            <p class="text-gray-500 text-sm">No pending SUA memos found.</p>
                                                        </div>
                                                    </td>
                                                </tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <div x-show="activeTab === 'generated'" x-transition class="mt-6">
                                <div class="mb-6">
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Search Generated SUA
                                        Memos</label>
                                    <div class="flex gap-2">
                                        <div class="relative flex-1">
                                            <div
                                                class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                                <i data-lucide="search" class="h-5 w-5 text-gray-400"></i>
                                            </div>
                                            <input type="text" id="generatedSearch"
                                                oninput="filterTable('generatedSearch','generatedTableBody','generatedResults', totals.generated)"
                                                placeholder="Search by memo number, file number, owner name..."
                                                class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                        </div>
                                        <button type="button"
                                            onclick="clearSearch('generatedSearch','generatedTableBody','generatedResults', totals.generated)"
                                            class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-500">
                                            Clear
                                        </button>
                                    </div>
                                    <div class="mt-2">
                                        <span id="generatedResults" class="text-sm text-gray-500"></span>
                                    </div>
                                </div>

                                <div class="overflow-x-auto">
                                    <table class="min-w-full divide-y divide-gray-200" id="generatedTable">
                                        <thead class="bg-gray-50">
                                            <tr>
                                                <th
                                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                    Memo No</th>
                                                <th
                                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                    File Number</th>
                                                <th
                                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                    Unit Owner</th>

                                                <th
                                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                    Generated On</th>
                                                <th
                                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                    Status</th>
                                                <th
                                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                    Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody id="generatedTableBody" class="bg-white divide-y divide-gray-200">
                                            @forelse($generatedApplications as $application)
                                                @php
                                                    $memo = $application->memo_record;
                                                    $ownerLabel = $application->owner_name ?? 'N/A';
                                                    $generatedDate = $memo && $memo->created_at ? \Carbon\Carbon::parse($memo->created_at)->format('d M Y') : 'N/A';
                                                    $latestUploadedMemo = null;

                                                    if (!empty($application->main_application_id) && !empty($application->fileno)) {
                                                        $latestUploadedMemo = DB::connection('sqlsrv')->table('memo_uploads')
                                                            ->where('application_id', $application->main_application_id)
                                                            ->where('memo_type', 'sua')
                                                            ->where('status', 'active')
                                                            ->where('file_no', $application->fileno)
                                                            ->orderBy('uploaded_at', 'desc')
                                                            ->first();
                                                    }
                                                @endphp
                                                <tr class="hover:bg-gray-50">
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-semibold text-gray-900">
                                                        {{ $memo->memo_no ?? 'N/A' }}</td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                        {{ $application->fileno ?? 'N/A' }}</td>
                                                    <td class="px-6 py-4 whitespace-nowrap">
                                                        <div class="text-sm font-semibold text-gray-900">{{ $ownerLabel }}</div>
                                                        @if(!empty($application->owner_names_list) && count($application->owner_names_list) > 1)
                                                            <div class="text-xs text-gray-500 mt-1">
                                                                +{{ count($application->owner_names_list) - 1 }} additional
                                                                owner{{ count($application->owner_names_list) - 1 === 1 ? '' : 's' }}
                                                            </div>
                                                        @endif
                                                    </td>

                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                        {{ $generatedDate }}</td>
                                                    <td class="px-6 py-4 whitespace-nowrap">
                                                        <span
                                                            class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-green-100 text-green-700">
                                                            Generated
                                                        </span>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                                        <div class="relative" x-data="{
                                                                    open: false,
                                                                    toggleDropdown() {
                                                                        this.open = !this.open;
                                                                        if (this.open) {
                                                                            this.$nextTick(() => this.positionDropdown());
                                                                        }
                                                                    },
                                                                    positionDropdown() {
                                                                        const button = this.$refs.button;
                                                                        const dropdown = this.$refs.dropdown;
                                                                        const rect = button.getBoundingClientRect();
                                                                        dropdown.style.position = 'fixed';
                                                                        dropdown.style.top = (rect.bottom + 5) + 'px';
                                                                        dropdown.style.right = (window.innerWidth - rect.right) + 'px';
                                                                        dropdown.style.zIndex = '99999';
                                                                        const dropdownRect = dropdown.getBoundingClientRect();
                                                                        if (dropdownRect.bottom > window.innerHeight - 10) {
                                                                            dropdown.style.top = (rect.top - dropdown.offsetHeight - 5) + 'px';
                                                                        }
                                                                    }
                                                                }">
                                                            <button @click="toggleDropdown()" @click.away="open = false"
                                                                x-ref="button"
                                                                class="inline-flex items-center px-3 py-2 border border-gray-300 rounded-md bg-white text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                                                <i data-lucide="more-horizontal" class="w-4 h-4"></i>
                                                                <span class="sr-only">Open actions menu</span>
                                                            </button>
                                                            <div x-show="open" x-ref="dropdown" x-cloak style="display: none;"
                                                                x-transition:enter="transition ease-out duration-100"
                                                                x-transition:enter-start="transform opacity-0 scale-95"
                                                                x-transition:enter-end="transform opacity-100 scale-100"
                                                                x-transition:leave="transition ease-in duration-75"
                                                                x-transition:leave-start="transform opacity-100 scale-100"
                                                                x-transition:leave-end="transform opacity-0 scale-95"
                                                                class="w-52 rounded-md shadow-xl bg-white ring-1 ring-black ring-opacity-5 origin-top-right">
                                                                <div class="py-1" role="menu">
                                                                    <a href="{{ url('/sectionaltitling/edit_sub/' . $application->id) }}"
                                                                        class="group flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 hover:text-gray-900">
                                                                        <i data-lucide="eye"
                                                                            class="mr-3 h-4 w-4 text-blue-500 group-hover:text-blue-600"></i>
                                                                        View Application
                                                                    </a>
                                                                    @if($latestUploadedMemo)
                                                                        <a href="{{ route('programmes.view_uploaded_memo', $latestUploadedMemo->id) }}"
                                                                            target="_blank"
                                                                            class="group flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 hover:text-gray-900">
                                                                            <i data-lucide="file-text"
                                                                                class="mr-3 h-4 w-4 text-amber-600 group-hover:text-amber-700"></i>
                                                                            View Memo (Edited)
                                                                        </a>
                                                                    @else
                                                                        <a href="{{ route('programmes.view_sua_memo', $application->id) }}"
                                                                            target="_blank"
                                                                            class="group flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 hover:text-gray-900">
                                                                            <i data-lucide="clipboard"
                                                                                class="mr-3 h-4 w-4 text-amber-600 group-hover:text-amber-700"></i>
                                                                            View Memo
                                                                        </a>
                                                                    @endif
                                                                    @if($latestUploadedMemo)
                                                                        <span
                                                                            class="group flex items-center px-4 py-2 text-sm text-gray-400 cursor-not-allowed"
                                                                            title="An edited memo has already been uploaded">
                                                                            <i data-lucide="upload"
                                                                                class="mr-3 h-4 w-4 text-gray-300"></i>
                                                                            Upload Edited ST Memo
                                                                        </span>
                                                                    @elseif($application->main_application_id)
                                                                        <button type="button"
                                                                            onclick="openUploadModal({{ $application->main_application_id }}, {{ json_encode($application->fileno ?? 'N/A') }}, 'sua')"
                                                                            class="group flex items-center w-full px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 hover:text-gray-900">
                                                                            <i data-lucide="upload"
                                                                                class="mr-3 h-4 w-4 text-green-600 group-hover:text-green-700"></i>
                                                                            Upload Edited ST Memo
                                                                        </button>
                                                                    @else
                                                                        <span
                                                                            class="group flex items-center px-4 py-2 text-sm text-gray-400 cursor-not-allowed"
                                                                            title="Missing parent application for upload">
                                                                            <i data-lucide="upload"
                                                                                class="mr-3 h-4 w-4 text-gray-300"></i>
                                                                            Upload Edited ST Memo
                                                                        </span>
                                                                    @endif
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </td>
                                                </tr>
                                            @empty
                                                <tr>
                                                    <td colspan="7" class="px-6 py-8 text-center">
                                                        <div class="flex flex-col items-center">
                                                            <i data-lucide="file-x" class="w-12 h-12 text-gray-300 mb-3"></i>
                                                            <h3 class="text-lg font-medium text-gray-900 mb-1">No generated SUA
                                                                memos yet</h3>
                                                            <p class="text-gray-500 text-sm">Generate a memo from the Not
                                                                Generated tab once prerequisites are complete.</p>
                                                        </div>
                                                    </td>
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

    <!-- Upload Memo Modal -->
    <div id="uploadMemoModal"
        class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50 upload-modal-backdrop">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
            <div class="mt-3">
                <div class="flex items-center justify-between pb-3">
                    <h3 class="text-lg font-medium text-gray-900">Upload Memo Document</h3>
                    <button type="button" class="text-gray-400 hover:text-gray-600" onclick="closeUploadModal()">
                        <i data-lucide="x" class="w-6 h-6"></i>
                    </button>
                </div>

                <form id="uploadMemoForm" enctype="multipart/form-data">
                    <div class="mt-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Select PDF Document</label>
                        <div
                            class="mt-1 flex justify-center px-6 pt-5 pb-6 border-2 border-gray-300 border-dashed rounded-md transition-colors">
                            <div class="space-y-1 text-center">
                                <i data-lucide="file-text" class="mx-auto h-12 w-12 text-gray-400"></i>
                                <div class="flex text-sm text-gray-600">
                                    <label for="memo-file"
                                        class="relative cursor-pointer bg-white rounded-md font-medium text-green-600 hover:text-green-500 focus-within:outline-none focus-within:ring-2 focus-within:ring-offset-2 focus-within:ring-green-500">
                                        <span>Upload a file</span>
                                        <input id="memo-file" name="memo_file" type="file" class="sr-only" accept=".pdf"
                                            onchange="handleFileSelect(this)">
                                    </label>
                                    <p class="pl-1">or drag and drop</p>
                                </div>
                                <p class="text-xs text-gray-500">PDF files only, up to 10MB</p>
                            </div>
                        </div>

                        <div id="filePreview" class="hidden mt-3 p-3 bg-gray-50 rounded-md">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center">
                                    <i data-lucide="file-text" class="w-5 h-5 text-red-600 mr-2"></i>
                                    <span id="fileName" class="text-sm text-gray-700"></span>
                                </div>
                                <button type="button" onclick="removeFile()" class="text-red-600 hover:text-red-800">
                                    <i data-lucide="trash-2" class="w-4 h-4"></i>
                                </button>
                            </div>
                            <div class="mt-2">
                                <span id="fileSize" class="text-xs text-gray-500"></span>
                            </div>
                        </div>

                        <div id="uploadError" class="hidden mt-3 p-3 bg-red-100 border border-red-400 text-red-700 rounded">
                            <div class="flex items-center">
                                <i data-lucide="alert-circle" class="w-5 h-5 mr-2"></i>
                                <span id="errorMessage"></span>
                            </div>
                        </div>

                        <div class="mt-4">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Document Description
                                (Optional)</label>
                            <textarea id="memo-description" name="description" rows="3"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-green-500 focus:border-green-500"
                                placeholder="Add any additional notes about this memo document..."></textarea>
                        </div>
                    </div>

                    <div class="flex items-center justify-end pt-4 border-t border-gray-200 space-x-3">
                        <button type="button" onclick="closeUploadModal()"
                            class="px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400 transition-colors">Cancel</button>
                        <button type="submit" id="uploadBtn"
                            class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                            disabled>
                            <span id="uploadBtnText">Upload Edited ST Memo</span>
                            <i id="uploadSpinner" class="hidden ml-2 w-4 h-4 animate-spin" data-lucide="loader-2"></i>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection

@section('scripts')
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        window.totals = {
            pending: {{ $pendingCount }},
            generated: {{ $generatedCount }}
        };
        const totals = window.totals;

        let currentApplicationId = null;
        let currentFileNo = null;
        let currentMemoType = 'sua';

        function filterTable(searchInputId, tableBodyId, resultsId, total) {
            const input = document.getElementById(searchInputId);
            const tbody = document.getElementById(tableBodyId);
            const results = document.getElementById(resultsId);

            if (!input || !tbody) {
                return;
            }

            const searchTerm = input.value.toLowerCase().trim();
            const rows = Array.from(tbody.querySelectorAll('tr'));
            let visibleRows = 0;

            rows.forEach((row) => {
                const cells = row.getElementsByTagName('td');
                if (cells.length === 1 && cells[0].getAttribute('colspan')) {
                    row.style.display = '';
                    return;
                }

                const text = row.textContent.toLowerCase();
                const matches = !searchTerm || text.includes(searchTerm);
                row.style.display = matches ? '' : 'none';
                if (matches) {
                    visibleRows += 1;
                }
            });

            if (results) {
                if (searchTerm) {
                    if (visibleRows === 0) {
                        results.textContent = `No results found for "${input.value}"`;
                        results.className = 'text-sm text-red-500';
                    } else {
                        results.textContent = `Showing ${visibleRows} of ${total} result${visibleRows === 1 ? '' : 's'} for "${input.value}"`;
                        results.className = 'text-sm text-green-600';
                    }
                } else {
                    results.textContent = '';
                    results.className = 'text-sm text-gray-500';
                }
            }
        }

        function clearSearch(searchInputId, tableBodyId, resultsId, total) {
            const input = document.getElementById(searchInputId);
            if (input) {
                input.value = '';
                filterTable(searchInputId, tableBodyId, resultsId, total);
                input.focus();
            }
        }

        function openUploadModal(applicationId, fileNo, memoType = 'sua') {
            if (!applicationId) {
                Swal.fire({
                    title: 'Upload unavailable',
                    text: 'This memo cannot be linked to a parent application, so uploads are disabled.',
                    icon: 'info',
                    confirmButtonColor: '#10B981',
                    customClass: { popup: 'rounded-lg' }
                });
                return;
            }

            currentApplicationId = applicationId;
            currentFileNo = fileNo;
            currentMemoType = memoType || 'sua';

            const form = document.getElementById('uploadMemoForm');
            if (form) {
                form.reset();
            }

            const preview = document.getElementById('filePreview');
            const errorDiv = document.getElementById('uploadError');
            const uploadBtn = document.getElementById('uploadBtn');

            if (preview) {
                preview.classList.add('hidden');
            }
            if (errorDiv) {
                errorDiv.classList.add('hidden');
            }
            if (uploadBtn) {
                uploadBtn.disabled = true;
            }

            const modal = document.getElementById('uploadMemoModal');
            if (modal) {
                modal.classList.remove('hidden');
            }
            document.body.style.overflow = 'hidden';
        }

        function closeUploadModal() {
            const modal = document.getElementById('uploadMemoModal');
            if (modal) {
                modal.classList.add('hidden');
            }
            document.body.style.overflow = 'auto';
            currentApplicationId = null;
            currentFileNo = null;
            currentMemoType = 'sua';
        }

        function handleFileSelect(input) {
            const file = input.files[0];
            const errorDiv = document.getElementById('uploadError');
            const previewDiv = document.getElementById('filePreview');
            const uploadBtn = document.getElementById('uploadBtn');

            if (errorDiv) {
                errorDiv.classList.add('hidden');
            }

            if (!file) {
                if (previewDiv) {
                    previewDiv.classList.add('hidden');
                }
                if (uploadBtn) {
                    uploadBtn.disabled = true;
                }
                return;
            }

            if (file.type !== 'application/pdf') {
                showUploadError('Please select a PDF file only.');
                input.value = '';
                if (previewDiv) {
                    previewDiv.classList.add('hidden');
                }
                if (uploadBtn) {
                    uploadBtn.disabled = true;
                }
                return;
            }

            const maxSize = 10 * 1024 * 1024;
            if (file.size > maxSize) {
                showUploadError('File size must be less than 10MB.');
                input.value = '';
                if (previewDiv) {
                    previewDiv.classList.add('hidden');
                }
                if (uploadBtn) {
                    uploadBtn.disabled = true;
                }
                return;
            }

            const fileNameEl = document.getElementById('fileName');
            const fileSizeEl = document.getElementById('fileSize');

            if (fileNameEl) {
                fileNameEl.textContent = file.name;
            }
            if (fileSizeEl) {
                fileSizeEl.textContent = formatFileSize(file.size);
            }
            if (previewDiv) {
                previewDiv.classList.remove('hidden');
            }
            if (uploadBtn) {
                uploadBtn.disabled = false;
            }
        }

        function removeFile() {
            const fileInput = document.getElementById('memo-file');
            if (fileInput) {
                fileInput.value = '';
            }
            const previewDiv = document.getElementById('filePreview');
            if (previewDiv) {
                previewDiv.classList.add('hidden');
            }
            const errorDiv = document.getElementById('uploadError');
            if (errorDiv) {
                errorDiv.classList.add('hidden');
            }
            const uploadBtn = document.getElementById('uploadBtn');
            if (uploadBtn) {
                uploadBtn.disabled = true;
            }
        }

        function showUploadError(message) {
            const errorDiv = document.getElementById('uploadError');
            const errorMessage = document.getElementById('errorMessage');
            if (errorMessage) {
                errorMessage.textContent = message;
            }
            if (errorDiv) {
                errorDiv.classList.remove('hidden');
            }
        }

        function formatFileSize(bytes) {
            if (bytes === 0) return '0 Bytes';
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        }

        function preventDefaults(e) {
            e.preventDefault();
            e.stopPropagation();
        }

        function highlight(e) {
            e.currentTarget.classList.add('border-green-400', 'bg-green-50');
        }

        function unhighlight(e) {
            e.currentTarget.classList.remove('border-green-400', 'bg-green-50');
        }

        function handleDrop(e) {
            const dt = e.dataTransfer;
            const files = dt.files;

            if (files.length > 0) {
                const fileInput = document.getElementById('memo-file');
                if (fileInput) {
                    fileInput.files = files;
                    handleFileSelect(fileInput);
                }
            }
        }

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                closeUploadModal();
            }
        });

        document.addEventListener('DOMContentLoaded', () => {
            filterTable('pendingSearch', 'pendingTableBody', 'pendingResults', totals.pending);
            filterTable('generatedSearch', 'generatedTableBody', 'generatedResults', totals.generated);

            const uploadForm = document.getElementById('uploadMemoForm');
            if (uploadForm) {
                uploadForm.addEventListener('submit', function (e) {
                    e.preventDefault();

                    const fileInput = document.getElementById('memo-file');
                    const descriptionInput = document.getElementById('memo-description');
                    const uploadBtn = document.getElementById('uploadBtn');
                    const uploadBtnText = document.getElementById('uploadBtnText');
                    const uploadSpinner = document.getElementById('uploadSpinner');

                    if (!fileInput || !fileInput.files[0]) {
                        showUploadError('Please select a file to upload.');
                        return;
                    }

                    if (!currentApplicationId) {
                        showUploadError('Invalid application reference.');
                        return;
                    }

                    if (uploadBtn) {
                        uploadBtn.disabled = true;
                    }
                    if (uploadBtnText) {
                        uploadBtnText.textContent = 'Uploading...';
                    }
                    if (uploadSpinner) {
                        uploadSpinner.classList.remove('hidden');
                    }

                    const formData = new FormData();
                    formData.append('memo_file', fileInput.files[0]);
                    formData.append('description', descriptionInput ? descriptionInput.value : '');
                    formData.append('application_id', currentApplicationId);
                    formData.append('file_no', currentFileNo || '');
                    formData.append('memo_type', currentMemoType || 'sua');

                    const csrfToken = document.querySelector('meta[name="csrf-token"]');
                    if (csrfToken) {
                        formData.append('_token', csrfToken.getAttribute('content'));
                    }

                    fetch('/programmes/upload-memo', {
                        method: 'POST',
                        body: formData,
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    })
                        .then(async response => {
                            const data = await response.json().catch(() => ({ success: false, message: 'Upload failed.' }));
                            if (!response.ok || !data.success) {
                                throw new Error(data.message || 'Upload failed.');
                            }

                            Swal.fire({
                                title: 'Success!',
                                text: data.message || 'Memo uploaded successfully.',
                                icon: 'success',
                                confirmButtonColor: '#10B981',
                                customClass: { popup: 'rounded-lg' }
                            }).then(() => {
                                closeUploadModal();
                                window.location.reload();
                            });
                        })
                        .catch(error => {
                            console.error('Upload error:', error);
                            showUploadError(error.message || 'An error occurred during upload. Please try again.');
                        })
                        .finally(() => {
                            if (uploadBtn) {
                                uploadBtn.disabled = false;
                            }
                            if (uploadBtnText) {
                                uploadBtnText.textContent = 'Upload Edited ST Memo';
                            }
                            if (uploadSpinner) {
                                uploadSpinner.classList.add('hidden');
                            }
                        });
                });
            }

            const dropZone = document.querySelector('#uploadMemoModal .border-dashed');
            if (dropZone) {
                ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
                    dropZone.addEventListener(eventName, preventDefaults, false);
                });

                ['dragenter', 'dragover'].forEach(eventName => {
                    dropZone.addEventListener(eventName, highlight, false);
                });

                ['dragleave', 'drop'].forEach(eventName => {
                    dropZone.addEventListener(eventName, unhighlight, false);
                });

                dropZone.addEventListener('drop', handleDrop, false);
            }
        });
    </script>
@endsection