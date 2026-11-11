{{-- The two extra FMC tabs' tables (CR 11112026-2, item 13). Read only; actions are View and the card. --}}
@php
  $cdMenuBtn = 'inline-flex items-center justify-center p-1.5 rounded-lg text-gray-500 hover:text-blue-600 hover:bg-gray-100';
  $cdMenuItem = 'flex w-full items-center gap-2 px-3 py-2 text-sm text-gray-700 hover:bg-gray-50';
@endphp
<div class="rounded-xl border border-gray-200 bg-white">
  @if ($fmcTab === 'waiting')
    <table class="min-w-full text-sm">
      <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500">
        <tr>
          <th class="px-4 py-2">S/N</th><th class="px-4 py-2">Ref</th><th class="px-4 py-2">File No.</th><th class="px-4 py-2">Title</th>
          <th class="px-4 py-2">Type</th><th class="px-4 py-2">Still to do before the FMC</th><th class="px-4 py-2">Date created</th>
          <th class="px-4 py-2 text-right">Action</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-gray-100">
        @forelse ($fmcWaiting as $case)
          <tr class="hover:bg-gray-50 align-top">
            <td class="px-4 py-2 text-gray-500">{{ $loop->iteration }}</td>
            <td class="px-4 py-2 font-mono whitespace-nowrap">{{ $case->case_ref }}</td>
            <td class="px-4 py-2 font-mono">{{ $case->file_number }}</td>
            <td class="px-4 py-2">{{ $case->file_title }}</td>
            <td class="px-4 py-2 whitespace-nowrap">{!! $def->wfChip($case->workflow) !!} <span class="text-xs text-gray-500">{{ $case->fileTypeLabel() }}</span></td>
            <td class="px-4 py-2">
              @foreach ((array) $case->fmc_waiting_for as $cdWait)
                <span class="inline-block mb-0.5 rounded px-2 py-0.5 text-xs font-semibold bg-amber-100 text-amber-900">{{ $cdWait }}</span>
              @endforeach
            </td>
            <td class="px-4 py-2 whitespace-nowrap">{{ optional($case->created_at)->format('d M Y') }}</td>
            <td class="px-4 py-2 text-right">
              <div class="relative inline-block text-left" x-data="{ open: false }" @keydown.escape.window="open = false">
                <button type="button" class="{{ $cdMenuBtn }}" aria-label="Actions" @click.stop="open = ! open"><i data-lucide="more-vertical" class="w-4 h-4"></i></button>
                <div x-show="open" x-cloak @click.outside="open = false" class="absolute right-0 z-30 mt-1 w-44 rounded-lg border border-gray-200 bg-white py-1 shadow-lg">
                  <a href="{{ route('cadastral-dept.cases.show', $case->id) }}" class="{{ $cdMenuItem }}"><i data-lucide="eye" class="w-4 h-4"></i> View</a>
                </div>
              </div>
            </td>
          </tr>
        @empty
          <tr><td colspan="8" class="px-4 py-6 text-center text-gray-400">No files waiting on charting.</td></tr>
        @endforelse
      </tbody>
    </table>
  @else
    <table class="min-w-full text-sm">
      <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500">
        <tr>
          <th class="px-4 py-2">S/N</th><th class="px-4 py-2">FMC No.</th><th class="px-4 py-2">File No.</th><th class="px-4 py-2">Title</th>
          <th class="px-4 py-2">Plot No.</th><th class="px-4 py-2">District</th><th class="px-4 py-2">LGA</th><th class="px-4 py-2">Location</th>
          <th class="px-4 py-2">Commissioned</th><th class="px-4 py-2">File is now</th>
          <th class="px-4 py-2 text-right">Action</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-gray-100">
        @forelse ($fmcAll as $fmc)
          @php $cdCase = $fmc->case; @endphp
          <tr class="hover:bg-gray-50 align-top">
            <td class="px-4 py-2 text-gray-500">{{ $loop->iteration }}</td>
            <td class="px-4 py-2 font-mono whitespace-nowrap">{{ $fmc->fmc_no }}</td>
            <td class="px-4 py-2 font-mono">{{ $fmc->file_number }}</td>
            <td class="px-4 py-2">{{ $fmc->file_title }}</td>
            <td class="px-4 py-2">{{ $fmc->plot_no }}</td>
            <td class="px-4 py-2">{{ $fmc->district }}</td>
            <td class="px-4 py-2">{{ $fmc->lga }}</td>
            <td class="px-4 py-2">{{ $fmc->location }}</td>
            <td class="px-4 py-2 whitespace-nowrap">
              {{ optional($fmc->commissioned_at)->format('d M Y H:i') }}
              @if ($fmc->commissioner)<div class="text-xs text-gray-500">{{ $fmc->commissioner->name }}</div>@endif
            </td>
            <td class="px-4 py-2 whitespace-nowrap">
              @if ($cdCase)
                @if ($cdCase->status === 'in_progress')
                  <span class="inline-block rounded px-2 py-0.5 text-xs font-semibold bg-blue-100 text-blue-800">{{ $cdCase->deskShort() }}</span>
                @else
                  <span class="inline-block rounded px-2 py-0.5 text-xs font-semibold bg-gray-200 text-gray-800">{{ $cdCase->status === 'returned' ? 'Returned' : 'Completed' }}</span>
                @endif
              @else
                <span class="text-xs text-gray-400">—</span>
              @endif
            </td>
            <td class="px-4 py-2 text-right">
              <div class="relative inline-block text-left" x-data="{ open: false }" @keydown.escape.window="open = false">
                <button type="button" class="{{ $cdMenuBtn }}" aria-label="Actions" @click.stop="open = ! open"><i data-lucide="more-vertical" class="w-4 h-4"></i></button>
                <div x-show="open" x-cloak @click.outside="open = false" class="absolute right-0 z-30 mt-1 w-48 rounded-lg border border-gray-200 bg-white py-1 shadow-lg">
                  @if ($cdCase)
                    @if ($fmcVisible)
                      <a href="{{ route('cadastral-dept.cases.fmc.show', $cdCase->id) }}" class="{{ $cdMenuItem }}"><i data-lucide="id-card" class="w-4 h-4"></i> Open FMC</a>
                      <a href="{{ route('cadastral-dept.cases.fmc.print', $cdCase->id) }}" target="_blank" class="{{ $cdMenuItem }}"><i data-lucide="printer" class="w-4 h-4"></i> Print FMC</a>
                    @endif
                    <a href="{{ route('cadastral-dept.cases.show', $cdCase->id) }}" class="{{ $cdMenuItem }}"><i data-lucide="eye" class="w-4 h-4"></i> View file</a>
                  @endif
                </div>
              </div>
            </td>
          </tr>
        @empty
          <tr><td colspan="11" class="px-4 py-6 text-center text-gray-400">No FMC has been opened yet.</td></tr>
        @endforelse
      </tbody>
    </table>
  @endif
</div>
