@extends('layouts.app')

@section('content')
@php
  $money = fn ($v) => '₦' . number_format((float) $v, 2);
  $cycles = config('billing.cycles');
@endphp
<div class="flex-1 overflow-auto bg-gray-50"
     x-data="revBills(@js(['lookupUrl' => '', 'storeUrl' => '', 'showUrl' => url('revenue/bill/__REF__'), 'generateUrl' => url('revenue/bill/__REF__/generate'), 'kind' => '']))"
     @rev-view.window="openView($event.detail.ref, $event.detail.tab)">
  @include('admin.header', ['PageTitle' => 'Payment Cycles', 'PageDescription' => 'Recurring bills and when their next payment falls due'])

  <div class="p-6">
    <div class="flex items-center gap-2 text-sm text-gray-500 mb-4">
      <i data-lucide="banknote" class="w-4 h-4 text-emerald-600"></i>
      <span>KLAES REV-M</span>
      <i data-lucide="chevron-right" class="w-3.5 h-3.5"></i>
      <span>Billing</span>
      <i data-lucide="chevron-right" class="w-3.5 h-3.5"></i>
      <span class="font-semibold text-gray-700">Payment Cycles</span>
    </div>

    {{-- Which bill types recur, and how --}}
    <div class="rounded-xl border border-sky-200 bg-sky-50/60 p-4 mb-5 text-sm">
      <div class="font-semibold flex items-center gap-2 mb-2"><i data-lucide="repeat" class="w-4 h-4 text-sky-700"></i> Recurring bill types</div>
      @if ($recurringTypes->isEmpty())
        <p class="text-gray-600">No bill type is recurring yet. Make one recurring in System Admin → Configurable Entries → Bill Settings (its sub-tab → "Recurring bill"), choosing the payment cycle and how many days before the due date the SMS reminder goes out.</p>
      @else
        <div class="flex flex-wrap gap-2 mb-2">
          @foreach ($recurringTypes as $t)
            <span class="px-2.5 py-1 rounded-full bg-white border border-sky-200 text-xs"><b>{{ $t->name }}</b> · {{ $cycles[$t->payment_cycle]['label'] ?? $t->payment_cycle }} · reminder {{ (int) $t->remind_days_before }} day(s) before</span>
          @endforeach
        </div>
      @endif
      <p class="text-xs text-gray-600">Every day at 07:30 the system sends the "next payment due" SMS to bills within their reminder window, and on the due date raises the next cycle's bill (same file, payer and lines) and sends its "bill generated" SMS. Both can be done by hand from a row's menu.</p>
    </div>

    <form method="GET" class="flex flex-wrap items-center gap-2 mb-4">
      <select name="when" class="border border-gray-300 rounded-lg px-3 py-2 text-sm">
        @foreach (['current' => 'Current cycle bills (with a next due date)', 'due30' => 'Due in the next 30 days (or overdue)', 'overdue' => 'Due now / overdue', 'all' => 'Every recurring bill (all cycles)'] as $value => $label)
          <option value="{{ $value }}" @selected($filters['when'] === $value)>{{ $label }}</option>
        @endforeach
      </select>
      <select name="type" class="border border-gray-300 rounded-lg px-3 py-2 text-sm">
        <option value="">Every bill type</option>
        @foreach ($types as $t)
          <option value="{{ $t->code }}" @selected($filters['type'] === $t->code)>{{ $t->parent_code ? '— ' : '' }}{{ $t->name }}</option>
        @endforeach
      </select>
      <input type="text" name="q" value="{{ $filters['q'] }}" placeholder="Bill Ref ID, file no., payer, phone" class="border border-gray-300 rounded-lg px-3 py-2 text-sm w-72">
      <button class="px-3 py-2 rounded-lg bg-gray-800 text-white text-sm font-semibold">Search</button>
      <a href="{{ $indexUrl }}" class="px-3 py-2 text-sm text-gray-600">Clear</a>
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
            <th class="px-3 py-2">Phone</th>
            <th class="px-3 py-2">Cycle</th>
            <th class="px-3 py-2">Period</th>
            <th class="px-3 py-2">Next due</th>
            <th class="px-3 py-2 text-right">Amount</th>
            <th class="px-3 py-2">Status</th>
            <th class="px-3 py-2 text-right">Action</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
          @forelse ($rows as $bill)
            @php
              $ref = $bill->bill_ref_id;
              $dueIn = $bill->next_due_date ? now()->startOfDay()->diffInDays($bill->next_due_date->copy()->startOfDay(), false) : null;
              $cyItems = [['View', 'eye', null, false, "window.dispatchEvent(new CustomEvent('rev-view', {detail: {ref: '{$ref}'}}))"]];
              if ($canEdit && $bill->next_due_date) {
                  $cyItems[] = ['Send due reminder (SMS)', 'message-square', null, false, "cyAction('remind', '{$ref}')"];
                  $cyItems[] = ['Raise next cycle now', 'repeat', null, false, "cyAction('raise', '{$ref}')"];
              }
            @endphp
            <tr class="hover:bg-gray-50">
              <td class="px-3 py-2 text-gray-500">{{ $rows->firstItem() + $loop->index }}</td>
              <td class="px-3 py-2 font-mono whitespace-nowrap">{{ $ref }}
                @if ($bill->previous_bill_ref)<span class="block text-[11px] text-gray-500">after {{ $bill->previous_bill_ref }}</span>@endif
              </td>
              <td class="px-3 py-2 whitespace-nowrap">{{ optional($bill->type)->name }}</td>
              <td class="px-3 py-2 font-mono whitespace-nowrap">{{ $bill->file_number ?: '—' }}</td>
              <td class="px-3 py-2">{{ $bill->payer_name ?: '—' }}</td>
              <td class="px-3 py-2 font-mono whitespace-nowrap">{{ $bill->payer_phone ?: '—' }}</td>
              <td class="px-3 py-2">{{ $cycles[$bill->payment_cycle]['label'] ?? $bill->payment_cycle }}</td>
              <td class="px-3 py-2 whitespace-nowrap text-xs">{{ optional($bill->period_start)->format('d M Y') }} – {{ optional($bill->period_end)->format('d M Y') }}</td>
              <td class="px-3 py-2 whitespace-nowrap">
                @if ($bill->next_due_date)
                  {{ $bill->next_due_date->format('d M Y') }}
                  <span class="block text-[11px] {{ $dueIn <= 0 ? 'text-red-700 font-semibold' : ($dueIn <= 30 ? 'text-amber-700' : 'text-gray-500') }}">
                    {{ $dueIn < 0 ? abs($dueIn) . ' day(s) overdue' : ($dueIn === 0 ? 'due today' : 'in ' . $dueIn . ' day(s)') }}
                  </span>
                @else
                  <span class="text-gray-400 text-xs">Next cycle raised</span>
                @endif
              </td>
              <td class="px-3 py-2 text-right whitespace-nowrap">{{ $money($bill->amount) }}</td>
              <td class="px-3 py-2">@include('revenue.partials._status_pill', ['status' => $bill->displayStatus()])</td>
              <td class="px-3 py-2 text-right">@include('revenue.partials._row_actions', ['items' => $cyItems])</td>
            </tr>
          @empty
            <tr><td colspan="12" class="px-3 py-10 text-center text-gray-400"><i data-lucide="repeat" class="w-8 h-8 mx-auto mb-2"></i><div>No recurring bills match.</div></td></tr>
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

  @include('admin.footer')
