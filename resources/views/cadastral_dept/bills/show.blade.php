@extends('layouts.app')

@section('content')
@php
  $cdPillars = old('pillars', $pd
      ? $pillars->map(fn ($p) => ['pillar_number' => $p->pillar_number, 'ownership' => $p->ownership ?: 'government', 'easting' => $p->easting === null ? '' : (string) $p->easting, 'northing' => $p->northing === null ? '' : (string) $p->northing])->values()->all()
      : ($backfill['pillars'] ?? []));
  $cdFeeInit = [
      'url' => route('cadastral-dept.cases.bill.preview', $case->id),
      'delay_days' => old('delay_days', ''), 'field_work_days' => old('field_work_days', ''), 'office_work_days' => old('office_work_days', ''),
      'plan_prints' => old('plan_prints', ''), 'transport_band' => old('transport_band', 'none'), 'transport_km' => old('transport_km', ''),
  ];
  $naira = fn ($v) => $v === null ? '—' : '₦' . number_format((float) $v, 2);
@endphp
<div class="flex-1 overflow-auto bg-gray-50">
  @include('admin.header', ['PageTitle' => 'Fees P&D Bill', 'PageDescription' => $case->case_ref . ' · ' . $case->file_number])

  <div class="p-6 max-w-6xl mx-auto space-y-6">
    @include('cadastral_dept.partials._nav')

    <div class="flex flex-wrap items-center gap-2">
      <a href="{{ route('cadastral-dept.cases.show', $case->id) }}" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg border border-gray-300 bg-white text-sm font-semibold text-gray-700">
        <i data-lucide="arrow-left" class="w-4 h-4"></i> Back to {{ $case->case_ref }}
      </a>
      @if ($liveBill)
        <span class="px-3 py-1 rounded-full text-xs font-semibold bg-green-100 text-green-800">Bill {{ $liveBill->bill_ref }} issued · {{ $naira($liveBill->grand_total) }}</span>
        @canDo('Cad - Records', 'print')
          <a href="{{ route('cadastral-dept.bills.print', $liveBill->id) }}" target="_blank" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-gray-800 text-white text-sm font-semibold">
            <i data-lucide="printer" class="w-4 h-4"></i> Print bill
          </a>
        @endcanDo
      @endif
    </div>

    <div class="rounded-xl border border-gray-200 bg-white p-5">
      <dl class="grid gap-3 sm:grid-cols-5 text-sm">
        <div><dt class="text-xs text-gray-500">File No.</dt><dd class="font-mono">{{ $case->file_number }}</dd></div>
        <div><dt class="text-xs text-gray-500">Plot No.</dt><dd>{{ $case->plot_no ?: '—' }}</dd></div>
        <div><dt class="text-xs text-gray-500">District</dt><dd>{{ $case->district ?: '—' }}</dd></div>
        <div><dt class="text-xs text-gray-500">LGA</dt><dd>{{ $case->lga ?: '—' }}</dd></div>
        <div><dt class="text-xs text-gray-500">Location</dt><dd>{{ $case->location ?: '—' }}</dd></div>
      </dl>
      <p class="text-xs text-gray-500 mt-3">No payment is taken here. Once issued, the bill total is filled in as the Survey Fee on Deeds Bill Balance when this file number is picked.</p>
    </div>

    @unless ($due)
      <div class="rounded-lg border border-yellow-300 bg-yellow-50 px-4 py-3 text-sm text-yellow-900">
        {{ $case->case_ref }} is not at the billing step, so this page is read only.
      </div>
    @endunless

    {{-- 1. Area and beacons --}}
    <form method="POST" action="{{ route('cadastral-dept.cases.bill.area.save', $case->id) }}" class="rounded-xl border border-gray-200 bg-white p-5 space-y-4"
          x-data="cdPndArea(@js($cdPillars))">
      @csrf
      <div class="flex flex-wrap items-baseline justify-between gap-2">
        <h3 class="text-base font-bold text-gray-800">1. Area and beacons</h3>
        @if ($pd)
          <span class="text-sm text-gray-600">{{ $pd->pd_ref }} · saved area <strong>{{ number_format((float) $pd->area_sqm, 2) }} m²</strong> ({{ number_format((float) $pd->area_ha, 4) }} ha, {{ $pd->area_source === 'manual' ? 'typed' : 'from beacons' }})</span>
        @elseif ($backfill)
          <span class="text-xs text-blue-700">Prefilled from charting card {{ $backfill['source'] }}. Check and save.</span>
        @endif
      </div>

      <div class="overflow-x-auto">
        <table class="min-w-full text-sm">
          <thead class="text-left text-xs uppercase text-gray-500">
            <tr><th class="py-1 pr-2">S/N</th><th class="py-1 pr-2">Beacon</th><th class="py-1 pr-2">Easting</th><th class="py-1 pr-2">Northing</th><th class="py-1 pr-2">Ownership</th><th></th></tr>
          </thead>
          <tbody>
            <template x-for="(p, i) in pillars" :key="i">
              <tr>
                <td class="py-1 pr-2 text-gray-500" x-text="i + 1"></td>
                <td class="py-1 pr-2"><input type="text" :name="'pillars[' + i + '][pillar_number]'" x-model="p.pillar_number" maxlength="50" class="w-24 border border-gray-300 rounded px-2 py-1 uppercase" @disabled(! $due)></td>
                <td class="py-1 pr-2"><input type="text" inputmode="decimal" :name="'pillars[' + i + '][easting]'" x-model="p.easting" class="w-36 border border-gray-300 rounded px-2 py-1" @disabled(! $due)></td>
                <td class="py-1 pr-2"><input type="text" inputmode="decimal" :name="'pillars[' + i + '][northing]'" x-model="p.northing" class="w-36 border border-gray-300 rounded px-2 py-1" @disabled(! $due)></td>
                <td class="py-1 pr-2">
                  <select :name="'pillars[' + i + '][ownership]'" x-model="p.ownership" class="border border-gray-300 rounded px-2 py-1" @disabled(! $due)>
                    <option value="government">Government</option><option value="private">Private</option>
                  </select>
                </td>
                <td class="py-1">@if ($due)<button type="button" @click="pillars.splice(i, 1)" class="text-gray-400 hover:text-red-600" aria-label="Remove beacon">&times;</button>@endif</td>
              </tr>
            </template>
          </tbody>
        </table>
      </div>
      <div class="flex flex-wrap items-center gap-3">
        @if ($due)
          <button type="button" @click="pillars.push({pillar_number: '', ownership: 'government', easting: '', northing: ''})" class="px-3 py-1 rounded-md border border-gray-300 text-sm font-semibold text-gray-700">+ Add beacon</button>
        @endif
        <span class="text-sm text-gray-600">Beacons: <strong x-text="pillars.filter(p => (p.pillar_number || p.easting || p.northing)).length"></strong> · area from coordinates: <strong x-text="area() ? area().toLocaleString(undefined, {maximumFractionDigits: 2}) + ' m²' : '—'"></strong></span>
      </div>
      <div class="flex flex-wrap items-end gap-3">
        <div>
          <label class="block text-xs font-semibold text-gray-600 mb-1">Area (leave blank to use the coordinates)</label>
          <input type="text" inputmode="decimal" name="area_value" value="{{ old('area_value', $pd && $pd->area_source === 'manual' ? $pd->area_sqm : '') }}" class="w-40 border border-gray-300 rounded-lg px-3 py-2 text-sm" @disabled(! $due)>
        </div>
        <div>
          <label class="block text-xs font-semibold text-gray-600 mb-1">Unit</label>
          <select name="area_unit" class="border border-gray-300 rounded-lg px-3 py-2 text-sm" @disabled(! $due)>
            @foreach ($units as $k => $u)<option value="{{ $k }}" @selected(old('area_unit', 'sqm') === $k)>{{ is_array($u) ? ($u['label'] ?? $k) : $u }}</option>@endforeach
          </select>
        </div>
        @if ($due)
          <button class="ml-auto px-4 py-2 rounded-lg bg-green-700 text-white text-sm font-semibold">Save area and beacons</button>
        @endif
      </div>
    </form>

    {{-- 2. Description --}}
    <div class="rounded-xl border border-gray-200 bg-white p-5 space-y-3">
      <div class="flex flex-wrap items-center justify-between gap-2">
        <h3 class="text-base font-bold text-gray-800">2. Land description</h3>
        @if ($pd && $due)
          <form method="POST" action="{{ route('cadastral-dept.cases.bill.description.store', $case->id) }}"
                @if ($pd->description_body) onsubmit="return confirm('Rewrite the description from the template? Your edits will be replaced.')" @endif>
            @csrf
            <button class="px-3 py-1 rounded-md border border-gray-300 text-sm font-semibold text-gray-700">{{ $pd->description_body ? 'Rewrite from template' : 'Write from template' }}</button>
          </form>
        @endif
      </div>
      @if (! $pd)
        <p class="text-sm text-gray-500">Save the area and beacons first.</p>
      @else
        <form method="POST" action="{{ route('cadastral-dept.cases.bill.description.save', $case->id) }}" class="space-y-2">
          @csrf @method('PUT')
          <textarea name="description_body" rows="6" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" @disabled(! $due)
                    placeholder="Write it from the template, then edit.">{{ old('description_body', $pd->description_body) }}</textarea>
          @if ($due)
            <div class="flex justify-end"><button class="px-4 py-2 rounded-lg bg-gray-800 text-white text-sm font-semibold">Save description</button></div>
          @endif
        </form>
      @endif
    </div>

    {{-- 3. Fees --}}
    <form method="POST" action="{{ route('cadastral-dept.cases.bill.store', $case->id) }}" class="rounded-xl border border-gray-200 bg-white p-5 space-y-4"
          x-data="cdPndFees(@js($cdFeeInit))" x-init="load()"
          onsubmit="return confirm('Issue the Fees P&D bill? An earlier unpaid bill for this file is cancelled and replaced.')">
      @csrf
      <h3 class="text-base font-bold text-gray-800">3. Fees (official sheet)</h3>
      <div class="grid gap-3 sm:grid-cols-3 lg:grid-cols-6">
        @foreach (['delay_days' => 'Delay (days)', 'field_work_days' => 'Additional field work (days)', 'office_work_days' => 'Office work (days)', 'plan_prints' => 'Plan prints'] as $cdName => $cdLabel)
          <div>
            <label class="block text-xs font-semibold text-gray-600 mb-1">{{ $cdLabel }}</label>
            <input type="number" min="0" max="999" name="{{ $cdName }}" x-model="f.{{ $cdName }}" @input.debounce.400ms="load()" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" @disabled(! $due)>
          </div>
        @endforeach
        <div>
          <label class="block text-xs font-semibold text-gray-600 mb-1">Transport band</label>
          <select name="transport_band" x-model="f.transport_band" @change="f.transport_km = ''; load()" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" @disabled(! $due)>
            <option value="none">No transport</option>
            @foreach ($bands as $k => $b)<option value="{{ $k }}">{{ $b['label'] }} ({{ $naira($b['fee']) }})</option>@endforeach
          </select>
        </div>
        <div>
          <label class="block text-xs font-semibold text-gray-600 mb-1">or distance (km)</label>
          <input type="number" min="0" step="0.1" name="transport_km" x-model="f.transport_km" @input.debounce.400ms="load()" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" @disabled(! $due)>
        </div>
      </div>

      <template x-if="r && r.flags && Object.keys(r.flags).length">
        <div class="rounded-lg border border-yellow-300 bg-yellow-50 px-3 py-2 text-xs text-yellow-900 space-y-1">
          <template x-for="(msg, k) in r.flags" :key="k"><div x-text="msg"></div></template>
        </div>
      </template>
      <template x-if="r && r.refusal">
        <div class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-800" x-text="r.refusal"></div>
      </template>

      <div class="overflow-x-auto">
        <table class="min-w-full text-sm">
          <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500">
            <tr><th class="px-3 py-2">S/N</th><th class="px-3 py-2">Line</th><th class="px-3 py-2">Quantity</th><th class="px-3 py-2">Basis</th><th class="px-3 py-2 text-right">Rate</th><th class="px-3 py-2 text-right">Amount</th></tr>
          </thead>
          <tbody class="divide-y divide-gray-100">
            <template x-for="(l, i) in (r ? Object.values(r.lines || {}) : [])" :key="l.key">
              <tr>
                <td class="px-3 py-2 text-gray-500" x-text="i + 1"></td>
                <td class="px-3 py-2" x-text="l.label"></td>
                <td class="px-3 py-2" x-text="l.qty_label"></td>
                <td class="px-3 py-2 text-xs text-gray-500" x-text="l.basis"></td>
                <td class="px-3 py-2 text-right" x-text="money(l.rate)"></td>
                <td class="px-3 py-2 text-right font-semibold" x-text="money(l.amount)"></td>
              </tr>
            </template>
          </tbody>
          <tfoot>
            <tr class="border-t-2 border-gray-200"><td colspan="5" class="px-3 py-2 text-right font-bold">Total</td><td class="px-3 py-2 text-right font-bold" x-text="r ? money(r.total) : '—'"></td></tr>
          </tfoot>
        </table>
      </div>
      @if ($due)
        <div class="flex items-center justify-end gap-3">
          <span class="text-xs text-gray-500" x-show="loading">Working out…</span>
          <button :disabled="!r || !r.ok" class="px-4 py-2 rounded-lg bg-green-700 text-white text-sm font-semibold disabled:opacity-50">{{ $liveBill ? 'Issue a corrected bill' : 'Issue bill' }}</button>
        </div>
      @endif
    </form>

    @if ($history->isNotEmpty())
      <div>
        <h3 class="text-sm font-bold uppercase tracking-wide text-gray-500 mb-3">Bills for this file</h3>
        <div class="rounded-xl border border-gray-200 bg-white overflow-x-auto">
          <table class="min-w-full text-sm">
            <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500">
              <tr><th class="px-3 py-2">S/N</th><th class="px-3 py-2">Bill</th><th class="px-3 py-2">Date</th><th class="px-3 py-2 text-right">Total</th><th class="px-3 py-2">Status</th><th class="px-3 py-2 text-right">Action</th></tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
              @foreach ($history as $b)
                <tr>
                  <td class="px-3 py-2 text-gray-500">{{ $loop->iteration }}</td>
                  <td class="px-3 py-2 font-mono">{{ $b->bill_ref }}</td>
                  <td class="px-3 py-2">{{ optional($b->issued_at)->format('d M Y H:i') }}</td>
                  <td class="px-3 py-2 text-right">{{ $naira($b->grand_total) }}</td>
                  <td class="px-3 py-2"><span class="px-2 py-0.5 rounded-full text-xs font-semibold {{ $b->status === 'Issued' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-600' }}">{{ $b->status }}</span>@if ($b->cancel_reason)<div class="text-xs text-gray-500">{{ $b->cancel_reason }}</div>@endif</td>
                  <td class="px-3 py-2 text-right">
                    @canDo('Cad - Records', 'print')
                      @include('cadastral_dept.partials._row_actions', ['items' => [['Print bill', 'printer', route('cadastral-dept.bills.print', $b->id), true]]])
                    @endcanDo
                  </td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      </div>
    @endif
  </div>
