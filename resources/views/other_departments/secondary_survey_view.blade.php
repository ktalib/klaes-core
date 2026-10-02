@extends('layouts.app')
@section('page-title')
   {{$PageTitle}}
@endsection

<style>

   
    .tab-content {
      display: none;
    }
    .tab-content.active {
      display: block;
    }
    .tab-button {
      position: relative;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      font-size: 0.75rem;
      padding: 0.5rem 1rem;
      border-radius: 0.25rem;
      cursor: pointer;
      transition: background-color 0.2s;
    }
    .tab-button.active {
      background-color: #f3f4f6;
      font-weight: 500;
    }
    .tab-button:hover:not(.active) {
      background-color: #f9fafb;
    }
  </style>
@include('sectionaltitling.partials.assets.css')
@section('content')
   @php
    use Illuminate\Support\Facades\DB;
    $deeds = DB::connection('sqlsrv')->table('landAdministration')->where('application_id', $application->id)->first();  
     @endphp
    <div class="flex-1 overflow-auto">
        <!-- Header -->
        @include('admin.header')
        <!-- Dashboard Content -->
        <div class="p-6">
           
          
            <div class="bg-white rounded-md shadow-sm border border-gray-200 p-6">
              

                <div class="modal-content p-6">
                    <div class="flex justify-between items-center mb-4">
                      <h2 class="text-lg font-medium">Survey</h2>
                      <button  type="button"  class="text-gray-500 hover:text-gray-700" onclick="window.history.back()">
                        <i data-lucide="x" class="w-5 h-5"></i>
                      </button>
                    </div>
                    
                    <div class="py-2">
              
                    <div class="py-2">
                  <div class="flex flex-col md:flex-row items-center justify-between mb-6 bg-gray-50 border border-gray-200 rounded-lg p-4 shadow-sm">
                        <div class="flex-1 mb-4 md:mb-0">
                          <h3 class="text-base font-semibold text-gray-800 flex items-center gap-2">
                            <i data-lucide="home" class="w-5 h-5 text-blue-500"></i>
                            {{ $application->land_use }} Property
                          </h3>
                          <div class="flex flex-wrap gap-2 mt-2 text-xs text-gray-500">
                            <span class="inline-flex items-center gap-1">
                              <i data-lucide="hash" class="w-4 h-4"></i>
                              Mother FileNo: <span class="font-medium text-gray-700">{{ $application->primary_fileno ?? 'N/A' }}</span>
                            </span>
                            <span class="inline-flex items-center gap-1">
                              <i data-lucide="folder" class="w-4 h-4"></i>
                              ST FileNo: <span class="font-medium text-gray-700">{{ $application->fileno }}</span>
                            </span>
                          </div>
                        </div>
                        <div class="flex-1 text-right">
                          <h3 class="text-base font-semibold text-gray-800">
                            @if($application->applicant_type == 'individual')
                          {{$application->applicant_title }} {{$application->first_name }} {{$application->surname }}
                          @elseif($application->applicant_type == 'corporate')
                          {{$application->rc_number }} {{$application->corporate_name }}
                          @elseif($application->applicant_type == 'multiple')
                          {{$application->multiple_owners_names }}
                          @endif
                          </h3>
                          <span class="inline-flex items-center px-3 py-1 mt-2 rounded-full text-xs font-semibold bg-green-100 text-green-700 border border-green-200">
                            <i data-lucide="map-pin" class="w-4 h-4 mr-1"></i>
                            {{ $application->land_use }}
                          </span>
                        </div>
                      </div>
                      <!-- Tabs Navigation -->
                      
                      @php
                        // Determine which tab should be active based on URL parameters
                        $activeTab = 'initial'; // Default to Survey tab
                        
                        if(request()->has('deeds')) {
                            $activeTab = 'detterment';
                        } elseif(request()->has('lands')) {
                            $activeTab = 'final';
                        } elseif(request()->has('survey')) {
                            $activeTab = 'initial';
                        }
                      @endphp

            
                      <!-- Survey Tab -->
                      @include('other_departments.partials.sec_survey')
                      <!-- Deeds Tab -->
                    </div>
                  </div>

                <!-- Footer -->
                @include('admin.footer')
            </div>
            
          
          @include('other_departments.partials.js')
    
        @endsection



        