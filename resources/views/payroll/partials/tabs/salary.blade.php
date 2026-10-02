<div id="salary-tab" class="tab-content hidden space-y-6">
    <div class="bg-white rounded-lg border card-shadow p-6">
        <div class="space-y-2">
            <h3 class="text-lg font-bold">Salary Calculation</h3>
            <p class="text-sm text-gray-500">Monthly salary calculated based on daily rate × login days + bonuses + extra hours</p>
        </div>
        <div class="mt-4 grid grid-cols-1 gap-4 md:grid-cols-2">
            <div class="space-y-2">
                <label class="text-sm font-medium" for="salary-month">Month</label>
                <div class="select-container">
                    <div class="select-trigger flex items-center justify-between w-full px-3 py-2 border border-gray-300 rounded-md cursor-pointer" data-type="salary-month">
                        <span>January 2024</span>
                        <i class="fas fa-chevron-down text-gray-400"></i>
                    </div>
                    <div class="select-dropdown hidden" id="salary-month-dropdown">
                        <div class="py-1">
                            <div class="px-3 py-2 cursor-pointer hover:bg-gray-100 select-option" data-value="2024-01">January 2024</div>
                            <div class="px-3 py-2 cursor-pointer hover:bg-gray-100 select-option" data-value="2024-02">February 2024</div>
                            <div class="px-3 py-2 cursor-pointer hover:bg-gray-100 select-option" data-value="2024-03">March 2024</div>
                            <div class="px-3 py-2 cursor-pointer hover:bg-gray-100 select-option" data-value="2024-04">April 2024</div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="space-y-2">
                <label class="text-sm font-medium" for="salary-search">Search Employee</label>
                <div class="relative">
                    <i class="fas fa-search absolute left-2.5 top-2.5 h-4 w-4 text-gray-400"></i>
                    <input class="salary-search-input w-full px-3 py-2 pl-8 border border-gray-300 rounded-md" id="salary-search" placeholder="Search by name or ID..." type="text">
                </div>
            </div>
        </div>
    </div>
    <div class="bg-white rounded-lg border card-shadow p-6">
        <div class="mb-4 flex items-center justify-between">
            <div>
                <h3 class="text-lg font-bold">Salary Breakdown</h3>
                <p class="text-sm text-gray-500">View and manage employee salaries for the month</p>
            </div>
        </div>
        <div class="overflow-auto rounded-md border">
            <table class="w-full min-w-[1000px]">
                <thead class="bg-gray-50 sticky-header">
                    <tr>
                        <th class="p-4 text-center font-medium w-14">S/N</th>
                        <th class="p-4 text-left font-medium">Employee</th>
                        <th class="p-4 text-center font-medium">Shift</th>
                        <th class="p-4 text-right font-medium">Daily Rate</th>
                        <th class="p-4 text-center font-medium">Login Days</th>
                        <th class="p-4 text-right font-medium">Base Salary</th>
                        <th class="p-4 text-right font-medium">Bonuses</th>
                        <th class="p-4 text-right font-medium">Extra Hours</th>
                        <th class="p-4 text-right font-medium">Total</th>
                        <th class="p-4 text-center font-medium">Actions</th>
                    </tr>
                </thead>
                <tbody id="salary-table-body"></tbody>
                <tfoot class="bg-gray-50 font-bold">
                    <tr class="border-t-2">
                        <td class="p-4 text-right" colspan="5">Total Payroll:</td>
                        <td class="p-4 text-right" id="total-base-salary">₦0</td>
                        <td class="p-4 text-right text-green-600" id="total-bonuses">+₦0</td>
                        <td class="p-4 text-right text-blue-600" id="total-extra-hours">+₦0</td>
                        <td class="p-4 text-right" id="total-salary">₦0</td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>
