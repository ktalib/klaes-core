{{--
    Generic records export modal (preview + date range + CSV + PDF).

    Usage:
    @include('exports.records_export_modal', ['exportConfig' => [
        'title'        => 'Export Land RofO',
        'subtitle'     => 'Consolidated report generation & export filter',
        'endpoint'     => route('land-rofos.export'),
        'params'       => ['view' => 'only'],          // always-sent query params
        'filename'     => 'Land_RofO',                 // download filename base
        'reportTitle'  => 'Land RofO Register',        // PDF heading
        'search'       => request('search'),           // initial search value
        'statusOptions'=> ['' => 'All Statuses', 'pending' => 'Pending'],
    ]])
--}}
@php
    $cfg = array_merge([
        'title'         => 'Export Records',
        'subtitle'      => 'Consolidated report generation & export filter',
        'endpoint'      => '',
        'params'        => [],
        'filename'      => 'Records',
        'reportTitle'   => 'Records Register',
        'search'        => '',
        'statusOptions' => ['' => 'All Statuses'],
    ], $exportConfig ?? []);

    // Only the keys the JS driver needs — built here because Blade's @json
    // directive cannot parse an inline array literal argument.
    $exportJsConfig = [
        'endpoint'    => $cfg['endpoint'],
        'params'      => (object) $cfg['params'],
        'filename'    => $cfg['filename'],
        'reportTitle' => $cfg['reportTitle'],
    ];
@endphp

