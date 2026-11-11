{{-- Shown and never edited from the card (Survey Update call, 2026-11-10): File No., File Type, the
     OP Serial No. on an OP File only, and the File Title where the caller passes $fixedTitle. --}}
@php
  $cdFixed = array_filter([
      'file_number' => 'File No.',
      'file_type' => 'File Type',
      'op_no' => ($fixed['is_op'] ?? false) ? 'OP Serial No.' : null,
  ]);
@endphp
<div class="grid gap-3 sm:grid-cols-{{ count($cdFixed) }}">
  @foreach ($cdFixed as $cdFixedKey => $cdFixedLabel)
    <div>
      <label class="block text-xs font-semibold text-gray-600 mb-1">{{ $cdFixedLabel }}</label>
      <input type="text" value="{{ $fixed[$cdFixedKey] ?? '' }}" placeholder="{{ $cdFixedKey === 'op_no' ? 'No OP on record' : '' }}" readonly tabindex="-1"
             class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm bg-gray-100 text-gray-700{{ $cdFixedKey === 'file_type' ? '' : ' font-mono' }}">
    </div>
  @endforeach
  @isset($fixedTitle)
    <div class="sm:col-span-{{ count($cdFixed) }}">
      <label class="block text-xs font-semibold text-gray-600 mb-1">File Title</label>
      <input type="text" value="{{ $fixedTitle }}" readonly tabindex="-1" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm bg-gray-100 text-gray-700">
    </div>
  @endisset
</div>
