@extends('layouts.app')

{{-- The Cadastral Verification Report Sheet (client DOCX, 2026-10-15), Verification files. --}}
@section('content')
@php
  $R = \App\Models\CadastralDept\CadastralVerificationReport::class;
  $val = fn (string $c) => old($c, $v[$c] ?? null);
  $input = 'w-full rounded-lg border border-gray-300 px-3 py-2 text-sm disabled:bg-gray-100';
  $label = 'block text-xs font-medium text-gray-600 mb-1';
  $head = 'px-5 py-3 bg-gray-50 border-b border-gray-200 text-xs font-bold uppercase tracking-wide text-gray-600';
  $nature = $val('current_transaction_nature');
  $natureOther = $nature && ! in_array($nature, $R::NATURES, true) ? trim(preg_replace('/^Other:\s*/i', '', $nature)) : '';
  $natureChoice = $nature === null || $nature === '' ? '' : ($natureOther !== '' || $nature === 'Other' ? 'Other' : $nature);
  $natureOther = old('current_transaction_other', $natureOther);
  $verdict = $val('verdict');
@endphp
<div class="flex-1 overflow-auto bg-gray-50">
  @include('admin.header', ['PageTitle' => 'Verification Report Sheet', 'PageDescription' => $case->case_ref . ' · ' . $case->file_number])

  <div class="p-6 space-y-6">
    @include('cadastral_dept.partials._nav')

    <div class="flex flex-wrap items-center gap-2">
      <a href="{{ route('cadastral-dept.cases.show', $case->id) }}" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg border border-gray-300 bg-white text-sm font-semibold text-gray-700">
        <i data-lucide="arrow-left" class="w-4 h-4"></i> Back to {{ $case->case_ref }}
      </a>
      @if ($report)
        <span class="px-3 py-1 rounded-full text-xs font-semibold {{ $report->isComplete() ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800' }}">
          {{ $report->isComplete() ? $R::VERDICTS[$report->verdict][0] : 'No verdict yet' }}
        </span>
      @endif
      @cadCan('print')
        <a href="{{ route('cadastral-dept.cases.verification-report.print', $case->id) }}" target="_blank" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-gray-800 text-white text-sm font-semibold">
          <i data-lucide="printer" class="w-4 h-4"></i> Print Verification Report Sheet
        </a>
      @endcadCan
    </div>

    @if (! $installed)
      <div class="rounded-lg border border-yellow-300 bg-yellow-50 px-4 py-3 text-sm text-yellow-900">
        <strong>Not installed yet.</strong> The table (migration <code>2026_10_15_090000_create_cadastral_dept_verification_reports</code>) has not been created.
        The sheet can still be printed, with the [Auto] fields filled.
      </div>
    @elseif (! $editable)
      <div class="rounded-lg border border-yellow-300 bg-yellow-50 px-4 py-3 text-sm text-yellow-900">
        {{ $case->case_ref }} is closed, so this sheet is read only.
      </div>
    @elseif (! $report)
      <div class="rounded-lg border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-900">
        Sections 2, 3 and 5 start from the property records and the File Status checks. Confirm or correct them, then save.
      </div>
    @endif

    <form method="POST" action="{{ route('cadastral-dept.cases.verification-report.save', $case->id) }}"
          x-data="{ nature: @js($natureChoice), verdict: @js($verdict ?? '') }"
          class="rounded-xl border border-gray-200 bg-white overflow-hidden"
          data-confirm-title="Save the Verification Report Sheet?" data-confirm="The sheet for {{ $case->file_number }} is saved.">
      @csrf
      <fieldset @disabled(! $editable || ! $installed)>

        {{-- SECTION 1 --}}
        <div class="{{ $head }}">Section 1: General file information <span class="font-normal normal-case text-gray-400">· [Auto] from the FMC and the registry</span></div>
        <div class="p-5 grid gap-4 sm:grid-cols-2">
          @foreach ([
            'file_number' => 'File Reference No', 'application_date' => 'Date of Application',
            'allottee_name' => 'Allottee Name', 'application_type' => 'Application Type',
            'plot_no' => 'Plot Number', 'layout_no' => 'Layout Name/No',
            'district_location' => 'District/Location', 'fmc_commission_date' => 'FMC Commission Date',
          ] as $col => $text)
            @php
              $auto = $col === 'file_number' ? $case->file_number : ($general[$col] ?? null);
            @endphp
            <div>
              <label class="{{ $label }}">{{ $text }} @if ($auto)<span class="text-green-700">[Auto]</span>@endif</label>
              @if ($auto)
                <input type="text" value="{{ $auto }}" readonly title="From the records" class="{{ $input }} bg-gray-100 text-gray-700">
              @else
                <input type="text" name="{{ $col }}" value="{{ $val($col) }}" maxlength="300" placeholder="Not on the records; enter it" class="{{ $input }}">
              @endif
            </div>
          @endforeach
        </div>

        {{-- SECTION 2 --}}
        <div class="{{ $head }}">Section 2: Transaction history</div>
        <div class="p-5">
          <p class="text-xs text-gray-500 mb-3">Verify against the historical records in the Registry and Cadastral Information Unit.</p>
          <div class="overflow-x-auto">
            <table class="w-full text-sm">
              <thead class="text-xs text-gray-500"><tr><th class="text-left py-2 pr-3 w-12">S/N</th><th class="text-left py-2 pr-3">Holder Details</th><th class="text-left py-2 pr-3">Original Holder (First Allottee)</th><th class="text-left py-2">Current Holder (Applicant)</th></tr></thead>
              <tbody class="divide-y divide-gray-100">
                <tr><td class="py-2 pr-3 text-gray-500">1</td><td class="py-2 pr-3">Full Name</td>
                  <td class="py-2 pr-3"><input type="text" name="original_holder_name" value="{{ $val('original_holder_name') }}" maxlength="300" class="{{ $input }}"></td>
                  <td class="py-2"><input type="text" name="current_holder_name" value="{{ $val('current_holder_name') }}" maxlength="300" class="{{ $input }}"></td></tr>
                <tr><td class="py-2 pr-3 text-gray-500">2</td><td class="py-2 pr-3">File / Plot Reference</td>
                  <td class="py-2 pr-3"><input type="text" name="original_holder_ref" value="{{ $val('original_holder_ref') }}" maxlength="150" class="{{ $input }}"></td>
                  <td class="py-2"><input type="text" name="current_holder_ref" value="{{ $val('current_holder_ref') }}" maxlength="150" class="{{ $input }}"></td></tr>
                <tr><td class="py-2 pr-3 text-gray-500">3</td><td class="py-2 pr-3">Date of Allocation/Transaction</td>
                  <td class="py-2 pr-3"><input type="text" name="original_holder_date" value="{{ $val('original_holder_date') }}" maxlength="40" placeholder="dd/mm/yyyy" class="{{ $input }}"></td>
                  <td class="py-2"><input type="text" name="current_holder_date" value="{{ $val('current_holder_date') }}" maxlength="40" placeholder="dd/mm/yyyy" class="{{ $input }}"></td></tr>
                <tr><td class="py-2 pr-3 text-gray-500">4</td><td class="py-2 pr-3">Nature of Transaction</td>
                  <td class="py-2 pr-3 text-gray-700">Original Allocation</td>
                  <td class="py-2">
                    <div class="flex flex-wrap gap-x-4 gap-y-1">
                      @foreach (array_merge($R::NATURES, ['Other']) as $opt)
                        <label class="inline-flex items-center gap-1.5"><input type="radio" name="current_transaction_nature" value="{{ $opt }}" x-model="nature"> {{ $opt }}</label>
                      @endforeach
                      <button type="button" class="text-xs text-gray-500 underline" x-show="nature" @click="nature = ''">Clear</button>
                    </div>
                    <input type="text" name="current_transaction_other" value="{{ $natureOther }}" maxlength="90" placeholder="Specify the other transaction"
                           x-show="nature === 'Other'" :disabled="nature !== 'Other'" :required="nature === 'Other'" class="{{ $input }} mt-2">
                  </td></tr>
              </tbody>
            </table>
          </div>
        </div>

        {{-- SECTION 3 --}}
        <div class="{{ $head }}">Section 3: Encumbrance check</div>
        <div class="p-5">
          <p class="text-xs text-gray-500 mb-3">Verify the legal status of the parcel against the Deeds and Registry database.</p>
          <table class="w-full text-sm">
            <thead class="text-xs text-gray-500"><tr><th class="text-left py-2 pr-3 w-12">S/N</th><th class="text-left py-2 pr-3">Encumbrance Type</th><th class="text-left py-2 pr-3">Status</th><th class="text-left py-2">Details / Reference No.</th></tr></thead>
            <tbody class="divide-y divide-gray-100">
              @foreach ([
                ['Caveat', 'caveat_status', $R::ENCUMBRANCE, 'caveat_ref'],
                ['Mortgage', 'mortgage_status', $R::ENCUMBRANCE, 'mortgage_ref'],
                ['Surrender & Release', 'release_status', $R::RELEASE, 'release_ref'],
              ] as $i => [$text, $col, $opts, $ref])
                <tr>
                  <td class="py-2 pr-3 text-gray-500">{{ $i + 1 }}</td>
                  <td class="py-2 pr-3">{{ $text }}</td>
                  <td class="py-2 pr-3 whitespace-nowrap">
                    @foreach ($opts as $opt)
                      <label class="inline-flex items-center gap-1.5 mr-3"><input type="radio" name="{{ $col }}" value="{{ $opt }}" @checked($val($col) === $opt)> {{ $opt }}</label>
                    @endforeach
                  </td>
                  <td class="py-2"><input type="text" name="{{ $ref }}" value="{{ $val($ref) }}" maxlength="300" class="{{ $input }}"></td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>

        {{-- SECTION 4 --}}
        <div class="{{ $head }}">Section 4: DCIV investigation check</div>
        <div class="p-5 grid gap-4 sm:grid-cols-2">
          <p class="sm:col-span-2 text-xs text-gray-500">With the DCIV (Department of Control and Investigation): genuineness of the transaction, fraud or irregularities.</p>
          <div>
            <span class="{{ $label }}">1. DCIV Investigation Status</span>
            @foreach ($R::DCIV_STATUS as $opt)
              <label class="inline-flex items-center gap-1.5 mr-3 text-sm"><input type="radio" name="dciv_status" value="{{ $opt }}" @checked($val('dciv_status') === $opt)> {{ $opt }}</label>
            @endforeach
          </div>
          <div>
            <label class="{{ $label }}">2. Investigating Officer Name</label>
            <input type="text" name="dciv_officer" value="{{ $val('dciv_officer') }}" maxlength="200" class="{{ $input }}">
          </div>
          <div>
            <span class="{{ $label }}">3. Genuineness of Documents</span>
            @foreach ($R::GENUINENESS as $opt)
              <label class="inline-flex items-center gap-1.5 mr-3 text-sm"><input type="radio" name="dciv_genuineness" value="{{ $opt }}" @checked($val('dciv_genuineness') === $opt)> {{ $opt }}</label>
            @endforeach
          </div>
          <div class="sm:col-span-2">
            <label class="{{ $label }}">4. DCIV Findings / Remarks</label>
            <textarea name="dciv_remarks" rows="2" maxlength="2000" class="{{ $input }}">{{ $val('dciv_remarks') }}</textarea>
          </div>
        </div>

        {{-- SECTION 5 --}}
        <div class="{{ $head }}">Section 5: Title status &amp; parcel update check</div>
        <div class="p-5 space-y-4">
          <p class="text-xs text-gray-500">By the Cadastral Information and Report Units: the physical and spatial status of the land.</p>
          @foreach ([
            ['1. Current Title Status', 'title_status', $R::TITLE_STATUS],
          ] as [$text, $col, $opts])
            <div>
              <span class="{{ $label }}">{{ $text }}</span>
              @foreach ($opts as $opt)
                <label class="inline-flex items-center gap-1.5 mr-3 text-sm"><input type="radio" name="{{ $col }}" value="{{ $opt }}" @checked($val($col) === $opt)> {{ $opt }}</label>
              @endforeach
            </div>
          @endforeach
          <div>
            <label class="{{ $label }}">2. Title Document Reference</label>
            <input type="text" name="title_ref" value="{{ $val('title_ref') }}" maxlength="300" class="{{ $input }}">
          </div>
          @foreach ([
            ['3. Parcel Update Status (APU/SPU)', 'parcel_update_status', $R::PARCEL_UPDATE],
            ['4. Survey Plan Status', 'survey_plan_status', $R::SURVEY_PLAN],
            ['5. Spatial/Charting Verification', 'charting_status', $R::CHARTING],
          ] as [$text, $col, $opts])
            <div>
              <span class="{{ $label }}">{{ $text }}</span>
              @foreach ($opts as $opt)
                <label class="inline-flex items-center gap-1.5 mr-3 text-sm"><input type="radio" name="{{ $col }}" value="{{ $opt }}" @checked($val($col) === $opt)> {{ $opt }}</label>
              @endforeach
            </div>
          @endforeach
        </div>

        {{-- SECTION 6 --}}
        <div class="{{ $head }}">Section 6: Verification conclusion &amp; recommendation</div>
        <div class="p-5 space-y-3">
          <span class="{{ $label }}">Final Verification Verdict</span>
          @foreach ($R::VERDICTS as $key => [$title, $hint])
            <label class="flex items-start gap-2 text-sm">
              <input type="radio" name="verdict" value="{{ $key }}" x-model="verdict" class="mt-1">
              <span><strong>{{ $title }}</strong> <span class="text-gray-500">({{ $hint }})</span></span>
            </label>
          @endforeach
          <div>
            <label class="{{ $label }}">Detailed Remarks / Conditions <span class="text-red-600" x-show="verdict === 'conditions' || verdict === 'rejected'">*</span></label>
            <textarea name="remarks" rows="4" maxlength="4000" :required="verdict === 'conditions' || verdict === 'rejected'" class="{{ $input }}">{{ $val('remarks') }}</textarea>
          </div>
        </div>

        {{-- SECTION 7 --}}
        <div class="{{ $head }}">Section 7: Authorization &amp; sign-off</div>
        <div class="p-5">
          <table class="w-full text-sm">
            <thead class="text-xs text-gray-500"><tr><th class="text-left py-2 pr-3 w-12">S/N</th><th class="text-left py-2 pr-3">Role</th><th class="text-left py-2 pr-3">Name</th><th class="text-left py-2">Date</th></tr></thead>
            <tbody class="divide-y divide-gray-100">
              @foreach (array_values($signOff) as $i => $s)
                <tr><td class="py-2 pr-3 text-gray-500">{{ $i + 1 }}</td><td class="py-2 pr-3">{{ $s['role'] }}</td>
                  <td class="py-2 pr-3">{{ $s['name'] ?: '—' }}</td><td class="py-2">{{ $s['date'] ?: '—' }}</td></tr>
              @endforeach
            </tbody>
          </table>
          <p class="mt-2 text-xs text-gray-400">The officer who acted at each desk on this file. Signatures go on the printed sheet.</p>
        </div>
      </fieldset>

      @if ($editable && $installed)
        <div class="px-5 py-4 border-t border-gray-200 flex justify-end">
          <button type="submit" class="inline-flex items-center gap-2 px-5 py-2 rounded-lg bg-green-700 text-white text-sm font-bold">
            <i data-lucide="save" class="w-4 h-4"></i> Save Verification Report Sheet
          </button>
        </div>
      @endif
    </form>
  </div>
  @include('admin.footer')
</div>
@endsection
