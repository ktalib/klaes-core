<div class="bg-gray-50 p-4 rounded-md mb-6">
  <h3 class="font-medium text-center mb-4">
    {{ request()->query('landuse') === 'Mixed' ? 'MIXED INITIAL BILL (Residential + Commercial)' : 'INITIAL BILL' }}
  </h3>
  
  @php
      $landUse = request()->query('landuse');

      if ($landUse === 'Mixed') {
          // Residential fees
          $residentialApplicationFee = '10000.00';
          $residentialProcessingFee  = '20000.00';
          $residentialSitePlanFee    = '10000.00';
          // Commercial fees
          $commercialApplicationFee = '20000.00';
          $commercialProcessingFee  = '50000.00';
          $commercialSitePlanFee    = '10000.00';

          // Aggregate Mixed fees (Residential + Commercial)
          $applicationFee = floatval($residentialApplicationFee) + floatval($commercialApplicationFee);
          $processingFee  = floatval($residentialProcessingFee)  + floatval($commercialProcessingFee);
          $sitePlanFee    = floatval($residentialSitePlanFee)    + floatval($commercialSitePlanFee);

          // Mixed total from aggregated fees
          $totalFee = $applicationFee + $processingFee + $sitePlanFee;
      } else {
          // Single land use (Commercial/Industrial => commercial rates; else residential)
          if ($landUse === 'Commercial' || $landUse === 'Industrial') {
              $applicationFee = '20000.00';
              $processingFee  = '50000.00';
              $sitePlanFee    = '10000.00';
          } else {
              $applicationFee = '10000.00';
              $processingFee  = '20000.00';
              $sitePlanFee    = '10000.00';
          }
          $totalFee = floatval($applicationFee) + floatval($processingFee) + floatval($sitePlanFee);
      }
  @endphp

  @if ($landUse === 'Mixed')
    <h4 class="font-medium text-center mb-2"> </h4>
    
    <!-- Application Fee -->
    <div class="border rounded-md p-4 mb-4 bg-white">
      <div class="grid grid-cols-3 gap-4 mb-3">
        <div>
          <label class="flex items-center text-sm mb-1">
            <i data-lucide="file-text" class="w-4 h-4 mr-1 text-green-600"></i>
            Application fee (₦)
          </label>
          <input type="text" class="w-full p-2 border border-gray-300 rounded-md fee-input bg-blue-50" name="application_fee" value="{{ number_format($applicationFee, 2) }}" readonly>
        </div>
        <div>
          <label class="flex items-center text-sm mb-1">
            <i data-lucide="calendar" class="w-4 h-4 mr-1 text-green-600"></i>
            has been paid on
          </label>
          <input type="date" class="w-full p-2 border border-gray-300 rounded-md"  name="application_fee_payment_date">
        </div>
        <div>
          <label class="flex items-center text-sm mb-1">
            <i data-lucide="receipt" class="w-4 h-4 mr-1 text-green-600"></i>
            with receipt No.
          </label>
          <input type="text" class="w-full p-2 border border-gray-300 rounded-md" placeholder="Enter receipt number" name="application_fee_receipt_number" oninput="this.value = this.value.replace(/[₦$€£¥]/g, '')">
        </div>
      </div>
    </div>

    <!-- Processing Fee -->
    <div class="border rounded-md p-4 mb-4 bg-white">
      <div class="grid grid-cols-3 gap-4 mb-3">
        <div>
          <label class="flex items-center text-sm mb-1">
            <i data-lucide="file-check" class="w-4 h-4 mr-1 text-green-600"></i>
            Processing fee (₦)
          </label>
          <input type="text" class="w-full p-2 border border-gray-300 rounded-md fee-input bg-blue-50" name="processing_fee" value="{{ number_format($processingFee, 2) }}" readonly>
        </div>
        <div>
          <label class="flex items-center text-sm mb-1">
            <i data-lucide="calendar" class="w-4 h-4 mr-1 text-green-600"></i>
            has been paid on
          </label>
          <input type="date" class="w-full p-2 border border-gray-300 rounded-md"  name="processing_fee_payment_date">
        </div>
        <div>
          <label class="flex items-center text-sm mb-1">
            <i data-lucide="receipt" class="w-4 h-4 mr-1 text-green-600"></i>
            with receipt No.
          </label>
          <input type="text" class="w-full p-2 border border-gray-300 rounded-md" placeholder="Enter receipt number" name="processing_fee_receipt_number" oninput="this.value = this.value.replace(/[₦$€£¥]/g, '')">
        </div>
      </div>
    </div>

    <!-- Site Plan Fee -->
    <div class="border rounded-md p-4 mb-6 bg-white">
      <div class="grid grid-cols-3 gap-4 mb-3">
        <div>
          <label class="flex items-center text-sm mb-1">
            <i data-lucide="map" class="w-4 h-4 mr-1 text-green-600"></i>
            Site Plan (₦)
          </label>
          <input type="text" class="w-full p-2 border border-gray-300 rounded-md fee-input bg-blue-50" name="site_plan_fee" value="{{ number_format($sitePlanFee, 2) }}" readonly>
        </div>
        <div>
          <label class="flex items-center text-sm mb-1">
            <i data-lucide="calendar" class="w-4 h-4 mr-1 text-green-600"></i>
            has been paid on
          </label>
          <input type="date" class="w-full p-2 border border-gray-300 rounded-md"  name="site_plan_fee_payment_date">
        </div>
        <div>
          <label class="flex items-center text-sm mb-1">
            <i data-lucide="receipt" class="w-4 h-4 mr-1 text-green-600"></i>
            with receipt No.
          </label>
          <input type="text" class="w-full p-2 border border-gray-300 rounded-md" placeholder="Enter receipt number" name="site_plan_fee_receipt_number" oninput="this.value = this.value.replace(/[₦$€£¥]/g, '')">
        </div>
      </div>
    </div>

    <div class="flex justify-between items-center mb-4">
      <div class="flex items-center">
        <i data-lucide="file-text" class="w-4 h-4 mr-1 text-green-600"></i>
        <span>Total Mixed Initial Bill (Residential + Commercial):</span>
      </div>
      <span class="font-bold" id="total-amount">₦{{ number_format($totalFee, 2) }}</span>
    </div>
  @else
    <!-- Single land use (Commercial/Industrial or Residential) -->
    
    <!-- Application Fee -->
    <div class="border rounded-md p-4 mb-4 bg-white">
      <div class="grid grid-cols-3 gap-4 mb-3">
        <div>
          <label class="flex items-center text-sm mb-1">
            <i data-lucide="file-text" class="w-4 h-4 mr-1 text-green-600"></i>
            Application fee (₦)
          </label>
          <input type="text" class="w-full p-2 border border-gray-300 rounded-md fee-input bg-blue-50" name="application_fee" value="{{ number_format($applicationFee, 2) }}" readonly>
        </div>
        <div>
          <label class="flex items-center text-sm mb-1">
            <i data-lucide="calendar" class="w-4 h-4 mr-1 text-green-600"></i>
            has been paid on
          </label>
          <input type="date" class="w-full p-2 border border-gray-300 rounded-md"  name="application_fee_payment_date">
        </div>
        <div>
          <label class="flex items-center text-sm mb-1">
            <i data-lucide="receipt" class="w-4 h-4 mr-1 text-green-600"></i>
            with receipt No.
          </label>
          <input type="text" class="w-full p-2 border border-gray-300 rounded-md" placeholder="Enter receipt number" name="application_fee_receipt_number" oninput="this.value = this.value.replace(/[₦$€£¥]/g, '')">
        </div>
      </div>
    </div>

    <!-- Processing Fee -->
    <div class="border rounded-md p-4 mb-4 bg-white">
      <div class="grid grid-cols-3 gap-4 mb-3">
        <div>
          <label class="flex items-center text-sm mb-1">
            <i data-lucide="file-check" class="w-4 h-4 mr-1 text-green-600"></i>
            Processing fee (₦)
          </label>
          <input type="text" class="w-full p-2 border border-gray-300 rounded-md fee-input bg-blue-50" name="processing_fee" value="{{ number_format($processingFee, 2) }}" readonly>
        </div>
        <div>
          <label class="flex items-center text-sm mb-1">
            <i data-lucide="calendar" class="w-4 h-4 mr-1 text-green-600"></i>
            has been paid on
          </label>
          <input type="date" class="w-full p-2 border border-gray-300 rounded-md"  name="processing_fee_payment_date">
        </div>
        <div>
          <label class="flex items-center text-sm mb-1">
            <i data-lucide="receipt" class="w-4 h-4 mr-1 text-green-600"></i>
            with receipt No.
          </label>
          <input type="text" class="w-full p-2 border border-gray-300 rounded-md" placeholder="Enter receipt number" name="processing_fee_receipt_number" oninput="this.value = this.value.replace(/[₦$€£¥]/g, '')">
        </div>
      </div>
    </div>

    <!-- Site Plan Fee -->
    <div class="border rounded-md p-4 mb-4 bg-white">
      <div class="grid grid-cols-3 gap-4 mb-3">
        <div>
          <label class="flex items-center text-sm mb-1">
            <i data-lucide="map" class="w-4 h-4 mr-1 text-green-600"></i>
            Site Plan (₦)
          </label>
          <input type="text" class="w-full p-2 border border-gray-300 rounded-md fee-input bg-blue-50" name="site_plan_fee" value="{{ number_format($sitePlanFee, 2) }}" readonly>
        </div>
        <div>
          <label class="flex items-center text-sm mb-1">
            <i data-lucide="calendar" class="w-4 h-4 mr-1 text-green-600"></i>
            has been paid on
          </label>
          <input type="date" class="w-full p-2 border border-gray-300 rounded-md"  name="site_plan_fee_payment_date">
        </div>
        <div>
          <label class="flex items-center text-sm mb-1">
            <i data-lucide="receipt" class="w-4 h-4 mr-1 text-green-600"></i>
            with receipt No.
          </label>
          <input type="text" class="w-full p-2 border border-gray-300 rounded-md" placeholder="Enter receipt number" name="site_plan_fee_receipt_number" oninput="this.value = this.value.replace(/[₦$€£¥]/g, '')">
        </div>
      </div>
    </div>
  
    <div class="flex justify-between items-center mb-4">
      <div class="flex items-center">
        <i data-lucide="file-text" class="w-4 h-4 mr-1 text-green-600"></i>
        <span>Total:</span>
      </div>
      <span class="font-bold" id="total-amount">₦{{ number_format($totalFee, 2) }}</span>
    </div>
  @endif


</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
  // Since the fee inputs are now readonly and pre-populated,
  // we just need to ensure the total is displayed correctly
  const feeInputs = document.querySelectorAll('.fee-input');
  const totalDisplay = document.getElementById('total-amount');
  
  // Function to calculate and update the total
  function updateTotal() {
      let total = 0;
      feeInputs.forEach(input => {
          // Remove commas and parse the value
          const cleanValue = input.value.replace(/,/g, '');
          const value = parseFloat(cleanValue) || 0;
          total += value;
      });
      
      // Format the total with 2 decimal places and the Naira symbol
      totalDisplay.textContent = '₦' + total.toLocaleString('en-US', {
          minimumFractionDigits: 2,
          maximumFractionDigits: 2
      });
  }
  
  // Calculate initial total on page load
  updateTotal();
  
  // Add a visual indicator that these fields are auto-calculated
  feeInputs.forEach(input => {
      input.title = 'This amount is automatically calculated based on the land use type';
  });
});
</script>