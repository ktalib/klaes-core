<!-- Stats Cards -->
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
    <div class="bg-blue-50 p-4 rounded-lg shadow border-l-4 border-blue-500">
        <div class="text-sm font-medium text-blue-700 mb-1">Total Instruments</div>
        <div class="text-2xl font-bold text-blue-800">{{ $totalCount }}</div>
        <p class="text-xs text-blue-600 mt-1">All Registrations</p>
    </div>
    <div class="bg-green-50 p-4 rounded-lg shadow border-l-4 border-green-500">
        <div class="text-sm font-medium text-green-700 mb-1">Registered</div>
        <div class="text-2xl font-bold text-green-800">{{ $registeredCount }}</div>
        @if($isStDeeds ?? false)
            {{-- ST Deeds: registered instruments broken down by application type --}}
            <div class="flex flex-wrap items-center gap-x-3 gap-y-0.5 mt-1 text-xs text-green-700">
                <span><span class="font-semibold">Primary</span> {{ $registeredByAppType['Primary'] ?? 0 }}</span>
                <span class="text-green-300">|</span>
                <span><span class="font-semibold">PUA</span> {{ $registeredByAppType['PUA'] ?? 0 }}</span>
                <span class="text-green-300">|</span>
                <span><span class="font-semibold">SUA</span> {{ $registeredByAppType['SUA'] ?? 0 }}</span>
            </div>
        @else
            <p class="text-xs text-green-600 mt-1">Successfully registered</p>
        @endif
    </div>
    <div class="bg-amber-50 p-4 rounded-lg shadow border-l-4 border-amber-500">
        <div class="text-sm font-medium text-amber-700 mb-1">Pending Registrations</div>
        <div class="text-2xl font-bold text-amber-800">{{ $pendingCount }}</div>
        @if($isStDeeds ?? false)
            {{-- ST Deeds: pending instruments broken down by application type --}}
            <div class="flex flex-wrap items-center gap-x-3 gap-y-0.5 mt-1 text-xs text-amber-700">
                <span><span class="font-semibold">Primary</span> {{ $pendingByAppType['Primary'] ?? 0 }}</span>
                <span class="text-amber-300">|</span>
                <span><span class="font-semibold">PUA</span> {{ $pendingByAppType['PUA'] ?? 0 }}</span>
                <span class="text-amber-300">|</span>
                <span><span class="font-semibold">SUA</span> {{ $pendingByAppType['SUA'] ?? 0 }}</span>
            </div>
            {{-- ST Deeds: pending instruments broken down by ST instrument type --}}
            <div class="flex flex-wrap items-center gap-x-3 gap-y-0.5 mt-1 text-xs text-amber-700">
                <span><span class="font-semibold">Assignment</span> {{ $pendingByInstrumentType['Assignment'] ?? 0 }}</span>
                <span class="text-amber-300">|</span>
                <span><span class="font-semibold">Fragmentation</span> {{ $pendingByInstrumentType['Fragmentation'] ?? 0 }}</span>
                <span class="text-amber-300">|</span>
                <span><span class="font-semibold">CofO</span> {{ $pendingByInstrumentType['CofO'] ?? 0 }}</span>
            </div>
        @else
            <p class="text-xs text-amber-600 mt-1">Awaiting registration</p>
        @endif
    </div>
    <div class="bg-red-50 p-4 rounded-lg shadow border-l-4 border-red-500">
        <div class="text-sm font-medium text-red-700 mb-1">Rejected</div>
        <div class="text-2xl font-bold text-red-800">{{ $rejectedCount }}</div>
        <p class="text-xs text-red-600 mt-1">Rejected instruments</p>
    </div>
</div>