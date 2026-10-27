@extends('layouts.app')

@php
    $label  = $config['label'];
    $isCop  = $type === 'change-of-purpose';
    $tabs   = \App\Services\ParcelRegisterService::TYPES;
    $sourceStyles = [
        \App\Services\ParcelRegisterService::SOURCE_SPU    => 'bg-violet-50 text-violet-700 border-violet-200',
        \App\Services\ParcelRegisterService::SOURCE_DUPLEX => 'bg-sky-50 text-sky-700 border-sky-200',
        \App\Services\ParcelRegisterService::SOURCE_LEGACY => 'bg-amber-50 text-amber-700 border-amber-200',
    ];
    $sourceCodes = \App\Services\ParcelRegisterService::SOURCE_CODES;
    $folioIds    = $folioIds ?? [];
    $folioUrl    = fn ($f) => isset($folioIds[strtoupper(trim((string) $f))]) ? route('vfs.show', $folioIds[strtoupper(trim((string) $f))]) : null;
    $statusStyle = function (string $s) {
        $s = strtolower($s);
        if (in_array($s, ['commissioned', 'committed', 'linked'])) return 'bg-green-50 text-green-700';
        if (in_array($s, ['approved', 'in land'])) return 'bg-blue-50 text-blue-700';
        if (in_array($s, ['rejected'])) return 'bg-red-50 text-red-700';
        return 'bg-gray-100 text-gray-700';
    };
@endphp

