@extends('layouts.app')

@section('content')
<div class="flex-1 overflow-auto bg-gray-50">
  @include('admin.header', ['PageTitle' => $pageTitle, 'PageDescription' => $pageDescription])

  <div class="p-6">
    <div class="flex items-center gap-2 text-sm text-gray-500 mb-4">
      <i data-lucide="banknote" class="w-4 h-4 text-emerald-600"></i>
      <span>KLAES REV-M</span>
      <i data-lucide="chevron-right" class="w-3.5 h-3.5"></i>
      <span>Legacy Billing</span>
      <i data-lucide="chevron-right" class="w-3.5 h-3.5"></i>
      <span class="font-semibold text-gray-700">{{ $pageTitle }}</span>
    </div>

    @include('revenue.partials._stat_cards')

    <form method="GET" class="flex flex-wrap items-center gap-2 mb-4">
      <select name="type" class="border border-gray-300 rounded-lg px-3 py-2 text-sm">
        <option value="">Every bill type</option>
        @foreach ($types as $value => $label)
          <option value="{{ $value }}" @selected($filters['type'] === $value)>{{ $label }}</option>
        @endforeach
      </select>
      <select name="status" class="border border-gray-300 rounded-lg px-3 py-2 text-sm">
        <option value="">Every status</option>
        @foreach (\App\Http\Controllers\Revenue\BillMasterController::statuses() as $value => $label)
          <option value="{{ $value }}" @selected($filters['status'] === $value)>{{ $label }}</option>
        @endforeach
      </select>
      <input type="text" name="q" value="{{ $filters['q'] }}" placeholder="Bill Ref ID, receipt no., file no., payer, plot"
             class="border border-gray-300 rounded-lg px-3 py-2 text-sm w-80">
      <button class="px-3 py-2 rounded-lg bg-gray-800 text-white text-sm font-semibold">Search</button>
      @if ($filters['q'] !== '' || $filters['status'] !== '' || $filters['type'] !== '')
        <a href="{{ $indexUrl }}" class="px-3 py-2 text-sm text-gray-600">Clear</a>
      @endif
    </form>

    <div class="rounded-xl border border-gray-200 bg-white overflow-x-auto">
      <table class="min-w-full text-sm">
        <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500">
          <tr>
            <th class="px-3 py-2">S/N</th>
            <th class="px-3 py-2">Bill Ref ID</th>
            <th class="px-3 py-2">Type</th>
            <th class="px-3 py-2">File No.</th>
            <th class="px-3 py-2">Payer</th>
            <th class="px-3 py-2">Plot No.</th>
            <th class="px-3 py-2">District</th>
            <th class="px-3 py-2">LGA</th>
            <th class="px-3 py-2">Location</th>
            <th class="px-3 py-2">Period</th>
            <th class="px-3 py-2 text-right">Amount</th>
            <th class="px-3 py-2">Receipt No.</th>
            <th class="px-3 py-2">Receipt Date</th>
            <th class="px-3 py-2">Status</th>
            <th class="px-3 py-2 text-right">Action</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
          @forelse ($rows as $bill)
            <tr class="hover:bg-gray-50">
              <td class="px-3 py-2 text-gray-500">{{ $rows->firstItem() + $loop->index }}</td>
              <td class="px-3 py-2 font-mono whitespace-nowrap">{{ $bill->bill_ref_id }}</td>
              <td class="px-3 py-2 whitespace-nowrap">{{ $bill->typeName() }}</td>
              <td class="px-3 py-2 font-mono whitespace-nowrap">{{ $bill->file_number ?: '—' }}</td>
              <td class="px-3 py-2">{{ $bill->payer_name ?: '—' }}</td>
              <td class="px-3 py-2">{{ $bill->plot_no ?: '—' }}</td>
              <td class="px-3 py-2">{{ $bill->district ?: '—' }}</td>
              <td class="px-3 py-2">{{ $bill->lga ?: '—' }}</td>
              <td class="px-3 py-2">{{ $bill->location ?: '—' }}</td>
              <td class="px-3 py-2 whitespace-nowrap">{{ $bill->from_year || $bill->to_year ? ($bill->from_year ?: '?') . ' – ' . ($bill->to_year ?: '?') : '—' }}</td>
              <td class="px-3 py-2 text-right whitespace-nowrap">₦{{ number_format((float) $bill->amount, 2) }}</td>
              <td class="px-3 py-2 font-mono whitespace-nowrap">{{ $bill->receipt_no ?: '—' }}</td>
              <td class="px-3 py-2 whitespace-nowrap">{{ optional($bill->receipt_date)->format('d M Y') ?: '—' }}</td>
              <td class="px-3 py-2">@include('revenue.partials._status_pill', ['status' => $bill->status])</td>
              <td class="px-3 py-2 text-right">
                @include('revenue.partials._row_actions', ['items' => [
                    ['View bill', 'eye', null],
                    ['View indexing record', 'folder-open', null],
                ]])
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="15" class="px-3 py-10 text-center text-gray-400">
                <i data-lucide="inbox" class="w-8 h-8 mx-auto mb-2"></i>
                <div>No legacy bills loaded yet. They come in from File Indexing once the legacy load is approved.</div>
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
