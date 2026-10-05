@extends('layouts.app')
@section('page-title')
    {{$PageTitle}}
@endsection

{{-- Add DataTables CSS --}}
@push('styles')
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/jquery.dataTables.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.2.2/css/buttons.dataTables.min.css">
@endpush

@include('sectionaltitling.partials.assets.css')
@section('content')
    <!-- Main Content -->
    <div class="flex-1 overflow-auto">
        <!-- Header -->
        @include('admin.header')
        @include('exports.consolidated_report_action', ['reportKey' => 'st-fc'])
        <!-- Dashboard Content -->
        <div class="p-6">
            <!-- Final Conveyance Table -->
            <div class="bg-white rounded-md shadow-sm border border-gray-200 p-6">
                <div class="flex justify-between items-center mb-6">
                    <h2 class="text-xl font-bold">Generate Final Conveyance</h2>

                    <div class="flex items-center space-x-4">
                        <div class="relative">
                            <select id="statusFilter"
                                class="pl-4 pr-8 py-2 border border-gray-200 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 appearance-none">
                                <option value="All...">All...</option>
                                <option value="Generated">Generated</option>
                                <option value="Not Started">Not Generated</option>
                                <option value="Approved">Approved</option>
                                <option value="Pending">Pending</option>
                                <option value="Declined">Declined</option>
                            </select>
                            <i data-lucide="chevron-down"
                                class="absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-400 w-4 h-4"></i>
                        </div>

                        {{-- Export dropdown --}}
                        <div class="relative inline-block">
                            <button onclick="toggleExportDropdown(event)"
                                class="flex items-center space-x-2 px-4 py-2 border border-gray-200 rounded-md">
                                <i data-lucide="download" class="w-4 h-4 text-gray-600"></i>
                                <span>Export</span>
                                <i data-lucide="chevron-down" class="w-4 h-4 ml-2"></i>
                            </button>
                            <div id="exportDropdown"
                                class="hidden absolute right-0 mt-2 w-48 bg-white border border-gray-200 rounded-md shadow-lg z-50">
                                <button onclick="exportTable('excel')"
                                    class="w-full px-4 py-2 text-left hover:bg-gray-100 flex items-center">
                                    <i data-lucide="file-text" class="w-4 h-4 mr-2 text-green-600"></i>
                                    Export to Excel
                                </button>
                                <button onclick="exportTable('csv')"
                                    class="w-full px-4 py-2 text-left hover:bg-gray-100 flex items-center">
                                    <i data-lucide="file" class="w-4 h-4 mr-2 text-blue-600"></i>
                                    Export to CSV
                                </button>
                                <button onclick="exportTable('pdf')"
                                    class="w-full px-4 py-2 text-left hover:bg-gray-100 flex items-center">
                                    <i data-lucide="file-text" class="w-4 h-4 mr-2 text-red-600"></i>
                                    Export to PDF
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    {{-- DataTables Table --}}
                    <table id="conveyanceTable" class="min-w-full divide-y divide-gray-200 display nowrap"
                        style="width:100%">
                        <thead>
                            <tr class="text-xs">
                                <th class="table-header text-green-500">ST FileNo</th>
                                <th class="table-header text-green-500">MLSFileNo</th>
                                <th class="table-header text-green-500">Property Type</th>
                                <th class="table-header text-green-500">Land Use</th>
                                <th class="table-header text-green-500">Owner</th>
                                <th class="table-header text-green-500">Units</th>
                                <th class="table-header text-green-500">Director's Approval</th>
                                <th class="table-header text-green-500">ST Memo Status</th>
                                <th class="table-header text-green-500">Final Conveyance</th>
                                <th class="table-header text-green-500">Application Date</th>
                                <th class="table-header text-green-500">Date Captured</th>
                                <th class="table-header text-green-500">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @foreach ($PrimaryApplications as $PrimaryApplication)
                                <tr class="text-xs">

                                    <td class="table-cell">
                                        <div class="truncate max-w-[120px]" title="{{ $PrimaryApplication->np_fileno }}">
                                            {{ $PrimaryApplication->np_fileno }}
                                        </div>
                                    </td>
                                    <td class="table-cell">
                                        <div class="truncate max-w-[120px]" title="{{ $PrimaryApplication->fileno }}">
                                            {{ $PrimaryApplication->fileno }}
                                        </div>
                                    </td>
                                    <td class="table-cell">
                                        @php
                                            // Same normalisation as the ST applications list: the building type
                                            // is free text, so "Housing Estate", "SEMI DETACHED FLATS" and
                                            // "shopping mall and shops" all landed in one column. Display only —
                                            // the stored value is left as entered.
                                            $buildingType = collect([
                                                $PrimaryApplication->residential_type,
                                                $PrimaryApplication->commercial_type,
                                                $PrimaryApplication->industrial_type,
                                                $PrimaryApplication->mixed_type,
                                            ])->first(fn($value) => filled($value));
                                        @endphp
                                        {{ filled($buildingType) ? Str::of($buildingType)->lower()->title() : 'N/A' }}
                                    </td>
                                    <td class="table-cell">
                                        @if($PrimaryApplication->land_use)
                                            @php
                                                $landUseBadgeClass = match (strtolower($PrimaryApplication->land_use)) {
                                                    'residential' => 'bg-blue-100 text-blue-800 border-blue-200',
                                                    'commercial' => 'bg-green-100 text-green-800 border-green-200',
                                                    'industrial' => 'bg-red-100 text-red-800 border-red-200',
                                                    default => 'bg-gray-100 text-gray-800 border-gray-200'
                                                };
                                            @endphp
                                            <span
                                                class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium border {{ $landUseBadgeClass }}">
                                                {{ $PrimaryApplication->land_use }}
                                            </span>
                                        @else
                                            <span
                                                class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium border bg-gray-100 text-gray-800 border-gray-200">
                                                N/A
                                            </span>
                                        @endif
                                    </td>
                                    <td class="table-cell">
                                        <div class="flex items-center">
                                            <div
                                                class="w-10 h-10 rounded-full bg-gray-200 flex items-center justify-center mr-2">
                                                @if ($PrimaryApplication->passport)
                                                    <img src="{{ asset('storage/app/public/' . $PrimaryApplication->passport) }}"
                                                        alt="Passport"
                                                        class="w-full h-full rounded-full object-cover cursor-pointer"
                                                        onclick="showPassportPreview('{{ asset('storage/app/public/' . $PrimaryApplication->passport) }}', 'Owner Passport')">
                                                @elseif ($PrimaryApplication->multiple_owners_passport)
                                                    @php
                                                        $passports = json_decode($PrimaryApplication->multiple_owners_passport, true);
                                                        $firstPassport = $passports[0] ?? null;
                                                    @endphp
                                                    @if ($firstPassport)
                                                        <img src="{{ asset('storage/app/public/' . $firstPassport) }}" alt="Passport"
                                                            class="w-full h-full rounded-full object-cover cursor-pointer"
                                                            onclick="showMultipleOwners({{ $PrimaryApplication->multiple_owners_names }}, {{ $PrimaryApplication->multiple_owners_passport }})">
                                                    @endif
                                                @endif
                                            </div>
                                            <span class="truncate max-w-[120px]">
                                                @if ($PrimaryApplication->corporate_name)
                                                    {{ $PrimaryApplication->corporate_name }}
                                                @elseif($PrimaryApplication->multiple_owners_names)
                                                    @php
                                                        $ownerNames = json_decode(
                                                            $PrimaryApplication->multiple_owners_names,
                                                            true,
                                                        );
                                                        $firstOwner = $ownerNames[0] ?? 'Unknown Owner';
                                                    @endphp
                                                    {{ $firstOwner }}
                                                    <span class="ml-1 cursor-pointer text-blue-500"
                                                        onclick="showMultipleOwners({{ $PrimaryApplication->multiple_owners_names }}, {{ $PrimaryApplication->multiple_owners_passport }})">
                                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 inline" fill="none"
                                                            viewBox="0 0 24 24" stroke="currentColor">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                                d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                        </svg>
                                                    </span>
                                                @elseif($PrimaryApplication->first_name || $PrimaryApplication->surname)
                                                    {{ $PrimaryApplication->first_name }} {{ $PrimaryApplication->surname }}
                                                @else
                                                    Unknown Owner
                                                @endif
                                            </span>
                                        </div>
                                    </td>
                                    <td class="table-cell">
                                        @php
                                            $enteredUnitsCount = \DB::connection('sqlsrv')
                                                ->table('subapplications')
                                                ->where('main_application_id', $PrimaryApplication->id)
                                                ->count();
                                            $totalUnits = $PrimaryApplication->NoOfUnits ?? 0;
                                        @endphp
                                        @if($totalUnits > 0)
                                            <span class="badge badge-units">
                                                {{ $totalUnits }} Units
                                            </span>
                                        @else
                                            <span class="badge badge-primary">0 Units</span>
                                        @endif
                                    </td>
                                    <td class="table-cell capitalize">
                                        <div class="flex items-center">
                                            @php
                                                $directorStatus = $PrimaryApplication->application_status ?? '';
                                                $directorBadgeClass = match (strtolower($directorStatus)) {
                                                    'approved' => 'bg-green-100 text-green-800 border-green-200',
                                                    'pending' => 'bg-yellow-100 text-yellow-800 border-yellow-200',
                                                    'declined', 'rejected' => 'bg-red-100 text-red-800 border-red-200',
                                                    'in progress' => 'bg-blue-100 text-blue-800 border-blue-200',
                                                    default => 'bg-gray-100 text-gray-800 border-gray-200'
                                                };
                                            @endphp
                                            <span
                                                class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium border {{ $directorBadgeClass }}">
                                                {{ ucfirst(strtolower($directorStatus)) }}
                                            </span>
                                            @if(strtolower($directorStatus) == 'declined')
                                                <i data-lucide="info" class="w-4 h-4 ml-1 text-blue-500 cursor-pointer"
                                                    onclick="showDeclinedInfo(event, 'Application Status', {{ json_encode($PrimaryApplication->recomm_comments) }}, {{ json_encode($PrimaryApplication->director_comments) }})"></i>
                                            @endif
                                        </div>
                                    </td>
                                    <td class="table-cell capitalize">
                                        <div class="flex items-center">
                                            @php
                                                $stMemoStatus = $PrimaryApplication->st_memo_status ?? 'Not Generated';
                                                $stMemoBadgeClass = match (strtolower($stMemoStatus)) {
                                                    'generated' => 'bg-green-100 text-green-800 border-green-200',
                                                    'not generated' => 'bg-gray-100 text-gray-800 border-gray-200',
                                                    default => 'bg-gray-100 text-gray-800 border-gray-200'
                                                };
                                            @endphp
                                            <span
                                                class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium border {{ $stMemoBadgeClass }}">
                                                {{ ucfirst(strtolower($stMemoStatus)) }}
                                            </span>
                                            @if($PrimaryApplication->st_memo_comments)

                                            @endif
                                        </div>
                                    </td>
                                    <td class="table-cell capitalize">
                                        @php
                                            $finalStatusRaw = $PrimaryApplication->final_conveyance_status ?? null;
                                            if (!$finalStatusRaw && (int) ($PrimaryApplication->final_conveyance_generated ?? 0) === 1) {
                                                $finalStatusRaw = 'generated';
                                            }

                                            $finalStatus = $finalStatusRaw ? strtolower($finalStatusRaw) : 'not generated';
                                            $finalBadgeClass = match ($finalStatus) {
                                                'generated' => 'bg-green-100 text-green-800 border-green-200',
                                                'approved' => 'bg-blue-100 text-blue-800 border-blue-200',
                                                'pending' => 'bg-yellow-100 text-yellow-800 border-yellow-200',
                                                default => 'bg-gray-100 text-gray-800 border-gray-200'
                                            };

                                            $finalGeneratedAt = $PrimaryApplication->fc_generated_date
                                                ?? $PrimaryApplication->final_conveyance_generated_at
                                                ?? null;
                                        @endphp
                                        <div>
                                            <span
                                                class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium border {{ $finalBadgeClass }}">
                                                {{ $finalStatusRaw ? ucfirst($finalStatus) : 'Not Generated' }}
                                            </span>
                                            @if(!empty($finalGeneratedAt))
                                                <span class="block text-[10px] text-gray-500 mt-1">
                                                    {{ \Carbon\Carbon::parse($finalGeneratedAt)->format('Y-m-d H:i') }}
                                                </span>
                                            @endif
                                        </div>
                                    </td>
                                    <td class="table-cell">
                                        {{ \Carbon\Carbon::parse($PrimaryApplication->created_at)->format('Y-m-d') }}
                                    </td>
                                    <td class="table-cell">
                                        {{ \Carbon\Carbon::parse($PrimaryApplication->created_at)->format('Y-m-d H:i') }}
                                    </td>
                                    <td class="table-cell overflow-visible relative">
                                        @include('sectionaltitling.action_menu.conveyance_action')
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

            </div>
        </div>

        <!-- Footer -->
        @include('admin.footer')
    </div>

    @include('sectionaltitling.action_modals.eRegistry_modal')
    @include('sectionaltitling.action_modals.cofo_details_modal')
    @include('sectionaltitling.action_modals.final_conveyance_modal')

    <!-- Final Conveyance Upload Modal -->
    <div id="finalConveyanceUploadModal" class="fixed inset-0 z-50 hidden">
        <div class="absolute inset-0 bg-black bg-opacity-50" data-close-final-upload-modal></div>
        <div class="relative mx-auto mt-20 w-full max-w-xl rounded-lg bg-white p-6 shadow-2xl">
            <div class="flex items-center justify-between">
                <div>
                    <h3 class="text-lg font-semibold text-gray-900">Upload Edited Final Conveyance</h3>
                    <p class="text-sm text-gray-500">
                        Upload a PDF document for <span class="font-semibold" id="finalConveyanceUploadFileNo">—</span>
                    </p>
                </div>
                <button type="button" class="text-gray-400 hover:text-gray-600" data-close-final-upload-modal>
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24"
                        stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <form id="finalConveyanceUploadForm" class="mt-4 space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Select Document</label>
                    <div class="rounded-md border-2 border-dashed border-gray-300 p-4 text-center">
                        <input type="file" id="finalConveyanceUploadFile" class="hidden" accept=".pdf">
                        <button type="button" id="triggerFinalConveyanceUploadFile"
                            class="rounded-md bg-green-600 px-3 py-2 text-sm font-semibold text-white hover:bg-green-700">
                            Choose File
                        </button>
                        <p class="mt-2 text-xs text-gray-500">Accepted format: .pdf (max 15MB).</p>
                        <div id="finalConveyanceUploadPreview" class="mt-3 hidden text-left text-sm text-gray-700">
                            <p class="font-medium" id="finalConveyanceUploadFileName"></p>
                            <p class="text-xs text-gray-500" id="finalConveyanceUploadFileSize"></p>
                        </div>
                    </div>
                </div>
                <div id="finalConveyanceUploadError"
                    class="hidden rounded-md border border-red-200 bg-red-50 p-3 text-sm text-red-700">
                    <span id="finalConveyanceUploadErrorText"></span>
                </div>
                <div>
                    <label for="finalConveyanceUploadDescription"
                        class="block text-sm font-medium text-gray-700 mb-2">Description (optional)</label>
                    <textarea id="finalConveyanceUploadDescription" rows="3"
                        class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-green-600 focus:outline-none focus:ring-1 focus:ring-green-600"
                        placeholder="Add any notes about this document."></textarea>
                </div>
                <div class="flex items-center justify-end gap-3 pt-2">
                    <button type="button"
                        class="rounded-md border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
                        data-close-final-upload-modal>
                        Cancel
                    </button>
                    <button type="submit" id="finalConveyanceUploadBtn"
                        class="rounded-md bg-green-600 px-4 py-2 text-sm font-semibold text-white disabled:cursor-not-allowed disabled:opacity-60"
                        disabled>
                        Upload Document
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            // Simple filter functionality that works with DataTables
            const statusFilter = document.getElementById('statusFilter');
            if (statusFilter) {
                statusFilter.addEventListener('change', function () {
                    // This will be handled by the DataTables initialization
                });
            }
        });

        function toggleDropdown(event) {
            event.stopPropagation();
            const dropdownMenu = event.currentTarget.nextElementSibling;
            if (dropdownMenu) {
                dropdownMenu.classList.toggle('hidden');
            }
        }

        document.addEventListener('click', () => {
            const dropdownMenus = document.querySelectorAll('.dropdown-menu');
            dropdownMenus.forEach(menu => menu.classList.add('hidden'));
        });



        function showPassportPreview(imageSrc, title) {
            Swal.fire({
                title: title,
                html: `<img src="${imageSrc}" class="img-fluid" style="max-height: 400px;">`,
                width: 'auto',
                showCloseButton: true,
                showConfirmButton: false
            });
        }

        function showMultipleOwners(owners, passports) {
            if (Array.isArray(owners) && owners.length > 0) {
                let htmlContent = '<div class="grid grid-cols-3 gap-4" style="max-width: 600px;">';

                owners.forEach((name, index) => {
                    const passport = Array.isArray(passports) && passports[index]
                        ? `<img src="{{ asset('storage/app/public/') }}/${passports[index]}" 
                                                                                 class="w-24 h-32 object-cover mx-auto border-2 border-gray-300" 
                                                                                 style="object-position: center top;">`
                        : '<div class="w-24 h-32 bg-gray-300 mx-auto flex items-center justify-center"><span>No Image</span></div>';

                    htmlContent += `
                                                                            <div class="flex flex-col items-center">
                                                                                <div class="passport-container bg-blue-50 p-2 rounded">
                                                                                    ${passport}
                                                                                    <p class="text-center text-sm font-medium mt-1">${name}</p>
                                                                                </div>
                                                                            </div>
                                                                        `;
                });

                htmlContent += '</div>';

                Swal.fire({
                    title: 'Multiple Owners',
                    html: htmlContent,
                    width: 'auto',
                    showCloseButton: true,
                    showConfirmButton: false
                });
            } else {
                Swal.fire({
                    title: 'Multiple Owners',
                    text: 'No owners available',
                    icon: 'info',
                    confirmButtonText: 'Close'
                });
            }
        }

        function showDeclinedInfo(event, title, recommComments, directorComments) {
            event.stopPropagation();

            let htmlContent = '<div class="text-left">';
            if (recommComments) {
                htmlContent += `
                                                                        <div class="mb-3">
                                                                            <h3 class="font-bold text-gray-700">Recommendation Comments:</h3>
                                                                            <p class="text-gray-600 mt-1 p-2 bg-gray-100 rounded">${recommComments}</p>
                                                                        </div>
                                                                    `;
            }

            if (directorComments) {
                htmlContent += `
                                                                        <div>
                                                                            <h3 class="font-bold text-gray-700">Director Comments:</h3>
                                                                            <p class="text-gray-600 mt-1 p-2 bg-gray-100 rounded">${directorComments}</p>
                                                                        </div>
                                                                    `;
            }

            if (!recommComments && !directorComments) {
                htmlContent += '<p>No comments available.</p>';
            }

            htmlContent += '</div>';

            Swal.fire({
                title: `Declined: ${title}`,
                html: htmlContent,
                icon: 'info',
                width: 'auto',
                showCloseButton: true,
                showConfirmButton: true,
                confirmButtonText: 'Close'
            });
        }

        // Final Conveyance Modal Functions
        let currentApplicationId = null;
        let editingBuyerId = null;

        function showFinalConveyanceModal(applicationId) {
            currentApplicationId = applicationId;
            const modal = document.getElementById('finalConveyanceModal');
            const loadingState = document.getElementById('loadingState');
            const tabsContainer = document.getElementById('tabsContainer');
            const generateButton = document.getElementById('generateButton');

            // Show modal and loading state
            modal.style.display = 'block';
            loadingState.classList.remove('hidden');
            tabsContainer.classList.add('hidden');
            generateButton.classList.add('hidden');

            // Reset tabs to Generate tab
            switchTab('generate');

            // Fetch application information
            fetch(`/final-conveyance/info/${applicationId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Populate modal with application data
                        document.getElementById('stFileNo').textContent = data.data.np_file_no || 'N/A';
                        document.getElementById('mlsFileNo').textContent = data.data.file_no || 'N/A';
                        document.getElementById('applicantName').textContent = data.data.applicant_name || 'N/A';
                        document.getElementById('landUse').textContent = data.data.land_use || 'N/A';
                        document.getElementById('propertyLocation').textContent = data.data.property_location || 'N/A';
                        document.getElementById('unitsCount').textContent = data.data.units_count || '0 Units';

                        // Hide loading, show tabs and button
                        loadingState.classList.add('hidden');
                        tabsContainer.classList.remove('hidden');
                        generateButton.classList.remove('hidden');

                        // Populate titles dropdown
                        populateTitlesDropdown(data.data.titles || []);

                        // Load buyers for the Buyers tab
                        loadBuyers(applicationId);
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: data.message || 'Failed to load application information'
                        });
                        closeFinalConveyanceModal();
                    }
                })
                .catch(error => {
                    console.error('Error fetching application info:', error);
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: 'Failed to load application information'
                    });
                    closeFinalConveyanceModal();
                });
        }

        function populateTitlesDropdown(titles) {
            const editTitleSelect = document.getElementById('editBuyerTitle');

            if (editTitleSelect) {
                // Clear existing options except the first one
                editTitleSelect.innerHTML = '<option value="">Select Title</option>';

                // Add titles from database
                titles.forEach(title => {
                    const option = document.createElement('option');
                    option.value = title.title;
                    option.textContent = title.display_name || title.title;
                    editTitleSelect.appendChild(option);
                });
            }
        }

        function switchTab(tabName) {
            // Update tab buttons
            document.getElementById('generateTab').classList.remove('border-blue-500', 'text-blue-600');
            document.getElementById('generateTab').classList.add('border-transparent', 'text-gray-500');
            document.getElementById('buyersTab').classList.remove('border-blue-500', 'text-blue-600');
            document.getElementById('buyersTab').classList.add('border-transparent', 'text-gray-500');

            // Update tab content
            document.getElementById('generateTabContent').classList.add('hidden');
            document.getElementById('buyersTabContent').classList.add('hidden');

            if (tabName === 'generate') {
                document.getElementById('generateTab').classList.remove('border-transparent', 'text-gray-500');
                document.getElementById('generateTab').classList.add('border-blue-500', 'text-blue-600');
                document.getElementById('generateTabContent').classList.remove('hidden');
                document.getElementById('generateButton').classList.remove('hidden');
            } else if (tabName === 'buyers') {
                document.getElementById('buyersTab').classList.remove('border-transparent', 'text-gray-500');
                document.getElementById('buyersTab').classList.add('border-blue-500', 'text-blue-600');
                document.getElementById('buyersTabContent').classList.remove('hidden');
                document.getElementById('generateButton').classList.add('hidden');
            }
        }

        function closeFinalConveyanceModal() {
            const modal = document.getElementById('finalConveyanceModal');
            modal.style.display = 'none';
            currentApplicationId = null;
            editingBuyerId = null;

            // Close edit modal if open
            closeEditBuyerModal();

            // Reset button state
            const generateButton = document.getElementById('generateButton');
            const generateButtonText = document.getElementById('generateButtonText');
            const generateButtonSpinner = document.getElementById('generateButtonSpinner');

            generateButton.disabled = false;
            generateButtonText.textContent = 'Generate';
            generateButtonSpinner.style.display = 'none';
        }

        function generateFinalConveyance() {
            if (!currentApplicationId) {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'No application selected'
                });
                return;
            }

            const generateButton = document.getElementById('generateButton');
            const generateButtonText = document.getElementById('generateButtonText');
            const generateButtonSpinner = document.getElementById('generateButtonSpinner');

            // Disable button and show loading
            generateButton.disabled = true;
            generateButtonText.textContent = 'Generating...';
            generateButtonSpinner.style.display = 'inline-block';

            // Send generation request
            fetch('/final-conveyance/generate', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                },
                body: JSON.stringify({
                    application_id: currentApplicationId
                })
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Success!',
                            text: data.message || 'Final Conveyance generated successfully',
                            showConfirmButton: true,
                            confirmButtonText: 'OK'
                        }).then(() => {
                            // Close modal and refresh page
                            closeFinalConveyanceModal();
                            location.reload();
                        });
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: data.message || 'Failed to generate Final Conveyance'
                        });

                        // Reset button state
                        generateButton.disabled = false;
                        generateButtonText.textContent = 'Generate';
                        generateButtonSpinner.style.display = 'none';
                    }
                })
                .catch(error => {
                    console.error('Error generating final conveyance:', error);
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: 'Failed to generate Final Conveyance'
                    });

                    // Reset button state
                    generateButton.disabled = false;
                    generateButtonText.textContent = 'Generate';
                    generateButtonSpinner.style.display = 'none';
                });
        }

        // Buyer management functions
        function loadBuyers(applicationId) {
            fetch(`/final-conveyance/buyers/${applicationId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        renderBuyersTable(data.data);
                    } else {
                        console.error('Error loading buyers:', data.message);
                    }
                })
                .catch(error => {
                    console.error('Error loading buyers:', error);
                });
        }

        function renderBuyersTable(buyers) {
            const tbody = document.getElementById('buyersTableBody');
            const noBuyersMessage = document.getElementById('noBuyersMessage');

            // Filter out buyers with "Other" in their name (case-insensitive)
            const filteredBuyers = buyers.filter(buyer => {
                const buyerName = buyer.buyer_name || '';
                return !buyerName.toLowerCase().includes('other');
            });

            if (filteredBuyers.length === 0) {
                tbody.innerHTML = '';
                noBuyersMessage.classList.remove('hidden');
                return;
            }

            noBuyersMessage.classList.add('hidden');

            tbody.innerHTML = filteredBuyers.map((buyer, index) => `
                        <tr>
                            <td class="px-4 py-3 text-sm text-gray-900">${index + 1}</td>
                            <td class="px-4 py-3 text-sm text-gray-900">
                                ${buyer.buyer_title ? buyer.buyer_title + ' ' : ''}${buyer.buyer_name}
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-900">${buyer.unit_no}</td>
                            <td class="px-4 py-3 text-sm text-gray-900">${buyer.dimension || '-'}</td>
                            <td class="px-4 py-3 text-sm text-gray-900">${buyer.measurement || '-'}</td>
                            <td class="px-4 py-3 text-sm font-medium">
                                <button type="button" class="edit-buyer-btn text-blue-600 hover:text-blue-900 mr-3" data-buyer-id="${buyer.id}">
                                    Edit
                                </button>
                                <button type="button" class="delete-buyer-btn text-red-600 hover:text-red-900" data-buyer-id="${buyer.id}">
                                    Delete
                                </button>
                            </td>
                        </tr>
                    `).join('');
        }

        function editBuyer(buyerId) {
            if (!currentApplicationId) {
                Swal.fire('Error!', 'No application selected', 'error');
                return;
            }

            // Find buyer data
            fetch(`/final-conveyance/buyers/${currentApplicationId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Try multiple comparison methods to handle type mismatches
                        const buyer = data.data.find(b => b.id == buyerId || b.id === buyerId || parseInt(b.id) === parseInt(buyerId));

                        if (buyer) {
                            populateEditBuyerForm(buyer);
                            editingBuyerId = buyerId;
                            showEditBuyerModal();
                        } else {
                            Swal.fire('Error!', 'Buyer not found', 'error');
                        }
                    } else {
                        Swal.fire('Error!', data.message || 'Failed to fetch buyer data', 'error');
                    }
                })
                .catch(error => {
                    console.error('Error fetching buyer data:', error);
                    Swal.fire('Error!', 'Failed to fetch buyer data', 'error');
                });
        }

        function populateEditBuyerForm(buyer) {
            document.getElementById('editBuyerTitle').value = buyer.buyer_title || '';
            document.getElementById('editBuyerName').value = buyer.buyer_name || '';
            document.getElementById('editUnitNo').value = buyer.unit_no || '';
            document.getElementById('editMeasurement').value = buyer.measurement || '';
            document.getElementById('editDimension').value = buyer.dimension || '';
        }

        function showEditBuyerModal() {
            document.getElementById('editBuyerModal').style.display = 'block';
        }

        function closeEditBuyerModal() {
            document.getElementById('editBuyerModal').style.display = 'none';
            document.getElementById('editBuyerForm').reset();
            editingBuyerId = null;

            // Reset button state
            const updateButton = document.getElementById('updateBuyerButton');
            const updateButtonText = document.getElementById('updateBuyerButtonText');
            const updateButtonSpinner = document.getElementById('updateBuyerButtonSpinner');

            updateButton.disabled = false;
            updateButtonText.textContent = 'Update';
            updateButtonSpinner.style.display = 'none';
        }

        function updateBuyer() {
            if (!editingBuyerId) {
                Swal.fire('Error!', 'No buyer selected for editing', 'error');
                return;
            }

            const updateButton = document.getElementById('updateBuyerButton');
            const updateButtonText = document.getElementById('updateBuyerButtonText');
            const updateButtonSpinner = document.getElementById('updateBuyerButtonSpinner');

            // Disable button and show loading
            updateButton.disabled = true;
            updateButtonText.textContent = 'Updating...';
            updateButtonSpinner.style.display = 'inline-block';

            const formData = {
                buyer_title: document.getElementById('editBuyerTitle').value,
                buyer_name: document.getElementById('editBuyerName').value,
                unit_no: document.getElementById('editUnitNo').value,
                measurement: document.getElementById('editMeasurement').value,
                cubic_easurement: document.getElementById('editCubicMeasurement').value,
                dimension: document.getElementById('editDimension').value
            };

            fetch(`/final-conveyance/buyers/${editingBuyerId}`, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                },
                body: JSON.stringify(formData)
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        Swal.fire('Success!', data.message, 'success');
                        closeEditBuyerModal();
                        loadBuyers(currentApplicationId);
                    } else {
                        Swal.fire('Error!', data.message, 'error');
                    }
                })
                .catch(error => {
                    console.error('Error updating buyer:', error);
                    Swal.fire('Error!', 'Failed to update buyer', 'error');
                })
                .finally(() => {
                    // Reset button state
                    updateButton.disabled = false;
                    updateButtonText.textContent = 'Update';
                    updateButtonSpinner.style.display = 'none';
                });
        }

        function deleteBuyer(buyerId) {
            Swal.fire({
                title: 'Are you sure?',
                text: "This buyer will be permanently deleted!",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Yes, delete it!'
            }).then((result) => {
                if (result.isConfirmed) {
                    fetch(`/final-conveyance/buyers/${buyerId}`, {
                        method: 'DELETE',
                        headers: {
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                        }
                    })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                Swal.fire('Deleted!', data.message, 'success');
                                loadBuyers(currentApplicationId);
                            } else {
                                Swal.fire('Error!', data.message, 'error');
                            }
                        })
                        .catch(error => {
                            console.error('Error deleting buyer:', error);
                            Swal.fire('Error!', 'Failed to delete buyer', 'error');
                        });
                }
            });
        }

        // Event delegation for buyer actions and modal events
        document.addEventListener('DOMContentLoaded', function () {
            // Event delegation for edit and delete buttons
            document.addEventListener('click', function (e) {
                if (e.target.classList.contains('edit-buyer-btn')) {
                    const buyerIdAttr = e.target.getAttribute('data-buyer-id');
                    const buyerId = buyerIdAttr ? parseInt(buyerIdAttr) : null;
                    if (buyerId) {
                        editBuyer(buyerId);
                    } else {
                        Swal.fire('Error!', 'Invalid buyer ID', 'error');
                    }
                }

                if (e.target.classList.contains('delete-buyer-btn')) {
                    const buyerIdAttr = e.target.getAttribute('data-buyer-id');
                    const buyerId = buyerIdAttr ? parseInt(buyerIdAttr) : null;
                    if (buyerId) {
                        deleteBuyer(buyerId);
                    } else {
                        Swal.fire('Error!', 'Invalid buyer ID', 'error');
                    }
                }
            });

            // Close edit buyer modal when clicking outside
            document.getElementById('editBuyerModal').addEventListener('click', function (e) {
                if (e.target === this) {
                    closeEditBuyerModal();
                }
            });
        });

        // Close modal when clicking outside
        document.getElementById('finalConveyanceModal').addEventListener('click', function (e) {
            if (e.target === this) {
                closeFinalConveyanceModal();
            }
        });
        // Final Conveyance upload helpers
        const fcUploadModal = document.getElementById('finalConveyanceUploadModal');
        const fcUploadForm = document.getElementById('finalConveyanceUploadForm');
        const fcUploadFileInput = document.getElementById('finalConveyanceUploadFile');
        const fcUploadTrigger = document.getElementById('triggerFinalConveyanceUploadFile');
        const fcUploadPreview = document.getElementById('finalConveyanceUploadPreview');
        const fcUploadFileName = document.getElementById('finalConveyanceUploadFileName');
        const fcUploadFileSize = document.getElementById('finalConveyanceUploadFileSize');
        const fcUploadBtn = document.getElementById('finalConveyanceUploadBtn');
        const fcUploadDescription = document.getElementById('finalConveyanceUploadDescription');
        const fcUploadError = document.getElementById('finalConveyanceUploadError');
        const fcUploadErrorText = document.getElementById('finalConveyanceUploadErrorText');
        const fcUploadFileNoLabel = document.getElementById('finalConveyanceUploadFileNo');
        const finalConveyanceUploadUrl = "{{ route('actions.final-conveyance.upload') }}";
        const csrfMeta = document.querySelector('meta[name=\"csrf-token\"]');
        const finalConveyanceCsrfToken = csrfMeta ? csrfMeta.getAttribute('content') : '';
        let activeFinalConveyanceApplicationId = null;
        let activeFinalConveyanceFileNo = '';

        function setFinalConveyanceUploadError(message) {
            if (!fcUploadError || !fcUploadErrorText) return;
            if (message) {
                fcUploadErrorText.textContent = message;
                fcUploadError.classList.remove('hidden');
            } else {
                fcUploadErrorText.textContent = '';
                fcUploadError.classList.add('hidden');
            }
        }

        function resetFinalConveyanceUploadState() {
            activeFinalConveyanceApplicationId = null;
            activeFinalConveyanceFileNo = '';
            fcUploadForm?.reset();
            fcUploadPreview?.classList.add('hidden');
            setFinalConveyanceUploadError('');
            if (fcUploadBtn) {
                fcUploadBtn.disabled = true;
                fcUploadBtn.textContent = 'Upload Document';
            }
        }

        function closeFinalConveyanceUploadModal() {
            if (!fcUploadModal) return;
            fcUploadModal.classList.add('hidden');
            document.body.style.overflow = '';
            resetFinalConveyanceUploadState();
        }

        window.openFinalConveyanceUploadModal = function (applicationId, fileRef = '') {
            if (!fcUploadModal || !fcUploadBtn) {
                Swal.fire({
                icon: 'error',
                title: 'Error!',
                text: 'Upload modal is not available.'
            });
            return;
        }
                activeFinalConveyanceApplicationId = applicationId;
                activeFinalConveyanceFileNo = fileRef || '';
                if (fcUploadFileNoLabel) {
                    fcUploadFileNoLabel.textContent = activeFinalConveyanceFileNo || 'Not provided';
                }

                fcUploadModal.classList.remove('hidden');
                document.body.style.overflow = 'hidden';
                setFinalConveyanceUploadError('');
            };

            document.querySelectorAll('[data-close-final-upload-modal]').forEach((button) => {
                button.addEventListener('click', closeFinalConveyanceUploadModal);
            });

            if (fcUploadModal) {
                fcUploadModal.addEventListener('click', (event) => {
                    if (event.target === fcUploadModal) {
                        closeFinalConveyanceUploadModal();
                    }
                });
            }

            if (fcUploadTrigger && fcUploadFileInput) {
                fcUploadTrigger.addEventListener('click', () => fcUploadFileInput.click());
            }

            function formatFinalConveyanceFileSize(bytes) {
                if (!bytes && bytes !== 0) {
                    return '';
                }
                const units = ['Bytes', 'KB', 'MB', 'GB'];
                const exponent = Math.floor(Math.log(bytes) / Math.log(1024));
                return `${(bytes / Math.pow(1024, exponent)).toFixed(2)} ${units[exponent]}`;
            }

            fcUploadFileInput?.addEventListener('change', () => {
                setFinalConveyanceUploadError('');
                const file = fcUploadFileInput.files[0];

                if (!file) {
                    fcUploadPreview?.classList.add('hidden');
                    if (fcUploadBtn) fcUploadBtn.disabled = true;
                    return;
                }

                const allowedExtensions = ['pdf'];
                const extension = file.name.split('.').pop().toLowerCase();
                if (!allowedExtensions.includes(extension)) {
                    fcUploadFileInput.value = '';
                    fcUploadPreview?.classList.add('hidden');
                    if (fcUploadBtn) fcUploadBtn.disabled = true;
                    setFinalConveyanceUploadError('Please select a .pdf file.');
                    return;
                }

                if (file.size > 15 * 1024 * 1024) {
                    fcUploadFileInput.value = '';
                    fcUploadPreview?.classList.add('hidden');
                    if (fcUploadBtn) fcUploadBtn.disabled = true;
                    setFinalConveyanceUploadError('File must be 15MB or smaller.');
                    return;
                }

                fcUploadPreview?.classList.remove('hidden');
                if (fcUploadFileName) {
                    fcUploadFileName.textContent = file.name;
                }
                if (fcUploadFileSize) {
                    fcUploadFileSize.textContent = formatFinalConveyanceFileSize(file.size);
                }
                if (fcUploadBtn) {
                    fcUploadBtn.disabled = false;
                }
            });

            fcUploadForm?.addEventListener('submit', async (event) => {
                event.preventDefault();
                if (!fcUploadFileInput || !fcUploadBtn) {
                    return;
                }

                const file = fcUploadFileInput.files[0];
                if (!file || !activeFinalConveyanceApplicationId) {
                    setFinalConveyanceUploadError('Select a document before uploading.');
                    return;
                }

                setFinalConveyanceUploadError('');
                fcUploadBtn.disabled = true;
                fcUploadBtn.textContent = 'Uploading...';

                try {
                    const formData = new FormData();
                    formData.append('document', file);
                    formData.append('application_id', activeFinalConveyanceApplicationId);
                    formData.append('file_no', activeFinalConveyanceFileNo || '');
                    formData.append('description', fcUploadDescription?.value || '');
                    formData.append('_token', finalConveyanceCsrfToken);

                    const response = await fetch(finalConveyanceUploadUrl, {
                        method: 'POST',
                        body: formData,
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                    });

                    const result = await response.json();
                    if (!response.ok || !result.success) {
                        throw new Error(result.message || 'Upload failed.');
                    }

                    Swal.fire({
                        icon: 'success',
                        title: 'Success!',
                        text: result.message || 'Document uploaded successfully.',
                        showConfirmButton: true,
                        confirmButtonText: 'OK'
                    }).then(() => {
                        window.location.reload();
                    });
                } catch (error) {
                    console.error(error);
                    Swal.fire({
                        icon: 'error',
                        title: 'Error!',
                        text: error.message || 'Unable to upload document right now.',
                        showConfirmButton: true,
                        confirmButtonText: 'OK'
                    });
                    fcUploadBtn.disabled = false;
                    fcUploadBtn.textContent = 'Upload Document';
                }
            });

        </script>

        {{-- Add DataTables JS dependencies at the end of the file --}}

        <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
        <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
        <script src="https://cdn.datatables.net/buttons/2.2.2/js/dataTables.buttons.min.js"></script>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.1.3/jszip.min.js"></script>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/pdfmake.min.js"></script>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/vfs_fonts.js"></script>
        <script src="https://cdn.datatables.net/buttons/2.2.2/js/buttons.html5.min.js"></script>

        <script>
            $(document).ready(function () {
                // Initialize DataTable with proper configuration
                const table = $('#conveyanceTable').DataTable({
                    dom: 'Bfrtip',
                    pageLength: 10,
                    responsive: true,
                    ordering: true,
                    searching: true,
                    paging: true,
                    info: true,
                    scrollX: true,
                    autoWidth: false,
                    buttons: [
                        {
                            extend: 'excel',
                            text: 'Excel',
                            className: 'hidden'
                        },
                        {
                            extend: 'csv',
                            text: 'CSV',
                            className: 'hidden'
                        },
                        {
                            extend: 'pdf',
                            text: 'PDF',
                            className: 'hidden'
                        }
                    ]
                });

                // Handle status filter
                $('#statusFilter').on('change', function () {
                    const status = $(this).val();
                    const columnIndexes = table.columns().indexes().toArray();
                    const targetColumns = [6, 7, 8]; // Director's Approval, ST Memo Status, Final Conveyance

                    if (status === 'All...') {
                        targetColumns.forEach(index => {
                            if (columnIndexes.includes(index)) {
                                table.column(index).search('');
                            }
                        });
                        table.draw();
                        return;
                    }

                    targetColumns.forEach(index => {
                        if (columnIndexes.includes(index)) {
                            table.column(index).search(status, false, false);
                        }
                    });
                    table.draw();
                });
            });

            function toggleExportDropdown(event) {
                event.stopPropagation();
                const dropdown = document.getElementById('exportDropdown');
                dropdown.classList.toggle('hidden');
            }

            document.addEventListener('click', () => {
                document.getElementById('exportDropdown').classList.add('hidden');
            });

            function exportTable(type) {
                const table = $('#conveyanceTable').DataTable();

                switch (type) {
                    case 'excel':
                        table.button(0).trigger();
                        break;
                    case 'csv':
                        table.button(1).trigger();
                        break;
                    case 'pdf':
                        table.button(2).trigger();
                        break;
                }
            }
        </script>


@endsection
