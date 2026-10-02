       @props(['entity'])

<div x-data="{ open: false }" class="relative inline-block text-left" x-cloak>
    <button type="button"
        class="inline-flex items-center justify-center rounded-md border border-gray-200 bg-white p-2 text-sm font-semibold text-gray-700 shadow-sm hover:bg-gray-50 focus:outline-none"
        aria-haspopup="true"
        aria-expanded="false"
        @click="open = !open"
        @keydown.escape.window="open = false"
    >
        <i data-lucide="more-horizontal" class="h-4 w-4 text-gray-500" aria-hidden="true"></i>
    </button>

    <div
        x-show="open"
        x-transition:enter="transition ease-out duration-100"
        x-transition:enter-start="transform opacity-0 scale-95"
        x-transition:enter-end="transform opacity-100 scale-100"
        x-transition:leave="transition ease-in duration-75"
        x-transition:leave-start="transform opacity-100 scale-100"
        x-transition:leave-end="transform opacity-0 scale-95"
        class="absolute right-0 z-20 mt-2 w-44 origin-top-right rounded-lg border border-gray-100 bg-white py-1 shadow-lg"
        role="menu"
        aria-orientation="vertical"
        tabindex="-1"
        @click.away="open = false"
    >
        <a href="{{ route('entities.show', $entity) }}" class="flex items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50" role="menuitem">
            <i data-lucide="eye" class="h-4 w-4 text-blue-500"></i>
            View Details
        </a>
        <a href="{{ route('entities.customers', $entity) }}" class="flex items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50" role="menuitem">
            <i data-lucide="users" class="h-4 w-4 text-green-500"></i>
            View Customers
        </a>
        <a href="{{ route('entities.edit', $entity) }}" class="flex items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50" role="menuitem">
            <i data-lucide="pencil" class="h-4 w-4 text-indigo-500"></i>
            Edit Entity
        </a>
    </div>
</div>
