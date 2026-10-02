@extends('layouts.app')
@section('page-title')
   Survey
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
                      @include('other_departments.partials.header')
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
                      @include('other_departments.partials.survey')
                      <!-- Deeds Tab -->
                    </div>
                  </div>

                <!-- Footer -->
                @include('admin.footer')
            </div>
            
          
          @include('other_departments.partials.js')
    
        @endsection



        