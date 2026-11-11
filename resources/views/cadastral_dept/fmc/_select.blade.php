{{--
  One FMC dropdown (2026-11-09): District, LGA or Layout No. (= TP No.).
  Expects $name, $options (list of names), $value (current or prefilled), $other (offer "Other" + specify).
  A current value that is not on the list opens as Other with the value typed in.
--}}
@php
  $cdSelOld = old($name);
  $cdSelVal = trim((string) ($cdSelOld ?? $value));
  $cdSelListed = null;
  foreach ($options as $cdSelOpt) {
      if (mb_strtoupper($cdSelOpt) === mb_strtoupper($cdSelVal)) { $cdSelListed = $cdSelOpt; break; }
  }
  $cdSelIsOther = $other && ($cdSelVal === \App\Services\CadastralDept\FileMovementCards::OTHER || ($cdSelVal !== '' && $cdSelListed === null));
  $cdSelOtherVal = $cdSelOld === \App\Services\CadastralDept\FileMovementCards::OTHER ? old($name . '_other') : ($cdSelIsOther ? $cdSelVal : '');
@endphp
<select name="{{ $name }}" data-fmc-select data-placeholder="Choose…" autocomplete="off"
        data-value="{{ $cdSelIsOther ? \App\Services\CadastralDept\FileMovementCards::OTHER : ($cdSelListed ?? '') }}" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
  <option value=""></option>
  @foreach ($options as $cdSelOpt)
    <option value="{{ $cdSelOpt }}" @selected(! $cdSelIsOther && $cdSelListed === $cdSelOpt)>{{ $cdSelOpt }}</option>
  @endforeach
  @if ($other)
    <option value="{{ \App\Services\CadastralDept\FileMovementCards::OTHER }}" @selected($cdSelIsOther)>Other</option>
  @endif
</select>
@if ($other)
  <input type="text" name="{{ $name }}_other" value="{{ $cdSelOtherVal }}" data-fmc-other-for="{{ $name }}" maxlength="{{ $name === 'layout_no' ? 50 : 100 }}"
         placeholder="Specify {{ $name === 'layout_no' ? 'the Layout No. (TP No.)' : 'the district' }}"
         class="{{ $cdSelIsOther ? '' : 'hidden' }} mt-2 w-full border border-gray-300 rounded-lg px-3 py-2 text-sm uppercase">
@endif
