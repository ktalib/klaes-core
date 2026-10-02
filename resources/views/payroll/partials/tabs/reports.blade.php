<div id="payroll-tab" class="tab-content hidden space-y-6">
    <div class="bg-white rounded-lg border card-shadow p-6">
        <div class="space-y-2">
            <h3 class="text-lg font-bold">Payroll Reports</h3>
            <p class="text-sm text-gray-500">Generate and export individual employee payroll reports</p>
        </div>
        <div class="mt-4 grid grid-cols-1 gap-4 md:grid-cols-3">
            <div class="space-y-2">
                <label class="text-sm font-medium" for="payroll-month">Month</label>
                <div class="select-container">
                    <div class="select-trigger flex items-center justify-between w-full px-3 py-2 border border-gray-300 rounded-md cursor-pointer" data-type="payroll-month">
                        <span>January 2024</span>
                        <i class="fas fa-chevron-down text-gray-400"></i>
                    </div>
                    <div class="select-dropdown hidden" id="payroll-month-dropdown">
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
                <label class="text-sm font-medium" for="payroll-unit">Unit</label>
                <div class="select-container">
                    <div class="select-trigger flex items-center justify-between w-full px-3 py-2 border border-gray-300 rounded-md cursor-pointer" data-type="payroll-unit">
                        <span>All Units</span>
                        <i class="fas fa-chevron-down text-gray-400"></i>
                    </div>
                    <div class="select-dropdown hidden" id="payroll-unit-dropdown">
                        <div class="py-1">
                            <div class="px-3 py-2 cursor-pointer hover:bg-gray-100 select-option" data-value="all">All Units</div>
                            <div class="px-3 py-2 cursor-pointer hover:bg-gray-100 select-option" data-value="PRA">PRA</div>
                            <div class="px-3 py-2 cursor-pointer hover:bg-gray-100 select-option" data-value="PIC">PIC</div>
                            <div class="px-3 py-2 cursor-pointer hover:bg-gray-100 select-option" data-value="Page Typing">Page Typing</div>
                            <div class="px-3 py-2 cursor-pointer hover:bg-gray-100 select-option" data-value="Blind Scanning">Blind Scanning</div>
                            <div class="px-3 py-2 cursor-pointer hover:bg-gray-100 select-option" data-value="Indexing">Indexing</div>
                            <div class="px-3 py-2 cursor-pointer hover:bg-gray-100 select-option" data-value="File Archiving">File Archiving</div>
                            <div class="px-3 py-2 cursor-pointer hover:bg-gray-100 select-option" data-value="File Tracking">File Tracking</div>
                            <div class="px-3 py-2 cursor-pointer hover:bg-gray-100 select-option" data-value="Sorting, Collation &amp; Batching">Sorting, Collation &amp; Batching</div>
                        </div>
                    </div>
                </div>
                <div class="mt-2 flex flex-wrap gap-2" id="selected-units-container"></div>
            </div>
            <div class="flex items-end gap-2">
                <button class="export-payroll-btn flex-1 rounded-md bg-blue-600 px-4 py-2 text-white hover:bg-blue-700">
                    <i class="fas fa-download mr-2"></i>
                    Export
                </button>
                <button class="print-payroll-btn rounded-md border border-gray-300 px-4 py-2 hover:bg-gray-50">
                    <i class="fas fa-print"></i>
                </button>
            </div>
        </div>
    </div>
    <div class="grid grid-cols-1 gap-4 md:grid-cols-4" id="payroll-summary-cards"></div>
    <div class="bg-white rounded-lg border card-shadow p-6">
        <div class="mb-4 space-y-2">
            <h3 class="text-lg font-bold">Employee Payroll Details</h3>
            <p class="text-sm text-gray-500" id="payroll-details-description">Individual salary breakdown for January 2024 (based on shift hours)</p>
        </div>
        <div class="overflow-auto rounded-md border">
            <table class="w-full min-w-[1300px]">
                <thead class="bg-gray-50 sticky-header">
                    <tr>
                        <th class="sticky left-0 z-10 border-r bg-gray-50 p-4 text-center font-medium w-16">S/N</th>
                        <th class="sticky left-16 z-10 border-r bg-gray-50 p-4 text-left font-medium w-24">Employee ID</th>
                        <th class="sticky left-40 z-10 border-r bg-gray-50 p-4 text-left font-medium min-w-[200px]">Name</th>
                        <th class="p-4 text-left font-medium">Department</th>
                        <th class="p-4 text-left font-medium">Unit</th>
                        <th class="p-4 text-center font-medium">Shift</th>
                        <th class="p-4 text-right font-medium">Daily Rate</th>
                        <th class="p-4 text-center font-medium">Hours Worked</th>
                        <th class="bg-blue-50 p-4 text-center font-medium">Login Days</th>
                        <th class="p-4 text-right font-medium">Base Salary</th>
                        <th class="p-4 text-right font-medium">Bonuses</th>
                        <th class="p-4 text-right font-medium">Extra Hours</th>
                        <th class="p-4 text-right font-medium">Total Salary</th>
                    </tr>
                </thead>
                <tbody id="payroll-table-body"></tbody>
                <tfoot class="border-t-2 bg-gray-50 font-bold">
                    <tr>
                        <td class="sticky left-0 z-10 border-r bg-gray-50 p-4" colspan="5">Grand Total (<span id="employee-count">0</span> employees)</td>
                        <td class="p-4" colspan="4"></td>
                        <td class="p-4 text-right" id="payroll-total-base">₦0</td>
                        <td class="p-4 text-right text-green-600" id="payroll-total-bonuses">₦0</td>
                        <td class="p-4 text-right text-blue-600" id="payroll-total-extra">₦0</td>
                        <td class="p-4 text-right" id="payroll-grand-total">₦0</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>
