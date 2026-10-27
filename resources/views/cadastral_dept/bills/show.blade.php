@extends('layouts.app')

@section('content')
@php
  $cdNum = fn ($v) => $v === null ? '' : rtrim(rtrim(number_format((float) $v, 3, '.', ''), '0'), '.');
  $cdPillars = old('pillars', $pd
      ? $pillars->map(fn ($p) => [
          'pillar_number' => $p->pillar_number, 'ownership' => $p->ownership ?: $defaultOwnership,
          'easting' => $p->easting === null ? '' : (string) $p->easting, 'northing' => $p->northing === null ? '' : (string) $p->northing,
          // Plan Description line (change request 26-10-2026); a type not in the list shows as Other.
          'pillar_type' => $p->pillar_type === null ? '' : (in_array($p->pillar_type, $beaconTypes, true) ? $p->pillar_type : 'other'),
          'pillar_type_other' => $p->pillar_type !== null && ! in_array($p->pillar_type, $beaconTypes, true) ? $p->pillar_type : '',
          'distance_m' => $linesInstalled ? $cdNum($p->distance_m) : '',
          'bearing_deg' => $linesInstalled && $p->bearing_deg !== null ? (string) $p->bearing_deg : '',
          'bearing_min' => $linesInstalled && $p->bearing_min !== null ? (string) $p->bearing_min : '',
          'bearing_sec' => $linesInstalled && $p->bearing_sec !== null && (float) $p->bearing_sec > 0 ? $cdNum($p->bearing_sec) : '',
        ])->values()->all()
      : ($backfill['pillars'] ?? []));
  $cdHeader = [
      'situated_at' => old('situated_at', $header['situated_at']),
      'plan_drawing_no' => old('plan_drawing_no', $header['plan_drawing_no']),
      'copied_from_plan_no' => old('copied_from_plan_no', $header['copied_from_plan_no']),
      'dimensions' => old('dimensions', $header['dimensions']),
      'description_form' => old('description_form', $header['description_form']),
  ];
  $cdFeeInit = [
      'url' => route('cadastral-dept.cases.bill.preview', $case->id),
      'delay_days' => old('delay_days', ''), 'field_work_days' => old('field_work_days', ''), 'office_work_days' => old('office_work_days', ''),
      'plan_prints' => old('plan_prints', ''), 'transport_band' => old('transport_band', 'none'), 'transport_km' => old('transport_km', ''),
  ];
  $naira = fn ($v) => $v === null ? '—' : '₦' . number_format((float) $v, 2);
