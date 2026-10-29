@extends('layouts.app')

@section('content')
<div class="flex-1 overflow-auto bg-gray-50">
  @include('admin.header', ['PageTitle' => 'Cadastral Dashboard', 'PageDescription' => 'Files in the Cadastral Department, desk by desk'])

  <div class="p-6">
    @include('cadastral_dept.partials._nav')

    {{-- Dashboard cards: this workflow's own figures (2026-10-29). No Duplicates card for now. --}}
    @if (!empty($kpis))
      <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6 mb-6">
        @foreach ($kpis as $k)
          <a href="{{ $k['url'] }}" class="relative rounded-xl border border-gray-200 border-l-4 bg-white p-4 shadow-sm hover:shadow" style="border-left-color:#e11d48">
            <i data-lucide="{{ $k['icon'] }}" class="absolute top-4 right-4 w-6 h-6 text-rose-300"></i>
            <div class="text-xs font-semibold uppercase tracking-wide text-gray-600 pr-8">{{ $k['label'] }}</div>
            <div class="text-2xl font-black text-gray-800 mt-2">{{ $k['value'] }}</div>
            <div class="text-xs text-gray-400 mt-1">{{ $k['hint'] }}</div>
          </a>
        @endforeach
      </div>
    @endif

    {{-- Open files per application type --}}
    <div class="grid gap-4 md:grid-cols-3 mb-8">
      @foreach ($def->workflows() as $wfKey => $wf)
        <a href="{{ route('cadastral-dept.cases.index', ['workflow' => $wfKey, 'status' => 'in_progress']) }}"
           class="rounded-xl border border-{{ $wf['colour'] }}-200 bg-white p-5 hover:shadow">
          <div class="flex items-center gap-2 text-sm font-semibold text-{{ $wf['colour'] }}-700">
            <i data-lucide="{{ $wf['icon'] ?? 'folder' }}" class="w-4 h-4"></i>{{ $wf['label'] }}
          </div>
          <div class="text-3xl font-black text-gray-800 mt-1">{{ number_format($byWorkflow[$wfKey] ?? 0) }}</div>
          <div class="text-xs text-gray-500">open files</div>
        </a>
      @endforeach
    </div>

    {{-- Desk inboxes --}}
    <h3 class="text-sm font-bold uppercase tracking-wide text-gray-500 mb-3">Desks</h3>
    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4 mb-8">
      @foreach ($def->internalDesks() as $deskKey => $desk)
        <a href="{{ route('cadastral-dept.desks.show', $deskKey) }}"
           class="flex items-center justify-between rounded-xl border border-gray-200 bg-white px-4 py-3 hover:bg-gray-50">
          <span class="text-sm font-semibold text-gray-700">{{ $desk['label'] }}</span>
          <span class="ml-3 inline-flex items-center justify-center px-2 py-0.5 rounded-full text-sm font-bold {{ ($byDesk[$deskKey] ?? 0) ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-400' }}">
            {{ $byDesk[$deskKey] ?? 0 }}
          </span>
        </a>
      @endforeach
    </div>

    {{-- Latest intakes --}}
    <h3 class="text-sm font-bold uppercase tracking-wide text-gray-500 mb-3">Latest files received</h3>
    <div class="rounded-xl border border-gray-200 bg-white overflow-x-auto">
      <table class="min-w-full text-sm">
        <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500">
          <tr><th class="px-4 py-2">S/N</th><th class="px-4 py-2">Ref</th><th class="px-4 py-2">File No.</th><th class="px-4 py-2">Type</th><th class="px-4 py-2">Now at</th><th class="px-4 py-2">Status</th><th class="px-4 py-2 text-right">Action</th></tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
          @forelse ($recent as $case)
            <tr class="hover:bg-gray-50">
              <td class="px-4 py-2 text-gray-500">{{ $loop->iteration }}</td>
              <td class="px-4 py-2 font-mono">{{ $case->case_ref }}</td>
              <td class="px-4 py-2 font-mono">{{ $case->file_number }}</td>
              <td class="px-4 py-2">{!! $def->wfChip($case->workflow) !!}</td>
              <td class="px-4 py-2">{{ $case->isOpen() ? $case->deskShort() : '—' }}</td>
              <td class="px-4 py-2">{!! $def->statusChip($case->status) !!}</td>
              <td class="px-4 py-2 text-right">@include('cadastral_dept.partials._row_actions', ['items' => $case->rowActions()])</td>
            </tr>
          @empty
            <tr><td colspan="7" class="px-4 py-6 text-center text-gray-400">No files received yet.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
  @include('admin.footer')
</div>
@endsection
