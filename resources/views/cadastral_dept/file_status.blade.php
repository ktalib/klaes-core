@extends('layouts.app')

{{-- Cadastral > Verification > File Status > Search (user, 2026-10-31): a copy of the Survey File Status
     search (survey_module/file_status), drawn in the Cadastral pages' style. The links open each list view
     only (?url=cadastral), filtered to the file. --}}
@php
  $groupColors = [
      'RofO' => ['#be123c', '#fff1f2'], 'CofO' => ['#b45309', '#fffbeb'], 'Decommissioned' => ['#475569', '#f1f5f9'],
      'Duplicates' => ['#6d28d9', '#f5f3ff'], 'Regrant Files' => ['#0f766e', '#f0fdfa'], 'Title Status' => ['#047857', '#ecfdf5'],
      'DCIV' => ['#1d4ed8', '#eff6ff'], 'Encumbrance' => ['#c2410c', '#fff7ed'],
  ];
  $foundCount = collect($sections)->where('found', true)->count();
@endphp

@section('content')
<div class="flex-1 overflow-auto bg-gray-50">
  @include('admin.header', ['PageTitle' => 'File Status', 'PageDescription' => 'Search a file by file number, OP serial number or name to see where it appears across every File Status list.'])

  <style>
    .cfs-chip { display:flex; flex-direction:column; gap:2px; padding:10px 12px; border-radius:10px; border:1.5px solid #e5e7eb; background:#fff; color:#6b7280; }
    .cfs-chip.is-found { border-color:var(--c); background:var(--bg); }
    .cfs-chip.is-found .cfs-state { color:var(--c); font-weight:700; }
    .cfs-details { margin-top:6px; padding-top:6px; border-top:1px dashed var(--c); }
    .cfs-details div { display:flex; gap:6px; font-size:12px; min-width:0; }
    .cfs-details dt { flex-shrink:0; color:#6b7280; }
    .cfs-details dt::after { content:':'; }
    .cfs-details dd { margin:0; color:#1f2937; font-weight:600; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
  </style>

  <div class="p-6 space-y-5">
    <form method="GET" action="{{ route('cadastral-dept.file-status') }}" class="flex flex-wrap items-center gap-2">
      <select name="by" aria-label="Search by" class="border border-gray-300 rounded-md px-3 py-2 text-sm bg-white">
        @foreach (\App\Services\FileStatusLookupService::BY as $k => $label)
          <option value="{{ $k }}" @selected($by === $k)>{{ $label }}</option>
        @endforeach
      </select>
      <input type="text" name="q" value="{{ $q }}" required autofocus placeholder="File number, OP serial number or name…" class="border border-gray-300 rounded-md px-3 py-2 text-sm bg-white" style="width:320px">
      <button class="inline-flex items-center gap-2 px-4 py-2 rounded-md bg-green-700 text-white text-sm font-semibold hover:bg-green-800"><i data-lucide="search" class="h-4 w-4"></i> Search</button>
      @if ($q !== '')<a href="{{ route('cadastral-dept.file-status') }}" class="px-3 py-2 rounded-md border border-gray-300 text-sm text-gray-600 bg-white">Clear</a>@endif
    </form>

    @if ($q === '')
      <div class="text-center text-gray-500 py-12">
        <i data-lucide="file-search" class="h-8 w-8 mx-auto mb-2 opacity-50"></i>
        Search a file to see where it appears: RofO, CofO, Decommissioned, Duplicates, Regrant Files, Title Status, DCIV and Encumbrance.
        <div class="text-sm mt-1">Or open any list from the sidebar.</div>
      </div>
    @elseif (! $file)
      @if (empty($candidates))
        <div class="text-center text-gray-500 py-10">
          @if ($by === 'op') No OP with serial number <strong>{{ $q }}</strong> was found. @else No file was found for the name <strong>{{ $q }}</strong>. @endif
        </div>
      @else
        <p class="text-sm text-gray-600">{{ count($candidates) }} {{ \Illuminate\Support\Str::plural('file', count($candidates)) }} match <strong>{{ $q }}</strong>{{ count($candidates) >= 50 ? ' (first 50 shown; refine the search)' : '' }}. Pick one to see its status.</p>
        <div class="bg-white border border-gray-200 rounded-lg overflow-x-auto">
          <table class="min-w-full text-sm">
            <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500"><tr><th class="px-3 py-2">S/N</th><th class="px-3 py-2">File No.</th><th class="px-3 py-2">Title / Name</th><th class="px-3 py-2">Found by</th><th class="px-3 py-2"></th></tr></thead>
            <tbody>
              @foreach ($candidates as $c)
                <tr class="border-t border-gray-100">
                  <td class="px-3 py-2">{{ $loop->iteration }}</td>
                  <td class="px-3 py-2 font-mono font-semibold">{{ $c['file_number'] }}</td>
                  <td class="px-3 py-2">{{ $c['title'] ?: '—' }}</td>
                  <td class="px-3 py-2">{{ $c['via'] ?: '—' }}</td>
                  <td class="px-3 py-2"><a href="{{ route('cadastral-dept.file-status', ['by' => 'file', 'q' => $c['file_number']]) }}" class="px-3 py-1 rounded-md border border-gray-300 text-xs">Check status</a></td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      @endif
    @else
      <div class="bg-white border border-gray-200 rounded-lg px-4 py-3 flex flex-wrap items-baseline gap-x-4 gap-y-1">
        <span class="font-mono text-lg font-bold">{{ $file['file_number'] }}</span>
        @if ($file['title'])<span class="font-semibold">{{ $file['title'] }}</span>@endif
        @if ($file['via'])<span class="text-sm text-gray-500">Found by {{ $file['via'] }}</span>@endif
        @if (count($file['aliases']) > 1)<span class="w-full text-sm text-gray-500">Also recorded as: {{ implode(', ', array_slice($file['aliases'], 1)) }}</span>@endif
        <span class="w-full text-sm {{ $foundCount ? 'text-gray-600' : 'text-red-700' }}">{{ $foundCount ? "Found in {$foundCount} of " . count($sections) . ' lists.' : 'Not found in any File Status list.' }}</span>
      </div>

      <div class="grid gap-2" style="grid-template-columns:repeat(auto-fill,minmax(200px,1fr))">
        @foreach ($sections as $s)
          @php [$c, $bg] = $groupColors[$s['group']] ?? ['#15803d', '#f0fdf4']; @endphp
          <a href="{{ $s['found'] ? '#cfs-' . $s['key'] : ($s['link'] ?? '#') }}" style="--c:{{ $c }};--bg:{{ $bg }};" class="cfs-chip {{ $s['found'] ? 'is-found' : '' }}">
            @if ($s['group'] !== $s['label'])<span class="text-xs uppercase tracking-wide">{{ $s['group'] }}</span>@endif
            <span class="font-semibold text-gray-800">{{ $s['label'] }}</span>
            <span class="cfs-state text-xs">{{ $s['found'] ? 'Found (' . (count($s['rows']) + $s['more']) . ')' : ($s['note'] ?? 'Not found') }}</span>
            @if ($s['found'] && ! empty($s['card']))
              <dl class="cfs-details">@foreach ($s['card'] as $k => $v)<div><dt>{{ $k }}</dt><dd title="{{ $v }}">{{ $v }}</dd></div>@endforeach</dl>
              @if (count($s['rows']) + $s['more'] > 1)<span class="text-xs" style="color:var(--c)">+{{ count($s['rows']) + $s['more'] - 1 }} more below</span>@endif
            @endif
          </a>
        @endforeach
      </div>

      @foreach ($sections as $s)
        @continue(! $s['found'])
        @php
          [$c, $bg] = $groupColors[$s['group']] ?? ['#15803d', '#f0fdf4'];
          $cols = array_keys($s['rows'][0]);
        @endphp
        <div id="cfs-{{ $s['key'] }}" class="bg-white border border-gray-200 rounded-lg overflow-hidden">
          <div class="flex items-center justify-between gap-2 px-4 py-2" style="border-left:4px solid {{ $c }};background:{{ $bg }}">
            <h3 class="font-semibold" style="color:{{ $c }}">{{ $s['group'] !== $s['label'] ? $s['group'] . ' · ' : '' }}{{ $s['label'] }}</h3>
            @if ($s['link'])<a href="{{ $s['link'] }}" class="px-3 py-1 rounded-md border border-gray-300 bg-white text-xs">Open in {{ $s['label'] }}</a>@endif
          </div>
          <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
              <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500"><tr><th class="px-3 py-2">S/N</th>@foreach ($cols as $col)<th class="px-3 py-2">{{ $col }}</th>@endforeach</tr></thead>
              <tbody>
                @foreach ($s['rows'] as $row)
                  <tr class="border-t border-gray-100">
                    <td class="px-3 py-2">{{ $loop->iteration }}</td>
                    @foreach ($cols as $col)<td class="px-3 py-2 {{ str_contains($col, 'File No') ? 'font-mono whitespace-nowrap' : '' }}">{{ ($row[$col] ?? '') !== '' ? $row[$col] : '—' }}</td>@endforeach
                  </tr>
                @endforeach
              </tbody>
            </table>
          </div>
          @if ($s['more'])<p class="px-4 py-2 text-xs text-gray-500">{{ $s['more'] }} more; open the list to see them all.</p>@endif
        </div>
      @endforeach
    @endif
  </div>
</div>
@endsection
