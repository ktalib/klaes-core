@extends('layouts.app')

@section('content')
<div class="flex-1 overflow-auto bg-gray-50"
     x-data="revBills(@js([
         'lookupUrl' => route('revenue.bills.lookup', $billType->code),
         'storeUrl' => route('revenue.bills.store', $billType->code),
         'showUrl' => url('revenue/bill/__REF__'),
         'generateUrl' => url('revenue/bill/__REF__/generate'),
         'kind' => $sub !== '' && $sub !== $billType->code ? $sub : '',
     ]))"
     @rev-generate.window="openGenerate($event.detail && $event.detail.file)"
     @rev-view.window="openView($event.detail.ref, $event.detail.tab)"
     @rev-generate-bill.window="generateBill($event.detail.ref)">
  @include('admin.header', ['PageTitle' => $pageTitle, 'PageDescription' => $pageDescription])

  <div class="p-6">
    <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
      <div class="flex items-center gap-2 text-sm text-gray-500">
        <i data-lucide="banknote" class="w-4 h-4 text-emerald-600"></i>
        <span>KLAES REV-M</span>
        <i data-lucide="chevron-right" class="w-3.5 h-3.5"></i>
        <span>Billing · Automated Billing</span>
        <i data-lucide="chevron-right" class="w-3.5 h-3.5"></i>
        <span class="font-semibold text-gray-700">{{ $pageTitle }}</span>
      </div>
      <div class="flex items-center gap-2">
      @if ($can['create'] && $codeBilled)
        <button type="button" @click="window.dispatchEvent(new CustomEvent('rev-on-the-fly', {detail: {type: @js($billType->code)}}))"
                class="inline-flex items-center gap-2 px-3 py-2 rounded-lg bg-emerald-600 text-white text-sm font-semibold hover:bg-emerald-700">
          <i data-lucide="plus" class="w-4 h-4"></i> Generate bill
        </button>
      @elseif ($can['create'])
        <button type="button" @click="openGenerate()"
                class="inline-flex items-center gap-2 px-3 py-2 rounded-lg bg-emerald-600 text-white text-sm font-semibold hover:bg-emerald-700">
          <i data-lucide="plus" class="w-4 h-4"></i> Generate bill
        </button>
      @else
        <button type="button" disabled
                title="{{ $billType->code === 'land_use_charge' ? 'Land Use Charge billing is on hold' : (! $billType->is_active ? 'Switched off in Bill Settings' : 'You cannot generate bills here') }}"
                class="inline-flex items-center gap-2 px-3 py-2 rounded-lg bg-emerald-600 text-white text-sm font-semibold opacity-50 cursor-not-allowed">
          <i data-lucide="plus" class="w-4 h-4"></i> Generate bill
        </button>
      @endif
      </div>
    </div>

    @foreach (['success' => 'border-green-200 bg-green-50 text-green-800', 'error' => 'border-red-200 bg-red-50 text-red-800'] as $revFlash => $revTone)
      @if (session($revFlash))
        <div class="rounded-lg border {{ $revTone }} text-sm px-4 py-3 mb-4">{{ session($revFlash) }}</div>
      @endif
    @endforeach

    @include('revenue.partials._stat_cards')

    @if ($children->isNotEmpty())
      <div class="flex flex-wrap gap-2 mb-4">
        @foreach (collect([$billType])->merge($children) as $tab)
          @php $revActive = $tab->code === $billType->code ? $sub === '' || $sub === $billType->code : $sub === $tab->code; @endphp
          <a href="{{ $indexUrl }}{{ $tab->code === $billType->code ? '' : '?sub=' . $tab->code }}"
             class="px-3 py-1.5 rounded-full text-sm font-semibold border {{ $revActive ? 'bg-emerald-600 border-emerald-600 text-white' : 'bg-white border-gray-300 text-gray-600 hover:bg-gray-50' }}">
            {{ $tab->code === $billType->code ? 'All' : $tab->name }}
          </a>
        @endforeach
      </div>
    @endif

    <form method="GET" class="flex flex-wrap items-center gap-2 mb-4">
      @if ($sub !== '')<input type="hidden" name="sub" value="{{ $sub }}">@endif
      <select name="status" class="border border-gray-300 rounded-lg px-3 py-2 text-sm">
        <option value="">Every status</option>
        @foreach (\App\Http\Controllers\Revenue\BillMasterController::filterStatuses() as $value => $label)
          <option value="{{ $value }}" @selected($filters['status'] === $value)>{{ $label }}</option>
        @endforeach
      </select>
      <input type="text" name="q" value="{{ $filters['q'] }}" placeholder="Bill Ref ID, Transaction ID, file no., payer, plot"
             class="border border-gray-300 rounded-lg px-3 py-2 text-sm w-80">
      <button class="px-3 py-2 rounded-lg bg-gray-800 text-white text-sm font-semibold">Search</button>
      @if ($filters['q'] !== '' || $filters['status'] !== '')
        <a href="{{ $indexUrl }}{{ $sub !== '' ? '?sub=' . $sub : '' }}" class="px-3 py-2 text-sm text-gray-600">Clear</a>
      @endif
    </form>

    <div class="rounded-xl border border-gray-200 bg-white overflow-x-auto">
      <table class="min-w-full text-sm">
        <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500">
          <tr>
            <th class="px-3 py-2">S/N</th>
            <th class="px-3 py-2">Bill Ref ID</th>
            @if ($children->isNotEmpty())<th class="px-3 py-2">Type</th>@endif
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
              if ($can['create'] && $bill->file_number && ! $moduleRaised) {
                  $revItems[] = $codeBilled
                      ? ['Generate bill', 'file-plus', null, false, 'window.dispatchEvent(new CustomEvent(\'rev-on-the-fly\', {detail: {type: ' . json_encode($billType->code) . ', file: ' . json_encode($bill->file_number) . '}}))']
                      : ['Generate bill', 'file-plus', null, false, 'window.dispatchEvent(new CustomEvent(\'rev-generate\', {detail: {file: ' . json_encode($bill->file_number) . '}}))'];
              }
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
              @if ($children->isNotEmpty())<td class="px-3 py-2 whitespace-nowrap">{{ optional($bill->type)->name ?? $bill->bill_type_code }}</td>@endif
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
              <td colspan="16" class="px-3 py-10 text-center text-gray-400">
                <i data-lucide="inbox" class="w-8 h-8 mx-auto mb-2"></i>
                <div>No {{ $pageTitle }} records yet.</div>
              </td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>
    <div class="mt-4">{{ $rows->links() }}</div>
  </div>

  {{-- Generate bill modal --}}
  <div x-show="genOpen" x-cloak class="fixed inset-0 z-[1000] flex items-center justify-center bg-black/50 p-4" @keydown.escape.window="genOpen = false">
    <form x-ref="genForm" @submit.prevent="submitGenerate()"
          class="bg-white rounded-xl shadow-2xl w-full max-w-3xl max-h-[90vh] flex flex-col overflow-hidden">
      <div class="px-6 py-4 bg-gradient-to-r from-emerald-600 to-emerald-700 text-white flex items-center justify-between">
        <div class="flex items-center gap-2 font-semibold"><i data-lucide="file-plus" class="w-5 h-5"></i> Generate {{ $billType->name }}</div>
        <button type="button" @click="genOpen = false" class="p-1 rounded hover:bg-white/20" aria-label="Close"><i data-lucide="x" class="w-5 h-5"></i></button>
      </div>
      <div class="px-6 py-4 overflow-y-auto flex-1">
        <div class="flex items-center gap-2 mb-4">
          <div class="flex-1 border border-gray-300 rounded-lg px-3 py-2 bg-gray-50 font-mono text-sm" x-text="fileNumber || 'No file selected'"></div>
          <button type="button" @click="pickFile()" class="inline-flex items-center gap-2 px-3 py-2 rounded-lg border border-emerald-600 text-emerald-700 text-sm font-semibold hover:bg-emerald-50">
            <i data-lucide="folder-search" class="w-4 h-4"></i> <span x-text="fileNumber ? 'Change file' : 'Select file number'"></span>
          </button>
        </div>
        <div x-ref="genBody" @change="if ($event.target.matches('[data-rev-kind]')) { kind = $event.target.value; loadPreview(); }">
          <div class="text-center text-gray-400 py-10"><i data-lucide="folder-search" class="w-10 h-10 mx-auto mb-2"></i><div>Select the file number to start.</div></div>
        </div>
        <div x-show="loading" class="text-center text-sm text-gray-500 py-4">Loading…</div>
      </div>
      <div class="px-6 py-3 border-t border-gray-100 flex justify-end gap-2 bg-gray-50">
        <button type="button" @click="genOpen = false" class="px-4 py-2 rounded-lg border border-gray-300 text-sm font-semibold">Cancel</button>
        @if (! $moduleRaised)
        <button type="submit" :disabled="!ready || busy"
                class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-emerald-600 text-white text-sm font-semibold disabled:opacity-50">
          <i data-lucide="check" class="w-4 h-4"></i> <span x-text="busy ? 'Generating…' : 'Generate bill'"></span>
        </button>
        @endif
      </div>
    </form>
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

  {{-- Ground Rent / Re-grant: generated from the type's own Revenue Codes --}}
  @if ($codeBilled && $can['create'])
    @include('revenue.bills.partials._on_the_fly_modal', ['otfFixedType' => $billType->code])
  @endif

  {{-- The global file-number selector (inside the content section so its Select2 script loads after jQuery). --}}
  @include('components.global-fileno-modal')

  @include('admin.footer')
</div>
@endsection

@section('footer-scripts')
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script src="{{ asset('js/global-fileno-modal.js') }}"></script>
<script src="{{ asset('js/revenue/bill-master.js') }}?v={{ is_file(public_path('js/revenue/bill-master.js')) ? filemtime(public_path('js/revenue/bill-master.js')) : 1 }}"></script>
@endsection
