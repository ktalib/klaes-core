{{-- Verification: the File Status lists the file is on (CaseController::fileStatus). --}}
@php
  $cdTone = [
    'warn' => ['bg-red-50 border-red-200', 'bg-red-100 text-red-800', 'alert-triangle', 'text-red-600'],
    'found' => ['bg-blue-50 border-blue-200', 'bg-blue-100 text-blue-800', 'check-circle-2', 'text-blue-600'],
    'clear' => ['bg-white border-gray-200', 'bg-gray-100 text-gray-500', 'minus-circle', 'text-gray-300'],
    'error' => ['bg-yellow-50 border-yellow-200', 'bg-yellow-100 text-yellow-800', 'help-circle', 'text-yellow-600'],
  ];
  $cdWarn = collect($rows)->where('tone', 'warn')->count();
  // Only the lists the file is on (user, 2026-10-15); "not on it" rows stay hidden.
  $cdShown = collect($rows)->reject(fn ($r) => $r['tone'] === 'clear');
  // Notices, worded as Legal Search's (user, 2026-10-15): red for what needs attention, blue for information.
  $cdNotes = collect($rows)->filter(fn ($r) => ($r['note'] ?? '') !== '')->sortBy(fn ($r) => $r['tone'] === 'warn' ? 0 : 1);
@endphp
@if ($cdNotes->isNotEmpty())
  <div class="space-y-2 mb-4">
    @foreach ($cdNotes as $r)
      @php $cdDanger = $r['tone'] === 'warn'; @endphp
      <div class="p-3 rounded-lg border {{ $cdDanger ? 'bg-red-50 border-red-400' : 'bg-blue-50 border-blue-300' }}">
        <div class="flex items-start gap-2">
          <i data-lucide="{{ $cdDanger ? 'alert-triangle' : 'info' }}" class="w-4 h-4 mt-0.5 flex-shrink-0 {{ $cdDanger ? 'text-red-600' : 'text-blue-600' }}"></i>
          <p class="text-sm {{ $cdDanger ? 'font-semibold text-red-700' : 'text-blue-800' }}">
            <span class="uppercase text-[11px] tracking-wide opacity-75">{{ $r['label'] }}:</span> {{ $r['note'] }}
          </p>
        </div>
      </div>
    @endforeach
  </div>
@endif
<div class="flex flex-wrap items-center justify-between gap-2 mb-3">
  <h3 class="text-xs font-bold uppercase tracking-wide text-gray-500">File status checks</h3>
  <span class="text-xs font-semibold {{ $cdWarn ? 'text-red-700' : 'text-green-700' }}">
    {{ $cdWarn ? $cdWarn . ' need' . ($cdWarn === 1 ? 's' : '') . ' attention' : 'Nothing needs attention' }}
  </span>
</div>
@if ($cdShown->isEmpty())
  <p class="rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm text-gray-600">This file is not on any of the File Status lists (RofO, CofO, Decommissioned, Duplicate, Re-grant, Title Status Update, DCIV, Caveat, Mortgage).</p>
@endif
<div class="grid gap-2 sm:grid-cols-2 xl:grid-cols-3">
  @foreach ($cdShown as $r)
    @php [$box, $chip, $icon, $iconCls] = $cdTone[$r['tone']] ?? $cdTone['error']; @endphp
    <div class="rounded-lg border px-3 py-2 {{ $box }}">
      <div class="flex items-center justify-between gap-2">
        <span class="flex items-center gap-1.5 text-sm font-semibold text-gray-800">
          <i data-lucide="{{ $icon }}" class="w-4 h-4 {{ $iconCls }}"></i>{{ $r['label'] }}
        </span>
        <span class="px-2 py-0.5 rounded-full text-[11px] font-bold whitespace-nowrap {{ $chip }}">{{ $r['status'] }}</span>
      </div>
      @if ($r['detail'] !== '')
        <div class="mt-1 text-xs text-gray-600 break-words">{{ $r['detail'] }}</div>
      @endif
    </div>
  @endforeach
</div>
<p class="mt-2 text-[11px] text-gray-400">Matched on every number the file is known by and its PropID. A mortgage counts as released when the file also has a Deed of Surrender &amp; Release.</p>
