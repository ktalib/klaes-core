@php
    $filters = [
        'search' => request('search'),
        'entity_type' => request('entity_type'),
    ];

    $activeFilters = collect([
        ['label' => 'Search', 'value' => $filters['search']],
        ['label' => 'Type', 'value' => $filters['entity_type']],
    ])->filter(fn($item) => filled($item['value']));
@endphp

<section id="entity-filters" class="rounded-lg border border-gray-200 bg-white px-5 py-5 shadow-sm">
    <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
        <div>
            <h2 class="text-lg font-semibold text-gray-900">Filter entities</h2>
            <p class="text-sm text-gray-600">Combine search and type filters to narrow the list.</p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            @foreach (['Individual' => 'Individual', 'Corporate' => 'Corporate', 'Multiple' => 'Multiple Owners'] as $type => $label)
                @php
                    $query = request()->only('search');
                    $query = array_filter($query, fn($value) => $value !== null && $value !== '');
                    $query['entity_type'] = $type;
                @endphp
                <a href="{{ route('entities.index', $query) }}"
                    class="inline-flex items-center gap-2 rounded-full border {{ request('entity_type') === $type ? 'border-blue-200 bg-blue-50 text-blue-700' : 'border-gray-200 text-gray-600 hover:bg-gray-50' }} px-3 py-1 text-xs font-medium">
                    <i data-lucide="sparkles" class="h-3 w-3"></i>
                    {{ $label }}
                </a>
            @endforeach
        </div>
    </div>

    <form method="GET" action="{{ route('entities.index') }}" class="mt-4 space-y-4" data-entity-filter-form>
        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label class="block text-sm font-medium text-gray-700">Search</label>
                <div class="mt-1 flex items-center rounded-md border border-gray-200 bg-white">
                    <span class="px-3 text-gray-400">
                        <i data-lucide="search" class="h-4 w-4"></i>
                    </span>
                    <input type="text" name="search" value="{{ $filters['search'] }}"
                        placeholder="Search by entity name"
                        class="w-full rounded-r-md border-0 py-2 pr-3 text-sm text-gray-700 placeholder-gray-400 focus:outline-none focus:ring-0"
                        data-entity-search-input>
                </div>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700">Entity type</label>
                <div class="mt-1 flex items-center rounded-md border border-gray-200 bg-white">
                    <span class="px-3 text-gray-400">
                        <i data-lucide="layers" class="h-4 w-4"></i>
                    </span>
                    <select name="entity_type"
                        class="w-full rounded-r-md border-0 py-2 pr-3 text-sm text-gray-700 focus:outline-none focus:ring-0"
                        data-entity-type-filter>
                        <option value="">All</option>
                        @foreach (['Individual' => 'Individual', 'Corporate' => 'Corporate', 'Multiple' => 'Multiple Owners'] as $type => $label)
                            <option value="{{ $type }}" @selected($filters['entity_type'] === $type)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>

        <div class="flex flex-col gap-4 border-t border-gray-100 pt-4 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex flex-wrap items-center gap-2">
                @if ($activeFilters->isNotEmpty())
                    <p class="text-xs uppercase tracking-wide text-gray-500">Active filters</p>
                    @foreach ($activeFilters as $filter)
                        <span
                            class="inline-flex items-center gap-1 rounded-full bg-gray-100 px-3 py-1 text-xs font-medium text-gray-700">
                            <i data-lucide="filter" class="h-3 w-3"></i>
                            {{ $filter['label'] }}:
                            <span class="font-semibold text-gray-900">{{ $filter['value'] }}</span>
                        </span>
                    @endforeach
                @else
                    <p class="text-sm text-gray-500">No filters applied</p>
                @endif
            </div>

            <div class="flex items-center gap-3">
                <a href="{{ route('entities.index') }}" class="text-sm text-gray-600 hover:text-gray-800"
                    data-entity-reset>Reset</a>
                <button type="submit"
                    class="inline-flex items-center gap-2 rounded-md bg-gray-900 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-700">
                    <i data-lucide="filter" class="h-4 w-4"></i>
                    Apply
                </button>
            </div>
        </div>
    </form>
</section>