@extends('layouts.app')

@section('content')
<div class="flex-1 overflow-auto bg-gray-50">
  @include('admin.header', ['PageTitle' => $pageTitle, 'PageDescription' => $pageDescription])

  <div class="p-6">
    <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
      <div class="flex items-center gap-2 text-sm text-gray-500">
        <i data-lucide="banknote" class="w-4 h-4 text-emerald-600"></i>
        <span>KLAES REV-M</span>
        <i data-lucide="chevron-right" class="w-3.5 h-3.5"></i>
        <span>Receipting · Generate Receipt</span>
        <i data-lucide="chevron-right" class="w-3.5 h-3.5"></i>
        <span class="font-semibold text-gray-700">{{ $pageTitle }}</span>
      </div>
      <a href="{{ route('revenue.receipts.awaiting') }}" class="inline-flex items-center gap-2 px-3 py-2 rounded-lg border border-amber-300 bg-amber-50 text-amber-800 text-sm font-semibold hover:bg-amber-100">
        <i data-lucide="hourglass" class="w-4 h-4"></i> Awaiting receipt: {{ number_format($awaiting) }}
      </a>
    </div>

    @include('revenue.receipts.partials._flash')

    <div class="grid grid-cols-2 gap-4 mb-6 max-w-xl">
      <div class="rounded-xl border border-gray-200 bg-white p-4 flex items-center gap-3">
        <span class="inline-flex items-center justify-center w-10 h-10 rounded-lg text-emerald-600 bg-emerald-50"><i data-lucide="receipt" class="w-5 h-5"></i></span>
        <div><div class="text-xs uppercase text-gray-500">Receipts issued</div><div class="text-lg font-bold text-gray-800">{{ number_format((int) $totals->n) }}</div></div>
      </div>
      <div class="rounded-xl border border-gray-200 bg-white p-4 flex items-center gap-3">
        <span class="inline-flex items-center justify-center w-10 h-10 rounded-lg text-blue-600 bg-blue-50"><i data-lucide="banknote" class="w-5 h-5"></i></span>
        <div><div class="text-xs uppercase text-gray-500">Amount receipted</div><div class="text-lg font-bold text-gray-800">₦{{ number_format((float) $totals->amount, 2) }}</div></div>
      </div>
    </div>

    @include('revenue.receipts.partials._filters')

    <div class="rounded-xl border border-gray-200 bg-white overflow-x-auto">
      <table class="min-w-full text-sm">
        <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500">
          <tr>
            <th class="px-3 py-2">S/N</th>
            <th class="px-3 py-2">Receipt No.</th>
            <th class="px-3 py-2">Receipt Date</th>
            <th class="px-3 py-2">Bill Ref ID</th>
            <th class="px-3 py-2">Transaction ID</th>
            <th class="px-3 py-2">Bill Type</th>
            <th class="px-3 py-2">File No.</th>
            <th class="px-3 py-2">Payer</th>
            <th class="px-3 py-2">Plot No.</th>
            <th class="px-3 py-2">District</th>
            <th class="px-3 py-2">LGA</th>
            <th class="px-3 py-2">Location</th>
            <th class="px-3 py-2 text-right">Amount</th>
            <th class="px-3 py-2">Printed</th>
            <th class="px-3 py-2 text-right">Action</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
          @forelse ($rows as $row)
            @php
              $revItems = [['View e-Receipt', 'file-text', route('revenue.receipts.show', $row->receipt_no), true]];
              if ($canPrint && $row->status === 'issued') {
                  $revItems[] = [$row->copies_printed ? 'Reprint receipt' : 'Print receipt (3 copies)', 'printer', route('revenue.receipts.print', $row->receipt_no), true];
              }
              $revItems[] = ['Send e-Receipt', 'mail', null];
            @endphp
            <tr class="hover:bg-gray-50">
              <td class="px-3 py-2 text-gray-500">{{ $rows->firstItem() + $loop->index }}</td>
              <td class="px-3 py-2 font-mono whitespace-nowrap">{{ $row->receipt_no }}</td>
              <td class="px-3 py-2 whitespace-nowrap">{{ $row->issued_at ? \Illuminate\Support\Carbon::parse($row->issued_at)->format('d M Y') : '—' }}</td>
              <td class="px-3 py-2 font-mono whitespace-nowrap">{{ $row->bill_ref_id }}</td>
              <td class="px-3 py-2 font-mono whitespace-nowrap">{{ $row->transaction_id ?: '—' }}</td>
              <td class="px-3 py-2 whitespace-nowrap">{{ $row->type_name ?: '—' }}</td>
              <td class="px-3 py-2 font-mono whitespace-nowrap">{{ $row->file_number ?: '—' }}</td>
              <td class="px-3 py-2">{{ $row->payer_name ?: '—' }}</td>
              <td class="px-3 py-2">{{ $row->plot_no ?: '—' }}</td>
              <td class="px-3 py-2">{{ $row->district ?: '—' }}</td>
              <td class="px-3 py-2">{{ $row->lga ?: '—' }}</td>
              <td class="px-3 py-2">{{ $row->location ?: '—' }}</td>
              <td class="px-3 py-2 text-right whitespace-nowrap">₦{{ number_format((float) $row->amount, 2) }}</td>
              <td class="px-3 py-2 whitespace-nowrap">
                @if ($row->status !== 'issued')
                  <span class="px-2 py-0.5 rounded-full text-xs font-semibold bg-gray-100 text-gray-600">Cancelled</span>
                @elseif ($row->copies_printed)
                  <span class="px-2 py-0.5 rounded-full text-xs font-semibold bg-green-100 text-green-800">Printed{{ $row->reprint_count ? ' · ' . $row->reprint_count . ' reprint' . ($row->reprint_count > 1 ? 's' : '') : '' }}</span>
                @else
                  <span class="px-2 py-0.5 rounded-full text-xs font-semibold bg-amber-100 text-amber-800">Not printed</span>
                @endif
              </td>
              <td class="px-3 py-2 text-right">@include('revenue.partials._row_actions', ['items' => $revItems])</td>
            </tr>
          @empty
            <tr>
              <td colspan="15" class="px-3 py-10 text-center text-gray-400">
                <i data-lucide="inbox" class="w-8 h-8 mx-auto mb-2"></i>
                <div>No receipts yet. Paid bills appear under Awaiting Receipt.</div>
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
