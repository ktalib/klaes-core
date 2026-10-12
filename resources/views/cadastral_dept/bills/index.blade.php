@extends('layouts.app')

@section('content')
<div class="flex-1 overflow-auto bg-gray-50">
  @include('admin.header', ['PageTitle' => 'P&D Bills', 'PageDescription' => 'Fees, Plan and Description bills for files in the Cadastral Department workflow'])

  <div class="p-6">
    @include('cadastral_dept.partials._nav')

    @include('cadastral_dept.partials._stat_cards')

    <form method="GET" class="flex flex-wrap items-center gap-2 mb-4">
      <select name="status" class="border border-gray-300 rounded-lg px-3 py-2 text-sm">
        <option value="">Every status</option>
        @foreach (['Issued', 'Cancelled'] as $s)<option value="{{ $s }}" @selected($status === $s)>{{ $s }}</option>@endforeach
      </select>
      <input type="text" name="q" value="{{ $term }}" placeholder="Bill or file no." class="border border-gray-300 rounded-lg px-3 py-2 text-sm w-60">
      <button class="px-3 py-2 rounded-lg bg-gray-800 text-white text-sm font-semibold">Search</button>
      @if ($term !== '' || $status)
        <a href="{{ route('cadastral-dept.bills.index') }}" class="px-3 py-2 text-sm text-gray-600">Clear</a>
      @endif
    </form>

    <div class="rounded-xl border border-gray-200 bg-white overflow-x-auto">
      <table class="min-w-full text-sm">
        <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500">
          <tr>
            <th class="px-3 py-2">S/N</th><th class="px-3 py-2">Bill</th><th class="px-3 py-2">File No.</th>
            <th class="px-3 py-2">Plot No.</th><th class="px-3 py-2">District</th><th class="px-3 py-2">LGA</th><th class="px-3 py-2">Location</th>
            <th class="px-3 py-2">Area (ha)</th><th class="px-3 py-2 text-right">Total</th><th class="px-3 py-2">Status</th><th class="px-3 py-2">Issued</th>
            <th class="px-3 py-2 text-right">Action</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
          @forelse ($rows as $bill)
            @php
              $cdCase = $cases[$bill->file_number] ?? null;
              $items = [];
              if (auth()->user()->canDo('Cad - Records', 'print')) {
                  $items[] = ['Print bill', 'printer', route('cadastral-dept.bills.print', $bill->id), true];
              }
              if ($cdCase) {
                  $items[] = ['Open bill page', 'receipt', route('cadastral-dept.cases.bill.show', $cdCase->id)];
                  $items[] = ['View', 'eye', route('cadastral-dept.cases.show', $cdCase->id)];
              }
            @endphp
            <tr class="hover:bg-gray-50">
              <td class="px-3 py-2 text-gray-500">{{ $rows->firstItem() + $loop->index }}</td>
              <td class="px-3 py-2 font-mono">{{ $bill->bill_ref }}</td>
              <td class="px-3 py-2 font-mono">{{ $bill->file_number }}</td>
              <td class="px-3 py-2">{{ optional($cdCase)->plot_no ?: '—' }}</td>
              <td class="px-3 py-2">{{ optional($cdCase)->district ?: '—' }}</td>
              <td class="px-3 py-2">{{ optional($cdCase)->lga ?: '—' }}</td>
              <td class="px-3 py-2">{{ optional($cdCase)->location ?: '—' }}</td>
              <td class="px-3 py-2">{{ $bill->area_ha !== null ? number_format((float) $bill->area_ha, 4) : '—' }}</td>
              <td class="px-3 py-2 text-right">₦{{ number_format((float) $bill->grand_total, 2) }}</td>
              <td class="px-3 py-2"><span class="px-2 py-0.5 rounded-full text-xs font-semibold {{ $bill->status === 'Issued' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-600' }}">{{ $bill->status }}</span></td>
              <td class="px-3 py-2 whitespace-nowrap">{{ optional($bill->issued_at)->format('d M Y') }}</td>
              <td class="px-3 py-2 text-right">@if ($items)@include('cadastral_dept.partials._row_actions', ['items' => $items])@endif</td>
            </tr>
          @empty
            <tr><td colspan="12" class="px-3 py-6 text-center text-gray-400">No P&amp;D bills yet. AD P&amp;D issues one from the file's billing step.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
    <div class="mt-4">{{ $rows->links() }}</div>
  </div>
  @include('admin.footer')
</div>
@endsection
