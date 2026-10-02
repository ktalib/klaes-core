<div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
    <!-- Total Page Typed Files -->
    <div class="card bg-blue-50 border-blue-200">
        <div class="p-4 pb-2">
            <h3 class="text-sm font-medium text-blue-700">Total Page Typed Files</h3>
        </div>
        <div class="p-4 pt-0">
            <div class="text-2xl font-bold text-blue-900">{{ number_format($stats['total_archived'] ?? 0) }}</div>
            <p class="text-xs text-blue-600 mt-1">Completed page typed files</p>
        </div>
    </div>

    <!-- Recently Added -->
    <div class="card bg-green-50 border-green-200">
        <div class="p-4 pb-2">
            <h3 class="text-sm font-medium text-green-700">Recently Added</h3>
        </div>
        <div class="p-4 pt-0">
            <div class="text-2xl font-bold text-green-900">{{ number_format($stats['recently_added'] ?? 0) }}</div>
            <p class="text-xs text-green-600 mt-1">Added in the last 30 days</p>
        </div>
    </div>

    <!-- Total Pages -->
    <div class="card bg-yellow-50 border-yellow-200">
        <div class="p-4 pb-2">
            <h3 class="text-sm font-medium text-yellow-700">Total Pages</h3>
        </div>
        <div class="p-4 pt-0">
            <div class="text-2xl font-bold text-yellow-900">{{ number_format($stats['total_pages'] ?? 0) }}</div>
            <p class="text-xs text-yellow-600 mt-1">Digitally classified pages</p>
        </div>
    </div>

    <!-- Storage Used -->
    <div class="card bg-purple-50 border-purple-200">
        <div class="p-4 pb-2">
            <h3 class="text-sm font-medium text-purple-700">Storage Used</h3>
        </div>
        <div class="p-4 pt-0">
            <div class="text-2xl font-bold text-purple-900">{{ $stats['storage_used'] }}</div>
            <p class="text-xs text-purple-600 mt-1">Of archived documents</p>
        </div>
    </div>
</div>
