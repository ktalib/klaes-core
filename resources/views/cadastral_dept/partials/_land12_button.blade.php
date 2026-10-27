{{--
    Land 12 at AD Cad Report (Land12Link, user 2026-10-27): opens the shared Land 12 form in a pop-up
    on this page, prefilled from the file. The Land 12 Lands raised for the file is completed when there
    is one; otherwise a new one is started. Saving it ticks the Land 12 task and goes on to the Yes/No form.
    Expects $case. $cdL12Small: compact buttons (task line).
--}}
@php
  $cdL12 = app(\App\Services\CadastralDept\Land12Link::class)->latest($case);
  $cdL12Btn = ! empty($cdL12Small) ? 'px-3 py-1 rounded-md text-xs' : 'px-3 py-1.5 rounded-lg text-sm';
  $cdL12Query = 'url=cadastral&cd_case=' . $case->id;
@endphp
@if ($cdL12 && $cdL12->status === 'Completed')
  <button type="button" class="customModal {{ $cdL12Btn }} border border-gray-300 bg-white text-gray-700 font-semibold"
          data-size="lg" data-title="Land 12" data-url="{{ route('survey-report.show', $cdL12->id) }}?url=cadastral">View Land 12 #{{ $cdL12->id }}</button>
  <form method="POST" action="{{ route('cadastral-dept.cases.tasks.mark-done', $case->id) }}"
        data-confirm-title="Mark Land 12 done?" data-confirm="Land 12 #{{ $cdL12->id }} is completed for {{ $case->file_number }}.">
    @csrf
    <input type="hidden" name="task" value="land12">
    <button class="{{ $cdL12Btn }} bg-gray-800 text-white font-semibold">Mark done</button>
  </form>
@elseif ($cdL12)
  <button type="button" class="customModal {{ $cdL12Btn }} bg-green-700 text-white font-semibold"
          data-size="lg" data-title="Land 12" data-url="{{ route('survey-report.edit', $cdL12->id) }}?{{ $cdL12Query }}"
          title="Land 12 #{{ $cdL12->id }} ({{ $cdL12->status }}): fill in Cadastral's part">Complete Land 12 #{{ $cdL12->id }}</button>
@else
  <button type="button" class="customModal {{ $cdL12Btn }} bg-green-700 text-white font-semibold"
          data-size="lg" data-title="Land 12" data-url="{{ route('survey-report.create') }}?{{ $cdL12Query }}">Fill Land 12</button>
@endif
@once
  @push('scripts')
    {{-- The Land 12 form's own submit handler (saves by AJAX, then goes on to cd_next). --}}
    <script src="{{ asset('js/survey-report.js') }}?v={{ filemtime(public_path('js/survey-report.js')) }}"></script>
  @endpush
@endonce