<div id="recordsExportModal" class="fixed inset-0 z-[100000] hidden overflow-y-auto" aria-labelledby="records-export-title" role="dialog" aria-modal="true">
    <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
        <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" aria-hidden="true" onclick="closeRecordsExportModal()"></div>

        <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

        <div class="inline-block align-bottom bg-white rounded-xl text-left overflow-hidden shadow-2xl transform transition-all sm:my-8 sm:align-middle sm:max-w-6xl sm:w-full border border-gray-200">
            <!-- Modal Header -->
            <div class="bg-gradient-to-r from-emerald-600 to-emerald-700 px-6 py-4 flex justify-between items-center">
                <div class="flex items-center gap-3">
                    <div class="bg-white/20 p-2 rounded-lg">
                        <i data-lucide="download" class="h-5 w-5 text-white"></i>
                    </div>
                    <div>
                        <h3 class="text-xl font-bold text-white" id="records-export-title">{{ $cfg['title'] }}</h3>
                        <p class="text-emerald-100 text-sm opacity-90">{{ $cfg['subtitle'] }}</p>
                    </div>
                </div>
                <button type="button" onclick="closeRecordsExportModal()" class="text-white hover:text-emerald-100 transition-colors">
                    <i data-lucide="x" class="h-5 w-5"></i>
                </button>
            </div>

            <!-- Modal Body -->
            <div class="bg-white px-6 py-6">
                <!-- Consolidated Filter Summary & Controls -->
                <div class="mb-6 p-4 bg-gray-50 rounded-xl border border-gray-200 shadow-sm">
                    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-5 gap-4 items-end">
                        <!-- Search -->
                        <div class="space-y-1">
                            <label for="recordsExportSearch" class="block text-[10px] font-bold text-gray-500 uppercase tracking-wider">Search</label>
                            <input type="text" id="recordsExportSearch" value="{{ $cfg['search'] }}" placeholder="File, applicant, location"
                                   class="w-full border border-gray-300 rounded-lg px-3 py-2 text-xs bg-white focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 transition-all outline-none">
                        </div>

                        <!-- Status -->
                        <div class="space-y-1">
                            <label for="recordsExportStatus" class="block text-[10px] font-bold text-gray-500 uppercase tracking-wider">Status</label>
                            <select id="recordsExportStatus" onchange="loadRecordsExportData()"
                                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-xs bg-white focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 transition-all outline-none">
                                @foreach($cfg['statusOptions'] as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>

                        <!-- Start Date -->
                        <div class="space-y-1">
                            <label for="recordsExportStartDate" class="block text-[10px] font-bold text-gray-500 uppercase tracking-wider">Start Date</label>
                            <input type="date" id="recordsExportStartDate" data-no-flatpickr="1" onchange="loadRecordsExportData()"
                                   class="w-full border border-gray-300 rounded-lg px-3 py-2 text-xs bg-white focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 transition-all outline-none">
                        </div>

                        <!-- End Date -->
                        <div class="space-y-1">
                            <label for="recordsExportEndDate" class="block text-[10px] font-bold text-gray-500 uppercase tracking-wider">End Date</label>
                            <input type="date" id="recordsExportEndDate" data-no-flatpickr="1" onchange="loadRecordsExportData()"
                                   class="w-full border border-gray-300 rounded-lg px-3 py-2 text-xs bg-white focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 transition-all outline-none">
                        </div>

                        <!-- Refresh & Count -->
                        <div class="flex items-center justify-between md:justify-end gap-4 h-full md:pb-0.5">
                            <div class="text-right md:mr-2">
                                <span class="block text-[10px] font-bold text-gray-400 uppercase tracking-wider">Total Records</span>
                                <span id="recordsExportCount" class="text-lg font-black text-emerald-700 font-mono">0</span>
                            </div>
                            <button type="button" onclick="loadRecordsExportData()"
                                    class="bg-emerald-600 hover:bg-emerald-700 text-white font-bold py-2 px-4 rounded-lg flex items-center gap-2 text-xs shadow-sm transition-all hover:shadow-md h-[36px]">
                                <i data-lucide="refresh-cw" class="h-3.5 w-3.5"></i>
                                <span>Refresh</span>
                            </button>
                        </div>
                    </div>
                    <p class="mt-3 text-[10px] text-gray-400 uppercase tracking-wider font-bold">
                        Date range filters on the record creation date. Leave both blank to export everything.
                    </p>
                </div>

                <!-- Preview Table Wrapper -->
                <div class="overflow-hidden border border-gray-200 rounded-xl shadow-sm">
                    <div class="overflow-x-auto max-h-[500px]">
                        <table class="min-w-full divide-y divide-gray-200" id="recordsExportTable">
                            <thead class="bg-gray-50 sticky top-0 z-10">
                                <tr></tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200" id="recordsExportBody"></tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Modal Footer -->
            <div class="bg-gray-50 px-6 py-4 flex flex-col sm:flex-row-reverse gap-3 border-t border-gray-200">
                <button type="button" onclick="downloadRecordsExportPdf()"
                        class="w-full inline-flex justify-center rounded-lg border border-transparent shadow-sm px-6 py-2.5 bg-red-600 text-base font-bold text-white hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 sm:w-auto sm:text-sm items-center gap-2 transition-all">
                    <i data-lucide="file-text" class="h-4 w-4"></i>
                    <span>Download PDF</span>
                </button>
                <button type="button" onclick="downloadRecordsExportCsv()"
                        class="w-full inline-flex justify-center rounded-lg border border-transparent shadow-sm px-6 py-2.5 bg-emerald-600 text-base font-bold text-white hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-emerald-500 sm:w-auto sm:text-sm items-center gap-2 transition-all">
                    <i data-lucide="table" class="h-4 w-4"></i>
                    <span>Download CSV</span>
                </button>
                <button type="button" onclick="closeRecordsExportModal()"
                        class="w-full inline-flex justify-center rounded-lg border border-gray-300 shadow-sm px-6 py-2.5 bg-white text-base font-medium text-gray-700 hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-300 sm:w-auto sm:text-sm transition-all">
                    Cancel
                </button>
            </div>
        </div>
    </div>
</div>

<style>
    #recordsExportTable thead th {
        position: sticky;
        top: 0;
        z-index: 10;
    }
</style>

<script>
    window.recordsExportConfig = @json($exportJsConfig);
</script>
<script src="{{ asset('js/records_export.js') }}"></script>
