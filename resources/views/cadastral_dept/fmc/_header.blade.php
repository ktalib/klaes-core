{{-- FMC header fields. Property details in the standard order: Plot No., District, LGA, Location. --}}
<dl class="grid gap-x-6 gap-y-2 text-sm sm:grid-cols-2">
  <div><dt class="text-xs uppercase text-gray-500">FMC No.</dt><dd class="font-mono font-semibold">{{ $fmc->fmc_no }}</dd></div>
  <div><dt class="text-xs uppercase text-gray-500">File No.</dt><dd class="font-mono font-semibold">{{ $fmc->file_number }}</dd></div>
  <div class="sm:col-span-2"><dt class="text-xs uppercase text-gray-500">File Title</dt><dd>{{ $fmc->file_title ?: '—' }}</dd></div>
  @if ($fmc->allottee_name && $fmc->allottee_name !== $fmc->file_title)
    <div class="sm:col-span-2"><dt class="text-xs uppercase text-gray-500">Allottee</dt><dd>{{ $fmc->allottee_name }}</dd></div>
  @endif
  <div><dt class="text-xs uppercase text-gray-500">Plot No.</dt><dd>{{ $fmc->plot_no ?: '—' }}</dd></div>
  <div><dt class="text-xs uppercase text-gray-500">District</dt><dd>{{ $fmc->district ?: '—' }}</dd></div>
  <div><dt class="text-xs uppercase text-gray-500">LGA</dt><dd>{{ $fmc->lga ?: '—' }}</dd></div>
  <div><dt class="text-xs uppercase text-gray-500">Location</dt><dd>{{ $fmc->location ?: '—' }}</dd></div>
  <div><dt class="text-xs uppercase text-gray-500">Block No.</dt><dd>{{ $fmc->block_no ?: '—' }}</dd></div>
  <div><dt class="text-xs uppercase text-gray-500">Layout No.</dt><dd>{{ $fmc->layout_no ?: '—' }}</dd></div>
  <div class="sm:col-span-2"><dt class="text-xs uppercase text-gray-500">Commissioned</dt>
    <dd>{{ $fmc->commissioned_at->format('d M Y H:i') }} · {{ optional($fmc->commissioner)->name }}</dd></div>
</dl>
