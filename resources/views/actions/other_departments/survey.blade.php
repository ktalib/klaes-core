<form id="survey-form" method="POST">
    @csrf
    <!-- Survey Tab -->
    <div id="initial-tab" class="tab-content {{ request()->has('survey') || (!request()->has('deeds') && !request()->has('lands')) ? 'active' : '' }}">
      <div class="bg-white border border-gray-200 rounded-lg shadow-sm">
      <div class="p-4 border-b">
        <h3 class="text-sm font-medium">Survey</h3>
        <p class="text-xs text-gray-500">Fill in the survey details for the property</p>
      </div>
      <div class="p-4 space-y-4">
        <input type="hidden" id="application_id" name="application_id" value="{{$application->id}}">
        <input type="hidden" name="fileno" value="{{$application->fileno}}">
        
        <!-- Property Identification Card -->
        <div class="border border-gray-200 rounded-lg p-4 bg-gray-50">
        <h4 class="text-sm font-medium mb-3">Property Identification</h4>
        <div class="grid grid-cols-2 gap-4">
          <div class="space-y-2">
          <label for="plot-no" class="text-xs font-medium block">Plot No</label>
          <input id="plot-no" name="plot_no" type="text" placeholder="Enter plot number" class="w-full p-2 border border-gray-300 rounded-md text-sm">
          </div>
          <div class="space-y-2">
          <label for="block-no" class="text-xs font-medium block">Block No</label>
          <input id="block-no" name="block_no" type="text" placeholder="Enter block number" class="w-full p-2 border border-gray-300 rounded-md text-sm">
          </div>
        </div>

        <div class="grid grid-cols-2 gap-4 mt-3">
          <div class="space-y-2">
          <label for="approved-plan-no" class="text-xs font-medium block">Approved Plan No</label>
          <input id="approved-plan-no" name="approved_plan_no" type="text" placeholder="Enter approved plan number" class="w-full p-2 border border-gray-300 rounded-md text-sm">
          </div>
          <div class="space-y-2">
          <label for="tp-plan-no" class="text-xs font-medium block">TP Plan No</label>
          <input id="tp-plan-no" name="tp_plan_no" type="text" placeholder="Enter TP plan number" class="w-full p-2 border border-gray-300 rounded-md text-sm">
          </div>
        </div>
        </div>

      <!-- Unit Control Beacon Information Card -->
      <div class="border border-gray-200 rounded-lg p-4 bg-gray-50">
        <h4 class="text-sm font-medium mb-3">Control Beacon Information</h4>
        <div class="grid grid-cols-3 gap-4">
          <div class="space-y-2">
          <label for="unit-beacon-control-name" class="text-xs font-medium block">
            {{ request()->query('is') == 'survey' ? 'Unit Control Beacon Name' : 'Control Beacon Name' }}
          </label>
          <input id="unit-beacon-control-name" name="beacon_control_name" type="text" placeholder="Enter unit control beacon name" class="w-full p-2 border border-gray-300 rounded-md text-sm">
          </div>
          <div class="space-y-2">
          <label for="unit-beacon-control-x" class="text-xs font-medium block">
            {{ request()->query('is') == 'survey' ? 'Unit Control Beacon X' : 'Control Beacon X' }}
          </label>
          <input id="unit-beacon-control-x" name="Control_Beacon_Coordinate_X" type="text" placeholder="Enter X coordinate" class="w-full p-2 border border-gray-300 rounded-md text-sm">
          </div>
          <div class="space-y-2">
          <label for="unit-beacon-control-y" class="text-xs font-medium block">
            {{ request()->query('is') == 'survey' ? 'Unit Control Beacon Y' : 'Control Beacon Y' }}
          </label>
          <input id="unit-beacon-control-y" name="Control_Beacon_Coordinate_Y" type="text" placeholder="Enter Y coordinate" class="w-full p-2 border border-gray-300 rounded-md text-sm">
          </div>
        </div>
        </div>
        <!-- Sheet Information Card -->
        <div class="border border-gray-200 rounded-lg p-4 bg-gray-50">
        <h4 class="text-sm font-medium mb-3">Sheet Information</h4>
        <div class="grid grid-cols-2 gap-4">
          <div class="space-y-2">
          <label for="metric-sheet-index" class="text-xs font-medium block">Metric Sheet Index</label>
          <input id="metric-sheet-index" name="Metric_Sheet_Index" type="text" placeholder="Enter metric sheet index" class="w-full p-2 border border-gray-300 rounded-md text-sm">
          </div>
          <div class="space-y-2">
          <label for="metric-sheet-no" class="text-xs font-medium block">Metric Sheet No</label>
          <input id="metric-sheet-no" name="Metric_Sheet_No" type="text" placeholder="Enter metric sheet number" class="w-full p-2 border border-gray-300 rounded-md text-sm">
          </div>
        </div>

        <div class="grid grid-cols-2 gap-4 mt-3">
          <div class="space-y-2">
          <label for="imperial-sheet" class="text-xs font-medium block">Imperial Sheet</label>
          <input id="imperial-sheet" name="Imperial_Sheet" type="text" placeholder="Enter imperial sheet" class="w-full p-2 border border-gray-300 rounded-md text-sm">
          </div>
          <div class="space-y-2">
          <label for="imperial-sheet-no" class="text-xs font-medium block">Imperial Sheet No</label>
          <input id="imperial-sheet-no" name="Imperial_Sheet_No" type="text" placeholder="Enter imperial sheet number" class="w-full p-2 border border-gray-300 rounded-md text-sm">
          </div>
        </div>
        </div>

        <!-- Location Information Card -->
        <div class="border border-gray-200 rounded-lg p-4 bg-gray-50">
        <h4 class="text-sm font-medium mb-3">Location Information</h4>
        <div class="grid grid-cols-3 gap-4">
          <div class="space-y-2">
          <label for="layout-name" class="text-xs font-medium block">Layout Name</label>
          <input id="layout-name" name="layout_name" type="text" placeholder="Enter layout name" class="w-full p-2 border border-gray-300 rounded-md text-sm">
          </div>
          <div class="space-y-2">
          <label for="district-name" class="text-xs font-medium block">District Name</label>
          <input id="district-name" name="district_name" type="text" placeholder="Enter district name" class="w-full p-2 border border-gray-300 rounded-md text-sm">
          </div> 
          <div class="space-y-2">
          <label for="lga-name" class="text-xs font-medium block">LGA Name</label>
          <input id="lga-name" name="lga_name" type="text" placeholder="Enter LGA name" class="w-full p-2 border border-gray-300 rounded-md text-sm">
          </div>
        </div>
        </div>

        <!-- Personnel Information Card -->
        <div class="border border-gray-200 rounded-lg p-4 bg-gray-50">
        <h4 class="text-sm font-medium mb-3">Personnel Information</h4>
        <div class="grid grid-cols-2 gap-4">
          <div class="space-y-2">
          <label for="survey-by" class="text-xs font-medium block">Survey By</label>
          <input id="survey-by" name="survey_by" type="text" placeholder="Enter surveyor's name" class="w-full p-2 border border-gray-300 rounded-md text-sm">
          </div>
          <div class="space-y-2">
          <label for="survey-date" class="text-xs font-medium block">Date</label>
          <input id="survey-date" name="survey_by_date" type="date" class="w-full p-2 border border-gray-300 rounded-md text-sm">
          </div>
        </div>
    
        <div class="grid grid-cols-2 gap-4 mt-3">
          <div class="space-y-2">
          <label for="drawn-by" class="text-xs font-medium block">Drawn By</label>
          <input id="drawn-by" name="drawn_by" type="text" placeholder="Enter drafter's name" class="w-full p-2 border border-gray-300 rounded-md text-sm">
          </div>
          <div class="space-y-2">
          <label for="drawn-date" class="text-xs font-medium block">Date</label>
          <input id="drawn-date" name="drawn_by_date" type="date" class="w-full p-2 border border-gray-300 rounded-md text-sm">
          </div>
        </div>
    
        <div class="grid grid-cols-2 gap-4 mt-3">
          <div class="space-y-2">
          <label for="checked-by" class="text-xs font-medium block">Checked By</label>
          <input id="checked-by" name="checked_by" type="text" placeholder="Enter checker name" class="w-full p-2 border border-gray-300 rounded-md text-sm">
          </div>
          <div class="space-y-2">
          <label for="checked-date" class="text-xs font-medium block">Date</label>
          <input id="checked-date" name="checked_by_date" type="date" class="w-full p-2 border border-gray-300 rounded-md text-sm">
          </div>
        </div>
    
        <div class="grid grid-cols-2 gap-4 mt-3">
          <div class="space-y-2">
          <label for="approved-by" class="text-xs font-medium block">Approved By</label>
          <input id="approved-by" name="approved_by" type="text" placeholder="Enter approver's name" class="w-full p-2 border border-gray-300 rounded-md text-sm">
          </div>
          <div class="space-y-2">
          <label for="approved-date" class="text-xs font-medium block">Date</label>
          <input id="approved-date" name="approved_by_date" type="date" class="w-full p-2 border border-gray-300 rounded-md text-sm">
          </div>
        </div>
        </div>
    
        <hr class="my-4">
    
        <div class="flex justify-between items-center">
        <div class="flex gap-2">
          <a href="{{route('sectionaltitling.primary')}}" class="flex items-center px-3 py-1 text-xs border border-gray-300 rounded-md bg-white hover:bg-gray-50">
          <i data-lucide="undo-2" class="w-3.5 h-3.5 mr-1.5"></i>
          Back
          </a>
          <button type="button" id="view-survey-plan" class="flex items-center px-3 py-1 text-xs border border-gray-300 rounded-md bg-white hover:bg-gray-50">
            <i data-lucide="map" class="w-3.5 h-3.5 mr-1.5"></i>
            View Survey Plan
          </button>

          <!-- Survey Plan Modal -->
         
          <!-- Full image viewer modal -->
       @include('actions.document')
          
          @if(request()->query('is') === 'survey')
            <button type="button" id="submit-survey" class="flex items-center px-3 py-1 text-xs bg-green-700 text-white rounded-md hover:bg-gray-800">
              <i data-lucide="send-horizontal" class="w-3.5 h-3.5 mr-1.5"></i>
              Submit
            </button>
          @endif
        </div>
        </div>
      </div>
      </div>
    </div>
 </form>
