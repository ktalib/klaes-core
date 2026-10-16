@extends('layouts.app')

@section('content')
<div class="flex-1 overflow-auto bg-gray-50">
  @include('admin.header', ['PageTitle' => 'Field Jackets', 'PageDescription' => 'Field jackets, their survey jobs, surveyors and I to S'])

  <div class="p-6">
    @include('cadastral_dept.partials._nav')

    @include('cadastral_dept.partials._stat_cards')

    <form method="GET" class="flex flex-wrap items-center gap-2 mb-4">
      <select name="status" class="border border-gray-300 rounded-lg px-3 py-2 text-sm">
        <option value="">Every status</option>
        @foreach (\App\Models\Cadastral\CadastralSurveyJob::STATUSES as $s)
          <option value="{{ $s }}" @selected($status === $s)>{{ $s === 'Draft' ? 'I to S not issued' : $s }}</option>
        @endforeach
      </select>
      <input type="text" name="q" value="{{ $term }}" placeholder="Jacket, job, I to S, file no. or surveyor" class="border border-gray-300 rounded-lg px-3 py-2 text-sm w-72">
      <button class="px-3 py-2 rounded-lg bg-gray-800 text-white text-sm font-semibold">Search</button>
      @if ($term !== '' || $status)
        <a href="{{ route('cadastral-dept.field-jackets.index') }}" class="px-3 py-2 text-sm text-gray-600">Clear</a>
      @endif
      <a href="{{ route('cadastral-module.surveyors.index') }}" class="ml-auto inline-flex items-center gap-2 px-3 py-2 rounded-lg border border-gray-300 bg-white text-sm font-semibold text-gray-700">
        <i data-lucide="users" class="w-4 h-4"></i> Surveyors directory
      </a>
    </form>

    @if (! $installed)
      <div class="rounded-lg border border-yellow-300 bg-yellow-50 px-4 py-3 text-sm text-yellow-900">The field jacket tables have not been created yet.</div>
    @else
      <div class="rounded-xl border border-gray-200 bg-white overflow-x-auto">
        <table class="min-w-full text-sm">
          <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500">
            <tr>
              <th class="px-3 py-2">S/N</th><th class="px-3 py-2">Field Jacket</th><th class="px-3 py-2">Job No.</th><th class="px-3 py-2">File No.</th>
              <th class="px-3 py-2">Plot No.</th><th class="px-3 py-2">District</th><th class="px-3 py-2">LGA</th><th class="px-3 py-2">Location</th>
              <th class="px-3 py-2">Surveyor</th><th class="px-3 py-2">I to S</th><th class="px-3 py-2">Status</th><th class="px-3 py-2">Commissioned</th>
              <th class="px-3 py-2 text-right">Action</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100">
            @forelse ($rows as $jacket)
              @php
                $items = [['Open field jacket', 'briefcase', route('cadastral-dept.field-jackets.show', $jacket->id)]];
                if (\App\Services\CadastralDept\DeskAccess::can(auth()->user(), 'print')) {
                    $items[] = ['Print jacket cover', 'printer', route('cadastral-dept.field-jackets.print', $jacket->id), true];
                    if ($jacket->job?->its_number) {
                        $items[] = ['Print I to S', 'file-text', route('cadastral-dept.field-jackets.its.print', $jacket->id), true];
                    }
                }
                $items[] = ['View', 'eye', route('cadastral-dept.cases.show', $jacket->cadastral_case_id)];
              @endphp
              <tr class="hover:bg-gray-50">
                <td class="px-3 py-2 text-gray-500">{{ $rows->firstItem() + $loop->index }}</td>
                <td class="px-3 py-2 font-mono">{{ $jacket->field_jacket_no }}</td>
                <td class="px-3 py-2 font-mono">{{ $jacket->job?->job_number }}</td>
                <td class="px-3 py-2 font-mono">{{ $jacket->file_number }}</td>
                <td class="px-3 py-2">{{ $jacket->plot_no ?: '—' }}</td>
                <td class="px-3 py-2">{{ $jacket->district ?: '—' }}</td>
                <td class="px-3 py-2">{{ $jacket->lga ?: '—' }}</td>
                <td class="px-3 py-2">{{ $jacket->location ?: '—' }}</td>
                <td class="px-3 py-2">{{ $jacket->job?->surveyor_name ?: '—' }}@if ($jacket->job?->firm_name)<div class="text-xs text-gray-500">{{ $jacket->job?->firm_name }}</div>@endif</td>
                <td class="px-3 py-2 font-mono">{{ $jacket->job?->its_number ?: '—' }}</td>
                <td class="px-3 py-2">@include('cadastral_dept.field_jackets._status', ['cdStatus' => $jacket->job?->status ?? 'No job yet'])</td>
                <td class="px-3 py-2 whitespace-nowrap">{{ $jacket->commissioned_at->format('d M Y') }}</td>
                <td class="px-3 py-2 text-right">@include('cadastral_dept.partials._row_actions', ['items' => $items])</td>
              </tr>
            @empty
              <tr><td colspan="13" class="px-3 py-6 text-center text-gray-400">No field jackets yet. AD P&amp;D commissions one from the file's page.</td></tr>
            @endforelse
          </tbody>
        </table>
      </div>
      <div class="mt-4">{{ $rows->links() }}</div>
    @endif
  </div>
  @include('admin.footer')
</div>
@endsection
