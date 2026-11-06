{{--
    Export Records button for a Survey register, with the shared preview / CSV / PDF modal.

    Include it in the page's button row, with:
        endpoint   route of the page's export
        title      modal + PDF heading, and the file name
        statuses   optional: the page's Status filter, value => label
        dateLabel  optional: what Start / End Date filter (default the creation date)
        params     optional: extra query values
        dates      optional: false hides Start / End Date, for a list that has no date
        onclick    optional: what the button runs (default opens the modal)
        class      optional: button class

    The page's other filters and its open tab go along as they stand, so the export starts from what the table shows.
    Only one per page: the modal has fixed ids.
--}}
@php
    $reStatuses = $statuses ?? [];
    // With no Status box in the modal, the page's own status filter goes along with the others.
    $reParams = collect(request()->query())
        ->except(array_merge(['q', 'search', 'page', 'gpage', 'edit', 'open', '_form'], $reStatuses ? ['status'] : []))
        ->filter(fn ($v) => is_scalar($v) && (string) $v !== '')
        ->all();
    $reParams = array_merge($reParams, $params ?? []);
    $reDate = $dateLabel ?? 'Date Created';
    $reDateNote = isset($dateLabel) ? strtolower($dateLabel) : 'record creation date';
@endphp
<button type="button" class="{{ $class ?? 'btn btn-secondary' }}" onclick="{{ $onclick ?? 'openRecordsExportModal()' }}">
    <i class="fas fa-file-export"></i> Export Records
</button>

@push('scripts')
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.7.1/jspdf.plugin.autotable.min.js"></script>
{{-- The modal's z-[100000] and max-h-[500px] are Tailwind v3 classes; the layout's v2 CDN ignores them. --}}
<style>
    #recordsExportModal { z-index: 100000; }
    /* Its messages (nothing loaded, too many for a PDF) open over it. */
    body > .swal2-container { z-index: 100001; }
    #recordsExportModal .overflow-x-auto { max-height: 500px; overflow-y: auto; }
</style>
@include('exports.records_export_modal', ['exportConfig' => [
    'title'        => 'Export ' . $title,
    'endpoint'     => $endpoint,
    'params'       => $reParams,
    'filename'     => 'Survey_' . trim(preg_replace('/[^A-Za-z0-9]+/', '_', $title), '_'),
    'reportTitle'  => $title,
    'department'   => 'SURVEY DEPARTMENT',
    'dateLabel'    => $reDate,
    'dateNote'     => $reDateNote,
    'showDates'    => $dates ?? true,
    'search'       => request('q'),
    'status'       => (string) request('status', ''),
    'statusOptions' => ['' => 'All Statuses'] + $reStatuses,
    // Records go out as stored: file numbers, emails and names keep their exact form.
    'preserveCase' => true,
    'columnPicker' => false,
    'previewLimit' => 500,
    'pdfLimit'     => 5000,
]])
@endpush
