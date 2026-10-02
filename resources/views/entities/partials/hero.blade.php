@php
    $totalEntities = number_format(data_get($stats, 'total_entities', 0));
    $linkedCustomers = number_format(data_get($stats, 'entities_with_customers', 0));
    $totalCustomers = number_format(data_get($stats, 'total_customers', 0));
    $orphanedCustomers = number_format(data_get($stats, 'orphaned_customers', 0));
    $byType = data_get($stats, 'by_type', []);
@endphp

<section class="rounded-lg border border-gray-200 bg-gradient-to-br from-blue-50 to-indigo-50 px-6 py-6 shadow-sm">
    <div class="flex flex-col justify-between gap-4 md:flex-row md:items-center">
        <div class="space-y-2">
            <p class="text-xs font-medium uppercase tracking-wide text-blue-600">Entities Management</p>
            <h1 class="text-3xl font-bold text-gray-900">Entity Management</h1>
            <p class="max-w-2xl text-sm text-gray-700">
                Manage unique owners (individuals, corporations, multiple owners) linked to customer records. 
                Get insights with comprehensive statistics and maintain data integrity.
            </p>
        </div>

        <div class="flex flex-wrap items-center gap-3 md:flex-nowrap">
            <a href="#entity-filters" class="inline-flex items-center gap-2 rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50 whitespace-nowrap">
            <i data-lucide="filter" class="h-4 w-4"></i>
            Filters
            </a>
            <a href="{{ route('customers.index') }}" class="inline-flex items-center gap-2 rounded-md bg-blue-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-blue-700 whitespace-nowrap">
            <i data-lucide="users" class="h-4 w-4"></i>
            View All Customers
            </a>
        </div>
    </div>

    <dl class="mt-6 grid gap-4 sm:grid-cols-4">
        <div class="rounded-lg border border-blue-200 bg-blue-100/50 px-4 py-3 backdrop-blur-sm">
            <dt class="text-xs font-semibold uppercase tracking-wide text-blue-900">Total Entities</dt>
            <dd class="mt-2 text-2xl font-bold text-blue-900">{{ $totalEntities }}</dd>
            <p class="text-xs text-blue-700 mt-1">Registered owners</p>
        </div>
        {{-- <div class="rounded-lg border border-purple-200 bg-purple-100/50 px-4 py-3 backdrop-blur-sm">
            <dt class="text-xs font-semibold uppercase tracking-wide text-purple-900">With Customers</dt>
            <dd class="mt-2 text-2xl font-bold text-purple-900">{{ $linkedCustomers }}</dd>
            <p class="text-xs text-purple-700 mt-1">Active links</p>
        </div> --}}
        {{-- <div class="rounded-lg border border-green-200 bg-green-100/50 px-4 py-3 backdrop-blur-sm">
            <dt class="text-xs font-semibold uppercase tracking-wide text-green-900">Total Customers</dt>
            <dd class="mt-2 text-2xl font-bold text-green-900">{{ $totalCustomers }}</dd>
            <p class="text-xs text-green-700 mt-1">Linked records</p>
        </div> --}}
        <div class="rounded-lg border border-amber-200 bg-amber-100/50 px-4 py-3 backdrop-blur-sm">
            <dt class="text-xs font-semibold uppercase tracking-wide text-amber-900">Orphaned</dt>
            <dd class="mt-2 text-2xl font-bold text-amber-900">{{ $orphanedCustomers }}</dd>
            <p class="text-xs text-amber-700 mt-1">Review needed</p>
        </div>
    </dl>
</section>
