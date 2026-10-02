@extends('layouts.app')

@section('page-title')
  {{ $PageTitle ?? __('KLAES') }}
@endsection

@section('content')
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
  @include('programmes.partials.style')

  <!-- Print Dialog Loading Indicator -->
  <div id="print-loading" style="display:none;">
    <div class="spinner"></div>
    <div>Preparing your document...</div>
  </div>

  <!-- Main Content Container - This will remain in place -->
  <div id="main-content" class="flex-1 overflow-auto">
    <!-- Header -->
    @include($headerPartial ?? 'admin.header')

    <!-- Main Content -->
    <div class="p-6">
      <!-- Filter Form -->
      <div class="bg-white rounded-lg shadow-md border border-gray-200 p-6 mb-6">
        <div class="flex items-center justify-between mb-4">
          <div class="flex items-center">
            <h3 class="text-lg font-semibold text-gray-800">
              <i data-lucide="filter" class="w-5 h-5 inline-block mr-2 text-blue-600"></i>
              Payments
            </h3>
            <button id="toggle-filters-btn"
              class="ml-4 bg-blue-600 hover:bg-blue-700 text-white font-medium py-1.5 px-3 rounded-md transition-colors shadow-sm flex items-center justify-center text-sm">
              <i data-lucide="filter" class="w-3.5 h-3.5 mr-1"></i>
              Filter
            </button>
          </div>
          <span class="text-sm text-gray-500">Filter payment records by date, type and status</span>
        </div>

        <!-- Filter form that's hidden by default -->
        <div id="payment-filter-container" style="display: none;">
          <form id="payment-filter-form" class="grid grid-cols-1 md:grid-cols-5 gap-5">
            <div class="col-span-1">
              <label class="block text-sm font-medium text-gray-700 mb-1.5">Start Date</label>
              <div class="relative">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                  <i data-lucide="calendar" class="w-4 h-4 text-gray-500"></i>
                </div>
                <input type="text" id="start-date" name="start_date"
                  class="w-full pl-10 rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 transition-all"
                  placeholder="Select start date">
              </div>
            </div>

            <div class="col-span-1">
              <label class="block text-sm font-medium text-gray-700 mb-1.5">End Date</label>
              <div class="relative">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                  <i data-lucide="calendar" class="w-4 h-4 text-gray-500"></i>
                </div>
                <input type="text" id="end-date" name="end_date"
                  class="w-full pl-10 rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 transition-all"
                  placeholder="Select end date">
              </div>
            </div>

            <div class="col-span-1">
              <label class="block text-sm font-medium text-gray-700 mb-1.5">Payment Type</label>
              <div class="relative">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                  <i data-lucide="credit-card" class="w-4 h-4 text-gray-500"></i>
                </div>
                <select id="payment-type" name="payment_type"
                  class="w-full pl-10 rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 appearance-none transition-all">
                  <option value="all">All Payments</option>
                  @if(request()->get('source') === 'pp_conversion')
                    <option value="betterment">Betterment Charges</option>
                  @else
                    <option value="initial">Initial Bill</option>
                    <option value="betterment">Betterment Charges</option>
                    <option value="final">Final Bill</option>
                  @endif
                </select>
                <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none">
                  <i data-lucide="chevron-down" class="w-4 h-4 text-gray-500"></i>
                </div>
              </div>
            </div>

            <div class="col-span-1">
              <label class="block text-sm font-medium text-gray-700 mb-1.5">Payment Status</label>
              <div class="relative">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                  <i data-lucide="check-circle-2" class="w-4 h-4 text-gray-500"></i>
                </div>
                <select id="payment-status" name="payment_status"
                  class="w-full pl-10 rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 appearance-none transition-all">
                  <option value="all">All Status</option>
                  <option value="Complete">Complete</option>
                  <option value="Incomplete">Incomplete</option>
                </select>
                <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none">
                  <i data-lucide="chevron-down" class="w-4 h-4 text-gray-500"></i>
                </div>
              </div>
            </div>

            <div class="col-span-1 flex items-end gap-2">
              <button type="submit"
                class="flex-1 bg-blue-600 hover:bg-blue-700 text-white font-medium py-2.5 px-4 rounded-md transition-colors shadow-sm flex items-center justify-center">
                <i data-lucide="filter" class="w-4 h-4 mr-2"></i>
                Apply
              </button>
              <button type="reset"
                class="bg-gray-100 hover:bg-gray-200 text-gray-700 font-medium py-2.5 px-3 rounded-md transition-colors shadow-sm">
                <i data-lucide="x" class="w-4 h-4"></i>
              </button>
            </div>
          </form>
        </div>
      </div>

      <script>
        document.addEventListener('DOMContentLoaded', function () {
          // Add toggle functionality for the filter form
          document.getElementById('toggle-filters-btn').addEventListener('click', function () {
            const filterContainer = document.getElementById('payment-filter-container');
            if (filterContainer.style.display === 'none') {
              filterContainer.style.display = 'block';
            } else {
              filterContainer.style.display = 'none';
            }
          });
        });
      </script>


      @if(request()->get('url') === 'report')
        {{-- show the print‐friendly report --}}
        @include('programmes.partials.payments_report')
        <!-- Payment Visualization -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
          <div class="bg-white rounded-md shadow-sm border border-gray-200 p-6">
            <h3 class="text-lg font-semibold mb-4">Payment Status Distribution</h3>
            <div class="chart-container">
              <canvas id="statusChart"></canvas>
            </div>
          </div>

          <div class="bg-white rounded-md shadow-sm border border-gray-200 p-6">
            <h3 class="text-lg font-semibold mb-4">Payment Trends</h3>
            <div class="chart-container">
              <canvas id="trendsChart"></canvas>
            </div>
          </div>

          <div class="bg-white rounded-md shadow-sm border border-gray-200 p-6">
            <h3 class="text-lg font-semibold mb-4">Application Type Comparison</h3>
            <div class="chart-container">
              <canvas id="comparisonChart"></canvas>
            </div>
          </div>
        </div>

        <!-- Report Print Button -->
        <div class="flex justify-end mb-6">
          <button id="print-charts-btn"
            class="flex items-center space-x-2 px-4 py-2 bg-green-600 text-white rounded-md shadow-sm hover:bg-green-700">
            <i data-lucide="printer" class="w-4 h-4"></i>
            <span>Print Payment Summary</span>
          </button>
        </div>
      @else
        {{-- show the normal payments table --}}
        <div class="bg-white rounded-md shadow-sm border border-gray-200" id="payments-table">
          <!-- … all your Primary/Unit Applications tabs & tables … -->
        </div>
      @endif

      @php
        $resolveFeeStatus = function ($amount, $date = null, $receipt = null) {
          $amount = floatval($amount ?? 0);
          if ($amount <= 0) {
            return ['label' => 'Not Paid', 'class' => 'bg-red-100 text-red-700 border border-red-200'];
          }
          return ['label' => 'Paid', 'class' => 'bg-green-100 text-green-700 border border-green-200'];
        };

        $formatFeeDate = function ($date) {
          if (empty($date)) {
            return null;
          }
          try {
            return \Carbon\Carbon::parse($date)->format('M d, Y');
          } catch (\Exception $e) {
            return $date;
          }
        };
      @endphp


      <!-- Payments Tabs -->
      <div class="bg-white rounded-md shadow-sm border border-gray-200" id="payments-table"
        style="{{ request()->get('url') === 'report' ? 'display: none;' : '' }}">
        <div class="border-b border-gray-200">
          <div class="flex">
            <!-- Remove inline onclick attributes -->
            <button id="primary-tab" class="px-6 py-3 tab-active border-t border-l border-r">
              @if(request()->get('source') === 'pp_conversion')
                Conversion Applications ({{ count($primaryPayments) }})
              @else
                Primary Applications ({{ count($primaryPayments) }})
              @endif
            </button>
            @if(request()->get('url') !== 'phycal_plan' && request()->get('source') !== 'pp_conversion')
              <button id="unit-tab" class="px-6 py-3 tab-inactive">
                Unit Applications ({{ count($unitPayments) }})
              </button>
            @endif
          </div>
        </div>

        <!-- Primary Applications Tab -->
        <div id="primary-content" class="p-6">
          <div class="flex justify-between items-center mb-6">
            <h2 class="text-xl font-bold">
              @if(request()->get('source') === 'pp_conversion')
                Conversion Application Payments
              @else
                Primary Application Payments
              @endif
            </h2>

            <div class="flex items-center space-x-4">
              <div class="relative">
                <select id="primary-filter"
                  class="pl-4 pr-8 py-2 border border-gray-200 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 appearance-none">
                  <option value="all">All...</option>
                  <option value="paid">Complete</option>
                  <option value="pending">Incomplete</option>
                  <option value="overdue">Overdue</option>
                </select>
                <i data-lucide="chevron-down"
                  class="absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-400 w-4 h-4"></i>
              </div>

              <button id="print-primary-btn"
                class="flex items-center space-x-2 px-4 py-2 border border-gray-200 rounded-md">
                <i data-lucide="printer" class="w-4 h-4 text-gray-600"></i>
                <span>Print</span>
              </button>
            </div>
          </div>

          @if(request()->get('source') === 'pp_conversion')
          {{-- Conversion-specific payments table (simplified) --}}
          <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
              <thead class="bg-gray-50">
                <tr>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">File No</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Ref ID</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Applicant</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Land Use</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Area (m²)</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Betterment Charges</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Receipt No</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                </tr>
              </thead>
              <tbody class="bg-white divide-y divide-gray-200">
                @forelse($primaryPayments as $payment)
                  @php
                    $betterment = floatval($payment->Betterment_Charges ?? 0);
                    $area = floatval($payment->property_value ?? 0);
                    $status = $payment->Payment_Status ?? 'Pending';
                    $statusClass = match(strtolower($status)) {
                      'paid', 'complete' => 'bg-green-100 text-green-800',
                      'partial' => 'bg-yellow-100 text-yellow-800',
                      default => 'bg-red-100 text-red-800',
                    };
                    $paymentDate = $payment->receipt_date ?? $payment->created_at;
                    $paymentDateDisplay = $paymentDate ? \Carbon\Carbon::parse($paymentDate)->format('M d, Y') : 'N/A';
                  @endphp
                  <tr class="hover:bg-gray-50 transition-colors">
                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                      <div class="flex items-center">
                        <i data-lucide="file-text" class="w-4 h-4 text-gray-400 mr-2"></i>
                        {{ $payment->Sectional_Title_File_No ?? $payment->jsi_file_number ?? 'N/A' }}
                      </div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-xs text-blue-700 font-semibold">
                      {{ $payment->ref_id ?? '—' }}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                      {{ ucwords(strtolower($payment->owner_name ?? 'N/A')) }}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                      {{ $payment->applied_land_use ?? '—' }}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                      {{ $area > 0 ? number_format($area, 2) : '—' }}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm font-semibold text-gray-900">
                      ₦{{ number_format($betterment, 2) }}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-xs text-gray-600">
                      @if(!empty($payment->Betterment_receipt))
                        <span class="inline-block px-2 py-0.5 bg-green-50 text-green-700 text-xs font-bold rounded border border-green-200 uppercase w-fit">Rec: {{ $payment->Betterment_receipt }}</span>
                      @else
                        —
                      @endif
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-xs text-gray-600">
                      {{ $paymentDateDisplay }}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                      <span class="px-2 py-1 text-xs font-medium rounded-full {{ $statusClass }}">
                        {{ $status }}
                      </span>
                    </td>
                  </tr>
                @empty
                  <tr>
                    <td colspan="9" class="px-6 py-8 text-center text-gray-500">
                      <div class="flex flex-col items-center">
                        <i data-lucide="inbox" class="w-12 h-12 text-gray-300 mb-2"></i>
                        <p class="text-sm">No conversion payment records found</p>
                      </div>
                    </td>
                  </tr>
                @endforelse
              </tbody>
            </table>
          </div>
          @else
          <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
              <thead class="bg-gray-50">
                <tr>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">File No</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">NP File No
                  </th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Owner</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Application
                    Fee</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Processing
                    Fee</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Site Plan Fee
                  </th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Betterment
                    Charges</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Penalty</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                </tr>
              </thead>
              <tbody class="bg-white divide-y divide-gray-200">
                @forelse($primaryPayments as $payment)
                  @php
                    $total = floatval($payment->application_fee ?? 0) +
                      floatval($payment->processing_fee ?? 0) +
                      floatval($payment->site_plan_fee ?? 0) +
                      floatval($payment->Betterment_Charges ?? 0) +
                      floatval($payment->Penalty_Fees ?? 0);

                    // Determine if all main bills (excluding Penalty) are paid
                    $mainPaid = (
                      floatval($payment->application_fee ?? 0) > 0 &&
                      floatval($payment->processing_fee ?? 0) > 0 &&
                      floatval($payment->site_plan_fee ?? 0) > 0 &&
                      floatval($payment->Betterment_Charges ?? 0) > 0
                    );
                    $status = $mainPaid ? 'Complete' : ($payment->Payment_Status ?? 'Incomplete');
                    $statusClass = match (strtolower($status)) {
                      'complete', 'paid' => 'bg-green-100 text-green-800',
                      'incomplete', 'pending' => 'bg-yellow-100 text-yellow-800',
                      'overdue' => 'bg-red-100 text-red-800',
                      default => 'bg-gray-100 text-gray-800'
                    };

                    // Get np_fileno if available (from mother_applications)
                    $np_fileno = null;
                    if (!empty($payment->Sectional_Title_File_No)) {
                      $np_fileno = \DB::connection('sqlsrv')->table('mother_applications')
                        ->where('fileno', $payment->Sectional_Title_File_No)
                        ->value('np_fileno');
                    }

                    $paymentDateRaw = $payment->payment_date ?? $payment->created_at;
                    $paymentDateDisplay = $paymentDateRaw
                      ? \Carbon\Carbon::parse($paymentDateRaw)->format('M d, Y')
                      : 'N/A';

                    $applicationFeeStatus = $resolveFeeStatus(
                      $payment->application_fee ?? 0,
                      $payment->application_fee_payment_date ?? null,
                      $payment->application_fee_receipt_number ?? null
                    );
                    $processingFeeStatus = $resolveFeeStatus(
                      $payment->processing_fee ?? 0,
                      $payment->processing_fee_payment_date ?? null,
                      $payment->processing_fee_receipt_number ?? null
                    );
                    $sitePlanFeeStatus = $resolveFeeStatus(
                      $payment->site_plan_fee ?? 0,
                      $payment->site_plan_fee_payment_date ?? null,
                      $payment->site_plan_fee_receipt_number ?? null
                    );
                    $bettermentStatus = $resolveFeeStatus($payment->Betterment_Charges ?? 0);
                    $penaltyStatus = $resolveFeeStatus($payment->Penalty_Fees ?? 0);

                    $primaryFeeGroups = [
                      [
                        'title' => 'Initial Bill',
                        'fees' => [
                          [
                            'label' => 'Application Fee',
                            'amount' => floatval($payment->application_fee ?? 0),
                            'status' => $applicationFeeStatus['label'],
                            'status_class' => $applicationFeeStatus['class'],
                            'payment_date' => $formatFeeDate($payment->application_fee_payment_date ?? null),
                            'receipt' => $payment->application_fee_receipt_number ?? null,
                          ],
                          [
                            'label' => 'Processing Fee',
                            'amount' => floatval($payment->processing_fee ?? 0),
                            'status' => $processingFeeStatus['label'],
                            'status_class' => $processingFeeStatus['class'],
                            'payment_date' => $formatFeeDate($payment->processing_fee_payment_date ?? null),
                            'receipt' => $payment->processing_fee_receipt_number ?? null,
                          ],
                          [
                            'label' => 'Site Plan Fee',
                            'amount' => floatval($payment->site_plan_fee ?? 0),
                            'status' => $sitePlanFeeStatus['label'],
                            'status_class' => $sitePlanFeeStatus['class'],
                            'payment_date' => $formatFeeDate($payment->site_plan_fee_payment_date ?? null),
                            'receipt' => $payment->site_plan_fee_receipt_number ?? null,
                          ],
                        ],
                      ],
                      [
                        'title' => 'Betterment Bill',
                        'fees' => [
                          [
                            'label' => 'Betterment Charges',
                            'amount' => floatval($payment->Betterment_Charges ?? 0),
                            'status' => $bettermentStatus['label'],
                            'status_class' => $bettermentStatus['class'],
                            'payment_date' => null,
                            'receipt' => $payment->Betterment_receipt ?? null,
                          ],
                          [
                            'label' => 'Penalty',
                            'amount' => floatval($payment->Penalty_Fees ?? 0),
                            'status' => $penaltyStatus['label'],
                            'status_class' => $penaltyStatus['class'],
                            'payment_date' => null,
                            'receipt' => null,
                          ],
                        ],
                      ],
                    ];

                    $paymentDetails = [
                      'type' => 'Primary',
                      'file_no' => $payment->Sectional_Title_File_No,
                      'np_file_no' => $np_fileno,
                      'owner' => ucwords(strtolower($payment->owner_name)),
                      'status' => $status,
                      'status_class' => $statusClass,
                      'payment_date' => $paymentDateDisplay,
                      'receipt_number' => $payment->receipt_number ?? null,
                      'is_paid' => $mainPaid,
                      'amount_due' => $total,
                      'notes' => $payment->notes ?? null,
                      'groups' => $primaryFeeGroups,
                    ];
                  @endphp
                  <tr class="hover:bg-gray-50 transition-colors">
                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                      <div class="flex items-center">
                        <i data-lucide="file-text" class="w-4 h-4 text-gray-400 mr-2"></i>
                        {{ $payment->Sectional_Title_File_No }}
                      </div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-xs text-teal-700 font-semibold">
                      @if($np_fileno)
                        <span class="bg-teal-50 px-2 py-1 rounded">{{ $np_fileno }}</span>
                      @else
                        <span class="text-gray-300">-</span>
                      @endif
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                      <div class="flex items-center">
                        <div class="flex-shrink-0 h-8 w-8">
                          <div class="h-8 w-8 rounded-full bg-blue-100 flex items-center justify-center">
                            <span class="text-xs font-medium text-blue-800">
                              {{ substr(ucwords(strtolower($payment->owner_name)), 0, 2) }}
                            </span>
                          </div>
                        </div>
                        <div class="ml-3">
                          <div class="text-sm font-bold text-gray-900">{{ strtoupper($payment->owner_name) }}
                          </div>
                        </div>
                      </div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 font-mono">
                      <div class="flex flex-col">
                        <span class="text-green-600 font-semibold">N {{ number_format(floatval($payment->application_fee ?? 0), 2) }}</span>
                        @if(!empty($payment->application_fee_receipt_number))
                          <span class="inline-block mt-1 px-1.5 py-0.5 bg-blue-50 text-blue-700 text-xs font-bold rounded border border-blue-200 uppercase w-fit">Rec: {{ $payment->application_fee_receipt_number }}</span>
                        @else
                          <button onclick="enterSpecificReceipt('{{ $payment->Sectional_Title_File_No }}', 'application_fee', '', '{{ number_format(floatval($payment->application_fee ?? 0), 2) }}')" class="text-[10px] text-blue-600 hover:underline font-normal">+ Add Receipt</button>
                        @endif
                      </div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 font-mono">
                      <div class="flex flex-col">
                        <span class="text-green-600 font-semibold">N {{ number_format(floatval($payment->processing_fee ?? 0), 2) }}</span>
                        @if(!empty($payment->processing_fee_receipt_number))
                          <span class="inline-block mt-1 px-1.5 py-0.5 bg-indigo-50 text-indigo-700 text-xs font-bold rounded border border-indigo-200 uppercase w-fit">Rec: {{ $payment->processing_fee_receipt_number }}</span>
                        @else
                          <button onclick="enterSpecificReceipt('{{ $payment->Sectional_Title_File_No }}', 'processing_fee', '', '{{ number_format(floatval($payment->processing_fee ?? 0), 2) }}')" class="text-[10px] text-blue-600 hover:underline font-normal">+ Add Receipt</button>
                        @endif
                      </div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 font-mono">
                      <div class="flex flex-col">
                        <span class="text-green-700 font-bold">₦{{ number_format(floatval($payment->site_plan_fee ?? 0), 2) }}</span>
                        @if(!empty($payment->site_plan_fee_receipt_number))
                          <span class="inline-block mt-1 px-1.5 py-0.5 bg-teal-50 text-teal-700 text-xs font-bold rounded border border-teal-200 uppercase w-fit">Rec: {{ $payment->site_plan_fee_receipt_number }}</span>
                        @else
                          <button onclick="enterSpecificReceipt('{{ $payment->Sectional_Title_File_No }}', 'site_plan_fee', '', '{{ number_format(floatval($payment->site_plan_fee ?? 0), 2) }}')" class="text-[10px] text-blue-600 hover:underline font-normal">+ Add Receipt</button>
                        @endif
                      </div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 font-mono">
                      <div class="flex flex-col">
                        <span class="text-green-600 font-semibold">N {{ number_format(floatval($payment->Betterment_Charges ?? 0), 2) }}</span>
                        @if(!empty($payment->Betterment_receipt))
                          <span class="inline-block mt-1 px-1.5 py-0.5 bg-green-50 text-green-700 text-xs font-bold rounded border border-green-200 uppercase w-fit">Rec: {{ $payment->Betterment_receipt }}</span>
                        @endif
                      </div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 font-mono">
                      <span class="text-red-600 font-semibold">N
                        {{ number_format(floatval($payment->Penalty_Fees ?? 0), 2) }}</span>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                      <div class="flex items-center">
                        <i data-lucide="calendar" class="w-4 h-4 text-gray-400 mr-2"></i>
                        {{ \Carbon\Carbon::parse($payment->created_at)->format('M d, Y') }}
                      </div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                      <span
                        class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $statusClass }}">
                        @if(strtolower($status) === 'complete' || strtolower($status) === 'paid')
                          <i data-lucide="check-circle" class="w-3 h-3 mr-1"></i>
                        @elseif(strtolower($status) === 'incomplete' || strtolower($status) === 'pending')
                          <i data-lucide="clock" class="w-3 h-3 mr-1"></i>
                        @else
                          <i data-lucide="alert-circle" class="w-3 h-3 mr-1"></i>
                        @endif
                        {{ $status }}
                      </span>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm font-bold text-gray-900">
                      <div class="flex items-center">
                        <span class="text-lg text-blue-600">N {{ number_format($total, 2) }}</span>
                      </div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                      <div class="relative inline-block text-left" x-data="{ open: false }">
                        <button type="button" @click="open = !open"
                          class="inline-flex items-center px-3 py-2 border border-gray-300 shadow-sm text-sm leading-4 font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                          <i data-lucide="more-horizontal" class="w-4 h-4"></i>

                        </button>

                        <div x-show="open" @click.away="open = false" x-transition
                          class="origin-top-right absolute right-0 mt-2 w-56 rounded-md shadow-lg bg-white ring-1 ring-black ring-opacity-5 focus:within:outline-none z-10">
                          <div class="py-1" role="menu">
                            <button type="button" @click="viewInitialBillReceipt('{{ $payment->Sectional_Title_File_No }}')"
                              class="group flex items-center px-3 py-1 text-xs text-gray-700 hover:bg-gray-100 hover:text-gray-900 w-full text-left">
                              <i data-lucide="file-text"
                                class="mr-2 h-3.5 w-3.5 text-gray-400 group-hover:text-gray-500"></i>
                              View Initial Bill Receipt
                            </button>
                            <button type="button" data-payment='@json($paymentDetails)'
                              @click="viewPaymentDetails(JSON.parse($el.dataset.payment))"
                              class="group flex items-center px-3 py-1 text-xs text-gray-700 hover:bg-gray-100 hover:text-gray-900 w-full text-left">
                              <i data-lucide="eye" class="mr-2 h-3.5 w-3.5 text-gray-400 group-hover:text-gray-500"></i>
                              View Payment
                            </button>
                            <button type="button"
                              @click="viewBettermentBillReference('{{ $payment->Sectional_Title_File_No }}')"
                              class="group flex items-center px-3 py-1 text-xs text-gray-700 hover:bg-gray-100 hover:text-gray-900 w-full text-left">
                              <i data-lucide="hash" class="mr-2 h-3.5 w-3.5 text-gray-400 group-hover:text-gray-500"></i>
                              View Betterment Bill Reference
                            </button>
                            @php
                              // Check if betterment receipt already exists
                              $hasBettermentReceipt = !empty($payment->Betterment_receipt);
                            @endphp
                            <button type="button"
                              @click="enterBettermentBillReceipt('{{ $payment->Sectional_Title_File_No }}', '{{ number_format(floatval($payment->Betterment_Charges ?? 0), 2) }}')"
                              class="group flex items-center px-3 py-1 text-xs w-full text-left {{ $hasBettermentReceipt ? 'text-gray-400 cursor-not-allowed bg-gray-50' : 'text-gray-700 hover:bg-gray-100 hover:text-gray-900' }}"
                              {{ $hasBettermentReceipt ? 'disabled' : '' }}>
                              <i data-lucide="plus-circle"
                                class="mr-2 h-3.5 w-3.5 {{ $hasBettermentReceipt ? 'text-gray-300' : 'text-gray-400 group-hover:text-gray-500' }}"></i>
                              {{ $hasBettermentReceipt ? 'Betterment Receipt Entered' : 'Enter Betterment Receipt' }}
                            </button>
                          </div>
                        </div>
                      </div>
                    </td>
                  </tr>
                @empty
                  <tr>
                    <td colspan="12" class="px-6 py-8 text-center text-gray-500">
                      <div class="flex flex-col items-center">
                        <i data-lucide="inbox" class="w-12 h-12 text-gray-300 mb-2"></i>
                        <p class="text-sm">No primary application payment records found</p>
                      </div>
                    </td>
                  </tr>
                @endforelse
              </tbody>
            </table>
          </div>
          @endif
        </div>

        <!-- Unit Applications Tab -->
        <div id="unit-content" class="p-6 hidden">
          <div class="flex justify-between items-center mb-6">
            <h2 class="text-xl font-bold">Unit Application Payments</h2>

            <div class="flex items-center space-x-4">
              <div class="relative">
                <select id="unit-filter"
                  class="pl-4 pr-8 py-2 border border-gray-200 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 appearance-none">
                  <option value="all">All...</option>
                  <option value="paid">Complete</option>
                  <option value="pending">Incomplete</option>
                  <option value="overdue">Overdue</option>
                </select>
                <i data-lucide="chevron-down"
                  class="absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-400 w-4 h-4"></i>
              </div>

              <button id="print-unit-btn" class="flex items-center space-x-2 px-4 py-2 border border-gray-200 rounded-md">
                <i data-lucide="printer" class="w-4 h-4 text-gray-600"></i>
                <span>Print</span>
              </button>
            </div>
          </div>

          <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
              <thead class="bg-gray-50">
                <tr>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">File No</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Unit Owner
                  </th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Unit Type
                  </th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Application
                    Fee</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Processing
                    Fee</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Survey Fee
                  </th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    Recertification Fee</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Assignment
                    Fee</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Betterment
                    Charges</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Bill Balance
                  </th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Development
                    Charges</th>

                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Penalty</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                </tr>
              </thead>
              <tbody class="bg-white divide-y divide-gray-200">
                @forelse($unitPayments as $payment)
                  @php
                    $total = floatval($payment->application_fee ?? 0) +
                      floatval($payment->processing_fee ?? 0) +
                      floatval($payment->site_plan_fee ?? 0) +
                      floatval($payment->recertification_fee ?? 0) +
                      floatval($payment->assignment_fee ?? 0) +
                      floatval($payment->dev_charges ?? 0) +
                      floatval($payment->bill_balance ?? 0) +
                      floatval($payment->Penalty_Fees ?? 0);

                    // For Unit Applications: Exclude Development Charges and Penalty from completion check
                    // Payment is complete when core fees are paid (excluding dev_charges and penalty)
                    $mainPaid = (
                      floatval($payment->application_fee ?? 0) > 0 &&
                      floatval($payment->processing_fee ?? 0) > 0 &&
                      floatval($payment->site_plan_fee ?? 0) > 0 &&
                      floatval($payment->recertification_fee ?? 0) > 0 &&
                      floatval($payment->assignment_fee ?? 0) > 0
                    );
                    $status = $mainPaid ? 'Complete' : ($payment->Payment_Status ?? 'Incomplete');
                    $statusClass = match (strtolower($status)) {
                      'complete', 'paid' => 'bg-green-100 text-green-800',
                      'incomplete', 'pending' => 'bg-yellow-100 text-yellow-800',
                      'overdue' => 'bg-red-100 text-red-800',
                      default => 'bg-gray-100 text-gray-800'
                    };

                    $unitPaymentDateRaw = $payment->payment_date ?? $payment->created_at;
                    $unitPaymentDateDisplay = $unitPaymentDateRaw
                      ? \Carbon\Carbon::parse($unitPaymentDateRaw)->format('M d, Y')
                      : 'N/A';

                    $unitApplicationStatus = $resolveFeeStatus(
                      $payment->application_fee ?? 0,
                      $payment->application_fee_payment_date ?? null,
                      $payment->application_fee_receipt_number ?? null
                    );
                    $unitProcessingStatus = $resolveFeeStatus(
                      $payment->processing_fee ?? 0,
                      $payment->processing_fee_payment_date ?? null,
                      $payment->processing_fee_receipt_number ?? null
                    );
                    $unitSurveyStatus = $resolveFeeStatus(
                      $payment->site_plan_fee ?? 0,
                      $payment->survey_fee_payment_date ?? null,
                      $payment->survey_fee_receipt_number ?? null
                    );
                    $unitRecertStatus = $resolveFeeStatus($payment->recertification_fee ?? 0);
                    $unitAssignmentStatus = $resolveFeeStatus($payment->assignment_fee ?? 0);
                    $unitBillBalanceStatus = $resolveFeeStatus($payment->bill_balance ?? 0);
                    $unitDevChargeStatus = $resolveFeeStatus($payment->dev_charges ?? 0);
                    $unitPenaltyStatus = $resolveFeeStatus($payment->Penalty_Fees ?? 0);

                    $unitFeeGroups = [
                      [
                        'title' => 'Initial Bill',
                        'fees' => [
                          [
                            'label' => 'Application Fee',
                            'amount' => floatval($payment->application_fee ?? 0),
                            'status' => $unitApplicationStatus['label'],
                            'status_class' => $unitApplicationStatus['class'],
                            'payment_date' => $formatFeeDate($payment->application_fee_payment_date ?? null),
                            'receipt' => $payment->application_fee_receipt_number ?? null,
                          ],
                          [
                            'label' => 'Processing Fee',
                            'amount' => floatval($payment->processing_fee ?? 0),
                            'status' => $unitProcessingStatus['label'],
                            'status_class' => $unitProcessingStatus['class'],
                            'payment_date' => $formatFeeDate($payment->processing_fee_payment_date ?? null),
                            'receipt' => $payment->processing_fee_receipt_number ?? null,
                          ],
                          [
                            'label' => 'Survey Fee',
                            'amount' => floatval($payment->site_plan_fee ?? 0),
                            'status' => $unitSurveyStatus['label'],
                            'status_class' => $unitSurveyStatus['class'],
                            'payment_date' => $formatFeeDate($payment->survey_fee_payment_date ?? null),
                            'receipt' => $payment->survey_fee_receipt_number ?? null,
                          ],
                        ],
                      ],
                      [
                        'title' => 'Bill Balance',
                        'fees' => [
                          [
                            'label' => 'Recertification Fee',
                            'amount' => floatval($payment->recertification_fee ?? 0),
                            'status' => $unitRecertStatus['label'],
                            'status_class' => $unitRecertStatus['class'],
                            'payment_date' => null,
                            'receipt' => null,
                          ],
                          [
                            'label' => 'Assignment Fee',
                            'amount' => floatval($payment->assignment_fee ?? 0),
                            'status' => $unitAssignmentStatus['label'],
                            'status_class' => $unitAssignmentStatus['class'],
                            'payment_date' => null,
                            'receipt' => null,
                          ],
                          [
                            'label' => 'Bill Balance',
                            'amount' => floatval($payment->bill_balance ?? 0),
                            'status' => $unitBillBalanceStatus['label'],
                            'status_class' => $unitBillBalanceStatus['class'],
                            'payment_date' => null,
                            'receipt' => $payment->Bill_Balance_receipt ?? null,
                          ],
                          [
                            'label' => 'Development Charges',
                            'amount' => floatval($payment->dev_charges ?? 0),
                            'status' => $unitDevChargeStatus['label'],
                            'status_class' => $unitDevChargeStatus['class'],
                            'payment_date' => null,
                            'receipt' => null,
                          ],
                          [
                            'label' => 'Penalty',
                            'amount' => floatval($payment->Penalty_Fees ?? 0),
                            'status' => $unitPenaltyStatus['label'],
                            'status_class' => $unitPenaltyStatus['class'],
                            'payment_date' => null,
                            'receipt' => null,
                          ],
                        ],
                      ],
                    ];

                    $unitPaymentDetails = [
                      'type' => (strtoupper(trim($payment->unit_type ?? '')) === 'SUA' || ($payment->is_sua_unit ?? 0) ? 'SuA' : 'PuA') . ' Unit',
                      'file_no' => $payment->Sectional_Title_File_No,
                      'owner' => ucwords(strtolower($payment->owner_name)),
                      'status' => $status,
                      'status_class' => $statusClass,
                      'payment_date' => $unitPaymentDateDisplay,
                      'receipt_number' => $payment->receipt_number ?? null,
                      'is_paid' => $mainPaid,
                      'amount_due' => $total,
                      'notes' => $payment->notes ?? null,
                      'groups' => $unitFeeGroups,
                    ];
                  @endphp
                  <tr class="hover:bg-gray-50 transition-colors">
                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                      <div class="flex items-center">
                        <i data-lucide="file-text" class="w-4 h-4 text-gray-400 mr-2"></i>
                        {{ $payment->Sectional_Title_File_No }}
                      </div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                      <div class="flex items-center">
                        <div class="flex-shrink-0 h-8 w-8">
                          <div class="h-8 w-8 rounded-full bg-purple-100 flex items-center justify-center">
                            <span class="text-xs font-medium text-purple-800">
                              {{ substr(ucwords(strtolower($payment->owner_name)), 0, 2) }}
                            </span>
                          </div>
                        </div>
                        <div class="ml-3">
                          <div class="text-sm font-medium text-gray-900">{{ ucwords(strtolower($payment->owner_name)) }}
                          </div>
                          <div class="text-xs text-gray-500">Unit Owner</div>
                        </div>
                      </div>
                    </td>
                    @php
                      $rawUnitType = strtoupper(trim($payment->unit_type ?? ''));
                      $unitTypeLabel = $rawUnitType === 'SUA' || ($payment->is_sua_unit ?? 0) ? 'SuA' : 'PuA';
                      $isSua = $unitTypeLabel === 'SuA';
                      $bettermentAmountDisplay = $isSua ? 40000.00 : floatval($payment->billing_betterment_charges ?? 0);
                    @endphp
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                      <span
                        class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-gray-100 text-gray-800">{{ $unitTypeLabel }}</span>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 font-mono">
                      <div class="flex flex-col">
                        <span class="text-green-600 font-semibold">N {{ number_format(floatval($payment->application_fee ?? 0), 2) }}</span>
                        @if(!empty($payment->application_fee_receipt_number))
                          <span class="inline-block mt-1 px-1.5 py-0.5 bg-blue-50 text-blue-700 text-xs font-bold rounded border border-blue-200 uppercase w-fit">Rec: {{ $payment->application_fee_receipt_number }}</span>
                        @else
                          <button onclick="enterSpecificReceipt('{{ $payment->Sectional_Title_File_No }}', 'application_fee', '', '{{ number_format(floatval($payment->application_fee ?? 0), 2) }}')" class="text-[10px] text-blue-600 hover:underline font-normal">+ Add Receipt</button>
                        @endif
                      </div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 font-mono">
                      <div class="flex flex-col">
                        <span class="text-green-600 font-semibold">N {{ number_format(floatval($payment->processing_fee ?? 0), 2) }}</span>
                        @if(!empty($payment->processing_fee_receipt_number))
                          <span class="inline-block mt-1 px-1.5 py-0.5 bg-indigo-50 text-indigo-700 text-xs font-bold rounded border border-indigo-200 uppercase w-fit">Rec: {{ $payment->processing_fee_receipt_number }}</span>
                        @else
                          <button onclick="enterSpecificReceipt('{{ $payment->Sectional_Title_File_No }}', 'processing_fee', '', '{{ number_format(floatval($payment->processing_fee ?? 0), 2) }}')" class="text-[10px] text-blue-600 hover:underline font-normal">+ Add Receipt</button>
                        @endif
                      </div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 font-mono">
                      <div class="flex flex-col">
                        <span class="text-green-600 font-semibold">N {{ number_format(floatval($payment->site_plan_fee ?? 0), 2) }}</span>
                        @if(!empty($payment->survey_fee_receipt_number))
                          <span class="inline-block mt-1 px-1.5 py-0.5 bg-teal-50 text-teal-700 text-xs font-bold rounded border border-teal-200 uppercase w-fit">Rec: {{ $payment->survey_fee_receipt_number }}</span>
                        @else
                          <button onclick="enterSpecificReceipt('{{ $payment->Sectional_Title_File_No }}', 'site_plan_fee', '', '{{ number_format(floatval($payment->site_plan_fee ?? 0), 2) }}')" class="text-[10px] text-blue-600 hover:underline font-normal">+ Add Receipt</button>
                        @endif
                      </div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 font-mono">
                      <span class="text-blue-600 font-semibold">N
                        {{ number_format(floatval($payment->recertification_fee ?? 0), 2) }}</span>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 font-mono">
                      <span class="text-blue-600 font-semibold">N
                        {{ number_format(floatval($payment->assignment_fee ?? 0), 2) }}</span>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 font-mono">
                      <div class="flex flex-col">
                        <span class="text-green-600 font-semibold">N {{ number_format($bettermentAmountDisplay, 2) }}</span>
                        @if(!empty($payment->Betterment_receipt))
                          <span class="inline-block mt-1 px-1.5 py-0.5 bg-green-50 text-green-700 text-xs font-bold rounded border border-green-200 uppercase w-fit">Rec: {{ $payment->Betterment_receipt }}</span>
                        @endif
                      </div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 font-mono">
                      <div class="flex flex-col">
                        <span class="text-orange-600 font-semibold">N {{ number_format(floatval($payment->bill_balance ?? 0), 2) }}</span>
                        @if(!empty($payment->Bill_Balance_receipt))
                          <span class="inline-block mt-1 px-1.5 py-0.5 bg-amber-50 text-amber-700 text-xs font-bold rounded border border-amber-200 uppercase w-fit">Rec: {{ $payment->Bill_Balance_receipt }}</span>
                        @endif
                      </div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 font-mono">
                      <span class="text-indigo-600 font-semibold">N
                        {{ number_format(floatval($payment->dev_charges ?? 0), 2) }}</span>
                    </td>

                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 font-mono">
                      <span class="text-red-600 font-semibold">N
                        {{ number_format(floatval($payment->Penalty_Fees ?? 0), 2) }}</span>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                      <div class="flex items-center">
                        <i data-lucide="calendar" class="w-4 h-4 text-gray-400 mr-2"></i>
                        {{ \Carbon\Carbon::parse($payment->created_at)->format('M d, Y') }}
                      </div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                      <span
                        class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $statusClass }}">
                        @if(strtolower($status) === 'complete' || strtolower($status) === 'paid')
                          <i data-lucide="check-circle" class="w-3 h-3 mr-1"></i>
                        @elseif(strtolower($status) === 'incomplete' || strtolower($status) === 'pending')
                          <i data-lucide="clock" class="w-3 h-3 mr-1"></i>
                        @else
                          <i data-lucide="alert-circle" class="w-3 h-3 mr-1"></i>
                        @endif
                        {{ $status }}
                      </span>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm font-bold text-gray-900">
                      <div class="flex items-center">
                        <span class="text-lg text-purple-600">N {{ number_format($total, 2) }}</span>
                      </div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                      <div class="relative inline-block text-left" x-data="{ open: false }">
                        <button type="button" @click="open = !open"
                          class="inline-flex items-center px-3 py-2 border border-gray-300 shadow-sm text-sm leading-4 font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                          <i data-lucide="more-horizontal" class="w-4 h-4"></i>
                        </button>

                        <div x-show="open" @click.away="open = false" x-transition
                          class="origin-top-right absolute right-0 mt-2 w-56 rounded-md shadow-lg bg-white ring-1 ring-black ring-opacity-5 focus:within:outline-none z-10">
                          <div class="py-1" role="menu">
                            <button type="button" @click="viewInitialBillReceipt('{{ $payment->Sectional_Title_File_No }}')"
                              class="group flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 hover:text-gray-900 w-full text-left">
                              <i data-lucide="file-text" class="mr-3 h-4 w-4 text-gray-400 group-hover:text-gray-500"></i>
                              View Initial Bill Receipt No
                            </button>
                            <button type="button" data-payment='@json($unitPaymentDetails)'
                              @click="viewPaymentDetails(JSON.parse($el.dataset.payment))"
                              class="group flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 hover:text-gray-900 w-full text-left">
                              <i data-lucide="eye" class="mr-3 h-4 w-4 text-gray-400 group-hover:text-gray-500"></i>
                              View Payment
                            </button>
                            @if($isSua)
                              <button type="button"
                                @click="viewBettermentBillReference('{{ $payment->Sectional_Title_File_No }}')"
                                class="group flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 hover:text-gray-900 w-full text-left">
                                <i data-lucide="hash" class="mr-3 h-4 w-4 text-gray-400 group-hover:text-gray-500"></i>
                                View Betterment Bill Reference
                              </button>
                              <button type="button"
                                @click="enterBettermentBillReceipt('{{ $payment->Sectional_Title_File_No }}', '{{ number_format($bettermentAmountDisplay, 2) }}')"
                                class="group flex items-center px-4 py-2 text-sm w-full text-left {{ !empty($payment->Betterment_receipt) ? 'text-gray-400 cursor-not-allowed bg-gray-50' : 'text-gray-700 hover:bg-gray-100 hover:text-gray-900' }}"
                                {{ !empty($payment->Betterment_receipt) ? 'disabled' : '' }}>
                                <i data-lucide="plus-circle"
                                  class="mr-3 h-4 w-4 {{ !empty($payment->Betterment_receipt) ? 'text-gray-300' : 'text-gray-400 group-hover:text-gray-500' }}"></i>
                                {{ !empty($payment->Betterment_receipt) ? 'Betterment Receipt Entered' : 'Enter Betterment Receipt' }}
                              </button>
                            @endif
                            <button type="button"
                              @click="viewBillBalanceReference('{{ $payment->Sectional_Title_File_No }}')"
                              class="group flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 hover:text-gray-900 w-full text-left">
                              <i data-lucide="hash" class="mr-3 h-4 w-4 text-gray-400 group-hover:text-gray-500"></i>
                              View Bill Balance Receipt
                            </button>
                            @php
                              // Check if bill balance receipt already exists
                              $hasBillBalanceReceipt = !empty($payment->Bill_Balance_receipt);
                            @endphp
                            <button type="button"
                              @click="enterBillBalanceReceipt('{{ $payment->Sectional_Title_File_No }}', '{{ number_format(floatval($payment->bill_balance ?? 0), 2) }}')"
                              class="group flex items-center px-4 py-2 text-sm w-full text-left {{ $hasBillBalanceReceipt ? 'text-gray-400 cursor-not-allowed bg-gray-50' : 'text-gray-700 hover:bg-gray-100 hover:text-gray-900' }}"
                              {{ $hasBillBalanceReceipt ? 'disabled' : '' }}>
                              <i data-lucide="plus-circle"
                                class="mr-3 h-4 w-4 {{ $hasBillBalanceReceipt ? 'text-gray-300' : 'text-gray-400 group-hover:text-gray-500' }}"></i>
                              {{ $hasBillBalanceReceipt ? 'Bill Balance Receipt Entered' : 'Enter Bill Balance Receipt' }}
                            </button>
                          </div>
                        </div>
                      </div>
                    </td>
                  </tr>
                @empty
                  <tr>
                    <td colspan="14" class="px-6 py-8 text-center text-gray-500">
                      <div class="flex flex-col items-center">
                        <i data-lucide="inbox" class="w-12 h-12 text-gray-300 mb-2"></i>
                        <p class="text-sm">No unit application payment records found</p>
                      </div>
                    </td>
                  </tr>
                @endforelse
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>

    <!-- Page Footer -->
    @include($footerPartial ?? 'admin.footer')
  </div>

  <!-- Print Area - Content will be injected here dynamically -->
  <div id="print-area" class="printable" style="display:none;">
  </div>

  <script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
  <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
  <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
  <script>
    // Initialize when DOM is loaded
    document.addEventListener('DOMContentLoaded', function () {
      // Initialize Lucide icons
      if (typeof lucide !== 'undefined') {
        lucide.createIcons();
      }

      // Initialize charts if we're on the report page
      if (window.location.search.includes('url=report')) {
        initializeCharts();
      }

      // Tab switching functionality
      const primaryTab = document.getElementById('primary-tab');
      const unitTab = document.getElementById('unit-tab');
      const primaryContent = document.getElementById('primary-content');
      const unitContent = document.getElementById('unit-content');

      // Function to switch to primary tab
      function showPrimaryTab() {
        // Update tab styles
        primaryTab.classList.remove('tab-inactive');
        primaryTab.classList.add('tab-active');
        unitTab.classList.remove('tab-active');
        unitTab.classList.add('tab-inactive');

        // Show/hide content
        primaryContent.classList.remove('hidden');
        unitContent.classList.add('hidden');
      }

      // Function to switch to unit tab
      function showUnitTab() {
        // Update tab styles
        unitTab.classList.remove('tab-inactive');
        unitTab.classList.add('tab-active');
        primaryTab.classList.remove('tab-active');
        primaryTab.classList.add('tab-inactive');

        // Show/hide content
        unitContent.classList.remove('hidden');
        primaryContent.classList.add('hidden');
      }

      // Add event listeners
      primaryTab.addEventListener('click', showPrimaryTab);
      unitTab.addEventListener('click', showUnitTab);

      // Set default tab (primary)
      showPrimaryTab();

      // Filter functionality
      document.getElementById('primary-filter').addEventListener('change', function () {
        filterTable('primary', this.value);
      });

      document.getElementById('unit-filter').addEventListener('change', function () {
        filterTable('unit', this.value);
      });

      // Print functionality
      document.getElementById('print-primary-btn').addEventListener('click', function () {
        printTable('primary');
      });

      document.getElementById('print-unit-btn').addEventListener('click', function () {
        printTable('unit');
      });

      // Print charts functionality
      const printChartsBtn = document.getElementById('print-charts-btn');
      if (printChartsBtn) {
        printChartsBtn.addEventListener('click', function () {
          printCharts();
        });
      }
    });

    // Filter table function
    function filterTable(type, status) {
      const tableId = type === 'primary' ? 'primary-content' : 'unit-content';
      const table = document.querySelector(`#${tableId} table tbody`);
      const rows = table.querySelectorAll('tr');

      rows.forEach(row => {
        if (status === 'all') {
          row.style.display = '';
        } else {
          const statusCell = row.querySelector('.inline-flex');
          if (statusCell) {
            const statusText = statusCell.textContent.toLowerCase().trim();
            const shouldShow =
              (status === 'paid' && (statusText.includes('complete') || statusText.includes('paid'))) ||
              (status === 'pending' && (statusText.includes('incomplete') || statusText.includes('pending'))) ||
              (status === 'overdue' && statusText.includes('overdue'));

            row.style.display = shouldShow ? '' : 'none';
          }
        }
      });
    }

    // Print table function
    function printTable(type) {
      const contentId = type === 'primary' ? 'primary-content' : 'unit-content';
      const content = document.getElementById(contentId);
      const printWindow = window.open('', '_blank');

      printWindow.document.write(`
                <!DOCTYPE html>
                <html>
                <head>
                  <title>${type === 'primary' ? 'Primary' : 'Unit'} Application Payments</title>
                  <style>
                    body { font-family: Arial, sans-serif; margin: 20px; }
                    table { width: 100%; border-collapse: collapse; margin-top: 20px; }
                    th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
                    th { background-color: #f2f2f2; font-weight: bold; }
                    .print-header { text-align: center; margin-bottom: 20px; }
                    .no-print { display: none; }
                  </style>
                </head>
                <body>
                  <div class="print-header">
                    <h1>${type === 'primary' ? 'Primary' : 'Unit'} Application Payments Report</h1>
                    <p>Generated on: ${new Date().toLocaleDateString()}</p>
                  </div>
                  ${content.innerHTML}
                </body>
                </html>
              `);

      printWindow.document.close();
      printWindow.print();
    }

    // Print charts function
    function printCharts() {
      const chartsContainer = document.querySelector('.grid.grid-cols-1.md\\:grid-cols-3.gap-6.mb-6');
      if (!chartsContainer) {
        alert('Charts not found. Please ensure the charts are loaded.');
        return;
      }

      const printWindow = window.open('', '_blank');

      // Get chart canvases and convert to images
      const statusChart = document.getElementById('statusChart');
      const trendsChart = document.getElementById('trendsChart');
      const comparisonChart = document.getElementById('comparisonChart');

      const statusImage = statusChart ? statusChart.toDataURL('image/png') : '';
      const trendsImage = trendsChart ? trendsChart.toDataURL('image/png') : '';
      const comparisonImage = comparisonChart ? comparisonChart.toDataURL('image/png') : '';

      printWindow.document.write(`
                <!DOCTYPE html>
                <html>
                <head>
                  <title>Payment Analytics Report</title>
                  <style>
                    body { 
                      font-family: Arial, sans-serif; 
                      margin: 20px; 
                      background: white;
                    }
                    .print-header { 
                      text-align: center; 
                      margin-bottom: 30px; 
                      border-bottom: 2px solid #333;
                      padding-bottom: 20px;
                    }
                    .charts-grid { 
                      display: grid; 
                      grid-template-columns: 1fr; 
                      gap: 30px; 
                      margin-top: 30px;
                    }
                    .chart-container { 
                      text-align: center; 
                      page-break-inside: avoid;
                      margin-bottom: 40px;
                    }
                    .chart-title { 
                      font-size: 18px; 
                      font-weight: bold; 
                      margin-bottom: 15px;
                      color: #333;
                    }
                    .chart-image { 
                      max-width: 100%; 
                      height: auto;
                      border: 1px solid #ddd;
                      border-radius: 8px;
                      box-shadow: 0 2px 4px rgba(0,0,0,0.1);
                    }
                    .report-info {
                      display: grid;
                      grid-template-columns: 1fr 1fr;
                      gap: 20px;
                      margin-bottom: 30px;
                      font-size: 14px;
                    }
                    .info-item {
                      display: flex;
                      justify-content: space-between;
                      padding: 8px;
                      background: #f8f9fa;
                      border-radius: 4px;
                    }
                    @media print {
                      body { margin: 0; }
                      .chart-container { page-break-inside: avoid; }
                    }
                  </style>
                </head> 
                <body>
                  <div class="print-header">
                    <h1>Payment Analytics Report</h1>
                    <p>KLAES - Kano State Land Administration and Estate System</p>
                    <p style="font-size: 14px; color: #666;">Generated on: ${new Date().toLocaleDateString()} at ${new Date().toLocaleTimeString()}</p>
                  </div>

                  <div class="report-info">
                    <div class="info-item">
                      <span><strong>Total Primary Applications:</strong></span>
                      <span>${@json(count($primaryPayments ?? []))}</span>
                    </div>
                    <div class="info-item">
                      <span><strong>Total Unit Applications:</strong></span>
                      <span>${@json(count($unitPayments ?? []))}</span>
                    </div>
                  </div>

                  <div class="charts-grid">
                    ${statusImage ? `
                      <div class="chart-container">
                        <div class="chart-title">Payment Status Distribution</div>
                        <img src="${statusImage}" alt="Payment Status Distribution Chart" class="chart-image">
                      </div>
                    ` : ''}

                    ${trendsImage ? `
                      <div class="chart-container">
                        <div class="chart-title">Payment Trends (Last 6 Months)</div>
                        <img src="${trendsImage}" alt="Payment Trends Chart" class="chart-image">
                      </div>
                    ` : ''}

                    ${comparisonImage ? `
                      <div class="chart-container">
                        <div class="chart-title">Application Type Comparison</div>
                        <img src="${comparisonImage}" alt="Application Type Comparison Chart" class="chart-image">
                      </div>
                    ` : ''}
                  </div>

                  <div style="margin-top: 40px; padding-top: 20px; border-top: 1px solid #ccc; font-size: 12px; color: #666; text-align: center;">
                    <p>This report was automatically generated by KLAES Payment Management System</p>
                  </div>
                </body>
                </html>
              `);

      printWindow.document.close();

      // Wait for images to load before printing
      setTimeout(() => {
        printWindow.print();
      }, 1000);
    }

    // Global Alpine.js functions for action menu
    window.viewPaymentDetails = function (payment) {
      const formatCurrency = (value) => {
        const amount = Number(value ?? 0);
        return `N ${amount.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
      };

      const fallbackChip = 'bg-gray-100 text-gray-700 border border-gray-200';
      const groupStyles = {
        'initial bill': { bg: 'bg-blue-50', border: 'border-blue-200', text: 'text-blue-900', sub: 'text-blue-600' },
        'betterment bill': { bg: 'bg-emerald-50', border: 'border-emerald-200', text: 'text-emerald-900', sub: 'text-emerald-600' },
        'bill balance': { bg: 'bg-purple-50', border: 'border-purple-200', text: 'text-purple-900', sub: 'text-purple-600' },
        'fees': { bg: 'bg-gray-50', border: 'border-gray-200', text: 'text-gray-900', sub: 'text-gray-500' },
        'default': { bg: 'bg-gray-50', border: 'border-gray-200', text: 'text-gray-900', sub: 'text-gray-500' }
      };

      const feeGroups = (payment.groups && payment.groups.length)
        ? payment.groups
        : [{
          title: 'Fees',
          fees: payment.fees || []
        }];

      const feeRows = feeGroups.map(group => {
        const styleKey = (group.title || 'default').toLowerCase();
        const style = groupStyles[styleKey] || groupStyles.default;
        const rows = (group.fees || []).length
          ? group.fees.map(fee => `
                        <div class="flex items-center justify-between px-4 py-4 text-sm border-b last:border-b-0 hover:bg-gray-50 transition-colors">
                          <div>
                            <p class="text-sm font-semibold text-gray-900">${fee.label}</p>
                            <div class="text-xs text-gray-500 space-x-3 mt-1">
                              <span>Date: ${fee.payment_date || 'N/A'}</span>
                              <span>Receipt: ${fee.receipt ? `<span class="px-2 py-0.5 bg-blue-50 text-blue-700 text-[10px] font-bold rounded border border-blue-200 uppercase inline-block ml-1">${fee.receipt}</span>` : '<span class="text-gray-400">N/A</span>'}</span>
                            </div>
                          </div>
                          <div class="text-right">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold mb-2 ${fee.status_class || fallbackChip}">
                              ${fee.status || 'Not Paid'}
                            </span>
                            <p class="text-base font-semibold text-gray-900">${formatCurrency(fee.amount)}</p>
                          </div>
                        </div>
                      `).join('')
          : `<div class="px-4 py-3 text-sm text-gray-500">No fee details available.</div>`;

        return `
                    <div class="rounded-2xl border ${style.border} shadow-sm overflow-hidden">
                      <div class="px-4 py-3 border-b ${style.border} ${style.bg}">
                        <p class="text-sm font-semibold ${style.text}">${group.title}</p>
                        <p class="text-xs ${style.sub}">Detailed charges and payment references</p>
                      </div>
                      ${rows}
                    </div>
                  `;
      }).join('');

      const statusClass = payment.status_class ? `${payment.status_class} border` : fallbackChip;
      const ownerInitials = (payment.owner || 'NA').slice(0, 2).toUpperCase();
      const content = `
                  <div class="space-y-5">
                    <div class="bg-gradient-to-r from-indigo-600 to-purple-600 text-white rounded-2xl p-5 shadow-lg">
                      <div class="flex items-center justify-between">
                        <div>
                          <p class="text-xs uppercase tracking-wide text-white/80 mb-1">${payment.type || 'Application'}</p>
                          <p class="text-2xl font-semibold">${payment.file_no || 'N/A'}</p>
                          ${payment.np_file_no ? `<p class="text-sm text-white/80 mt-1">NP File No: ${payment.np_file_no}</p>` : ''}
                          <p class="text-sm text-white/90 mt-4">Owner: <span class="font-semibold text-white">${payment.owner || 'N/A'}</span></p>
                        </div>
                        <div class="flex items-center gap-3">
                          <div class="w-12 h-12 rounded-full bg-white/20 flex items-center justify-center text-lg font-semibold">
                            ${ownerInitials}
                          </div>
                          <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold border border-white/40 bg-white/20 text-white">
                            ${payment.status || 'Unknown'}
                          </span>
                        </div>
                      </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                      <div class="rounded-xl border border-gray-200 p-4">
                        <p class="text-xs text-gray-500 uppercase">Payment Date</p>
                        <p class="text-base font-semibold text-gray-900 mt-1">${payment.payment_date || 'N/A'}</p>
                      </div>
                      <div class="rounded-xl border border-gray-200 p-4">
                        <p class="text-xs text-gray-500 uppercase">Receipt Number</p>
                        <p class="mt-1">${payment.receipt_number ? `<span class="px-3 py-1 bg-blue-50 text-blue-700 text-lg font-bold rounded-lg border border-blue-200 uppercase shadow-sm inline-block">${payment.receipt_number}</span>` : '<span class="text-gray-400 font-medium italic">Not provided</span>'}</p>
                      </div>
                      <div class="rounded-xl border border-gray-200 p-4">
                        <p class="text-xs text-gray-500 uppercase">Total Amount</p>
                        <p class="text-2xl font-bold text-indigo-600 mt-1">${formatCurrency(payment.amount_due)}</p>
                      </div>
                      <div class="rounded-xl border border-gray-200 p-4">
                        <p class="text-xs text-gray-500 uppercase">Overall Status</p>
                        <div class="mt-2">
                          <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold ${statusClass}">
                            ${payment.status || 'Unknown'}
                          </span>
                          <p class="text-sm text-gray-600 mt-2">${payment.is_paid ? 'Paid in full' : 'Pending / Partial'}</p>
                        </div>
                      </div>
                    </div>

                    <div class="rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
                      <div class="px-4 py-3 border-b border-gray-200 bg-gray-50 flex items-center justify-between">
                        <div>
                          <p class="text-sm font-semibold text-gray-900">Fee Payment Details</p>
                          <p class="text-xs text-gray-500">Grouped breakdown with receipts and status</p>
                        </div>
                      </div>
                    </div>

                    <div class="space-y-4">
                      ${feeRows}
                    </div>

                    ${payment.notes ? `
                      <div class="bg-amber-50 border border-amber-100 rounded-2xl p-4 shadow-inner">
                        <p class="text-xs text-amber-700 uppercase tracking-wide mb-1">Notes</p>
                        <p class="text-sm text-amber-900 leading-relaxed">${payment.notes}</p>
                      </div>
                    ` : ''}
                  </div>
                `;

      showModal('Payment Report', content);
    };

    window.viewInitialBillReceipt = function (fileNo) {
      // Show loading
      showModal('Loading...', '<div class="text-center"><div class="spinner-border" role="status"><span class="sr-only">Loading...</span></div></div>');

      // Fetch initial bill receipt data from mother_applications table
      fetch(`/programmes/payments/initial-bill-receipt/${fileNo}`)
        .then(response => response.json())
        .then(data => {
          if (data.success) {
            const content = `
                        <div class="space-y-4">
                          <div class="bg-blue-50 p-4 rounded-lg">
                            <h4 class="font-semibold text-blue-900">Initial Bill Receipt Details</h4>
                            <p class="text-sm text-blue-700 mt-1">File No: ${fileNo}</p>
                          </div>
                          <div class="grid grid-cols-2 gap-4">
                            <div>
                              <label class="block text-sm font-medium text-gray-700">Receipt Number</label>
                              <p class="mt-1">${data.receipt_number ? `<span class="px-2 py-1 bg-blue-50 text-blue-700 text-sm font-bold rounded border border-blue-200 uppercase inline-block">${data.receipt_number}</span>` : '<span class="text-gray-400 font-medium italic">N/A</span>'}</p>
                            </div>
                            <div>
                              <label class="block text-sm font-medium text-gray-700">Amount</label>
                              <p class="mt-1 text-sm text-gray-900">N ${data.amount ? parseFloat(data.amount).toLocaleString() : 'N/A'}</p>
                            </div>
                            <div>
                              <label class="block text-sm font-medium text-gray-700">Payment Date</label>
                              <p class="mt-1 text-sm text-gray-900">${data.payment_date || 'N/A'}</p>
                            </div>
                            <div>
                              <label class="block text-sm font-medium text-gray-700">Status</label>
                              <p class="mt-1 text-sm text-gray-900">${data.status || 'N/A'}</p>
                            </div>
                          </div>
                          ${data.notes ? `
                            <div>
                              <label class="block text-sm font-medium text-gray-700">Notes</label>
                              <p class="mt-1 text-sm text-gray-900">${data.notes}</p>
                            </div>
                          ` : ''}
                        </div>
                      `;
            showModal('Initial Bill Receipt', content);
          } else {
            showModal('Error', `<p class="text-red-600">${data.message || 'Failed to load initial bill receipt'}</p>`);
          }
        })
        .catch(error => {
          console.error('Error:', error);
          showModal('Error', '<p class="text-red-600">An error occurred while loading the initial bill receipt</p>');
        });
    };

    // Updated function to handle both primary and unit applications
    window.viewBettermentBillReference = function (fileNo) {
      // Show loading
      showModal('Loading...', '<div class="text-center"><div class="spinner-border" role="status"><span class="sr-only">Loading...</span></div></div>');

      // Fetch betterment bill reference data
      fetch(`/programmes/payments/betterment-bill-reference/${fileNo}`)
        .then(response => response.json())
        .then(data => {
          if (data.success) {
            const content = `
                        <div class="space-y-4">
                          <div class="bg-green-50 p-4 rounded-lg">
                            <h4 class="font-semibold text-green-900">Betterment Bill Reference Details</h4>
                            <p class="text-sm text-green-700 mt-1">File No: ${fileNo}</p>
                          </div>
                          <div class="grid grid-cols-1 gap-4">
                            <div>
                              <label class="block text-sm font-medium text-gray-700">Reference ID</label>
                              <p class="mt-1 text-lg font-mono text-gray-900 bg-gray-100 p-2 rounded">${data.reference_id || 'N/A'}</p>
                            </div>
                            <div>
                              <label class="block text-sm font-medium text-gray-700">Generated Date</label>
                              <p class="mt-1 text-sm text-gray-900">${data.generated_date || 'N/A'}</p>
                            </div>
                            <div>
                              <label class="block text-sm font-medium text-gray-700">Amount</label>
                              <p class="mt-1 text-sm text-gray-900">N ${data.amount ? parseFloat(data.amount).toLocaleString() : 'N/A'}</p>
                            </div>
                            <div>
                              <label class="block text-sm font-medium text-gray-700">Status</label>
                              <p class="mt-1 text-sm text-gray-900">${data.status || 'N/A'}</p>
                            </div>
                          </div>
                        </div>
                      `;
            showModal('Betterment Bill Reference ID', content);
          } else {
            showModal('Error', `<p class="text-red-600">${data.message || 'Failed to load betterment bill reference'}</p>`);
          }
        })
        .catch(error => {
          console.error('Error:', error);
          showModal('Error', '<p class="text-red-600">An error occurred while loading the betterment bill reference</p>');
        });
    };

    // New function for bill balance reference (unit applications)
    window.viewBillBalanceReference = function (fileNo) {
      // Show loading
      showModal('Loading...', '<div class="text-center"><div class="spinner-border" role="status"><span class="sr-only">Loading...</span></div></div>');

      // Fetch bill balance reference data
      fetch(`/programmes/payments/bill-balance-reference/${fileNo}`)
        .then(response => response.json())
        .then(data => {
          if (data.success) {
            const content = `
                        <div class="space-y-4">
                          <div class="bg-orange-50 p-4 rounded-lg">
                            <h4 class="font-semibold text-orange-900">Bill Balance Reference Details</h4>
                            <p class="text-sm text-orange-700 mt-1">File No: ${fileNo}</p>
                          </div>
                          <div class="grid grid-cols-1 gap-4">
                            <div>
                              <label class="block text-sm font-medium text-gray-700">Reference ID</label>
                              <p class="mt-1 text-lg font-mono text-gray-900 bg-gray-100 p-2 rounded">${data.reference_id || 'N/A'}</p>
                            </div>
                            <div>
                              <label class="block text-sm font-medium text-gray-700">Generated Date</label>
                              <p class="mt-1 text-sm text-gray-900">${data.generated_date || 'N/A'}</p>
                            </div>
                            <div>
                              <label class="block text-sm font-medium text-gray-700">Amount</label>
                              <p class="mt-1 text-sm text-gray-900">N ${data.amount ? parseFloat(data.amount).toLocaleString() : 'N/A'}</p>
                            </div>
                            <div>
                              <label class="block text-sm font-medium text-gray-700">Status</label>
                              <p class="mt-1 text-sm text-gray-900">${data.status || 'N/A'}</p>
                            </div>
                          </div>
                        </div>
                      `;
            showModal('Bill Balance Reference ID', content);
          } else {
            showModal('Error', `<p class="text-red-600">${data.message || 'Failed to load bill balance reference'}</p>`);
          }
        })
        .catch(error => {
          console.error('Error:', error);
          showModal('Error', '<p class="text-red-600">An error occurred while loading the bill balance reference</p>');
        });
    };

    // Updated function for betterment bill receipt
    window.enterBettermentBillReceipt = function (fileNo, amount = '') {
      const content = `
                  <form id="betterment-bill-form" class="space-y-4">
                    <div class="bg-yellow-50 p-4 rounded-lg flex justify-between items-start">
                      <div>
                        <h4 class="font-semibold text-yellow-900">Enter Betterment Bill Receipt</h4>
                        <p class="text-sm text-yellow-700 mt-1">File No: ${fileNo}</p>
                      </div>
                      ${amount ? `
                      <div class="text-right">
                        <p class="text-[10px] uppercase font-semibold text-yellow-600 tracking-wider">Amount Due</p>
                        <p class="text-lg font-bold text-yellow-800">N ${amount}</p>
                      </div>
                      ` : ''}
                    </div>
                    <div>
                      <label for="receipt-number" class="block text-sm font-medium text-gray-700">Receipt Number *</label>
                      <input type="text" id="receipt-number" name="receipt_number" required 
                             class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    </div>
                    <div>
                      <label for="receipt-date" class="block text-sm font-medium text-gray-700">Receipt Date *</label>
                      <input type="date" id="receipt-date" name="receipt_date" required 
                             class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    </div>
                    <div>
                      <label for="receipt-notes" class="block text-sm font-medium text-gray-700">Notes</label>
                      <textarea id="receipt-notes" name="notes" rows="3" 
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"></textarea>
                    </div>
                    <div class="flex justify-end space-x-3 pt-4">
                      <button type="button" onclick="closeModal()" 
                              class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 border border-gray-300 rounded-md hover:bg-gray-200">
                        Cancel
                      </button>
                      <button type="submit" 
                              class="px-4 py-2 text-sm font-medium text-white bg-indigo-600 border border-transparent rounded-md hover:bg-indigo-700">
                        Save Receipt
                      </button>
                    </div>
                  </form>
                `;

      showModal('Enter Betterment Bill Receipt', content);

      // Handle form submission
      document.getElementById('betterment-bill-form').addEventListener('submit', function (e) {
        e.preventDefault();

        const formData = new FormData(this);
        formData.append('file_no', fileNo);

        // Show loading
        const submitBtn = this.querySelector('button[type="submit"]');
        const originalText = submitBtn.textContent;
        submitBtn.textContent = 'Saving...';
        submitBtn.disabled = true;

        fetch('/programmes/payments/save-betterment-bill-receipt', {
          method: 'POST',
          body: formData,
          headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
          }
        })
          .then(response => response.json())
          .then(data => {
            if (data.success) {
              closeModal();
              showSuccessMessage('Betterment bill receipt saved successfully!');
              // Refresh the page to update the button state
              setTimeout(() => {
                window.location.reload();
              }, 1500);
            } else {
              showModal('Error', `<p class="text-red-600">${data.message || 'Failed to save receipt'}</p>`);
            }
          })
          .catch(error => {
            console.error('Error:', error);
            showModal('Error', '<p class="text-red-600">An error occurred while saving the receipt</p>');
          })
          .finally(() => {
            submitBtn.textContent = originalText;
            submitBtn.disabled = false;
          });
      });
    };

    // New function for bill balance receipt (unit applications)
    window.enterBillBalanceReceipt = function (fileNo, amount = '') {
      const content = `
                  <form id="bill-balance-form" class="space-y-4">
                    <div class="bg-blue-50 p-4 rounded-lg flex justify-between items-start">
                      <div>
                        <h4 class="font-semibold text-blue-900">Enter Bill Balance Receipt</h4>
                        <p class="text-sm text-blue-700 mt-1">File No: ${fileNo}</p>
                      </div>
                      ${amount ? `
                      <div class="text-right">
                        <p class="text-[10px] uppercase font-semibold text-blue-600 tracking-wider">Amount Due</p>
                        <p class="text-lg font-bold text-blue-800">N ${amount}</p>
                      </div>
                      ` : ''}
                    </div>
                    <div>
                      <label for="receipt-number" class="block text-sm font-medium text-gray-700">Receipt Number *</label>
                      <input type="text" id="receipt-number" name="receipt_number" required 
                             class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    </div>
                    <div>
                      <label for="receipt-date" class="block text-sm font-medium text-gray-700">Receipt Date *</label>
                      <input type="date" id="receipt-date" name="receipt_date" required 
                             class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    </div>
                    <div>
                      <label for="receipt-notes" class="block text-sm font-medium text-gray-700">Notes</label>
                      <textarea id="receipt-notes" name="notes" rows="3" 
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"></textarea>
                    </div>
                    <div class="flex justify-end space-x-3 pt-4">
                      <button type="button" onclick="closeModal()" 
                              class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 border border-gray-300 rounded-md hover:bg-gray-200">
                        Cancel
                      </button>
                      <button type="submit" 
                              class="px-4 py-2 text-sm font-medium text-white bg-indigo-600 border border-transparent rounded-md hover:bg-indigo-700">
                        Save Receipt
                      </button>
                    </div>
                  </form>
                `;

      showModal('Enter Bill Balance Receipt', content);

      // Handle form submission
      document.getElementById('bill-balance-form').addEventListener('submit', function (e) {
        e.preventDefault();

        const formData = new FormData(this);
        formData.append('file_no', fileNo);

        // Show loading
        const submitBtn = this.querySelector('button[type="submit"]');
        const originalText = submitBtn.textContent;
        submitBtn.textContent = 'Saving...';
        submitBtn.disabled = true;

        fetch('/programmes/payments/save-bill-balance-receipt', {
          method: 'POST',
          body: formData,
          headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
          }
        })
          .then(response => response.json())
          .then(data => {
            if (data.success) {
              closeModal();
              showSuccessMessage('Bill balance receipt saved successfully!');
              // Refresh the page to update the button state
              setTimeout(() => {
                window.location.reload();
              }, 1500);
            } else {
              showModal('Error', `<p class="text-red-600">${data.message || 'Failed to save receipt'}</p>`);
            }
          })
          .catch(error => {
            console.error('Error:', error);
            showModal('Error', '<p class="text-red-600">An error occurred while saving the receipt</p>');
          })
          .finally(() => {
            submitBtn.textContent = originalText;
            submitBtn.disabled = false;
          });
      });
    };

    /**
     * Enter and save specific fee receipt
     */
    function enterSpecificReceipt(fileNo, feeType, currentReceipt = '', amount = '') {
      const feeLabels = {
        'application_fee': 'Application Fee',
        'processing_fee': 'Processing Fee',
        'site_plan_fee': 'Site Plan/Survey Fee'
      };

      const modalContent = `
        <div class="space-y-4">
          <div class="p-3 bg-blue-50 text-blue-700 rounded-lg text-sm mb-4 flex justify-between items-start">
            <div>
              <p class="font-medium text-blue-900">Update Receipt for: ${feeLabels[feeType]}</p>
              <p class="text-xs mt-0.5">File Number: ${fileNo}</p>
            </div>
            ${amount ? `
            <div class="text-right">
              <p class="text-[10px] uppercase font-semibold text-blue-600 tracking-wider">Amount Due</p>
              <p class="text-lg font-bold text-blue-800">N ${amount}</p>
            </div>
            ` : ''}
          </div>
          
          <form id="specific-receipt-form" onsubmit="event.preventDefault(); window.saveSpecificReceiptAction();">
            <div class="grid grid-cols-1 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Receipt Number</label>
                <input type="text" id="spec_receipt_no" class="modal-input" 
                       value="${currentReceipt}" required placeholder="Enter receipt number">
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Payment Date</label>
                <input type="date" id="spec_receipt_date" class="modal-input" 
                       value="${new Date().toISOString().split('T')[0]}" required>
              </div>
            </div>
            
            <input type="hidden" id="spec_file_no" value="${fileNo}">
            <input type="hidden" id="spec_fee_type" value="${feeType}">
            
            <div class="mt-6 flex justify-end space-x-3">
              <button type="button" onclick="closeModal()" class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50">Cancel</button>
              <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 shadow-md transition">Save Receipt</button>
            </div>
          </form>
        </div>
      `;

      showModal('Update Fee Receipt', modalContent);
    }

    // Attach to window to ensure accessibility from modal
    window.saveSpecificReceiptAction = async function() {
      const fileNo = document.getElementById('spec_file_no').value;
      const feeType = document.getElementById('spec_fee_type').value;
      const receiptNo = document.getElementById('spec_receipt_no').value;
      const receiptDate = document.getElementById('spec_receipt_date').value;

      const data = {
        file_no: fileNo,
        fee_type: feeType,
        receipt_number: receiptNo,
        receipt_date: receiptDate,
        _token: '{{ csrf_token() }}'
      };

      try {
        const response = await fetch('{{ route("programmes.payments.save-specific-receipt") }}', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}'
          },
          body: JSON.stringify(data)
        });

        const result = await response.json();
        if (result.success) {
          showSuccessMessage('Receipt saved successfully');
          closeModal();
          // Reload to show changes
          setTimeout(() => location.reload(), 1000);
        } else {
          alert('Error: ' + result.message);
        }
      } catch (error) {
        console.error('Error saving receipt:', error);
        alert('An error occurred while saving the receipt.');
      }
    };

    // Modal utility functions
    function showModal(title, content) {
      // Remove any existing modal
      const existingModal = document.getElementById('dynamic-modal');
      if (existingModal) {
        existingModal.remove();
      }

      // Create modal HTML
      const modalHtml = `
                <div id="dynamic-modal" class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
                  <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                    <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" aria-hidden="true"></div>
                    <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
                    <div class="inline-block align-bottom bg-white rounded-2xl text-left overflow-hidden shadow-2xl transform transition-all sm:my-8 sm:align-middle sm:max-w-3xl sm:w-full max-h-[90vh]">
                      <div class="flex items-center justify-between px-5 py-4 border-b border-gray-200 sticky top-0 bg-white z-10">
                        <div>
                          <h3 class="text-xl font-semibold text-gray-900" id="modal-title">${title}</h3>
                        </div>
                        <button type="button" class="text-gray-400 hover:text-gray-600 transition" onclick="closeModal()" aria-label="Close modal">
                          <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                          </svg>
                        </button>
                      </div>
                      <div class="bg-white px-5 py-4 overflow-y-auto max-h-[80vh]">
                        ${content}
                      </div>
                    </div>
                  </div>
                </div>
              `;

      // Add modal to page
      document.body.insertAdjacentHTML('beforeend', modalHtml);

      // Add close functionality
      const modal = document.getElementById('dynamic-modal');
      modal.addEventListener('click', function (e) {
        if (e.target === modal || e.target.getAttribute('aria-hidden') === 'true') {
          closeModal();
        }
      });

      // Focus trap and ESC key handler
      document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
          closeModal();
        }
      });
    }

    function closeModal() {
      const modal = document.getElementById('dynamic-modal');
      if (modal) {
        modal.remove();
      }
    }

    function showSuccessMessage(message) {
      // Create success toast
      const toast = document.createElement('div');
      toast.className = 'fixed top-4 right-4 z-50 bg-green-500 text-white px-6 py-3 rounded-lg shadow-lg flex items-center space-x-2 transition-all duration-300';
      toast.innerHTML = `
                <i data-lucide="check-circle" class="w-5 h-5"></i>
                <span>${message}</span>
              `;

      document.body.appendChild(toast);

      // Initialize lucide icon
      if (typeof lucide !== 'undefined') {
        lucide.createIcons();
      }

      // Auto remove after 3 seconds
      setTimeout(() => {
        toast.style.opacity = '0';
        toast.style.transform = 'translateX(100%)';
        setTimeout(() => {
          if (toast.parentNode) {
            toast.parentNode.removeChild(toast);
          }
        }, 300);
      }, 3000);
    }

    // Initialize charts function
    function initializeCharts() {
      try {
        // Check if Chart.js is loaded
        if (typeof Chart === 'undefined') {
          console.error('Chart.js is not loaded');
          return;
        }

        // Prepare data from PHP backend
        const primaryPayments = @json($primaryPayments ?? []);
        const unitPayments = @json($unitPayments ?? []);

        console.log('Initializing charts with data:', {
          primaryCount: primaryPayments.length,
          unitCount: unitPayments.length
        });

        // Payment Status Distribution Chart
        initializeStatusChart(primaryPayments, unitPayments);

        // Payment Trends Chart
        initializeTrendsChart(primaryPayments, unitPayments);

        // Application Type Comparison Chart
        initializeComparisonChart(primaryPayments, unitPayments);

        console.log('Charts initialized successfully');
      } catch (error) {
        console.error('Error initializing charts:', error);
      }
    }

    function initializeStatusChart(primaryPayments, unitPayments) {
      try {
        const allPayments = [...primaryPayments, ...unitPayments];

        // Count payment statuses
        let complete = 0;
        let incomplete = 0;
        let overdue = 0;

        allPayments.forEach(payment => {
          // Calculate if payment is complete based on main bills
          const mainPaid = (
            parseFloat(payment.application_fee || 0) > 0 &&
            parseFloat(payment.processing_fee || 0) > 0 &&
            parseFloat(payment.site_plan_fee || 0) > 0 &&
            parseFloat(payment.Betterment_Charges || 0) > 0
          );

          if (mainPaid) {
            complete++;
          } else {
            // Check if overdue (more than 30 days old)
            const paymentDate = new Date(payment.created_at);
            const daysDiff = (new Date() - paymentDate) / (1000 * 60 * 60 * 24);

            if (daysDiff > 30) {
              overdue++;
            } else {
              incomplete++;
            }
          }
        });

        const ctx = document.getElementById('statusChart');
        if (ctx) {
          new Chart(ctx, {
            type: 'doughnut',
            data: {
              labels: ['Complete', 'Incomplete', 'Overdue'],
              datasets: [{
                data: [complete, incomplete, overdue],
                backgroundColor: [
                  '#10B981', // Green for complete
                  '#F59E0B', // Yellow for incomplete
                  '#EF4444'  // Red for overdue
                ],
                borderWidth: 2,
                borderColor: '#ffffff'
              }]
            },
            options: {
              responsive: true,
              maintainAspectRatio: false,
              plugins: {
                legend: {
                  position: 'bottom',
                  labels: {
                    padding: 20,
                    usePointStyle: true
                  }
                },
                tooltip: {
                  callbacks: {
                    label: function (context) {
                      const total = context.dataset.data.reduce((a, b) => a + b, 0);
                      const percentage = total > 0 ? ((context.raw / total) * 100).toFixed(1) : '0.0';
                      return `${context.label}: ${context.raw} (${percentage}%)`;
                    }
                  }
                }
              }
            }
          });
          console.log('Status chart initialized successfully');
        } else {
          console.error('Status chart canvas not found');
        }
      } catch (error) {
        console.error('Error initializing status chart:', error);
      }
    }

    function initializeTrendsChart(primaryPayments, unitPayments) {
      try {
        const allPayments = [...primaryPayments, ...unitPayments];

        // Group payments by month
        const monthlyData = {};
        const last6Months = [];
        const currentDate = new Date();

        // Generate last 6 months
        for (let i = 5; i >= 0; i--) {
          const date = new Date(currentDate.getFullYear(), currentDate.getMonth() - i, 1);
          const monthKey = date.toLocaleDateString('en-US', { year: 'numeric', month: 'short' });
          last6Months.push(monthKey);
          monthlyData[monthKey] = { complete: 0, incomplete: 0, total: 0 };
        }

        // Process payments
        allPayments.forEach(payment => {
          const paymentDate = new Date(payment.created_at);
          const monthKey = paymentDate.toLocaleDateString('en-US', { year: 'numeric', month: 'short' });

          if (monthlyData[monthKey]) {
            const mainPaid = (
              parseFloat(payment.application_fee || 0) > 0 &&
              parseFloat(payment.processing_fee || 0) > 0 &&
              parseFloat(payment.site_plan_fee || 0) > 0 &&
              parseFloat(payment.Betterment_Charges || 0) > 0
            );

            monthlyData[monthKey].total++;
            if (mainPaid) {
              monthlyData[monthKey].complete++;
            } else {
              monthlyData[monthKey].incomplete++;
            }
          }
        });

        const ctx = document.getElementById('trendsChart');
        if (ctx) {
          new Chart(ctx, {
            type: 'line',
            data: {
              labels: last6Months,
              datasets: [
                {
                  label: 'Complete Payments',
                  data: last6Months.map(month => monthlyData[month].complete),
                  borderColor: '#10B981',
                  backgroundColor: 'rgba(16, 185, 129, 0.1)',
                  fill: true,
                  tension: 0.4
                },
                {
                  label: 'Incomplete Payments',
                  data: last6Months.map(month => monthlyData[month].incomplete),
                  borderColor: '#F59E0B',
                  backgroundColor: 'rgba(245, 158, 11, 0.1)',
                  fill: true,
                  tension: 0.4
                }
              ]
            },
            options: {
              responsive: true,
              maintainAspectRatio: false,
              plugins: {
                legend: {
                  position: 'bottom',
                  labels: {
                    padding: 20,
                    usePointStyle: true
                  }
                }
              },
              scales: {
                y: {
                  beginAtZero: true,
                  ticks: {
                    stepSize: 1
                  }
                }
              }
            }
          });
          console.log('Trends chart initialized successfully');
        } else {
          console.error('Trends chart canvas not found');
        }
      } catch (error) {
        console.error('Error initializing trends chart:', error);
      }
    }

    function initializeComparisonChart(primaryPayments, unitPayments) {
      try {
        // Calculate totals for each application type
        const primaryTotal = primaryPayments.reduce((sum, payment) => {
          return sum + (
            parseFloat(payment.application_fee || 0) +
            parseFloat(payment.processing_fee || 0) +
            parseFloat(payment.site_plan_fee || 0) +
            parseFloat(payment.Betterment_Charges || 0) +
            parseFloat(payment.Penalty_Fees || 0)
          );
        }, 0);

        const unitTotal = unitPayments.reduce((sum, payment) => {
          return sum + (
            parseFloat(payment.application_fee || 0) +
            parseFloat(payment.processing_fee || 0) +
            parseFloat(payment.site_plan_fee || 0) +
            parseFloat(payment.Betterment_Charges || 0) +
            parseFloat(payment.Penalty_Fees || 0)
          );
        }, 0);

        const ctx = document.getElementById('comparisonChart');
        if (ctx) {
          new Chart(ctx, {
            type: 'bar',
            data: {
              labels: ['Primary Applications', 'Unit Applications'],
              datasets: [
                {
                  label: 'Total Count',
                  data: [primaryPayments.length, unitPayments.length],
                  backgroundColor: ['#3B82F6', '#8B5CF6'],
                  borderColor: ['#2563EB', '#7C3AED'],
                  borderWidth: 2,
                  yAxisID: 'y'
                },
                {
                  label: 'Total Amount (N)',
                  data: [primaryTotal, unitTotal],
                  backgroundColor: ['rgba(59, 130, 246, 0.3)', 'rgba(139, 92, 246, 0.3)'],
                  borderColor: ['#2563EB', '#7C3AED'],
                  borderWidth: 2,
                  type: 'line',
                  yAxisID: 'y1'
                }
              ]
            },
            options: {
              responsive: true,
              maintainAspectRatio: false,
              plugins: {
                legend: {
                  position: 'bottom',
                  labels: {
                    padding: 20,
                    usePointStyle: true
                  }
                },
                tooltip: {
                  callbacks: {
                    label: function (context) {
                      if (context.dataset.label === 'Total Amount (N)') {
                        return `${context.dataset.label}: N ${context.raw.toLocaleString()}`;
                      }
                      return `${context.dataset.label}: ${context.raw}`;
                    }
                  }
                }
              },
              scales: {
                y: {
                  type: 'linear',
                  display: true,
                  position: 'left',
                  beginAtZero: true,
                  title: {
                    display: true,
                    text: 'Count'
                  }
                },
                y1: {
                  type: 'linear',
                  display: true,
                  position: 'right',
                  beginAtZero: true,
                  title: {
                    display: true,
                    text: 'Amount (N)'
                  },
                  grid: {
                    drawOnChartArea: false,
                  },
                  ticks: {
                    callback: function (value) {
                      return 'N ' + value.toLocaleString();
                    }
                  }
                }
              }
            }
          });
          console.log('Comparison chart initialized successfully');
        } else {
          console.error('Comparison chart canvas not found');
        }
      } catch (error) {
        console.error('Error initializing comparison chart:', error);
      }
    }
  </script>

  <!-- Add required CSS for tabs -->
  <style>
    .tab-active {
      background-color: white;
      border-bottom: 2px solid #3b82f6;
      color: #3b82f6;
      font-weight: 600;
    }

    .tab-inactive {
      background-color: #f8fafc;
      color: #6b7280;
      border-bottom: 1px solid #e5e7eb;
    }

    .tab-active:hover,
    .tab-inactive:hover {
      background-color: #f1f5f9;
      cursor: pointer;
    }

    /* Chart container styles */
    .chart-container {
      position: relative;
      height: 300px;
      width: 100%;
    }

    /* Ensure charts are responsive */
    .chart-container canvas {
      max-width: 100%;
      height: auto;
    }

    /* Print styles for charts */
    @media print {
      .chart-container {
        height: 400px;
        page-break-inside: avoid;
      }
    }
  </style>

@endsection