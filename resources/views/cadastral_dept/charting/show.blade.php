@extends('layouts.app')

@section('content')
<div class="flex-1 overflow-auto bg-gray-50">
  @include('admin.header', ['PageTitle' => 'Charting Card ' . $card->charting_no, 'PageDescription' => $case->case_ref . ' · ' . $card->stageLabel() . ' · version ' . $card->version])

  <div class="p-6 space-y-6">
    @include('cadastral_dept.partials._nav')

    <div class="flex flex-wrap items-center gap-2">
      <a href="{{ route('cadastral-dept.cases.show', $case->id) }}" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg border border-gray-300 bg-white text-sm font-semibold text-gray-700">
        <i data-lucide="arrow-left" class="w-4 h-4"></i> Back to {{ $case->case_ref }}
      </a>
      @canDo('Cad - Records', 'print')
        <a href="{{ route('cadastral-dept.charting.print', $card->id) }}" target="_blank" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-gray-800 text-white text-sm font-semibold">
          <i data-lucide="printer" class="w-4 h-4"></i> Print card
        </a>
      @endcanDo
      @unless ($card->is_current)
        <span class="px-3 py-1 rounded-full text-xs font-semibold bg-gray-200 text-gray-700">Replaced by a later version</span>
      @endunless
    </div>

    @if ($card->flags())
      <div class="rounded-lg border border-yellow-300 bg-yellow-50 px-4 py-3 text-sm text-yellow-900 space-y-1">
        @foreach ($card->flags() as $flag)<div>{{ $flag }}</div>@endforeach
      </div>
    @endif

    <div class="rounded-xl border border-gray-200 bg-white p-5">
      @include('cadastral_dept.charting._card')
    </div>

    @if ($card->beacons->isNotEmpty())
      <div class="rounded-xl border border-gray-200 bg-white overflow-x-auto">
        <table class="min-w-full text-sm">
          <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500">
            <tr><th class="px-4 py-2">S/N</th><th class="px-4 py-2">Beacon</th><th class="px-4 py-2">Easting</th><th class="px-4 py-2">Northing</th><th class="px-4 py-2">Remarks</th></tr>
          </thead>
          <tbody class="divide-y divide-gray-100">
            @foreach ($card->beacons as $b)
              <tr>
                <td class="px-4 py-2 text-gray-500">{{ $loop->iteration }}</td><td class="px-4 py-2">{{ $b->beacon }}</td>
                <td class="px-4 py-2 font-mono">{{ number_format($b->easting, 3) }}</td><td class="px-4 py-2 font-mono">{{ number_format($b->northing, 3) }}</td>
                <td class="px-4 py-2">{{ $b->remarks }}</td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    @endif

    @if ($versions->count() > 1)
      <div>
        <h3 class="text-sm font-bold uppercase tracking-wide text-gray-500 mb-2">Versions</h3>
        <div class="rounded-xl border border-gray-200 bg-white overflow-x-auto">
          <table class="min-w-full text-sm">
            <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500"><tr><th class="px-4 py-2">S/N</th><th class="px-4 py-2">Version</th><th class="px-4 py-2">By</th><th class="px-4 py-2">Date</th><th class="px-4 py-2 text-right">Action</th></tr></thead>
            <tbody class="divide-y divide-gray-100">
              @foreach ($versions as $v)
                <tr class="{{ $v->id === $card->id ? 'bg-blue-50' : '' }}">
                  <td class="px-4 py-2 text-gray-500">{{ $loop->iteration }}</td>
                  <td class="px-4 py-2">v{{ $v->version }} {{ $v->is_current ? '(current)' : '' }}</td>
                  <td class="px-4 py-2">{{ optional($v->officer)->name }}</td>
                  <td class="px-4 py-2">{{ $v->charted_at->format('d M Y H:i') }}</td>
                  <td class="px-4 py-2 text-right">@include('cadastral_dept.partials._row_actions', ['items' => [['Open version', 'eye', route('cadastral-dept.charting.show', $v->id)]]])</td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      </div>
    @endif
  </div>
  @include('admin.footer')
</div>
@endsection
