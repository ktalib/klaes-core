@extends('layouts.app')

@section('content')
<div class="flex-1 overflow-auto bg-gray-50">
  @include('admin.header', ['PageTitle' => 'Cadastral Files', 'PageDescription' => 'Every main file received by the Cadastral Department'])

  <div class="p-6 max-w-7xl mx-auto">
    @include('cadastral_dept.partials._nav')

    <form method="GET" class="flex flex-wrap items-end gap-3 mb-4">
      <div>
        <label class="block text-xs font-semibold text-gray-500 mb-1">Search</label>
        <input type="text" name="q" value="{{ request('q') }}" placeholder="File no., ref or title"
               class="border border-gray-300 rounded-lg px-3 py-2 text-sm w-64">
      </div>
      <div>
        <label class="block text-xs font-semibold text-gray-500 mb-1">Type</label>
        <select name="workflow" class="border border-gray-300 rounded-lg px-3 py-2 text-sm">
          <option value="">All</option>
          @foreach ($def->workflows() as $k => $wf)
            <option value="{{ $k }}" @selected(request('workflow') === $k)>{{ $wf['label'] }}</option>
          @endforeach
        </select>
      </div>
      <div>
        <label class="block text-xs font-semibold text-gray-500 mb-1">Status</label>
        <select name="status" class="border border-gray-300 rounded-lg px-3 py-2 text-sm">
          <option value="">All</option>
          @foreach (['in_progress' => 'In progress', 'completed' => 'Completed', 'returned' => 'Returned'] as $k => $label)
            <option value="{{ $k }}" @selected(request('status') === $k)>{{ $label }}</option>
          @endforeach
        </select>
      </div>
      <button class="px-4 py-2 rounded-lg bg-gray-800 text-white text-sm font-semibold">Filter</button>
    </form>

    <div class="rounded-xl border border-gray-200 bg-white overflow-x-auto">
      <table class="min-w-full text-sm">
        <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500">
          <tr>
            <th class="px-4 py-2">S/N</th><th class="px-4 py-2">Ref</th><th class="px-4 py-2">File No.</th><th class="px-4 py-2">Title</th>
            <th class="px-4 py-2">Type</th><th class="px-4 py-2">Now at</th><th class="px-4 py-2">Received</th><th class="px-4 py-2">Status</th>
            <th class="px-4 py-2 text-right">Action</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
          @forelse ($cases ?? [] as $case)
            <tr class="hover:bg-gray-50">
              <td class="px-4 py-2 text-gray-500">{{ $cases->firstItem() + $loop->index }}</td>
              <td class="px-4 py-2 font-mono">{{ $case->case_ref }}</td>
              <td class="px-4 py-2 font-mono">{{ $case->file_number }}</td>
              <td class="px-4 py-2">{{ $case->file_title }}</td>
              <td class="px-4 py-2">{!! $def->wfChip($case->workflow) !!}</td>
              <td class="px-4 py-2">{{ $case->isOpen() ? $case->deskShort() : '—' }}</td>
              <td class="px-4 py-2 text-gray-500">{{ optional($case->received_at)->format('d M Y') }}</td>
              <td class="px-4 py-2">{!! $def->statusChip($case->status) !!}</td>
              <td class="px-4 py-2 text-right">@include('cadastral_dept.partials._row_actions', ['items' => $case->rowActions()])</td>
            </tr>
          @empty
            <tr><td colspan="9" class="px-4 py-6 text-center text-gray-400">No files.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
    @if ($cases)
      <div class="mt-4">{{ $cases->links() }}</div>
    @endif
  </div>
</div>
@endsection
