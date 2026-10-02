@extends('layouts.app')
@section('page-title')
    {{ __('Certificate of Occupancy (CofO) Records') }}
@endsection
@section('content')
@include('propertycard.css.style')
<style>
    .timeline-badge {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 36px;
        padding: 0.15rem 0.65rem;
        border-radius: 9999px;
        font-size: 0.75rem;
        font-weight: 600;
        border-width: 1px;
        border-style: solid;
        cursor: pointer;
        line-height: 1.1;
    }
    .timeline-tier-1 {
        color: #1e40af;              /* blue-800 */
        background-color: #eff6ff;   /* blue-50  */
        border-color: #bfdbfe;       /* blue-200 */
    }
    .timeline-badge:hover {
        background-color: #dbeafe;   /* blue-100 */
    }
</style>
    <!-- Main Content --> 
    <div class="flex-1 overflow-auto">
        <!-- Header -->
        @include('admin.header')
        <!-- Dashboard Content -->
        <div class="p-6"> 
            <div class="container mx-auto py-6 space-y-6">
                <!-- Page Header -->
                <div class="flex flex-col space-y-2">
                    <!-- <h1 class="text-3xl font-bold tracking-tight text-green-800">Other Instruments</h1> -->
                    <!-- <p class="text-gray-500">Manage and view Certificate of Occupancy records</p> -->
                </div>
        
                <div class="flex items-center justify-between mb-4">
                    <a href="{{ route('propertycard.index') }}" class="flex items-center text-gray-600 hover:text-gray-800">
                        <i class="fas fa-arrow-left mr-2"></i> Back to Property Records
                    </a>
                    <a href="{{ route('propertycard.ai') }}" class="ml-3 flex items-center text-gray-600">
                        <i class="fas fa-robot mr-2"></i> AI Assistant
                    </a>
                </div>

                <!-- CofO Records Content -->
                <div class="bg-white rounded-lg shadow-sm border border-gray-200">
                    <div class="card">
                        <div class="card-header">
                            <h2 class="text-lg font-medium text-green-800">Certificate of Occupancy (CofO) Records</h2>
                            <div class="flex items-center gap-2">
                                <input type="text" id="cofo-search" class="form-input w-64" placeholder="Search CofO records...">
                                <button id="reset-cofo-cards-view" class="btn btn-secondary" style="display: none;">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4 mr-2">
                                        <path d="M3 12a9 9 0 0 1 9-9 9.75 9.75 0 0 1 6.74 2.74L21 8"></path>
                                        <path d="M21 3v5h-5"></path>
                                        <path d="M21 12a9 9 0 0 1-9 9 9.75 9.75 0 0 1-6.74-2.74L3 16"></path>
                                        <path d="M3 21v-5h5"></path>
                                    </svg>
                                    Reset View
                                </button>
                                <span id="cofo-search-status" class="hidden"></span>
                            </div>
                        </div>
                        <div class="card-body">
                            <!-- CofO Cards -->
                            <div class="grid grid-cols-1 gap-4 mb-6" id="cofo-cards-container">
                                <!-- Selected CofO Detail Card will be injected here by JS -->
                                <div id="selected-cofo-detail-card" class="col-span-1">
                                    @php
                                        $cofoRecord = ($cofoRecords ?? collect())->first();
                                    @endphp
                                    @if($cofoRecord)
                                        <div class="border rounded-lg shadow-lg overflow-hidden bg-green-50 border-green-200">
                                            <div class="bg-green-100 p-4 border-b border-green-200">
                                                <div class="flex justify-between items-center">
                                                    <span class="bg-green-200 text-green-800 border-green-300 px-3 py-1 rounded-full text-sm font-medium">
                                                        {{ $cofoRecord->cofo_type ?? 'CofO' }} - Selected Record
                                                    </span>
                                                    <span class="text-xs text-gray-500">Actions disabled</span>
                                                </div>
                                                <h3 class="mt-2 font-bold text-lg text-green-900">
                                                    @if($cofoRecord->kangisFileNo)
                                                        {{ $cofoRecord->kangisFileNo }}
                                                    @elseif($cofoRecord->mlsFNo)
                                                        {{ $cofoRecord->mlsFNo }}
                                                    @elseif($cofoRecord->NewKANGISFileno)
                                                        {{ $cofoRecord->NewKANGISFileno }}
                                                    @else
                                                        No File Number
                                                    @endif
                                                </h3>
                                            </div>
                                            <div class="p-4">
                                                <div class="space-y-4">
                                                    <div class="text-sm">
                                                        <strong>Description:</strong> {{ $cofoRecord->property_description ?? 'No description available' }}
                                                    </div>
                                                    <div class="grid grid-cols-2 gap-4 text-sm">
                                                        <div>
                                                            <strong>LGA/City:</strong> {{ $cofoRecord->lgsaOrCity ?? 'N/A' }}
                                                        </div>
                                                        <div>
                                                            <strong>Plot Number:</strong> {{ $cofoRecord->plot_no ?? 'N/A' }}
                                                        </div>
                                                        <div>
                                                            <strong>Layout:</strong> {{ $cofoRecord->layout ?? 'N/A' }}
                                                        </div>
                                                        <div>
                                                            <strong>Location:</strong> {{ $cofoRecord->location ?? 'N/A' }}
                                                        </div>
                                                        <div>
                                                            <strong>Land Use:</strong> {{ $cofoRecord->land_use ?? 'N/A' }}
                                                        </div>
                                                        <div>
                                                            <strong>CofO Type:</strong> {{ $cofoRecord->cofo_type ?? 'N/A' }}
                                                        </div>
                                                    </div> 
             
                                                    <div class="border-t pt-3">
                                                        <div class="grid grid-cols-2 gap-4 text-sm">
                                                            <div>
                                                                <strong>Transaction Type:</strong> {{ $cofoRecord->transaction_type ?? 'N/A' }}
                                                            </div>
                                                            <div>
                                                                <strong>Transaction Date:</strong> {{ $cofoRecord->transaction_date ? \Carbon\Carbon::parse($cofoRecord->transaction_date)->toFormattedDateString() : 'N/A' }}
                                                            </div>
                                                            <div>
                                                                <strong>Registration No:</strong> {{ $cofoRecord->regNo ?? 'N/A' }}
                                                            </div>
                                                            <div>
                                                                <strong>Instrument Type:</strong> {{ $cofoRecord->instrument_type ?? 'N/A' }}
                                                            </div>
                                                        </div>
                                                    </div>
                                                    @php
                                                        $fromParty = $toParty = $fromLabel = $toLabel = '';
                                                        switch(strtolower($cofoRecord->transaction_type ?? '')) {
                                                            case 'assignment':
                                                                $fromParty = $cofoRecord->Assignor ?? '';
                                                                $toParty = $cofoRecord->Assignee ?? '';
                                                                $fromLabel = 'Assignor';
                                                                $toLabel = 'Assignee';
                                                                break;
                                                            case 'mortgage':
                                                                $fromParty = $cofoRecord->Mortgagor ?? '';
                                                                $toParty = $cofoRecord->Mortgagee ?? '';
                                                                $fromLabel = 'Mortgagor';
                                                                $toLabel = 'Mortgagee';
                                                                break;
                                                            case 'surrender':
                                                                $fromParty = $cofoRecord->Surrenderor ?? '';
                                                                $toParty = $cofoRecord->Surrenderee ?? '';
                                                                $fromLabel = 'Surrenderor';
                                                                $toLabel = 'Surrenderee';
                                                                break;
                                                            case 'sub-lease':
                                                            case 'lease':
                                                                $fromParty = $cofoRecord->Lessor ?? '';
                                                                $toParty = $cofoRecord->Lessee ?? '';
                                                                $fromLabel = 'Lessor';
                                                                $toLabel = 'Lessee';
                                                                break;
                                                            default:
                                                                $fromParty = $cofoRecord->Grantor ?? '';
                                                                $toParty = $cofoRecord->Grantee ?? '';
                                                                $fromLabel = 'Grantor';
                                                                $toLabel = 'Grantee';
                                                        }
                                                    @endphp
                                                    @if($fromParty || $toParty)
                                                    <div class="border-t pt-3">
                                                        <div class="grid grid-cols-2 gap-4 text-sm">
                                                            @if($fromParty)
                                                                <div><strong>{{ $fromLabel }}:</strong> {{ $fromParty }}</div>
                                                            @endif
                                                            @if($toParty)
                                                                <div><strong>{{ $toLabel }}:</strong> {{ $toParty }}</div>
                                                            @endif
                                                        </div>
                                                    </div>
                                                    @endif
                                                </div>
                                            </div>
                                            <div class="p-4 pt-0 flex justify-between border-t bg-white">
                                                <div class="text-xs text-gray-500">
                                                    <div>File Numbers:</div>
                                                    @if($cofoRecord->mlsFNo)
                                                        <div>MLPP: {{ $cofoRecord->mlsFNo }}</div>
                                                    @endif
                                                    @if($cofoRecord->kangisFileNo)
                                                        <div>KANGIS: {{ $cofoRecord->kangisFileNo }}</div>
                                                    @endif
                                                    @if($cofoRecord->NewKANGISFileno)
                                                        <div>New KANGIS: {{ $cofoRecord->NewKANGISFileno }}</div>
                                                    @endif
                                                </div>
                                                <div class="flex gap-2 text-xs text-gray-400 italic">
                                                    Actions are disabled for now.
                                                    @if(!empty($cofoRecord->prop_id))
                                                    <button type="button"
                                                        class="ml-2 px-3 py-1 border rounded-md text-xs flex items-center bg-indigo-600 text-white hover:bg-indigo-700 not-italic"
                                                        onclick="openPropertyTimeline('{{ $cofoRecord->prop_id }}', '{{ $cofoRecord->kangisFileNo ?? $cofoRecord->mlsFNo ?? $cofoRecord->NewKANGISFileno ?? '' }}')">
                                                        <i class="fas fa-history mr-1"></i> Timeline
                                                    </button>
                                                    @endif
                                                </div>
                                            </div>
                                        </div>
                                    @else
                                        <div class="border rounded-lg shadow-lg overflow-hidden bg-gray-50 border-gray-200 p-8 text-center">
                                            <div class="text-gray-500">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="mx-auto mb-4 text-gray-400">
                                                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                                                    <polyline points="14,2 14,8 20,8"></polyline>
                                                    <line x1="16" y1="13" x2="8" y2="13"></line>
                                                    <line x1="16" y1="17" x2="8" y2="17"></line>
                                                    <polyline points="10,9 9,9 8,9"></polyline>
                                                </svg>
                                                <h3 class="text-lg font-medium text-gray-900 mb-2">No CofO Records Found</h3>
                                                <p class="text-gray-600">Start by adding your first Certificate of Occupancy record.</p>
                                            </div>
                                        </div>
                                    @endif
                                </div>
                            </div>
