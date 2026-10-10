@extends('layouts.app')

@section('content')
<div class="flex-1 overflow-auto bg-gray-50">
  @include('admin.header', ['PageTitle' => 'Charting', 'PageDescription' => 'Every charting card, by charting number'])

  <div class="p-6 max-w-7xl mx-auto">
    @include('cadastral_dept.partials._nav')

    <div class="flex flex-wrap items-center gap-2 mb-4">
      @foreach (\App\Http\Controllers\CadastralDept\ChartingController::TABLES as $key => [$label])
        <a href="{{ route('cadastral-dept.charting.index', ['table' => $key]) }}"
           class="px-4 py-2 rounded-lg text-sm font-semibold border {{ $table === $key ? 'bg-gray-800 text-white border-gray-800' : 'bg-white text-gray-700 border-gray-200 hover:bg-gray-50' }}">{{ $label }}</a>
      @endforeach
      <form method="GET" class="ml-auto flex gap-2">
        <input type="hidden" name="table" value="{{ $table }}">
        <input type="text" name="q" value="{{ $term }}" placeholder="Charting no. or file no." class="border border-gray-300 rounded-lg px-3 py-2 text-sm w-56">
        <button class="px-3 py-2 rounded-lg bg-gray-800 text-white text-sm font-semibold">Search</button>
      </form>
    </div>

    @if (! $installed)
      <div class="rounded-lg border border-yellow-300 bg-yellow-50 px-4 py-3 text-sm text-yellow-900">The charting tables have not been created yet.</div>
    @else
      <div class="rounded-xl border border-gray-200 bg-white overflow-x-auto">
        <table class="min-w-full text-sm">
          <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500">
            <tr>
              <th class="px-3 py-2">S/N</th><th class="px-3 py-2">Charting No.</th><th class="px-3 py-2">File No.</th>
              <th class="px-3 py-2">Plot No.</th><th class="px-3 py-2">District</th><th class="px-3 py-2">LGA</th><th class="px-3 py-2">Location</th>
              <th class="px-3 py-2">Stage</th><th class="px-3 py-2">{{ $table === 'checks' ? 'Matches' : 'Charted' }}</th>
              @unless ($table === 'checks')<th class="px-3 py-2">On existing plot</th>@endunless
              <th class="px-3 py-2">By</th><th class="px-3 py-2">Date</th><th class="px-3 py-2 text-right">Action</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100">
            @php
              $pill = fn ($v, $goodWhenTrue = true) => $v === null ? '<span class="text-gray-400">—</span>'
                  : '<span class="px-2 py-0.5 rounded-full text-xs font-semibold ' . (($v xor ! $goodWhenTrue) ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800') . '">' . ($v ? 'Yes' : 'No') . '</span>';
            @endphp
            @forelse ($rows as $card)
              @php
                $items = [['Open card', 'id-card', route('cadastral-dept.charting.show', $card->id)]];
                if (auth()->user()->canDo('Cad - Records', 'print')) {
                    $items[] = ['Print card', 'printer', route('cadastral-dept.charting.print', $card->id), true];
                }
                $items[] = ['Open file', 'folder-open', route('cadastral-dept.cases.show', $card->cadastral_case_id)];
              @endphp
              <tr class="hover:bg-gray-50">
                <td class="px-3 py-2 text-gray-500">{{ $rows->firstItem() + $loop->index }}</td>
                <td class="px-3 py-2 font-mono">{{ $card->charting_no }}@if ($card->version > 1)<span class="text-xs text-gray-500"> v{{ $card->version }}</span>@endif</td>
                <td class="px-3 py-2 font-mono">{{ $card->case->file_number }}</td>
                <td class="px-3 py-2">{{ $card->case->plot_no ?: '—' }}</td>
                <td class="px-3 py-2">{{ $card->case->district ?: '—' }}</td>
                <td class="px-3 py-2">{{ $card->case->lga ?: '—' }}</td>
                <td class="px-3 py-2">{{ $card->case->location ?: '—' }}</td>
                <td class="px-3 py-2 whitespace-nowrap">{!! $def->wfChip($card->case->workflow) !!} <span class="text-xs text-gray-500">{{ $card->stageLabel() }}</span></td>
                <td class="px-3 py-2">{!! $table === 'checks' ? $pill($card->matches_record) : $pill($card->charted) !!}</td>
                @unless ($table === 'checks')
                  <td class="px-3 py-2">{!! $pill($card->falls_on_existing, false) !!}</td>
                @endunless
                <td class="px-3 py-2">{{ optional($card->officer)->name }}</td>
                <td class="px-3 py-2 whitespace-nowrap">{{ $card->charted_at->format('d M Y') }}</td>
                <td class="px-3 py-2 text-right">@include('cadastral_dept.partials._row_actions', ['items' => $items])</td>
              </tr>
            @empty
              <tr><td colspan="13" class="px-3 py-6 text-center text-gray-400">No charting cards yet.</td></tr>
            @endforelse
          </tbody>
        </table>
      </div>
      <div class="mt-4">{{ $rows->links() }}</div>
    @endif
  </div>
</div>
@endsection
