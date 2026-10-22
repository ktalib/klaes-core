{{--
    The main file's page count to and from Land (PageCounts, recording 2026-10-18), as a line
    in the step's task list. Going out: DMS's count is offered and Registry corrects it to the
    folio. Coming back: the count it went out with is shown beside the new one; more pages
    triggers the DMS update below. $pageCounts comes from PageCounts::state().
--}}
@php
  $pcOut = $pageCounts['direction'] === 'out';
  $pcRec = $pageCounts['recorded'];
  $pcWent = $pageCounts['out'];
  $pcDefault = $pcRec->pages ?? ($pcOut && $pageCounts['dms_pages'] ? $pageCounts['dms_pages'] : null);
@endphp
<li class="rounded-lg border px-3 py-2 {{ $pcRec ? 'border-green-200 bg-green-50' : 'border-blue-200 bg-blue-50' }}" x-data="{ edit: {{ $pcRec ? 'false' : 'true' }} }">
  <div class="flex flex-wrap items-center justify-between gap-3">
    <span class="text-sm {{ $pcRec ? 'text-green-800' : 'text-gray-800' }}">
      <i data-lucide="{{ $pcRec ? 'check-circle-2' : 'file-stack' }}" class="w-4 h-4 inline -mt-0.5"></i>
      {{ $pcOut ? 'Main file page count going to Land' : 'Main file page count back from Land' }}
    </span>
    @if ($pcRec)
      <span class="flex items-center gap-2 text-xs text-gray-600">
        <span class="font-semibold text-gray-800">{{ number_format($pcRec->pages) }} page(s)</span>
        · {{ optional(\App\Models\User::find($pcRec->recorded_by))->name }} · {{ \Illuminate\Support\Carbon::parse($pcRec->recorded_at)->format('d M H:i') }}
        <button type="button" x-show="!edit" @click="edit = true" class="text-blue-700 font-semibold hover:underline">Change</button>
      </span>
    @endif
  </div>

  @unless ($pcOut)
    {{-- Initial against new: the comparison that decides the DMS update. --}}
    <div class="mt-2 grid grid-cols-3 gap-2 text-center">
      <div class="rounded-lg bg-white border border-gray-200 px-2 py-1.5">
        <div class="text-[10px] font-bold uppercase text-gray-500">Went to Land with</div>
        <div class="text-lg font-bold text-gray-800">{{ $pcWent ? number_format($pcWent->pages) : '—' }}</div>
      </div>
      <div class="rounded-lg bg-white border border-gray-200 px-2 py-1.5">
        <div class="text-[10px] font-bold uppercase text-gray-500">Came back with</div>
        <div class="text-lg font-bold text-gray-800">{{ $pcRec ? number_format($pcRec->pages) : '—' }}</div>
      </div>
      <div class="rounded-lg border px-2 py-1.5 {{ $pageCounts['triggered'] ? 'bg-yellow-100 border-yellow-300' : 'bg-white border-gray-200' }}">
        <div class="text-[10px] font-bold uppercase text-gray-500">New pages</div>
        <div class="text-lg font-bold {{ $pageCounts['triggered'] ? 'text-yellow-800' : 'text-gray-800' }}">
          @if ($pageCounts['new_pages'] !== null)
            {{ max(0, $pageCounts['new_pages']) }}
          @elseif ($pcRec)
            ?
          @else
            —
          @endif
        </div>
      </div>
    </div>
    @if ($pcRec && ! $pcWent)
      <p class="mt-1 text-xs text-yellow-800">No count was taken when it went to Land, so the DMS update is needed to add the new pages.</p>
    @endif
  @endunless

  @if ($case->isOpen())
    <form method="POST" action="{{ route('cadastral-dept.cases.page-count.save', $case->id) }}" x-show="edit" x-cloak class="mt-2 flex flex-wrap items-center gap-2"
          data-confirm-title="Record the page count?" data-confirm="{{ $pcOut ? 'The number of pages in the main file as it goes to Land.' : 'The number of pages the main file came back with. More pages than went out starts the DMS update.' }}">
      @csrf
      <label class="text-xs font-semibold text-gray-600" for="pc-pages">Pages in the main file</label>
      <input type="number" min="1" max="100000" required name="pages" id="pc-pages" value="{{ $pcDefault }}"
             class="w-28 border border-gray-300 rounded-lg px-2 py-1 text-sm">
      <button class="px-3 py-1 rounded-md bg-green-700 text-white text-xs font-semibold">Save count</button>
      @if ($pcRec)
        <button type="button" @click="edit = false" class="text-xs text-gray-500 hover:underline">Cancel</button>
      @endif
      @if ($pcOut && $pageCounts['dms_pages'])
        <span class="text-xs text-gray-500">DMS holds {{ number_format($pageCounts['dms_pages']) }} page(s) for this file; correct it if the folio differs.</span>
      @elseif ($pcOut)
        <span class="text-xs text-gray-500">DMS holds no pages for this file yet; count the folio.</span>
      @else
        <span class="text-xs text-gray-500">Count the folio as it came back.</span>
      @endif
    </form>
  @endif
</li>
