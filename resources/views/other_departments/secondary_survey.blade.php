@extends('layouts.app')
@section('page-title')
    {{ $PageTitle }}
@endsection


@include('sectionaltitling.partials.assets.css')
@section('content')
    <div class="flex-1 overflow-auto">
        <!-- Header -->
        @include('admin.header')
        <!-- Dashboard Content -->
        <div class="p-6">

            <!-- Secondary Applications Table - Screenshot 135 -->
            <div class="bg-white rounded-md shadow-sm border border-gray-200 p-6">
                <div class="flex justify-between items-center mb-6">
                    <h2 class="text-xl font-bold">Secondary Applications</h2>

                    <a href="{{ route('other_departments.survey_primary') }}"
                        class="flex items-center space-x-2 px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition-colors">
                        <i data-lucide="clipboard-list" class="w-4 h-4"></i>
                        <span>View Primary Applications</span>
                    </a>

                    <div class="flex items-center space-x-4">

                        <div class="relative">
                            <select
                                class="pl-4 pr-8 py-2 border border-gray-200 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 appearance-none">
                                <option>All...</option>
                                <option>Approved</option>
                                <option>Pending</option>
                                <option>Declined</option>
                            </select>
                            <i data-lucide="chevron-down"
                                class="absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-400 w-4 h-4"></i>
                        </div>



                        <style>
                            button:hover {
                                background-color: #fed7aa;
                            }
                        </style>
                        <button class="flex items-center space-x-2 px-4 py-2 border border-gray-200 rounded-md">
                            <i data-lucide="download" class="w-4 h-4 text-gray-600"></i>
                            <span>Export</span>
                        </button>


                    </div>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead>
                            <tr class="text-xs">
                               
                                <th class="table-header text-green-500">SchemeNo</th>
                                <th class="table-header text-green-500">Mother FileNo</th>
                                <th class="table-header text-green-500">STFileNo</th>
                                <th class="table-header text-green-500">Assignment Reg Particulars</th>
                                <th class="table-header text-green-500">CofO Reg Particular</th>
                                <th class="table-header text-green-500">Land Use</th>
                                <th class="table-header text-green-500">Original Owner</th>
                                <th class="table-header text-green-500">Unit Owner</th>
                                <th class="table-header text-green-500">Unit</th>
                                <th class="table-header text-green-500">Phone Number</th>

                                <th class="table-header text-green-500">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @foreach ($SecondaryApplications as $app)
                                <tr class="text-xs">
                                    
                                    <td class="table-cell px-1 py-1 truncate">{{ $app->scheme_no ?? 'N/A' }}</td>
                                    <td class="table-cell px-1 py-1 truncate">{{ $app->mother_fileno ?? 'N/A' }}</td>
                                    <td class="table-cell px-1 py-1 truncate">{{ $app->fileno ?? 'N/A' }}</td>

                                    <td class="table-cell px-1 py-1 truncate">
                                        @php
                                            // Fetch ST FileNo from application object
                                            $stFileNo = $app->st_fileno ?? $app->STFileNo ?? $app->fileno ?? null;
                                            // Query registered_instruments for ST Assignment (Transfer of Title)
                                            $assignmentReg = null;
                                            if ($stFileNo) {
                                                $assignmentReg = DB::connection('sqlsrv')
                                                    ->table('registered_instruments')
                                                    ->select('volume_no', 'page_no', 'serial_no', 'deeds_time', 'deeds_date')
                                                    ->where('instrument_type', 'ST Assignment (Transfer of Title)')
                                                    ->where('StFileNo', $stFileNo)
                                                    ->first();
                                            }
                                        @endphp
                                        @if ($assignmentReg)
                                            {{ $assignmentReg->serial_no ?? 'N/A' }}/{{ $assignmentReg->page_no ?? 'N/A' }}/{{ $assignmentReg->volume_no ?? 'N/A' }}
                                        @else
                                            <span class="text-gray-400">N/A</span>
                                        @endif
                                    </td>

                                    <td class="table-cell px-1 py-1 truncate">
                                        @php
                                            // Query registered_instruments for ST CofO Registration
                                            $cofoReg = null;
                                            if ($stFileNo) {
                                                $cofoReg = DB::connection('sqlsrv')
                                                    ->table('registered_instruments')
                                                    ->select('volume_no', 'page_no', 'serial_no', 'deeds_time', 'deeds_date')
                                                    ->where('instrument_type', 'Sectional Titling CofO')
                                                    ->where('StFileNo', $stFileNo)
                                                    ->first();
                                            } 
                                        @endphp
                                        @if ($cofoReg)
                                            {{ $cofoReg->serial_no ?? 'N/A' }}/{{ $cofoReg->page_no ?? 'N/A' }}/{{ $cofoReg->volume_no ?? 'N/A' }}
                                        @else
                                            <span class="text-gray-400">N/A</span>
                                        @endif
                                    </td>
            <td class="table-cell px-1 py-1 truncate">{{ $app->land_use ?? 'N/A' }}</td>
            <td class="table-cell px-1 py-1">
                <div class="flex items-center">
                    <div class="w-10 h-10 rounded-full bg-gray-200 flex items-center justify-center mr-2">
                        @if (!empty($app->mother_passport))
                            <img src="{{ asset('storage/app/public/' . $app->mother_passport) }}"
                                alt="Original Owner Passport" class="w-full h-full rounded-full object-cover cursor-pointer"
                                onclick="showPassportPreview('{{ asset('storage/app/public/' . $app->mother_passport) }}', 'Original Owner Passport')">
                        @elseif(!empty($app->mother_multiple_owners_passport))
                            @php
                                $passports = is_array($app->mother_multiple_owners_passport)
                                    ? $app->mother_multiple_owners_passport
                                    : json_decode($app->mother_multiple_owners_passport, true);
                                $firstPassport = !empty($passports) && isset($passports[0]) ? $passports[0] : null;
                            @endphp
                            @if ($firstPassport)
                                <img src="{{ asset('storage/app/public/' . $firstPassport) }}"
                                    alt="Original Owner Passport"
                                    class="w-full h-full rounded-full object-cover cursor-pointer"
                                    onclick="showMultipleOwners(
                             @json(is_array($app->mother_multiple_owners_names)
                                     ? $app->mother_multiple_owners_names
                                     : json_decode($app->mother_multiple_owners_names, true)), 
                             @json($passports)
                           )">
                            @else
                                <i data-lucide="{{ !empty($app->mother_corporate_name) ? 'building' : (!empty($app->mother_multiple_owners_names) ? 'users' : 'user') }}"
                                    class="w-3 h-3 text-gray-500"></i>
                            @endif
                        @else
                            <i data-lucide="{{ !empty($app->mother_corporate_name) ? 'building' : (!empty($app->mother_multiple_owners_names) ? 'users' : 'user') }}"
                                class="w-3 h-3 text-gray-500"></i>
                        @endif
                    </div>
                    <div>
                        @if (!empty($app->mother_corporate_name))
                            <span>{{ $app->mother_corporate_name }}</span>
                        @elseif(!empty($app->mother_multiple_owners_names))
                            @php
                                $names = $app->mother_multiple_owners_names;
                                $decoded = [];
                                if (!empty($names)) {
                                    $decoded = is_array($names) ? $names : json_decode($names, true);
                                    if (!is_array($decoded)) {
                                        $decoded = [];
                                    }
                                }
                            @endphp
                            <span>{{ !empty($decoded) && isset($decoded[0]) ? $decoded[0] : '' }}</span>
                            @if (!empty($decoded))
                                <span class="ml-1 cursor-pointer text-blue-500"
                                    onclick="showMultipleOwners(
                              @json($decoded), 
                              @json(is_array($app->mother_multiple_owners_passport)
                                      ? $app->mother_multiple_owners_passport
                                      : json_decode($app->mother_multiple_owners_passport, true))
                            )">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 inline" fill="none"
                                        viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                </span>
                            @endif
                        @else
                            <span>{{ $app->mother_applicant_title ?? '' }}
                                {{ $app->mother_first_name ?? '' }}
                                {{ $app->mother_surname ?? '' }}</span>
                        @endif
                    </div>
                </div>
            </td>
            <td class="table-cell px-1 py-1">
                <div class="flex items-center">
                    <div class="w-10 h-10 rounded-full bg-gray-200 flex items-center justify-center mr-2">
                        @if (!empty($app->passport))
                            <img src="{{ asset('storage/app/public/' . $app->passport) }}" alt="Unit Owner Passport"
                                class="w-full h-full rounded-full object-cover cursor-pointer"
                                onclick="showPassportPreview('{{ asset('storage/app/public/' . $app->passport) }}', 'Unit Owner Passport')">
                        @elseif(!empty($app->multiple_owners_passport))
                            @php
                                $passports = is_array($app->multiple_owners_passport)
                                    ? $app->multiple_owners_passport
                                    : json_decode($app->multiple_owners_passport, true);
                                $firstPassport = !empty($passports) && isset($passports[0]) ? $passports[0] : null;
                            @endphp
                            @if ($firstPassport)
                                <img src="{{ asset('storage/app/public/' . $firstPassport) }}" alt="Unit Owner Passport"
                                    class="w-full h-full rounded-full object-cover cursor-pointer"
                                    onclick="showMultipleOwners(
                             @json(is_array($app->multiple_owners_names)
                                     ? $app->multiple_owners_names
                                     : json_decode($app->multiple_owners_names, true)), 
                             @json($passports)
                           )">
                            @else
                                <i data-lucide="{{ !empty($app->corporate_name) ? 'building' : (!empty($app->multiple_owners_names) ? 'users' : 'user') }}"
                                    class="w-3 h-3 text-gray-500"></i>
                            @endif
                        @else
                            <i data-lucide="{{ !empty($app->corporate_name) ? 'building' : (!empty($app->multiple_owners_names) ? 'users' : 'user') }}"
                                class="w-3 h-3 text-gray-500"></i>
                        @endif
                    </div>
                    <div>
                        @if (!empty($app->corporate_name))
                            <span>{{ $app->corporate_name }}</span>
                        @elseif(!empty($app->multiple_owners_names))
                            @php
                                $names = $app->multiple_owners_names;
                                $decoded = [];
                                if (!empty($names)) {
                                    if (is_array($names)) {
                                        $decoded = $names;
                                    } else {
                                        $tryJson = json_decode($names, true);
                                        if (is_array($tryJson)) {
                                            $decoded = $tryJson;
                                        } else {
                                            $decoded = array_map('trim', str_getcsv($names));
                                        }
                                    }
                                }
                            @endphp
                            <span>{{ !empty($decoded) && isset($decoded[0]) ? $decoded[0] : '' }}</span>
                            @if (!empty($decoded))
                                <span class="ml-1 cursor-pointer text-blue-500"
                                    onclick="showMultipleOwners(
                              @json($decoded), 
                              @json(is_array($app->multiple_owners_passport)
                                      ? $app->multiple_owners_passport
                                      : json_decode($app->multiple_owners_passport, true))
                            )">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 inline" fill="none"
                                        viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                </span>
                            @endif
                        @else
                            <span>{{ $app->applicant_title ?? '' }} {{ $app->first_name ?? '' }}
                                {{ $app->surname ?? '' }}</span>
                        @endif
                    </div>
                </div>
            </td>
            <td class="table-cell px-1 py-1 truncate">{{ $app->unit_number ?? 'N/A' }}</td>
            <td class="table-cell px-1 py-1 truncate">
                @if (!empty($app->phone_number) && str_contains($app->phone_number, ','))
                    @php
                        $phones = array_map('trim', explode(',', $app->phone_number));
                        $firstPhone = $phones[0];
                        $allPhones = implode('<br>', $phones);
                    @endphp
                    <div class="relative group">
                        <span>{{ $firstPhone }}</span>
                        <i data-lucide="more-horizontal" class="inline-block w-3 h-3 text-gray-500 ml-1"></i>
                        <div
                            class="absolute hidden group-hover:block bg-white border border-gray-200 shadow-lg rounded-md p-2 z-10 text-xs">
                            {!! $allPhones !!}
                        </div>
                    </div>
                @else
                    {{ $app->phone_number ?? 'N/A' }}
                @endif
            </td>

            <td class="table-cell overflow-visible relative">
                <button
                    class="flex items-center px-2 py-1 text-xs border border-gray-200 rounded-md bg-white hover:bg-gray-50"
                    onclick="toggleDropdown(event)">
                    <i data-lucide="more-horizontal" class="w-4 h-4"></i>
                </button>


                <div class="dropdown-menu hidden fixed w-48 bg-white shadow-lg rounded-md z-50">
                    <ul class="py-2">
                        <li>
                            <a href="{{ route('sectionaltitling.viewrecorddetail') }}?id={{ $app->id }}"
                                class="flex items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                <i data-lucide="eye" class="w-4 h-4 text-blue-500"></i>
                                View Application
                            </a>
                        </li>


                        <li>
                            <a href="{{ route('other_departments.survey_secondary', ['id' => $app->id]) }}"
                                class="flex items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                <i data-lucide="edit-3" class="w-4 h-4 text-yellow-500"></i>
                                Survey
                            </a>
                        </li>
                        <li>
                            <a href="{{ route('other_departments.deeds', ['id' => $app->id]) }}?is=secondary"
                                class="flex items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                <i data-lucide="file-text" class="w-4 h-4 text-green-600"></i>
                                Deeds
                            </a>
                        </li>

                        <li>
                            <a href="{{ route('other_departments.lands', ['id' => $app->id]) }}?is=secondary"
                                class="flex items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                <i data-lucide="layers" class="w-4 h-4 text-purple-600"></i>
                                Lands
                            </a>
                        </li>

                    </ul>
                </div>
            </td>
            </tr>
            @endforeach
            </tbody>
            </table>
        </div>
        <div class="flex justify-between items-center mt-6 text-sm">
            <div class="text-gray-500"></div>
            <div class="flex items-center space-x-2">
                <button class="px-3 py-1 border border-gray-200 rounded-md flex items-center">
                    <i data-lucide="chevron-left" class="w-4 h-4 mr-1"></i>
                    <span>Previous</span>
                </button>
                <button class="px-3 py-1 border border-gray-200 rounded-md flex items-center">
                    <span>Next</span>
                    <i data-lucide="chevron-right" class="w-4 h-4 ml-1"></i>
                </button>
            </div>
        </div>
    </div>

    </div>
    <!-- Footer -->
    @include('admin.footer')
    </div>
    @include('sectionaltitling.sub_action_modals.payment_modal')
    @include('sectionaltitling.sub_action_modals.other_departments')
    @include('sectionaltitling.sub_action_modals.eRegistry_modal')
    @include('sectionaltitling.sub_action_modals.recommendation')
    @include('sectionaltitling.sub_action_modals.directorApproval')
