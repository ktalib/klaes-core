{{-- Betterment payments of a central Physical Planning source (SLTR, Conversion, Master JSI). --}}
<div class="overflow-x-auto">
  <table class="min-w-full divide-y divide-gray-200">
    <thead class="bg-gray-50">
      <tr>
        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">S/N</th>
        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">File No</th>
        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Ref ID</th>
        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Applicant</th>
        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Land Use</th>
        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Area (m²)</th>
        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Betterment Charges</th>
        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Amount Paid</th>
        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Receipt No</th>
        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
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
          $fileNo = $payment->Sectional_Title_File_No ?? $payment->jsi_file_number ?? '';
          $hasReceipt = !empty($payment->Betterment_receipt);
          $evidenceUrl = \App\Services\PhysicalPlanningBillSources::evidenceUrl($payment->Betterment_evidence_path ?? null);
        @endphp
        <tr class="hover:bg-gray-50 transition-colors" data-payment-row>
          <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-500" data-sn>{{ $loop->iteration }}</td>
          <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
            <div class="flex items-center">
              <i data-lucide="file-text" class="w-4 h-4 text-gray-400 mr-2"></i>
              {{ $fileNo ?: 'N/A' }}
            </div>
          </td>
          <td class="px-6 py-4 whitespace-nowrap text-xs text-blue-700 font-semibold">{{ $payment->ref_id ?? '—' }}</td>
          <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ ucwords(strtolower($payment->owner_name ?? 'N/A')) }}</td>
          <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">{{ $payment->applied_land_use ?? '—' }}</td>
          <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">{{ $area > 0 ? number_format($area, 2) : '—' }}</td>
          <td class="px-6 py-4 whitespace-nowrap text-sm font-semibold text-gray-900">₦{{ number_format($betterment, 2) }}</td>
          <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
            @if(($payment->Betterment_amount_paid ?? null) !== null)
              @include('programmes.partials.pp_betterment_paid', ['billAmount' => $betterment, 'amountPaid' => $payment->Betterment_amount_paid])
            @else
              —
            @endif
          </td>
          <td class="px-6 py-4 whitespace-nowrap text-xs text-gray-600">
            @if($hasReceipt)
              <span class="inline-block px-2 py-0.5 bg-green-50 text-green-700 text-xs font-bold rounded border border-green-200 uppercase w-fit">Rec: {{ $payment->Betterment_receipt }}</span>
            @else
              —
            @endif
          </td>
          <td class="px-6 py-4 whitespace-nowrap text-xs text-gray-600">{{ $paymentDateDisplay }}</td>
          <td class="px-6 py-4 whitespace-nowrap">
            <span class="inline-flex px-2 py-1 text-xs font-medium rounded-full {{ $statusClass }}">{{ $status }}</span>
          </td>
          <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
            <div class="relative inline-block text-left" @click.away="open = false"
              x-data="{ open: false, top: 0, left: 0, toggle(e) { const r = e.currentTarget.getBoundingClientRect(); this.top = r.bottom + 4; this.left = Math.max(8, r.right - 224); this.open = !this.open; } }">
              <button type="button" aria-label="Actions" @click="toggle($event)"
                class="inline-flex items-center px-2 py-2 border border-gray-300 shadow-sm rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                <i data-lucide="more-vertical" class="w-4 h-4"></i>
              </button>
              <div x-show="open" x-transition :style="{ top: top + 'px', left: left + 'px' }"
                class="fixed w-56 rounded-md shadow-lg bg-white ring-1 ring-black ring-opacity-5 z-50" style="display: none;">
                <div class="py-1" role="menu">
                  <button type="button" {{ $hasReceipt ? 'disabled' : '' }}
                    @click="open = false; enterBettermentBillReceipt(@js($fileNo), @js(number_format($betterment, 2)), @js($payment->id))"
                    class="group flex items-center px-3 py-1.5 text-xs w-full text-left {{ $hasReceipt ? 'text-gray-400 cursor-not-allowed bg-gray-50' : 'text-gray-700 hover:bg-gray-100 hover:text-gray-900' }}">
                    <i data-lucide="plus-circle" class="mr-2 h-3.5 w-3.5 {{ $hasReceipt ? 'text-gray-300' : 'text-gray-400' }}"></i>
                    {{ $hasReceipt ? 'Betterment Receipt Entered' : 'Enter Betterment Receipt' }}
                  </button>
                  <a href="{{ route('physical-planning.bills.print', ['id' => $payment->id]) }}" target="_blank" rel="noopener"
                    class="group flex items-center px-3 py-1.5 text-xs text-gray-700 hover:bg-gray-100 hover:text-gray-900 w-full text-left">
                    <i data-lucide="printer" class="mr-2 h-3.5 w-3.5 text-gray-400"></i>
                    View / Print Bill
                  </a>
                  <a href="{{ route('physical-planning.bills.print', ['id' => $payment->id, 'download' => 1]) }}"
                    class="group flex items-center px-3 py-1.5 text-xs text-gray-700 hover:bg-gray-100 hover:text-gray-900 w-full text-left">
                    <i data-lucide="download" class="mr-2 h-3.5 w-3.5 text-gray-400"></i>
                    Download Bill
                  </a>
                  @if($evidenceUrl)
                    <a href="{{ $evidenceUrl }}" target="_blank" rel="noopener"
                      class="group flex items-center px-3 py-1.5 text-xs text-gray-700 hover:bg-gray-100 hover:text-gray-900 w-full text-left">
                      <i data-lucide="paperclip" class="mr-2 h-3.5 w-3.5 text-gray-400"></i>
                      View Evidence of Payment
                    </a>
                  @endif
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
              <p class="text-sm">No {{ $ppLabel }} payment records found</p>
            </div>
          </td>
        </tr>
      @endforelse
    </tbody>
  </table>
</div>
