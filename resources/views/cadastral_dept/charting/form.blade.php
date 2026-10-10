@extends('layouts.app')

@section('content')
@php
  $stage = $due['stage'];
  $stageLabel = \App\Models\CadastralDept\CadastralCharting::STAGES[$stage];
  // Start from the card being corrected; for a 2nd charting with no card yet, from the 1st.
  $seed = $existing ?: ($stage === 'second' ? $first : null);
  $yn = fn ($v) => $v === null ? '' : ($v ? '1' : '0');
  $cdInit = [
      'charted' => old('charted', $yn($seed?->charted)),
      'falls' => old('falls_on_existing', $yn($seed?->falls_on_existing)),
      'agrees' => old('agrees_with_first', $yn($existing?->agrees_with_first)),
      'matches' => old('matches_record', $yn($existing?->matches_record)),
      'reason' => old('not_charted_reason', $seed && $seed->not_charted_reason && ! in_array($seed->not_charted_reason, $reasons, true) ? 'other' : ($seed->not_charted_reason ?? '')),
      'files' => old('existing_files', $seed && $seed->existing_files ? array_map('trim', explode(',', $seed->existing_files)) : []),
      'beacons' => old('beacons', $seed ? $seed->beacons->map(fn ($b) => ['beacon' => $b->beacon, 'easting' => (string) $b->easting, 'northing' => (string) $b->northing, 'remarks' => $b->remarks])->all() : []),
  ];
