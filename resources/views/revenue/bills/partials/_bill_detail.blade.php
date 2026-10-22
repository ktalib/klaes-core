{{-- View-bill modal body (BillMasterController::show): Bill · Payments · Notifications · Application (file record). --}}
@php
  $money = fn ($v) => '₦' . number_format((float) $v, 2);
  $firstSelectable = collect($methods)->filter(fn ($m) => $m['selectable'])->keys()->first();
@endphp

<div data-column-picker="off" x-data="{ tab: @js(request('tab', 'bill')), method: @js($firstSelectable) }">
  {{-- Header --}}
  <div class="flex flex-wrap items-start justify-between gap-3 mb-4">
    <div>
      <div class="font-mono text-lg font-bold">{{ $bill->bill_ref_id }}</div>
      <div class="text-sm text-gray-500">{{ optional($bill->type)->name }}@if ($bill->type && $bill->type->revenueCode()) · <span class="font-mono" title="Revenue Code">{{ $bill->type->revenueCode() }}</span>@endif · {{ optional($bill->bill_date)->format('d M Y') }}
        @if ($bill->raised_via === 'on_the_fly')<span class="ml-1 px-1.5 py-0.5 rounded text-[10px] font-semibold bg-violet-100 text-violet-700">On-the-fly</span>@endif
        @if ($bill->raised_via === 'cycle')<span class="ml-1 px-1.5 py-0.5 rounded text-[10px] font-semibold bg-sky-100 text-sky-700">Next cycle</span>@endif
      </div>
    </div>
    <div class="text-right">
      @include('revenue.partials._status_pill', ['status' => $bill->displayStatus()])
      <div class="text-xl font-bold mt-1">{{ $money($bill->amount) }}</div>
      @if ($outstanding > 0 && (float) $bill->amount_paid > 0)
        <div class="text-xs text-gray-500">Paid {{ $money($bill->amount_paid) }} · Outstanding {{ $money($outstanding) }}</div>
      @endif
    </div>
  </div>

  {{-- Tabs --}}
  <div class="flex gap-1 border-b border-gray-200 mb-4">
    @foreach (['bill' => ['Bill', 'receipt'], 'payments' => ['Payments', 'credit-card'], 'notifications' => ['Notifications', 'message-square'], 'application' => ['Application', 'folder-open']] as $key => [$label, $icon])
      <button type="button" @click="tab = @js($key)"
              :class="tab === @js($key) ? 'border-emerald-600 text-emerald-700' : 'border-transparent text-gray-500 hover:text-gray-700'"
              class="inline-flex items-center gap-1.5 px-3 py-2 text-sm font-semibold border-b-2 -mb-px">
        <i data-lucide="{{ $icon }}" class="w-4 h-4"></i> {{ $label }}
        @if ($key === 'payments' && $bill->payments->isNotEmpty())<span class="text-xs bg-gray-100 rounded-full px-1.5">{{ $bill->payments->count() }}</span>@endif
        @if ($key === 'notifications' && $smsLog->isNotEmpty())<span class="text-xs bg-gray-100 rounded-full px-1.5">{{ $smsLog->count() }}</span>@endif
      </button>
    @endforeach
  </div>

  {{-- Bill --}}
  <div x-show="tab === 'bill'">
    <div class="grid grid-cols-2 md:grid-cols-3 gap-3 text-sm mb-4">
      <div><div class="text-xs uppercase text-gray-500">File No.</div><div class="font-mono">{{ $bill->file_number ?: '—' }}</div></div>
      <div><div class="text-xs uppercase text-gray-500">Payer</div><div>{{ $bill->payer_name ?: '—' }}</div></div>
      <div><div class="text-xs uppercase text-gray-500">Phone</div><div>{{ $bill->payer_phone ?: '—' }}</div></div>
      <div><div class="text-xs uppercase text-gray-500">Plot No.</div><div>{{ $bill->plot_no ?: '—' }}</div></div>
      <div><div class="text-xs uppercase text-gray-500">District</div><div>{{ $bill->district ?: '—' }}</div></div>
      <div><div class="text-xs uppercase text-gray-500">LGA</div><div>{{ $bill->lga ?: '—' }}</div></div>
      <div class="col-span-2 md:col-span-3"><div class="text-xs uppercase text-gray-500">Location</div><div>{{ $bill->location ?: '—' }}</div></div>
    </div>

    {{-- Payment cycle (recurring bill types) --}}
    @if ($bill->payment_cycle)
      <div class="rounded-lg border border-sky-200 bg-sky-50/60 p-3 mb-4 grid grid-cols-2 md:grid-cols-4 gap-3 text-sm">
        <div><div class="text-xs uppercase text-gray-500">Payment cycle</div><div class="font-semibold">{{ config('billing.cycles.' . $bill->payment_cycle . '.label', $bill->payment_cycle) }}</div></div>
        <div><div class="text-xs uppercase text-gray-500">This period</div><div>{{ optional($bill->period_start)->format('d M Y') }} – {{ optional($bill->period_end)->format('d M Y') }}</div></div>
        <div><div class="text-xs uppercase text-gray-500">Next payment due</div>
          <div class="{{ $bill->next_due_date && $bill->next_due_date->isPast() ? 'text-red-700 font-semibold' : '' }}">{{ optional($bill->next_due_date)->format('d M Y') ?: ($nextCycle ? 'Next cycle raised' : '—') }}</div></div>
        <div><div class="text-xs uppercase text-gray-500">Cycle bills</div>
          <div class="font-mono text-xs space-y-0.5">
            @if ($bill->previous_bill_ref)<div>Previous: <button type="button" class="text-emerald-700 underline" @click="openView(@js($bill->previous_bill_ref))">{{ $bill->previous_bill_ref }}</button></div>@endif
            @if ($nextCycle)<div>Next: <button type="button" class="text-emerald-700 underline" @click="openView(@js($nextCycle))">{{ $nextCycle }}</button></div>@endif
            @if (! $bill->previous_bill_ref && ! $nextCycle)<div class="text-gray-400">First cycle</div>@endif
          </div>
        </div>
      </div>
    @endif

    <div class="rounded-lg border border-gray-200 overflow-hidden">
      <table class="min-w-full text-sm">
        <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500">
          <tr><th class="px-3 py-2">S/N</th><th class="px-3 py-2">Code</th><th class="px-3 py-2">Item</th><th class="px-3 py-2 text-right">Amount</th></tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
          @forelse ($bill->items as $item)
            <tr>
              <td class="px-3 py-2 text-gray-500">{{ $loop->iteration }}</td>
              <td class="px-3 py-2 font-mono text-xs text-gray-600">{{ $item->item_code ?: '—' }}</td>
              <td class="px-3 py-2">{{ $item->name }}
                @if ((float) $item->quantity != 1.0 && (float) $item->quantity > 0)<span class="block text-xs text-gray-500">{{ rtrim(rtrim(number_format((float) $item->quantity, 4), '0'), '.') }} × {{ $money($item->rate) }}</span>@endif
              </td>
              <td class="px-3 py-2 text-right">{{ $money($item->amount) }}</td>
            </tr>
          @empty
            <tr><td colspan="4" class="px-3 py-4 text-center text-gray-400">No items on this bill.</td></tr>
          @endforelse
        </tbody>
        <tfoot class="bg-emerald-50"><tr><td></td><td></td><td class="px-3 py-2 font-semibold">Total</td><td class="px-3 py-2 text-right font-bold">{{ $money($bill->amount) }}</td></tr></tfoot>
      </table>
    </div>
  </div>

  {{-- Payments --}}
  <div x-show="tab === 'payments'" x-cloak>
    <div class="rounded-lg border border-gray-200 overflow-x-auto mb-4">
      <table class="min-w-full text-sm">
        <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500">
          <tr><th class="px-3 py-2">S/N</th><th class="px-3 py-2">Method</th><th class="px-3 py-2">Transaction ID</th><th class="px-3 py-2 text-right">Amount</th><th class="px-3 py-2">Status</th><th class="px-3 py-2">Reconciled</th><th class="px-3 py-2">Date</th></tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
          @forelse ($bill->payments as $p)
            <tr>
              <td class="px-3 py-2 text-gray-500">{{ $loop->iteration }}</td>
              <td class="px-3 py-2">{{ $p->methodLabel() }}</td>
              <td class="px-3 py-2 font-mono">{{ $p->transaction_id ?: '—' }}</td>
              <td class="px-3 py-2 text-right">{{ $money($p->amount) }}</td>
              <td class="px-3 py-2">
                <span class="px-2 py-0.5 rounded-full text-xs font-semibold {{ ['successful' => 'bg-green-100 text-green-800', 'pending' => 'bg-amber-100 text-amber-800'][$p->status] ?? 'bg-red-100 text-red-700' }}">{{ ucfirst($p->status) }}</span>
              </td>
              <td class="px-3 py-2">
                @if ($p->status === 'successful')
                  @include('revenue.partials._recon_pill', ['recon' => $p->reconciliation()])
                @else
                  <span class="text-gray-400">—</span>
                @endif
              </td>
              <td class="px-3 py-2 whitespace-nowrap">{{ optional($p->paid_at ?? $p->created_at)->format('d M Y H:i') }}</td>
            </tr>
          @empty
            <tr><td colspan="7" class="px-3 py-4 text-center text-gray-400">No payments yet.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>

    @if ($canPay && $outstanding > 0 && $bill->status !== 'cancelled' && ! $bill->isGenerated())
      <div class="rounded-lg border border-gray-200 bg-gray-50 text-gray-600 text-sm p-3">This bill is Pending. Generate it first; it can be paid once it has been generated.</div>
    @elseif ($canPay && $outstanding > 0 && $bill->status !== 'cancelled')
      <form method="POST" action="{{ route('revenue.bills.pay', $bill->bill_ref_id) }}" data-rev-ajax class="rounded-lg border border-emerald-200 bg-emerald-50/40 p-4 space-y-3">
        @csrf
        <div class="font-semibold text-sm flex items-center gap-2"><i data-lucide="wallet" class="w-4 h-4 text-emerald-600"></i> Pay</div>
        <div class="flex flex-wrap gap-2">
          @foreach ($methods as $key => $m)
            <label class="flex items-center gap-2 border rounded-lg px-3 py-2 text-sm {{ $m['selectable'] ? 'bg-white border-gray-300 cursor-pointer' : 'bg-gray-50 border-gray-200 text-gray-400 cursor-not-allowed' }}"
                   title="{{ $m['note'] ?? '' }}">
              <input type="radio" name="method" value="{{ $key }}" x-model="method" @disabled(! $m['selectable'])>
              <span class="font-semibold">{{ $m['label'] }}</span>
              @unless ($m['selectable'])<span class="text-xs">(not available yet)</span>@endunless
            </label>
          @endforeach
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
          <label class="text-sm"><span class="block text-xs font-semibold text-gray-600 mb-1">Amount (outstanding {{ $money($outstanding) }})</span>
            <input type="number" name="amount" min="0.01" step="0.01" max="{{ $outstanding }}" value="{{ number_format($outstanding, 2, '.', '') }}" required class="w-full border border-gray-300 rounded-lg px-3 py-2"></label>
          <label class="text-sm" x-show="method === 'pos'"><span class="block text-xs font-semibold text-gray-600 mb-1">POS Transaction ID</span>
            <input type="text" name="transaction_id" maxlength="100" :required="method === 'pos'" placeholder="From the POS slip" class="w-full border border-gray-300 rounded-lg px-3 py-2 font-mono"></label>
          <label class="text-sm" x-show="method === 'paystack'" x-cloak><span class="block text-xs font-semibold text-gray-600 mb-1">Payer email</span>
            <input type="email" name="payer_email" maxlength="150" value="{{ $bill->payer_email }}" :required="method === 'paystack'" class="w-full border border-gray-300 rounded-lg px-3 py-2"></label>
        </div>
        <div class="text-xs text-gray-500" x-show="method === 'pos'">Paid at a POS terminal using Bill Ref ID <span class="font-mono">{{ $bill->bill_ref_id }}</span>.</div>
        <div class="text-xs text-gray-500" x-show="method === 'paystack'" x-cloak>Opens Paystack's checkout; the payment is confirmed when Paystack returns.</div>
        <div class="flex justify-end">
          <button type="submit" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-emerald-600 text-white text-sm font-semibold hover:bg-emerald-700" :disabled="!method">
            <i data-lucide="check" class="w-4 h-4"></i> <span x-text="method === 'paystack' ? 'Pay with Paystack' : 'Pay'">Pay</span>
          </button>
        </div>
      </form>
    @elseif ($outstanding <= 0)
      <div class="rounded-lg border border-green-200 bg-green-50 text-green-800 text-sm p-3">This bill is fully paid. The official receipt is generated under Receipting.</div>
    @endif
  </div>

  {{-- Notifications: the SMS sent for this bill and what the gateway said --}}
  <div x-show="tab === 'notifications'" x-cloak>
    @php
      $smsLabels = ['bill_generated' => 'Bill generated', 'bill_payment_received' => 'Payment received', 'bill_payment_due' => 'Next payment due'];
      $smsTone = ['sent' => 'bg-green-100 text-green-800', 'pending' => 'bg-amber-100 text-amber-800', 'skipped' => 'bg-gray-100 text-gray-700', 'failed' => 'bg-red-100 text-red-700'];
    @endphp
    <div class="rounded-lg border border-gray-200 overflow-x-auto mb-4">
      <table class="min-w-full text-sm">
        <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500">
          <tr><th class="px-3 py-2">S/N</th><th class="px-3 py-2">SMS</th><th class="px-3 py-2">Phone</th><th class="px-3 py-2">Status</th><th class="px-3 py-2">Response</th><th class="px-3 py-2">When</th></tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
          @forelse ($smsLog as $sms)
            <tr class="align-top">
              <td class="px-3 py-2 text-gray-500">{{ $loop->iteration }}</td>
              <td class="px-3 py-2">{{ $smsLabels[$sms->message_key] ?? $sms->message_key }}
                @if ($sms->message)<span class="block text-xs text-gray-500 max-w-xs">{{ \Illuminate\Support\Str::limit($sms->message, 140) }}</span>@endif
              </td>
              <td class="px-3 py-2 font-mono whitespace-nowrap">{{ $sms->phone ?: '—' }}</td>
              <td class="px-3 py-2"><span class="px-2 py-0.5 rounded-full text-xs font-semibold {{ $smsTone[$sms->status] ?? 'bg-gray-100 text-gray-700' }}">{{ ucfirst($sms->status) }}</span></td>
              <td class="px-3 py-2 text-xs text-gray-600 max-w-xs">
                @if ($sms->status === 'sent')
                  Accepted by the gateway{{ $sms->gateway_code ? ' (code ' . $sms->gateway_code . ')' : '' }}.
                @else
                  {{ $sms->failure_reason ?: '—' }}{{ $sms->gateway_code ? ' (code ' . $sms->gateway_code . ')' : '' }}
                @endif
              </td>
              <td class="px-3 py-2 whitespace-nowrap text-xs">{{ optional($sms->created_at)->format('d M Y H:i') }}</td>
            </tr>
          @empty
            <tr><td colspan="6" class="px-3 py-4 text-center text-gray-400">No SMS for this bill yet. SMS go out when the bill is generated, when a payment is recorded, and before the next payment of a recurring bill is due, once the messages are switched on in the SMS Control Centre.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>

    @if ($canSms && $bill->isGenerated() && $bill->status !== 'cancelled')
      <form method="POST" action="{{ route('revenue.bills.sms', $bill->bill_ref_id) }}" data-rev-ajax class="rounded-lg border border-gray-200 bg-gray-50 p-4 grid grid-cols-1 md:grid-cols-3 gap-3 items-end">
        @csrf
        <label class="text-sm"><span class="block text-xs font-semibold text-gray-600 mb-1">Send SMS again</span>
          <select name="message" class="w-full border border-gray-300 rounded-lg px-3 py-2">
            <option value="generated">Bill generated</option>
            @if ((float) $bill->amount_paid > 0)<option value="payment">Payment received (latest)</option>@endif
            @if ($bill->next_due_date)<option value="due">Next payment due</option>@endif
          </select></label>
        <label class="text-sm"><span class="block text-xs font-semibold text-gray-600 mb-1">Payer phone</span>
          <input type="tel" name="payer_phone" value="{{ $bill->payer_phone }}" maxlength="30" class="w-full border border-gray-300 rounded-lg px-3 py-2"></label>
        <button type="submit" class="inline-flex justify-center items-center gap-2 px-4 py-2 rounded-lg bg-emerald-600 text-white text-sm font-semibold hover:bg-emerald-700">
          <i data-lucide="send" class="w-4 h-4"></i> Send SMS
        </button>
      </form>
    @endif
  </div>

  {{-- Application: the file's record --}}
  <div x-show="tab === 'application'" x-cloak>
    @if ($file && $file['indexed'])
      <div class="grid grid-cols-2 md:grid-cols-3 gap-3 text-sm">
        <div><div class="text-xs uppercase text-gray-500">File No.</div><div class="font-mono">{{ $file['file_number'] }}</div></div>
        <div class="col-span-2"><div class="text-xs uppercase text-gray-500">File title</div><div>{{ $file['payer_name'] ?: '—' }}</div></div>
        <div><div class="text-xs uppercase text-gray-500">Plot No.</div><div>{{ $file['plot_no'] ?: '—' }}</div></div>
        <div><div class="text-xs uppercase text-gray-500">District</div><div>{{ $file['district'] ?: '—' }}</div></div>
        <div><div class="text-xs uppercase text-gray-500">LGA</div><div>{{ $file['lga'] ?: '—' }}</div></div>
        <div><div class="text-xs uppercase text-gray-500">Location</div><div>{{ $file['location'] ?: '—' }}</div></div>
        <div><div class="text-xs uppercase text-gray-500">Plot size</div><div>{{ $file['plot_size'] ?: '—' }}</div></div>
        <div><div class="text-xs uppercase text-gray-500">Phone on record</div><div>{{ $file['payer_phone'] ?? '—' }}@if (! empty($file['phone_source']))<span class="block text-xs text-gray-500">from {{ $file['phone_source'] }}</span>@endif</div></div>
      </div>
    @else
      <div class="text-sm text-gray-500">This file is not indexed, so there is no application record to show.</div>
    @endif
    @if ($bill->source_table)
      <div class="mt-3 text-xs text-gray-500">Source: <span class="font-mono">{{ $bill->source_table }} #{{ $bill->source_id }}</span>@if ($bill->legacy_ref) · old ref <span class="font-mono">{{ $bill->legacy_ref }}</span>@endif</div>
    @endif
  </div>

  {{-- Footer --}}
  <div class="flex justify-end gap-2 mt-5 pt-4 border-t border-gray-100">
    @if (! $bill->isGenerated() && $bill->status !== 'cancelled' && $canGenerate)
      <button type="button" @click="generateBill(@js($bill->bill_ref_id))"
              class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-emerald-600 text-white text-sm font-semibold hover:bg-emerald-700">
        <i data-lucide="file-check" class="w-4 h-4"></i> Generate bill
      </button>
    @endif
    @if ($canPrint)
      @if ($bill->isGenerated())
        <a href="{{ route('revenue.bills.invoice', $bill->bill_ref_id) }}" target="_blank"
           class="inline-flex items-center gap-2 px-4 py-2 rounded-lg border border-gray-300 text-sm font-semibold hover:bg-gray-50">
          <i data-lucide="printer" class="w-4 h-4"></i> Print invoice
        </a>
      @else
        <span title="Generate the bill first" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg border border-gray-200 text-gray-400 text-sm font-semibold cursor-not-allowed">
          <i data-lucide="printer" class="w-4 h-4"></i> Print invoice
        </span>
      @endif
    @endif
  </div>
</div>
