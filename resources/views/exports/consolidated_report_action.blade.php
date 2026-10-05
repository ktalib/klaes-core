@php
    $reportDefinition = config('consolidated_reports.' . $reportKey);
@endphp
<div class="flex justify-end px-6 py-3">
    <button type="button" onclick="openRecordsExportModal()"
            class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-emerald-600 text-white text-sm font-semibold hover:bg-emerald-700">
        <i data-lucide="file-down" class="h-4 w-4"></i>
        Consolidated Report
    </button>
</div>
@push('scripts')
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.7.1/jspdf.plugin.autotable.min.js"></script>
    @include('exports.records_export_modal', ['exportConfig' => array_merge($reportDefinition, [
        'title' => $reportDefinition['title'] . ' Consolidated Report',
        'reportTitle' => $reportDefinition['title'] . ' Consolidated Report',
        'filename' => str_replace('-', '_', $reportKey) . '_Consolidated_Report',
        'endpoint' => route('consolidated-reports.export', ['report' => $reportKey]),
        'search' => request('search', ''),
    ])])
@endpush