@endphp
<div class="flex-1 overflow-auto bg-gray-50">
  @include('admin.header', ['PageTitle' => 'Charting Card', 'PageDescription' => $case->case_ref . ' · ' . $stageLabel])

  <div class="p-6 max-w-5xl mx-auto">
    @include('cadastral_dept.partials._nav')

    <form method="POST" action="{{ route('cadastral-dept.cases.charting.store', $case->id) }}" class="space-y-5"
          x-data="cdChart(@js($cdInit))">
      @csrf

      <div class="rounded-xl border border-gray-200 bg-white p-5">
        <div class="flex flex-wrap items-baseline justify-between gap-2 mb-4">
          <div>
            <div class="text-xs font-bold uppercase text-gray-500">Charting No.</div>
            <div class="text-xl font-black font-mono text-gray-800">{{ $existing ? $existing->charting_no . ' · new version ' . ($existing->version + 1) : 'Given on save' }}</div>
          </div>
          <span class="px-3 py-1 rounded-full text-xs font-semibold bg-blue-100 text-blue-800">{{ $def->workflows()[$case->workflow]['short'] }} · {{ $stageLabel }} ({{ $case->deskShort() }})</span>
        </div>
        {{-- From the records, locked. Property order: Plot No., District, LGA, Location. --}}
        <dl class="grid gap-x-6 gap-y-2 text-sm sm:grid-cols-4">
          <div><dt class="text-xs uppercase text-gray-500">File No.</dt><dd class="font-mono font-semibold">{{ $case->file_number }}</dd></div>
          <div><dt class="text-xs uppercase text-gray-500">FMC No.</dt><dd class="font-mono">{{ $fmc->fmc_no ?? '—' }}</dd></div>
          <div class="sm:col-span-2"><dt class="text-xs uppercase text-gray-500">File Title</dt><dd>{{ $case->file_title ?: '—' }}</dd></div>
          <div><dt class="text-xs uppercase text-gray-500">Plot No.</dt><dd>{{ $case->plot_no ?: '—' }}</dd></div>
          <div><dt class="text-xs uppercase text-gray-500">District</dt><dd>{{ $case->district ?: '—' }}</dd></div>
          <div><dt class="text-xs uppercase text-gray-500">LGA</dt><dd>{{ $case->lga ?: '—' }}</dd></div>
          <div><dt class="text-xs uppercase text-gray-500">Location</dt><dd>{{ $case->location ?: '—' }}</dd></div>
        </dl>
      </div>

      @if ($stage === 'second' && $first)
        <div class="rounded-xl border border-blue-200 bg-blue-50 p-4 text-sm">
          <div class="text-xs font-bold uppercase text-blue-800 mb-1">1st charting (AD Cad Info), read only</div>
          <span class="font-mono font-semibold">{{ $first->charting_no }}</span> ·
          Charted: {{ $first->charted ? 'Yes' : 'No' }} ·
          Falls on existing plot: {{ $first->falls_on_existing ? 'Yes (' . $first->existing_files . ')' : 'No' }}
          @if ($first->sheet_no) · Sheet: {{ ucfirst((string) $first->sheet_type) }} {{ $first->sheet_no }} @endif
          · {{ optional($first->officer)->name }}, {{ $first->charted_at->format('d M Y') }}
          <a href="{{ route('cadastral-dept.charting.show', $first->id) }}" class="ml-1 text-blue-700 hover:underline">Open</a>
        </div>
      @endif

      <div class="rounded-xl border border-gray-200 bg-white p-5 space-y-5">
        <h3 class="text-xs font-bold uppercase tracking-wide text-gray-500">{{ $stage === 'check' ? 'Charting check' : 'Charting result' }}</h3>

        @if ($stage === 'check')
          <div>
            <div class="text-sm font-semibold text-gray-700 mb-2">Charting information matches the record? <span class="text-red-600">*</span></div>
            @include('cadastral_dept.charting._yn', ['field' => 'matches_record', 'model' => 'matches'])
            <div class="mt-3" x-show="matches === '0'" x-cloak>
              <label class="block text-sm font-semibold text-gray-700 mb-1">What does not match? <span class="text-red-600">*</span></label>
              <textarea name="match_note" rows="2" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" :disabled="matches !== '0'">{{ old('match_note', $existing->match_note ?? '') }}</textarea>
            </div>
          </div>
        @else
          @if ($stage === 'second')
            <div>
              <div class="text-sm font-semibold text-gray-700 mb-2">Agrees with the 1st charting? <span class="text-red-600">*</span></div>
              @include('cadastral_dept.charting._yn', ['field' => 'agrees_with_first', 'model' => 'agrees'])
              <div class="mt-3" x-show="agrees === '0'" x-cloak>
                <label class="block text-sm font-semibold text-gray-700 mb-1">What is different? <span class="text-red-600">*</span></label>
                <textarea name="agree_note" rows="2" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" :disabled="agrees !== '0'">{{ old('agree_note', $existing->agree_note ?? '') }}</textarea>
              </div>
            </div>
          @endif

          <div>
            <div class="text-sm font-semibold text-gray-700 mb-2">Record charted? <span class="text-red-600">*</span></div>
            @include('cadastral_dept.charting._yn', ['field' => 'charted', 'model' => 'charted'])
            <div class="mt-3 grid gap-3 sm:grid-cols-2" x-show="charted === '0'" x-cloak>
              <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1">Why not? <span class="text-red-600">*</span></label>
                <select name="not_charted_reason" x-model="reason" :disabled="charted !== '0'" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                  <option value="">Choose…</option>
                  @foreach ($reasons as $r)<option value="{{ $r }}">{{ $r }}</option>@endforeach
                  <option value="other">Other</option>
                </select>
              </div>
              <div x-show="reason === 'other'">
                <label class="block text-sm font-semibold text-gray-700 mb-1">Specify <span class="text-red-600">*</span></label>
                <input type="text" name="not_charted_other" maxlength="255" :disabled="charted !== '0' || reason !== 'other'"
                       value="{{ old('not_charted_other', $cdInit['reason'] === 'other' ? ($seed->not_charted_reason ?? '') : '') }}"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
              </div>
            </div>
          </div>

          <div>
            <div class="text-sm font-semibold text-gray-700 mb-2">Falls on an existing plot? <span class="text-red-600" x-show="charted === '1'">*</span></div>
            @if ($suggestions)
              <div class="mb-2 rounded-lg bg-yellow-50 border border-yellow-200 px-3 py-2 text-sm text-yellow-900">
                KLAES check: {{ count($suggestions) }} other charted file(s) on plot {{ $case->plot_no }}{{ $case->district ? ' in ' . $case->district : '' }}:
                <span class="font-mono">{{ implode(', ', $suggestions) }}</span>. Please confirm on the sheet.
              </div>
            @endif
            @include('cadastral_dept.charting._yn', ['field' => 'falls_on_existing', 'model' => 'falls'])
            <div class="mt-3 space-y-3" x-show="falls === '1'" x-cloak>
              <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1">Which file(s)? <span class="text-red-600">*</span></label>
                <div class="flex flex-wrap gap-2 mb-2">
                  <template x-for="(f, i) in files" :key="f">
                    <span class="inline-flex items-center gap-1 px-2 py-1 rounded bg-gray-100 text-sm font-mono">
                      <span x-text="f"></span>
                      <input type="hidden" name="existing_files[]" :value="f" :disabled="falls !== '1'">
                      <button type="button" @click="files.splice(i, 1)" class="text-gray-500 hover:text-red-600" aria-label="Remove">&times;</button>
                    </span>
                  </template>
                  @foreach ($suggestions as $s)
                    <button type="button" x-show="!files.includes(@js($s))" @click="files.push(@js($s))"
                            class="px-2 py-1 rounded border border-dashed border-yellow-400 text-xs text-yellow-900">+ {{ $s }}</button>
                  @endforeach
                </div>
                <button type="button" @click="pick()" class="px-3 py-2 rounded-lg bg-blue-600 text-white text-sm font-semibold inline-flex items-center gap-2">
                  <i data-lucide="folder-search" class="w-4 h-4"></i> Select File Number
                </button>
              </div>
              <div class="max-w-xs">
                <label class="block text-sm font-semibold text-gray-700 mb-1">How much?</label>
                <select name="overlap_extent" :disabled="falls !== '1'" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                  <option value="">Choose…</option>
                  @foreach (['fully' => 'Fully', 'partly' => 'Partly'] as $k => $l)
                    <option value="{{ $k }}" @selected(old('overlap_extent', $seed->overlap_extent ?? '') === $k)>{{ $l }}</option>
                  @endforeach
                </select>
              </div>
            </div>
          </div>
        @endif

        <div>
          <label class="block text-sm font-semibold text-gray-700 mb-1">Description</label>
          <textarea name="description" rows="2" placeholder="What the charting shows: boundaries, neighbours, road access…"
                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">{{ old('description', $seed->description ?? '') }}</textarea>
        </div>
      </div>

      <div class="rounded-xl border border-gray-200 bg-white p-5 space-y-4">
        <h3 class="text-xs font-bold uppercase tracking-wide text-gray-500">Sheet and plan references</h3>
        <div class="grid gap-3 sm:grid-cols-4">
          <div>
            <label class="block text-sm font-semibold text-gray-700 mb-1">Sheet</label>
            <select name="sheet_type" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
              <option value="">—</option>
              @foreach (['metric' => 'Metric', 'imperial' => 'Imperial'] as $k => $l)
                <option value="{{ $k }}" @selected(old('sheet_type', $seed->sheet_type ?? '') === $k)>{{ $l }}</option>
              @endforeach
            </select>
          </div>
          <div><label class="block text-sm font-semibold text-gray-700 mb-1">Sheet No. / Index</label>
            <input type="text" name="sheet_no" maxlength="50" value="{{ old('sheet_no', $seed->sheet_no ?? '') }}" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm uppercase"></div>
          <div><label class="block text-sm font-semibold text-gray-700 mb-1">Layout name / No.</label>
            <input type="text" name="layout" maxlength="150" value="{{ old('layout', $seed->layout ?? '') }}" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm"></div>
          <div><label class="block text-sm font-semibold text-gray-700 mb-1">Approved plan / TP No.</label>
            <input type="text" name="plan_no" maxlength="100" value="{{ old('plan_no', $seed->plan_no ?? '') }}" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm uppercase"></div>
        </div>
      </div>

      <div class="rounded-xl border border-gray-200 bg-white p-5 space-y-3">
        <div class="flex flex-wrap items-center justify-between gap-2">
          <h3 class="text-xs font-bold uppercase tracking-wide text-gray-500">Coordinates (beacons) · UTM Zone 32N, Minna datum</h3>
          <span class="text-sm text-gray-600">Area: <strong x-text="area() ? area().toLocaleString(undefined, {maximumFractionDigits: 1}) + ' m²' : '—'"></strong></span>
        </div>
        <div class="overflow-x-auto">
          <table class="min-w-full text-sm">
            <thead class="text-left text-xs uppercase text-gray-500"><tr><th class="py-1 pr-2">S/N</th><th class="py-1 pr-2">Beacon</th><th class="py-1 pr-2">Easting</th><th class="py-1 pr-2">Northing</th><th class="py-1 pr-2">Remarks</th><th></th></tr></thead>
            <tbody>
              <template x-for="(b, i) in beacons" :key="i">
                <tr>
                  <td class="py-1 pr-2 text-gray-500" x-text="i + 1"></td>
                  <td class="py-1 pr-2"><input type="text" :name="'beacons[' + i + '][beacon]'" x-model="b.beacon" class="w-24 border border-gray-300 rounded px-2 py-1 uppercase"></td>
                  <td class="py-1 pr-2"><input type="text" inputmode="decimal" :name="'beacons[' + i + '][easting]'" x-model="b.easting" class="w-36 border border-gray-300 rounded px-2 py-1"></td>
                  <td class="py-1 pr-2"><input type="text" inputmode="decimal" :name="'beacons[' + i + '][northing]'" x-model="b.northing" class="w-36 border border-gray-300 rounded px-2 py-1"></td>
                  <td class="py-1 pr-2"><input type="text" :name="'beacons[' + i + '][remarks]'" x-model="b.remarks" class="w-48 border border-gray-300 rounded px-2 py-1"></td>
                  <td class="py-1"><button type="button" @click="beacons.splice(i, 1)" class="text-gray-400 hover:text-red-600" aria-label="Remove beacon">&times;</button></td>
                </tr>
              </template>
            </tbody>
          </table>
        </div>
        <button type="button" @click="beacons.push({beacon: '', easting: '', northing: '', remarks: ''})" class="px-3 py-1 rounded-md border border-gray-300 text-sm font-semibold text-gray-700">+ Add beacon</button>
        <p class="text-xs text-gray-500">Optional. With 3 or more beacons the area is worked out and saved.</p>
      </div>

      <div class="rounded-xl border border-gray-200 bg-white p-5">
        <label class="block text-sm font-semibold text-gray-700 mb-1">Remarks</label>
        <textarea name="remarks" rows="2" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">{{ old('remarks', $seed->remarks ?? '') }}</textarea>
        <p class="text-xs text-gray-500 mt-2">Charted by and the date and time are recorded on save.</p>
      </div>

      <div class="flex justify-end gap-2">
        <a href="{{ route('cadastral-dept.cases.show', $case->id) }}" class="px-4 py-2 rounded-lg border border-gray-300 bg-white text-sm font-semibold text-gray-700">Cancel</a>
        <button class="px-5 py-2 rounded-lg bg-green-700 text-white text-sm font-bold">{{ $existing ? 'Save new version' : 'Save card' }}</button>
      </div>
    </form>
  </div>
</div>

@include('components.global-fileno-modal')
@endsection

@push('scripts')
<script src="{{ asset('js/global-fileno-modal.js') }}"></script>
<script>
  function cdChart(init) {
    return {
      charted: init.charted, falls: init.falls, agrees: init.agrees, matches: init.matches,
      reason: init.reason, files: init.files || [], beacons: init.beacons || [],
      pick() {
        GlobalFileNoModal.open({ callback: (d) => { const n = (d.fileNumber || '').trim(); if (n && !this.files.includes(n)) this.files.push(n); } });
      },
      // Shoelace over eastings and northings, as the server works it out.
      area() {
        const p = this.beacons.map(b => [parseFloat(String(b.easting).replace(/[ ,]/g, '')), parseFloat(String(b.northing).replace(/[ ,]/g, ''))])
          .filter(x => !isNaN(x[0]) && !isNaN(x[1]));
        if (p.length < 3) return null;
        let s = 0;
        for (let i = 0; i < p.length; i++) { const a = p[i], b = p[(i + 1) % p.length]; s += a[0] * b[1] - b[0] * a[1]; }
        return Math.abs(s) / 2;
      },
    };
  }
</script>
@endpush
