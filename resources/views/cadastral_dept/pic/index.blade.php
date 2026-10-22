@extends('layouts.app')

@section('content')
<div class="flex-1 overflow-auto bg-gray-50">
  @include('admin.header', ['PageTitle' => 'Generate Property Index Card', 'PageDescription' => 'Cadastral Registry: one card per file, from the Legal Search records, filed to the file\'s EDMS folder (Cadastral Registry / PIC).'])

  <div class="p-6 space-y-6">
    @include('cadastral_dept.partials._nav')
    {{-- Opened from the button on Property Index Cards (Legacy Records) (2026-10-20). --}}
    <a href="{{ route('property_index_card.index') }}" class="inline-flex items-center gap-1 text-sm text-gray-600 hover:text-gray-800"><i data-lucide="arrow-left" class="w-4 h-4"></i> Property Index Cards (Legacy Records)</a>

    @if (! $installed)
      <div class="rounded-lg border border-yellow-200 bg-yellow-50 px-4 py-3 text-sm text-yellow-900">
        The Property Index Cards table has not been created yet, so cards cannot be filed. The preview still works.
      </div>
    @endif

    {{-- Pick a file (global file-number selector), then generate its card. --}}
    <div class="rounded-xl border border-gray-200 bg-white p-5">
      <div class="text-base font-bold text-gray-800">Generate a card</div>
      <p class="text-xs text-gray-500 mb-3">The card lists the file's details and every transaction in PRA, File History, CofO and Deeds Registration. A file has one card: generating again replaces it. Registering a file at Registry generates its card on its own.</p>
      <form method="POST" action="{{ route('cadastral-dept.pic.store') }}" class="flex flex-wrap items-center gap-2"
            data-confirm-title="Generate the Property Index Card?" data-confirm="The card is filed to the file's EDMS folder, replacing any earlier card.">
        @csrf
        <input type="hidden" name="file_number" id="pic-file" value="">
        <button type="button" id="pic-pick" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg border border-gray-300 bg-white text-sm font-semibold text-gray-700 hover:bg-gray-50">
          <i data-lucide="search" class="w-4 h-4"></i> <span id="pic-file-label">Select file number</span>
        </button>
        @if ($canGenerate)
          <button id="pic-generate" disabled class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-blue-600 text-white text-sm font-semibold disabled:opacity-50">
            <i data-lucide="file-plus" class="w-4 h-4"></i> Generate and file
          </button>
        @else
          <span class="text-xs text-gray-500">Only Registry generates cards.</span>
        @endif
        <a href="{{ route('cadastral-dept.pic.preview') }}" target="_blank" class="ml-auto text-xs text-gray-500 underline">Template sample</a>
      </form>
    </div>

    <div class="rounded-xl border border-gray-200 bg-white">
      <div class="flex flex-wrap items-center gap-3 px-5 py-3 border-b border-gray-100">
        <div class="text-base font-bold text-gray-800">Filed cards</div>
        <form method="GET" class="ml-auto flex items-center gap-2">
          <input type="text" name="q" value="{{ $search }}" placeholder="File number or title" class="border border-gray-300 rounded-lg px-3 py-1.5 text-sm">
          <button class="px-3 py-1.5 rounded-lg bg-gray-800 text-white text-sm font-semibold">Search</button>
        </form>
      </div>
      <div class="overflow-x-auto" x-data @scroll="window.dispatchEvent(new Event('scroll'))">
        <table class="min-w-full text-sm">
          <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500">
            <tr>
              <th class="px-4 py-2">S/N</th>
              <th class="px-4 py-2">File No.</th>
              <th class="px-4 py-2">File Title</th>
              <th class="px-4 py-2">Transactions</th>
              <th class="px-4 py-2">Generated</th>
              <th class="px-4 py-2">By</th>
              <th class="px-4 py-2">How</th>
              <th class="px-4 py-2 text-right"><span class="sr-only">Actions</span></th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100">
            @forelse (($rows ?? collect()) as $row)
              <tr>
                <td class="px-4 py-2 text-gray-500">{{ $rows->firstItem() + $loop->index }}</td>
                <td class="px-4 py-2 font-mono">{{ $row->file_number }}</td>
                <td class="px-4 py-2">{{ $row->file_title ?: '—' }}</td>
                <td class="px-4 py-2">{{ $row->transactions_count }}</td>
                <td class="px-4 py-2 whitespace-nowrap">
                  @if ($row->generated_at)
                    {{ \Illuminate\Support\Carbon::parse($row->generated_at)->format('d M Y H:i') }}
                    @if ($row->generation_count > 1)<span class="text-xs text-gray-500">({{ $row->generation_count }}×)</span>@endif
                  @else
                    <span class="text-gray-400">Not yet</span>
                  @endif
                  @if ($row->last_error)
                    <div class="text-xs text-red-700" title="{{ $row->last_error }}">Last attempt failed: {{ \Illuminate\Support\Str::limit($row->last_error, 80) }}</div>
                  @endif
                </td>
                <td class="px-4 py-2">{{ trim(ucfirst((string) $row->generated_by_first) . ' ' . ucfirst((string) $row->generated_by_last)) ?: '—' }}</td>
                <td class="px-4 py-2">{{ $row->trigger === 'intake' ? 'On registration' : 'Generated here' }}</td>
                <td class="px-4 py-2 text-right">
                  {{-- Fixed to the viewport, so the table's scroll box does not clip it. --}}
                  <div class="inline-block text-left" x-data="{ open: false, top: 0, left: 0,
                         toggle(e) { const r = e.currentTarget.getBoundingClientRect(); this.top = r.bottom + 4; this.left = Math.max(8, r.right - 224); this.open = !this.open; } }"
                       @click.outside="open = false" @keydown.escape.window="open = false" @scroll.window="open = false" @resize.window="open = false">
                    <button type="button" @click="toggle($event)" title="Actions" aria-label="Actions"
                            class="inline-flex items-center justify-center w-8 h-8 rounded-full text-gray-600 hover:bg-gray-100">
                      <i data-lucide="more-vertical" class="w-5 h-5"></i>
                    </button>
                    <div x-show="open" x-cloak :style="`top:${top}px;left:${left}px`" class="fixed z-50 w-56 rounded-lg border border-gray-200 bg-white shadow-lg py-1 text-left">
                      @if ($row->path)
                        <a href="{{ route('cadastral-dept.pic.show', ['file' => $row->file_number]) }}" target="_blank" class="flex items-center gap-2 px-3 py-2 text-sm text-gray-700 hover:bg-gray-50"><i data-lucide="file-text" class="w-4 h-4 text-gray-500"></i> View filed card</a>
                      @endif
                      <a href="{{ route('cadastral-dept.pic.preview', ['file' => $row->file_number]) }}" target="_blank" class="flex items-center gap-2 px-3 py-2 text-sm text-gray-700 hover:bg-gray-50"><i data-lucide="eye" class="w-4 h-4 text-gray-500"></i> Preview from the records</a>
                      @if ($canGenerate)
                        <form method="POST" action="{{ route('cadastral-dept.pic.store') }}"
                              data-confirm-title="Generate the card again?" data-confirm="{{ $row->file_number }}'s card is rebuilt from the records and replaces the filed one.">
                          @csrf
                          <input type="hidden" name="file_number" value="{{ $row->file_number }}">
                          <button class="w-full flex items-center gap-2 px-3 py-2 text-sm text-blue-700 hover:bg-gray-50"><i data-lucide="refresh-cw" class="w-4 h-4"></i> Generate again</button>
                        </form>
                      @endif
                    </div>
                  </div>
                </td>
              </tr>
            @empty
              <tr><td colspan="8" class="px-4 py-6 text-center text-gray-500">{{ $search !== '' ? 'No card matches.' : 'No cards filed yet.' }}</td></tr>
            @endforelse
          </tbody>
        </table>
      </div>
      @if ($rows && $rows->hasPages())
        <div class="px-5 py-3 border-t border-gray-100">{{ $rows->links() }}</div>
      @endif
    </div>
  </div>
  @include('admin.footer')
</div>

@include('components.global-fileno-modal')
@endsection

@push('scripts')
<script src="{{ asset('js/global-fileno-modal.js') }}"></script>
<script>
(function () {
  const input = document.getElementById('pic-file');
  const label = document.getElementById('pic-file-label');
  const generate = document.getElementById('pic-generate');
  document.getElementById('pic-pick').addEventListener('click', function () {
    GlobalFileNoModal.open({
      callback: function (data) {
        const no = (data && data.fileNumber) ? String(data.fileNumber).trim() : '';
        input.value = no;
        label.textContent = no || 'Select file number';
        if (generate) generate.disabled = !no;
      }
    });
  });
})();
</script>
@endpush
