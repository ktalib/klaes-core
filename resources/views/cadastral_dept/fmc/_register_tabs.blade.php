{{-- FMC list tabs (CR 11112026-2, item 13), included by desk.blade.php on AD Cad Info > File Movement Card.
     Awaiting FMC = the list as it was. Waiting on Charting = files whose FMC waits for the steps before it
     (view only). All FMCs = every card opened, wherever the file is now. --}}
<div class="flex flex-wrap items-center gap-2 mb-4" role="tablist">
  @foreach (['queue' => ['Awaiting FMC', 'clock'], 'waiting' => ['Waiting on Charting', 'route'], 'all' => ['All FMCs', 'id-card']] as $cdTabKey => [$cdTabLabel, $cdTabIcon])
    <a href="{{ route('cadastral-dept.desks.show', array_filter(['desk' => $desk, 'queue' => $queueKey, 'tab' => $cdTabKey === 'queue' ? null : $cdTabKey, 'q' => $term, 'workflow' => $filterWorkflow])) }}"
       role="tab" aria-selected="{{ $fmcTab === $cdTabKey ? 'true' : 'false' }}"
       class="inline-flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-semibold border {{ $fmcTab === $cdTabKey ? 'bg-blue-600 border-blue-600 text-white' : 'bg-white border-gray-300 text-gray-700 hover:bg-gray-50' }}">
      <i data-lucide="{{ $cdTabIcon }}" class="w-4 h-4"></i> {{ $cdTabLabel }}
      <span class="px-2 py-0.5 rounded-full text-xs {{ $fmcTab === $cdTabKey ? 'bg-white bg-opacity-25 text-white' : 'bg-gray-100 text-gray-600' }}">{{ number_format($tabCounts[$cdTabKey] ?? 0) }}</span>
    </a>
  @endforeach
</div>
