@extends('layouts.app')

{{-- The Yes/No form ("Report on Application – Cadastral Department") at AD Cad Report.
     Copied from cadastral_module/partials/_report_application_form (clone per module). --}}
@section('content')
@php
  $F = \App\Models\CadastralDept\CadastralYesNoForm::class;
  $v = fn (string $c) => old($c, $form?->{$c});
  // The reference lines are filled from the file (refs(): the file number on the line its type says,
  // e.g. R. of O. No. for an ordinary file); a line holding the file number is locked (user, 2026-10-12).
  $fileRef = fn (string $prefix) => \App\Services\CadastralDept\YesNoForms::withoutPrefix($case->file_number, $prefix);
  // Square metres entered, hectares worked out (m2 / 10,000), no acres (transcript, 2026-10-16).
  $areaValue = old('area_applied_sqm', $form?->area_applied_ha !== null ? round((float) $form->area_applied_ha * 10000, 2) : null);
  $areaPrefilled = ($areaValue === null || $areaValue === '') && $area['ha'] !== null;
  if ($areaPrefilled) {
      $areaValue = round($area['ha'] * 10000, 2);
  }
  $purpose = $v('q4_purpose');
  // Q4(c) backfilled from the file's land use until the officer saves one (user, 2026-10-28).
  $purposePrefilled = ($purpose === null || $purpose === '') && ($landUse['value'] ?? null) !== null;
  if ($purposePrefilled) {
      $purpose = $landUse['value'];
  }
  $purposeIsOther = $purpose !== null && $purpose !== '' && $purpose !== 'Other' && ! in_array($purpose, $F::PURPOSES, true);
  $cdYn = [
      'q2_ground_open' => $v('q2_ground_open'), 'q3_beaconed' => $v('q3_beaconed'),
      'q4_town_plan' => $v('q4_town_plan'), 'q5_previous_title' => $v('q5_previous_title'),
      'purpose' => $purposeIsOther ? 'Other' : $purpose,
      'sqm' => $areaValue === null ? '' : (string) $areaValue,
      // Q8 from dimensions (2026-10-28): the form's own, else the file's plan description's.
      // Save stays disabled until something is changed (user, 2026-10-28).
      'dirty' => false,
      'dimsText' => (string) (old('area_dimensions', $form?->area_dimensions ?? null)
          ?: (\App\Models\Cadastral\CadastralPlanDescription::query()->where('file_number', $case->file_number)->whereNotNull('dimensions')->orderByDesc('id')->value('dimensions') ?? '')),
  ];
  $input = 'w-full rounded-lg border border-gray-300 px-3 py-2 text-sm disabled:bg-gray-100';
  $label = 'block text-xs font-medium text-gray-600 mb-1';
