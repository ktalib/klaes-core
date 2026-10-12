@extends('layouts.app')

@section('content')
<div class="flex-1 overflow-auto bg-gray-50">
  @include('admin.header', ['PageTitle' => 'Cadastral Workflow Map', 'PageDescription' => 'Conversion, Direct Allocation and Verification, desk by desk'])

  <div class="p-6">
    @include('cadastral_dept.partials._nav')

    <div x-data="{ wf: '{{ array_key_first($def->workflows()) }}' }">
      <div class="flex flex-wrap gap-2 mb-6">
        @foreach ($def->workflows() as $wfKey => $wf)
          <button type="button" @click="wf = '{{ $wfKey }}'"
                  :class="wf === '{{ $wfKey }}' ? 'bg-{{ $wf['colour'] }}-600 text-white border-{{ $wf['colour'] }}-600' : 'bg-white text-gray-700 border-gray-200'"
                  class="px-4 py-2 rounded-lg border text-sm font-bold">
            {{ $loop->iteration }}. {{ $wf['label'] }}
          </button>
        @endforeach
      </div>

      @foreach ($def->workflows() as $wfKey => $wf)
        @php $c = $wf['colour']; @endphp
        <div x-show="wf === '{{ $wfKey }}'" x-cloak>
          <div class="mb-4 rounded-xl border border-{{ $c }}-200 bg-{{ $c }}-50 px-5 py-4">
            <div class="font-bold text-{{ $c }}-900">{{ $wf['label'] }}</div>
            <div class="text-sm text-{{ $c }}-800">Applies to: {{ $wf['applies_to'] }}</div>
            @if (! empty($wf['sub_types']))
              <div class="text-sm text-{{ $c }}-800 mt-1">Sub-types: {{ implode(', ', $wf['sub_types']) }}</div>
            @endif
          </div>

          @foreach ($def->stepsByPhase($wfKey) as $phase => $steps)
            <div class="mb-6">
              <h3 class="text-sm font-bold uppercase tracking-wide text-gray-500 mb-2">Phase {{ $phase }}: {{ $wf['phases'][$phase] ?? '' }}</h3>
              <div class="grid gap-3 md:grid-cols-2 lg:grid-cols-3">
                @foreach ($steps as $key => $s)
                  @php $desk = $def->desks()[$s['desk']] ?? []; @endphp
                  <div class="rounded-xl border bg-white p-4 {{ ! empty($s['external']) ? 'border-dashed border-gray-300' : 'border-gray-200' }}">
                    <div class="flex items-center justify-between gap-2 mb-1">
                      <span class="text-xs font-bold px-2 py-0.5 rounded-full {{ ! empty($desk['internal']) ? 'bg-'.$c.'-100 text-'.$c.'-800' : 'bg-gray-100 text-gray-600' }}">
                        {{ $desk['label'] ?? $s['desk'] }}
                      </span>
                      @if (! empty($s['external']))
                        <span class="text-xs text-gray-400">external · recorded by {{ $def->deskLabel($s['recorded_by']) }}</span>
                      @endif
                    </div>
                    <div class="font-semibold text-gray-800">{{ $s['label'] }}</div>

                    @if (! empty($s['tasks']))
                      <ul class="mt-2 space-y-1 text-sm text-gray-600">
                        @foreach ($s['tasks'] as $task)
                          <li class="flex gap-2"><i data-lucide="check-square" class="w-4 h-4 mt-0.5 text-gray-400 flex-shrink-0"></i>{{ $task }}</li>
                        @endforeach
                      </ul>
                    @endif

                    <div class="mt-3 pt-2 border-t border-gray-100 text-xs text-gray-500 space-y-0.5">
                      @if (isset($s['next']))
                        <div>&rarr; {{ $def->deskLabel($def->step($wfKey, $s['next'])['desk']) }}</div>
                      @elseif (isset($s['outcomes']))
                        @foreach ($s['outcomes'] as $o)
                          <div><strong>{{ $o['label'] }}</strong> &rarr; {{ $def->step($wfKey, $o['next'])['label'] }}</div>
                        @endforeach
                      @elseif (isset($s['routes']))
                        @foreach ($s['routes'] as $sub => $next)
                          <div><strong>{{ $wf['sub_types'][$sub] ?? $sub }}</strong> &rarr; {{ $def->deskLabel($def->step($wfKey, $next)['desk']) }}</div>
                        @endforeach
                      @elseif (isset($s['end']))
                        <div class="font-semibold {{ $s['end'] === 'returned' ? 'text-red-600' : 'text-green-700' }}">End: {{ $s['end'] }}</div>
                      @endif
                    </div>
                  </div>
                @endforeach
              </div>
            </div>
          @endforeach
        </div>
      @endforeach
    </div>
  </div>
  @include('admin.footer')
</div>
@endsection
