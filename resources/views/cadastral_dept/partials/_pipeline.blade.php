{{--
    The process on one line: a circle per step joined by a line. Green tick = done,
    coloured pin = where the file is now, grey number = still to come, flag = the end.
    Under each circle only the desk; the step, answer, officer and date are in the tooltip.
    $pipeline comes from WorkflowEngine::pipeline(); $c is the workflow colour.
    $jumpUrl (optional, Super Admin / Central Admin on an open file): every step but the current one
    is a button that moves the file there, past any checks, with a reason (CaseWorkflow::jumpTo, 2026-10-26).
--}}
@php
  $cdC = $c ?? 'blue';
  $cdN = count($pipeline);
  // Numbered from 0 when the first step is step zero (Registry intake, user 2026-10-18).
  $cdBase = ! empty(($pipeline[0] ?? [])['zero']) ? 0 : 1;
  $cdJump = $jumpUrl ?? null;
@endphp
<div x-data="{ jump: null, reason: '' }">
@if ($cdJump)
  <p class="mb-2 text-xs text-gray-500"><i data-lucide="mouse-pointer-click" class="w-3.5 h-3.5 inline -mt-0.5"></i>
    Admin: click a step to move the file there. This skips the tasks and checks in between.</p>
@endif
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
      @php
        $cdCanJump = $cdJump && $cdP['key'] && $cdP['state'] !== 'current';
      @endphp
      <li class="relative flex-1 flex flex-col items-center text-center {{ $cdCanJump ? 'cursor-pointer group' : '' }}" style="min-width: 64px;"
          title="{{ $cdTip }}{{ $cdCanJump ? "\n\nClick to move the file here" : '' }}"
          @if ($cdCanJump) role="button" tabindex="0"
            @click="jump = @js(['step' => $cdP['key'], 'desk' => $cdP['desk'], 'label' => $cdP['label']]); reason = ''"
            @keydown.enter="jump = @js(['step' => $cdP['key'], 'desk' => $cdP['desk'], 'label' => $cdP['label']]); reason = ''"
          @endif>
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
        @if ($cdCanJump)
          <span class="absolute inset-x-1 -top-1 h-10 rounded-lg ring-2 ring-transparent group-hover:ring-indigo-300"></span>
        @endif
        @if (! empty($cdP['only_if']) && $cdP['state'] !== 'done')
          <span class="mt-0.5 px-1 text-xs italic text-orange-600 leading-tight" title="Only files that need it go through this step; the others skip it">if {{ $cdP['only_if'] }}</span>
        @endif
        {{-- A split route (GIS buffer: KAMMA OR Physical Planning): the other route's step at this
             position, when it is at another desk (WorkflowEngine::pipeline 'alts', 2026-10-27). --}}
        @foreach (collect($cdP['alts'] ?? [])->filter(fn ($a) => $a['desk'] !== $cdP['desk']) as $cdAlt)
          <span class="relative z-10 mt-1 flex flex-col items-center leading-tight" title="{{ $cdAlt['desk'] }}: {{ $cdAlt['label'] }}{{ $cdJump ? "

Click to move the file here" : '' }}">
            <span class="font-bold uppercase tracking-wide text-orange-600" style="font-size:10px">or</span>
            @if ($cdJump)
              <button type="button" class="px-1 rounded text-xs text-gray-500 italic hover:bg-indigo-50 hover:text-indigo-700"
                      @click.stop="jump = @js(['step' => $cdAlt['key'], 'desk' => $cdAlt['desk'], 'label' => $cdAlt['label']]); reason = ''">{!! str_replace('/', '/<wbr>', e($cdAlt['desk'])) !!}</button>
            @else
              <span class="px-1 text-xs text-gray-500 italic">{!! str_replace('/', '/<wbr>', e($cdAlt['desk'])) !!}</span>
            @endif
          </span>
        @endforeach
      </li>
    @endforeach
  </ol>
</div>
@if ($cdJump)
  <div x-show="jump" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-40 p-4" @keydown.escape.window="jump = null">
    <form method="POST" action="{{ $cdJump }}" class="w-full max-w-md rounded-xl bg-white shadow-xl p-5 space-y-4" @click.outside="jump = null">
      @csrf
      <input type="hidden" name="step" :value="jump && jump.step">
      <div>
        <div class="text-xs font-bold uppercase text-indigo-700">Admin · move the file</div>
        <div class="text-lg font-bold text-gray-800 mt-1">Move to <span x-text="jump && jump.desk"></span></div>
        <div class="text-sm text-gray-600 mt-1" x-text="jump && jump.label"></div>
      </div>
      <div class="rounded-lg border border-yellow-200 bg-yellow-50 px-3 py-2 text-xs text-yellow-900">
        The file goes straight to this step. Tasks and checks in between are skipped and nothing is created for them;
        the move and your reason are kept in the file's history and the audit log.
      </div>
      <div>
        <label class="block text-sm font-semibold text-gray-700 mb-1">Reason <span class="text-red-600">*</span></label>
        <textarea name="reason" rows="3" x-model="reason" required class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm"></textarea>
      </div>
      <div class="flex justify-end gap-2">
        <button type="button" @click="jump = null" class="px-4 py-2 rounded-lg border border-gray-300 text-sm font-semibold text-gray-700">Cancel</button>
        <button type="submit" :disabled="reason.trim() === ''" class="px-4 py-2 rounded-lg bg-indigo-600 text-white text-sm font-semibold disabled:opacity-50">Move file</button>
      </div>
    </form>
  </div>
@endif
</div>