@endsection

<script src="//cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    function toggleDropdown(event) {
        event.stopPropagation();
        const button = event.currentTarget;
        const dropdownMenu = button.nextElementSibling;

        if (dropdownMenu) {
            // Close all other open dropdowns first
            document.querySelectorAll('.dropdown-menu:not(.hidden)').forEach(menu => {
                if (menu !== dropdownMenu) {
                    menu.classList.add('hidden');
                }
            });

            // Toggle the current dropdown
            const isHidden = dropdownMenu.classList.contains('hidden');

            if (isHidden) {
                // Position the dropdown
                const buttonRect = button.getBoundingClientRect();
                const windowHeight = window.innerHeight;
                const dropdownHeight = 180; // Approximate height of dropdown

                // Check if there's enough space below
                const spaceBelow = windowHeight - buttonRect.bottom;

                if (spaceBelow < dropdownHeight && buttonRect.top > dropdownHeight) {
                    // Position above the button
                    dropdownMenu.style.top = (buttonRect.top - dropdownHeight) + 'px';
                } else {
                    // Position below the button
                    dropdownMenu.style.top = buttonRect.bottom + 'px';
                }

                // Horizontal positioning
                dropdownMenu.style.left = (buttonRect.left - 120) + 'px'; // Align dropdown to the left of the button

                // Show the dropdown
                dropdownMenu.classList.remove('hidden');
            } else {
                dropdownMenu.classList.add('hidden');
            }
        }
    }

    document.addEventListener('click', () => {
        const dropdownMenus = document.querySelectorAll('.dropdown-menu');
        dropdownMenus.forEach(menu => menu.classList.add('hidden'));
    });

    window.showFullNames = function(owners) {
        if (!Array.isArray(owners)) {
            owners = [];
        }
        if (owners.length > 0) {
            Swal.fire({
                title: 'Full Names of Multiple Owners',
                html: '<ul>' + owners.map(name => `<li>${name}</li>`).join('') + '</ul>',
                icon: 'info',
                confirmButtonText: 'Close'
            });
        } else {
            Swal.fire({
                title: 'Full Names of Multiple Owners',
                text: 'No owners available',
                icon: 'info',
                confirmButtonText: 'Close'
            });
        }
    }
</script>