@endphp
<div class="flex-1 overflow-auto bg-gray-50">
  @include('admin.header', ['PageTitle' => 'Yes/No Form', 'PageDescription' => $case->case_ref . ' · ' . $case->file_number])

  <div class="p-6 space-y-6">
    @include('cadastral_dept.partials._nav')

    <div class="flex flex-wrap items-center gap-2">
      <a href="{{ route('cadastral-dept.cases.show', $case->id) }}" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg border border-gray-300 bg-white text-sm font-semibold text-gray-700">
        <i data-lucide="arrow-left" class="w-4 h-4"></i> Back to {{ $case->case_ref }}
      </a>
      @if ($form)
        <span class="px-3 py-1 rounded-full text-xs font-semibold {{ $form->isComplete() ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800' }}">
          {{ $form->isComplete() ? 'Complete' : 'Incomplete' }}
        </span>
      @endif
      @cadCan('print')
        <a href="{{ route('cadastral-dept.cases.yes-no.print', $case->id) }}" target="_blank" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-gray-800 text-white text-sm font-semibold">
          <i data-lucide="printer" class="w-4 h-4"></i> Generate Yes/No Form
        </a>
      @endcadCan
    </div>

    <div class="rounded-xl border border-gray-200 bg-white p-5">
      <dl class="grid gap-3 sm:grid-cols-5 text-sm">
        <div><dt class="text-xs text-gray-500">File No.</dt><dd class="font-mono">{{ $case->file_number }}</dd></div>
        <div><dt class="text-xs text-gray-500">Plot No.</dt><dd>{{ $case->plot_no ?: '—' }}</dd></div>
        <div><dt class="text-xs text-gray-500">District</dt><dd>{{ $case->district ?: '—' }}</dd></div>
        <div><dt class="text-xs text-gray-500">LGA</dt><dd>{{ $case->lga ?: '—' }}</dd></div>
        <div><dt class="text-xs text-gray-500">Location</dt><dd>{{ $case->location ?: '—' }}</dd></div>
      </dl>
    </div>

    @php
      // Next card (change request 26-10-2026): Land 12 and the Yes/No form are ticked from here,
      // so the officer is not sent back to the file page to find "Mark done".
      $cdStepTasks = $case->isOpen() && $def->hasStep($case->workflow, $case->current_step) ? ($def->step($case->workflow, $case->current_step)['tasks'] ?? []) : [];
      $cdDoneKeys = $case->isOpen() ? $case->doneTaskKeys($case->current_step) : [];
      $cdLand12Due = isset($cdStepTasks['land12']) && ! in_array('land12', $cdDoneKeys, true);
      $cdYnDue = isset($cdStepTasks[\App\Services\CadastralDept\YesNoForms::TASK]) && ! in_array(\App\Services\CadastralDept\YesNoForms::TASK, $cdDoneKeys, true);
    @endphp
    @if ($cdLand12Due || ($cdYnDue && $form?->isComplete()))
      <div class="rounded-xl border-2 border-green-300 bg-green-50 p-4 flex flex-wrap items-center gap-3">
        <div class="text-sm font-semibold text-green-900 mr-auto">Next</div>
        @if ($cdLand12Due)
          <span class="text-sm text-gray-700">Land 12:</span>
          @include('cadastral_dept.partials._land12_button')
        @endif
        @if ($cdYnDue && $form?->isComplete())
          <form method="POST" action="{{ route('cadastral-dept.cases.tasks.mark-done', $case->id) }}"
                data-confirm-title="Mark the Yes/No form done?" data-confirm="The Yes/No form is then locked and cannot be changed. You go back to {{ $case->case_ref }} for the next task.">
            @csrf
            <input type="hidden" name="task" value="{{ \App\Services\CadastralDept\YesNoForms::TASK }}">
            <input type="hidden" name="return" value="case">
            <button class="px-3 py-1.5 rounded-lg bg-green-700 text-white text-sm font-semibold">Mark the Yes/No form done</button>
          </form>
        @endif
      </div>
    @endif

    @if (! $installed)
      <div class="rounded-lg border border-yellow-300 bg-yellow-50 px-4 py-3 text-sm text-yellow-900">
        <strong>Not installed yet.</strong> The Yes/No form table (migration <code>2026_10_11_090000_create_cadastral_dept_yes_no_forms</code>) has not been created.
        The Yes/No form can still be generated, with the file references and area filled and the questions blank.
      </div>
    @elseif (! $editable)
      <div class="rounded-lg border border-yellow-300 bg-yellow-50 px-4 py-3 text-sm text-yellow-900">
        The Yes/No form is filled at AD Cad Report, before "Yes/No form" is ticked, so this page is read only now.
      </div>
    @endif

    @if ($installed)
    <form method="POST" action="{{ route('cadastral-dept.cases.yes-no.save', $case->id) }}" x-data='{!! json_encode($cdYn, JSON_HEX_APOS | JSON_HEX_QUOT) !!}'
          @input="dirty = true" @change="dirty = true" @click="if ($event.target.closest('button[type=button]')) dirty = true"
          class="rounded-xl border border-gray-200 bg-white" data-confirm-title="Save the Yes/No form?" data-confirm="The answers for {{ $case->file_number }} are saved.">
      @csrf
      <fieldset @disabled(! $editable) class="divide-y divide-gray-100">
        <div class="p-5 grid gap-4 sm:grid-cols-4">
          {{-- Only the reference line the file number goes on is shown (user, 2026-10-28); the others
               keep any saved value as hidden fields, and print as the form always has. --}}
          @php
            $cdLine = \App\Services\CadastralDept\YesNoForms::line($case);
            $cdRefFields = [
              'lkn' => ['lkn_no', 'R. of O. No. LKN/', 'LKN'],
              'sltr' => ['sltr_no', 'SLTR/', 'SLTR'],
              'gkn' => ['govt_item_no', 'Government Item No. GKN/', 'GKN'],
              'sit' => ['sit_no', 'SIT/', 'SIT'],
            ];
          @endphp
          @foreach ($cdRefFields as $cdKey => [$cdName, $cdLabel, $cdPrefix])
            @php $cdRef = old($cdName, $refs[$cdKey]); @endphp
            @if ($cdKey === $cdLine)
              @php $cdLock = $cdRef !== null && $cdRef === $fileRef($cdPrefix); @endphp
              <div><label class="{{ $label }}">{{ $cdLabel }}</label><input type="text" name="{{ $cdName }}" maxlength="100" value="{{ $cdRef }}" @if ($cdLock) readonly title="Filled from the file number" @endif class="{{ $input }} {{ $cdLock ? 'bg-gray-100 text-gray-700' : '' }}"></div>
            @else
              <input type="hidden" name="{{ $cdName }}" value="{{ $cdRef }}">
            @endif
          @endforeach
        </div>

        @php
          $yn = function (string $name, bool $model = false) use ($v) {
              $cur = $v($name);
              $bind = $model ? ' x-model="' . e($name) . '"' : '';
              return '<span class="inline-flex gap-4 text-sm font-medium whitespace-nowrap">'
                  . '<label class="inline-flex items-center gap-1"><input type="radio" name="' . e($name) . '" value="Yes"' . ($cur === 'Yes' ? ' checked' : '') . $bind . '> Yes</label>'
                  . '<label class="inline-flex items-center gap-1"><input type="radio" name="' . e($name) . '" value="No"' . ($cur === 'No' ? ' checked' : '') . $bind . '> No</label>'
                  . '</span>';
          };
          $q = 'flex flex-wrap items-center justify-between gap-3 text-sm font-semibold text-gray-800';
          $sub = 'mt-3 ml-0 sm:ml-6 grid gap-3 sm:grid-cols-2';
        @endphp

        <div class="p-5"><div class="{{ $q }}"><span>{{ $F::QUESTIONS['q1_plan_sufficient'] }}</span>{!! $yn('q1_plan_sufficient') !!}</div></div>

        <div class="p-5">
          <div class="{{ $q }}"><span>{{ $F::QUESTIONS['q2_ground_open'] }}</span>{!! $yn('q2_ground_open', true) !!}</div>
          <div class="{{ $sub }}" x-show="q2_ground_open === 'No'" x-cloak>
            <div class="sm:col-span-2"><label class="{{ $label }}">If No, what title or application lies over the same land? *</label>
              <input type="text" name="q2_overlapping_title" maxlength="500" value="{{ $v('q2_overlapping_title') }}" class="{{ $input }}"></div>
          </div>
        </div>

        <div class="p-5">
          <div class="{{ $q }}"><span>{{ $F::QUESTIONS['q3_beaconed'] }}</span>{!! $yn('q3_beaconed', true) !!}</div>
          <div class="{{ $sub }}" x-show="q3_beaconed === 'Yes'" x-cloak>
            <div><label class="{{ $label }}">If Yes: (a) Tracing No. *</label><input type="text" name="q3_tracing_no" maxlength="100" value="{{ $v('q3_tracing_no') }}" class="{{ $input }}"></div>
            <div><label class="{{ $label }}">(b) Deposition Plan No.</label><input type="text" name="q3_deposition_plan_no" maxlength="100" value="{{ $v('q3_deposition_plan_no') }}" class="{{ $input }}"></div>
          </div>
          <div class="{{ $sub }}" x-show="q3_beaconed === 'No'" x-cloak>
            <div><label class="{{ $label }}">If No: (a) It lies on unapproved Town Plan No.</label><input type="text" name="q3_unapproved_town_plan_no" maxlength="100" value="{{ $v('q3_unapproved_town_plan_no') }}" class="{{ $input }}"></div>
            <div><label class="{{ $label }}">(b) It lies on Lay-Out No.</label><input type="text" name="q3_layout_no" maxlength="100" value="{{ $v('q3_layout_no') }}" class="{{ $input }}"></div>
            <div class="sm:col-span-2 flex flex-wrap items-center justify-between gap-3 text-sm"><span>(c) Is a separate survey required? *</span>{!! $yn('q3_separate_survey') !!}</div>
          </div>
        </div>

        <div class="p-5">
          <div class="{{ $q }}"><span>{{ $F::QUESTIONS['q4_town_plan'] }}</span>{!! $yn('q4_town_plan', true) !!}</div>
          <div class="{{ $sub }}" x-show="q4_town_plan === 'Yes'" x-cloak>
            <div><label class="{{ $label }}">If Yes: (a) The Town Plan No. is *</label><input type="text" name="q4_town_plan_no" maxlength="100" value="{{ $v('q4_town_plan_no') }}" class="{{ $input }}"></div>
            <div class="sm:col-span-2 flex flex-wrap items-center justify-between gap-3 text-sm"><span>(b) Does the shape of the plot agree with the Town Plan? *</span>{!! $yn('q4_shape_agrees') !!}</div>
            <div><label class="{{ $label }}">(c) The application is for *</label>
              <select name="q4_purpose" x-model="purpose" class="{{ $input }}">
                <option value="">Choose…</option>
                @foreach ($F::PURPOSES as $p)
                  <option value="{{ $p }}" @selected($cdYn['purpose'] === $p)>{{ $p }}</option>
                @endforeach
                <option value="Other" @selected($cdYn['purpose'] === 'Other')>Other</option>
              </select>
              @if ($purposePrefilled)
                <p class="mt-1 text-xs text-blue-700">Filled from the {{ $landUse['source'] }} ({{ $landUse['value'] }}). Change it if the application is for another use.</p>
              @endif
            </div>
            <div x-show="purpose === 'Other'" x-cloak>
              <label class="{{ $label }}">Specify the other purpose *</label>
              <input type="text" name="q4_purpose_other" maxlength="100" value="{{ old('q4_purpose_other', $purposeIsOther ? $purpose : '') }}" x-bind:disabled="purpose !== 'Other'" class="{{ $input }}">
            </div>
            <div class="sm:col-span-2 flex flex-wrap items-center justify-between gap-3 text-sm"><span>(d) Is the area shown on the Town Plan for the purpose applied? *</span>{!! $yn('q4_area_for_purpose') !!}</div>
          </div>
        </div>

        <div class="p-5">
          <div class="{{ $q }}"><span>{{ $F::QUESTIONS['q5_previous_title'] }}</span>{!! $yn('q5_previous_title', true) !!}</div>
          <div class="{{ $sub }}" x-show="q5_previous_title === 'Yes'" x-cloak>
            <div class="sm:col-span-2"><label class="{{ $label }}">If Yes, details *</label><textarea name="q5_details" rows="2" maxlength="1000" class="{{ $input }}">{{ $v('q5_details') }}</textarea></div>
          </div>
        </div>

        <div class="p-5"><div class="{{ $q }}"><span>{{ $F::QUESTIONS['q6_railway'] }}</span>{!! $yn('q6_railway') !!}</div></div>
        <div class="p-5"><div class="{{ $q }}"><span>{{ $F::QUESTIONS['q7_trunk_road'] }}</span>{!! $yn('q7_trunk_road') !!}</div></div>

        <div class="p-5" x-data="cdYnDims(dimsText)" x-init="fromDims = total() > 0; sync()">
          <div class="{{ $q }}"><span>8. Area applied for</span></div>
          {{-- Worked out from dimensions (user, 2026-10-28): rows of length x width in metres, + to add a
               row; the total fills Square metres. With no rows filled, the area can be typed. --}}
          <div class="mt-2 mb-3">
            <label class="{{ $label }}">Dimensions</label>
            <div class="space-y-2">
              <template x-for="(d, i) in dims" :key="i">
                <div class="flex flex-wrap items-center gap-2">
                  <input type="text" inputmode="decimal" x-model="d.l" @input="sync()" placeholder="Length" aria-label="Length (m)"
                         class="w-28 rounded-lg border border-gray-300 px-3 py-2 text-sm font-mono">
                  <span class="text-gray-500">m ×</span>
                  <input type="text" inputmode="decimal" x-model="d.w" @input="sync()" placeholder="Width" aria-label="Width (m)"
                         class="w-28 rounded-lg border border-gray-300 px-3 py-2 text-sm font-mono">
                  <span class="text-gray-500">m</span>
                  <span class="text-sm text-gray-600" x-show="rowArea(d)" x-text="'= ' + rowArea(d).toLocaleString(undefined, {maximumFractionDigits: 2}) + ' m²'"></span>
                  <button type="button" @click="dims.splice(i, 1); sync()" x-show="dims.length > 1" aria-label="Remove this row"
                          class="inline-flex items-center justify-center w-8 h-8 rounded-full text-red-600 hover:bg-red-50">&times;</button>
                </div>
              </template>
            </div>
            <button type="button" @click="dims.push({l: '', w: ''})"
                    class="mt-2 inline-flex items-center gap-1 px-3 py-1.5 rounded-lg border border-green-600 text-green-700 text-sm font-semibold hover:bg-green-50">+ Add</button>
            <input type="hidden" name="area_dimensions" :value="text()">
          </div>
          <div class="{{ $sub }}">
            <div>
              <label class="{{ $label }}">Square metres (m²)</label>
              <input type="number" step="0.01" min="0" name="area_applied_sqm" x-model="sqm" value="{{ $areaValue }}" :readonly="total() > 0"
                     :class="total() > 0 ? 'bg-gray-100 text-gray-700' : ''" class="{{ $input }}">
              <p class="mt-1 text-xs text-gray-500">
                <span x-show="total() > 0">Worked out from the dimensions.</span>
                <span x-show="!(total() > 0)">
                @if ($areaPrefilled) Filled in from {{ $area['source'] }}; saved when you save the form.
                @elseif ($area['source'] === 'entered') As entered.
                @else No plan description or charting card area for this file yet.
                @endif
                </span>
              </p>
            </div>
            <div>
              <label class="{{ $label }}">Hectares (m² ÷ 10,000)</label>
              <input type="text" disabled x-bind:value="sqm === '' || isNaN(parseFloat(sqm)) ? '' : (parseFloat(sqm) / 10000).toFixed(4)" class="{{ $input }}">
            </div>
          </div>
        </div>
      </fieldset>

      @if ($editable)
        <div class="p-5 flex flex-wrap items-center justify-between gap-3 border-t border-gray-100">
          <span class="text-xs text-gray-500" x-text="dirty ? 'With questions 1–7 all answered, saving takes you to the file to mark the Yes/No form done.' : @js($form ? 'Saved. Change an answer to save again.' : 'Answer questions 1–7, then save.')"></span>
          @cadCan('edit')
            <button type="submit" :disabled="!dirty" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-blue-600 text-white text-sm font-semibold disabled:opacity-50 disabled:cursor-not-allowed">
              <i data-lucide="save" class="w-4 h-4"></i> Save the Yes/No form
            </button>
          @endcadCan
        </div>
      @endif
    </form>
    @endif
  </div>
  @include('admin.footer')
