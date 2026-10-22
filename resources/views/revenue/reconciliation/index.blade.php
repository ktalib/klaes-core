@extends('layouts.app')

@section('content')
@php
  $money = fn ($v) => '₦' . number_format((float) $v, 2);
@endphp
<div class="flex-1 overflow-auto bg-gray-50">
  @include('admin.header', ['PageTitle' => 'Reconciliation', 'PageDescription' => 'Payments checked against Paystack, the POS settlement report and the bank'])

  <div class="p-6">
    <div class="flex items-center gap-2 text-sm text-gray-500 mb-4">
      <i data-lucide="banknote" class="w-4 h-4 text-emerald-600"></i>
      <span>KLAES REV-M</span>
      <i data-lucide="chevron-right" class="w-3.5 h-3.5"></i>
      <span class="font-semibold text-gray-700">Reconciliation</span>
    </div>

    {{-- Summary --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
      @foreach ([
          ['Unreconciled', (int) $summary->un_n, $summary->un_amt, 'scale', 'text-amber-600 bg-amber-50', 'unreconciled'],
          ['Reconciled', (int) $summary->ok_n, $summary->ok_amt, 'badge-check', 'text-green-600 bg-green-50', 'reconciled'],
          ['Disputed', (int) $summary->dis_n, $summary->dis_amt, 'alert-octagon', 'text-red-600 bg-red-50', 'disputed'],
          ['Paystack not returned', (int) $summary->pend_n, $summary->pend_amt, 'hourglass', 'text-blue-600 bg-blue-50', 'pending'],
      ] as [$label, $n, $amt, $icon, $tone, $key])
        <a href="{{ $indexUrl }}?recon={{ $key }}" class="rounded-xl border bg-white p-4 flex items-center gap-3 hover:shadow-sm {{ $filters['recon'] === $key ? 'border-emerald-400 ring-1 ring-emerald-300' : 'border-gray-200' }}">
          <span class="inline-flex items-center justify-center w-10 h-10 rounded-lg {{ $tone }}"><i data-lucide="{{ $icon }}" class="w-5 h-5"></i></span>
          <div class="min-w-0">
            <div class="text-xs uppercase text-gray-500">{{ $label }}</div>
            <div class="text-lg font-bold text-gray-800">{{ number_format($n) }}</div>
            <div class="text-xs text-gray-500 truncate">{{ $money($amt) }}</div>
          </div>
        </a>
      @endforeach
    </div>

    {{-- Filters --}}
    <form method="GET" class="flex flex-wrap items-center gap-2 mb-4">
      <select name="recon" class="border border-gray-300 rounded-lg px-3 py-2 text-sm">
        @foreach (['unreconciled' => 'Unreconciled', 'reconciled' => 'Reconciled', 'disputed' => 'Disputed', 'all' => 'Every successful payment', 'pending' => 'Paystack not returned (pending)'] as $value => $label)
          <option value="{{ $value }}" @selected($filters['recon'] === $value)>{{ $label }}</option>
        @endforeach
      </select>
      <select name="method" class="border border-gray-300 rounded-lg px-3 py-2 text-sm">
        <option value="">Every method</option>
        @foreach ($methods as $value => $label)
          <option value="{{ $value }}" @selected($filters['method'] === $value)>{{ $label }}</option>
        @endforeach
      </select>
      <label class="text-xs text-gray-500">From <input type="date" name="from" value="{{ $filters['from'] }}" class="border border-gray-300 rounded-lg px-2 py-1.5 text-sm"></label>
      <label class="text-xs text-gray-500">To <input type="date" name="to" value="{{ $filters['to'] }}" class="border border-gray-300 rounded-lg px-2 py-1.5 text-sm"></label>
      <input type="text" name="q" value="{{ $filters['q'] }}" placeholder="Transaction ID, settlement ref, Bill Ref ID, file no., payer"
             class="border border-gray-300 rounded-lg px-3 py-2 text-sm w-80">
      <button class="px-3 py-2 rounded-lg bg-gray-800 text-white text-sm font-semibold">Search</button>
      <a href="{{ $indexUrl }}" class="px-3 py-2 text-sm text-gray-600">Clear</a>
      <span class="ml-auto text-sm text-gray-600">{{ number_format($totals->n) }} payment(s) · <b>{{ $money($totals->amount) }}</b></span>
    </form>

    <div class="rounded-xl border border-gray-200 bg-white overflow-x-auto">
      <table class="min-w-full text-sm">
        <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500">
          <tr>
            <th class="px-3 py-2 whitespace-nowrap">
              @if ($canEdit && $filters['recon'] !== 'pending')<input type="checkbox" class="rv-batch-all h-3.5 w-3.5 mr-1.5 align-middle rounded border-gray-300" aria-label="Select all">@endif
              S/N
            </th>
            <th class="px-3 py-2">Date paid</th>
            <th class="px-3 py-2">Method</th>
            <th class="px-3 py-2">Transaction ID</th>
            <th class="px-3 py-2 text-right">Amount</th>
            <th class="px-3 py-2">Bill Ref ID</th>
            <th class="px-3 py-2">Bill Type</th>
            <th class="px-3 py-2">File No.</th>
            <th class="px-3 py-2">Payer</th>
            <th class="px-3 py-2">Reconciliation</th>
            <th class="px-3 py-2">Settlement ref</th>
            <th class="px-3 py-2 text-right">Action</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
          @forelse ($rows as $row)
            @php
              $recon = $row->reconciliation_status ?: 'unreconciled';
              $successful = $row->status === 'successful';
              $paid = $row->paid_at ? \Illuminate\Support\Carbon::parse($row->paid_at) : null;
              $rvItems = [
                  ['View bill', 'eye', url('revenue/bills') . '?q=' . urlencode($row->bill_ref_id) . '&open=' . urlencode($row->bill_ref_id), false],
              ];
              if ($canEdit) {
                  if ($row->method === 'paystack' && $paystackReady) {
                      $rvItems[] = ['Check with Paystack', 'refresh-cw', null, false, "rvVerify({$row->id})"];
                  }
                  if ($successful && $recon !== 'reconciled') {
                      $rvItems[] = ['Reconcile', 'badge-check', null, false, "rvMark('reconcile', [{$row->id}])"];
                  }
                  if ($successful && $recon !== 'disputed') {
                      $rvItems[] = ['Dispute', 'alert-octagon', null, false, "rvMark('dispute', [{$row->id}])"];
                  }
                  if ($successful && $recon !== 'unreconciled') {
                      $rvItems[] = ['Set back to unreconciled', 'rotate-ccw', null, false, "rvMark('reopen', [{$row->id}])"];
                  }
              }
            @endphp
            <tr class="hover:bg-gray-50 align-top">
              <td class="px-3 py-2 text-gray-500 whitespace-nowrap">
                @if ($canEdit && $filters['recon'] !== 'pending')
                  <input type="checkbox" class="rv-batch-check h-3.5 w-3.5 mr-1.5 align-middle rounded border-gray-300"
                         value="{{ $row->id }}" data-label="{{ $row->transaction_id ?: $row->bill_ref_id }}" data-recon="{{ $recon }}"
                         @disabled(! $successful) aria-label="Select {{ $row->transaction_id }}">
                @endif
                {{ $rows->firstItem() + $loop->index }}
              </td>
              <td class="px-3 py-2 whitespace-nowrap">{{ $paid ? $paid->format('d M Y H:i') : '—' }}</td>
              <td class="px-3 py-2 whitespace-nowrap">{{ \App\Http\Controllers\Revenue\DashboardController::methodLabel($row->method) }}</td>
              <td class="px-3 py-2 font-mono whitespace-nowrap">{{ $row->transaction_id ?: '—' }}</td>
              <td class="px-3 py-2 text-right whitespace-nowrap">{{ $money($row->amount) }}</td>
              <td class="px-3 py-2 font-mono whitespace-nowrap">{{ $row->bill_ref_id }}</td>
              <td class="px-3 py-2 whitespace-nowrap">{{ $row->type_name }}</td>
              <td class="px-3 py-2 font-mono whitespace-nowrap">{{ $row->file_number ?: '—' }}</td>
              <td class="px-3 py-2">{{ $row->payer_name ?: '—' }}</td>
              <td class="px-3 py-2">
                @if ($successful)
                  @include('revenue.partials._recon_pill', ['recon' => $recon])
                  @if ($row->reconciled_by)<span class="block text-[11px] text-gray-500 mt-0.5">{{ $row->reconciled_by }} · {{ \Illuminate\Support\Carbon::parse($row->reconciled_at)->format('d M Y') }}</span>@endif
                  @if ($row->reconciliation_note)<span class="block text-[11px] text-gray-600 max-w-[14rem]">{{ $row->reconciliation_note }}</span>@endif
                @else
                  <span class="px-2 py-0.5 rounded-full text-xs font-semibold bg-blue-100 text-blue-800">Pending at Paystack</span>
                @endif
              </td>
              <td class="px-3 py-2 font-mono text-xs whitespace-nowrap">{{ $row->settlement_ref ?: '—' }}</td>
              <td class="px-3 py-2 text-right">@include('revenue.partials._row_actions', ['items' => $rvItems])</td>
            </tr>
          @empty
            <tr><td colspan="12" class="px-3 py-10 text-center text-gray-400"><i data-lucide="inbox" class="w-8 h-8 mx-auto mb-2"></i><div>No payments match.</div></td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
    <div class="mt-4">{{ $rows->links() }}</div>

    {{-- Bill totals that no longer match their payments --}}
    <div class="mt-8 rounded-xl border border-gray-200 bg-white">
      <div class="px-4 py-3 border-b border-gray-100 flex items-center gap-2">
        <i data-lucide="sigma" class="w-4 h-4 text-emerald-600"></i>
        <span class="font-semibold">Bill totals check</span>
        <span class="text-xs text-gray-500">Bills whose "amount paid" differs from the sum of their successful payments.</span>
      </div>
      @if ($drift->isEmpty())
        <div class="px-4 py-4 text-sm text-green-700 flex items-center gap-2"><i data-lucide="check-circle" class="w-4 h-4"></i> Every bill's amount paid matches its payments.</div>
      @else
        <div class="overflow-x-auto">
          <table class="min-w-full text-sm">
            <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500">
              <tr><th class="px-3 py-2">S/N</th><th class="px-3 py-2">Bill Ref ID</th><th class="px-3 py-2">File No.</th><th class="px-3 py-2">Payer</th><th class="px-3 py-2 text-right">Amount</th><th class="px-3 py-2 text-right">Amount paid (bill)</th><th class="px-3 py-2 text-right">Payments</th><th class="px-3 py-2">Status</th><th class="px-3 py-2 text-right">Action</th></tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
              @foreach ($drift as $d)
                <tr>
                  <td class="px-3 py-2 text-gray-500">{{ $loop->iteration }}</td>
                  <td class="px-3 py-2 font-mono">{{ $d->bill_ref_id }}</td>
                  <td class="px-3 py-2 font-mono">{{ $d->file_number ?: '—' }}</td>
                  <td class="px-3 py-2">{{ $d->payer_name ?: '—' }}</td>
                  <td class="px-3 py-2 text-right">{{ $money($d->amount) }}</td>
                  <td class="px-3 py-2 text-right text-red-700">{{ $money($d->amount_paid) }}</td>
                  <td class="px-3 py-2 text-right">{{ $money($d->payments) }}</td>
                  <td class="px-3 py-2">{{ ucfirst(str_replace('_', ' ', $d->status)) }}</td>
                  <td class="px-3 py-2 text-right">
                    @include('revenue.partials._row_actions', ['items' => $canEdit
                        ? [['Recompute from payments', 'calculator', null, false, "rvTotals('{$d->bill_ref_id}')"]]
                        : [['Recompute from payments', 'calculator', null, false, null, 'Needs the Billing edit permission']]])
                  </td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      @endif
    </div>
  </div>

  @if ($canEdit && $filters['recon'] !== 'pending')
    @include('revenue.reconciliation._batch_bar')
  @endif

  @include('admin.footer')
</div>
@endsection

@section('footer-scripts')
<script>
(function () {
  const CSRF = '{{ csrf_token() }}';
  const URLS = {
    mark: @json(route('revenue.reconciliation.mark')),
    verify: @json(url('revenue/reconciliation/verify/__ID__')),
    totals: @json(url('revenue/reconciliation/totals/__REF__')),
  };

  async function post(url, body) {
    const res = await fetch(url, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF },
      body: JSON.stringify(body || {}),
      credentials: 'same-origin',
    });
    let data = {};
    try { data = await res.json(); } catch (e) {}
    if (!res.ok || data.success === false) {
      let m = data.message || ('HTTP ' + res.status);
      if (data.errors) m = Object.values(data.errors).map((e) => e[0]).join('\n');
      throw new Error(m);
    }
    return data;
  }

  async function done(promise) {
    try {
      const data = await promise;
      await Swal.fire({ icon: 'success', title: 'Done', text: data.message || 'Saved.', confirmButtonColor: '#047857' });
      window.location.reload();
    } catch (e) {
      Swal.fire({ icon: 'error', title: 'Not saved', text: e.message, confirmButtonColor: '#047857' });
    }
  }

  /** Reconcile (settlement ref + note), dispute (reason required) or reopen the given payments. */
  window.rvMark = async function (action, ids) {
    if (!ids.length) return;
    const n = ids.length + ' payment' + (ids.length === 1 ? '' : 's');
    if (action === 'reconcile') {
      const r = await Swal.fire({
        title: 'Reconcile ' + n,
        html: '<div class="text-left space-y-2">'
          + '<p class="text-xs text-gray-500">Confirmed against the POS settlement report, the bank statement or the receipt book.</p>'
          + '<label class="block text-xs font-bold text-gray-600">Settlement / batch / statement reference</label>'
          + '<input id="rv-settle" class="swal2-input !m-0 !w-full" maxlength="100" placeholder="Optional">'
          + '<label class="block text-xs font-bold text-gray-600">Note</label>'
          + '<textarea id="rv-note" class="swal2-textarea !m-0 !w-full" maxlength="500" placeholder="Optional"></textarea></div>',
        showCancelButton: true, confirmButtonText: 'Reconcile', confirmButtonColor: '#047857',
        preConfirm: () => ({ settlement_ref: document.getElementById('rv-settle').value, note: document.getElementById('rv-note').value }),
      });
      if (!r.isConfirmed) return;
      return done(post(URLS.mark, Object.assign({ action, ids }, r.value)));
    }
    if (action === 'dispute') {
      const r = await Swal.fire({
        title: 'Dispute ' + n, input: 'textarea', inputPlaceholder: 'What does not match? (required)',
        inputValidator: (v) => (!v || !v.trim() ? 'Say why the payment is disputed.' : null),
        showCancelButton: true, confirmButtonText: 'Mark disputed', confirmButtonColor: '#b91c1c',
      });
      if (!r.isConfirmed) return;
      return done(post(URLS.mark, { action, ids, note: r.value.trim() }));
    }
    const ok = await Swal.fire({ title: 'Set ' + n + ' back to unreconciled?', icon: 'question', showCancelButton: true, confirmButtonText: 'Set back', confirmButtonColor: '#047857' });
    if (ok.isConfirmed) return done(post(URLS.mark, { action, ids }));
  };

  window.rvVerify = function (id) {
    Swal.fire({ title: 'Checking with Paystack…', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
    done(post(URLS.verify.replace('__ID__', id)));
  };

  window.rvTotals = async function (ref) {
    const ok = await Swal.fire({ title: 'Recompute ' + ref + '?', text: 'Its amount paid and status are worked out again from its successful payments.', icon: 'question', showCancelButton: true, confirmButtonText: 'Recompute', confirmButtonColor: '#047857' });
    if (ok.isConfirmed) done(post(URLS.totals.replace('__REF__', encodeURIComponent(ref))));
  };
})();
</script>
@endsection
