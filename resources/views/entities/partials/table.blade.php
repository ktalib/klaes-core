<section class="rounded-lg border border-gray-200 bg-white shadow-sm" aria-label="Entity results">
    <div class="relative overflow-x-auto">
        <div id="entities-search-indicator" class="pointer-events-none absolute inset-x-0 top-4 z-10 hidden items-center justify-center">
            <span class="inline-flex items-center gap-2 rounded-full border border-blue-200 bg-blue-50 px-4 py-2 text-xs font-semibold uppercase tracking-wide text-blue-700 shadow-sm">
                <i data-lucide="loader-2" class="h-4 w-4 animate-spin"></i>
                Searching...
            </span>
        </div>
        <table
            id="entities-table"
            data-source="{{ route('entities.datatable') }}"
            data-create-url="{{ route('entities.create') }}"
            class="min-w-full divide-y divide-gray-200 opacity-100 transition-opacity duration-200"
            data-loading="true"
        >
            <thead class="bg-gray-50">
                <tr>
                    <th scope="col" class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wide text-gray-500">File Number</th>
                    <th scope="col" class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wide text-gray-500">Passport/Logo</th>
                    <th scope="col" class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wide text-gray-500">Entity</th>
                    <th scope="col" class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wide text-gray-500">Type</th>
                    <th scope="col" class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wide text-gray-500">Linked Customers</th>
                    <th scope="col" class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wide text-gray-500">Date Captured</th>
                    <th scope="col" class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wide text-gray-500">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 bg-white"></tbody>
        </table>
        <noscript>
            <div class="px-4 py-4 text-center text-sm text-gray-500">
                JavaScript is required to load entity records.
            </div>
        </noscript>
    </div>
</section>
