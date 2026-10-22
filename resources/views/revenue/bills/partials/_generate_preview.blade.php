{{-- Generate-bill modal body, loaded after a file is picked (BillMasterController::lookup). --}}
@php $money = fn ($v) => '₦' . number_format((float) $v, 2); @endphp

@if (! $file)
  <div class="text-center text-gray-400 py-10">
    <i data-lucide="folder-search" class="w-10 h-10 mx-auto mb-2"></i>
    <div>Select the file number to start.</div>
  </div>
@else
  <input type="hidden" name="file_number" value="{{ $file['file_number'] }}">

  {{-- File details from the records --}}
  <div class="rounded-lg border border-gray-200 bg-gray-50 p-4 mb-4">
    <div class="flex items-center justify-between mb-3">
      <div class="flex items-center gap-2">
        <i data-lucide="folder" class="w-4 h-4 text-emerald-600"></i>
        <span class="font-mono font-semibold">{{ $file['file_number'] }}</span>
      </div>
      @if ($file['indexed'])
        <span class="px-2 py-0.5 rounded-full text-xs font-semibold bg-green-100 text-green-800">Indexed</span>
      @else
        <span class="px-2 py-0.5 rounded-full text-xs font-semibold bg-amber-100 text-amber-800">Not indexed: enter the payer details</span>
      @endif
    </div>
    <div class="grid grid-cols-2 md:grid-cols-4 gap-3 text-sm">
      @foreach (['Plot No.' => 'plot_no', 'District' => 'district', 'LGA' => 'lga', 'Location' => 'location'] as $label => $key)
        <div><div class="text-xs uppercase text-gray-500">{{ $label }}</div><div class="font-medium">{{ $file[$key] ?: '—' }}</div></div>
      @endforeach
    </div>
  </div>

  @if (! $moduleRaised)
  {{-- Payer --}}
  <div class="grid grid-cols-1 md:grid-cols-2 gap-3 mb-4">
    <label class="text-sm"><span class="block text-xs font-semibold text-gray-600 mb-1">Payer name</span>
      <input type="text" name="payer_name" value="{{ $file['payer_name'] }}" maxlength="255" class="w-full border border-gray-300 rounded-lg px-3 py-2"></label>
    <label class="text-sm"><span class="block text-xs font-semibold text-gray-600 mb-1">Phone (SMS notices go here)</span>
      <input type="tel" name="payer_phone" value="{{ $file['payer_phone'] ?? '' }}" maxlength="30" class="w-full border border-gray-300 rounded-lg px-3 py-2">
      @if (! empty($file['phone_source']))
        <span class="block text-xs text-gray-500 mt-1">Found in {{ $file['phone_source'] }}. Check it with the payer.</span>
      @endif
    </label>
    <label class="text-sm"><span class="block text-xs font-semibold text-gray-600 mb-1">Email (needed for Paystack)</span>
      <input type="email" name="payer_email" maxlength="150" class="w-full border border-gray-300 rounded-lg px-3 py-2"></label>
    <label class="text-sm"><span class="block text-xs font-semibold text-gray-600 mb-1">Address</span>
      <input type="text" name="payer_address" maxlength="500" class="w-full border border-gray-300 rounded-lg px-3 py-2"></label>
  </div>

  {{-- Kind (Initial Bill: Primary / PuA / SuA) --}}
  @if ($kinds->isNotEmpty())
    <label class="block text-sm mb-4"><span class="block text-xs font-semibold text-gray-600 mb-1">Which {{ $billType->name }}?</span>
      <select name="kind" data-rev-kind class="w-full md:w-64 border border-gray-300 rounded-lg px-3 py-2" required>
        <option value="">Choose…</option>
        @foreach ($kinds as $k)<option value="{{ $k->code }}" @selected(optional($kind)->code === $k->code)>{{ $k->name }}</option>@endforeach
      </select></label>
  @endif

  @endif

  {{-- Existing bills for this file --}}
  @if ($existing->isNotEmpty())
    <div class="mb-4">
      <div class="text-xs font-semibold uppercase text-gray-500 mb-1">Bills already on this file</div>
      <div class="rounded-lg border border-gray-200 overflow-x-auto">
        <table class="min-w-full text-sm">
          <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500">
            <tr><th class="px-3 py-1.5">S/N</th><th class="px-3 py-1.5">Bill Ref ID</th><th class="px-3 py-1.5">Type</th><th class="px-3 py-1.5 text-right">{{ $moduleRaised ? 'Total' : 'Amount' }}</th><th class="px-3 py-1.5">Status</th><th class="px-3 py-1.5">Date</th><th class="px-3 py-1.5"></th></tr>
          </thead>
          <tbody class="divide-y divide-gray-100">
            @foreach ($existing as $old)
              <tr>
                <td class="px-3 py-1.5 text-gray-500">{{ $loop->iteration }}</td>
                <td class="px-3 py-1.5 font-mono">{{ $old->bill_ref_id }}</td>
                <td class="px-3 py-1.5">{{ optional($old->type)->name }}</td>
                <td class="px-3 py-1.5 text-right">{{ $money($old->amount) }}</td>
                <td class="px-3 py-1.5">@include('revenue.partials._status_pill', ['status' => $old->displayStatus()])</td>
                <td class="px-3 py-1.5 whitespace-nowrap">{{ optional($old->bill_date)->format('d M Y') }}</td>
                @php
                  $revCanPayNow = $canPay && $old->isGenerated() && in_array($old->status, ['unpaid', 'part_paid'], true);
                @endphp
                  <td class="px-3 py-1.5 whitespace-nowrap text-right">
                    <button type="button" class="inline-flex items-center gap-1 px-2 py-1 rounded border border-gray-300 text-xs font-semibold hover:bg-gray-50"
                            @click="genOpen = false; openView(@js($old->bill_ref_id))"><i data-lucide="eye" class="w-3.5 h-3.5"></i> View</button>
                    @if ($old->isGenerated())
                      @if ($canPrint)
                        <a href="{{ route('revenue.bills.invoice', $old->bill_ref_id) }}" target="_blank"
                           class="inline-flex items-center gap-1 px-2 py-1 rounded bg-emerald-600 text-white text-xs font-semibold hover:bg-emerald-700"><i data-lucide="printer" class="w-3.5 h-3.5"></i> Invoice</a>
                      @endif
                    @else
                      @if ($canGenerate && $old->status !== 'cancelled')
                        <button type="button" @click="generateBill(@js($old->bill_ref_id))"
                                class="inline-flex items-center gap-1 px-2 py-1 rounded bg-emerald-600 text-white text-xs font-semibold hover:bg-emerald-700"><i data-lucide="file-check" class="w-3.5 h-3.5"></i> Generate</button>
                      @endif
                      @if ($canPrint)
                        <span title="Generate the bill first" class="inline-flex items-center gap-1 px-2 py-1 rounded border border-gray-200 text-gray-400 text-xs font-semibold cursor-not-allowed"><i data-lucide="printer" class="w-3.5 h-3.5"></i> Invoice</span>
                      @endif
                    @endif
                    @if ($canPay && ! in_array($old->status, ['paid', 'cancelled'], true))
                      @if ($revCanPayNow)
                        <button type="button" @click="genOpen = false; openView(@js($old->bill_ref_id), 'payments')"
                                class="inline-flex items-center gap-1 px-2 py-1 rounded bg-blue-600 text-white text-xs font-semibold hover:bg-blue-700"><i data-lucide="credit-card" class="w-3.5 h-3.5"></i> Pay</button>
                      @else
                        <span title="Generate the bill first" class="inline-flex items-center gap-1 px-2 py-1 rounded border border-gray-200 text-gray-400 text-xs font-semibold cursor-not-allowed"><i data-lucide="credit-card" class="w-3.5 h-3.5"></i> Pay</span>
                      @endif
                    @endif
                  </td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    </div>
  @endif

  {{-- Items --}}
  @if ($moduleRaised)
    @if ($existing->isEmpty())
      <div class="rounded-lg border border-gray-200 bg-gray-50 text-gray-600 text-sm p-3 flex gap-2">
        <i data-lucide="info" class="w-4 h-4 mt-0.5 shrink-0"></i>
        <div>No {{ $billType->name }} has been raised for this file yet. Its items are entered on the form in {{ $moduleRaised }}; the bill comes here with its total.</div>
      </div>
    @endif
  @elseif ($error)
    <div class="rounded-lg border border-amber-200 bg-amber-50 text-amber-800 text-sm p-3 flex gap-2">
      <i data-lucide="alert-triangle" class="w-4 h-4 mt-0.5 shrink-0"></i><div>{{ $error }}</div>
    </div>
  @elseif ($spec)
    <span data-rev-ready hidden></span>
    <div class="text-xs font-semibold uppercase text-gray-500 mb-1">Bill items</div>
    <div class="rounded-lg border border-gray-200 overflow-hidden mb-2">
      <table class="min-w-full text-sm">
        <tbody class="divide-y divide-gray-100">
          @foreach ($spec['items'] as $item)
            <tr>
              <td class="px-3 py-2 text-gray-500 w-10">{{ $loop->iteration }}</td>
              <td class="px-3 py-2">{{ $item['name'] }}</td>
              <td class="px-3 py-2 text-right w-48">
                @if ($item['input'])
                  <input type="number" name="inputs[{{ $item['code'] }}]" min="0" step="0.01" required placeholder="Enter amount"
                         class="w-40 border border-gray-300 rounded-lg px-2 py-1 text-right">
                @else
                  {{ $money($item['amount']) }}
                @endif
              </td>
            </tr>
          @endforeach
        </tbody>
        <tfoot class="bg-emerald-50">
          <tr>
            <td></td>
            <td class="px-3 py-2 font-semibold">Total</td>
            <td class="px-3 py-2 text-right font-bold">{{ $priced ? $money($priced['total']) : 'Worked out on generate' }}</td>
          </tr>
        </tfoot>
      </table>
    </div>
    @if (trim($spec['formula']) !== '')
      <div class="text-xs text-gray-500">Formula: <span class="font-mono">{{ $spec['formula'] }}</span></div>
    @endif
  @elseif ($kinds->isNotEmpty())
    <div class="text-sm text-gray-500">Choose which {{ $billType->name }} to see its items.</div>
  @endif
@endif
