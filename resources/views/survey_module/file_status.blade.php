@extends('survey_module.layouts.klaes')

@section('page-title', 'File Status — KLAES')

@php
  $PageTitle = 'File Status';
  $PageDescription = 'Search a file by file number, OP serial number or name to see where it appears across every File Status list.';
  $inputStyle = 'padding:8px 14px;border:1.5px solid var(--gray-300);border-radius:var(--radius-sm);font-family:var(--font);font-size:14px;background:#fff;';
  // Tab colours, as the File Status tabs draw them.
  $groupColors = [
      'RofO' => ['#be123c', '#fff1f2'], 'CofO' => ['#b45309', '#fffbeb'], 'Decommissioned' => ['#475569', '#f1f5f9'],
      'Duplicates' => ['#6d28d9', '#f5f3ff'], 'Regrant Files' => ['#0f766e', '#f0fdfa'], 'Title Status' => ['#047857', '#ecfdf5'],
      'DCIV' => ['#1d4ed8', '#eff6ff'], 'Encumbrance' => ['#c2410c', '#fff7ed'],
  ];
  $foundCount = collect($sections)->where('found', true)->count();
@endphp

@section('survey-content')
{{-- CR 30102026 item 29 (user, 2026-10-31): File Status opens on this search; each list is still a tab. --}}
@include('survey_module.partials._file_status_tabs')

