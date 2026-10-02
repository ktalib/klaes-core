<div id="cumulative-hours-tab" class="tab-content {{ isset($forceVisible) && $forceVisible ? '' : 'hidden' }} space-y-6">
    <div class="bg-white rounded-lg border card-shadow p-6">
        <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
            <div>
                <h3 class="text-lg font-bold">Cumulative Hours Leaderboard</h3>
                <p class="text-sm text-gray-500">All MDC users ordered by the total hours they logged within the selected payroll period.</p>
            </div>
            <div class="flex items-center gap-3">
                <div class="rounded-md border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-900">
                    <span class="font-semibold">Total Hours Logged:</span>
                    <span id="cumulative-hours-total">0h</span>
                </div>
                <button type="button" class="export-cumulative-hours-btn inline-flex items-center gap-2 rounded-md border border-blue-300 bg-white px-3 py-2 text-sm font-medium text-blue-700 hover:bg-blue-50">
                    <i class="fas fa-download"></i>
                    Export
                </button>
            </div>
        </div>
        <div class="mt-4 overflow-auto rounded-md border">
            <table class="w-full min-w-[900px]">
                <thead class="bg-gray-50 sticky-header">
                    <tr>
                        <th class="w-16 p-4 text-center font-medium">S/N</th>
                        <th class="p-4 text-left font-medium">Employee ID</th>
                        <th class="p-4 text-left font-medium">Name</th>
                        <th class="p-4 text-left font-medium">Department</th>
                        <th class="p-4 text-left font-medium">Unit</th>
                        <th class="p-4 text-center font-medium">Shift Hours</th>
                        <th class="p-4 text-center font-medium">Total Hours</th>
                        <th class="p-4 text-center font-medium">Login Days</th>
                        <th class="p-4 text-center font-medium">Attendance %</th>
                    </tr>
                </thead>
                <tbody id="cumulative-hours-table-body"></tbody>
            </table>
        </div>
    </div>
</div>
