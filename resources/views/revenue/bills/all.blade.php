@extends('layouts.app')

@section('content')
<div class="flex-1 overflow-auto bg-gray-50"
     x-data="revBills(@js(['lookupUrl' => '', 'storeUrl' => '', 'showUrl' => url('revenue/bill/__REF__'), 'generateUrl' => url('revenue/bill/__REF__/generate'), 'kind' => '']))"
     @rev-view.window="openView($event.detail.ref, $event.detail.tab)"
     @rev-generate-bill.window="generateBill($event.detail.ref)">
  @include('admin.header', ['PageTitle' => 'Bill Master', 'PageDescription' => 'The Bill Master register: every system bill, every type'])

  <div class="p-6">
    <div class="flex items-center gap-2 text-sm text-gray-500 mb-4">
      <i data-lucide="banknote" class="w-4 h-4 text-emerald-600"></i>
      <span>KLAES REV-M</span>
      <i data-lucide="chevron-right" class="w-3.5 h-3.5"></i>
      <span>Billing · Automated Billing</span>
      <i data-lucide="chevron-right" class="w-3.5 h-3.5"></i>
      <span class="font-semibold text-gray-700">Bill Master</span>
      @if ($can['onTheFly'])
        <button type="button" onclick="window.dispatchEvent(new CustomEvent('rev-on-the-fly'))"
                class="ml-auto inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-emerald-600 text-white text-sm font-semibold hover:bg-emerald-700">
          <i data-lucide="zap" class="w-4 h-4"></i> On-the-fly bill
        </button>
      @endif
    </div>

    @include('revenue.partials._stat_cards')

    <form method="GET" class="flex flex-wrap items-center gap-2 mb-4">
      <select name="type" class="border border-gray-300 rounded-lg px-3 py-2 text-sm">
        <option value="">Every bill type</option>
        @foreach ($types as $t)
          <option value="{{ $t->code }}" @selected($filters['type'] === $t->code)>{{ $t->parent_code ? '— ' : '' }}{{ $t->name }}</option>
        @endforeach
      </select>
      <select name="status" class="border border-gray-300 rounded-lg px-3 py-2 text-sm">
        <option value="">Every status</option>
        @foreach (\App\Http\Controllers\Revenue\BillMasterController::filterStatuses() as $value => $label)
          <option value="{{ $value }}" @selected($filters['status'] === $value)>{{ $label }}</option>
        @endforeach
      </select>
      <select name="raised" class="border border-gray-300 rounded-lg px-3 py-2 text-sm">
        @foreach (['' => 'However raised', 'on_the_fly' => 'On-the-fly bills', 'recurring' => 'Recurring (on a payment cycle)', 'cycle' => 'Next-cycle bills'] as $value => $label)
          <option value="{{ $value }}" @selected($filters['raised'] === $value)>{{ $label }}</option>
        @endforeach
      </select>
      <input type="text" name="q" value="{{ $filters['q'] }}" placeholder="Bill Ref ID, Transaction ID, file no., payer, plot"
             class="border border-gray-300 rounded-lg px-3 py-2 text-sm w-80">
      <button class="px-3 py-2 rounded-lg bg-gray-800 text-white text-sm font-semibold">Search</button>
      @if ($filters['q'] !== '' || $filters['status'] !== '' || $filters['type'] !== '' || $filters['raised'] !== '')
        <a href="{{ $indexUrl }}" class="px-3 py-2 text-sm text-gray-600">Clear</a>
      @endif
    </form>

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
            <th class="px-3 py-2 text-right">Amount</th>
            <th class="px-3 py-2 text-right">Paid</th>
            <th class="px-3 py-2">Status</th>
            <th class="px-3 py-2">Transaction ID</th>
            <th class="px-3 py-2">Bill Date</th>
            <th class="px-3 py-2 text-right">Action</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
          @forelse ($rows as $bill)
            @php
              $revRef = $bill->bill_ref_id;
              $revItems = [
                  ['View', 'eye', null, false, "window.dispatchEvent(new CustomEvent('rev-view', {detail: {ref: '{$revRef}'}}))"],
                  ['View application', 'folder-open', null, false, "window.dispatchEvent(new CustomEvent('rev-view', {detail: {ref: '{$revRef}', tab: 'application'}}))"],
              ];
              if ($bill->isGenerated()) {
                  if ($can['print']) {
                      $revItems[] = ['Print invoice', 'printer', route('revenue.bills.invoice', $revRef), true];
                  }
              } else {
                  if ($can['generate'] && $bill->status !== 'cancelled') {
                      $revItems[] = ['Generate bill', 'file-check', null, false, "window.dispatchEvent(new CustomEvent('rev-generate-bill', {detail: {ref: '{$revRef}'}}))"];
                  }
                  if ($can['print']) {
                      $revItems[] = ['Print invoice', 'printer', null, false, null, 'Generate the bill first'];
                  }
              }
            @endphp
            <tr class="hover:bg-gray-50">
              <td class="px-3 py-2 text-gray-500">{{ $rows->firstItem() + $loop->index }}</td>
              <td class="px-3 py-2 font-mono whitespace-nowrap">{{ $bill->bill_ref_id }}</td>
              <td class="px-3 py-2 whitespace-nowrap">
                {{ optional($bill->type)->name ?? $bill->bill_type_code }}
                @if ($bill->raised_via === 'on_the_fly')<span class="ml-1 px-1.5 py-0.5 rounded text-[10px] font-semibold bg-violet-100 text-violet-700">On-the-fly</span>@endif
                @if ($bill->payment_cycle)<span class="ml-1 px-1.5 py-0.5 rounded text-[10px] font-semibold bg-sky-100 text-sky-700" title="Next due {{ optional($bill->next_due_date)->format('d M Y') ?: '—' }}">{{ config('billing.cycles.' . $bill->payment_cycle . '.label') }}</span>@endif
              </td>
              <td class="px-3 py-2 font-mono whitespace-nowrap">{{ $bill->file_number ?: '—' }}</td>
              <td class="px-3 py-2">{{ $bill->payer_name ?: '—' }}</td>
              <td class="px-3 py-2">{{ $bill->plot_no ?: '—' }}</td>
              <td class="px-3 py-2">{{ $bill->district ?: '—' }}</td>
              <td class="px-3 py-2">{{ $bill->lga ?: '—' }}</td>
              <td class="px-3 py-2">{{ $bill->location ?: '—' }}</td>
              <td class="px-3 py-2 text-right whitespace-nowrap">₦{{ number_format((float) $bill->amount, 2) }}</td>
              <td class="px-3 py-2 text-right whitespace-nowrap">₦{{ number_format((float) $bill->amount_paid, 2) }}</td>
              <td class="px-3 py-2">@include('revenue.partials._status_pill', ['status' => $bill->displayStatus()])</td>
              <td class="px-3 py-2 font-mono whitespace-nowrap">{{ $bill->transaction_id ?: '—' }}</td>
              <td class="px-3 py-2 whitespace-nowrap">{{ optional($bill->bill_date)->format('d M Y') ?: '—' }}</td>
              <td class="px-3 py-2 text-right">@include('revenue.partials._row_actions', ['items' => $revItems])</td>
            </tr>
          @empty
            <tr>
              <td colspan="15" class="px-3 py-10 text-center text-gray-400">
                <i data-lucide="inbox" class="w-8 h-8 mx-auto mb-2"></i>
                <div>No bills match.</div>
              </td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>
    <div class="mt-4">{{ $rows->links() }}</div>
  </div>

  {{-- View bill modal --}}
  <div x-show="viewOpen" x-cloak class="fixed inset-0 z-[1000] flex items-center justify-center bg-black/50 p-4" @keydown.escape.window="viewOpen = false">
    <div @click.outside="viewOpen = false" class="bg-white rounded-xl shadow-2xl w-full max-w-3xl max-h-[90vh] flex flex-col overflow-hidden">
      <div class="px-6 py-4 bg-gradient-to-r from-emerald-600 to-emerald-700 text-white flex items-center justify-between">
        <div class="flex items-center gap-2 font-semibold"><i data-lucide="receipt" class="w-5 h-5"></i> Bill</div>
        <button type="button" @click="viewOpen = false" class="p-1 rounded hover:bg-white/20" aria-label="Close"><i data-lucide="x" class="w-5 h-5"></i></button>
      </div>
      <div class="px-6 py-4 overflow-y-auto flex-1" x-ref="viewBody" @submit.prevent="submitAjax($event.target)">
        <div class="text-center text-sm text-gray-500 py-10">Loading…</div>
      </div>
    </div>
  </div>

  {{-- On-the-fly bill: bill type + file + the type's Revenue Codes --}}
  @if ($can['onTheFly'])
  @include('revenue.bills.partials._on_the_fly_modal', ['otfTypes' => $onTheFlyTypes, 'otfTypeCodes' => $typeCodes, 'otfCycles' => $cycleTypes, 'otfAllTypes' => $types])

  {{-- The global file-number selector (inside the content section so its Select2 script loads after jQuery). --}}
  @include('components.global-fileno-modal')
  @endif

  @include('admin.footer')
</div>
@endsection

@section('footer-scripts')
@if ($can['onTheFly'])
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script src="{{ asset('js/global-fileno-modal.js') }}"></script>
@endif
<script src="{{ asset('js/revenue/bill-master.js') }}?v={{ is_file(public_path('js/revenue/bill-master.js')) ? filemtime(public_path('js/revenue/bill-master.js')) : 1 }}"></script>
@endsection
