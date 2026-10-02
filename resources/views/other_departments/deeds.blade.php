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
    // Only query landAdministration for primary applications
    if(!isset($isSecondary) || !$isSecondary) {
        $deeds = DB::connection('sqlsrv')
            ->table('landAdministration')
            ->where('application_id', $application->id)
            ->first();
    }
    // For secondary applications, $deeds should already be set in the controller
   @endphp
    <div class="flex-1 overflow-auto">
        <!-- Header -->
        @include('admin.header')
        <!-- Dashboard Content -->
        <div class="p-6">
           
          
            <div class="bg-white rounded-md shadow-sm border border-gray-200 p-6">
              

                <div class="modal-content p-6">
                    <div class="flex justify-between items-center mb-4">
                      <h2 class="text-lg font-medium">Deeds</h2>
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
                      @include('other_departments.partials.deed')
                      <!-- Deeds Tab -->
                    </div>
                  </div>

                <!-- Footer -->
                @include('admin.footer')
            </div>
            
          <!-- Debugging scripts -->
          <script>
            document.addEventListener('DOMContentLoaded', function() {
              console.log('DOM content loaded in main view');
              
              // Check for edit button in the DOM
              const editButton = document.getElementById('edit-deeds');
              if (editButton) {
                console.log('Edit button found in main view:', editButton);
                
                // Add a direct event listener
                editButton.onclick = function() {
                  console.log('Edit button clicked directly!');
                  
                  // Enable all deed fields
                  const deedFields = document.querySelectorAll('.deed-field');
                  deedFields.forEach(field => {
                    field.disabled = false;
                    field.classList.remove('bg-gray-100', 'text-gray-500', 'cursor-not-allowed');
                    field.classList.add('bg-white');
                  });
                  
                  // Show the submit button
                  const submitBtn = document.getElementById('submit-deeds');
                  submitBtn.classList.remove('hidden');
                  
                  // Hide the edit button
                  this.classList.add('hidden');
                };
              } else {
                console.error('Edit button not found in the main view!');
              }
            });
          </script>
          
          @include('other_departments.partials.js')
        </div>
    </div>
@endsection



