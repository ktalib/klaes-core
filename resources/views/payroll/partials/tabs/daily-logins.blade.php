<div id="daily-logins-tab" class="tab-content {{ isset($forceVisible) && $forceVisible ? '' : 'hidden' }} space-y-6">
    <div class="bg-white rounded-lg border card-shadow p-6">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div class="space-y-2">
                <h3 class="text-lg font-bold">Daily Logins &amp; Manual Attendance</h3>
                <p class="text-sm text-gray-500">Review digital login sessions and manual attendance marks captured for the selected date.</p>
            </div>
            <div class="flex items-center gap-3">
                <button type="button" class="export-daily-logins-btn inline-flex items-center gap-2 rounded-md border border-blue-300 bg-white px-3 py-2 text-sm font-medium text-blue-700 hover:bg-blue-50">
                    <i class="fas fa-download"></i>
                    Export
                </button>
                <label class="text-sm font-medium text-gray-700" for="daily-logins-date-filter">Date:</label>
                <input type="date" id="daily-logins-date-filter" 
                    class="px-3 py-2 border border-gray-300 rounded-md text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                    value="{{ date('Y-m-d') }}">
            </div>
        </div>
        <div class="mt-4 overflow-auto rounded-md border">
            <table class="w-full min-w-[1020px]">
                <thead class="bg-gray-50 sticky-header">
                    <tr>
                        <th class="w-16 p-4 text-center font-medium">S/N</th>
                        <th class="p-4 text-left font-medium">Employee ID</th>
                        <th class="p-4 text-left font-medium">Name</th>
                        <th class="p-4 text-left font-medium">Department</th>
                        <th class="p-4 text-left font-medium">Unit</th>
                        <th class="p-4 text-left font-medium">Presence / Lateness</th>
                        <th class="p-4 text-left font-medium">Login Time</th>
                        <th class="p-4 text-left font-medium">Logout Time</th>
                        <th class="p-4 text-center font-medium">Shift Hours</th>
                        <th class="p-4 text-center font-medium">Total Hours</th>
                    </tr>
                </thead>
                <tbody id="daily-logins-table-body"></tbody>
            </table>
        </div>
        <div class="mt-4 rounded-md border border-blue-200 bg-blue-50 p-3 text-sm text-blue-900">
            <strong>Tip:</strong> Manual attendance marks appear inline and are tagged as “Manual attendance” in the status column. Login/logout reflect the longest recorded session; shift hours show the configured daily target, and total hours aggregate all captured time.
        </div>
    </div>
</div>
