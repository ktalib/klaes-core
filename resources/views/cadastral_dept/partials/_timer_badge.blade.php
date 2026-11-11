{{--
    One working day per office (CadastralCaseTimer). $t is the case's timer state, or null for a file that is
    not timed (finished, or out with another department). Shown on the desk lists and on Central Admin.
--}}
@if (! empty($t) && $t['stage'] !== 'on_time')
    @php
        $cdTimerTone = [
            'yellow' => 'bg-yellow-100 text-yellow-900 border-yellow-300',
            'orange' => 'bg-orange-100 text-orange-900 border-orange-300',
            'red'    => 'bg-red-100 text-red-800 border-red-300',
        ][$t['tone']] ?? 'bg-gray-100 text-gray-800 border-gray-300';
    @endphp
    <div class="mt-1">
        <span class="inline-flex items-center gap-1 rounded border px-1.5 py-0.5 text-xs font-semibold {{ $cdTimerTone }}" title="{{ $t['text'] }} · at this office since {{ $t['since']->format('d/m/Y H:i') }}">
            <i data-lucide="{{ $t['stage'] === 'reminder' ? 'clock-3' : 'alarm-clock' }}" class="w-3 h-3"></i>{{ $t['label'] }}
        </span>
        <div class="text-xs {{ $t['tone'] === 'red' ? 'text-red-700' : 'text-gray-500' }} mt-0.5">{{ $t['text'] }}</div>
    </div>
@elseif (! empty($t) && $t['paused'])
    <div class="text-xs text-gray-500 mt-1">{{ $t['text'] }}</div>
@endif