<style>
  .fs-summary { display:grid; grid-template-columns:repeat(auto-fill, minmax(170px, 1fr)); gap:8px; margin:12px 0 18px; }
  .fs-chip { display:flex; flex-direction:column; gap:2px; padding:10px 12px; border-radius:var(--radius-sm); border:1.5px solid var(--gray-200); background:#fff; text-decoration:none; color:var(--gray-500); }
  .fs-chip .fs-chip-group { font-size:11px; text-transform:uppercase; letter-spacing:.04em; }
  .fs-chip .fs-chip-label { font-weight:600; font-size:14px; color:var(--gray-700); }
  .fs-chip .fs-chip-state { font-size:12px; }
  .fs-chip.is-found { border-color:var(--c); background:var(--bg); }
  .fs-chip.is-found .fs-chip-state { color:var(--c); font-weight:700; }
  .fs-section { margin-bottom:18px; }
  .fs-section-head { display:flex; align-items:center; justify-content:space-between; gap:10px; padding:10px 14px; border-left:4px solid var(--c); background:var(--bg); border-radius:var(--radius-sm) var(--radius-sm) 0 0; }
  .fs-section-head h3 { margin:0; font-size:15px; color:var(--c); }
  .fs-file { display:flex; flex-wrap:wrap; align-items:baseline; gap:6px 16px; padding:14px 16px; border:1.5px solid var(--gray-200); border-radius:var(--radius-sm); background:#fff; }
  .fs-file .fs-no { font-family:monospace; font-size:18px; font-weight:700; }
</style>

<form method="GET" action="{{ route('survey-module.file-status') }}" class="table-toolbar" style="margin-top:16px;">
    <div class="left" style="flex-wrap:wrap;">
        <select name="by" aria-label="Search by" style="{{ $inputStyle }}">
            @foreach (\App\Services\FileStatusLookupService::BY as $k => $label)
                <option value="{{ $k }}" @selected($by === $k)>{{ $label }}</option>
            @endforeach
        </select>
        <input type="text" name="q" value="{{ $q }}" required autofocus
               placeholder="File number, OP serial number or name…" style="{{ $inputStyle }}width:320px;" />
        <button class="btn btn-primary" type="submit"><i class="fas fa-search"></i> Search</button>
        @if ($q !== '')
            <a class="btn btn-outline btn-sm" href="{{ route('survey-module.file-status') }}">Clear</a>
        @endif
    </div>
</form>

@if ($q === '')
    <div style="text-align:center;padding:40px 16px;color:var(--gray-500);">
        <i class="fas fa-magnifying-glass" style="font-size:28px;display:block;margin-bottom:10px;opacity:.4;"></i>
        Search a file to see where it appears: RofO, CofO, Decommissioned, Duplicates, Regrant Files, Title Status, DCIV and Encumbrance.
        <div style="margin-top:6px;font-size:13px;">Or open any list from the tabs above.</div>
    </div>
@elseif (! $file)
    {{-- An OP serial or name matched no file, or several: pick one. --}}
    @if (empty($candidates))
        <div style="text-align:center;padding:32px 16px;color:var(--gray-500);">
            <i class="fas fa-folder-open" style="font-size:24px;display:block;margin-bottom:8px;opacity:.4;"></i>
            @if ($by === 'op')
                No OP with serial number <strong>{{ $q }}</strong> was found.
            @else
                No file was found for the name <strong>{{ $q }}</strong>.
            @endif
        </div>
    @else
        <p style="margin:8px 0;color:var(--gray-600);">{{ count($candidates) }} {{ \Illuminate\Support\Str::plural('file', count($candidates)) }} match <strong>{{ $q }}</strong>{{ count($candidates) >= 50 ? ' (first 50 shown; refine the search)' : '' }}. Pick one to see its status.</p>
        <div class="table-wrapper">
            <div class="table-scroll">
                <table>
                    <thead>
                        <tr><th>S/N</th><th>File No.</th><th>Title / Name</th><th>Found by</th><th></th></tr>
                    </thead>
                    <tbody>
                        @foreach ($candidates as $c)
                            <tr>
                                <td>{{ $loop->iteration }}</td>
                                <td><strong style="font-family:monospace;">{{ $c['file_number'] }}</strong></td>
                                <td>{{ $c['title'] ?: '—' }}</td>
                                <td>{{ $c['via'] ?: '—' }}</td>
                                <td><a class="btn btn-outline btn-sm" href="{{ route('survey-module.file-status', ['by' => 'file', 'q' => $c['file_number']]) }}">Check status</a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
@else
    <div class="fs-file">
        <span class="fs-no">{{ $file['file_number'] }}</span>
        @if ($file['title'])<span style="font-weight:600;">{{ $file['title'] }}</span>@endif
        @if ($file['via'])<span style="color:var(--gray-500);font-size:13px;">Found by {{ $file['via'] }}</span>@endif
        @if (count($file['aliases']) > 1)
            <span style="color:var(--gray-500);font-size:13px;width:100%;">Also recorded as: {{ implode(', ', array_slice($file['aliases'], 1)) }}</span>
        @endif
        <span style="width:100%;font-size:13px;color:{{ $foundCount ? 'var(--gray-600)' : '#b91c1c' }};">
            {{ $foundCount ? "Found in {$foundCount} of " . count($sections) . ' lists.' : 'Not found in any File Status list.' }}
        </span>
    </div>

    {{-- One card per list: found ones are coloured and jump to their rows below. --}}
    <div class="fs-summary">
        @foreach ($sections as $s)
            @php [$c, $bg] = $groupColors[$s['group']] ?? ['#be185d', '#fdf2f8']; @endphp
            <a href="{{ $s['found'] ? '#fs-' . $s['key'] : ($s['link'] ?? '#') }}" style="--c:{{ $c }};--bg:{{ $bg }};"
               class="fs-chip {{ $s['found'] ? 'is-found' : '' }}">
                @if ($s['group'] !== $s['label'])<span class="fs-chip-group">{{ $s['group'] }}</span>@endif
                <span class="fs-chip-label">{{ $s['label'] }}</span>
                <span class="fs-chip-state">
                    @if ($s['found'])
                        <i class="fas fa-circle-check"></i> Found ({{ count($s['rows']) + $s['more'] }})
                    @else
                        {{ $s['note'] ?? 'Not found' }}
                    @endif
                </span>
            </a>
        @endforeach
    </div>

    @foreach ($sections as $s)
        @continue(! $s['found'])
        @php
            [$c, $bg] = $groupColors[$s['group']] ?? ['#be185d', '#fdf2f8'];
            $cols = array_keys($s['rows'][0]);
        @endphp
        <div class="fs-section" id="fs-{{ $s['key'] }}" style="--c:{{ $c }};--bg:{{ $bg }};">
            <div class="fs-section-head">
                <h3>{{ $s['group'] !== $s['label'] ? $s['group'] . ' · ' : '' }}{{ $s['label'] }}</h3>
                @if ($s['link'])
                    <a href="{{ $s['link'] }}" class="btn btn-outline btn-sm"><i class="fas fa-arrow-up-right-from-square"></i> Open in {{ $s['label'] }}</a>
                @endif
            </div>
            <div class="table-wrapper" style="border-top-left-radius:0;border-top-right-radius:0;">
                <div class="table-scroll">
                    <table>
                        <thead>
                            <tr>
                                <th>S/N</th>
                                @foreach ($cols as $col)<th>{{ $col }}</th>@endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($s['rows'] as $row)
                                <tr>
                                    <td>{{ $loop->iteration }}</td>
                                    @foreach ($cols as $col)
                                        <td @if (str_contains($col, 'File No')) style="font-family:monospace;white-space:nowrap;" @endif>{{ ($row[$col] ?? '') !== '' ? $row[$col] : '—' }}</td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
            @if ($s['more'])
                <p style="margin:6px 0 0;font-size:13px;color:var(--gray-500);">{{ $s['more'] }} more; open the list to see them all.</p>
            @endif
        </div>
    @endforeach
@endif
@endsection
