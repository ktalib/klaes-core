{{--
    The process on one line: a circle per step joined by a line. Green tick = done,
    coloured pin = where the file is now, grey number = still to come, flag = the end.
    Under each circle only the desk; the step, answer, officer and date are in the tooltip.
    $pipeline comes from WorkflowEngine::pipeline(); $c is the workflow colour.
--}}
@php
  $cdC = $c ?? 'blue';
  $cdN = count($pipeline);
  // Numbered from 0 when the first step is step zero (Registry intake, user 2026-10-18).
  $cdBase = ! empty(($pipeline[0] ?? [])['zero']) ? 0 : 1;
@endphp
<div class="overflow-x-auto pb-1">
  <ol class="flex items-start w-full" style="min-width: {{ $cdN * 64 }}px;">
    @foreach ($pipeline as $i => $cdP)
      @php
        $cdNext = $pipeline[$i + 1] ?? null;
        $cdLineOn = $cdP['state'] === 'done' && $cdNext && in_array($cdNext['state'], ['done', 'current', 'end'], true);
        $cdIsEnd = $cdP['key'] === null && $cdP['label'] !== 'Registered';
        $cdTip = trim(implode("\n", array_filter([
            ($cdP['desk'] ? $cdP['desk'] . ': ' : '') . $cdP['label'],
            $cdP['outcome'],
            trim(($cdP['by'] ?? '') . ($cdP['at'] ? ' · ' . \Illuminate\Support\Carbon::parse($cdP['at'])->format('d M Y H:i') : ''), ' ·'),
            $cdP['options'] ? 'Options: ' . implode(' / ', $cdP['options']) : null,
        ])));
      @endphp
      <li class="relative flex-1 flex flex-col items-center text-center" style="min-width: 64px;" title="{{ $cdTip }}">
        @unless ($loop->last)
          <span class="absolute h-0.5 {{ $cdLineOn ? 'bg-green-500' : 'bg-gray-200' }}" style="top: 15px; left: 50%; width: 100%;"></span>
        @endunless

        @if ($cdP['state'] === 'done')
          <span class="relative z-10 flex items-center justify-center w-8 h-8 rounded-full bg-green-600 text-white shadow-sm">
            <i data-lucide="{{ $cdP['label'] === 'Registered' ? 'file-plus' : 'check' }}" class="w-4 h-4"></i>
          </span>
        @elseif ($cdP['state'] === 'current')
          <span class="relative z-10 flex items-center justify-center w-8 h-8 rounded-full bg-{{ $cdC }}-600 text-white ring-4 ring-{{ $cdC }}-200 shadow">
            <i data-lucide="map-pin" class="w-4 h-4"></i>
          </span>
        @elseif ($cdIsEnd)
          <span class="relative z-10 flex items-center justify-center w-8 h-8 rounded-full {{ $cdP['state'] === 'end' ? ($cdP['label'] === 'Completed' ? 'bg-green-700' : 'bg-red-600') . ' text-white' : 'bg-white border-2 border-gray-300 text-gray-400' }}">
            <i data-lucide="flag" class="w-4 h-4"></i>
          </span>
        @else
          <span class="relative z-10 flex items-center justify-center w-8 h-8 rounded-full bg-white border-2 border-gray-300 text-xs font-bold text-gray-400">{{ $loop->index + $cdBase }}</span>
        @endif

        <span class="mt-1 px-0.5 text-xs leading-tight
                     {{ $cdP['state'] === 'current' ? 'font-bold text-' . $cdC . '-800' : ($cdP['state'] === 'done' ? 'font-semibold text-gray-700' : 'text-gray-400') }}">
          {!! $cdIsEnd ? e($cdP['label']) : str_replace('/', '/<wbr>', e($cdP['desk'])) !!}
        </span>
      </li>
    @endforeach
  </ol>
</div>
