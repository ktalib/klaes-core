@extends('layouts.app')

@section('content')
<div class="flex-1 overflow-auto bg-gray-50">
  @include('admin.header', ['PageTitle' => 'Awaiting Receipt', 'PageDescription' => 'Paid bills waiting for their official Ministry receipt'])

  <div class="p-6">
    <div class="flex items-center gap-2 text-sm text-gray-500 mb-4">
      <i data-lucide="banknote" class="w-4 h-4 text-emerald-600"></i>
      <span>KLAES REV-M</span>
      <i data-lucide="chevron-right" class="w-3.5 h-3.5"></i>
      <span>Receipting · Generate Receipt</span>
      <i data-lucide="chevron-right" class="w-3.5 h-3.5"></i>
      <span class="font-semibold text-gray-700">Awaiting Receipt</span>
    </div>

    @include('revenue.receipts.partials._flash')

    <div class="grid grid-cols-2 gap-4 mb-6 max-w-xl">
      <div class="rounded-xl border border-gray-200 bg-white p-4 flex items-center gap-3">
        <span class="inline-flex items-center justify-center w-10 h-10 rounded-lg text-amber-600 bg-amber-50"><i data-lucide="hourglass" class="w-5 h-5"></i></span>
        <div><div class="text-xs uppercase text-gray-500">Payments awaiting</div><div class="text-lg font-bold text-gray-800">{{ number_format((int) $totals->n) }}</div></div>
      </div>
      <div class="rounded-xl border border-gray-200 bg-white p-4 flex items-center gap-3">
        <span class="inline-flex items-center justify-center w-10 h-10 rounded-lg text-emerald-600 bg-emerald-50"><i data-lucide="banknote" class="w-5 h-5"></i></span>
        <div><div class="text-xs uppercase text-gray-500">Amount</div><div class="text-lg font-bold text-gray-800">₦{{ number_format((float) $totals->amount, 2) }}</div></div>
      </div>
    </div>

    @include('revenue.receipts.partials._filters')

    <div class="rounded-xl border border-gray-200 bg-white overflow-x-auto">
      <table class="min-w-full text-sm">
        <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500">
          <tr>
            <th class="px-3 py-2">S/N</th>
            <th class="px-3 py-2">Bill Ref ID</th>
            <th class="px-3 py-2">Bill Type</th>
            <th class="px-3 py-2">File No.</th>
            <th class="px-3 py-2">Payer</th>
            <th class="px-3 py-2">Plot No.</th>
            <th class="px-3 py-2">District</th>
            <th class="px-3 py-2">LGA</th>
            <th class="px-3 py-2">Location</th>
            <th class="px-3 py-2">Method</th>
            <th class="px-3 py-2">Transaction ID</th>
            <th class="px-3 py-2 text-right">Amount</th>
            <th class="px-3 py-2">Paid</th>
            <th class="px-3 py-2 text-right">Action</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
          @forelse ($rows as $row)
            @php
              $revItems = [];
              if ($canIssue) {
                  $revItems[] = ['Generate receipt', 'file-check', null, false, 'RevReceipts.issue(' . (int) $row->id . ', ' . json_encode($row->bill_ref_id) . ', ' . json_encode('₦' . number_format((float) $row->amount, 2)) . ')'];
              }
            @endphp
            <tr class="hover:bg-gray-50">
              <td class="px-3 py-2 text-gray-500">{{ $rows->firstItem() + $loop->index }}</td>
              <td class="px-3 py-2 font-mono whitespace-nowrap">{{ $row->bill_ref_id }}</td>
              <td class="px-3 py-2 whitespace-nowrap">{{ $row->type_name ?: '—' }}</td>
              <td class="px-3 py-2 font-mono whitespace-nowrap">{{ $row->file_number ?: '—' }}</td>
              <td class="px-3 py-2">{{ $row->payer_name ?: '—' }}</td>
              <td class="px-3 py-2">{{ $row->plot_no ?: '—' }}</td>
              <td class="px-3 py-2">{{ $row->district ?: '—' }}</td>
              <td class="px-3 py-2">{{ $row->lga ?: '—' }}</td>
              <td class="px-3 py-2">{{ $row->location ?: '—' }}</td>
              <td class="px-3 py-2 whitespace-nowrap">{{ \App\Models\BillPayment::labelFor($row->method) }}</td>
              <td class="px-3 py-2 font-mono whitespace-nowrap">{{ $row->transaction_id ?: '—' }}</td>
              <td class="px-3 py-2 text-right whitespace-nowrap">₦{{ number_format((float) $row->amount, 2) }}</td>
              <td class="px-3 py-2 whitespace-nowrap">{{ $row->paid_at ? \Illuminate\Support\Carbon::parse($row->paid_at)->format('d M Y H:i') : '—' }}</td>
              <td class="px-3 py-2 text-right">@if ($revItems)@include('revenue.partials._row_actions', ['items' => $revItems])@endif</td>
            </tr>
          @empty
            <tr>
              <td colspan="14" class="px-3 py-10 text-center text-gray-400">
                <i data-lucide="check-circle" class="w-8 h-8 mx-auto mb-2"></i>
                <div>Every payment has its receipt.</div>
              </td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>
    <div class="mt-4">{{ $rows->links() }}</div>
  </div>
  @include('admin.footer')
</div>
@endsection

@section('footer-scripts')
<script>
  window.RevReceipts = {
    issueUrl: @json(url('revenue/receipts/issue/__ID__')),
    printUrl: @json(url('revenue/receipt/__NO__/print')),
    canPrint: @json($canPrint),
  };
</script>
<script src="{{ asset('js/revenue/receipts.js') }}?v={{ is_file(public_path('js/revenue/receipts.js')) ? filemtime(public_path('js/revenue/receipts.js')) : 1 }}"></script>
@endsection
