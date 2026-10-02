<div id="bonus-dialog" class="dialog-overlay hidden" role="dialog" aria-modal="true" aria-labelledby="bonus-dialog-title">
    <div class="dialog-content" tabindex="-1">
        <div class="dialog-header">
            <h2 id="bonus-dialog-title" class="text-lg font-bold">Add Bonus/Extra Hours</h2>
            <p class="mt-1 text-sm text-gray-500" id="dialog-description"></p>
        </div>
        <div class="p-6 space-y-4">
            <div class="space-y-2">
                <label for="base-salary-amount" class="text-sm font-medium">Base Salary (₦)</label>
                <input type="number" id="base-salary-amount" placeholder="0" class="w-full px-3 py-2 border border-gray-300 rounded-md">
            </div>
            <div class="space-y-2">
                <label for="bonus-amount" class="text-sm font-medium">Bonus Amount (₦)</label>
                <input type="number" id="bonus-amount" placeholder="0" class="w-full px-3 py-2 border border-gray-300 rounded-md">
            </div>
            <div class="space-y-2">
                <label for="extra-hours-amount" class="text-sm font-medium">Extra Hours Payment (₦)</label>
                <input type="number" id="extra-hours-amount" placeholder="0" class="w-full px-3 py-2 border border-gray-300 rounded-md">
            </div>
            <div class="space-y-2">
                <label for="reason" class="text-sm font-medium">Reason</label>
                <input type="text" id="reason" placeholder="Performance bonus, overtime, etc." class="w-full px-3 py-2 border border-gray-300 rounded-md">
            </div>
        </div>
        <div class="dialog-footer">
            <button class="px-4 py-2 border border-gray-300 rounded-md hover:bg-gray-50 cancel-dialog-btn">Cancel</button>
            <button class="px-4 py-2 text-white bg-blue-600 rounded-md hover:bg-blue-700 add-payroll-btn">Add to Payroll</button>
        </div>
    </div>
</div>
<div id="attendance-breakdown-dialog" class="dialog-overlay hidden" role="dialog" aria-modal="true" aria-labelledby="attendance-breakdown-title">
    <div class="dialog-content max-w-3xl" tabindex="-1">
        <div class="dialog-header">
            <h2 id="attendance-breakdown-title" class="text-lg font-bold">Daily Hours Breakdown</h2>
            <p class="mt-1 text-sm text-gray-500" id="attendance-breakdown-subtitle"></p>
        </div>
        <div class="p-6 space-y-4">
            <div class="overflow-auto rounded-md border">
                <table class="min-w-full">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-2 text-left text-sm font-semibold text-gray-600">Date</th>
                            <th class="px-4 py-2 text-right text-sm font-semibold text-gray-600">Hours</th>
                            <th class="px-4 py-2 text-right text-sm font-semibold text-gray-600">Cumulative</th>
                            <th class="px-4 py-2 text-right text-sm font-semibold text-gray-600">Overtime</th>
                            <th class="px-4 py-2 text-left text-sm font-semibold text-gray-600">Notes</th>
                        </tr>
                    </thead>
                    <tbody id="attendance-breakdown-table"></tbody>
                </table>
            </div>
            <div id="attendance-breakdown-summary" class="text-sm text-gray-600"></div>
        </div>
        <div class="dialog-footer">
            <button class="px-4 py-2 border border-gray-300 rounded-md hover:bg-gray-50 close-attendance-breakdown-btn">Close</button>
        </div>
    </div>
</div>
<div id="edit-rate-dialog" class="dialog-overlay hidden" role="dialog" aria-modal="true" aria-labelledby="edit-rate-dialog-title">
    <div class="dialog-content" tabindex="-1">
        <div class="dialog-header">
            <h2 id="edit-rate-dialog-title" class="text-lg font-bold">Edit Daily Rate &amp; Shift</h2>
            <p class="mt-1 text-sm text-gray-500" id="rate-dialog-description"></p>
        </div>
        <div class="p-6 space-y-4">
            <div class="space-y-2">
                <label for="rate-user" class="text-sm font-medium">Employee</label>
                <select id="rate-user" class="w-full px-3 py-2 border border-gray-300 rounded-md">
                    <option value="">Select employee</option>
                </select>
            </div>
            <div class="space-y-2">
                <label for="current-rate" class="text-sm font-medium">Current Daily Rate</label>
                <input type="text" id="current-rate" disabled class="w-full px-3 py-2 border border-gray-300 rounded-md bg-gray-50">
            </div>
            <div class="space-y-2">
                <label for="new-rate" class="text-sm font-medium">New Daily Rate (₦)</label>
                <input type="number" id="new-rate" placeholder="Enter new rate" class="w-full px-3 py-2 border border-gray-300 rounded-md">
            </div>
            <div class="space-y-2">
                <label for="shift-hours" class="text-sm font-medium">Shift Hours</label>
                <select id="shift-hours" class="w-full px-3 py-2 border border-gray-300 rounded-md">
                    <option value="8">8 Hours (Full Day)</option>
                    <option value="4">4 Hours (Half Day)</option>
                </select>
                <p class="mt-1 text-xs text-gray-500">Sets the number of hours required for 1 login day</p>
            </div>
            <div class="space-y-2">
                <span class="text-sm font-medium">Overtime Authorization</span>
                <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                    <input type="checkbox" id="allow-overtime" class="rounded border-gray-300 text-blue-600 shadow-sm">
                    <span>Allow overtime beyond shift hours</span>
                </label>
                <p class="mt-1 text-xs text-gray-500">When disabled, sessions are auto-logged out at shift end.</p>
            </div>
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <div class="space-y-2">
                    <label for="overtime-cap" class="text-sm font-medium">Overtime Cap (hours)</label>
                    <input type="number" id="overtime-cap" step="0.25" min="0" placeholder="Optional" class="w-full px-3 py-2 border border-gray-300 rounded-md">
                    <p class="mt-1 text-xs text-gray-500">Cap overtime to a maximum number of hours per day.</p>
                </div>
                <div class="space-y-2">
                    <label for="overtime-notes" class="text-sm font-medium">Overtime Notes</label>
                    <input type="text" id="overtime-notes" placeholder="Reason or special handling" class="w-full px-3 py-2 border border-gray-300 rounded-md">
                </div>
            </div>
            <div class="space-y-2">
                <label for="effective-date" class="text-sm font-medium">Effective Date</label>
                <input type="date" id="effective-date" class="w-full px-3 py-2 border border-gray-300 rounded-md">
            </div>
            <div class="space-y-2">
                <label for="rate-reason" class="text-sm font-medium">Reason for Change</label>
                <input type="text" id="rate-reason" placeholder="Promotion, shift change, adjustment, etc." class="w-full px-3 py-2 border border-gray-300 rounded-md">
            </div>
        </div>
        <div class="dialog-footer">
            <button class="px-4 py-2 border border-gray-300 rounded-md hover:bg-gray-50 cancel-rate-dialog-btn">Cancel</button>
            <button class="px-4 py-2 text-white bg-blue-600 rounded-md hover:bg-blue-700 save-rate-btn">Save Changes</button>
        </div>
    </div>
</div>
