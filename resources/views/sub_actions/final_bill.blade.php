<div class="bg-white border border-gray-200 rounded-lg shadow-sm">
  <div class="p-4">
    <!-- Add Tab Navigation -->
    <div class="mb-4 border-b border-gray-200">
      <ul class="flex flex-wrap -mb-px text-sm font-medium text-center" role="tablist">
        <li class="mr-2" role="presentation">
          <button class="inline-block p-4 border-b-2 border-green-600 rounded-t-lg active" 
                  id="customize-tab" data-tabs-target="#customize-content" type="button" role="tab" 
                  aria-controls="customize" aria-selected="true">
            <i data-lucide="calculator" class="w-4 h-4 mr-1.5 inline-block"></i>
            Generate Bill Balance
          </button>
        </li>
        <li class="mr-2" role="presentation">
          <button class="inline-block p-4 border-b-2 border-transparent rounded-t-lg hover:text-gray-600 hover:border-gray-300" 
                  id="final-bill-tab" data-tabs-target="#final-bill-content" type="button" role="tab" 
                  aria-controls="final-bill" aria-selected="false">
            <i data-lucide="file-text" class="w-4 h-4 mr-1.5 inline-block"></i>
            Bill Balance
          </button>
        </li>
      </ul>
    </div>
    
    <!-- Tab Content -->
    <div id="final-bill-tabs-content">
      <!-- Customize Fees Tab Content -->
      <div class="block" id="customize-content" role="tabpanel" aria-labelledby="customize-tab">
        <div class="p-4 border border-gray-200 rounded-md">
          <h4 class="text-sm font-medium mb-3">Generate Bill Balance</h4>
          <form id="fee-form" class="grid grid-cols-2 gap-4">
            <input type="hidden" name="sub_application_id" value="{{ $application->id }}">
            
            <!-- Owner Details (Disabled) -->
            <div class="col-span-2 mb-3">
                <div class="space-y-2">
                <label for="bill_ref_id" class="text-xs font-medium text-green-600">Bill Reference ID</label>
                <input id="bill_ref_id" name="bill_ref_id" type="text" 
                  value="ST-BILL-{{ $application->id }}-{{ date('Ymd') }}-{{ rand(1000, 9999) }}"
                  class="w-full p-2 border border-gray-300 rounded-md text-sm bg-gray-100" readonly disabled>
                </div> 
               <br>
              <h5 class="text-xs font-semibold mb-2">Owner Details</h5>
              <div class="grid grid-cols-2 gap-4">
                <div class="space-y-2">
                  <label for="file_no" class="text-xs font-medium">File Number</label>
                  <input id="file_no" type="text" value="{{ $application->fileno }}" 
                      class="w-full p-2 border border-gray-300 rounded-md text-sm bg-gray-100" disabled>
                </div>
                <div class="space-y-2">
                  <label for="owner_name" class="text-xs font-medium">Owner Name</label>
                  <input id="owner_name" type="text" 
                    value="@if(!empty($application->corporate_name)){{ $application->corporate_name }}@elseif(!empty($application->multiple_owners_names)){{ $application->multiple_owners_names }}@else{{ $application->applicant_title }} {{ $application->first_name }} {{ $application->surname }}@endif" 
                    class="w-full p-2 border border-gray-300 rounded-md text-sm bg-gray-100" disabled>
                </div>
                <div class="space-y-2">
                  <label for="land_use" class="text-xs font-medium">Land Use</label>
                  <input id="land_use" type="text" value="{{ $application->land_use }}" 
                      class="w-full p-2 border border-gray-300 rounded-md text-sm bg-gray-100" disabled>
                </div>
                <div class="space-y-2">
                  <label for="plot_size" class="text-xs font-medium">Unit Size</label>
                  <input id="plot_size" type="text" value="{{ $application->plot_size }}" 
                      class="w-full p-2 border border-gray-300 rounded-md text-sm bg-gray-100" disabled>
                </div>
              </div>
            </div>
            
            <!-- Fee Customization -->
            <div class="col-span-2 mb-3">
              <h5 class="text-xs font-semibold mb-2">Charges & Fees</h5>
              <div class="grid grid-cols-2 gap-4">
                @php
                    // Add fallback if $fees is not defined (removed processing_fee)
                    $fees = $fees ?? [
                        'assignment_fee' => 50000,
                        'bill_balance' => 30525,
                        'recertification_fee' => 5000,
                        'total_amount' => 85525
                    ];
                @endphp
                
                <div class="space-y-2">
                  <label for="assignment_fee" class="text-xs font-medium">Assignment Fee (₦)</label>
                  <input id="assignment_fee" name="assignment_fee" type="number" value="{{ $fees['assignment_fee'] }}" 
                      class="w-full p-2 border border-gray-300 rounded-md text-sm" disabled>
                </div>
                
                <div class="space-y-2">
                  <label for="bill_balance" class="text-xs font-medium">Bill Balance (₦)</label>
                  <input id="bill_balance" name="bill_balance" type="number" value="{{ $fees['bill_balance'] }}" 
                      class="w-full p-2 border border-gray-300 rounded-md text-sm" disabled>
                </div>
                
                <div class="space-y-2">
                  <label for="recertification_fee" class="text-xs font-medium">Recertification Fee  (₦)</label>
                  <input id="recertification_fee" name="recertification_fee" type="number" value="{{ $fees['recertification_fee'] }}" 
                      class="w-full p-2 border border-gray-300 rounded-md text-sm" disabled>
                </div>
                
                <div class="space-y-2">
                  <label for="bill_date" class="text-xs font-medium">Bill Date</label>
                  <input id="bill_date" name="bill_date" type="date" value="{{ date('Y-m-d') }}" 
                      class="w-full p-2 border border-gray-300 rounded-md text-sm" disabled>
                </div>
              </div>
            </div>
            
            <!-- Development Charges (Enabled) -->
            <div class="space-y-2 col-span-2">
              <label for="dev_charges" class="text-xs font-medium">Development Charges (₦)</label>
              <input id="dev_charges" name="dev_charges" type="number" value="{{ $fees['dev_charges'] ?? 0 }}" 
                  class="w-full p-2 border border-gray-300 rounded-md text-sm" disabled>
             
            </div>
            
            <!-- Total Amount (Calculated) -->
            <div class="col-span-2 mt-4 p-3 bg-gray-100 rounded-md">
              <div class="flex justify-between items-center">
                <div>
                  <p class="text-xs text-gray-600">Total Amount:</p>
                  <p class="text-lg font-bold" id="calculated-total">
                    ₦ {{ number_format($fees['total_amount'], 2) }}
                  </p>
                </div>
                <div class="flex gap-2">
                  {{-- Removed Calculate, Generate Bill, and Preview Bill buttons --}}
                  {{-- 
                  <button type="button" id="calculate-total-btn" class="px-3 py-1 text-xs bg-gray-600 text-white rounded-md hover:bg-gray-700">
                    <i data-lucide="calculator" class="w-3.5 h-3.5 mr-1.5 inline-block"></i>
                    Calculate
                  </button>
                 
                  <button type="button" id="save-bill-btn" class="px-3 py-1 text-xs bg-green-600 text-white rounded-md hover:bg-green-700">
                    <i data-lucide="save" class="w-3.5 h-3.5 mr-1.5 inline-block"></i>
                   Generate Bill
                  </button>

                   <button type="button" id="preview-bill-btn" class="px-3 py-1 text-xs bg-blue-600 text-white rounded-md hover:bg-blue-700">
                    <i data-lucide="eye" class="w-3.5 h-3.5 mr-1.5 inline-block"></i>
                    Preview Bill
                  </button>
                  --}}
                </div>
              </div>
            </div>
          </form>
        </div>
      </div>
      
      <!-- Final Bill Tab Content -->
      <div class="hidden" id="final-bill-content" role="tabpanel" aria-labelledby="final-bill-tab">
        <!-- Header with two logos and title -->
        <div class="flex items-center justify-between mb-2">
          <div class="w-12 h-12">
            <img src="{{ asset('assets/logo/logo1.jpg') }}" alt="Kano State Logo" class="w-full h-full object-contain">
          </div>
          <div class="text-center">
            <h3 class="text-sm font-bold text-green-800">KANO STATE MINISTRY OF LAND AND PHYSICAL PLANNING</h3>
            <p class="text-xs font-medium text-red-600">SECTIONAL TITLE BILL BALANCE</p>
          </div>
          <div class="w-12 h-12">
            <img src="{{ asset('assets/logo/logo3.jpeg') }}" alt="Ministry Logo" class="w-full h-full object-contain">
          </div>
        </div>
        
        <!-- Date -->
        <div class="text-right text-xs mb-4">
          <p>{{ $current_date ?? Carbon\Carbon::now()->format('l, F d, Y') }}</p>
          <br>
          <p><span class="font-medium">Bill Reference ID:</span> <span class="text-green-600 font-bold">ST-BILL-{{ $application->id }}-{{ date('Ymd') }}-{{ rand(1000, 9999) }}</span></p>
        </div>
        
        <!-- Introduction -->
        <div class="mb-4">
          <p class="text-xs mb-2">Dear Sir/Madam,</p>
          <p class="text-xs">
            I am directed to inform you that the total cost of processing of your application for sectional 
            title located at <span class="font-medium">{{ $application->property_house_no ?? '' }} {{ $application->property_plot_no ?? '' }}, {{ $application->property_street_name ?? '' }}, {{ $application->property_district ?? '' }}, {{ $application->property_lga ?? '' }}</span> with the following particulars.
          </p>
        </div>
        
        <!-- Property Details -->
        <div class="mb-4">
          <div class="grid grid-cols-2 gap-2 text-xs">
            <div>
           
              <p><span class="font-medium">Form No:</span> {{$application->id}}</p>
              <p><span class="font-medium">File No:</span> {{$application->fileno}}</p>
              <p><span class="font-medium">Name of Section Owner:</span> {{ $application->applicant_title}} {{ $application->surname}} {{ $application->first_name}}</p>
              <p><span class="font-medium">Unit Size:</span> {{ $application->plot_size}}</p>
              <p><span class="font-medium">Land Use:</span> {{ $application->land_use}}</p>
              <p><span class="font-medium">Location:</span> {{ $application->property_house_no ?? '' }} {{ $application->property_plot_no ?? '' }}, {{ $application->property_street_name ?? '' }}, {{ $application->property_district ?? '' }}, {{ $application->property_lga ?? '' }}</p>
              <p><span class="font-medium">Approval Date:</span> {{ $application->approval_date ?? 'Pending' }}</p>
            </div>
          </div>
        </div>
        
        <!-- Fee Table -->
        <div class="border border-black mt-2">
          <table class="w-full text-xs bill-table">
            <thead>
              <tr>
                <th width="40%">Land Use</th>
                <th width="30%">Survey / Processing Fees</th>
                <th width="30%">Dev. Charges ₦</th>
              </tr>
            </thead>
            <tbody>
              <tr>
                <td>
                  @php
                    $landUse = strtolower($application->land_use ?? 'residential');
                  @endphp
                  @if($landUse == 'residential')
                    <p><strong>a.Assignment Fees</strong></p>
                    <p><strong>b.Bill Balance</strong></p>
                  @else
                    <p><strong>a.Commercial Fees</strong></p>
                    <p style="padding-left: 15px;">i.Assignment Fees</p>
                    <p style="padding-left: 15px;">ii.Bill Balance</p>
                  @endif
                </td>
                <td>
                  <p>N {{ number_format($bill->assignment_fee ?? 0, 2) }}</p>
                  <p>N {{ number_format($bill->bill_balance ?? 0, 2) }}</p>
                </td>
                <td>
                  <p>N {{ number_format($bill->dev_charges ?? 0, 2) }}</p>
                </td>
              </tr>
              <tr>
                <td>Recertification Fee</td>
                <td>N {{ number_format($bill->recertification_fee ?? 0, 2) }}</td>
                <td>N __________________</td>
              </tr>
              <tr>
                <td colspan="3">
                  <p><strong>TOTAL: ₦ {{ number_format($bill->total_amount ?? 0, 2) }}</strong> ({{ $total_in_words ?? '' }})</p>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
        
        <!-- Footer Text -->
        <div class="text-xs space-y-2 mb-4 mt-4">
          <p>
            You are hereby directed to settle this bill promptly in order to accelerate the processing of your 
            application.
          </p>
          <p>
            <span class="font-medium">Note:</span> Documentary Payments can be made at the Checkout-Point and KANGIS 
            Cashier's Office.
          </p>
          <p>
            <span class="font-medium">Note:</span> Ensure that you obtain a duly acknowledged Revenue Receipt issued at the KANGIS 
            Office.
          </p>
          <p>Thank you.</p>
        </div>
        
        <!-- Action Buttons for Final Bill View -->
        <div class="mt-6">
          {{-- <button id="print-bill-btn" class="flex items-center px-3 py-1 text-xs border border-gray-300 rounded-md bg-white hover:bg-gray-50">
            <i data-lucide="printer" class="w-3.5 h-3.5 mr-1.5"></i>
            Print Bill
          </button> --}}
        </div>
      </div>
    </div>
  </div>
