<div id="rates-tab" class="tab-content space-y-6">
    <div class="bg-white rounded-lg border card-shadow p-6">
        <div class="space-y-2">
            <h3 class="text-lg font-bold">User Daily Rates &amp; Shift Configuration</h3>
            <p class="text-sm text-gray-500">Manage daily rates and shift hours for all employees</p>
        </div>
        <div class="mt-4 space-y-2">
            <label class="text-sm font-medium" for="user-search">Search Employee</label>
            <div class="relative">
                <i class="fas fa-search absolute left-2.5 top-2.5 h-4 w-4 text-gray-400"></i>
                <input class="rates-search-input w-full px-3 py-2 pl-8 border border-gray-300 rounded-md" id="user-search" placeholder="Search by name or ID..." type="text">
            </div>
        </div>
    </div>
    <div class="bg-white rounded-lg border card-shadow p-6">
        <div class="mb-4 flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
            <div>
                <h3 class="text-lg font-bold">Employee Daily Rates &amp; Shifts</h3>
                <p class="text-sm text-gray-500">View, add, and update daily rates plus shift hours for employees</p>
            </div>
            <button type="button" class="add-rate-btn inline-flex items-center gap-2 rounded-md border border-blue-200 bg-blue-50 px-4 py-2 text-sm font-medium text-blue-700 transition hover:bg-blue-100">
                <i class="fas fa-plus"></i>
                Add Rate
            </button>
        </div>
        <div class="overflow-auto rounded-md border">
            <table class="w-full min-w-[1020px]">
                <thead class="bg-gray-50 sticky-header">
                    <tr>
                        <th class="p-4 text-center font-medium w-14">S/N</th>
                        <th class="p-4 text-left font-medium">Employee ID</th>
                        <th class="p-4 text-left font-medium">Name</th>
                        <th class="p-4 text-left font-medium">Department</th>
                        <th class="p-4 text-left font-medium">Unit</th>
                        <th class="p-4 text-left font-medium">Position</th>
                        <th class="p-4 text-right font-medium">Daily Rate</th>
                        <th class="p-4 text-right font-medium">Base Salary</th>
                        <th class="p-4 text-center font-medium">Shift Hours</th>
                        <th class="p-4 text-center font-medium">Overtime</th>
                        <th class="p-4 text-center font-medium">Status</th>
                        <th class="p-4 text-center font-medium">Actions</th>
                    </tr>
                </thead>
                <tbody id="rates-table-body"></tbody>
            </table>
        </div>
    </div>
</div>
