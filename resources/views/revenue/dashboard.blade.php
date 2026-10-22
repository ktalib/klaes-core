@extends('layouts.app')

@section('content')
@php
  $money = fn ($v) => '₦' . number_format((float) $v, 2);
  $maxMonth = max(1, $months->max('amount'));
  $collectedPct = $totals->billed > 0 ? min(100, round($totals->collected / $totals->billed * 100, 1)) : 0;
@endphp
<div class="flex-1 overflow-auto bg-gray-50">
  @include('admin.header', ['PageTitle' => 'REV-M Dashboard', 'PageDescription' => 'Bills, collections and receipting across KLAES REV-M'])

  <div class="p-6 space-y-6">
    <div class="flex items-center gap-2 text-sm text-gray-500">
      <i data-lucide="banknote" class="w-4 h-4 text-emerald-600"></i>
      <span>KLAES REV-M</span>
      <i data-lucide="chevron-right" class="w-3.5 h-3.5"></i>
      <span class="font-semibold text-gray-700">Dashboard</span>
    </div>

    {{-- Headline figures --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
      @foreach ([
          ['Total billed', $money($totals->billed), number_format($totals->n) . ' bills', 'receipt', 'text-blue-600 bg-blue-50', route('revenue.bills.all')],
          ['Collected', $money($totals->collected), $collectedPct . '% of billed', 'badge-check', 'text-green-600 bg-green-50', route('revenue.bills.all', ['status' => 'paid'])],
          ['Outstanding', $money($totals->outstanding), number_format($totals->unpaid) . ' unpaid / part paid', 'alert-circle', 'text-amber-600 bg-amber-50', route('revenue.bills.all', ['status' => 'unpaid'])],
          ['Pending generation', number_format($totals->pending), 'module bills to generate', 'file-clock', 'text-gray-600 bg-gray-100', route('revenue.bills.all', ['status' => 'pending'])],
          ['Collected today', $money($collectedToday), now()->format('d M Y'), 'calendar-check', 'text-emerald-600 bg-emerald-50', null],
          ['Collected this month', $money($collectedMonth), now()->format('F Y'), 'calendar-range', 'text-emerald-600 bg-emerald-50', null],
          ['Awaiting receipt', number_format((int) $awaiting->n), $money($awaiting->amount) . ' paid, no receipt', 'hourglass', 'text-orange-600 bg-orange-50', route('revenue.receipts.awaiting')],
          ['Receipts issued', number_format((int) $receipts->total), (int) $receipts->today . ' today · ' . (int) $receipts->month . ' this month', 'file-check', 'text-indigo-600 bg-indigo-50', route('revenue.receipts.index', 'print')],
      ] as [$label, $value, $sub, $icon, $tone, $href])
        <a @if ($href) href="{{ $href }}" @endif class="rounded-xl border border-gray-200 bg-white p-4 flex items-center gap-3 {{ $href ? 'hover:border-emerald-300 hover:shadow-sm' : '' }}">
          <span class="inline-flex items-center justify-center w-10 h-10 rounded-lg shrink-0 {{ $tone }}"><i data-lucide="{{ $icon }}" class="w-5 h-5"></i></span>
          <div class="min-w-0">
            <div class="text-xs uppercase text-gray-500">{{ $label }}</div>
            <div class="text-lg font-bold text-gray-800 truncate">{{ $value }}</div>
            <div class="text-xs text-gray-500 truncate">{{ $sub }}</div>
          </div>
        </a>
      @endforeach
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
      {{-- Collections, last six months --}}
      <div class="lg:col-span-2 rounded-xl border border-gray-200 bg-white p-5">
        <div class="flex items-center justify-between mb-4">
          <div class="font-semibold text-gray-800 flex items-center gap-2"><i data-lucide="bar-chart-3" class="w-4 h-4 text-emerald-600"></i> Collections, last six months</div>
          <div class="text-xs text-gray-500">Successful payments by month paid</div>
        </div>
        <div class="flex items-end gap-3 h-48">
          @foreach ($months as $m)
            <div class="flex-1 flex flex-col items-center justify-end h-full" title="{{ $m->label }}: {{ $money($m->amount) }} ({{ $m->n }} payments)">
              <div class="text-xs text-gray-600 mb-1 whitespace-nowrap">{{ $m->amount > 0 ? '₦' . number_format($m->amount / 1000, 0) . 'k' : '' }}</div>
              <div class="w-full rounded-t bg-emerald-500" style="height: {{ max($m->amount > 0 ? 3 : 0, round($m->amount / $maxMonth * 100)) }}%"></div>
              <div class="text-xs text-gray-500 mt-2 whitespace-nowrap">{{ $m->label }}</div>
            </div>
          @endforeach
        </div>
      </div>

      {{-- This month by payment method --}}
      <div class="rounded-xl border border-gray-200 bg-white p-5">
        <div class="font-semibold text-gray-800 flex items-center gap-2 mb-4"><i data-lucide="wallet" class="w-4 h-4 text-emerald-600"></i> This month by payment method</div>
        @forelse ($byMethod as $m)
          @php $pct = $collectedMonth > 0 ? round($m->amount / $collectedMonth * 100) : 0; @endphp
          <div class="mb-3">
            <div class="flex justify-between text-sm"><span class="text-gray-700">{{ $m->label }}</span><span class="font-semibold">{{ $money($m->amount) }}</span></div>
            <div class="h-2 rounded bg-gray-100 mt-1"><div class="h-2 rounded bg-emerald-500" style="width: {{ $pct }}%"></div></div>
            <div class="text-xs text-gray-500 mt-0.5">{{ $m->n }} payments · {{ $pct }}%</div>
          </div>
        @empty
          <div class="text-sm text-gray-400 py-6 text-center">No payments this month yet.</div>
        @endforelse
      </div>
    </div>

    {{-- By bill type --}}
    <div class="rounded-xl border border-gray-200 bg-white">
      <div class="px-5 py-4 border-b border-gray-100 font-semibold text-gray-800 flex items-center gap-2"><i data-lucide="layers" class="w-4 h-4 text-emerald-600"></i> Bill Master by bill type</div>
      <div class="overflow-x-auto">
        <table class="min-w-full text-sm">
          <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500">
            <tr>
              <th class="px-3 py-2">S/N</th>
              <th class="px-3 py-2">Bill type</th>
              <th class="px-3 py-2 text-right">Bills</th>
              <th class="px-3 py-2 text-right">Pending</th>
              <th class="px-3 py-2 text-right">Unpaid</th>
              <th class="px-3 py-2 text-right">Part paid</th>
              <th class="px-3 py-2 text-right">Paid</th>
              <th class="px-3 py-2 text-right">Billed</th>
              <th class="px-3 py-2 text-right">Collected</th>
              <th class="px-3 py-2 text-right">Outstanding</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100">
            @foreach ($rows as $r)
              <tr class="hover:bg-gray-50">
                <td class="px-3 py-2 text-gray-500">{{ $loop->iteration }}</td>
                <td class="px-3 py-2 {{ $r->parent ? 'pl-8' : 'font-medium' }}">
                  @if ($r->url)<a href="{{ $r->url }}" class="text-emerald-700 hover:underline">{{ $r->name }}</a>@else{{ $r->name }} <span class="text-xs text-gray-400">(on hold)</span>@endif
                </td>
                <td class="px-3 py-2 text-right">{{ number_format($r->n) }}</td>
                <td class="px-3 py-2 text-right {{ $r->pending ? 'text-gray-800 font-semibold' : 'text-gray-400' }}">{{ number_format($r->pending) }}</td>
                <td class="px-3 py-2 text-right">{{ number_format($r->unpaid) }}</td>
                <td class="px-3 py-2 text-right">{{ number_format($r->part_paid) }}</td>
                <td class="px-3 py-2 text-right">{{ number_format($r->paid) }}</td>
                <td class="px-3 py-2 text-right whitespace-nowrap">{{ $money($r->billed) }}</td>
                <td class="px-3 py-2 text-right whitespace-nowrap">{{ $money($r->collected) }}</td>
                <td class="px-3 py-2 text-right whitespace-nowrap">{{ $money(max(0, $r->billed - $r->collected)) }}</td>
              </tr>
            @endforeach
          </tbody>
          <tfoot class="bg-emerald-50 font-semibold">
            <tr>
              <td></td>
              <td class="px-3 py-2">Total</td>
              <td class="px-3 py-2 text-right">{{ number_format($totals->n) }}</td>
              <td class="px-3 py-2 text-right">{{ number_format($totals->pending) }}</td>
              <td class="px-3 py-2 text-right" colspan="2">{{ number_format($totals->unpaid) }}</td>
              <td class="px-3 py-2 text-right">{{ number_format($totals->paid) }}</td>
              <td class="px-3 py-2 text-right whitespace-nowrap">{{ $money($totals->billed) }}</td>
              <td class="px-3 py-2 text-right whitespace-nowrap">{{ $money($totals->collected) }}</td>
              <td class="px-3 py-2 text-right whitespace-nowrap">{{ $money($totals->outstanding) }}</td>
            </tr>
          </tfoot>
        </table>
      </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
      {{-- Recent payments --}}
      <div class="lg:col-span-2 rounded-xl border border-gray-200 bg-white">
        <div class="px-5 py-4 border-b border-gray-100 font-semibold text-gray-800 flex items-center gap-2"><i data-lucide="credit-card" class="w-4 h-4 text-emerald-600"></i> Latest payments</div>
        <div class="overflow-x-auto">
          <table class="min-w-full text-sm">
            <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500">
              <tr><th class="px-3 py-2">S/N</th><th class="px-3 py-2">Date</th><th class="px-3 py-2">Bill Ref ID</th><th class="px-3 py-2">Type</th><th class="px-3 py-2">File No.</th><th class="px-3 py-2">Method</th><th class="px-3 py-2 text-right">Amount</th><th class="px-3 py-2">Receipt</th></tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
              @forelse ($recent as $p)
                <tr>
                  <td class="px-3 py-2 text-gray-500">{{ $loop->iteration }}</td>
                  <td class="px-3 py-2 whitespace-nowrap">{{ $p->paid_at ? \Illuminate\Support\Carbon::parse($p->paid_at)->format('d M Y') : '—' }}</td>
                  <td class="px-3 py-2 font-mono whitespace-nowrap">{{ $p->bill_ref_id }}</td>
                  <td class="px-3 py-2">{{ $p->type_name }}</td>
                  <td class="px-3 py-2 font-mono whitespace-nowrap">{{ $p->file_number ?: '—' }}</td>
                  <td class="px-3 py-2">{{ \App\Http\Controllers\Revenue\DashboardController::methodLabel($p->method) }}</td>
                  <td class="px-3 py-2 text-right whitespace-nowrap">{{ $money($p->amount) }}</td>
                  <td class="px-3 py-2 whitespace-nowrap">
                    @if ($p->receipt_no)
                      <a href="{{ route('revenue.receipts.show', $p->receipt_no) }}" target="_blank" class="font-mono text-emerald-700 hover:underline">{{ $p->receipt_no }}</a>
                    @else
                      <span class="px-2 py-0.5 rounded-full text-xs font-semibold bg-orange-100 text-orange-800">Awaiting</span>
                    @endif
                  </td>
                </tr>
              @empty
                <tr><td colspan="8" class="px-3 py-8 text-center text-gray-400">No payments yet.</td></tr>
              @endforelse
            </tbody>
          </table>
        </div>
      </div>

      {{-- Bill Legacy --}}
      <div class="rounded-xl border border-gray-200 bg-white p-5">
        <div class="font-semibold text-gray-800 flex items-center gap-2 mb-4"><i data-lucide="archive" class="w-4 h-4 text-emerald-600"></i> Bill Legacy (File Indexing)</div>
        <dl class="space-y-3 text-sm">
          <div class="flex justify-between"><dt class="text-gray-500">Bills</dt><dd class="font-semibold">{{ number_format((int) $legacy->n) }}</dd></div>
          <div class="flex justify-between"><dt class="text-gray-500">With a receipt (paid)</dt><dd class="font-semibold">{{ number_format((int) $legacy->paid) }}</dd></div>
          <div class="flex justify-between"><dt class="text-gray-500">Without a receipt</dt><dd class="font-semibold">{{ number_format((int) $legacy->unpaid) }}</dd></div>
          <div class="flex justify-between"><dt class="text-gray-500">Amount as captured</dt><dd class="font-semibold">{{ $money($legacy->billed) }}</dd></div>
        </dl>
        <a href="{{ route('revenue.legacy.index') }}" class="mt-5 inline-flex items-center gap-1 text-sm font-semibold text-emerald-700 hover:underline">Open Bill Legacy <i data-lucide="arrow-right" class="w-4 h-4"></i></a>
      </div>
    </div>
  </div>
</div>
@endsection
