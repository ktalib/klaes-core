@props(['customer'])

<div x-data="{ open: false }" class="relative inline-block text-left" x-cloak>
    <button type="button"
        class="inline-flex items-center justify-center rounded-md border border-gray-200 bg-white p-2 text-sm font-semibold text-gray-700 shadow-sm hover:bg-gray-50 focus:outline-none"
        aria-haspopup="true" aria-expanded="false" @click="open = !open" @keydown.escape.window="open = false">
        <i data-lucide="more-horizontal" class="h-4 w-4 text-gray-500" aria-hidden="true"></i>
    </button>

    <div x-show="open" x-transition:enter="transition ease-out duration-100"
        x-transition:enter-start="transform opacity-0 scale-95" x-transition:enter-end="transform opacity-100 scale-100"
        x-transition:leave="transition ease-in duration-75" x-transition:leave-start="transform opacity-100 scale-100"
        x-transition:leave-end="transform opacity-0 scale-95"
        class="absolute right-0 z-20 mt-2 w-44 origin-top-right rounded-lg border border-gray-100 bg-white py-1 shadow-lg"
        role="menu" aria-orientation="vertical" tabindex="-1" @click.away="open = false">
        <a href="{{ route('customers.show', $customer) }}"
            class="flex items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50" role="menuitem">
            <i data-lucide="user" class="h-4 w-4 text-blue-500"></i>
            View/Edit
        </a>

        <a href="#" class="flex items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50" role="menuitem">
            <i data-lucide="user" class="h-4 w-4 text-blue-500"></i>
            Account Details
        </a>
        <a href="#" class="flex items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50" role="menuitem">
            <i data-lucide="credit-card" class="h-4 w-4 text-green-500"></i>
            Bills & Payments
        </a>
        <a href="#" class="flex items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50" role="menuitem">
            <i data-lucide="file-text" class="h-4 w-4 text-indigo-500"></i>
            LIS
        </a>
        <a href="#" class="flex items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50" role="menuitem">
            <i data-lucide="map" class="h-4 w-4 text-orange-500"></i>
            GIS
        </a>
        <a href="#" class="flex items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50" role="menuitem">
            <i data-lucide="phone" class="h-4 w-4 text-purple-500"></i>
            Contact
        </a>
        <div class="border-t border-gray-100 my-1"></div>
        <a href="#" class="flex items-center gap-2 px-4 py-2 text-sm text-red-600 hover:bg-red-50" role="menuitem">
            <i data-lucide="trash-2" class="h-4 w-4 text-red-600"></i>
            Delete Customer
        </a>
    </div>
</div>