</div>
@endsection

@push('scripts')
<script>
  function cdPndArea(pillars) {
    return {
      pillars: (pillars && pillars.length) ? pillars : [{pillar_number: '', ownership: 'government', easting: '', northing: ''}],
      area() {
        const p = this.pillars.map(b => [parseFloat(String(b.easting).replace(/[ ,]/g, '')), parseFloat(String(b.northing).replace(/[ ,]/g, ''))])
          .filter(x => !isNaN(x[0]) && !isNaN(x[1]));
        if (p.length < 3) return null;
        let s = 0;
        for (let i = 0; i < p.length; i++) { const a = p[i], b = p[(i + 1) % p.length]; s += a[0] * b[1] - b[0] * a[1]; }
        return Math.abs(s) / 2;
      },
    };
  }
  function cdPndFees(init) {
    return {
      f: init, r: null, loading: false,
      money(v) { return v === null || v === undefined ? '—' : '₦' + Number(v).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2}); },
      load() {
        this.loading = true;
        const q = new URLSearchParams(this.f); q.delete('url');
        fetch(this.f.url + '?' + q.toString(), {headers: {'Accept': 'application/json'}})
          .then(res => res.json()).then(d => { this.r = d; })
          .catch(() => { this.r = {ok: false, refusal: 'The fees could not be worked out. Reload the page.', lines: {}}; })
          .finally(() => { this.loading = false; });
      },
    };
  }
</script>
@endpush