@section('content')
<div class="flex-1 overflow-auto bg-gray-50">
  @include('admin.header', [
      'PageTitle'       => 'Parcel Update',
      'PageDescription' => $label . ' register: every ' . $label . ' on record from Parcel Update, Advance Parcel Update (Duplex) and Parcel Update - Legacy, in one table.',
  ])

  <div class="p-6 space-y-5">
    {{-- One register per type; the tabs move between them. --}}
    <div class="flex flex-wrap gap-2">
      @foreach ($tabs as $tabKey => $tab)
        <a href="{{ route('parcel-register.index', ['type' => $tabKey, 'mode' => $mode]) }}"
           class="inline-flex items-center gap-2 px-4 py-2 rounded-lg border text-sm font-semibold {{ $tabKey === $type ? 'bg-gray-800 text-white border-gray-800' : 'bg-white text-gray-700 border-gray-300 hover:bg-gray-50' }}">
          <i data-lucide="{{ $tab['icon'] }}" class="w-4 h-4"></i> {{ $tab['label'] }}
        </a>
      @endforeach
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
      @foreach ($sourceStyles as $src => $style)
        <a href="{{ route('parcel-register.index', ['type' => $type, 'mode' => $mode, 'source' => $source === $src ? '' : $src]) }}"
           class="rounded-xl border bg-white px-4 py-3 hover:shadow-sm {{ $source === $src ? 'ring-2 ring-gray-800' : '' }}">
          <div class="text-xs font-semibold uppercase text-gray-500">{{ $sourceCodes[$src][0] ?? $src }} · {{ $sourceCodes[$src][1] ?? '' }}</div>
          <div class="text-2xl font-bold text-gray-800">{{ number_format($counts[$src] ?? 0) }}</div>
        </a>
      @endforeach
    </div>

    <div class="rounded-xl border border-gray-200 bg-white">
      <form method="GET" class="flex flex-wrap items-center gap-2 px-5 py-3 border-b border-gray-100">
        <input type="hidden" name="mode" value="{{ $mode }}">
        <div class="text-base font-bold text-gray-800 mr-auto">{{ $label }}s</div>
        <input type="text" name="search" value="{{ $search }}" placeholder="File no., applicant, plot, district, LGA"
               class="border border-gray-300 rounded-lg px-3 py-1.5 text-sm w-72">
        <select name="source" class="border border-gray-300 rounded-lg px-3 py-1.5 text-sm">
          <option value="">All sources</option>
          @foreach (array_keys($sourceStyles) as $src)
            <option value="{{ $src }}" @selected($source === $src)>{{ $sourceCodes[$src][0] ?? $src }} — {{ $sourceCodes[$src][1] ?? $src }}</option>
          @endforeach
        </select>
        <select name="status" class="border border-gray-300 rounded-lg px-3 py-1.5 text-sm">
          <option value="">All statuses</option>
          @foreach ($statuses as $st)
            <option value="{{ $st }}" @selected(strcasecmp($status, $st) === 0)>{{ $st }}</option>
          @endforeach
        </select>
        <button class="px-3 py-1.5 rounded-lg bg-gray-800 text-white text-sm font-semibold">Filter</button>
        @if ($search !== '' || $source !== '' || $status !== '')
          <a href="{{ route('parcel-register.index', ['type' => $type, 'mode' => $mode]) }}" class="text-sm text-gray-500 underline">Clear</a>
        @endif
      </form>

      <div class="overflow-x-auto" x-data @scroll="window.dispatchEvent(new Event('scroll'))">
        <table class="min-w-full text-sm">
          <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500">
            <tr>
              <th class="px-3 py-2">S/N</th>
              <th class="px-3 py-2">Source</th>
              <th class="px-3 py-2 whitespace-nowrap">Reference</th>
              <th class="px-3 py-2 whitespace-nowrap">{{ $type === 'merger' ? 'Source Files' : 'File No.' }}</th>
              <th class="px-3 py-2 whitespace-nowrap">{{ $type === 'merger' ? 'Merged File' : 'New File No(s)' }}</th>
              <th class="px-3 py-2">Applicant</th>
              <th class="px-3 py-2 whitespace-nowrap">Plot No.</th>
              <th class="px-3 py-2">District</th>
              <th class="px-3 py-2">LGA</th>
              <th class="px-3 py-2">Location</th>
              @if ($isCop)
                <th class="px-3 py-2 whitespace-nowrap">Purpose Change</th>
              @else
                <th class="px-3 py-2">Plots</th>
              @endif
              <th class="px-3 py-2">Status</th>
              <th class="px-3 py-2">Date</th>
              <th class="px-3 py-2 text-right"><span class="sr-only">Actions</span></th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100">
            @forelse ($rows as $row)
              @php
                [$srcCode, $srcName] = $sourceCodes[$row->source] ?? [$row->source, $row->source];
                $rowFiles = array_values(array_unique(array_merge($row->from_files, $row->to_files)));
                $folios   = [];
                foreach ($rowFiles as $f) {
                    if ($u = $folioUrl($f)) $folios[$f] = $u;
                }
                $payload = [
                    'title'     => $label . ' · ' . $row->reference,
                    'source'    => $srcCode . ' · ' . $srcName . ($row->origin ? ' (' . $row->origin . ')' : ''),
                    'folios'    => (object) $folios,
                    'reference' => $row->reference,
                    'from'      => $row->from_files,
                    'to'        => $row->to_files,
                    'applicant' => $row->applicant,
                    'plot_no'   => $row->plot_no,
                    'district'  => $row->district,
                    'lga'       => $row->lga,
                    'location'  => $row->location,
                    'plots'     => $row->plots,
                    'status'    => $row->status,
                    'detail'    => $row->detail,
                    'by'        => $row->by,
                    'date'      => $row->date ? $row->date->format('d M Y H:i') : '',
                ];
              @endphp
              <tr class="align-top">
                <td class="px-3 py-2 text-gray-500">{{ $rows->firstItem() + $loop->index }}</td>
                <td class="px-3 py-2">
                  <span class="inline-block px-2 py-0.5 rounded border text-xs font-semibold whitespace-nowrap {{ $sourceStyles[$row->source] ?? '' }}" title="{{ $srcName }}">{{ $srcCode }}</span>
                  @if ($row->origin)<div class="text-xs text-gray-400 mt-0.5">{{ $row->origin }}</div>@endif
                </td>
                <td class="px-3 py-2 font-mono text-xs whitespace-nowrap">{{ $row->reference ?: '—' }}</td>
                @foreach ([$row->from_files, $row->to_files] as $list)
                  <td class="px-3 py-2 font-mono text-xs whitespace-nowrap">
                    @forelse (array_slice($list, 0, 2) as $f)
                      <div>{{ $f }}</div>
                    @empty
                      <span class="text-gray-400">—</span>
                    @endforelse
                    @if (count($list) > 2)
                      <div class="text-gray-500 font-sans">+{{ count($list) - 2 }} more</div>
                    @endif
                  </td>
                @endforeach
                <td class="px-3 py-2">{{ $row->applicant ?: '—' }}</td>
                <td class="px-3 py-2">{{ $row->plot_no ?: '—' }}</td>
                <td class="px-3 py-2">{{ $row->district ?: '—' }}</td>
                <td class="px-3 py-2">{{ $row->lga ?: '—' }}</td>
                <td class="px-3 py-2">{{ $row->location ?: '—' }}</td>
                @if ($isCop)
                  <td class="px-3 py-2 text-xs">{{ $row->detail ?: '—' }}</td>
                @else
                  <td class="px-3 py-2">{{ $row->plots ?? '—' }}</td>
                @endif
                <td class="px-3 py-2"><span class="inline-block px-2 py-0.5 rounded text-xs font-semibold whitespace-nowrap {{ $statusStyle($row->status) }}">{{ $row->status ?: '—' }}</span></td>
                <td class="px-3 py-2 whitespace-nowrap text-xs">{{ $row->date ? $row->date->format('d M Y') : '—' }}</td>
                <td class="px-3 py-2 text-right">
                  {{-- Fixed to the viewport, so the table's scroll box does not clip it. --}}
                  <div class="inline-block text-left" x-data="{ open: false, top: 0, left: 0,
                         toggle(e) { const r = e.currentTarget.getBoundingClientRect(); this.top = r.bottom + 4; this.left = Math.max(8, r.right - 256); this.open = !this.open; } }"
                       @click.outside="open = false" @keydown.escape.window="open = false" @scroll.window="open = false" @resize.window="open = false">
                    <button type="button" @click.stop="toggle($event)" title="Actions" aria-label="Actions"
                            class="inline-flex items-center justify-center w-8 h-8 rounded-full text-gray-600 hover:bg-gray-100">
                      <i data-lucide="more-vertical" class="w-5 h-5"></i>
                    </button>
                    <div x-show="open" x-cloak :style="`top:${top}px;left:${left}px`" class="fixed z-50 w-64 max-h-96 overflow-y-auto rounded-lg border border-gray-200 bg-white shadow-lg py-1 text-left">
                      <div class="px-3 py-1.5 text-xs text-gray-500 border-b border-gray-100">
                        From <span class="font-semibold {{ $sourceStyles[$row->source] ?? '' }} px-1.5 py-0.5 rounded border">{{ $srcCode }}</span> {{ $srcName }}
                      </div>
                      <button type="button" @click="open = false; $dispatch('register-view', {{ \Illuminate\Support\Js::from($payload) }})"
                              class="w-full flex items-center gap-2 px-3 py-2 text-sm text-gray-700 hover:bg-gray-50">
                        <i data-lucide="eye" class="w-4 h-4 text-gray-500"></i> View details
                      </button>
                      <a href="{{ $row->open_url }}" class="flex items-center gap-2 px-3 py-2 text-sm text-gray-700 hover:bg-gray-50">
                        <i data-lucide="external-link" class="w-4 h-4 text-gray-500"></i>
                        {{ $row->source === \App\Services\ParcelRegisterService::SOURCE_LEGACY ? 'Open Parcel Update - Legacy' : 'Open in workflow' }}
                      </a>
                      {{-- View Folio: one entry per file that has an indexed folio (VFS, Super Admin only). --}}
                      @if ($folios)
                        <div class="border-t border-gray-100 my-1"></div>
                        @foreach (array_slice($folios, 0, 6, true) as $f => $u)
                          <a href="{{ $u }}" target="_blank" rel="noopener" class="flex items-center gap-2 px-3 py-2 text-sm text-gray-700 hover:bg-gray-50">
                            <i data-lucide="folder-open" class="w-4 h-4 text-amber-500"></i>
                            <span>View Folio <span class="font-mono text-xs text-gray-500">{{ $f }}</span></span>
                          </a>
                        @endforeach
                        @if (count($folios) > 6)
                          <button type="button" @click="open = false; $dispatch('register-view', {{ \Illuminate\Support\Js::from($payload) }})"
                                  class="w-full flex items-center gap-2 px-3 py-2 text-sm text-gray-700 hover:bg-gray-50">
                            <i data-lucide="folders" class="w-4 h-4 text-amber-500"></i> All folios ({{ count($folios) }})
                          </button>
                        @endif
                      @endif
                    </div>
                  </div>
                </td>
              </tr>
            @empty
              <tr><td colspan="14" class="px-4 py-6 text-center text-gray-500">
                {{ ($search !== '' || $source !== '' || $status !== '') ? 'No record matches.' : 'No ' . $label . ' on record yet.' }}
              </td></tr>
            @endforelse
          </tbody>
        </table>
      </div>
      @if ($rows->hasPages())
        <div class="px-5 py-3 border-t border-gray-100">{{ $rows->links() }}</div>
      @endif
    </div>
  </div>

  {{-- Read-only details: the full file lists the table truncates. --}}
  <div x-data="{ open: false, r: {} }" @register-view.window="r = $event.detail; open = true" @keydown.escape.window="open = false">
    <div x-show="open" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-40 p-4" @click.self="open = false">
      <div class="bg-white rounded-xl shadow-xl w-full max-w-2xl max-h-full overflow-y-auto">
        <div class="flex items-center justify-between px-5 py-3 border-b border-gray-100">
          <div class="font-bold text-gray-800" x-text="r.title"></div>
          <button type="button" @click="open = false" aria-label="Close" class="p-1 rounded hover:bg-gray-100"><i data-lucide="x" class="w-5 h-5"></i></button>
        </div>
        <div class="p-5 space-y-4 text-sm">
          <dl class="grid grid-cols-2 gap-x-6 gap-y-2">
            <template x-for="[k, v] in [['Source', r.source], ['Reference', r.reference], ['Applicant', r.applicant], ['Plot No.', r.plot_no], ['District', r.district], ['LGA', r.lga], ['Location', r.location], ['Plots', r.plots], ['Status', r.status], ['{{ $isCop ? 'Purpose Change' : 'Remarks' }}', r.detail], ['Processed By', r.by], ['Date', r.date]]" :key="k">
              <div>
                <dt class="text-xs uppercase text-gray-500" x-text="k"></dt>
                <dd class="text-gray-800" x-text="(v === null || v === undefined || v === '') ? '—' : v"></dd>
              </div>
            </template>
          </dl>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <div class="text-xs uppercase text-gray-500 mb-1">{{ $type === 'merger' ? 'Source Files' : 'File No.' }} (<span x-text="(r.from || []).length"></span>)</div>
              <div class="border border-gray-200 rounded-lg max-h-48 overflow-y-auto font-mono text-xs p-2">
                <template x-for="f in (r.from || [])" :key="f">
                  <div class="flex items-center justify-between gap-2">
                    <span x-text="f"></span>
                    <a x-show="r.folios && r.folios[f]" :href="r.folios && r.folios[f]" target="_blank" rel="noopener" class="font-sans text-amber-600 hover:underline whitespace-nowrap">View Folio</a>
                  </div>
                </template>
                <div x-show="!(r.from || []).length" class="text-gray-400 font-sans">—</div>
              </div>
            </div>
            <div>
              <div class="text-xs uppercase text-gray-500 mb-1">{{ $type === 'merger' ? 'Merged File' : 'New File No(s)' }} (<span x-text="(r.to || []).length"></span>)</div>
              <div class="border border-gray-200 rounded-lg max-h-48 overflow-y-auto font-mono text-xs p-2">
                <template x-for="f in (r.to || [])" :key="f">
                  <div class="flex items-center justify-between gap-2">
                    <span x-text="f"></span>
                    <a x-show="r.folios && r.folios[f]" :href="r.folios && r.folios[f]" target="_blank" rel="noopener" class="font-sans text-amber-600 hover:underline whitespace-nowrap">View Folio</a>
                  </div>
                </template>
                <div x-show="!(r.to || []).length" class="text-gray-400 font-sans">—</div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  @include('admin.footer')
</div>
@endsection
