@php
    $byType = data_get($stats, 'by_type', []);
    $totalEntities = data_get($stats, 'total_entities', 0);
    $entitiesWithCustomers = data_get($stats, 'entities_with_customers', 0);
    $totalCustomers = data_get($stats, 'total_customers', 0);
    $orphanedCustomers = max(data_get($stats, 'orphaned_customers', 0), 0);
@endphp

<section aria-label="Entity breakdown">
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
        <!-- Total Entities Card -->
        <article class="rounded-lg border border-blue-200 bg-blue-50 px-4 py-5 shadow-sm">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-medium uppercase tracking-wide text-blue-700">Total Entities</p>
                    <p class="mt-2 text-3xl font-bold text-blue-900">{{ number_format($totalEntities) }}</p>
                    <p class="text-xs text-blue-600 mt-1">Unique owners</p>
                </div>
                <i data-lucide="building" class="h-8 w-8 text-blue-300"></i>
            </div>
        </article>

        <!-- Individuals Card -->
        <article class="rounded-lg border border-purple-200 bg-purple-50 px-4 py-5 shadow-sm">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-medium uppercase tracking-wide text-purple-700">Individuals</p>
                    <p class="mt-2 text-3xl font-bold text-purple-900">
                        {{ number_format(data_get($byType, 'Individual', 0)) }}</p>
                    <p class="text-xs text-purple-600 mt-1">Personal profiles</p>
                </div>
                <i data-lucide="user" class="h-8 w-8 text-purple-300"></i>
            </div>
        </article>

        <!-- Corporate Card -->
        <article class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-5 shadow-sm">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-medium uppercase tracking-wide text-amber-700">Corporate</p>
                    <p class="mt-2 text-3xl font-bold text-amber-900">
                        {{ number_format(data_get($byType, 'Corporate', 0)) }}</p>
                    <p class="text-xs text-amber-600 mt-1">Organisations</p>
                </div>
                <i data-lucide="building-2" class="h-8 w-8 text-amber-300"></i>
            </div>
        </article>

        <!-- Multiple Owners Card -->
        <article class="rounded-lg border border-green-200 bg-green-50 px-4 py-5 shadow-sm">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-medium uppercase tracking-wide text-green-700">Multiple Owners</p>
                    <p class="mt-2 text-3xl font-bold text-green-900">
                        {{ number_format(data_get($byType, 'Multiple', 0)) }}</p>
                    <p class="text-xs text-green-600 mt-1">Shared ownership</p>
                </div>
                <i data-lucide="users" class="h-8 w-8 text-green-300"></i>
            </div>
        </article>

        <!-- Linked Customers Card -->
        {{-- <article class="rounded-lg border border-rose-200 bg-rose-50 px-4 py-5 shadow-sm">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-medium uppercase tracking-wide text-rose-700">With Customers</p>
                    <p class="mt-2 text-3xl font-bold text-rose-900">{{ number_format($entitiesWithCustomers) }}</p>
                    <p class="text-xs text-rose-600 mt-1">{{ $entitiesWithCustomers > 0 ?
                        number_format(round($totalCustomers / $entitiesWithCustomers, 1)) . ' avg customers' : 'No
                        linked customers' }}</p>
                </div>
                <i data-lucide="link-2" class="h-8 w-8 text-rose-300"></i>
            </div>
        </article> --}}
    </div>
</section>