@extends('layouts.app')

@section('content')
<div class="flex-1 overflow-auto bg-gray-50">
  @include('admin.header', ['PageTitle' => 'File Movement Card', 'PageDescription' => 'Log the file in or out of a unit'])

  <div class="p-4 max-w-xl mx-auto space-y-4">
    @if (session('error'))
      <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ session('error') }}</div>
    @endif

    <div class="rounded-xl border border-gray-200 bg-white p-4">
      @include('cadastral_dept.fmc._header')
      @if ($case && $case->isOpen())
        <div class="mt-3 text-xs text-gray-500">In the workflow: {{ $case->deskShort() }}, {{ $case->stepLabel() }}</div>
      @endif
    </div>

    @include('cadastral_dept.fmc._log_form')
    @include('cadastral_dept.partials._confirm')

    <details class="rounded-xl border border-gray-200 bg-white p-4">
      <summary class="text-sm font-semibold text-gray-700 cursor-pointer">Movement log ({{ count($timeline) }})</summary>
      <div class="mt-3">@include('cadastral_dept.fmc._timeline')</div>
    </details>
  </div>
  @include('admin.footer')
</div>
@endsection
