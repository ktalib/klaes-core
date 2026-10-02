@extends('layouts.app')

@section('page-title')
    {{ __('Instrument Capture') }}
@endsection

@push('scripts')
    {{-- Select2 JS --}}
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

    {{-- PDF Generation Libraries --}}
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.7.1/jspdf.plugin.autotable.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>


    @include('instrument_registration.modals.export_preview')
    <script src="{{ asset('js/instrument_capture_export.js') }}"></script>
@endpush

@section('content') 
    <meta name="csrf-token" content="{{ csrf_token() }}">

    {{-- DataTables CSS + Buttons --}}
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.2/css/buttons.bootstrap5.min.css">

    {{-- Select2 CSS --}}
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />

    <!-- Global Config for RDS/CoR  -->
    <script>
        window.KlaesConfig = {
            baseUrl: "{{ url('/') }}",
            csrfToken: "{{ csrf_token() }}",
            urls: {
                base: "{{ url('instrument_registration') }}",
                delete: "{{ url('instrument_registration/delete') }}",
                generateRds: "{{ url('instrument_registration/generate-rds') }}",
                viewRds: "{{ url('instrument_registration/view-rds') }}",
                rdsStatus: "{{ url('instrument_registration/rds-status') }}",
                corIndex: "{{ route('coroi.index') }}",
                generateCor: "{{ url('coroi/generate') }}"
            }
        };

        window.baseUrl = "{{ url('') }}";
        // Built with url() so the page keeps working when the app is served from a
        // sub-directory, where string-concatenating onto baseUrl would drop it.
        window.solicitorEndpointBase = "{{ url('instruments') }}";
        window.instrumentRegistrationBase = "{{ url('') }}";

        window.serverCofoData = @json($fullDataForJs).map(item => {
            item.registered_instrument_id = item.registered_instrument_id || null;
            return item;
        });

        // Use window and var to avoid redeclaration errors with other scripts
        // Initialize global variables only if they don't exist
        window.appData = window.appData || {};
        window.instrumentLookup = window.instrumentLookup || new Map();
        
        // Define local references without potential redeclaration issues
        var currentAppData = window.appData;
        var currentInstrumentLookup = window.instrumentLookup;

        function populateInstrumentCaches(data) {
            window.instrumentLookup.clear();
            data.forEach(entry => {
                const idKey = String(entry.id);
                window.appData[idKey] = entry;
                window.instrumentLookup.set(idKey, entry);
                if (entry.registered_instrument_id) {
                    window.instrumentLookup.set(String(entry.registered_instrument_id), entry);
                }
            });
        }
        populateInstrumentCaches(window.serverCofoData);
    </script>

    @include('instruments.partial.css')
    <!-- Main Content -->
    <div class="flex-1 overflow-auto">
        <!-- Header -->
        @include('admin.header')
        <!-- Dashboard Content -->
        <div class="p-6">

            <main class="space-y-6">
                <!-- Stats Cards -->
                @include('instruments.partial.stats_cards')

                <!-- Instruments Table -->
                <div class="table-container">
                    <div class="p-4 border-b border-gray-200">
                        <div class="flex justify-between items-center">
                            <h2 class="text-lg font-semibold">Instrument Capture</h2>
                            <div class="flex items-center gap-2">
                                {{-- Instrument Type Filter --}}
                                <div class="flex items-center gap-2">
                                    <label for="instrumentTypeFilter" class="text-xs font-semibold text-gray-700">Type:</label>
                                    <select id="instrumentTypeFilter"
                                        class="border border-gray-300 rounded px-2 py-1.5 text-xs w-48 bg-white focus:ring-2 focus:ring-blue-500 transition-all outline-none"
                                        onchange="filterInstrumentsTable()">
                                        <option value="">All Types</option>
                                        @foreach($instrumentTypes as $type)
                                            <option value="{{ $type }}" {{ ($typeFilter ?? '') === $type ? 'selected' : '' }}>{{ $type }}</option>
                                        @endforeach
                                    </select>
                                </div>

                                {{-- Volume Filter --}}
                                <div class="flex items-center gap-2">
                                    <label for="volumeFilter" class="text-xs font-semibold text-gray-700">Vol:</label>
                                    <select id="volumeFilter"
                                        class="border border-gray-300 rounded px-2 py-1.5 text-xs w-24 bg-white focus:ring-2 focus:ring-blue-500 transition-all outline-none"
                                        onchange="filterInstrumentsTable()">
                                        <option value="">All</option>
                                        @for ($i = 1; $i <= 999; $i++)
                                            <option value="{{ $i }}" {{ (string) ($volumeFilter ?? '') === (string) $i ? 'selected' : '' }}>{{ $i }}</option>
                                        @endfor
                                    </select>
                                </div>

                                @canDo('Deeds Registration', 'export')
                                {{-- Export Button --}}
                                <button id="exportRegistryBtn" onclick="openCaptureExportModal()"
                                    class="bg-green-600 hover:bg-green-700 text-white font-medium py-2 px-4 rounded-lg flex items-center gap-2 text-sm shadow-sm transition-all hover:shadow-md">
                                    <i data-lucide="file-export" class="w-4 h-4"></i>
                                    <span>Export Instruments</span>
                                </button>
                                @endcanDo

                                @canDo('Deeds Registration', 'create')
                                <a class="btn btn-primary" href="{{ route('instruments.create') }}">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-1" fill="none"
                                        viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M12 4v16m8-8H4" />
                                    </svg>
                                    Capture New Instrument
                                </a>
                                @endcanDo
                            </div>
                        </div>
                    </div>

                    <div class="p-4">
                        <div class="table-responsive">
                            <table id="instrumentsTable" class="w-full">
                                <thead>
                                    <tr>
                                        <th class="text-left whitespace-nowrap">S/N</th>
                                        <th class="text-left whitespace-nowrap">PropID</th>
                                        <th class="text-left whitespace-nowrap">File No</th>
                                        <th class="text-left whitespace-nowrap">Reg Particulars</th>
                                        <th class="text-left whitespace-nowrap">Instrument Type</th>
                                        <th class="text-left">Party 1</th>
                                        <th class="text-left">Party 2</th>
                                        <th class="text-left">Party 3</th>
                                       
                                        <th class="text-left">Solicitor</th>
                                        <th class="text-left">Land Use</th>
                                         <th class="text-left">Reg Time/Date</th>
                                        <th class="text-left">Time/Date Captured</th>
                                       
                                        <th class="text-left whitespace-nowrap">Property Description</th>
                                        <th class="text-left whitespace-nowrap">Registered By</th>
                                        <th class="text-left">Actions</th>
                                    </tr>
                                </thead>
                            <tbody>
                                @forelse($instruments as $instrument)
                                    @php
                                        // Occupancy Permits record an existing paper permit rather than a
                                        // registered instrument, so they carry no solicitor of their own.
                                        // Used by the type badge and to withhold the solicitor action below.
                                        $isOP = stripos($instrument->instrument_type ?? '', 'Occupancy Permit') !== false;

                                        // Whichever number this file actually carries; also the
                                        // timeline's fallback identifier when prop_id is missing.
                                        $rowFileNo = $instrument->mlsFNo ?: $instrument->kangisFileNo ?: $instrument->NewKANGISFileno ?: $instrument->temp_fileno;
                                    @endphp
                                    <tr class="instrument-row" data-volume="{{ $instrument->volume_no ?? '' }}">
                                        {{-- Counts through the whole result set, not just this page,
                                             so row 21 reads 21 on page 2. --}}
                                        <td class="align-top whitespace-nowrap text-xs text-gray-500">
                                            {{ $instruments->firstItem() + $loop->index }}
                                        </td>
                                        <td class="align-top whitespace-nowrap">
                                            <span class="font-mono text-xs text-gray-700">{{ $instrument->prop_id ?: '—' }}</span>
                                        </td>
                                        <td class="align-top">
                                            <div class="flex flex-col items-start gap-1">
                                                <span class="badge bg-gray-100 text-gray-800 border border-gray-200 rounded-full px-3 py-1 whitespace-nowrap font-mono text-xs tracking-wide">
                                                    {{ $rowFileNo }}
                                                </span>
                                                {{-- The same cross-table timeline the PRA and Instrument
                                                     Registration screens open. Either identifier will do,
                                                     so a row whose prop_id was never allocated still opens
                                                     on its file number. The count is the weighted
                                                     (deduped) record count the sibling screens badge. --}}
                                                @php $timelineCount = $timelineCounts[$instrument->id] ?? 0; @endphp
                                                @if(!empty($instrument->prop_id) || !empty($rowFileNo))
                                                    <button type="button"
                                                        class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-[10px] font-medium border transition {{ $timelineCount > 0 ? 'bg-indigo-50 text-indigo-700 border-indigo-200 hover:bg-indigo-100' : 'bg-gray-50 text-gray-400 border-gray-200 hover:bg-gray-100' }}"
                                                        onclick="openPropertyTimeline('{{ $instrument->prop_id ?? '' }}', '{{ $rowFileNo }}')"
                                                        title="View the full property timeline">
                                                        <i data-lucide="history" class="w-3 h-3"></i> Timeline ({{ $timelineCount }})
                                                    </button>
                                                @endif
                                            </div>
                                        </td>
                                        <td class="align-top whitespace-nowrap text-sm">
                                            @if($instrument->registered_instrument_id)
                                                <span class="px-2 py-1 bg-blue-100 text-blue-800 rounded-md font-mono text-xs">
                                                    {{ $instrument->serial_no ?? '0' }}/{{ $instrument->page_no ?? '0' }}/{{ $instrument->volume_no ?? '0' }}
                                                </span>
                                            @else
                                                <span class="text-gray-400 text-xs">Unregistered</span>
                                            @endif
                                        </td>
                                        <td class="align-top">
                                            @php
                                                $itype = $instrument->instrument_type ?? '';
                                                $itypeBadge = match(true) {
                                                    stripos($itype, 'Occupancy Permit') !== false
                                                        => 'bg-orange-100 text-orange-800 border-orange-200',
                                                    stripos($itype, 'Irrevocable Power of Attorney') !== false
                                                        => 'bg-green-100 text-green-800 border-green-200',
                                                    stripos($itype, 'Tripartite Mortgage') !== false
                                                        => 'bg-red-100 text-red-800 border-red-200',
                                                    stripos($itype, 'Deed of Mortgage') !== false
                                                        => 'bg-purple-100 text-purple-800 border-purple-200',
                                                    stripos($itype, 'Deed of Assignment') !== false
                                                        => 'bg-amber-100 text-amber-800 border-amber-200',
                                                    stripos($itype, 'Deed of Sub-Lease') !== false
                                                        => 'bg-pink-100 text-pink-800 border-pink-200',
                                                    stripos($itype, 'Deed of Lease') !== false
                                                        => 'bg-indigo-100 text-indigo-800 border-indigo-200',
                                                    stripos($itype, 'Surrender') !== false || stripos($itype, 'Release') !== false
                                                        => 'bg-lime-100 text-lime-800 border-lime-200',
                                                    stripos($itype, 'Deed of Gift') !== false
                                                        => 'bg-rose-100 text-rose-800 border-rose-200',
                                                    stripos($itype, 'Court Order') !== false
                                                        => 'bg-slate-100 text-slate-800 border-slate-200',
                                                    stripos($itype, 'Certificate of Occupancy') !== false || stripos($itype, 'CofO') !== false
                                                        => 'bg-teal-100 text-teal-800 border-teal-200',
                                                    default => 'bg-blue-100 text-blue-800 border-blue-200',
                                                };
                                            @endphp
                                            <div class="flex flex-col gap-1">
                                                <span class="badge border {{ $itypeBadge }} self-start whitespace-nowrap">
                                                    {{ $itype ?: '—' }}
                                                </span>
                                                @if($isOP)
                                                    @if(!empty($instrument->op_type))
                                                        <span class="text-[10px] tracking-wider text-orange-600 font-bold px-1 uppercase">
                                                            {{ ucwords(strtolower($instrument->op_type)) }}
                                                        </span>
                                                    @endif
                                                    @if(!empty($instrument->op_serial_number))
                                                        <span class="px-2 py-0.5 bg-orange-50 text-orange-700 border border-orange-100 rounded text-[10px] font-mono mt-0.5 self-start">
                                                            {{ $instrument->op_serial_number }}
                                                        </span>
                                                    @endif
                                                @endif
                                            </div>
                                        </td>
                                        <td class="align-top text-proper">{{ $instrument->party_1_name ? ucwords(strtolower($instrument->party_1_name)) : '' }}</td>
                                        <td class="align-top text-proper">{{ $instrument->party_2_name ? ucwords(strtolower($instrument->party_2_name)) : '' }}</td>
                                        <td class="align-top text-proper">{{ $instrument->party_3_name ? ucwords(strtolower($instrument->party_3_name)) : '' }}</td>
                                        <td class="align-top expandable" data-solicitor-cell="{{ $instrument->id }}">
                                            <div class="flex flex-col">
                                                <div class="font-bold text-gray-800 text-proper leading-tight w-full whitespace-normal" data-solicitor-name>
                                                    {{ $instrument->solicitor_name ? ucwords(strtolower($instrument->solicitor_name)) : '' }}
                                                </div>
                                                @if($instrument->solicitor_phone)
                                                    <div class="text-xs text-emerald-700 font-medium mt-0.5 w-full whitespace-normal" data-solicitor-phone>
                                                        <i data-lucide="phone" class="inline w-3 h-3"></i> {{ $instrument->solicitor_phone }}
                                                    </div>
                                                @else
                                                    <div class="hidden" data-solicitor-phone></div>
                                                @endif
                                                <div class="text-xs text-gray-500 text-proper mt-0.5 w-full whitespace-normal" style="font-size: 11px;" data-solicitor-address>
                                                    {{ $instrument->solicitor_address ? ucwords(strtolower($instrument->solicitor_address)) : '' }}
                                                </div>
                                            </div>
                                        </td>
                                    
                                        <td class="align-top">
                                            @php
                                                $detailLandUse = $instrument->land_use;
                                                if (!$detailLandUse) {
                                                    $fileno = $instrument->mlsFNo ?: $instrument->kangisFileNo ?: $instrument->NewKANGISFileno;
                                                    if ($fileno) {
                                                        if (Str::contains($fileno, 'RES')) $detailLandUse = 'Residential';
                                                        elseif (Str::contains($fileno, 'COM')) $detailLandUse = 'Commercial';
                                                        elseif (Str::contains($fileno, 'IND')) $detailLandUse = 'Industrial';
                                                        elseif (Str::contains($fileno, 'AG')) $detailLandUse = 'Agriculture';
                                                        else $detailLandUse = '-';
                                                    } else {
                                                        $detailLandUse = '-';
                                                    }
                                                }

                                                $badgeClass = match (true) {
                                                    in_array($detailLandUse, ['Residential', 'RESIDENTIAL'])
                                                        => 'bg-emerald-100 text-emerald-800 border-emerald-200',
                                                    in_array($detailLandUse, ['Commercial', 'COMMERCIAL'])
                                                        => 'bg-purple-100 text-purple-800 border-purple-200',
                                                    in_array($detailLandUse, ['Industrial', 'INDUSTRIAL'])
                                                        => 'bg-amber-100 text-amber-800 border-amber-200',
                                                    in_array($detailLandUse, ['Agriculture', 'Agricultural', 'AGRICULTURE'])
                                                        => 'bg-lime-100 text-lime-800 border-lime-200',
                                                    in_array($detailLandUse, ['Educational', 'EDUCATIONAL'])
                                                        => 'bg-pink-100 text-pink-800 border-pink-200',
                                                    in_array($detailLandUse, ['Government', 'GOVERNMENT'])
                                                        => 'bg-slate-100 text-slate-800 border-slate-200',
                                                    in_array($detailLandUse, ['Mixed', 'Mixed Use', 'MIXED'])
                                                        => 'bg-cyan-100 text-cyan-800 border-cyan-200',
                                                    in_array($detailLandUse, ['Religious', 'RELIGIOUS'])
                                                        => 'bg-violet-100 text-violet-800 border-violet-200',
                                                    default => 'bg-gray-100 text-gray-500 border-gray-200',
                                                };

                                                $purposeDisplay = $instrument->purpose ?? '';
                                                
                                                $purposeColor = match (strtolower(trim($purposeDisplay))) {
                                                    'residential', 'dwelling' => 'text-emerald-600',
                                                    'commercial', 'shop', 'office' => 'text-purple-600',
                                                    'industrial', 'factory', 'warehouse' => 'text-amber-600',
                                                    'agricultural', 'farming' => 'text-lime-600',
                                                    'educational', 'school' => 'text-pink-600',
                                                    'religious', 'worship' => 'text-violet-600',
                                                    'mixed', 'mixed use' => 'text-cyan-600',
                                                    'resettlement' => 'text-orange-600',
                                                    'government', 'public' => 'text-slate-600',
                                                    'hospital', 'health', 'clinic' => 'text-red-600',
                                                    'recreation', 'leisure' => 'text-teal-600',
                                                    default => 'text-blue-600',
                                                };
                                            @endphp
                                            <div class="flex flex-col gap-0.5">
                                                <span class="badge border {{ $badgeClass }} rounded-full px-2 py-0.5 text-xs font-semibold whitespace-nowrap">
                                                    {{ $detailLandUse }}
                                                </span>
                                                @if($purposeDisplay)
                                                    <span class="text-[10px] {{ $purposeColor }} font-medium pl-1">{{ ucwords(strtolower($purposeDisplay)) }}</span>
                                                @endif
                                            </div>
                                        </td>   

                                              <td class="align-top whitespace-nowrap">
                                            @if($instrument->deeds_date || $instrument->deeds_time)
                                                <div class="flex flex-col">
                                                    @if($instrument->deeds_time)
                                                        <span class="text-blue-700 text-xs">{{ \Carbon\Carbon::parse($instrument->deeds_time)->format('g:i A') }}</span>
                                                    @endif
                                                    @if($instrument->deeds_date)
                                                        <span class="text-blue-500 text-xs">{{ \Carbon\Carbon::parse($instrument->deeds_date)->format('M d, Y') }}</span>
                                                    @endif
                                                </div>
                                            @else
                                                <span class="text-gray-300 text-xs">—</span>
                                            @endif
                                        </td>

                                        
                                        <td class="align-top whitespace-nowrap" data-order="{{ \Carbon\Carbon::parse($instrument->created_at)->format('Y-m-d H:i:s') }}">
                                            <div class="flex flex-col">
                                                <span class="text-gray-700 text-xs">{{ \Carbon\Carbon::parse($instrument->created_at)->format('g:i A') }}</span>
                                                <span class="text-gray-500 text-xs">{{ \Carbon\Carbon::parse($instrument->created_at)->format('M d, Y') }}</span>
                                            </div>
                                        </td>
                                  
                                        <td class="align-top expandable">
                                            @if($instrument->property_description)
                                                <div class="text-gray-600 text-sm leading-tight text-proper whitespace-normal">
                                                    {{ ucwords(strtolower($instrument->property_description)) }}
                                                </div>
                                            @else
                                                <span class="text-gray-300 text-xs">—</span>
                                            @endif
                                        </td>
                                        <td class="align-top whitespace-nowrap">
                                            @if(!empty(trim($instrument->created_by_name)))
                                                {{-- Opens the shared profile card; created_by is a real user id here. --}}
                                                <span class="text-gray-700 text-xs font-medium upc-trigger" data-user-card
                                                    data-user-id="{{ $instrument->created_by }}"
                                                    data-user-name="{{ trim($instrument->created_by_name) }}"
                                                    title="{{ __('View profile') }}">{{ ucwords(strtolower(trim($instrument->created_by_name))) }}</span>
                                            @else
                                                <span class="text-gray-300 text-xs">—</span>
                                            @endif
                                        </td>
                                        <td class="relative group text-right">
                                            <button class="text-gray-500 hover:text-gray-700 focus:outline-none flex items-center justify-center w-8 h-8 rounded-full hover:bg-gray-100 transition ml-auto"
                                                data-dropdown-toggle="dropdown-{{ $instrument->id }}"
                                                onclick="toggleDropdown('dropdown-{{ $instrument->id }}')" aria-haspopup="true"
                                                aria-expanded="false" aria-controls="dropdown-{{ $instrument->id }}" type="button">
                                                <i data-lucide="ellipsis-vertical" class="w-4 h-4"></i>
                                            </button>
                                            <div id="dropdown-{{ $instrument->id }}"
                                                class="dropdown-menu hidden absolute right-0 mt-1 w-48 bg-white border border-gray-200 rounded-lg shadow-lg z-[100]"
                                                role="menu">
                                                <a href="javascript:void(0)" class="flex items-center gap-2 px-4 py-2 text-sm text-gray-400 opacity-50 pointer-events-none cursor-not-allowed transition" role="menuitem">
                                                    <i data-lucide="eye" class="w-4 h-4"></i> Views
                                                </a>
                                                <a href="javascript:void(0)" class="flex items-center gap-2 px-4 py-2 text-sm text-gray-400 opacity-50 pointer-events-none cursor-not-allowed transition" role="menuitem">
                                                    <i data-lucide="edit-3" class="w-4 h-4"></i> Edit
                                                </a>

                                                @canDo('Deeds Registration', 'delete')
                                                <form action="{{ route('instruments.destroy', $instrument->id) }}" method="POST" role="menuitem">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" disabled class="flex items-center gap-2 w-full text-left px-4 py-2 text-sm text-red-300 opacity-50 pointer-events-none cursor-not-allowed transition">
                                                        <i data-lucide="trash-2" class="w-4 h-4"></i> Delete
                                                    </button>
                                                </form>
                                                @endcanDo

                                                {{-- Solicitor details are hand-keyed at capture and are the one part of
                                                     a captured instrument officers routinely have to correct afterwards.
                                                     Full Edit is disabled on this screen, so this opens a small modal
                                                     carrying the capture card's Solicitor fields. Every instrument type
                                                     gets it except an OP, which has no solicitor to correct. --}}
                                                @unless($isOP)
                                                    <div class="border-t border-gray-100 my-1"></div>

                                                    <a href="#" onclick="showUpdateSolicitorModal('{{ $instrument->id }}'); return false;"
                                                       class="flex items-center gap-2 px-4 py-2 text-sm text-indigo-600 hover:bg-indigo-50 transition" role="menuitem">
                                                        <i data-lucide="briefcase" class="w-4 h-4"></i> Update Solicitors Details
                                                    </a>
                                                @endunless

                                                @if(!empty($instrument->registered_instrument_id))
                                                    <div class="border-t border-gray-100 my-1"></div>
                                                    
                                                    @php
                                                        // Check if RDS has been generated
                                                        $rdsGenerated = false;
                                                        try {
                                                            $rdsRecord = DB::connection('sqlsrv')
                                                                ->table('rds_tracking')
                                                                ->where('instrument_id', $instrument->registered_instrument_id)
                                                                ->first();
                                                            $rdsGenerated = !empty($rdsRecord);
                                                        } catch (\Exception $e) {
                                                            // Handle error silently
                                                        }
                                                        
                                                        // Check if CoR has been generated 
                                                        $corGenerated = false;
                                                        if (stripos($instrument->registered_instrument_id, 'deed_reg_') === 0) {
                                                            try {
                                                                $realId = substr($instrument->registered_instrument_id, 9);
                                                                $deedRecord = DB::connection('sqlsrv')
                                                                    ->table('deed_registrations')
                                                                    ->where('id', $realId)
                                                                    ->first();
                                                                $corGenerated = !empty($deedRecord) && $deedRecord->cor_exists == 1;
                                                            } catch (\Exception $e) {
                                                                // Handle error silently
                                                            }
                                                        }
                                                    @endphp
                                                    
                                                    <!-- RDS Actions -->
                                                    <div id="rds-actions-{{ $instrument->id }}">
                                                        <!-- Generate RDS Button -->
                                                        @if(!$rdsGenerated)
                                                            <a href="#" id="gen-rds-{{ $instrument->id }}" onclick="showGenerateRDSModal('{{ $instrument->id }}'); return false;" 
                                                               class="flex items-center gap-2 px-4 py-2 text-sm text-blue-600 hover:bg-blue-50 transition">
                                                                <i data-lucide="file-text" class="w-4 h-4"></i> Generate RDS
                                                            </a>
                                                        @else
                                                            <span class="flex items-center gap-2 px-4 py-2 text-sm text-gray-400 cursor-not-allowed">
                                                                <i data-lucide="file-text" class="w-4 h-4"></i> Generate RDS
                                                                <i data-lucide="check-circle" class="w-3 h-3 text-green-500"></i>
                                                            </span>
                                                        @endif
                                                        
                                                        <!-- View RDS Button -->
                                                        @if($rdsGenerated)
                                                            <a href="#" id="view-rds-{{ $instrument->id }}" onclick="viewRDS('{{ $instrument->id }}'); return false;" 
                                                               class="flex items-center gap-2 px-4 py-2 text-sm text-blue-600 hover:bg-blue-50 transition">
                                                                <i data-lucide="eye" class="w-4 h-4"></i> View RDS
                                                            </a>
                                                        @else
                                                            <span class="flex items-center gap-2 px-4 py-2 text-sm text-gray-400 cursor-not-allowed">
                                                                <i data-lucide="eye" class="w-4 h-4"></i> View RDS
                                                                <i data-lucide="x-circle" class="w-3 h-3 text-red-400"></i>
                                                            </span>
                                                        @endif
                                                    </div>

                                                    <!-- CoR Actions -->
                                                    <div id="cor-actions-{{ $instrument->id }}">
                                                        <!-- Generate CoR Button -->
                                                        @if(!$corGenerated)
                                                            <a href="#" id="gen-cor-{{ $instrument->id }}" onclick="showGenerateCoRModal('{{ $instrument->id }}'); return false;" 
                                                               class="flex items-center gap-2 px-4 py-2 text-sm text-green-600 hover:bg-green-50 transition">
                                                                <i data-lucide="check-square" class="w-4 h-4"></i>
                                                                Generate CoR
                                                            </a>
                                                        @else
                                                            <span class="flex items-center gap-2 px-4 py-2 text-sm text-gray-400 cursor-not-allowed">
                                                                <i data-lucide="check-square" class="w-4 h-4"></i>
                                                                Generate CoR
                                                                <i data-lucide="check-circle" class="w-3 h-3 text-green-500"></i>
                                                            </span>
                                                        @endif
                                                        
                                                        <!-- View CoR Button -->
                                                        @if($corGenerated)
                                                            <a href="#" id="view-cor-{{ $instrument->id }}" onclick="viewCOR('{{ $instrument->id }}'); return false;" 
                                                               class="flex items-center gap-2 px-4 py-2 text-sm text-green-600 hover:bg-green-50 transition">
                                                                <i data-lucide="file-check" class="w-4 h-4"></i>
                                                                View CoR
                                                            </a>
                                                        @else
                                                            <span class="flex items-center gap-2 px-4 py-2 text-sm text-gray-400 cursor-not-allowed">
                                                                <i data-lucide="file-check" class="w-4 h-4"></i>
                                                                View CoR
                                                                <i data-lucide="x-circle" class="w-3 h-3 text-red-400"></i>
                                                            </span>
                                                        @endif
                                                    </div>
                                                @endif
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="15" class="text-center py-4">No instruments found</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                        </div>

                        @if(method_exists($instruments, 'links'))
                            <div class="mt-4">
                                {{ $instruments->links() }}
                            </div>
                        @endif
                    </div>
                </div>
            </main>

            <!-- Update Solicitor's Details -->
            <div id="solicitor-modal" class="hidden fixed inset-0 z-[200] items-center justify-center bg-black/40 p-4">
                <div class="bg-white w-full max-w-3xl rounded-xl shadow-2xl overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <span class="flex items-center justify-center w-8 h-8 rounded-lg bg-indigo-50 text-indigo-600">
                                <i data-lucide="briefcase" class="w-4 h-4"></i>
                            </span>
                            <div>
                                <h2 class="text-sm font-semibold text-gray-800">Update Solicitor's Details</h2>
                                <p class="text-xs text-gray-500" id="solicitor-modal-subtitle">&nbsp;</p>
                            </div>
                        </div>
                        <button type="button" onclick="closeSolicitorModal()" class="text-gray-400 hover:text-gray-600 focus:outline-none">
                            <i data-lucide="x" class="w-5 h-5"></i>
                        </button>
                    </div>

                    <div class="px-6 py-5 max-h-[70vh] overflow-y-auto">
                        <input type="hidden" id="solicitor-instrument-id">

                        {{-- Same fields, order and controls as the Solicitor Details card on the
                             capture screen (instruments/partials/types/sidebars/solicitor_toggle),
                             so an officer correcting a record sees what they captured. --}}
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-3 bg-gray-50/50 p-3 rounded-lg border border-gray-100">
                            <h4 class="md:col-span-3 font-medium text-gray-800 border-b pb-1 mb-1 text-[11px] uppercase tracking-wider">Solicitor Details</h4>

                            <x-instrument-input id="solModalName" label="Name" icon="briefcase"
                                placeholder="Law firm or lawyer name" />

                            <x-instrument-input id="solModalPhone" label="Phone No." icon="phone"
                                placeholder="e.g. 08012345678" :phone="true" />

                            <div>
                                <label for="solModalDistrict" class="block text-sm font-medium text-gray-700 mb-1">District</label>
                                <div class="relative">
                                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                        <i data-lucide="map-pin" class="h-4 w-4 text-gray-400"></i>
                                    </div>
                                    <select id="solModalDistrict" name="solModalDistrict"
                                        class="w-full pl-10 px-4 py-2.5 bg-white border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-100 focus:border-blue-400 outline-none transition-all text-sm appearance-none">
                                        <option value="">Select District</option>
                                        <option value="Other">Other</option>
                                    </select>
                                    <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none">
                                        <i data-lucide="chevron-down" class="h-4 w-4 text-gray-400"></i>
                                    </div>
                                </div>
                                <div id="solModalDistrictOtherWrapper" class="mt-2 hidden">
                                    <input type="text" id="solModalDistrictOther" placeholder="Specify district..."
                                        class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-100 focus:border-blue-400 outline-none transition-all">
                                </div>
                            </div>

                            <x-instrument-select id="solModalState" label="State" icon="map-pin" :options="$states"
                                placeholder="Select State" />

                            <div>
                                <label for="solModalLga" class="block text-sm font-medium text-gray-700 mb-1">LGA</label>
                                <div class="relative">
                                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                        <i data-lucide="map" class="h-4 w-4 text-gray-400"></i>
                                    </div>
                                    <select id="solModalLga" name="solModalLga"
                                        class="w-full pl-10 px-4 py-2.5 bg-white border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-100 focus:border-blue-400 outline-none transition-all text-sm appearance-none">
                                        <option value="">Select LGA</option>
                                    </select>
                                    <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none">
                                        <i data-lucide="chevron-down" class="h-4 w-4 text-gray-400"></i>
                                    </div>
                                </div>
                            </div>

                            <div>
                                <label for="solModalAddress" class="block text-sm font-medium text-gray-700 mb-1">Office Address</label>
                                <div class="relative">
                                    <div class="absolute top-3 left-3 pointer-events-none">
                                        <i data-lucide="map-pin" class="h-4 w-4 text-gray-400"></i>
                                    </div>
                                    <textarea id="solModalAddress" name="solModalAddress" rows="2" readonly
                                        class="w-full pl-10 px-4 py-2.5 bg-gray-50 border border-gray-300 rounded-lg text-sm text-gray-700 outline-none"
                                        placeholder="Auto-generated from selected district, LGA and state"></textarea>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="px-6 py-4 bg-gray-50 border-t border-gray-100 flex items-center justify-end gap-3">
                        <button type="button" onclick="closeSolicitorModal()"
                            class="px-4 py-2 text-sm text-gray-700 font-medium bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition">
                            Cancel
                        </button>
                        <button type="button" id="solicitor-save-btn" onclick="saveSolicitorDetails()"
                            class="px-5 py-2 text-sm text-white font-medium bg-indigo-600 rounded-lg hover:bg-indigo-700 transition flex items-center gap-2 disabled:opacity-60 disabled:cursor-not-allowed">
                            <i data-lucide="save" class="w-4 h-4"></i>
                            <span>Save Changes</span>
                        </button>
                    </div>
                </div>
            </div>

            <!-- JavaScript -->
            @include('instruments.partial.js')
        </div>

        <!-- Footer -->
        @include('admin.footer')
    </div>

    {{-- DataTables JS (jQuery first, then DT, then Buttons) --}}
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.2/js/dataTables.buttons.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.bootstrap5.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/pdfmake.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/vfs_fonts.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.html5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.print.min.js"></script>

    <!-- Reuse Instrument Registration Logic -->
    <script src="{{ asset('js/instrument_registration_index.js') }}"></script>
    <script src="{{ asset('js/property-timeline-modal.js') }}"></script>
    
    <script>
        // ─── DataTables Initialisation ───────────────────────────────────────────
        $(document).ready(function () {
            var table = $('#instrumentsTable').DataTable({
                dom: "<'dt-top-bar'f>t",
                paging: false,
                info: false,
                lengthChange: false,
                order: [[10, 'desc']], // Reg Time/Date descending by default (S/N, PropID, File No occupy 0-2)
                columnDefs: [
                    { orderable: false, targets: [0, -1] }  // S/N and Actions are not sortable
                ],
                // Move the native DT search & export buttons into our header bar
                initComplete: function () {
                    // Render export buttons into the dedicated container
                    new $.fn.dataTable.Buttons(table, {
                        buttons: [
                            {
                                extend: 'copyHtml5',
                                text: '<svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1 inline" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg> Copy',
                                className: 'btn btn-outline btn-sm',
                                exportOptions: { columns: ':not(:last-child)' }
                            },
                            {
                                extend: 'csvHtml5',
                                text: '<svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1 inline" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg> CSV',
                                className: 'btn btn-outline btn-sm',
                                exportOptions: { columns: ':not(:last-child)' }
                            },
                            {
                                extend: 'excelHtml5',
                                text: '<svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1 inline" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414A1 1 0 0119 9.414V19a2 2 0 01-2 2z"/></svg> Excel',
                                className: 'btn btn-outline btn-sm',
                                exportOptions: { columns: ':not(:last-child)' }
                            },
                            {
                                extend: 'pdfHtml5',
                                text: '<svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1 inline" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg> PDF',
                                className: 'btn btn-outline btn-sm',
                                orientation: 'landscape',
                                pageSize: 'A4',
                                exportOptions: { columns: ':not(:last-child)' }
                            },
                            {
                                extend: 'print',
                                text: '<svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1 inline" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg> Print',
                                className: 'btn btn-outline btn-sm',
                                exportOptions: { columns: ':not(:last-child)' }
                            }
                        ]
                    });

                    table.buttons().container().appendTo('#dt-export-buttons');

                    // Re-initialise lucide icons after DT renders
                    if (typeof lucide !== 'undefined') lucide.createIcons();
                }
            });
        });

        // ─── Filter Logic ──────────────────────────────────────────────────────────
        function filterInstrumentsTable() {
            var selectedType = $('#instrumentTypeFilter').val();
            var selectedVolume = $('#volumeFilter').val();
            // The register is server-paged. A DataTables client filter can only
            // see the 20 rendered rows, then falsely says no match while its
            // paginator still advertises the whole register.
            var url = new URL(window.location.href);
            if (selectedType) url.searchParams.set('instrument_type', selectedType); else url.searchParams.delete('instrument_type');
            if (selectedVolume) url.searchParams.set('volume', selectedVolume); else url.searchParams.delete('volume');
            url.searchParams.delete('page');
            window.location.assign(url.toString());
        }

        // Local dropdown toggles for per-row menus on this page.
        function closeAllDropdowns(exceptId) {
            document.querySelectorAll('.dropdown-menu').forEach(menu => {
                if (!exceptId || menu.id !== exceptId) {
                    menu.classList.add('hidden');
                    menu.classList.remove('drop-up');
                }
            });
        }

        function toggleDropdown(dropdownId) {
            const menu = document.getElementById(dropdownId);
            const trigger = document.querySelector(`[data-dropdown-toggle="${dropdownId}"]`);
            if (!menu || !trigger) return;

            const isHidden = menu.classList.contains('hidden');
            closeAllDropdowns(dropdownId);

            if (isHidden) {
                menu.classList.remove('hidden', 'drop-up');

                requestAnimationFrame(() => {
                    const menuRect = menu.getBoundingClientRect();
                    const triggerRect = trigger.getBoundingClientRect();
                    const spaceBelow = window.innerHeight - triggerRect.bottom;
                    const spaceAbove = triggerRect.top;

                    if (menuRect.height > spaceBelow && spaceAbove > menuRect.height) {
                        menu.classList.add('drop-up');
                    }
                });
            } else {
                menu.classList.add('hidden');
                menu.classList.remove('drop-up');
            }
        }

        document.addEventListener('click', function (event) {
            if (event.target.closest('.dropdown-menu')) return;
            if (event.target.closest('[data-dropdown-toggle]')) return;
            closeAllDropdowns();
        });

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                closeAllDropdowns();
                closeSolicitorModal();
            }
        });

        // ─── Update Solicitor's Details ────────────────────────────────────────────
        // Mirrors the Solicitor Details card on the capture screen: District, State
        // and LGA drive a read-only Office Address. Only solicitor_name,
        // solicitor_district and solicitor_address exist as columns - State and LGA
        // survive, as they do at capture, inside the composed address.
        const SOLICITOR_DISTRICTS = @json($solicitorDistricts);

        // Set once the operator touches a location field. Until then the stored
        // address is left exactly as it is, so fixing a misspelt name on a legacy
        // record cannot silently replace a real street address with
        // "District, LGA, State".
        let solicitorAddressDirty = false;

        function solicitorModalEl() {
            return document.getElementById('solicitor-modal');
        }

        function openSolicitorModal() {
            const modal = solicitorModalEl();
            if (!modal) return;
            modal.classList.remove('hidden');
            modal.classList.add('flex');
            if (typeof lucide !== 'undefined') lucide.createIcons();
        }

        function closeSolicitorModal() {
            const modal = solicitorModalEl();
            if (!modal) return;
            modal.classList.add('hidden');
            modal.classList.remove('flex');
        }

        // ~1,800 districts: built the first time the modal opens rather than shipped
        // as option nodes on every listing page load.
        function ensureSolicitorDistrictOptions() {
            const select = document.getElementById('solModalDistrict');
            if (!select || select.dataset.populated === '1') return;

            const other = select.querySelector('option[value="Other"]');
            const frag = document.createDocumentFragment();
            SOLICITOR_DISTRICTS.forEach(name => {
                const opt = document.createElement('option');
                opt.value = name;
                opt.textContent = name;
                frag.appendChild(opt);
            });
            select.insertBefore(frag, other);
            select.dataset.populated = '1';
        }

        function isKanoSolicitorState(value) {
            return String(value || '').trim().toLowerCase() === 'kano';
        }

        function solicitorAddressParts() {
            const districtSelect = document.getElementById('solModalDistrict');
            const district = districtSelect.value === 'Other'
                ? document.getElementById('solModalDistrictOther').value.trim()
                : districtSelect.value.trim();
            const lga = document.getElementById('solModalLga').value.trim();
            const state = document.getElementById('solModalState').value.trim();

            return [district, lga, state].filter(Boolean);
        }

        function updateSolicitorOfficeAddressModal() {
            if (!solicitorAddressDirty) return;
            document.getElementById('solModalAddress').value = solicitorAddressParts().join(', ');
        }

        function handleSolicitorModalDistrictChange() {
            const select = document.getElementById('solModalDistrict');
            const wrapper = document.getElementById('solModalDistrictOtherWrapper');
            const other = document.getElementById('solModalDistrictOther');

            if (select.value === 'Other') {
                wrapper.classList.remove('hidden');
            } else {
                wrapper.classList.add('hidden');
                other.value = '';
            }
            updateSolicitorOfficeAddressModal();
        }

        // Outside Kano the district list does not apply, so the picker is forced to
        // "Other" and the specify box takes over - same rule as the capture card.
        function applySolicitorStateRules() {
            const stateEl = document.getElementById('solModalState');
            const districtEl = document.getElementById('solModalDistrict');

            if (stateEl.value && !isKanoSolicitorState(stateEl.value)) {
                districtEl.value = 'Other';
                districtEl.dataset.forcedOtherForState = '1';
            } else if (districtEl.dataset.forcedOtherForState === '1') {
                districtEl.value = '';
                delete districtEl.dataset.forcedOtherForState;
            }
            handleSolicitorModalDistrictChange();
        }

        function loadSolicitorLgas(state, selected) {
            const select = document.getElementById('solModalLga');
            select.innerHTML = '<option value="">Select LGA</option>';
            if (!state) return Promise.resolve();

            return fetch(window.solicitorEndpointBase + '/get-lgas/' + encodeURIComponent(state), {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
            })
                .then(res => res.json())
                .then(lgas => {
                    (lgas || []).forEach(lga => {
                        const name = lga.LGAName || lga.name || '';
                        if (!name) return;
                        const opt = document.createElement('option');
                        opt.value = name;
                        opt.textContent = name;
                        if (selected && name.toLowerCase() === String(selected).toLowerCase()) opt.selected = true;
                        select.appendChild(opt);
                    });
                })
                .catch(() => { /* an unreachable lookup leaves the LGA list empty, not the modal broken */ });
        }

        function showUpdateSolicitorModal(instrumentId) {
            closeAllDropdowns();
            ensureSolicitorDistrictOptions();
            solicitorAddressDirty = false;

            const cell = document.querySelector('[data-solicitor-cell="' + instrumentId + '"]');
            const row = cell ? cell.closest('tr') : null;
            const fileNoCell = row ? row.querySelector('td:first-child') : null;

            document.getElementById('solicitor-instrument-id').value = instrumentId;
            document.getElementById('solicitor-modal-subtitle').textContent =
                fileNoCell ? fileNoCell.innerText.trim() : '';
            document.getElementById('solModalName').value = '';
            document.getElementById('solModalPhone').value = '';
            document.getElementById('solModalDistrict').value = '';
            document.getElementById('solModalDistrictOther').value = '';
            document.getElementById('solModalDistrictOtherWrapper').classList.add('hidden');
            document.getElementById('solModalState').value = '';
            document.getElementById('solModalLga').innerHTML = '<option value="">Select LGA</option>';
            document.getElementById('solModalAddress').value = '';
            openSolicitorModal();

            fetch(window.solicitorEndpointBase + '/' + instrumentId + '/solicitor', {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
            })
                .then(res => res.json())
                .then(data => {
                    if (!data.success) throw new Error(data.message || 'Unable to load solicitor details.');

                    const sol = data.solicitor;
                    document.getElementById('solModalName').value = sol.name || '';
                    document.getElementById('solModalPhone').value = sol.phone || '';
                    document.getElementById('solModalAddress').value = sol.address || '';

                    // A district the picker does not carry (anything outside Kano)
                    // comes back as "Other" plus the stored text, the same shape the
                    // capture card posts it in.
                    const districtSelect = document.getElementById('solModalDistrict');
                    const district = sol.district || '';
                    const known = district && Array.from(districtSelect.options).some(o => o.value === district);
                    if (district && !known) {
                        districtSelect.value = 'Other';
                        document.getElementById('solModalDistrictOther').value = district;
                        document.getElementById('solModalDistrictOtherWrapper').classList.remove('hidden');
                    } else {
                        districtSelect.value = district;
                    }

                    document.getElementById('solModalState').value = sol.state || '';
                    document.getElementById('solModalName').focus();

                    return loadSolicitorLgas(sol.state, sol.lga);
                })
                .catch(err => {
                    closeSolicitorModal();
                    Swal.fire({ icon: 'error', title: 'Could not load', text: err.message });
                });
        }

        function saveSolicitorDetails() {
            const instrumentId = document.getElementById('solicitor-instrument-id').value;
            if (!instrumentId) return;

            const btn = document.getElementById('solicitor-save-btn');
            btn.disabled = true;

            const payload = {
                solicitorName: document.getElementById('solModalName').value,
                solicitorPhone: document.getElementById('solModalPhone').value,
                solicitorDistrict: document.getElementById('solModalDistrict').value,
                solicitorDistrictOther: document.getElementById('solModalDistrictOther').value,
                solicitorState: document.getElementById('solModalState').value,
                solicitorLga: document.getElementById('solModalLga').value,
                solicitorAddress: document.getElementById('solModalAddress').value
            };

            fetch(window.solicitorEndpointBase + '/' + instrumentId + '/solicitor', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                },
                body: JSON.stringify(payload)
            })
                .then(res => res.json().then(data => ({ ok: res.ok, data })))
                .then(result => {
                    if (!result.ok || !result.data.success) {
                        throw new Error(result.data.message || 'Failed to update solicitor details.');
                    }

                    updateSolicitorCell(instrumentId, result.data.solicitor);
                    closeSolicitorModal();
                    Swal.fire({
                        icon: 'success',
                        title: 'Updated',
                        text: result.data.message,
                        timer: 2000,
                        showConfirmButton: false
                    });
                })
                .catch(err => {
                    Swal.fire({ icon: 'error', title: 'Update failed', text: err.message });
                })
                .finally(() => {
                    btn.disabled = false;
                });
        }

        // Repaint the row in place - the listing is paginated and server-sorted, so a
        // reload would throw the operator back to page 1 after every correction.
        function updateSolicitorCell(instrumentId, solicitor) {
            const cell = document.querySelector('[data-solicitor-cell="' + instrumentId + '"]');
            if (!cell) return;

            const toProper = function (value) {
                return (value || '').replace(/[^\s]+/g, function (w) {
                    return w.charAt(0).toUpperCase() + w.substring(1).toLowerCase();
                });
            };

            const nameEl = cell.querySelector('[data-solicitor-name]');
            const phoneEl = cell.querySelector('[data-solicitor-phone]');
            const addressEl = cell.querySelector('[data-solicitor-address]');
            if (nameEl) nameEl.textContent = toProper(solicitor.name);
            if (phoneEl) {
                phoneEl.textContent = solicitor.phone || '';
                phoneEl.classList.toggle('hidden', !solicitor.phone);
            }
            if (addressEl) addressEl.textContent = toProper(solicitor.address);

            // Keep the in-page copy in step so exports and the RDS/CoR helpers that
            // read window.serverCofoData do not hand back the pre-edit values.
            if (Array.isArray(window.serverCofoData)) {
                const row = window.serverCofoData.find(function (item) {
                    return String(item.id) === String(instrumentId);
                });
                if (row) {
                    row.solicitor_name = solicitor.name;
                    row.solicitor_phone = solicitor.phone;
                    row.solicitor_district = solicitor.district;
                    row.solicitor_address = solicitor.address;
                }
            }
        }

        document.addEventListener('DOMContentLoaded', function () {
            const districtEl = document.getElementById('solModalDistrict');
            const districtOtherEl = document.getElementById('solModalDistrictOther');
            const stateEl = document.getElementById('solModalState');
            const lgaEl = document.getElementById('solModalLga');
            if (!districtEl || !stateEl || !lgaEl) return;

            const markDirty = function () { solicitorAddressDirty = true; };

            districtEl.addEventListener('change', function () {
                markDirty();
                handleSolicitorModalDistrictChange();
            });
            districtOtherEl.addEventListener('input', function () {
                markDirty();
                updateSolicitorOfficeAddressModal();
            });
            stateEl.addEventListener('change', function () {
                markDirty();
                applySolicitorStateRules();
                loadSolicitorLgas(this.value, '').then(updateSolicitorOfficeAddressModal);
            });
            lgaEl.addEventListener('change', function () {
                markDirty();
                updateSolicitorOfficeAddressModal();
            });
        });

        // Backdrop click closes, clicks inside the card do not.
        document.addEventListener('click', function (event) {
            if (event.target === solicitorModalEl()) closeSolicitorModal();
        });
    </script>

    <style>
        /* ── DataTables layout overrides ─────────────────────────────── */
        .dt-top-bar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0.5rem 0 1rem;
            flex-wrap: wrap;
            gap: 0.5rem;
        }
        .dt-bottom-bar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 1rem 0 0;
            flex-wrap: wrap;
            gap: 0.5rem;
        }

        /* Search Input */
        div.dataTables_filter input {
            padding: 0.45rem 0.75rem;
            font-size: 0.875rem;
            border: 1px solid #e5e7eb;
            border-radius: 0.375rem;
            background-color: white;
            outline: none;
            margin-left: 0.5rem;
        }
        div.dataTables_filter input:focus {
            border-color: #111827;
            box-shadow: 0 0 0 2px rgba(17,24,39,0.12);
        }
        div.dataTables_filter label {
            font-size: 0.875rem;
            color: #4b5563;
        }

        /* Length dropdown */
        div.dataTables_length select {
            padding: 0.45rem 1.75rem 0.45rem 0.75rem;
            font-size: 0.875rem;
            border: 1px solid #e5e7eb;
            border-radius: 0.375rem;
            background-color: white;
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%239ca3af'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M19 9l-7 7-7-7'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 0.5rem center;
            background-size: 1rem;
        }
        div.dataTables_length label {
            font-size: 0.875rem;
            color: #4b5563;
        }

        /* Info text */
        div.dataTables_info {
            font-size: 0.875rem;
            color: #6b7280;
        }

        /* Pagination */
        div.dataTables_paginate .paginate_button {
            padding: 0.4rem 0.7rem;
            margin: 0 1px;
            border: 1px solid #e5e7eb;
            border-radius: 0.375rem;
            font-size: 0.875rem;
            cursor: pointer;
            background: white;
            color: #4b5563 !important;
        }
        div.dataTables_paginate .paginate_button:hover {
            background: #f9fafb !important;
            color: #111827 !important;
            border-color: #d1d5db !important;
        }
        div.dataTables_paginate .paginate_button.current,
        div.dataTables_paginate .paginate_button.current:hover {
            background: #111827 !important;
            color: white !important;
            border-color: #111827 !important;
        }
        div.dataTables_paginate .paginate_button.disabled,
        div.dataTables_paginate .paginate_button.disabled:hover {
            color: #d1d5db !important;
            background: white !important;
            cursor: default;
        }

        /* Export buttons strip */
        .dt-buttons .btn {
            font-size: 0.8rem;
            padding: 0.35rem 0.7rem;
            border-radius: 0.375rem;
        }
        .dt-buttons .btn + .btn {
            margin-left: 4px;
        }

        /* Table overrides */
        .table-container {
            position: relative;
            overflow: visible !important;
        }
        table th, table td {
            vertical-align: top !important;
            text-align: left !important;
            padding: 1rem 0.75rem !important;
        }
        .dropdown-menu {
            position: absolute;
            z-index: 100;
        }

        /* Sorting arrows colour */
        table.dataTable thead th.sorting:after,
        table.dataTable thead th.sorting_asc:after,
        table.dataTable thead th.sorting_desc:after {
            color: #9ca3af;
        }
    </style>
@endsection