<br>
<hr>
<a href="{{ route('propertycard.index') }}" class="inline-flex items-center px-4 py-2 bg-green-600 text-white text-sm font-medium rounded-md shadow-sm hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 transition duration-150 ease-in-out mr-3">
    <i class="fas fa-home mr-2"></i>View Records
</a>
                <br>
                            <!-- CofO Table -->
                            <div class="table-container">
                                <table id="cofo-records-table" class="table">
                                    <thead>
                                        <tr>
                                            <th>File Number</th>
                                            <th>Description</th>
                                            <th>Location</th>
                                            <th>Registration Particulars</th>
                                            <th>Land Use</th>
                                            <th>Instrument Type</th>
                                            <th>Grantor</th>
                                            <th>Grantee</th>
                                            <th>DATE CAPTURED</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
           
            </div>
        

            <!-- Property Modal Dialogs -->
            @include('propertycard.partials.add_property_record')
            @include('propertycard.partials.edit_property_record')
            @include('propertycard.partials.view_property_record')
            @include('propertycard.partials.view_cofo_record')
        </div>
        <!-- Footer -->
        @include('admin.footer')
    </div>
    
    <!-- Include JavaScript -->
    @include('propertycard.js.cofo_javascript')
    @include('propertycard.partials.property_form_sweetalert')
    <script src="{{ asset('js/property-timeline-modal.js') }}"></script>
@endsection