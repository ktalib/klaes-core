{{--
    "Where does this file go next" — asked on the Generation Summary just before the
    file number is issued.

    A commissioned file does not sit in the registry: it is created at the File
    Commissioning Office and moves on for processing. Capturing the destination here
    means the file opens with TWO tracking lines instead of one — the File
    Commissioning line, then the onward movement to the department/unit office
    chosen below.

    Included by BOTH Generation Summary modals (generate_fileno/mlsfno.blade.php and
    components/partials/commission-fileno-modal-html.blade.php); the shared script in
    generate_fileno/mls_js.blade.php populates and reads these ids.
--}}
<div class="bg-white border border-indigo-200 rounded-lg">
    <div class="px-4 py-3 bg-indigo-50 border-b border-indigo-200 rounded-t-lg flex items-center justify-between">
        <h4 class="text-sm font-semibold text-indigo-900 flex items-center gap-2">
            <i data-lucide="send" class="w-4 h-4 text-indigo-600"></i>
            <span>Next Destination</span>
        </h4>
        <span class="text-[11px] text-indigo-700">From: File Commissioning Office</span>
    </div>
    <div class="p-4 grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
            <label for="summaryDepartment" class="block text-xs font-medium text-gray-600 mb-1">
                Department <span class="text-red-500">*</span>
            </label>
            <select id="summaryDepartment"
                    class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/40">
                <option value="">Loading departments…</option>
            </select>
        </div>
        <div>
            <label for="summaryUnitOffice" class="block text-xs font-medium text-gray-600 mb-1">
                Unit / Office <span class="text-red-500">*</span>
            </label>
            <select id="summaryUnitOffice" disabled
                    class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/40 disabled:bg-gray-100 disabled:text-gray-400">
                <option value="">Select a department first</option>
            </select>
        </div>
        <div class="md:col-span-2">
            <label for="summaryReceivingOfficer" class="block text-xs font-medium text-gray-600 mb-1">
                Receiving Officer <span class="text-red-500">*</span>
            </label>
            <select id="summaryReceivingOfficer"
                    class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/40">
                <option value="">Loading officers…</option>
            </select>
            {{-- Required. The Movement History row on the File Tracking Sheet names who
                 took the file, and it reading "N/A" is what this field was added to fix.
                 "+ Add Other Officer..." keeps that from ever being a dead end: an officer
                 missing from the directory can be created without leaving the page. --}}
            <p class="mt-1 text-[11px] text-gray-500">
                Who receives the file at that office. Printed in the Movement History of the File Tracking Sheet.
                Not listed? Choose <span class="font-medium">+ Add Other Officer...</span>
            </p>
        </div>
        <p class="md:col-span-2 text-xs text-gray-500">
            The file is logged out of the File Commissioning Office to this office as soon as it is generated.
        </p>
    </div>
</div>