<script>
  // Function to toggle form field editability
  function toggleFormFields(enabled) {
     const inputs = document.querySelectorAll('#survey-form input:not([type="hidden"])');
     inputs.forEach(input => {
      input.disabled = !enabled;
      if (enabled) {
       input.classList.remove('bg-gray-100');
       input.classList.remove('cursor-not-allowed');
      } else {
       input.classList.add('bg-gray-100');
       input.classList.add('cursor-not-allowed');
      }
     });
  }
  
  // Function to fetch survey data
  function fetchSurveyData() {
     const applicationId = document.getElementById('application_id').value;
     
     // Show loading message
     Swal.fire({
      title: 'Loading...',
      text: 'Retrieving survey information',
      allowOutsideClick: false,
      didOpen: () => {
       Swal.showLoading();
      }
     });
     
     // Fetch existing survey data using the named route
     fetch(`{{ route('survey.get', '') }}/${applicationId}`)
      .then(response => {
       if (!response.ok) {
        if (response.status === 404) {
            // Record not found is not a serious error
            Swal.close();
            return { success: false, notFound: true };
        }
        throw new Error('Network response was not ok');
       }
       return response.json();
      })
      .then(data => {
       if (data.success) {
        // Populate form with existing data
        const survey = data.survey;
        
        // Update all form fields with data from the server
        for (const key in survey) {
            const input = document.querySelector(`[name="${key}"]`);
            if (input) {
             input.value = survey[key];
            }
        }
        
        // Mark form as being in edit mode
        document.getElementById('survey-form').setAttribute('data-mode', 'update');
        
        // Change submit button text if submit button exists
        const submitButton = document.getElementById('submit-survey');
        if (submitButton) {
          submitButton.innerHTML = '<i data-lucide="save" class="w-3.5 h-3.5 mr-1.5"></i> Update';
          // Re-initialize Lucide icons for the new button
          lucide.createIcons();
        }
        
        // Always disable fields - they can only be edited when "is=survey" 
        // and the edit button is clicked
        toggleFormFields(false);
        
        Swal.close();
       } else if (data.notFound) {
        // No record found, but only enable fields for editing if is=survey is in URL
        toggleFormFields(window.location.search.includes('is=survey'));
        Swal.close();
       } else {
        Swal.fire({
            icon: 'info',
            title: 'No Record Found',
            text: 'No survey record exists yet.' + (window.location.search.includes('is=survey') ? ' Please create a new one.' : ''),
            confirmButtonColor: '#3085d6'
        });
        // Only enable fields for editing if is=survey is in URL
        toggleFormFields(window.location.search.includes('is=survey'));
       }
      })
      .catch(error => {
       console.error('Error:', error);
       
       Swal.fire({
        icon: 'error',
        title: 'Error',
        text: 'Could not retrieve survey data',
        confirmButtonColor: '#3085d6'
       });
       
       // Only enable fields for editing if is=survey is in URL
       toggleFormFields(window.location.search.includes('is=survey'));
      });
  }
  
  // Add submit survey handler if the button exists
  const submitButton = document.getElementById('submit-survey');
  if (submitButton) {
    submitButton.addEventListener('click', function(e) {
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
      
      // Determine if we're creating or updating
      const isUpdate = form.getAttribute('data-mode') === 'update';
      const url = isUpdate 
        ? '{{ route("survey.update") }}' 
        : '{{ route("primary-applications.store") }}';
      
      // Send AJAX request
      fetch(url, {
        method: 'POST',
        body: formData,
        headers: {
         'X-CSRF-TOKEN': '{{ csrf_token() }}'
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
        // Show success message and wait for user to acknowledge before fetching data
        Swal.fire({
         icon: 'success',
         title: 'Success!',
         text: data.message || (isUpdate ? 'Survey information updated successfully' : 'Survey information submitted successfully'),
         confirmButtonColor: '#3085d6',
         timer: 5000, // Keep alert visible for at least 5 seconds
         timerProgressBar: true
        }).then((result) => {
         // After user acknowledges or timer completes, fetch the latest data
         fetchSurveyData();
        });
      })
      .catch(error => {
        // Show error message
        console.error('Error:', error);
        Swal.fire({
         icon: 'error',
         title: 'Submission Failed',
         text: 'There was an error ' + (isUpdate ? 'updating' : 'submitting') + ' the survey information. Please try again.',
         confirmButtonColor: '#3085d6',
         timer: 5000, // Keep alert visible for at least 5 seconds
         timerProgressBar: true
        });
      });
    });
  }
    
  // Initialize on page load
  document.addEventListener('DOMContentLoaded', function() {
    // Only create edit button if is=survey in URL and button doesn't already exist
    if (window.location.search.includes('is=survey')) {
      let editButton = document.getElementById('edit-survey');
      if (!editButton) {
        // Find the submit button to place edit button before it
        const submitButton = document.getElementById('submit-survey');
        if (submitButton) {
          const parentElement = submitButton.parentElement;
          
          // Create the edit button
          editButton = document.createElement('button');
          editButton.id = 'edit-survey';
          editButton.type = 'button';
          editButton.className = 'flex items-center px-3 py-1 text-xs border border-gray-300 rounded-md bg-white hover:bg-gray-50';
          editButton.innerHTML = '<i data-lucide="pencil" class="w-3.5 h-3.5 mr-1.5"></i> Edit';
          
          // Insert before submit button
          parentElement.insertBefore(editButton, submitButton);
          
          // Re-initialize Lucide icons
          lucide.createIcons();
          
          // Add click event to edit button - only enable fields, don't fetch data
          editButton.addEventListener('click', function(e) {
            e.preventDefault();
            toggleFormFields(true);
          });
        }
      } else {
        // Ensure edit button has event listener
        editButton.addEventListener('click', function(e) {
          e.preventDefault();
          toggleFormFields(true);
        });
      }
    }
    
    // Always fetch survey data on page load (regardless of URL parameters)
    fetchSurveyData();
  });
</script>