@extends('layouts.app')
@section('page-title')
    {{ __('Other Departments') }}
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
    <div class="flex-1 overflow-auto">
        <!-- Header -->
        @include('admin.header')
        <!-- Dashboard Content -->
        <div class="p-6">
           
          
            <div class="bg-white rounded-md shadow-sm border border-gray-200 p-6">
              

                <div class="modal-content p-6">
                    <div class="flex justify-between items-center mb-4">
                      @if(request()->query('is') === 'survey')
                        <h2 class="text-lg font-medium">ST Survey - Survey Department</h2>
                      @else
                        <h2 class="text-lg font-medium">Other Departments</h2>
                      @endif
                      <button   class="text-gray-500 hover:text-gray-700" onclick="window.history.back()">
                        <i data-lucide="x" class="w-5 h-5"></i>
                      </button>
                    </div>
                    
                    <div class="py-2">
                        <div class="bg-gray-50 border border-gray-200 rounded-lg p-4 mb-4">
                        <!-- Primary Application Info (First, as requested) -->
                        <div class="flex items-center mb-3">
                          <div class="bg-blue-100 text-blue-800 rounded-full p-1 mr-2">
                          <i data-lucide="file-check" class="w-4 h-4"></i>
                          </div>
                          <div>
                          <h3 class="text-sm font-medium text-blue-800">Primary Application</h3>
                          <p class="text-xs text-gray-700">
                            {{ $application->primary_applicant_title ?? '' }} {{ $application->primary_first_name ?? '' }} {{ $application->primary_surname ?? '' }}
                            <span class="inline-flex items-center px-2 py-0.5 ml-1 rounded text-xs font-medium bg-blue-50 text-blue-700 border border-blue-200">
                            <i data-lucide="link" class="w-3 h-3 mr-1"></i>File No: {{ $application->primary_fileno ?? 'N/A' }}
                            </span>
                          </p>
                          </div>
                        </div>
                        
                        <!-- Current Application Info -->
                        <div class="flex justify-between items-center border-t border-gray-200 pt-3">
                          <div>
                          <h3 class="text-sm font-medium">{{ $application->land_use ?? 'Property' }}</h3>
                          <p class="text-xs text-gray-600 mt-1">
                            File No: <span class="font-medium">{{ $application->fileno ?? 'N/A' }}</span>
                          </p>
                          </div>

                       
                              @php
                          $assignment = \DB::connection('sqlsrv')
                            ->table('Sectional_title_transfer')
                            ->where('application_id', $application->primary_id)
                            ->select('serial_no', 'page_no', 'volume_no')
                              ->first();
                          @endphp
                        
                        
                          </div>

                       
                        

                          <div class="text-right">
                          <h3 class="text-sm font-medium">{{ $application->applicant_title ?? '' }} {{ $application->surname ?? '' }} {{ $application->first_name ?? '' }}</h3>
                          <p class="text-xs text-gray-600 mt-1">Applicant</p>
                          </div>
                        </div>
                        </div>
                
                      <!-- Tabs Navigation -->
              


                      <div class="grid grid-cols-3 gap-2 mb-4">
                        <button class="tab-button active" data-tab="initial">
                          <i data-lucide="banknote" class="w-3.5 h-3.5 mr-1.5"></i>
                          SURVEY
                        </button>

                        @if(request()->query('is') !== 'survey')
                          <button class="tab-button" data-tab="detterment">
                            <i data-lucide="calculator" class="w-3.5 h-3.5 mr-1.5"></i>
                            DEEDS
                          </button>
                          <button class="tab-button" data-tab="final">
                            <i data-lucide="file-check" class="w-3.5 h-3.5 mr-1.5"></i>
                            LANDS
                          </button>
                        @endif

                      </div>

                      @include('actions.other_departments.survey')
                        <!-- Tab Content for Deeds and Final Bill -->                      

                        <!-- Deeds Tab -->
                        @php
                        use Illuminate\Support\Facades\DB;
                        $deeds = DB::connection('sqlsrv')->table('SectionalCofOReg')->where('sub_application_id', $application->id)->first(); // Fetch SectionalCofOReg data for the application
                        $assignment = DB::connection('sqlsrv')
                            ->table('Sectional_title_transfer')
                            ->where('application_id', $application->primary_id)
                            ->first();
                        @endphp
                        <form id="deeds-form" method="POST">
                        @csrf
                        <div id="detterment-tab" class="tab-content">
                          <div class="bg-white border border-gray-200 rounded-lg shadow-sm">
                            <div class="p-4 border-b">
                              <h3 class="text-sm font-medium">Deeds</h3>
                              <p class="text-xs text-gray-500"> </p>
                            </div>
                            <input type="hidden" name="application_id" value="{{$application->id}}">
                            <input type="hidden" name="fileno" value="{{$application->fileno}}">
                            
                            <!-- Nested Tab Navigation -->
                            <div class="flex border-b px-4 pt-2 space-x-2">
                              <button type="button" class="nested-tab-button active px-4 py-2 text-xs font-medium border-b-2 border-blue-500 text-blue-600 hover:text-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500" data-nested-tab="assignment-content">
                              Assignment Reg Particulars
                              </button>
                              <button type="button" class="nested-tab-button px-4 py-2 text-xs font-medium border-b-2 border-transparent text-gray-600 hover:text-gray-800 hover:border-gray-300 focus:outline-none focus:ring-2 focus:ring-gray-300" data-nested-tab="cofo-content">
                              CofO Reg Particular
                              </button>
                            </div>
                            <br>
                            <!-- Assignment Reg Particulars Tab Content -->
                            <div id="assignment-content" class="nested-tab-content active">
                              <div class="p-4 space-y-4">
                                <div class="grid grid-cols-3 gap-4">
                                  <div class="space-y-2">
                                    <label for="assignment-serial-no" class="text-xs font-medium block">
                                      Serial No
                                    </label>
                                    <input id="assignment-serial-no" name="assignment_serial_no" type="text" 
                                    class="w-full p-2 border border-gray-300 rounded-md text-sm bg-gray-100 cursor-not-allowed text-gray-600" value="{{ $assignment->serial_no ?? '' }}" readonly>
                                  </div>
                                  <div class="space-y-2">
                                    <label for="assignment-page-no" class="text-xs font-medium block">
                                      Page No
                                    </label>
                                    <input id="assignment-page-no" name="assignment_page_no" type="text"   
                                    class="w-full p-2 border border-gray-300 rounded-md text-sm bg-gray-100 cursor-not-allowed text-gray-600" value="{{ $assignment->page_no ?? '' }}" readonly>
                                  </div>
                                  <div class="space-y-2">
                                    <label for="assignment-volume-no" class="text-xs font-medium block">
                                      Volume No
                                    </label>
                                    <input id="assignment-volume-no" name="assignment_volume_no" type="text"  
                                    class="w-full p-2 border border-gray-300 rounded-md text-sm bg-gray-100 cursor-not-allowed text-gray-600" value="{{ $assignment->volume_no ?? '' }}" readonly>
                                  </div>
                                </div>
                                
                                <div class="grid grid-cols-2 gap-4">
                                 
                                  <div class="space-y-2">
                                    <label for="assignment-reg-time" class="text-xs font-medium block">
                                      Registration Time
                                    </label>
                                    <input id="assignment-reg-time" name="assignment_reg_time" type="text" 
                                    class="w-full p-2 border border-gray-300 rounded-md text-sm bg-gray-100 cursor-not-allowed text-gray-600" value="{{ $assignment->deeds_time ?? '' }}" readonly>
                                  </div>

                                   <div class="space-y-2">
                                    <label for="assignment-date" class="text-xs font-medium block">
                                      Registration Date
                                    </label>
                                    <input id="assignment-date" name="assignment_date" type="date" 
                                    class="w-full p-2 border border-gray-300 rounded-md text-sm bg-gray-100 cursor-not-allowed text-gray-600" value="{{ $assignment->registration_date ?? '' }}" readonly>
                                  </div>
                                </div>
                              </div>
                            </div>
                            
                            <!-- CofO Reg Particular Tab Content -->
                            <div id="cofo-content" class="nested-tab-content hidden">
                              <div class="p-4 space-y-4">
                                <div class="grid grid-cols-3 gap-4">
                                  <div class="space-y-2">
                                    <label for="serial-no" class="text-xs font-medium block">
                                      Serial No
                                    </label>
                                    <input id="serial-no" name="serial_no" type="text" 
                                    class="w-full p-2 border border-gray-300 rounded-md text-sm bg-gray-100 cursor-not-allowed text-gray-600" value="{{ $deeds->serial_no ?? '' }}" readonly>
                                  </div>
                                  <div class="space-y-2">
                                    <label for="page-no" class="text-xs font-medium block">
                                      Page No
                                    </label>
                                    <input id="page-no" name="page_no" type="text"   
                                    class="w-full p-2 border border-gray-300 rounded-md text-sm bg-gray-100 cursor-not-allowed text-gray-600" value="{{ $deeds->page_no ?? '' }}" readonly>
                                  </div>
                                  <div class="space-y-2">
                                    <label for="volume-no" class="text-xs font-medium block">
                                      Volume No
                                    </label>
                                    <input id="volume-no" name="volume_no" type="text"  
                                    class="w-full p-2 border border-gray-300 rounded-md text-sm bg-gray-100 cursor-not-allowed text-gray-600" value="{{ $deeds->volume_no ?? '' }}" readonly>
                                  </div>
                                </div>
                        
                                <div class="grid grid-cols-2 gap-4">
                                  <div class="space-y-2">
                                    <label for="deeds-time" class="text-xs font-medium block">
                                      Deeds Time
                                    </label>
                                    <input id="deeds-time" name="deeds_time" type="text" 
                                    class="w-full p-2 border border-gray-300 rounded-md text-sm bg-gray-100 cursor-not-allowed text-gray-600" value="{{ $deeds->deeds_time ?? '' }}" readonly>
                                  </div>
                                  <div class="space-y-2">
                                    <label for="deeds-date" class="text-xs font-medium block">
                                      Deeds Date
                                    </label>
                                    <input id="deeds-date" name="deeds_date" type="date" 
                                    class="w-full p-2 border border-gray-300 rounded-md text-sm bg-gray-100 cursor-not-allowed text-gray-600" value="{{ $deeds->deeds_date ?? '' }}" readonly>
                                  </div>
                                </div>
                              </div>
                            </div>
                        
                            <hr class="my-4 mx-4">
                        
                            <div class="flex justify-between items-center p-4">
                              <div class="flex gap-2">
                                <a href="{{route('sectionaltitling.secondary')}}" class="flex items-center px-3 py-1 text-xs bg-white text-black p-2 border border-gray-500 rounded-md hover:bg-gray-800">
                                <i data-lucide="undo-2" class="w-3.5 h-3.5 mr-1.5"></i>
                                Back
                                </a>
                              </div>
                            </div>
                          </div>
                        </div>
                        </form>
                        
                        <!-- Add CSS for nested tabs -->
                        <style>
                          .nested-tab-content {
                            display: none;
                          }
                          .nested-tab-content.active {
                            display: block;
                          }
                          .nested-tab-button {
                            position: relative;
                            cursor: pointer;
                            transition: background-color 0.2s;
                          }
                          .nested-tab-button.active {
                            color: #1d4ed8;
                            font-weight: 500;
                          }
                          .nested-tab-button:hover:not(.active) {
                            color: #4b5563;
                          }
                        </style>
                        
                        <!-- Add JavaScript for nested tabs -->
                        <script>
                          document.addEventListener('DOMContentLoaded', function() {
                            // Add nested tab functionality
                            const nestedTabButtons = document.querySelectorAll('.nested-tab-button');
                            const nestedTabContents = document.querySelectorAll('.nested-tab-content');
                            
                            nestedTabButtons.forEach(button => {
                              button.addEventListener('click', function() {
                                const tabId = this.getAttribute('data-nested-tab');
                                
                                // Deactivate all nested tabs
                                nestedTabButtons.forEach(btn => {
                                  btn.classList.remove('active');
                                  btn.classList.remove('border-blue-500');
                                  btn.classList.add('border-transparent');
                                });
                                nestedTabContents.forEach(content => {
                                  content.classList.remove('active');
                                  content.classList.add('hidden');
                                });
                                
                                // Activate selected nested tab
                                this.classList.add('active', 'border-blue-500');
                                this.classList.remove('border-transparent');
                                const tabContent = document.getElementById(tabId);
                                if (tabContent) {
                                  tabContent.classList.add('active');
                                  tabContent.classList.remove('hidden');
                                }
                              });
                            });
                          });
                        </script>
                        
                      <!-- Final Bill Tab -->
                      <div id="final-tab" class="tab-content">
                        <div class="bg-white border border-gray-200 rounded-lg shadow-sm">
                          <div class="p-4 border-b">
                            <h3 class="text-sm font-medium">Lands</h3>
                            <p class="text-xs text-gray-500"></p>
                          </div>
                          <input type="hidden" id="application_id" value="{{$application->id}}">
                      <input type="hidden" name="fileno" value="{{$application->fileno}}">
                          <div class="p-4 space-y-4">
                            <div class="grid grid-cols-2 gap-4">
                                <div class="space-y-2">
                                  <label for="file-no" class="text-xs font-medium block">
                                    File No
                                  </label>
                                  <input id="file-no" type="text"  class="w-full p-2 border border-gray-300 rounded-md text-sm bg-gray-100 cursor-not-allowed text-gray-600" value="{{$application->fileno}}" readonly>
                                </div>
                                <div class="space-y-2">
                                    <label for="file-name" class="text-xs font-medium block">
                                        File Name
                                    </label>
                                    <input id="file-name" type="text"   class="w-full p-2 border border-gray-300 rounded-md text-sm bg-gray-100 cursor-not-allowed text-gray-600"  value="{{ $application->applicant_title ?? '' }} {{ $application->surname ?? '' }} {{ $application->first_name ?? '' }}">
                                </div>
                            </div>
                
                            {{-- <div class="grid grid-cols-2 gap-4">
                                <div class="space-y-2">
                                    <label for="land-type" class="text-xs font-medium block">
                                        Land Type
                                    </label>
                                    <input id="land-type" type="text" placeholder="Enter Land Type" class="w-full p-2 border border-gray-300 rounded-md text-sm">
                                </div>
                                <div class="space-y-2">
                                    <label for="land-size" class="text-xs font-medium block">
                                        Land Size
                                    </label>
                                    <input id="land-size" type="text" placeholder="Enter Land Size" class="w-full p-2 border border-gray-300 rounded-md text-sm">
                                </div>
                            </div>
                 --}}
                            <hr class="my-4">
                
                            <div class="flex justify-between items-center">
                                <div class="flex gap-2">
                                    
                                    
                                    <button class="flex items-center px-3 py-1 text-xs border border-gray-300 rounded-md bg-sky-900 hover:bg-gray-50">
                                    <i data-lucide="folder-git-2" class="w-3.5 h-3.5 mr-1.5"></i>
                                    EDMS
                                    </button>
                                 
                                    <button class="flex items-center px-3 py-1 text-xs bg-gray-300 text-gray-500 rounded-md cursor-not-allowed" disabled>
                                      <i data-lucide="send-horizontal" class="w-3.5 h-3.5 mr-1.5"></i>
                                      Submit
                                    </button>
                              </div>
                            </div>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>

                <!-- Footer -->
                @include('admin.footer')
            </div>
            
            <!-- SweetAlert CDN -->
            <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
            
            <script>
                // Initialize Lucide icons
                lucide.createIcons();
                
                // Tab switching functionality
                document.addEventListener('DOMContentLoaded', function() {
                  const tabButtons = document.querySelectorAll('.tab-button');
                  const tabContents = document.querySelectorAll('.tab-content');
                  
                  tabButtons.forEach(button => {
                    button.addEventListener('click', function() {
                      const tabId = this.getAttribute('data-tab');
                      
                      // Deactivate all tabs
                      tabButtons.forEach(btn => btn.classList.remove('active'));
                      tabContents.forEach(content => content.classList.remove('active'));
                      
                      // Activate selected tab
                      this.classList.add('active');
                      document.getElementById(`${tabId}-tab`).classList.add('active');
                    });
                  });
                  
                  // Close modal button
                  document.getElementById('closeModal').addEventListener('click', function() {
                    // In a real application, this would close the modal
                    alert('Modal closed');
                  });
                  
                  // Survey form submission
                  document.getElementById('submit-survey').addEventListener('click', function(e) {
                      e.preventDefault();
                      
                      // Show loading message
                      Swal.fire({
                          title: 'Processing...',
                          text: 'Submitting survey information',
                          allowOutsideClick: false,
                          didOpen: () => {
                              Swal.showLoading();
                          }
                      });
                      
                      // Get the form data
                      const form = document.getElementById('survey-form');
                      const formData = new FormData(form);
                      
                      // Send AJAX request
                      fetch('{{ route("sub-actions.store-survey") }}', {
                          method: 'POST',
                          body: formData,
                          headers: {
                              'X-CSRF-TOKEN': '{{ csrf_token() }}'
                              // Don't set Accept header to force browser's default content negotiation
                          },
                          credentials: 'same-origin'
                      })
                      .then(response => {
                          // First check if response is ok
                          if (!response.ok) {
                              return response.text().then(text => {
                                  throw new Error('Server error: ' + text);
                              });
                          }
                          
                          // Try to parse as JSON, but handle text responses too
                          const contentType = response.headers.get('content-type');
                          if (contentType && contentType.includes('application/json')) {
                              return response.json();
                          } else {
                              return response.text().then(text => {
                                  return { success: true, message: 'Operation completed', text: text };
                              });
                          }
                      })
                      .then(data => {
                          // Show success message
                          Swal.fire({
                              icon: 'success',
                              title: 'Success!',
                              text: data.message || 'Survey information submitted successfully',
                              confirmButtonColor: '#3085d6'
                          });
                      })
                      .catch(error => {
                          // Show error message
                          console.error('Error:', error);
                          Swal.fire({
                              icon: 'error',
                              title: 'Submission Failed',
                              text: 'There was an error submitting the survey information. Please try again.',
                              confirmButtonColor: '#3085d6'
                          });
                      });
                  });
                  
                  // Deeds form submission
                  document.getElementById('submit-deeds').addEventListener('click', function(e) {
                      e.preventDefault();
                      
                      // Show loading message
                      Swal.fire({
                          title: 'Processing...',
                          text: 'Submitting deeds information',
                          allowOutsideClick: false,
                          didOpen: () => {
                              Swal.showLoading();
                          }
                      });
                      
                      // Get the form data
                      const form = document.getElementById('deeds-form');
                      const formData = new FormData(form);
                      
                      // Send AJAX request
                      fetch('{{ route("sub-actions.store-deeds") }}', {
                          method: 'POST',
                          body: formData,
                          headers: {
                              'X-CSRF-TOKEN': '{{ csrf_token() }}'
                              // Don't set Accept header to force browser's default content negotiation
                          },
                          credentials: 'same-origin'
                      })
                      .then(response => {
                          // First check if response is ok
                          if (!response.ok) {
                              return response.text().then(text => {
                                  throw new Error('Server error: ' + text);
                              });
                          }
                          
                          // Try to parse as JSON, but handle text responses too
                          const contentType = response.headers.get('content-type');
                          if (contentType && contentType.includes('application/json')) {
                              return response.json();
                          } else {
                              return response.text().then(text => {
                                  return { success: true, message: 'Operation completed', text: text };
                              });
                          }
                      })
                      .then(data => {
                          // Show success message
                          Swal.fire({
                              icon: 'success',
                              title: 'Success!',
                              text: data.message || 'Deeds information submitted successfully',
                              confirmButtonColor: '#3085d6'
                          });
                      })
                      .catch(error => {
                          // Show error message
                          console.error('Error:', error);
                          Swal.fire({
                              icon: 'error',
                              title: 'Submission Failed',
                              text: 'There was an error submitting the deeds information. Please try again.',
                              confirmButtonColor: '#3085d6'
                          });
                      });
                  });
                });
              </script>
    
        @endsection


