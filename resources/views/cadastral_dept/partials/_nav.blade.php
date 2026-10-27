{{-- Module tabs, flash messages and the not-installed notice. The main sidebar links the desks only. --}}
@php
  $cdTabs = [
      'cadastral-dept.index' => ['Dashboard', 'gauge'],
      'cadastral-dept.cases.index' => ['All Files', 'files'],
      'cadastral-dept.cases.create' => ['Register File', 'file-plus'],
      'cadastral-dept.charting.index' => ['Charting', 'map'],
      'cadastral-dept.field-jackets.index' => ['Computation Files (Field Jackets)', 'briefcase'],
      'cadastral-dept.bills.index' => ['P&D Bills', 'receipt'],
      // Workflow Map hidden for now (user, 2026-10-11); the page still opens by URL.
  ];
@endphp
{{-- Header shortcuts hidden for now (user, 2026-10-12); the sidebar desks are the way in. Change false to true to show them again. --}}
@if (false)
<div class="flex flex-wrap items-center gap-2 mb-6">
  @foreach ($cdTabs as $cdRoute => [$cdLabel, $cdIcon])
    <a href="{{ route($cdRoute) }}"
       class="inline-flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-semibold border {{ request()->routeIs($cdRoute) ? 'bg-green-700 text-white border-green-700' : 'bg-white text-gray-700 border-gray-200 hover:bg-gray-50' }}">
      <i data-lucide="{{ $cdIcon }}" class="w-4 h-4"></i> {{ $cdLabel }}
    </a>
  @endforeach
</div>
@endif

@include('cadastral_dept.partials._confirm')

@if (session('success'))
  <div class="mb-4 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">{{ session('success') }}</div>
@endif
@if (session('error'))
  <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ session('error') }}</div>
@endif
@if ($errors->any())
  <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
    @foreach ($errors->all() as $cdError)<div>{{ $cdError }}</div>@endforeach
  </div>
@endif

@if (isset($installed) && ! $installed)
  <div class="mb-6 rounded-lg border border-yellow-300 bg-yellow-50 px-4 py-3 text-sm text-yellow-900">
    <strong>Not installed yet.</strong> The Cadastral Department tables have not been created, so no files can be received.
    The workflow map works without them.
  </div>
@endif
