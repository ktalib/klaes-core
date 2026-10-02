@extends('layouts.app')

@section('content')
@push('styles')
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/jquery.dataTables.min.css">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="{{ asset('css/global-fileno-modal.css') }}">
    <style>
        .dataTables_wrapper .dataTables_filter input {
            border: 1px solid #e2e8f0;
            border-radius: 0.75rem;
            padding: 0.5rem 1rem;
            margin-left: 0.5rem;
        }
        .dataTables_wrapper .dataTables_length select {
            border: 1px solid #e2e8f0;
            border-radius: 0.75rem;
            padding: 0.25rem 2rem 0.25rem 0.5rem;
        }
        /* Disabled link styles */
        .disabled-link {
            opacity: 0.5;
            cursor: not-allowed !important;
            pointer-events: none;
        }
        .disabled-icon {
            color: #94a3b8;
        }
    </style>
@endpush
@push('scripts')
    <script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="{{ asset('js/global-fileno-modal.js') }}"></script>
@endpush

<div class="flex-1 overflow-auto bg-slate-50/60" x-data="{}">
    @include('admin.header')
 
    <div class="py-12 bg-slate-50 min-h-screen">
        <div class="max-w-[95%] mx-auto px-4 sm:px-6 lg:px-8">
            <!-- Page Header -->
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-8">
                <div>
                    <h1 class="text-3xl font-extrabold text-slate-900 tracking-tight">Conversion Applications</h1>
                    <p class="text-slate-500 text-sm mt-1">Joint Site Inspection Reports for Conversion Files</p>
                </div>
                <div>
                    @if(!($isPPDirector ?? false))
                        <button onclick="openNewJsiReport()" class="inline-flex items-center gap-2 px-5 py-2.5 bg-emerald-600 text-white font-bold rounded-xl hover:bg-emerald-700 transition-all shadow-lg shadow-emerald-100">
                            <i data-lucide="clipboard-plus" class="h-4 w-4"></i>
                            Enter JSI Report Details
                        </button>
                    @endif
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-4 mb-6">
                <div class="bg-white rounded-xl border border-slate-200 p-4">
                    <p class="text-[11px] font-bold uppercase tracking-wider text-slate-500">Total Reports</p>
                    <p id="statTotalReports" class="text-2xl font-black text-slate-900 mt-2">{{ $stats['total_reports'] ?? 0 }}</p>
                </div>
                <div class="bg-white rounded-xl border border-teal-200 p-4">
                    <p class="text-[11px] font-bold uppercase tracking-wider text-teal-600">Obtainable</p>
                    <p id="statObtainableReports" class="text-2xl font-black text-teal-700 mt-2">{{ $stats['obtainable_reports'] ?? 0 }}</p>
                </div>
                <div class="bg-white rounded-xl border border-rose-200 p-4">
                    <p class="text-[11px] font-bold uppercase tracking-wider text-rose-600">Not Obtainable</p>
                    <p id="statNotObtainableReports" class="text-2xl font-black text-rose-700 mt-2">{{ $stats['not_obtainable_reports'] ?? 0 }}</p>
                </div>
            </div>

            <!-- Filters Section -->
            <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6 mb-6 hidden">
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <!-- Land Use Type Filter -->
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-2 uppercase tracking-wider">Land Use Type</label>
                        <select id="landUseFilter" class="w-full px-4 py-2.5 bg-white border border-slate-200 rounded-xl text-sm focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 outline-none transition-all">
                            <option value="">All Types</option>
                            @foreach($landUseTypes as $type)
                                <option value="{{ $type->name }}">{{ $type->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <!-- District Filter -->
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-2 uppercase tracking-wider">District</label>
                        <select id="districtFilter" class="w-full px-4 py-2.5 bg-white border border-slate-200 rounded-xl text-sm focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 outline-none transition-all">
                            <option value="">All Districts</option>
                            @foreach($districts as $district)
                                <option value="{{ $district->name }}">{{ $district->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <!-- LGA Filter -->
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-2 uppercase tracking-wider">LGA</label>
                        <select id="lgaFilter" class="w-full px-4 py-2.5 bg-white border border-slate-200 rounded-xl text-sm focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 outline-none transition-all">
                            <option value="">All LGAs</option>
                            @foreach($lgas as $lga)
                                <option value="{{ $lga->name }}">{{ $lga->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <!-- Registry Filter -->
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-2 uppercase tracking-wider">Registry</label>
                        <select id="registryFilter" class="w-full px-4 py-2.5 bg-white border border-slate-200 rounded-xl text-sm focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 outline-none transition-all">
                            <option value="">All Registries</option>
                            @foreach($registries as $registry)
                                <option value="{{ $registry->name }}">{{ $registry->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <!-- Filter Actions -->
                <div class="flex gap-3 mt-4">
                    <button id="applyFilters" class="inline-flex items-center gap-2 px-6 py-2.5 bg-emerald-600 text-white font-bold rounded-xl hover:bg-emerald-700 transition-all shadow-lg shadow-emerald-100">
                        <i data-lucide="filter" class="h-4 w-4"></i>
                        Apply Filters
                    </button>
                    <button id="resetFilters" class="inline-flex items-center gap-2 px-6 py-2.5 bg-slate-100 text-slate-700 font-bold rounded-xl hover:bg-slate-200 transition-all">
                        <i data-lucide="x-circle" class="h-4 w-4"></i>
                        Reset
                    </button>
                </div>
            </div>

            <!-- JSI Reports Table -->
            <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
                <div class="bg-slate-50 px-6 py-4 border-b border-slate-200">
                    <h3 class="font-bold text-slate-800">Joint Site Inspection Reports</h3>
                    <p class="text-xs text-slate-500 mt-1">Conversion Applications – JSI report records</p>
                </div>
                <div class="overflow-x-auto p-6">
                    <table id="conversionFilesTable" class="w-full text-left border-collapse display nowrap" style="width:100%">
                        <thead>
                            <tr class="bg-slate-50/50 border-b border-slate-200 text-[10px] font-black text-slate-500 uppercase tracking-widest">
                                <th class="px-4 py-3">File Number</th>
                                <th class="px-4 py-3">Applicant</th>
                                <th class="px-4 py-3">Applied Land Use</th>
                                <th class="px-4 py-3">TPNo</th>
                                <th class="px-4 py-3">LKN No</th>
                                <th class="px-4 py-3">Plot No</th>
                                <th class="px-4 py-3">Location</th>
                                <th class="px-4 py-3">Inspection Date</th>
                                <th class="px-4 py-3">Recommended Site Measurement</th>
                                <th class="px-4 py-3">Existing Site Measurement</th>
                                <th class="px-4 py-3">Compliance</th>
                                <th class="px-4 py-3">JSI Status</th>
                                <th class="px-4 py-3">Created</th>
                                <th class="px-4 py-3 text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-sm text-slate-600">
                            <!-- DataTable will populate this -->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Include Joint Site Inspection Modal -->
    @include('programmes.partials.joint_site_inspection_modal', ['renderJointInspectionModalMarkup' => true])
</div>

<!-- Include Joint Site Inspection JavaScript -->
@include('programmes.partials.joint_site_inspection_js')

<script>
    @php
        $jsiPurposesFlat = collect($jsiPurposesByLandUse ?? [])->flatten(1)->values();
    @endphp
    window.jsiDropdownData = {
        landUseTypes: @json($landUseTypes->map(fn($t) => ['id' => $t->id, 'name' => $t->name])) ?? [],
        inspectors: @json($inspectors->map(fn($i) => ['id' => $i->id, 'name' => $i->name, 'rank' => $i->rank])) ?? [],
        jsiDetailedLandUses: @json(collect($jsiDetailedLandUses ?? [])->map(fn($l) => ['id' => $l->id, 'name' => $l->name])->values()),
        jsiPurposes: @json($jsiPurposesFlat->map(fn($p) => ['id' => $p->id, 'land_use_id' => $p->land_use_id, 'name' => $p->name])->values()),
        jsiRoadCategories: @json(collect($jsiRoadCategories ?? [])->map(fn($r) => ['id' => $r->id, 'name' => $r->name])->values()),
        jsiReservations: @json(collect($jsiReservations ?? [])->map(fn($r) => ['id' => $r->id, 'name' => $r->name])->values())
    };

    // Initialize Global File Number Modal for conversion file number selection
    document.addEventListener('DOMContentLoaded', function() {
        if (window.GlobalFileNoModal) {
            window.GlobalFileNoModal.init();
        }
    });
</script>

<script>
$(document).ready(function() {
    /*
     | Two separate questions, both of which must be yes before the Approve/Decline item is
     | offered: isPPDirector is the OFFICE, canDo(...,'approve') is whether this account was
     | granted approval on the module at all. Holding the post does not imply the grant.
     |
     | The action menu is built in JavaScript, so the answer travels here rather than through
     | a blade guard. The endpoint is gated independently by the route middleware.
    */
    const canApproveJsi = @json(auth()->check() && auth()->user()->canDo('Planning Recommendation', 'approve'));
    const isPPDirector = @json($isPPDirector ?? false) && canApproveJsi;

    function setStatsValue(id, value) {
        const el = document.getElementById(id);
        if (!el) return;
        const num = Number(value || 0);
        el.textContent = Number.isFinite(num) ? num.toLocaleString() : '0';
    }

    function updateStatsCards(summary) {
        if (!summary || typeof summary !== 'object') {
            return;
        }

        setStatsValue('statTotalReports', summary.total_reports);
        setStatsValue('statObtainableReports', summary.obtainable_reports);
        setStatsValue('statNotObtainableReports', summary.not_obtainable_reports);
    }

    // Initialize DataTable
    var table = $('#conversionFilesTable').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: "{{ route('physical-planning.regular.planning-recommendation.data') }}",
            type: 'GET',
            data: function(d) {
                d.land_use_type = $('#landUseFilter').val();
                d.district = $('#districtFilter').val();
                d.lga = $('#lgaFilter').val();
                d.registry = $('#registryFilter').val();
            },
            dataSrc: function(json) {
                updateStatsCards(json && json.summary ? json.summary : null);
                return json && json.data ? json.data : [];
            }
        },
        columns: [
            {
                // 0 – File Number
                data: 'file_number',
                render: function(data) {
                    return '<span class="font-mono font-bold text-emerald-600 text-xs">' + data + '</span>';
                }
            },
            {
                // 1 – Applicant
                data: 'applicant_name',
                render: function(data) {
                    var text = data && data !== 'N/A' ? (data.length > 35 ? data.substring(0, 35) + '…' : data) : 'N/A';
                    return '<span class="font-medium text-slate-700">' + text + '</span>';
                }
            },
            {
                // 2 – Applied Land Use
                data: 'applied_land_use',
                render: function(data) {
                    if (!data || data === 'N/A') return '<span class="text-xs text-slate-400">N/A</span>';
                    var colors = {
                        'Residential': 'bg-blue-50 text-blue-700 border-blue-100',
                        'Commercial':  'bg-purple-50 text-purple-700 border-purple-100',
                        'Industrial':  'bg-orange-50 text-orange-700 border-orange-100',
                        'Agricultural':'bg-green-50 text-green-700 border-green-100'
                    };
                    var cls = colors[data] || 'bg-slate-50 text-slate-700 border-slate-100';
                    return '<span class="inline-flex px-2 py-0.5 rounded-md text-[10px] font-bold border ' + cls + '">' + data + '</span>';
                }
            },
            {
                // 3 – TPNo
                data: 'tp_no',
                render: function(data) {
                    return '<span class="font-mono text-xs">' + (data || 'N/A') + '</span>';
                }
            },
            {
                // 4 – LKN No
                data: 'lkn_number',
                render: function(data) {
                    return '<span class="font-mono text-xs">' + (data || 'N/A') + '</span>';
                }
            },
            {
                // 5 – Plot No
                data: 'plot_number',
                render: function(data) {
                    return '<span class="font-mono text-xs">' + (data || 'N/A') + '</span>';
                }
            },
            {
                // 6 – Location
                data: 'location',
                render: function(data) {
                    var text = data && data !== 'N/A' ? (data.length > 30 ? data.substring(0, 30) + '…' : data) : 'N/A';
                    return '<span class="text-xs">' + text + '</span>';
                }
            },
            {
                // 7 – Inspection Date
                data: 'inspection_date',
                render: function(data) {
                    if (!data) return '<span class="text-[10px] text-slate-400">Not set</span>';
                    var d = new Date(data);
                    return '<span class="text-[10px] text-slate-600">' + d.toLocaleDateString() + '</span>';
                }
            },
            {
                // 8 – Recommended Site Measurement
                data: 'recommended_site_area',
                render: function(data) {
                    if (data === null || data === undefined || data === '' || data === 'N/A') {
                        return '<span class="text-[10px] text-slate-400">N/A</span>';
                    }
                    var value = parseFloat(data);
                    if (!Number.isFinite(value)) {
                        return '<span class="text-[10px] text-slate-400">N/A</span>';
                    }
                    return '<span class="text-[10px] font-semibold text-slate-700">' + value.toLocaleString(undefined, {maximumFractionDigits: 2}) + ' m²</span>';
                }
            },
            {
                // 9 – Existing Site Measurement
                data: 'existing_site_area',
                render: function(data) {
                    if (data === null || data === undefined || data === '' || data === 'N/A') {
                        return '<span class="text-[10px] text-slate-400">N/A</span>';
                    }
                    var value = parseFloat(data);
                    if (!Number.isFinite(value)) {
                        return '<span class="text-[10px] text-slate-400">N/A</span>';
                    }
                    return '<span class="text-[10px] font-semibold text-slate-700">' + value.toLocaleString(undefined, {maximumFractionDigits: 2}) + ' m²</span>';
                }
            },
            {
                // 10 – Compliance
                data: 'compliance_status',
                render: function(data) {
                    if (!data || data === 'N/A') return '<span class="text-[10px] text-slate-400">Pending</span>';
                    var cls = 'bg-slate-50 text-slate-600 border-slate-200';
                    if (data.toLowerCase().includes('comply') || data.toLowerCase().includes('conform')) {
                        cls = 'bg-emerald-50 text-emerald-700 border-emerald-100';
                    } else if (data.toLowerCase().includes('not') || data.toLowerCase().includes('non')) {
                        cls = 'bg-red-50 text-red-700 border-red-100';
                    }
                    return '<span class="inline-flex px-2 py-0.5 rounded-md text-[10px] font-bold border ' + cls + '">' + data + '</span>';
                }
            },
            {
                // 11 – JSI Status
                data: null,
                render: function(data, type, row) {
                    var status = row.jsi_status_code || 'pending';
                    var label  = row.jsi_status_label || 'Pending';
                    var classes = {
                        approved:  'bg-emerald-50 text-emerald-700 border-emerald-100',
                        pending:   'bg-slate-50 text-slate-600 border-slate-200'
                    };
                    var badgeCls = classes[status] || classes.pending;
                    return '<div class="space-y-1">' +
                        '<span class="inline-flex px-2 py-0.5 rounded-md text-[10px] font-bold border ' + badgeCls + '">' + label + '</span>' +
                    '</div>';
                }
            },
            {
                // 12 – Created
                data: 'created_at',
                render: function(data) {
                    if (!data) return '<span class="text-[10px] text-slate-400">N/A</span>';
                    var d = new Date(data);
                    return '<span class="text-[10px] text-slate-500">' + d.toLocaleDateString() + '</span>';
                }
            },
            {
                // 13 – Actions dropdown
                data: null,
                orderable: false,
                searchable: false,
                render: function(data, type, row) {
                    var rowId = row.application_id || row.id;
                    var actionItems = isPPDirector
                        ? `
                            <button onclick="openApprovalWorkbench(${rowId})" @click="open = false"
                                class="w-full px-4 py-2 text-left text-xs font-bold text-slate-700 hover:bg-emerald-50 hover:text-emerald-700 flex items-center gap-2 transition-colors">
                                <i data-lucide="check-check" class="h-3.5 w-3.5"></i>
                                Approve/Decline JSI Report
                            </button>
                        `
                        : `
                            <button onclick="viewInspectionDetails(${rowId})" @click="open = false"
                                class="w-full px-4 py-2 text-left text-xs font-bold text-slate-700 hover:bg-indigo-50 hover:text-indigo-600 flex items-center gap-2 transition-colors">
                                <i data-lucide="layout-dashboard" class="h-3.5 w-3.5"></i>
                                View Inspection Details
                            </button>
                            <button onclick="editInspectionDetails(${rowId})" @click="open = false"
                                class="w-full px-4 py-2 text-left text-xs font-bold text-slate-700 hover:bg-amber-50 hover:text-amber-700 flex items-center gap-2 transition-colors">
                                <i data-lucide="square-pen" class="h-3.5 w-3.5"></i>
                                Edit Inspection Details
                            </button>
                        `;

                    return `
                        <div class="flex items-center justify-center">
                            <div class="relative inline-block" x-data="{ open: false }">
                                <button @click="open = !open" @click.away="open = false" class="p-1.5 hover:bg-slate-100 rounded-md transition-colors">
                                    <i data-lucide="more-vertical" class="h-4 w-4 text-slate-400"></i>
                                </button>
                                <div x-show="open"
                                     x-transition:enter="transition ease-out duration-100"
                                     x-transition:enter-start="transform opacity-0 scale-95"
                                     x-transition:enter-end="transform opacity-100 scale-100"
                                     class="absolute right-0 top-full mt-1 w-56 bg-white border border-slate-200 rounded-lg shadow-xl z-50 overflow-hidden">
                                    ${actionItems}
                                </div>
                            </div>
                        </div>
                    `;
                }
            }
        ],
        order: [[12, 'desc']], // Order by Created column
        pageLength: 25,
        lengthMenu: [[10, 25, 50, 100], [10, 25, 50, 100]],
        language: {
            processing: '<div class="flex items-center gap-2"><div class="animate-spin rounded-full h-4 w-4 border-b-2 border-emerald-600"></div><span>Loading...</span></div>',
            emptyTable: "No conversion files found",
            zeroRecords: "No matching conversion files found"
        },
        drawCallback: function() {
            // Re-initialize Lucide icons after DataTable draws
            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }
        }
    });
  

    // Apply filters button
    $('#applyFilters').on('click', function() {
        table.ajax.reload();
    });

    // Reset filters button
    $('#resetFilters').on('click', function() {
        $('#landUseFilter').val('');
        $('#districtFilter').val('');
        $('#lgaFilter').val('');
        $('#registryFilter').val('');
        table.ajax.reload();
    });

    // Initialize Select2 for filters
    $('#landUseFilter, #districtFilter, #lgaFilter, #registryFilter').select2({
        minimumResultsForSearch: 5,
        width: '100%'
    });
});



// View/Edit inspection details
function viewInspectionDetails(fileId) {
    if (typeof window.openJointInspectionModal === 'function') {
        window.openJointInspectionModal(fileId, null, {
            isConversionFile: true,
            mode: 'view'
        });
    } else {
        console.error('Joint Inspection Modal not loaded');
        Swal.fire({ icon: 'error', title: 'Error', text: 'Inspection modal is not available. Please refresh the page.' });
    }
}

function editInspectionDetails(applicationId) {
    if (!applicationId) {
        Swal.fire({ icon: 'error', title: 'Error', text: 'Application ID is missing.' });
        return;
    }

    if (typeof window.openJointInspectionModal === 'function') {
        window.openJointInspectionModal(applicationId, null, {
            isConversionFile: true
        });
    } else {
        Swal.fire({ icon: 'error', title: 'Error', text: 'Inspection modal is not available. Please refresh the page.' });
    }
}

// Approve JSI report for conversion application
function approveInspectionReport(applicationId) {
    if (!applicationId) {
        Swal.fire({ icon: 'error', title: 'Error', text: 'Application ID is missing.' });
        return;
    }

    const approveUrl = "{{ route('planning-recommendation.joint-inspection.approve') }}";

    Swal.fire({
        title: 'Approve Joint Site Inspection?',
        text: 'This will mark the JSI report as approved.',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Yes, approve',
        cancelButtonText: 'Cancel'
    }).then((result) => {
        if (!result.isConfirmed) {
            return;
        }

        fetch(approveUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': "{{ csrf_token() }}"
            },
            body: JSON.stringify({
                application_id: applicationId
            })
        })
        .then(async (response) => {
            const data = await response.json();
            if (!response.ok || !data.success) {
                throw new Error(data.message || 'Failed to approve Joint Site Inspection report.');
            }

            Swal.fire({ icon: 'success', title: 'Approved', text: data.message || 'Joint Site Inspection report approved successfully.' });
            if ($.fn.DataTable.isDataTable('#conversionFilesTable')) {
                $('#conversionFilesTable').DataTable().ajax.reload(null, false);
            }
        })
        .catch((error) => {
            Swal.fire({ icon: 'error', title: 'Error', text: error.message || 'An error occurred while approving the report.' });
        });
    });
}

// Print JSI report
function printInspectionReport(applicationId) {
    if (!applicationId) {
        Swal.fire({ icon: 'error', title: 'Error', text: 'Application ID is missing.' });
        return;
    }

    const showRouteTemplate = "{{ route('planning-recommendation.joint-inspection.show', ['application' => '__APP_ID__']) }}";
    const reportUrl = showRouteTemplate.replace('__APP_ID__', encodeURIComponent(applicationId)) + '?print=1';
    window.open(reportUrl, '_blank');
}

function printRecommendation(applicationId) {
    if (!applicationId) {
        Swal.fire({ icon: 'error', title: 'Error', text: 'Application ID is missing.' });
        return;
    }

    const recommendationRouteTemplate = "{{ route('physical-planning.conversion.recommendation.print', ['applicationId' => '__APP_ID__']) }}";
    const recommendationUrl = recommendationRouteTemplate.replace('__APP_ID__', encodeURIComponent(applicationId));
    window.open(recommendationUrl, '_blank');
}

function printScheduleOfPayment(applicationId) {
    if (!applicationId) {
        Swal.fire({ icon: 'error', title: 'Error', text: 'Application ID is missing.' });
        return;
    }

    const scheduleRouteTemplate = "{{ route('physical-planning.conversion.schedule-of-payment.print', ['applicationId' => '__APP_ID__']) }}";
    const scheduleUrl = scheduleRouteTemplate.replace('__APP_ID__', encodeURIComponent(applicationId));
    window.open(scheduleUrl, '_blank');
}

function openApprovalWorkbench(applicationId) {
    if (!applicationId) {
        Swal.fire({ icon: 'error', title: 'Error', text: 'Application ID is missing.' });
        return;
    }

    const approvalRouteTemplate = "{{ route('physical-planning.regular.planning-recommendation.approval', ['applicationId' => '__APP_ID__']) }}";
    let approvalUrl = approvalRouteTemplate.replace('__APP_ID__', encodeURIComponent(applicationId));
    @if(($isPPDirector ?? false) === true)
        approvalUrl += '?role=pp_director';
    @endif

    window.location.href = approvalUrl;
}

// Open inspection modal for an existing row
function openInspectionModal(fileId, fileNumber) {
    if (typeof window.openJointInspectionModal === 'function') {
        window.openJointInspectionModal(fileId, null, {
            fileNumber: fileNumber,
            isConversionFile: true
        });
    } else {
        console.error('Joint Inspection Modal not loaded');
        Swal.fire({ icon: 'error', title: 'Error', text: 'Inspection modal is not available. Please refresh the page.' });
    }
}

// Open a new JSI Report (header button) – opens the modal with no pre-selected file
function openNewJsiReport() {
    if (typeof window.openJointInspectionModal === 'function') {
        window.openJointInspectionModal(null, null, {
            isConversionFile: true,
            source: 'Conversion Applications'
        });
    } else {
        console.error('Joint Inspection Modal not loaded');
        Swal.fire({ icon: 'error', title: 'Error', text: 'Inspection modal is not available. Please refresh the page.' });
    }
}

// Close modal on escape key
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        const modal = document.getElementById('jointInspectionModal');
        if (modal && !modal.classList.contains('hidden')) {
            if (typeof window.closeJointInspectionModal === 'function') {
                window.closeJointInspectionModal();
            }
        }
    }
});
</script>

@endsection