</div>

 
<!-- JavaScript for handling bill actions and tab switching -->
<script>
  document.addEventListener('DOMContentLoaded', function() {
    // Initialize tab functionality
    const tabs = document.querySelectorAll('[data-tabs-target]');
    const tabContents = document.querySelectorAll('#final-bill-tabs-content > div');

    tabs.forEach(tab => {
      tab.addEventListener('click', () => {
        const target = document.querySelector(tab.dataset.tabsTarget);
        
        // Hide all tab contents
        tabContents.forEach(tc => tc.classList.add('hidden'));
        tabContents.forEach(tc => tc.classList.remove('block'));
        
        // Remove active state from all tabs
        tabs.forEach(t => {
          t.classList.remove('active');
          t.setAttribute('aria-selected', false);
          t.classList.remove('border-green-600');
          t.classList.add('border-transparent');
        });
        
        // Show the selected tab content
        target.classList.remove('hidden');
        target.classList.add('block');
        
        // Set the selected tab as active
        tab.classList.add('active');
        tab.setAttribute('aria-selected', true);
        tab.classList.remove('border-transparent');
        tab.classList.add('border-green-600');
      });
    });
    
    // Print bill functionality
    document.getElementById('print-bill-btn').addEventListener('click', function() {
      window.open('{{ route("sub-final-bill.print", ["id" => $application->id]) }}', '_blank');
    });
    
    // Preview bill (switch to bill tab)
    document.getElementById('preview-bill-btn').addEventListener('click', function() {
      // Validate required fields first
      const assignmentFee = document.getElementById('assignment_fee').value;
      const billBalance = document.getElementById('bill_balance').value;
      const recertificationFee = document.getElementById('recertification_fee').value;
      
      if (!assignmentFee || !billBalance || !recertificationFee) {
        Swal.fire({
          icon: 'warning',
          title: 'Missing Information',
          text: 'Please fill in all required fee fields before previewing the bill.',
          confirmButtonColor: '#16a34a'
        });
        return;
      }
      
      // Calculate and update all values first
      calculateAndUpdateBill();
      
      // Then switch to the bill tab
      document.getElementById('final-bill-tab').click();
      
      // Show a success toast notification
      Swal.fire({
        toast: true,
        position: 'top-end',
        icon: 'success',
        title: 'Bill preview updated',
        showConfirmButton: false,
        timer: 1500
      });
    });
    
    // Calculate total button
    document.getElementById('calculate-total-btn').addEventListener('click', function() {
      calculateAndUpdateBill();
      
      // Show a success toast notification
      Swal.fire({
        toast: true,
        position: 'top-end',
        icon: 'success',
        title: 'Bill calculated successfully',
        showConfirmButton: false,
        timer: 1500
      });
    });
    
    // Real-time calculation on input change (removed processing_fee)
    document.getElementById('assignment_fee').addEventListener('input', calculateAndUpdateBill);
    document.getElementById('bill_balance').addEventListener('input', calculateAndUpdateBill);
    document.getElementById('recertification_fee').addEventListener('input', calculateAndUpdateBill);
    document.getElementById('dev_charges').addEventListener('input', calculateAndUpdateBill);
    
    // Calculate and update bill values (removed processing_fee)
    function calculateAndUpdateBill() {
      const assignmentFee = parseFloat(document.getElementById('assignment_fee').value) || 0;
      const billBalance = parseFloat(document.getElementById('bill_balance').value) || 0;
      const groundRent = parseFloat(document.getElementById('recertification_fee').value) || 0;
      const devCharges = parseFloat(document.getElementById('dev_charges').value) || 0;
      
      const totalAmount = assignmentFee + billBalance + groundRent + devCharges;
      
      // Update calculated total in the form
      document.getElementById('calculated-total').textContent = '₦ ' + totalAmount.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ",");
      
      // Update the final bill preview (removed processing fee)
      document.getElementById('res-assignment-fee').textContent = 'N ' + assignmentFee.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ",");
      document.getElementById('res-bill-balance').textContent = 'N ' + billBalance.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ",");
      document.getElementById('ground-rent-amount').textContent = groundRent.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ",");
      // Fix: Use correct element ID
      document.getElementById('dev-charges').textContent = 'N ' + devCharges.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ",");
      document.getElementById('final-bill-total').textContent = totalAmount.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ",");
      
      // We would ideally update the words here too, but that requires server-side processing
      // So we'll let the server handle that when saving
    }
    
    // Save bill functionality
    document.getElementById('save-bill-btn').addEventListener('click', function() {
      // Show loading indicator
      Swal.fire({
        title: 'Processing...',
        text: 'Saving bill information',
        allowOutsideClick: false,
        allowEscapeKey: false,
        didOpen: () => {
          Swal.showLoading();
        }
      });
      
      const formData = new FormData(document.getElementById('fee-form'));
      const formDataObj = {};
      formData.forEach((value, key) => {
        formDataObj[key] = value;
      });
      
      // Send AJAX request to save the bill
      fetch('{{ route("sub-final-bill.save") }}', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': '{{ csrf_token() }}'
        },
        body: JSON.stringify(formDataObj)
      })
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          // Show success message
          Swal.fire({
            icon: 'success',
            title: 'Success!',
            text: data.message,
            confirmButtonColor: '#10B981'
          }).then(() => {
            // Switch to the bill tab to show the saved bill
            document.getElementById('final-bill-tab').click();
          });
        } else {
          // Show error message
          Swal.fire({
            icon: 'error',
            title: 'Error',
            text: 'Error: ' + data.message,
            confirmButtonColor: '#EF4444'
          });
        }
      })
      .catch(error => {
        console.error('Error:', error);
        // Show error message
        Swal.fire({
          icon: 'error',
          title: 'Error',
          text: 'An error occurred while saving the bill.',
          confirmButtonColor: '#EF4444'
        });
      });
    });
  });
</script>