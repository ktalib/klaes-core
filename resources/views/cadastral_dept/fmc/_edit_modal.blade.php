{{-- Edit the FMC header (recording 365, 2026-11-09). Record fields are corrected on the file's records too. --}}
@php
  $cdFmcEditFields = [
      'allottee_name' => 'Allottee Name',
      'block_no' => 'Block No.', 'layout_no' => 'Layout No.',
      'plot_no' => 'Plot No.', 'district' => 'District', 'lga' => 'LGA', 'location' => 'Location',
  ];
  if (! \App\Services\CadastralDept\FileMovementCards::SHOW_ALLOTTEE) {
      unset($cdFmcEditFields['allottee_name']);   // hidden for now (user, 2026-11-09)
  }
  $cdFmcEditMax = ['block_no' => 50, 'layout_no' => 50, 'plot_no' => 50, 'district' => 100, 'lga' => 100];
  $cdFmcEditOpen = session('fmc_edit_open') || ($errors->any() && old('fmc_edit') !== null);   // not on log-form errors
  // Dropdowns (2026-11-09): District and LGA from the lookups, Layout No. = TP No. from tp_lookups.
  $cdFmcOptions = app(\App\Services\CadastralDept\FileMovementCards::class)->options();
  $cdFmcSelects = ['district' => ['districts', true], 'lga' => ['lgas', false], 'layout_no' => ['layouts', true]];
@endphp
@include('cadastral_dept.fmc._select_js')
@push('scripts')
<script>
  // Location follows the District and LGA picked (user, 2026-11-10), as the server saves it.
  $(function () {
    var $form = $('#cd-fmc-edit-modal form'), $loc = $('#cd-fmc-edit-location');
    if (!$loc.length) return;
    function sync() {
      var d = $form.find('select[name="district"]').val() || '';
      if (d === '{{ \App\Services\CadastralDept\FileMovementCards::OTHER }}') d = $form.find('[name="district_other"]').val() || '';
      var parts = [d, $form.find('select[name="lga"]').val() || ''].map(function (v) { return $.trim(v); }).filter(Boolean);
      $loc.val(parts.join(', ').toUpperCase());
    }
    $form.on('change', 'select[name="district"], select[name="lga"]', sync);
    $form.on('input', '[name="district_other"]', sync);
  });
</script>
@endpush
<div id="cd-fmc-edit-modal" data-fmc-modal class="{{ $cdFmcEditOpen ? '' : 'hidden' }} fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-40 p-4">
  <form method="POST" action="{{ route('cadastral-dept.cases.fmc.update', $case->id) }}" autocomplete="off"
        data-confirm-title="Save the corrections?" data-confirm="{{ $fmc->fmc_no }} is updated, and the Plot No., District, LGA and Location are corrected on {{ $fmc->file_number }}'s records too."
        class="w-full max-w-lg rounded-xl bg-white shadow-xl p-5 space-y-4 max-h-full overflow-y-auto">
    @csrf
    <input type="hidden" name="fmc_edit" value="1">
    <div>
      <div class="text-xs font-bold uppercase text-gray-500">{{ $fmc->fmc_no }} · {{ $fmc->file_number }}</div>
      <div class="text-lg font-bold text-gray-800">Edit File Movement Card</div>
      <p class="text-xs text-gray-500 mt-1">Plot No., District, LGA and Location are also corrected on the file's indexing and file number records. A field left blank is cleared on the card only. The grey fields cannot be changed here.</p>
    </div>
    @include('cadastral_dept.fmc._fixed', ['fixedTitle' => $fmc->file_title])
    @if ($cdFmcEditOpen && $errors->any())
      <div class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-xs text-red-800">{{ $errors->first() }}</div>
    @endif
    <div class="grid gap-3 sm:grid-cols-2">
      @foreach ($cdFmcEditFields as $name => $label)
        <div class="{{ in_array($name, ['file_title', 'allottee_name', 'location'], true) ? 'sm:col-span-2' : '' }}">
          @php
            // A blank card field opens with what the file's records hold (standard 5).
            $cdFmcCard = trim((string) $fmc->{$name});
            $cdFmcFromRecords = $cdFmcCard === '' && isset($recordValues[$name]);
            $cdFmcValue = $cdFmcFromRecords ? $recordValues[$name] : $cdFmcCard;
            if ($name === 'location') { $cdFmcValue = mb_strtoupper($cdFmcValue); }
          @endphp
          <label class="block text-xs font-semibold text-gray-600 mb-1">{{ $label }}@if ($name === 'file_title') <span class="text-red-600">*</span>@endif</label>
          @if ($name === 'location')
            {{-- Not editable (user, 2026-11-10): made from the District and LGA above, as it is saved. --}}
            @php
              $cdFmcDistrict = trim((string) $fmc->district) !== '' ? $fmc->district : ($recordValues['district'] ?? null);
              $cdFmcLga = trim((string) $fmc->lga) !== '' ? $fmc->lga : ($recordValues['lga'] ?? null);
              if (old('district') !== null) {
                  $cdFmcDistrict = old('district') === \App\Services\CadastralDept\FileMovementCards::OTHER ? old('district_other') : old('district');
                  $cdFmcLga = old('lga');
              }
              $cdFmcFromRecords = false;
            @endphp
            <input type="text" id="cd-fmc-edit-location" value="{{ \App\Services\CadastralDept\FileMovementCards::locationOf($cdFmcDistrict, $cdFmcLga) }}"
                   readonly tabindex="-1" aria-readonly="true" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm bg-gray-100 text-gray-700 uppercase cursor-not-allowed">
            <p class="text-xs text-gray-500 mt-1">Made from the District and LGA.</p>
          @elseif (isset($cdFmcSelects[$name]))
            @include('cadastral_dept.fmc._select', ['name' => $name, 'options' => $cdFmcOptions[$cdFmcSelects[$name][0]], 'value' => $cdFmcValue, 'other' => $cdFmcSelects[$name][1]])
          @else
          <input type="text" name="{{ $name }}" value="{{ old($name, $cdFmcValue) }}" maxlength="{{ $cdFmcEditMax[$name] ?? 255 }}"
                 @if ($name === 'file_title') required @endif
                 class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm{{ in_array($name, ['block_no', 'location'], true) ? ' uppercase' : '' }}">
          @endif
          @if ($cdFmcFromRecords)
            <p class="text-xs text-blue-700 mt-1">From the file's records; saved to the card on Save.</p>
          @endif
        </div>
      @endforeach
    </div>
    <div class="flex justify-end gap-2">
      <button type="button" onclick="document.getElementById('cd-fmc-edit-modal').classList.add('hidden')" class="px-4 py-2 rounded-lg border border-gray-300 text-sm font-semibold text-gray-700">Cancel</button>
      <button class="px-4 py-2 rounded-lg bg-green-700 text-white text-sm font-semibold">Save changes</button>
    </div>
  </form>
</div>
