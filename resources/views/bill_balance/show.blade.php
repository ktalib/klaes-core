@extends('layouts.app')

@section('page-title', 'View Bill Balance')

@push('styles')
<link rel="stylesheet" href="{{ asset('css/bill_balance.css') }}">
@endpush

@push('scripts')
  <script>
    document.addEventListener('DOMContentLoaded', () => {
      if (typeof lucide !== 'undefined') {
        lucide.createIcons();
      }
    });
  </script>
@endpush

@section('content')
  <div class="flex-1 overflow-auto bg-slate-50/60">
    @include('admin.header', [
        'PageTitle' => 'Bill Balance Details',
        'PageDescription' => 'Summary of the selected bill balance record.',
    ])

    <div class="max-w-3xl mx-auto p-6 space-y-6">
      <div class="card space-y-4">
        <div class="flex items-center justify-between border-b pb-2 mb-2">
          <h3 class="text-lg font-semibold text-slate-900">Bill Balance Details</h3>
          @if($billBalance->reference)
          <p class="text-xs font-bold text-red-600 uppercase">{{ $billBalance->reference }}</p>
          @endif
        </div>
        <div class="grid grid-cols-2 gap-4 text-sm">
          <div>
            <p class="font-semibold text-slate-600 text-xs uppercase">File Number</p>
            <p class="text-slate-900">{{ $billBalance->file_number ?? '—' }}</p>
          </div>
          <div>
            <p class="font-semibold text-slate-600 text-xs uppercase">Applicant Name</p>
            <p class="text-slate-900">{{ $billBalance->applicant_name ?? '—' }}</p>
          </div>
          <div>
            <p class="font-semibold text-slate-600 text-xs uppercase">Applicant Address</p>
            <p class="text-slate-900">{{ $billBalance->applicant_address ?? '—' }}</p>
          </div>
          <div>
            <p class="font-semibold text-slate-600 text-xs uppercase">Location / Station</p>
            <p class="text-slate-900">{{ $billBalance->location_station ?? '—' }}</p>
          </div>
          <div>
            <p class="font-semibold text-slate-600 text-xs uppercase">District</p>
            <p class="text-slate-900">{{ $billBalance->district ?? '—' }}</p>
          </div>
          <div>
            <p class="font-semibold text-slate-600 text-xs uppercase">Amount (Rent Per Annum)</p>
            <p class="text-slate-900 font-semibold">₦{{ number_format($billBalance->amount ?? 0, 2) }}</p>
          </div>
          <div>
            <p class="font-semibold text-slate-600 text-xs uppercase">Date of Issue</p>
            <p class="text-slate-900">{{ optional($billBalance->prepared_at)->format('d M Y h:i A') ?? '—' }}</p>
          </div>
        </div>
      </div>

      @if($billingRecord)
        <div class="card space-y-4">
          <h3 class="text-lg font-semibold text-slate-900 border-b pb-2">Billing Information</h3>
          <div class="grid grid-cols-2 gap-4 text-sm">
            <div>
              <p class="font-semibold text-slate-600 text-xs uppercase">Receipt Number (CRC No)</p>
              <p class="text-slate-900">{{ $billingRecord->bill_balance_reciept ?? '—' }}</p>
            </div>
            <div>
              <p class="font-semibold text-slate-600 text-xs uppercase">Receipt Date</p>
              <p class="text-slate-900">{{ $billingRecord->bill_balance_date ? \Carbon\Carbon::parse($billingRecord->bill_balance_date)->format('d M Y') : '—' }}</p>
            </div>
            <div>
              <p class="font-semibold text-slate-600 text-xs uppercase">Payment Status</p>
              <p class="text-slate-900">{{ $billingRecord->Payment_Status ?? '—' }}</p>
            </div>
            <div>
              <p class="font-semibold text-slate-600 text-xs uppercase">Amount Deposited</p>
              <p class="text-slate-900 font-semibold">₦{{ number_format($billingRecord->Penalty_Fees ?? 0, 2) }}</p>
            </div>
          </div>

          <div class="border-t pt-4">
            <h4 class="text-sm font-semibold text-slate-700 uppercase mb-3">Fee Breakdown</h4>
            <div class="grid grid-cols-2 gap-3 text-sm">
              <div class="flex justify-between">
                <span class="text-slate-600">Registration Fees:</span>
                <span class="font-semibold">₦{{ number_format($billingRecord->Site_Plan_Fee ?? 0, 2) }}</span>
              </div>
              <div class="flex justify-between">
                <span class="text-slate-600">Survey Fees:</span>
                <span class="font-semibold">₦{{ number_format($billingRecord->survey_fee ?? 0, 2) }}</span>
              </div>
              <div class="flex justify-between">
                <span class="text-slate-600">Preparation Fees:</span>
                <span class="font-semibold">₦{{ number_format($billingRecord->Processing_Fee ?? 0, 2) }}</span>
              </div>
              <div class="flex justify-between">
                <span class="text-slate-600">Compensation Fees:</span>
                <span class="font-semibold">₦{{ number_format($billingRecord->Betterment_Charges ?? 0, 2) }}</span>
              </div>
              <div class="flex justify-between">
                <span class="text-slate-600">Development Charges:</span>
                <span class="font-semibold">₦{{ number_format($billingRecord->Land_Use_Charge ?? 0, 2) }}</span>
              </div>
              @php
                $totalFees = ($billingRecord->Site_Plan_Fee ?? 0) 
                           + ($billingRecord->survey_fee ?? 0)
                           + ($billingRecord->Processing_Fee ?? 0)
                           + ($billingRecord->Betterment_Charges ?? 0)
                           + ($billingRecord->Land_Use_Charge ?? 0);
              @endphp
              <div class="flex justify-between col-span-2 pt-2 border-t border-slate-200">
                <span class="text-slate-700 font-semibold">Total Fees:</span>
                <span class="text-teal-600 font-bold text-base">₦{{ number_format($totalFees, 2) }}</span>
              </div>
            </div>
          </div>
        </div>
      @endif

      <div class="flex gap-3">
        <a href="{{ route('bill-balance.index') }}" class="btn-secondary">
          <i data-lucide="arrow-left" class="w-4 h-4"></i>
          <span>Back</span>
        </a>
        <a href="{{ route('bill-balance.edit', $billBalance) }}" class="btn-primary">
          <i data-lucide="edit" class="w-4 h-4"></i>
          <span>Edit</span>
        </a>
        <a href="{{ route('bill-balance.print', $billBalance) }}" target="_blank" class="btn-primary" style="background: #059669;">
          <i data-lucide="printer" class="w-4 h-4"></i>
          <span>Print Certificate</span>
        </a>
      </div>
    </div>
  </div>
@endsection
