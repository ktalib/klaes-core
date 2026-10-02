{{-- Buyers List Tab --}}
@php
    $buyersListTabActive = ($activeTab ?? null) === 'buyers-list';
@endphp
<div id="buyers-list-tab" class="tab-content {{ $buyersListTabActive ? 'active' : '' }}">
    <div class="bg-white border border-gray-200 rounded-lg shadow-sm">
        <div class="p-4 border-b bg-indigo-50">
            <h3 class="text-lg font-medium text-indigo-900 flex items-center">
                <i data-lucide="users" class="w-5 h-5 mr-2"></i>
                Buyers List
            </h3>
            <p class="text-sm text-indigo-700 mt-1">Review the buyers captured for this application.</p>
        </div>

        <div class="p-6 space-y-6">
            @php
                $buyersFetchError = null;

                try {
                    $buyersCollection = DB::connection('sqlsrv')
                        ->table('buyer_list as bl')
                        ->leftJoin('st_unit_measurements as sum', function ($join) {
                            $join->on('bl.id', '=', 'sum.buyer_id')
                                 ->on('bl.application_id', '=', 'sum.application_id');
                        })
                        ->where('bl.application_id', $application->id)
                        ->select(
                            'bl.id',
                            'bl.buyer_title',
                            'bl.buyer_name',
                            'bl.unit_no',
                            'sum.measurement'
                        )
                        ->orderBy('bl.id')
                        ->distinct()
                        ->get();
                } catch (\Throwable $exception) {
                    $buyersCollection = collect();
                    $buyersFetchError = config('app.debug') ? $exception->getMessage() : null;
                }

                $buyersCollection = $buyersCollection instanceof \Illuminate\Support\Collection
                    ? $buyersCollection
                    : collect($buyersCollection);

                $totalBuyers = $buyersCollection->count();
                $totalMeasurement = $buyersCollection->reduce(function ($carry, $buyer) {
                    $value = $buyer->measurement ?? 0;
                    return $carry + (is_numeric($value) ? (float) $value : 0);
                }, 0.0);
            @endphp

            @if($buyersFetchError)
                <div class="flex items-start gap-3 p-4 rounded-lg border border-red-200 bg-red-50 text-sm text-red-700">
                    <i data-lucide="alert-triangle" class="w-4 h-4 mt-0.5"></i>
                    <div>
                        <p class="font-semibold">We couldn't load the buyers list.</p>
                        @if($buyersFetchError)
                            <p class="text-xs mt-1">{{ $buyersFetchError }}</p>
                        @endif
                    </div>
                </div>
            @endif

            @if($buyersCollection->isNotEmpty())
                <div class="overflow-x-auto border border-indigo-200 rounded-lg shadow-sm">
                    <table class="min-w-full divide-y divide-indigo-200">
                        <thead class="bg-indigo-600">
                            <tr>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-semibold text-black   uppercase tracking-wider border-r border-indigo-500">SN</th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-semibold   uppercase tracking-wider border-r border-indigo-500">Buyer Name</th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-semibold text-black uppercase tracking-wider border-r border-indigo-500">Unit No.</th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-semibold text-black uppercase tracking-wider">Measurement (sqm)</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-indigo-100">
                            @foreach($buyersCollection as $index => $buyer)
                                <tr class="hover:bg-indigo-50 transition-colors duration-200 {{ $index % 2 === 0 ? 'bg-indigo-50' : 'bg-white' }}">
                                    <td class="px-4 py-3 whitespace-nowrap text-sm font-semibold text-indigo-900 border-r border-indigo-100">{{ $index + 1 }}</td>
                                    <td class="px-4 uppercase py-3 whitespace-nowrap text-sm font-semibold text-gray-900 border-r border-indigo-100">
                                        {{ trim(($buyer->buyer_title ?? '') . ' ' . ($buyer->buyer_name ?? '')) ?: '—' }}
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-sm font-medium text-indigo-800 border-r border-indigo-100">{{ $buyer->unit_no ?? '—' }}</td>
                                    <td class="px-4 py-3 whitespace-nowrap text-sm font-medium text-gray-700">{{ $buyer->measurement ?? 'N/A' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="p-4 rounded-lg border border-indigo-200 bg-indigo-50 flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <i data-lucide="users" class="w-5 h-5 text-indigo-600"></i>
                            <span class="text-sm font-semibold text-indigo-800">Total Buyers</span>
                        </div>
                        <span class="text-2xl font-bold text-indigo-900">{{ $totalBuyers }}</span>
                    </div>
                    <div class="p-4 rounded-lg border border-emerald-200 bg-emerald-50 flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <i data-lucide="ruler" class="w-5 h-5 text-emerald-600"></i>
                            <span class="text-sm font-semibold text-emerald-700">Total Measured Area</span>
                        </div>
                        <span class="text-lg font-bold text-emerald-700">{{ number_format($totalMeasurement, 2) }} sqm</span>
                    </div>
                </div>
            @else
                <div class="text-center p-10 border-2 border-dashed border-indigo-200 bg-indigo-50 rounded-lg">
                    <i data-lucide="users-x" class="w-12 h-12 text-indigo-400 mx-auto mb-4"></i>
                    <p class="text-base font-semibold text-indigo-800">No buyers have been captured yet</p>
                    <p class="text-sm text-indigo-600 mt-1">Buyers added in the sectional titling workflow will appear here automatically.</p>
                    <div class="mt-4">
                        <a href="{{ route('actions.buyers_list', $application->id) }}" class="inline-flex items-center px-4 py-2 text-sm font-medium bg-indigo-600 text-white rounded-md hover:bg-indigo-700 hidden">
                            <i data-lucide="external-link" class="w-4 h-4 mr-2"></i>
                            Manage Buyers
                        </a>
                    </div>
                </div>
            @endif

            <div class="flex justify-end hidden">
                <a href="{{ route('actions.buyers_list', $application->id) }}" target="_blank" rel="noopener" class="inline-flex items-center px-3 py-2 text-xs font-semibold bg-indigo-600 text-white rounded-md hover:bg-indigo-700">
                    <i data-lucide="arrow-up-right" class="w-4 h-4 mr-1.5"></i>
                    Open Buyers Workspace
                </a>
            </div>
        </div>
    </div>
</div>