@endphp
<div class="flex-1 overflow-auto bg-gray-50">
  @include('admin.header', ['PageTitle' => 'Fees P&D Bill', 'PageDescription' => $case->case_ref . ' · ' . $case->file_number])

  <div class="p-6 space-y-6">
    @include('cadastral_dept.partials._nav')

    <div class="flex flex-wrap items-center gap-2">
      <a href="{{ route('cadastral-dept.cases.show', $case->id) }}" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg border border-gray-300 bg-white text-sm font-semibold text-gray-700">
        <i data-lucide="arrow-left" class="w-4 h-4"></i> Back to {{ $case->case_ref }}
      </a>
      @if ($liveBill)
        <span class="px-3 py-1 rounded-full text-xs font-semibold bg-green-100 text-green-800">Bill {{ $liveBill->bill_ref }} issued · {{ $naira($liveBill->grand_total) }}</span>
        @cadCan('print')
          <a href="{{ route('cadastral-dept.bills.print', $liveBill->id) }}" target="_blank" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-gray-800 text-white text-sm font-semibold">
            <i data-lucide="printer" class="w-4 h-4"></i> Print bill
          </a>
        @endcadCan
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

    @if ($due && $correction)
      <div class="rounded-lg border border-blue-300 bg-blue-50 px-4 py-3 text-sm text-blue-900">
        Correcting the bill before AD Cad Info signs it. Issuing a corrected bill cancels and replaces {{ $liveBill ? $liveBill->bill_ref : 'the unsigned bill' }}. Once the bill is signed it can no longer be changed here.
      </div>
    @endif
    @unless ($due)
      <div class="rounded-lg border border-yellow-300 bg-yellow-50 px-4 py-3 text-sm text-yellow-900">
        {{ $case->case_ref }} is not at the billing step (or the bill is already signed), so this page is read only.
      </div>
    @endunless

    @if ($linesInstalled)
    {{-- 1. Plan Description: CAD 20 / CAD 13 header and boundary lines (change request 26-10-2026) --}}
    <form method="POST" action="{{ route('cadastral-dept.cases.bill.area.save', $case->id) }}" class="rounded-xl border border-gray-200 bg-white p-5 space-y-5"
          x-data="cdPndLines(@js($cdPillars), @js($cdHeader), @js(['types' => $beaconTypes, 'ownership' => $defaultOwnership, 'register' => $registerBeacons]))"
          data-confirm-title="Save the Plan Description?" data-confirm="The header, boundary lines and area for {{ $case->file_number }} are saved.">
      @csrf
      <div class="flex flex-wrap items-baseline justify-between gap-2">
        <h3 class="text-base font-bold text-gray-800">1. Plan Description</h3>
        <div class="flex flex-wrap items-center gap-2">
          @if ($pd)
            <span class="text-sm text-gray-600">{{ $pd->pd_ref }} · saved area <strong>{{ number_format((float) $pd->area_sqm, 2) }} m²</strong> ({{ number_format((float) $pd->area_ha, 4) }} ha, {{ ['manual' => 'typed', 'bearings' => 'from bearings and distances'][$pd->area_source] ?? 'from coordinates' }})</span>
            @cadCan('print')
              @foreach ($forms as $fk => $fl)
                <a href="{{ route('cadastral-dept.cases.bill.description.print', ['case' => $case->id, 'form' => $fk]) }}" target="_blank"
                   class="inline-flex items-center gap-1 px-3 py-1 rounded-md {{ $fk === $header['description_form'] ? 'bg-gray-800 text-white' : 'border border-gray-300 text-gray-700' }} text-xs font-semibold">
                  <i data-lucide="printer" class="w-3.5 h-3.5"></i> Print {{ strtoupper(str_replace('cad', 'CAD ', $fk)) }}
                </a>
              @endforeach
            @endcadCan
          @elseif ($backfill)
            <span class="text-xs text-blue-700">Beacons prefilled from charting card {{ $backfill['source'] }}. Add the bearings and distances, then save.</span>
          @endif
        </div>
      </div>

      {{-- Header: the blanks of the form --}}
      <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4 text-sm">
        <div>
          <label class="block text-xs font-semibold text-gray-600 mb-1">R. of O. / File No.</label>
          <div class="px-3 py-2 rounded-lg bg-gray-50 border border-gray-200 font-mono">{{ $case->file_number }}</div>
        </div>
        <div>
          <label class="block text-xs font-semibold text-gray-600 mb-1">Situated at (village / location)</label>
          <input type="text" name="situated_at" x-model="h.situated_at" maxlength="255" class="w-full border border-gray-300 rounded-lg px-3 py-2" @disabled(! $due)>
        </div>
        <div>
          <label class="block text-xs font-semibold text-gray-600 mb-1">District</label>
          <div class="px-3 py-2 rounded-lg bg-gray-50 border border-gray-200">{{ $header['district'] ?: '—' }}</div>
        </div>
        <div>
          <label class="block text-xs font-semibold text-gray-600 mb-1">LGA (administrative area)</label>
          <div class="px-3 py-2 rounded-lg bg-gray-50 border border-gray-200">{{ $header['lga'] ?: '—' }}</div>
        </div>
        <div>
          <label class="block text-xs font-semibold text-gray-600 mb-1">Plan drawing No. (R. of O. KN)</label>
          <input type="text" name="plan_drawing_no" x-model="h.plan_drawing_no" maxlength="100" class="w-full border border-gray-300 rounded-lg px-3 py-2 font-mono" @disabled(! $due)>
        </div>
        <div x-show="h.description_form === 'cad13'" x-cloak>
          <label class="block text-xs font-semibold text-gray-600 mb-1">Copied from plan No.</label>
          <input type="text" name="copied_from_plan_no" x-model="h.copied_from_plan_no" maxlength="100" class="w-full border border-gray-300 rounded-lg px-3 py-2 font-mono" @disabled(! $due)>
        </div>
        <div>
          <label class="block text-xs font-semibold text-gray-600 mb-1">Form</label>
          <select name="description_form" x-model="h.description_form" class="w-full border border-gray-300 rounded-lg px-3 py-2" @disabled(! $due)>
            @foreach ($forms as $fk => $fl)<option value="{{ $fk }}">{{ $fl }}</option>@endforeach
          </select>
        </div>
      </div>

      {{-- Boundary lines: one card each, + to add (CAD 20 "For a distance of ... on a bearing of ... To a ...") --}}
      <datalist id="cd-beacon-register">
        <template x-for="b in o.register" :key="b"><option :value="b"></option></template>
      </datalist>
      <div class="space-y-3">
        <template x-for="(p, i) in pillars" :key="i">
          <div class="rounded-lg border border-gray-200 p-3">
            <div class="flex flex-wrap items-center justify-between gap-2 mb-2">
              <div class="text-sm font-semibold text-gray-800">
                <span x-text="'Line ' + (i + 1) + ':'"></span>
                <span x-text="i === 0 ? 'Starting from' : 'From'"></span>
                <span class="font-mono" x-text="p.pillar_number || '…'"></span>
                <span class="text-gray-400">&rarr;</span>
                <span class="font-mono" x-text="(pillars[i + 1] ? pillars[i + 1].pillar_number : (pillars.length > 1 ? pillars[0].pillar_number + ' (back to the start)' : '')) || '…'"></span>
              </div>
              @if ($due)
                <button type="button" @click="pillars.splice(i, 1)" class="text-xs text-gray-400 hover:text-red-600" aria-label="Remove line">Remove</button>
              @endif
            </div>
            <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-6 text-sm">
              <div>
                <label class="block text-xs text-gray-500 mb-0.5">Beacon No.</label>
                <input type="text" list="cd-beacon-register" :name="'pillars[' + i + '][pillar_number]'" x-model="p.pillar_number" maxlength="50" placeholder="From the register, or type it"
                       class="w-full border border-gray-300 rounded px-2 py-1 uppercase font-mono" @disabled(! $due)>
                <div class="text-[11px] mt-0.5" x-show="p.pillar_number" x-cloak>
                  <span x-show="inRegister(p.pillar_number)" class="text-green-700">In the beacon register</span>
                  <span x-show="!inRegister(p.pillar_number)" class="text-amber-700">Not in the beacon register (typed)</span>
                </div>
              </div>
              <div>
                <label class="block text-xs text-gray-500 mb-0.5">Beacon type</label>
                <select :name="'pillars[' + i + '][pillar_type]'" x-model="p.pillar_type" class="w-full border border-gray-300 rounded px-2 py-1" @disabled(! $due)>
                  <option value="">Choose…</option>
                  <template x-for="t in o.types" :key="t"><option :value="t" x-text="t" :selected="p.pillar_type === t"></option></template>
                  <option value="other">Other (specify)</option>
                </select>
                <input type="text" x-show="p.pillar_type === 'other'" x-cloak :disabled="p.pillar_type !== 'other' || {{ $due ? 'false' : 'true' }}" :required="p.pillar_type === 'other'"
                       :name="'pillars[' + i + '][pillar_type_other]'" x-model="p.pillar_type_other" maxlength="50" placeholder="Specify the type" class="mt-1 w-full border border-gray-300 rounded px-2 py-1">
              </div>
              <div>
                <label class="block text-xs text-gray-500 mb-0.5">Distance (m)</label>
                <input type="text" inputmode="decimal" :name="'pillars[' + i + '][distance_m]'" x-model="p.distance_m" class="w-full border border-gray-300 rounded px-2 py-1" @disabled(! $due)>
              </div>
              <div class="lg:col-span-2">
                <label class="block text-xs text-gray-500 mb-0.5">Bearing (degrees, minutes, seconds)</label>
                <div class="flex items-center gap-1">
                  <input type="text" inputmode="numeric" :name="'pillars[' + i + '][bearing_deg]'" x-model="p.bearing_deg" maxlength="3" class="w-16 border border-gray-300 rounded px-2 py-1" @disabled(! $due)><span>°</span>
                  <input type="text" inputmode="numeric" :name="'pillars[' + i + '][bearing_min]'" x-model="p.bearing_min" maxlength="2" class="w-14 border border-gray-300 rounded px-2 py-1" @disabled(! $due)><span>′</span>
                  <input type="text" inputmode="decimal" :name="'pillars[' + i + '][bearing_sec]'" x-model="p.bearing_sec" maxlength="5" placeholder="0" class="w-14 border border-gray-300 rounded px-2 py-1" @disabled(! $due)><span>″</span>
                </div>
              </div>
              <div>
                <label class="block text-xs text-gray-500 mb-0.5">Ownership</label>
                <select :name="'pillars[' + i + '][ownership]'" x-model="p.ownership" class="w-full border border-gray-300 rounded px-2 py-1" @disabled(! $due)>
                  <option value="private">Private</option><option value="government">Government</option>
                </select>
              </div>
            </div>
            <details class="mt-2 text-xs" :open="!!(p.easting || p.northing)">
              <summary class="cursor-pointer text-gray-500">Coordinates (optional)</summary>
              <div class="mt-1 flex flex-wrap gap-2">
                <input type="text" inputmode="decimal" :name="'pillars[' + i + '][easting]'" x-model="p.easting" placeholder="Easting" class="w-40 border border-gray-300 rounded px-2 py-1" @disabled(! $due)>
                <input type="text" inputmode="decimal" :name="'pillars[' + i + '][northing]'" x-model="p.northing" placeholder="Northing" class="w-40 border border-gray-300 rounded px-2 py-1" @disabled(! $due)>
              </div>
            </details>
          </div>
        </template>
      </div>
      @if ($due)
        <button type="button" @click="addLine()" class="inline-flex items-center gap-1 px-3 py-1.5 rounded-md border border-green-600 text-green-700 text-sm font-semibold">
          <i data-lucide="plus" class="w-4 h-4"></i> Add line
        </button>
      @endif

      <div class="rounded-lg bg-gray-50 border border-gray-200 px-3 py-2 text-sm text-gray-700 flex flex-wrap gap-x-6 gap-y-1">
        <span>Lines: <strong x-text="pillars.filter(p => p.pillar_number).length"></strong></span>
        <span>Area from bearings and distances: <strong x-text="fmtArea(traverse().area)"></strong></span>
        <span x-show="traverse().misclosure !== null" x-cloak>Gap back to the start: <strong x-text="traverse().misclosure !== null ? traverse().misclosure.toFixed(2) + ' m' : ''"></strong> <span class="text-xs text-gray-500">(irregular plots need not close)</span></span>
        <span x-show="area()" x-cloak>Area from coordinates: <strong x-text="fmtArea(area())"></strong></span>
      </div>

      <div class="grid gap-3 lg:grid-cols-3">
        <div class="lg:col-span-2">
          <label class="block text-xs font-semibold text-gray-600 mb-1">Dimensions (for irregular shapes; optional)</label>
          <textarea name="dimensions" x-model="h.dimensions" rows="2" maxlength="4000" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" placeholder="e.g. 46.13 m x 49.52 m x 20.68 m ..." @disabled(! $due)></textarea>
        </div>
        <div class="flex flex-wrap items-end gap-2">
          <div>
            <label class="block text-xs font-semibold text-gray-600 mb-1">Area (blank = worked out)</label>
            <input type="text" inputmode="decimal" name="area_value" value="{{ old('area_value', $pd && $pd->area_source === 'manual' ? $pd->area_sqm : '') }}" class="w-32 border border-gray-300 rounded-lg px-3 py-2 text-sm" @disabled(! $due)>
          </div>
          <div>
            <label class="block text-xs font-semibold text-gray-600 mb-1">Unit</label>
            <select name="area_unit" class="border border-gray-300 rounded-lg px-3 py-2 text-sm" @disabled(! $due)>
              @foreach ($units as $k => $u)<option value="{{ $k }}" @selected(old('area_unit', 'sqm') === $k)>{{ is_array($u) ? ($u['label'] ?? $k) : $u }}</option>@endforeach
            </select>
          </div>
        </div>
      </div>
      @if ($due)
        <div class="flex justify-end"><button class="px-4 py-2 rounded-lg bg-green-700 text-white text-sm font-semibold">Save Plan Description</button></div>
      @endif
    </form>
    @else
    {{-- 1. Area and beacons --}}
    <form method="POST" action="{{ route('cadastral-dept.cases.bill.area.save', $case->id) }}" class="rounded-xl border border-gray-200 bg-white p-5 space-y-4"
          x-data="cdPndArea(@js($cdPillars))" data-confirm-title="Save area and beacons?" data-confirm="The area and beacon list for {{ $case->file_number }} are saved.">
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
    @endif

    {{-- 2. Description --}}
    <div class="rounded-xl border border-gray-200 bg-white p-5 space-y-3">
      <div class="flex flex-wrap items-center justify-between gap-2">
        <h3 class="text-base font-bold text-gray-800">2. Land description</h3>
        @if ($pd && $due)
          <form method="POST" action="{{ route('cadastral-dept.cases.bill.description.store', $case->id) }}"
                @if ($pd->description_body) data-confirm-danger data-confirm-title="Rewrite the description?" data-confirm="It is rewritten from the template. Your edits will be replaced." @else data-confirm-title="Write the description?" data-confirm="It is written from the template. Check and edit it before it goes out." @endif>
            @csrf
            <button class="px-3 py-1 rounded-md border border-gray-300 text-sm font-semibold text-gray-700">{{ $pd->description_body ? 'Rewrite from template' : 'Write from template' }}</button>
          </form>
        @endif
      </div>
      @if (! $pd)
        <p class="text-sm text-gray-500">Save the area and beacons first.</p>
      @else
        <form method="POST" action="{{ route('cadastral-dept.cases.bill.description.save', $case->id) }}" class="space-y-2" data-confirm-title="Save the description?" data-confirm="The plan description for {{ $case->file_number }} is saved.">
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
          data-confirm-title="Issue the Fees P&D bill?" data-confirm="An earlier unpaid bill for {{ $case->file_number }} is cancelled and replaced.">
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
                    @cadCan('print')
                      @include('cadastral_dept.partials._row_actions', ['items' => [['Print bill', 'printer', route('cadastral-dept.bills.print', $b->id), true]]])
                    @endcadCan
                  </td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      </div>
    @endif
  </div>
  @include('admin.footer')
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
  // Plan Description lines (change request 26-10-2026). The area from the lines is worked out the
  // same way as App\Services\CadastralDept\BoundaryTraverse: walk the bearings from the first beacon,
  // shoelace over the corners; the gap back to the start is shown, never refused.
  function cdPndLines(pillars, header, o) {
    const blank = () => ({pillar_number: '', pillar_type: '', pillar_type_other: '', distance_m: '', bearing_deg: '', bearing_min: '', bearing_sec: '', ownership: o.ownership, easting: '', northing: ''});
    const keys = new Set((o.register || []).map(b => String(b).replace(/\s+/g, '').toUpperCase()));
    const num = v => { const n = parseFloat(String(v ?? '').replace(/[ ,]/g, '')); return isNaN(n) ? null : n; };
    return {
      o: o, h: header,
      pillars: (pillars && pillars.length) ? pillars.map(p => Object.assign(blank(), p)) : [blank(), blank(), blank(), blank()],
      addLine() { this.pillars.push(blank()); },
      inRegister(b) { return keys.has(String(b || '').replace(/\s+/g, '').toUpperCase()); },
      traverse() {
        const lines = this.pillars.filter(p => p.pillar_number || p.distance_m);
        if (lines.length < 3) return {area: null, misclosure: null};
        let e = 0, n = 0; const pts = [[0, 0]];
        for (const l of lines) {
          const d = num(l.distance_m), deg = num(l.bearing_deg);
          if (d === null || deg === null) return {area: null, misclosure: null};
          const t = (deg + (num(l.bearing_min) || 0) / 60 + (num(l.bearing_sec) || 0) / 3600) * Math.PI / 180;
          e += d * Math.sin(t); n += d * Math.cos(t); pts.push([e, n]);
        }
        const ring = pts; let s = 0;
        for (let i = 0; i < ring.length; i++) { const a = ring[i], b = ring[(i + 1) % ring.length]; s += a[0] * b[1] - b[0] * a[1]; }
        return {area: Math.abs(s) / 2, misclosure: Math.sqrt(e * e + n * n)};
      },
      area() {
        const p = this.pillars.map(b => [num(b.easting), num(b.northing)]).filter(x => x[0] !== null && x[1] !== null);
        if (p.length < 3) return null;
        let s = 0;
        for (let i = 0; i < p.length; i++) { const a = p[i], b = p[(i + 1) % p.length]; s += a[0] * b[1] - b[0] * a[1]; }
        return Math.abs(s) / 2;
      },
      fmtArea(a) { return a ? a.toLocaleString(undefined, {maximumFractionDigits: 2}) + ' m² (' + (a / 10000).toFixed(4) + ' ha)' : '—'; },
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