</div>
@endsection

@push('scripts')
<script>
  // Yes/No form Q8: dimensions rows (length x width, metres) and their total in m2 (2026-10-28).
  function cdYnDims(text) {
    const num = v => { const n = parseFloat(String(v ?? '').replace(/[ ,]/g, '')); return isNaN(n) ? null : n; };
    const rows = String(text || '').split(/[;\n]+/).map(r => r.match(/([\d.,]+)\s*m?\s*[x×*]\s*([\d.,]+)/i)).filter(Boolean).map(m => ({l: m[1], w: m[2]}));
    return {
      dims: rows.length ? rows : [{l: '', w: ''}],
      rowArea(d) { const l = num(d.l), w = num(d.w); return l && w ? l * w : 0; },
      total() { return this.dims.reduce((s, d) => s + this.rowArea(d), 0); },
      text() { return this.dims.filter(d => num(d.l) && num(d.w)).map(d => num(d.l) + ' m x ' + num(d.w) + ' m').join('; '); },
      // The total fills Square metres; when the last dimension is cleared, the figure it gave is cleared too.
      fromDims: false,
      sync() {
        const t = this.total();
        if (t) { this.sqm = String(+t.toFixed(2)); this.fromDims = true; }
        else if (this.fromDims) { this.sqm = ''; this.fromDims = false; }
      },
    };
  }
</script>
@endpush
