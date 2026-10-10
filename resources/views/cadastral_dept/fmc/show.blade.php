@extends('layouts.app')

@section('content')
<div class="flex-1 overflow-auto bg-gray-50">
  @include('admin.header', ['PageTitle' => 'File Movement Card ' . $fmc->fmc_no, 'PageDescription' => $case->case_ref . ' · ' . $fmc->file_number])

  <div class="p-6 max-w-6xl mx-auto">
    @include('cadastral_dept.partials._nav')

    <div class="flex flex-wrap items-center gap-2 mb-6">
      <a href="{{ route('cadastral-dept.cases.show', $case->id) }}" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg border border-gray-300 bg-white text-sm font-semibold text-gray-700">
        <i data-lucide="arrow-left" class="w-4 h-4"></i> Back to {{ $case->case_ref }}
      </a>
      @canDo('Cad - Records', 'print')
        <a href="{{ route('cadastral-dept.cases.fmc.print', $case->id) }}" target="_blank" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-gray-800 text-white text-sm font-semibold">
          <i data-lucide="printer" class="w-4 h-4"></i> Print FMC
        </a>
      @endcanDo
      <span class="text-xs text-gray-500">Printed {{ $fmc->print_count }} time(s)</span>
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
      <div class="lg:col-span-2 space-y-6">
        <div class="rounded-xl border border-gray-200 bg-white p-5">
          @include('cadastral_dept.fmc._header')
        </div>
        <div>
          <h3 class="text-sm font-bold uppercase tracking-wide text-gray-500 mb-3">Movement log</h3>
          @include('cadastral_dept.fmc._timeline')
        </div>
      </div>
      <div class="space-y-6">
        <div class="rounded-xl border border-gray-200 bg-white p-5 text-center">
          @if ($qr)
            <img src="{{ $qr }}" alt="QR code for {{ $fmc->fmc_no }}" class="mx-auto" style="width: 160px; height: 160px;">
          @endif
          <div class="text-xs text-gray-500 mt-2">Units scan this to log the file in and out.</div>
          <div class="text-xs font-mono text-gray-400 break-all mt-1">{{ $scanUrl }}</div>
        </div>
        @include('cadastral_dept.fmc._log_form')
      </div>
    </div>
  </div>
</div>
@endsection
