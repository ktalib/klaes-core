<!-- Stats Cards -->
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
    <div class="bg-amber-50 p-4 rounded-lg shadow border-l-4 border-amber-500">
        <div class="text-sm font-medium text-amber-700 mb-1">Pending Registrations</div>
        <div class="text-2xl font-bold text-amber-800">{{ $pendingCount }}</div>
        <p class="text-xs text-amber-600 mt-1">Awaiting registration</p>
    </div>
    <div class="bg-green-50 p-4 rounded-lg shadow border-l-4 border-green-500">
        <div class="text-sm font-medium text-green-700 mb-1">Registered</div>
        <div class="text-2xl font-bold text-green-800">{{ $registeredCount }}</div>
        <p class="text-xs text-green-600 mt-1">Successfully registered</p>
    </div>
    <div class="bg-red-50 p-4 rounded-lg shadow border-l-4 border-red-500">
        <div class="text-sm font-medium text-red-700 mb-1">Rejected</div>
        <div class="text-2xl font-bold text-red-800">{{ $rejectedCount }}</div>
        <p class="text-xs text-red-600 mt-1">Rejected instruments</p>
    </div>
    <div class="bg-blue-50 p-4 rounded-lg shadow border-l-4 border-blue-500">
        <div class="text-sm font-medium text-blue-700 mb-1">Total Instruments</div>
        <div class="text-2xl font-bold text-blue-800">{{ $totalCount }}</div>
        <p class="text-xs text-blue-600 mt-1">All Registrations</p>
    </div>
</div>
