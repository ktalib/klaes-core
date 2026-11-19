{{-- OP details beside the FMC (user, 2026-11-19): one card per OP filed under the file. A merger file
     has two or more OPs and each gets its own card. Property details in the standard order:
     Plot No., District, LGA, Location. The allottee is the OP's original grantee. --}}
@php
  $cdOpCount = count($opDetails);
@endphp
<div class="space-y-3">
  <div class="flex items-center justify-between gap-2">
    <h3 class="text-sm font-bold uppercase tracking-wide text-gray-500 flex items-center gap-2">
      <i data-lucide="file-warning" class="w-4 h-4 text-indigo-500"></i> OP Details
    </h3>
    @if ($cdOpCount > 1)
      <span class="inline-flex items-center gap-1 rounded-full bg-yellow-100 px-2.5 py-0.5 text-xs font-semibold text-yellow-800">
        <i data-lucide="git-merge" class="w-3 h-3"></i> Merger · {{ $cdOpCount }} OPs
      </span>
    @endif
  </div>

  {{-- $cdOpGrid (case page): a merger file's OPs side by side. --}}
  <div class="{{ !empty($cdOpGrid) && $cdOpCount > 1 ? 'grid gap-3 sm:grid-cols-2' : 'space-y-3' }}">
  @foreach ($opDetails as $op)
    <div class="rounded-xl border border-indigo-200 bg-white p-5">
      <div class="flex items-start justify-between gap-2 mb-3">
        <div>
          <div class="text-xs uppercase text-gray-500">OP Serial No.</div>
          <div class="font-mono text-lg font-bold text-indigo-700">{{ $op['serial'] ?: '—' }}</div>
        </div>
        <div class="text-right">
          @if ($cdOpCount > 1)
            <div class="text-xs font-semibold text-gray-500">OP {{ $loop->iteration }} of {{ $cdOpCount }}</div>
          @endif
          @if ($op['type'])
            <span class="mt-1 inline-block rounded-md bg-indigo-50 px-2 py-0.5 text-xs font-semibold text-indigo-700">{{ $op['type'] }}</span>
          @endif
        </div>
      </div>
      <dl class="grid grid-cols-2 gap-x-4 gap-y-2 text-sm uppercase">
        <div class="col-span-2"><dt class="text-xs uppercase text-gray-500">Allottee</dt><dd class="font-semibold">{{ $op['allottee'] ?: '—' }}</dd></div>
        <div class="col-span-2"><dt class="text-xs uppercase text-gray-500">Registration Particulars</dt><dd class="font-mono">{{ $op['reg_no'] ?: '—' }}</dd></div>
        <div><dt class="text-xs uppercase text-gray-500">Plot No.</dt><dd>{{ $op['plot_no'] ?: '—' }}</dd></div>
        <div><dt class="text-xs uppercase text-gray-500">District</dt><dd>{{ $op['district'] ?: '—' }}</dd></div>
        <div><dt class="text-xs uppercase text-gray-500">LGA</dt><dd>{{ $op['lga'] ?: '—' }}</dd></div>
        <div><dt class="text-xs uppercase text-gray-500">Plot Size</dt><dd>{{ $op['plot_size'] ?: '—' }}</dd></div>
        <div class="col-span-2"><dt class="text-xs uppercase text-gray-500">Location</dt><dd>{{ $op['location'] ?: '—' }}</dd></div>
        <div><dt class="text-xs uppercase text-gray-500">Layout / TP No.</dt><dd class="font-mono">{{ $op['layout'] ?: '—' }}</dd></div>
        <div><dt class="text-xs uppercase text-gray-500">Land Use</dt><dd>{{ $op['land_use'] ?: '—' }}</dd></div>
      </dl>
    </div>
  @endforeach
  </div>
</div>
