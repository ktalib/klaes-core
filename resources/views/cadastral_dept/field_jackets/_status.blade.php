{{-- Job status pill. $cdStatus: a CadastralSurveyJob status. --}}
@php
  $cdStatusColour = [
      'Draft' => 'bg-gray-100 text-gray-700', 'Issued' => 'bg-blue-100 text-blue-800', 'In Field' => 'bg-blue-100 text-blue-800',
      'Submitted' => 'bg-yellow-100 text-yellow-800', 'Accepted' => 'bg-green-100 text-green-800',
      'Rejected' => 'bg-red-100 text-red-800', 'Cancelled' => 'bg-red-100 text-red-800',
  ][$cdStatus] ?? 'bg-gray-100 text-gray-700';
@endphp
<span class="px-2 py-0.5 rounded-full text-xs font-semibold {{ $cdStatusColour }}">{{ $cdStatus === 'Draft' ? 'I to S not issued' : $cdStatus }}</span>
