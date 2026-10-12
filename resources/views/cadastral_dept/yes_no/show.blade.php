@extends('layouts.app')

{{-- The Yes/No form ("Report on Application – Cadastral Department") at AD Cad Report.
     Copied from cadastral_module/partials/_report_application_form (clone per module). --}}
@section('content')
@php
  $F = \App\Models\CadastralDept\CadastralYesNoForm::class;
  $v = fn (string $c) => old($c, $form?->{$c});
  $areaValue = old('area_applied_ha', $form?->area_applied_ha);
  $areaPrefilled = ($areaValue === null || $areaValue === '') && $area['ha'] !== null;
  if ($areaPrefilled) {
      $areaValue = $area['ha'];
  }
  $purpose = $v('q4_purpose');
  $purposeIsOther = $purpose !== null && $purpose !== '' && $purpose !== 'Other' && ! in_array($purpose, $F::PURPOSES, true);
  $cdYn = [
      'q2_ground_open' => $v('q2_ground_open'), 'q3_beaconed' => $v('q3_beaconed'),
      'q4_town_plan' => $v('q4_town_plan'), 'q5_previous_title' => $v('q5_previous_title'),
      'purpose' => $purposeIsOther ? 'Other' : $purpose,
      'ha' => $areaValue === null ? '' : (string) $areaValue,
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
      @canDo('Cad - Records', 'print')
        <a href="{{ route('cadastral-dept.cases.yes-no.print', $case->id) }}" target="_blank" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-gray-800 text-white text-sm font-semibold">
          <i data-lucide="printer" class="w-4 h-4"></i> Print the official form
        </a>
      @endcanDo
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

    @if (! $installed)
      <div class="rounded-lg border border-yellow-300 bg-yellow-50 px-4 py-3 text-sm text-yellow-900">
        <strong>Not installed yet.</strong> The Yes/No form table (migration <code>2026_10_11_090000_create_cadastral_dept_yes_no_forms</code>) has not been created.
        The official form still prints, with the file references and area filled and the questions blank.
      </div>
    @elseif (! $editable)
      <div class="rounded-lg border border-yellow-300 bg-yellow-50 px-4 py-3 text-sm text-yellow-900">
        The Yes/No form is filled at AD Cad Report, before "Land 12 and Yes/No form" is ticked, so this page is read only now.
      </div>
    @endif

    @if ($installed)
    <form method="POST" action="{{ route('cadastral-dept.cases.yes-no.save', $case->id) }}" x-data='{!! json_encode($cdYn, JSON_HEX_APOS | JSON_HEX_QUOT) !!}'
          class="rounded-xl border border-gray-200 bg-white" data-confirm-title="Save the Yes/No form?" data-confirm="The answers for {{ $case->file_number }} are saved.">
      @csrf
      <fieldset @disabled(! $editable) class="divide-y divide-gray-100">
        <div class="p-5 grid gap-4 sm:grid-cols-4">
          <div><label class="{{ $label }}">R. of O. No. LKN/</label><input type="text" name="lkn_no" maxlength="100" value="{{ $v('lkn_no') }}" placeholder="{{ $refs['lkn'] }}" class="{{ $input }}"></div>
          <div><label class="{{ $label }}">SLTR/</label><input type="text" name="sltr_no" maxlength="100" value="{{ $v('sltr_no') }}" placeholder="{{ $refs['sltr'] }}" class="{{ $input }}"></div>
          <div><label class="{{ $label }}">Government Item No. GKN/</label><input type="text" name="govt_item_no" maxlength="100" value="{{ $v('govt_item_no') }}" placeholder="{{ $refs['gkn'] }}" class="{{ $input }}"></div>
          <div><label class="{{ $label }}">SIT/</label><input type="text" name="sit_no" maxlength="100" value="{{ $v('sit_no') }}" placeholder="{{ $refs['sit'] }}" class="{{ $input }}"></div>
          <p class="sm:col-span-4 text-xs text-gray-500">A blank line prints the file number on the line its file type says (shown greyed in the box).</p>
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

        <div class="p-5">
          <div class="{{ $q }}"><span>8. Area applied for</span></div>
          <div class="{{ $sub }}">
            <div>
              <label class="{{ $label }}">Hectares</label>
              <input type="number" step="0.0001" min="0" name="area_applied_ha" x-model="ha" value="{{ $areaValue }}" class="{{ $input }}">
              <p class="mt-1 text-xs text-gray-500">
                @if ($areaPrefilled) Filled in from {{ $area['source'] }}; saved when you save the form.
                @elseif ($area['source'] === 'entered') As entered.
                @else No plan description or charting card area for this file yet.
                @endif
              </p>
            </div>
            <div>
              <label class="{{ $label }}">Acres (1 hectare = 2.47 acres, as the form prints)</label>
              <input type="text" disabled x-bind:value="ha === '' || isNaN(parseFloat(ha)) ? '' : (parseFloat(ha) * {{ $F::ACRES_PER_HECTARE_AS_PRINTED }}).toFixed(4)" class="{{ $input }}">
            </div>
          </div>
        </div>
      </fieldset>

      @if ($editable)
        <div class="p-5 flex flex-wrap items-center justify-between gap-3 border-t border-gray-100">
          <span class="text-xs text-gray-500">"Land 12 and Yes/No form" can be ticked once questions 1–7 are all answered.</span>
          @canDo('Cad - Records', 'edit')
            <button type="submit" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-blue-600 text-white text-sm font-semibold">
              <i data-lucide="save" class="w-4 h-4"></i> Save the Yes/No form
            </button>
          @endcanDo
        </div>
      @endif
    </form>
    @endif
  </div>
  @include('admin.footer')
</div>
@endsection
