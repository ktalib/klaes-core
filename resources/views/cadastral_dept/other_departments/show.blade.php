@extends('layouts.app')

@section('content')
{{-- Survey > Other Departments > Cadastral (user, 2026-10-28): the Cadastral files now with this Survey
     unit. Open a file to work on it and send it on (OtherDepartmentsController). --}}
<div class="flex-1 overflow-auto bg-gray-50">
  @include('admin.header', ['PageTitle' => $title, 'PageDescription' => 'Survey · Other Departments · Cadastral files with ' . $def->deskLabel($desk)])

  <div class="p-6 space-y-5">
    @if (session('error'))
      <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ session('error') }}</div>
    @endif

    <div class="flex flex-wrap items-center gap-3">
      <div class="rounded-xl border border-pink-200 bg-white px-4 py-3">
        <div class="text-xs font-semibold uppercase text-pink-700">With {{ $def->deskShort($desk) }}</div>
        <div class="text-2xl font-bold text-gray-800">{{ $rows->count() }}</div>
        <div class="text-xs text-gray-500">Cadastral files waiting here</div>
      </div>
      <form method="GET" class="flex flex-wrap items-center gap-2 ml-auto">
        <input type="text" name="q" value="{{ $term }}" placeholder="File no., ref or title" class="border border-gray-300 rounded-lg px-3 py-2 text-sm w-64">
        <button class="px-3 py-2 rounded-lg bg-gray-800 text-white text-sm font-semibold">Filter</button>
        @if ($term !== '')
          <a href="{{ route('cadastral-dept.other-departments.show', $desk) }}" class="text-sm text-gray-600 hover:underline">Clear</a>
        @endif
      </form>
    </div>

    <div class="rounded-xl border border-gray-200 bg-white overflow-x-auto">
      <table class="min-w-full text-sm">
        <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500">
          <tr>
            <th class="px-4 py-2">S/N</th><th class="px-4 py-2">Ref</th><th class="px-4 py-2">File No.</th><th class="px-4 py-2">Title</th>
            <th class="px-4 py-2">Plot No.</th><th class="px-4 py-2">District</th><th class="px-4 py-2">LGA</th><th class="px-4 py-2">Location</th>
            <th class="px-4 py-2">Step</th><th class="px-4 py-2">Arrived</th><th class="px-4 py-2 text-right">Action</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
          @forelse ($rows as $case)
            @php $cdLast = $case->latestMovement; @endphp
            <tr class="hover:bg-gray-50 align-top">
              <td class="px-4 py-2 text-gray-500">{{ $loop->iteration }}</td>
              <td class="px-4 py-2 font-mono whitespace-nowrap">{{ $case->case_ref }}</td>
              <td class="px-4 py-2 font-mono whitespace-nowrap">{{ $case->file_number }}</td>
              <td class="px-4 py-2 uppercase">{{ $case->file_title ?: '—' }}</td>
              <td class="px-4 py-2 uppercase">{{ $case->plot_no ?: '—' }}</td>
              <td class="px-4 py-2 uppercase">{{ $case->district ?: '—' }}</td>
              <td class="px-4 py-2 uppercase">{{ $case->lga ?: '—' }}</td>
              <td class="px-4 py-2 uppercase">{{ $case->location ?: '—' }}</td>
              <td class="px-4 py-2">{!! $def->wfChip($case->workflow) !!}<div class="text-xs text-gray-600 mt-0.5">{{ $case->stepLabel() }}</div></td>
              <td class="px-4 py-2 text-gray-600 whitespace-nowrap">
                {{ optional($cdLast?->acted_at)->format('d M Y H:i') ?: '—' }}
                @if ($cdLast && $cdLast->from_desk)
                  <div class="text-xs text-gray-500">from {{ $def->deskShort($cdLast->from_desk) }}</div>
                @endif
              </td>
              <td class="px-4 py-2 text-right">
                <div class="relative inline-block text-left" x-data="{ open: false }" @click.outside="open = false">
                  <button type="button" @click="open = !open" aria-label="Actions" title="Actions"
                          class="inline-flex items-center justify-center w-8 h-8 rounded-full text-gray-600 hover:bg-gray-100">
                    <i data-lucide="more-vertical" class="w-5 h-5"></i>
                  </button>
                  <div x-show="open" x-cloak class="absolute right-0 z-20 mt-1 w-52 rounded-lg border border-gray-200 bg-white shadow-lg py-1 text-left">
                    <a href="{{ route('cadastral-dept.cases.show', $case->id) }}" class="flex items-center gap-2 px-3 py-2 text-sm text-gray-700 hover:bg-gray-50">
                      <i data-lucide="folder-open" class="w-4 h-4 text-gray-500"></i> Open file to act
                    </a>
                  </div>
                </div>
              </td>
            </tr>
          @empty
            <tr><td colspan="11" class="px-4 py-10 text-center text-gray-500">No Cadastral file is with {{ $def->deskShort($desk) }} now.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
  @include('admin.footer')
</div>
@endsection
