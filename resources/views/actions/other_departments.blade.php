@extends('layouts.app')
@section('page-title')
    {{ __('SECTIONAL TITLING  MODULE') }}
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
                      <h2 class="text-lg font-medium">Other Departments</h2>
                      <button  type="button"  class="text-gray-500 hover:text-gray-700" onclick="window.history.back()">
                        <i data-lucide="x" class="w-5 h-5"></i>
                      </button>
                    </div>
                    
                    <div class="py-2">
                      <div class="flex items-center justify-between mb-4">
                        <div>
                          <h3 class="text-sm font-medium">{{$application->land_use }} Property</h3>
                          <p class="text-xs text-gray-500">
                            Application ID: {{$application->applicationID}} | File No: {{$application->fileno }}  
                          </p>
                        </div>
                        <div class="text-right">
                          <h3 class="text-sm font-medium">
                                         @if ($application->applicant_type == 'individual')
                          {{ $application->applicant_title }} {{ $application->first_name }}
                          {{ $application->surname }}
                        @elseif($application->applicant_type == 'corporate')
                          {{ $application->rc_number }} {{ $application->corporate_name }}
                        @elseif($application->applicant_type == 'multiple')
                          @php
                            $names = @json_decode($application->multiple_owners_names, true);
                            if (is_array($names) && count($names) > 0) {
                              echo implode(', ', $names);
                            } else {
                              echo $application->multiple_owners_names;
                            }
                          @endphp
                        @endif
    

                          
                          </h3>
                          <p class="text-xs text-gray-500">
                          <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800">
                            {{$application->land_use }}
                          </span>
                          </p>
                        </div>
                      </div>
                
                      <!-- Tabs Navigation -->
                      
                  

                      <div class="grid grid-cols-3 gap-2 mb-4">
                        <button class="tab-button active" data-tab="initial">
                          <i data-lucide="banknote" class="w-3.5 h-3.5 mr-1.5"></i>
                          SURVEY
                        </button>
                        <button class="tab-button" data-tab="detterment">
                          <i data-lucide="calculator" class="w-3.5 h-3.5 mr-1.5"></i>
                         DEEDS
                        </button>
                        <button class="tab-button" data-tab="final">
                          <i data-lucide="file-check" class="w-3.5 h-3.5 mr-1.5"></i>
                          LANDS
                        </button>
                      </div>
                      <!-- Survey Tab -->
                      @include('actions.other_departments.survey')
                      <!-- Deeds Tab -->
                      @include('actions.other_departments.deed')
                      <!-- Deeds Tab -->
                      <!-- land Tab -->
                      @include('actions.other_departments.lands')
                    </div>
                  </div>

                <!-- Footer -->
                @include('admin.footer')
            </div>
            
          
          @include('actions.other_departments.js')
    
        @endsection