</div>
@endsection

@section('footer-scripts')
<script src="{{ asset('js/revenue/bill-master.js') }}?v={{ is_file(public_path('js/revenue/bill-master.js')) ? filemtime(public_path('js/revenue/bill-master.js')) : 1 }}"></script>
<script>
  window.cyAction = async function (action, ref) {
    const url = @json(url('revenue/cycles/__REF__/__ACTION__')).replace('__REF__', encodeURIComponent(ref)).replace('__ACTION__', action);
    const ok = await Swal.fire({
      icon: 'question',
      title: action === 'raise' ? 'Raise the next cycle of ' + ref + '?' : 'Send the due reminder for ' + ref + '?',
      text: action === 'raise' ? 'A new bill for the following period is generated (same file, payer and lines) and its SMS goes out.' : 'The "next payment due" SMS is sent to the payer\'s phone.',
      showCancelButton: true, confirmButtonText: action === 'raise' ? 'Raise' : 'Send', confirmButtonColor: '#047857',
    });
    if (!ok.isConfirmed) return;
    try {
      const res = await fetch(url, { method: 'POST', headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' }, credentials: 'same-origin' });
      const data = await res.json().catch(() => ({}));
      if (!res.ok || data.success === false) throw new Error(data.message || ('HTTP ' + res.status));
      await Swal.fire({ icon: 'success', title: 'Done', text: data.message, confirmButtonColor: '#047857' });
      window.location.reload();
    } catch (e) {
      Swal.fire({ icon: 'error', title: 'Not done', text: e.message, confirmButtonColor: '#047857' });
    }
  };
</script>
@endsection
