@extends('layouts.app')

@section('content')
<div class="flex-1 overflow-auto bg-gray-50" x-data="cdDesk()">
  @include('admin.header', ['PageTitle' => $deskInfo['label'], 'PageDescription' => $deskInfo['short'] . ': files at this desk'])

  <div class="p-6 max-w-7xl mx-auto">
    @include('cadastral_dept.partials._nav')

    {{-- Filters, and Registry's Register button --}}
    <form method="GET" class="flex flex-wrap items-center gap-2 mb-6">
      <input type="text" name="q" value="{{ $term }}" placeholder="File no., ref or title"
             class="border border-gray-300 rounded-lg px-3 py-2 text-sm w-64">
      <select name="workflow" class="border border-gray-300 rounded-lg px-3 py-2 text-sm">
        <option value="">All application types</option>
        @foreach ($def->workflows() as $k => $w)
          <option value="{{ $k }}" @selected($filterWorkflow === $k)>{{ $w['label'] }}</option>
        @endforeach
      </select>
      <button class="px-3 py-2 rounded-lg bg-gray-800 text-white text-sm font-semibold">Filter</button>
      @if ($term !== '' || $filterWorkflow)
        <a href="{{ route('cadastral-dept.desks.show', $desk) }}" class="text-sm text-gray-600 hover:underline">Clear</a>
      @endif
      @if ($desk === 'registry')
        <a href="{{ route('cadastral-dept.cases.create') }}" class="ml-auto inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-green-700 text-white text-sm font-semibold">
          <i data-lucide="file-plus" class="w-4 h-4"></i> Register File
        </a>
      @endif
    </form>

    @php
      // Files at this desk, one group per step, so "Recommend or not recommend"
      // and the other jobs at the same desk are listed apart.
      $groups = [];
      foreach ($here as $case) {
          $groups[$case->workflow . '|' . $case->current_step][] = $case;
      }
      $sections = [];
      foreach ($groups as $rows) {
          $sections[] = [$rows[0]->stepLabel(), collect($rows), true, $def->workflows()[$rows[0]->workflow]['short']];
      }
      if (! $sections) {
          $sections[] = ['At this desk', collect(), true, null];
      }
      if ($away->isNotEmpty()) {
          $sections[] = ['Out with other departments: record when they come back', $away, true, null];
      }
      if ($frozen->isNotEmpty()) {
          $sections[] = ['With other desks (view only)', $frozen, false, null];
      }
    @endphp

    @foreach ($sections as [$title, $rows, $actionable, $wfShort])
      <h3 class="text-sm font-bold uppercase tracking-wide text-gray-500 mb-3 {{ $loop->first ? '' : 'mt-8' }}">
        {{ $title }} @if ($wfShort)<span class="normal-case font-semibold text-gray-400">· {{ $wfShort }}</span>@endif ({{ $rows->count() }})
      </h3>
      <div class="rounded-xl border border-gray-200 bg-white">
        <table class="min-w-full text-sm">
          <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500">
            <tr>
              <th class="px-4 py-2">S/N</th><th class="px-4 py-2">Ref</th><th class="px-4 py-2">File No.</th><th class="px-4 py-2">Title</th>
              <th class="px-4 py-2">Type</th><th class="px-4 py-2">{{ $actionable ? 'Note' : 'Now with' }}</th>
              <th class="px-4 py-2">Since</th><th class="px-4 py-2 text-right">Action</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100">
            @forelse ($rows as $case)
              @php
                $m = $menus[$case->id] ?? null;
                $back = $actionable ? $case->sentBack() : null;
              @endphp
              <tr class="{{ $actionable ? 'hover:bg-gray-50' : 'bg-gray-50 text-gray-500' }}">
                <td class="px-4 py-2 text-gray-500">{{ $loop->iteration }}</td>
                <td class="px-4 py-2 font-mono whitespace-nowrap">
                  {{ $case->case_ref }}
                  @if ($case->duplicate_flag)
                    <span class="ml-1 px-1.5 py-0.5 rounded text-xs font-semibold bg-yellow-100 text-yellow-900" title="{{ $case->duplicate_note }}">Duplicate?</span>
                  @endif
                </td>
                <td class="px-4 py-2 font-mono">{{ $case->file_number }}</td>
                <td class="px-4 py-2">{{ $case->file_title }}</td>
                <td class="px-4 py-2 whitespace-nowrap">{!! $def->wfChip($case->workflow) !!} <span class="text-xs text-gray-500">{{ $case->fileTypeLabel() }}</span></td>
                <td class="px-4 py-2">
                  @if (! $actionable)
                    <span class="inline-flex items-center gap-1"><i data-lucide="lock" class="w-3 h-3"></i> {{ $case->deskShort() }}: {{ $case->stepLabel() }}</span>
                  @elseif ($back)
                    <span class="inline-block rounded bg-red-50 border border-red-200 px-2 py-1 text-xs text-red-800">
                      Sent back by {{ $back['desk'] }}@if ($back['reason']): “{{ $back['reason'] }}”@endif
                    </span>
                  @elseif ($title === 'Out with other departments: record when they come back')
                    <span class="text-xs text-gray-600">{{ $case->stepLabel() }}</span>
                  @else
                    <span class="text-xs text-gray-400">—</span>
                  @endif
                </td>
                <td class="px-4 py-2 text-gray-500 whitespace-nowrap">{{ optional($case->updated_at)->diffForHumans() }}</td>
                <td class="px-4 py-2 text-right">
                  @if (! $actionable)
                    @include('cadastral_dept.partials._row_actions', ['items' => $case->rowActions()])
                  @else
                    <div class="relative inline-block text-left" x-data="{ open: false }" @click.outside="open = false">
                      <button type="button" @click="open = !open" title="Actions" aria-label="Actions"
                              class="inline-flex items-center justify-center w-8 h-8 rounded-full text-gray-600 hover:bg-gray-100">
                        <i data-lucide="more-vertical" class="w-5 h-5"></i>
                      </button>
                      <div x-show="open" x-cloak class="absolute right-0 z-20 mt-1 w-72 rounded-lg border border-gray-200 bg-white shadow-lg py-1 text-left">
                        <a href="{{ route('cadastral-dept.cases.show', $case->id) }}" class="flex items-center gap-2 px-3 py-2 text-sm text-gray-700 hover:bg-gray-50"><i data-lucide="folder-open" class="w-4 h-4 text-gray-500"></i> Open file</a>
                        @if ($fmcInstalled && $case->fmc)
                          <a href="{{ route('cadastral-dept.cases.fmc.show', $case->id) }}" class="flex items-center gap-2 px-3 py-2 text-sm text-gray-700 hover:bg-gray-50"><i data-lucide="id-card" class="w-4 h-4 text-gray-500"></i> File Movement Card</a>
                        @endif
                        @if ($m && $m['question'])
                          <div class="px-3 pt-2 pb-1 text-xs font-bold text-gray-500 border-t border-gray-100">{{ $m['question'] }}</div>
                        @endif
                        @if ($m && $m['tasks_open'])
                          <a href="{{ route('cadastral-dept.cases.show', $case->id) }}" class="block px-3 py-2 text-sm text-yellow-800 hover:bg-yellow-50">
                            Finish {{ $m['tasks_open'] }} task(s) first
                          </a>
                        @elseif ($m && $m['failing'])
                          <a href="{{ route('cadastral-dept.cases.show', $case->id) }}" class="block px-3 py-2 text-sm text-yellow-800 hover:bg-yellow-50">
                            Not ready: {{ implode('; ', $m['failing']) }}
                          </a>
                        @elseif ($m)
                          @foreach ($m['actions'] as $a)
                            <button type="button"
                                    @click="open = false; ask(@js([
                                        'url' => route('cadastral-dept.cases.move.save', $case->id),
                                        'ref' => $case->case_ref, 'file' => $case->file_number,
                                        'outcome' => $a['outcome'], 'label' => $a['label'],
                                        'question' => $m['question'], 'reason' => $a['reason'], 'to' => $a['to'],
                                        'danger' => $a['reason'] || $a['ends'] === 'returned',
                                    ]))"
                                    class="w-full text-left px-3 py-2 text-sm hover:bg-gray-50 {{ $a['reason'] || $a['ends'] === 'returned' ? 'text-red-700' : 'text-blue-700' }}">
                              {{ $a['label'] }}
                            </button>
                          @endforeach
                        @endif
                      </div>
                    </div>
                  @endif
                </td>
              </tr>
            @empty
              <tr><td colspan="8" class="px-4 py-6 text-center text-gray-400">No files waiting here.</td></tr>
            @endforelse
          </tbody>
        </table>
      </div>
    @endforeach
  </div>

  {{-- Confirmation pop-up for every action; a refusal needs its reason here. --}}
  <div x-show="modal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-40 p-4" @keydown.escape.window="modal = null">
    <form method="POST" :action="modal && modal.url" class="w-full max-w-md rounded-xl bg-white shadow-xl p-5 space-y-4" @click.outside="modal = null">
      @csrf
      <input type="hidden" name="outcome" :value="modal && modal.outcome">
      <div>
        <div class="text-xs font-bold uppercase text-gray-500" x-text="modal && (modal.ref + ' · ' + modal.file)"></div>
        <div class="text-sm text-gray-600 mt-1" x-show="modal && modal.question" x-text="modal && modal.question"></div>
        <div class="text-lg font-bold text-gray-800 mt-1" x-text="modal && modal.label"></div>
        <div class="text-sm text-gray-600 mt-1" x-show="modal && modal.to">Sends the file to <strong x-text="modal && modal.to"></strong>.</div>
      </div>
      <div>
        <label class="block text-sm font-semibold text-gray-700 mb-1">
          <span x-text="modal && modal.reason ? 'Reason' : 'Remarks (optional)'"></span>
          <span class="text-red-600" x-show="modal && modal.reason">*</span>
        </label>
        <textarea name="remarks" rows="3" x-model="remarks" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm"
                  :placeholder="modal && modal.reason ? 'Say why. The desk that receives the file sees this.' : ''"></textarea>
      </div>
      <div class="flex justify-end gap-2">
        <button type="button" @click="modal = null" class="px-4 py-2 rounded-lg border border-gray-300 text-sm font-semibold text-gray-700">Cancel</button>
        <button type="submit" :disabled="modal && modal.reason && remarks.trim() === ''"
                :class="modal && modal.danger ? 'bg-red-600' : 'bg-blue-600'"
                class="px-4 py-2 rounded-lg text-white text-sm font-semibold disabled:opacity-50">Confirm</button>
      </div>
    </form>
  </div>
</div>
@endsection

@push('scripts')
<script>
  function cdDesk() {
    return {
      modal: null, remarks: '',
      ask(m) { this.remarks = ''; this.modal = m; },
    };
  }
</script>
@endpush
