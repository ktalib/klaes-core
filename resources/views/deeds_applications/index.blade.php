@php 
    use App\Models\ConsentApplication; 
    $assignRoles = collect(explode(',', (string) (auth()->user()->assign_role ?? '')))
        ->map(fn($r) => trim($r))
        ->filter();
    $isSupperAdmin = $assignRoles->contains(fn($r) => strcasecmp($r, 'Supper Admin') === 0);
    // Master Reset (Print) only — Delete Master stays Supper Admin. See
    // config module_permissions.deeds_print_reset_users.
    $canResetPrints = auth()->user()?->canResetConsentPrints() ?? false;
@endphp
@extends('layouts.app')

@section('page-title', 'Applications for Consent')
 @section('content')
<link rel="stylesheet" href="{{ asset('css/deeds_applications.css') }}">
<style>
  [x-cloak] {
    display: none !important;
  }



  /* ── DataTables styling (deeds) ── */
  #deeds-table-wrapper .dataTables_wrapper {
    padding: 0;
  }

  #deeds-table-wrapper .dataTables_wrapper .dataTables_length,
  #deeds-table-wrapper .dataTables_wrapper .dataTables_filter {
    padding: 1rem 1.25rem 0;
  }

  #deeds-table-wrapper .dataTables_wrapper .dataTables_length label,
  #deeds-table-wrapper .dataTables_wrapper .dataTables_filter label {
    font-size: 0.8rem;
    color: #64748b;
    font-weight: 500;
  }

  #deeds-table-wrapper .dataTables_wrapper .dataTables_filter input {
    border: 1px solid #e2e8f0;
    border-radius: 0.75rem;
    padding: 0.45rem 0.85rem;
    font-size: 0.8rem;
    outline: none;
    transition: border-color 0.2s, box-shadow 0.2s;
  }

  #deeds-table-wrapper .dataTables_wrapper .dataTables_filter input:focus {
    border-color: #3b82f6;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.15);
  }

  #deeds-table-wrapper .dataTables_wrapper .dataTables_length select {
    border: 1px solid #e2e8f0;
    border-radius: 0.5rem;
    padding: 0.35rem 0.5rem;
    font-size: 0.8rem;
  }

  #deeds-table-wrapper .dataTables_wrapper .dataTables_info {
    padding: 0.75rem 1.25rem;
    font-size: 0.75rem;
    color: #94a3b8;
  }

  #deeds-table-wrapper .dataTables_wrapper .dataTables_paginate {
    padding: 0.75rem 1.25rem;
  }

  #deeds-table-wrapper .dataTables_wrapper .dataTables_paginate .paginate_button {
    padding: 0.35rem 0.7rem;
    margin: 0 0.15rem;
    border-radius: 0.5rem;
    border: 1px solid #e2e8f0;
    font-size: 0.75rem;
    color: #475569 !important;
    background: transparent;
  }

  #deeds-table-wrapper .dataTables_wrapper .dataTables_paginate .paginate_button.current,
  #deeds-table-wrapper .dataTables_wrapper .dataTables_paginate .paginate_button.current:hover {
    background: #3b82f6 !important;
    color: #fff !important;
    border-color: #3b82f6;
  }

  #deeds-table-wrapper .dataTables_wrapper .dataTables_paginate .paginate_button:hover {
    background: #eff6ff !important;
    color: #3b82f6 !important;
    border-color: #bfdbfe;
  }

  #deeds-table-wrapper .dataTables_wrapper .dataTables_paginate .paginate_button.disabled,
  #deeds-table-wrapper .dataTables_wrapper .dataTables_paginate .paginate_button.disabled:hover {
    color: #cbd5e1 !important;
    background: transparent !important;
    border-color: #e2e8f0;
    cursor: default;
  }

  #deeds-app-table thead th {
    background-color: #f8fafc;
    border-bottom: 2px solid #e2e8f0;
    white-space: nowrap;
  }

  #deeds-app-table tbody td {
    vertical-align: middle;
  }

  #deeds-app-table tbody tr:hover {
    background-color: #f8fafc;
  }

  #deeds-app-table.dataTable {
    border-collapse: collapse !important;
  }

  #deeds-app-table.dataTable thead .sorting,
  #deeds-app-table.dataTable thead .sorting_asc,
  #deeds-app-table.dataTable thead .sorting_desc {
    background-image: none !important;
    padding-right: 1.5rem !important;
    position: relative;
  }

  #deeds-app-table.dataTable thead .sorting::after,
  #deeds-app-table.dataTable thead .sorting_asc::after,
  #deeds-app-table.dataTable thead .sorting_desc::after {
    content: '';
    position: absolute;
    right: 0.5rem;
    top: 50%;
    transform: translateY(-50%);
  }

  #deeds-app-table.dataTable thead .sorting::after {
    content: '⇅';
    opacity: 0.25;
    font-size: 10px;
  }

  #deeds-app-table.dataTable thead .sorting_asc::after {
    content: '↑';
    opacity: 0.7;
    font-size: 10px;
  }

  #deeds-app-table.dataTable thead .sorting_desc::after {
    content: '↓';
    opacity: 0.7;
    font-size: 10px;
  }

  #deeds-app-table_wrapper .dataTables_empty {
    padding: 3rem 1.25rem !important;
    color: #94a3b8;
    font-style: italic;
  }

  /* Export buttons */
  #deeds-table-wrapper .dt-buttons {
    padding: 0.75rem 1.25rem 0;
  }

  #deeds-table-wrapper .dt-buttons .dt-button {
    padding: 0.4rem 1rem;
    margin-right: 0.4rem;
    border-radius: 0.5rem;
    background: #f1f5f9;
    color: #475569;
    border: 1px solid #e2e8f0;
    cursor: pointer;
    font-size: 0.75rem;
    font-weight: 600;
    transition: all 0.15s;
  }

  #deeds-table-wrapper .dt-buttons .dt-button:hover {
    background: #e2e8f0;
    color: #1e293b;
  }

  /* Actions dropdown - fixed positioning to escape all containers */
  .deeds-action-dropdown {
    position: fixed;
    z-index: 2147483647 !important;
    width: 13rem;
    border-radius: 0.75rem;
    border: 1px solid #e2e8f0 !important;
    background: #ffffff !important;
    box-shadow: 0 10px 30px -5px rgba(0, 0, 0, 0.15), 0 8px 15px -6px rgba(0, 0, 0, 0.1);
    display: flex;
    flex-direction: column;
    white-space: nowrap;
    padding: 0.5rem 0;
    pointer-events: auto;
    visibility: hidden;
    opacity: 1 !important;
  }

  .deeds-action-dropdown button {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    width: 100%;
    text-align: left;
    padding: 0.5rem 1rem;
    font-size: 0.75rem;
    color: rgb(100 116 139);
    transition: all 0.15s;
  }

  .deeds-action-dropdown button i {
    flex-shrink: 0;
  }

  .deeds-action-dropdown button:hover {
    background: rgb(239 246 255);
    color: rgb(37 99 235);
  }

  /* Let dropdowns escape the scroll area while keeping horizontal scroll */
  .deeds-table-card {
    overflow: visible !important;
  }

  .deeds-table-scroll {
    overflow-x: auto;
    overflow-y: visible;
  }

  /* ── Responsive column hiding ── */
  @media (max-width: 1024px) {
    .col-hide-lg {
      display: none !important;
    }
  }

  @media (max-width: 768px) {
    .col-hide-md {
      display: none !important;
    }
  }
</style>
 


<div class="flex-1 overflow-auto bg-slate-50/60">
  @include('admin.header', [
  'PageTitle' => 'Applications for Consent',
  'PageDescription' => 'Capture applications before generating letters.',
  ])

  <div class="max-w-[1500px] mx-auto p-4 md:p-6 space-y-5">
    {{-- Validation errors --}}
    @if($errors->any())
    <div class="bg-rose-50 border border-rose-200 text-rose-700 px-4 py-3 rounded-xl">
      <p class="font-semibold mb-1">Please fix the highlighted issues:</p>
      <ul class="list-disc list-inside text-sm">
        @foreach($errors->all() as $error)
        <li>{{ $error }}</li>
        @endforeach
      </ul>
    </div>
    @endif

    {{-- Hero --}}
    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-5 lg:p-6">
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
          <h1 class="text-2xl md:text-3xl font-bold text-slate-900">Applications for Consent</h1>
          <p class="text-sm text-slate-500 mt-1">Capture applications before generating letters</p>
        </div>
        <button type="button" id="open-generate-modal"
          class="inline-flex items-center gap-2 px-5 py-2.5 bg-blue-600 text-white text-sm font-semibold rounded-xl shadow-sm hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition">
          <i data-lucide="plus-circle" class="h-4 w-4"></i>
          <span>New Application</span>
        </button>
      </div>
    </div>

    {{-- ── Deeds Master Delete Legend ── --}}
    <div class="flex flex-wrap items-center gap-3 px-1 py-1 mb-2">
        <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Current Listing:</span>
        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-bold bg-violet-50 text-violet-700 border border-violet-200 whitespace-nowrap">
            DEEDS APPLICATIONS FOR CONSENT
        </span>

        @if($isSupperAdmin)
            <button type="button" id="btn-toggle-selection" onclick="toggleSelectionMode()"
                class="inline-flex items-center gap-1.5 px-3 py-1 bg-white border border-slate-200 text-slate-700 rounded-full text-[11px] font-semibold hover:bg-slate-50 transition shadow-sm cursor-pointer whitespace-nowrap">
                <i data-lucide="check-square" class="w-3.5 h-3.5 text-blue-600"></i>
                Start Master Deletion
            </button>

            <button type="button" id="btn-bulk-delete-master" onclick="deleteSelectedMasters()"
                class="hidden inline-flex items-center gap-1.5 px-3 py-1 bg-rose-600 border border-rose-600 text-white rounded-full text-[11px] font-semibold hover:bg-rose-700 transition shadow-sm cursor-pointer whitespace-nowrap">
                <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
                Delete Master
            </button>
        @endif
    </div>

    {{-- Stats cards --}}
    <div class="grid grid-cols-2 lg:grid-cols-3 xl:grid-cols-6 gap-3 md:gap-4">
      <div class="bg-white p-4 md:p-5 rounded-2xl border border-slate-100 shadow-sm ring-1 ring-emerald-100 bg-emerald-50/10">
        <p class="text-[10px] md:text-xs font-semibold text-emerald-600 uppercase tracking-wider">Captured Today</p>
        <p class="text-2xl md:text-3xl font-bold text-emerald-700 mt-1.5">{{ $counts['today'] }}</p>
      </div>

      <div class="bg-white p-4 md:p-5 rounded-2xl border border-slate-100 shadow-sm">
        <p class="text-[10px] md:text-xs font-semibold text-slate-500 uppercase tracking-wider">Total Applications</p>
        <p class="text-2xl md:text-3xl font-bold text-slate-900 mt-1.5">{{ $counts['total'] }}</p>
      </div>
    
      <div class="bg-white p-4 md:p-5 rounded-2xl border border-slate-100 shadow-sm">
        <p class="text-[10px] md:text-xs font-semibold text-blue-600 uppercase tracking-wider">Assignment</p>
        <p class="text-2xl md:text-3xl font-bold text-blue-700 mt-1.5">{{ $counts['Assignment'] }}</p>
      </div>
      <div class="bg-white p-4 md:p-5 rounded-2xl border border-slate-100 shadow-sm">
        <p class="text-[10px] md:text-xs font-semibold text-purple-600 uppercase tracking-wider">ST Assignment</p>
        <p class="text-2xl md:text-3xl font-bold text-purple-700 mt-1.5">{{ $counts['ST Assignment'] }}</p>
      </div>
      <div class="bg-white p-4 md:p-5 rounded-2xl border border-slate-100 shadow-sm">
        <p class="text-[10px] md:text-xs font-semibold text-emerald-600 uppercase tracking-wider">Gift</p>
        <p class="text-2xl md:text-3xl font-bold text-emerald-700 mt-1.5">{{ $counts['Gift'] }}</p>
      </div>
      <div class="bg-white p-4 md:p-5 rounded-2xl border border-slate-100 shadow-sm">
        <p class="text-[10px] md:text-xs font-semibold text-amber-600 uppercase tracking-wider">Mortgage</p>
        <p class="text-2xl md:text-3xl font-bold text-amber-700 mt-1.5">{{ $counts['Mortgage'] }}</p>
      </div>
    </div>

    <!-- Data Table -->
    <div class="bg-white rounded-3xl border border-slate-100 shadow-sm overflow-hidden">
      <div class="overflow-x-auto text-sm">
        <table class="w-full text-left border-collapse" id="consent-table">
          <thead class="bg-slate-50 border-b border-slate-100">
            <tr>
              @if($isSupperAdmin)
                <th class="checkbox-col hidden px-4 py-4 text-center whitespace-nowrap" style="width:40px; min-width:40px;">
                  <input type="checkbox" id="check-all-masters" onchange="toggleSelectAllMasters(this)" class="rounded text-blue-600 focus:ring-blue-500 w-4 h-4 cursor-pointer">
                </th>
              @endif
              <th class="px-6 py-4 font-semibold text-slate-700 uppercase tracking-wider text-[11px]">S/N</th>

              {{-- Where the file stands in Valuation -> Consent -> Registration.
                   Painted by public/js/deeds-pipeline.js from one batched lookup. --}}
              <th class="px-6 py-4 font-semibold text-slate-700 uppercase tracking-wider text-[11px] whitespace-nowrap" title="Valuation → Consent → Registration">Workflow</th>
              <th class="px-6 py-4 font-semibold text-slate-700 uppercase tracking-wider text-[11px] whitespace-nowrap">
                File Number</th>
              <th class="px-6 py-4 font-semibold text-slate-700 uppercase tracking-wider text-[11px]">Consent Type</th>
              <th class="px-6 py-4 font-semibold text-slate-700 uppercase tracking-wider text-[11px]">Party 1</th>
              <th class="px-6 py-4 font-semibold text-slate-700 uppercase tracking-wider text-[11px]">Party 2</th>
              <th class="px-6 py-4 font-semibold text-slate-700 uppercase tracking-wider text-[11px]">Party 3</th>
              <th class="px-6 py-4 font-semibold text-slate-700 uppercase tracking-wider text-[11px]">Captured By</th>
              <th class="px-6 py-4 font-semibold text-slate-700 uppercase tracking-wider text-[11px]">App. Date</th>
              <th class="px-6 py-4 font-semibold text-slate-700 uppercase tracking-wider text-[11px]">Time Captured
              </th>
              <th class="px-6 py-4 font-semibold text-slate-700 uppercase tracking-wider text-[11px]">Date Captured
              </th>
              <th class="px-6 py-4 font-semibold text-slate-700 uppercase tracking-wider text-[11px]">Prints</th>
              <th class="px-6 py-4 font-semibold text-slate-700 uppercase tracking-wider text-[11px] text-center">
                Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100">
            {{-- Filled a page at a time from deeds-applications.data; the cells come
                 from partials/_table_row_cells. --}}
          </tbody>
        </table>
      </div>
    </div>

    @include('consent_applications.partials.modal')
    @include('components.global-fileno-modal')

    <!-- ST Assignment Units Modal -->
    <div id="st-units-modal" class="fixed inset-0 z-[100] hidden items-center justify-center">
      <div class="absolute inset-0 bg-slate-900/40 backdrop-blur-sm transition-opacity opacity-0"
        id="st-units-modal-backdrop"></div>
      <div
        class="relative w-full max-w-5xl bg-white rounded-2xl shadow-2xl scale-95 opacity-0 transition-all duration-300"
        id="st-units-modal-panel">
        <div class="flex items-center justify-between p-5 border-b border-slate-100">
          <h3 class="text-lg font-bold text-slate-900 flex items-center gap-2">
            <i data-lucide="layers" class="h-5 w-5 text-blue-600"></i>
            ST Assignment Units
          </h3>
          <button type="button"
            class="text-slate-400 hover:text-slate-600 hover:bg-slate-100 p-1.5 rounded-lg transition"
            onclick="closeStUnitsModal()">
            <i data-lucide="x" class="h-5 w-5"></i>
          </button>
        </div>
        <div class="p-5 max-h-[60vh] overflow-y-auto">
          <div class="mb-4 text-sm hidden">
            <span class="font-bold text-slate-700">Party 1 (Mother Applicant):</span>
            <span id="st-party1-name" class="ml-2 text-slate-600"></span>
          </div>
          <table class="w-full text-left border-collapse text-sm">
            <thead class="bg-slate-50 border-b border-slate-100">
              <tr>
                <th class="px-4 py-3 font-semibold text-slate-700 whitespace-nowrap">S/N</th>
                <th class="px-4 py-3 font-semibold text-slate-700 whitespace-nowrap">File Number</th>
                <th class="px-4 py-3 font-semibold text-slate-700 whitespace-nowrap">Consent Type</th>
                <th class="px-4 py-3 font-semibold text-slate-700">Party 1</th>
                <th class="px-4 py-3 font-semibold text-slate-700">Party 2 (Unit Owner)</th>
              </tr>
            </thead>
            <tbody id="st-units-tbody" class="divide-y divide-slate-100">
            </tbody>
          </table>
        </div>
      </div>
    </div>


  </div>
</div>

@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
<script>
  window.consentFormVariant = 'application';
  window.consentLandUseTypes = @json(($landUseTypes ?? collect()) -> values());
  window.consentPurposes = @json(($purposes ?? collect()) -> values());
</script>
<script src="{{ asset('js/global-fileno-modal.js') }}"></script>
{{-- Cache-busted by file mtime: the browser otherwise keeps serving a stale
     copy while freshly rendered Blade markup expects the new behaviour. --}}
<script src="{{ asset('js/consent_applications.js') }}?v={{ @filemtime(public_path('js/consent_applications.js')) }}"></script>
{{-- Paints the Workflow column. Batches every row on the page into one lookup. --}}
<script src="{{ asset('js/deeds-pipeline.js') }}?v={{ @filemtime(public_path('js/deeds-pipeline.js')) }}"></script>
<script>
  const isSupperAdmin = @json($isSupperAdmin);
  const canResetPrints = @json($canResetPrints);

  document.addEventListener('DOMContentLoaded', function () {
    // ── Lucide icons ──
    if (window.lucide) window.lucide.createIcons();

    // ── Global File No Modal ──
    if (window.GlobalFileNoModal) window.GlobalFileNoModal.init();

    // ST Units Modal Logic
    const viewBtns = document.querySelectorAll('.view-st-assignment-btn');
    viewBtns.forEach(btn => {
      btn.addEventListener('click', function () {
        const party1 = this.dataset.party1;
        const units = JSON.parse(this.dataset.units || '[]');
        const fileNo = this.dataset.fileNo || 'N/A';

        document.getElementById('st-party1-name').textContent = party1;

        const tbody = document.getElementById('st-units-tbody');
        tbody.innerHTML = '';

        if (units.length === 0) {
          tbody.innerHTML = '<tr><td colspan="5" class="px-4 py-4 text-center text-slate-500 italic">No units found</td></tr>';
        } else {
          units.forEach((unit, idx) => {
            tbody.innerHTML += `
                <tr class="hover:bg-slate-50 transition whitespace-nowrap">
                  <td class="px-4 py-3 text-slate-600">${idx + 1}</td>
                  <td class="px-4 py-3 text-slate-900 font-bold">${unit.unit_fileno || 'N/A'}</td>
                  <td class="px-4 py-3 text-slate-600">
                    <span class="px-2.5 py-1 bg-purple-50 text-purple-700 border border-purple-100 rounded-full text-[10px] font-bold uppercase whitespace-nowrap inline-block">ST Assignment</span>
                  </td>
                  <td class="px-4 py-3 text-slate-600">${party1}</td>
                  <td class="px-4 py-3 text-slate-600">${unit.party2_name || 'N/A'}</td>
                </tr>
              `;
          });
        }

        openStUnitsModal();
      });
    });

    window.openStUnitsModal = function () {
      const modal = document.getElementById('st-units-modal');
      const backdrop = document.getElementById('st-units-modal-backdrop');
      const panel = document.getElementById('st-units-modal-panel');

      modal.classList.remove('hidden');
      modal.classList.add('flex');

      // Trigger animation
      setTimeout(() => {
        backdrop.classList.remove('opacity-0');
        backdrop.classList.add('opacity-100');
        panel.classList.remove('scale-95', 'opacity-0');
        panel.classList.add('scale-100', 'opacity-100');
      }, 10);
    };

    window.closeStUnitsModal = function () {
      const modal = document.getElementById('st-units-modal');
      const backdrop = document.getElementById('st-units-modal-backdrop');
      const panel = document.getElementById('st-units-modal-panel');

      backdrop.classList.remove('opacity-100');
      backdrop.classList.add('opacity-0');
      panel.classList.remove('scale-100', 'opacity-100');
      panel.classList.add('scale-95', 'opacity-0');

      setTimeout(() => {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
      }, 300);
    };

    // DataTable Initialization. Server-side: each page, search, sort and
    // consent-type filter is one request to deeds-applications.data, which
    // returns only the rows shown.
    if (typeof jQuery !== 'undefined' && jQuery.fn.DataTable) {
      const $table = jQuery('#consent-table');
      if (!jQuery.fn.DataTable.isDataTable('#consent-table')) {
        // Order must match partials/_table_row_cells.blade.php.
        const columns = [
          { name: 'sn', orderable: false, className: 'px-6 py-4 text-slate-600 font-bold italic' },
          { name: 'workflow', orderable: false, className: 'px-6 py-4 whitespace-nowrap' },
          { name: 'file_number', className: 'px-6 py-4 text-slate-900 font-bold whitespace-nowrap' },
          { name: 'consent_type', className: 'px-6 py-4' },
          { name: 'party1', className: 'px-6 py-4 text-slate-900 font-semibold uppercase' },
          { name: 'party2', className: 'px-6 py-4 text-slate-900 font-semibold uppercase' },
          { name: 'party3', orderable: false, className: 'px-6 py-4 text-slate-900 font-semibold uppercase' },
          { name: 'created_by', className: 'px-6 py-4' },
          { name: 'app_date', className: 'px-6 py-4 text-slate-600 font-medium whitespace-nowrap text-xs' },
          { name: 'time', className: 'px-6 py-4 text-slate-600 font-medium whitespace-nowrap text-xs' },
          { name: 'date', className: 'px-6 py-4 text-slate-600 font-medium whitespace-nowrap text-xs' },
          { name: 'prints', className: 'px-6 py-4' },
          { name: 'actions', orderable: false, className: 'px-6 py-4' }
        ];
        if (isSupperAdmin) {
          columns.unshift({ name: 'select', orderable: false, className: 'checkbox-col hidden px-4 py-4 text-center' });
        }

        let consentTypeFilter = '';
        let searchTimer = null;

        const dt = $table.DataTable({
          serverSide: true,
          processing: true,
          ajax: {
            url: @json(route('deeds-applications.data')),
            data: function (d) { d.consent_type = consentTypeFilter; }
          },
          columns: columns,
          // Newest first, as the list has always opened.
          order: [[columns.findIndex(c => c.name === 'date'), 'desc']],
          pageLength: 10,
          lengthMenu: [[10, 25, 50, -1], [10, 25, 50, "All"]],
          ordering: true,
          autoWidth: false,
          language: {
            search: "",
            searchPlaceholder: "Search...",
            processing: "Loading..."
          },
          drawCallback: function () {
            const table = document.getElementById('consent-table');
            // A page drawn while Master Deletion is on shows its checkboxes too.
            table.querySelectorAll('td.checkbox-col').forEach(function (td) {
              td.classList.toggle('hidden', !window.deedsSelectionMode);
            });
            const checkAll = document.getElementById('check-all-masters');
            if (checkAll) checkAll.checked = false;
            if (window.updateBulkDeleteButtonState) window.updateBulkDeleteButtonState();
            if (window.lucide) window.lucide.createIcons();
            if (window.DeedsPipeline) window.DeedsPipeline.refresh(table);
          },
          initComplete: function () {
            const api = this.api();

            // Typing searches after a pause rather than on every key, since each
            // search is now a server request.
            jQuery('#consent-table_filter input')
              .off('keyup.DT input.DT search.DT paste.DT cut.DT')
              .on('input', function () {
                const value = this.value;
                clearTimeout(searchTimer);
                searchTimer = setTimeout(function () { api.search(value).draw(); }, 350);
              });

            const filterWrapper = jQuery('<div class="inline-flex items-center ml-4"></div>')
              .appendTo(jQuery('#consent-table_filter'));

            const select = jQuery('<select class="border border-slate-200 rounded-lg text-sm px-3 py-1.5 outline-none focus:border-blue-500"><option value="">All Consent Types</option></select>')
              .appendTo(filterWrapper)
              .on('change', function () {
                consentTypeFilter = jQuery(this).val() || '';
                api.draw();
              });

            @json($consentTypes).forEach(function (value) {
              select.append(jQuery('<option></option>').attr('value', value).text(value));
            });
          }
        });
      }
    }

    // ── Global Actions Dropdown Logic ──
    const globalDropdown = document.getElementById('deeds-global-dropdown');
    const dropdownContent = document.getElementById('deeds-dropdown-content');

    if (!globalDropdown) {
      console.error("Global dropdown element not found!");
    }

    document.addEventListener('click', function (e) {
      const trigger = e.target.closest('.deeds-action-trigger');

      if (trigger) {
        console.log("Action trigger clicked", trigger.dataset);
        e.stopPropagation();

        let app;
        try {
          app = JSON.parse(trigger.dataset.app);
        } catch (err) {
          console.error("Error parsing application data:", err, trigger.dataset.app);
          return;
        }

        const isSt = trigger.dataset.isSt === 'true';
        const rect = trigger.getBoundingClientRect();

        // Build content
        let html = '';
        if (isSt) {
          html = `
                      <button type="button" class="view-st-global flex items-center gap-3 w-full px-4 py-2.5 text-xs text-blue-600 hover:bg-blue-50 transition font-bold"
                          data-party1="${app.mother_party1 || ''}"
                          data-file-no="${app.file_number || ''}"
                          data-units='${JSON.stringify(app.st_units || [])}'>
                          <i data-lucide="eye" class="h-4 w-4"></i>
                          <span>View Units</span>
                      </button>
                  `;
        } else {
          const isRegistered = app.is_registered_in_instrument === true || app.is_registered_in_instrument === 1 || app.is_registered_in_instrument === "1";
          const canUpdate = !isRegistered;
          const canPrint = app.print_count < 2;

          if (canUpdate) {
            html += `
                          <button type="button" class="edit-consent-btn flex items-center gap-3 w-full px-4 py-2.5 text-xs text-amber-600 hover:bg-amber-50 transition font-bold cursor-pointer"
                              data-id="${app.id}" data-app='${JSON.stringify(app)}'>
                              <i data-lucide="edit-3" class="h-4 w-4"></i>
                              <span>Update Application</span>
                          </button>
                      `;
          } else {
            html += `
                          <div class="flex items-center gap-3 w-full px-4 py-2.5 text-xs text-slate-300 cursor-not-allowed font-bold" title="Registered in Instrument Capture">
                              <i data-lucide="lock" class="h-4 w-4"></i>
                              <span>Update</span>
                          </div>
                      `;
          }

          // A consent captured before transaction types has none: Print first
          // asks for one (consent type and percentages too), saves it, then
          // opens the new letter. Handled by .consent-print-needs-type in
          // public/js/consent_applications.js.
          const needsType = !app.transaction_type;
          const esc = (v) => String(v == null ? '' : v).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

          if (canPrint) {
            html += `
                          <a href="/consent-applications/${app.id}" target="_blank"
                              class="${needsType ? 'consent-print-needs-type ' : ''}flex items-center gap-3 px-4 py-2.5 text-xs text-blue-600 hover:bg-blue-50 transition font-bold"
                              data-id="${esc(app.id)}" data-file="${esc(app.file_number)}" data-consent-type="${esc(app.consent_type)}">
                              <i data-lucide="printer" class="h-4 w-4"></i>
                              <span>Print</span>
                          </a>
                      `;
          } else {
            html += `
                          <div class="flex items-center gap-3 w-full px-4 py-2.5 text-xs text-slate-300 cursor-not-allowed font-bold" title="Cannot print again">
                              <i data-lucide="printer" class="h-4 w-4"></i>
                              <span>Print</span>
                          </div>
                      `;
          }

          if (isSupperAdmin || canResetPrints) {
            html += `<div class="h-px bg-slate-100 my-1"></div>`;
          }

          // Supper Admin, or a user on the print-reset list.
          if (canResetPrints) {
            if (app.print_count > 0) {
              html += `
                            <button type="button" onclick="confirmResetPrintMaster('${app.id}', '${app.file_number}')" class="flex items-center gap-3 w-full px-4 py-2.5 text-xs text-amber-600 hover:bg-amber-50 transition font-bold cursor-pointer">
                                <i data-lucide="rotate-ccw" class="h-4 w-4"></i>
                                <span>Master Reset (Print)</span>
                            </button>
                        `;
            }
          }

          // Delete Master stays Supper Admin only.
          if (isSupperAdmin) {
            html += `
                          <button type="button" onclick="confirmSingleDeleteMaster('${app.id}', '${app.file_number}')" class="flex items-center gap-3 w-full px-4 py-2.5 text-xs text-rose-600 hover:bg-rose-50 transition font-bold cursor-pointer">
                              <i data-lucide="trash-2" class="h-4 w-4"></i>
                              <span>Delete Master</span>
                          </button>
                      `;
          }
        }

        dropdownContent.innerHTML = html;
        if (window.lucide) {
          try {
            window.lucide.createIcons({ container: dropdownContent });
          } catch (e) { console.error("Lucide error:", e); }
        }

        // Show it so we can measure it
        globalDropdown.style.visibility = 'visible';

        const dropdownRect = globalDropdown.getBoundingClientRect();
        const width = dropdownRect.width || 208; // Fallback to 13rem
        const height = dropdownRect.height || 160;

        let top = rect.bottom + 12; // Increased offset
        let left = rect.right - width;

        // Prevent overflow
        if (top + height > window.innerHeight) {
          top = rect.top - height - 8;
        }
        if (left < 10) left = 10;
        if (left + width > window.innerWidth) {
          left = window.innerWidth - width - 10;
        }

        globalDropdown.style.top = `${top}px`;
        globalDropdown.style.left = `${left}px`;
        console.log("Final position set:", top, left, "Visible:", globalDropdown.style.visibility);
      } else if (globalDropdown && !e.target.closest('#deeds-global-dropdown')) {
        globalDropdown.style.visibility = 'hidden';
      }
    });

    // Special handling for the ST View Units button inside the global dropdown
    document.addEventListener('click', function (e) {
      const stBtn = e.target.closest('.view-st-global');
      if (stBtn) {
        const party1 = stBtn.dataset.party1;
        const units = JSON.parse(stBtn.dataset.units || '[]');
        const fileNo = stBtn.dataset.fileNo || 'N/A';

        document.getElementById('st-party1-name').textContent = party1;
        const tbody = document.getElementById('st-units-tbody');
        tbody.innerHTML = '';

        if (units.length === 0) {
          tbody.innerHTML = '<tr><td colspan="5" class="px-4 py-4 text-center text-slate-500 italic">No units found</td></tr>';
        } else {
          units.forEach((unit, idx) => {
            tbody.innerHTML += `
                          <tr class="hover:bg-slate-50 transition whitespace-nowrap">
                              <td class="px-4 py-3 text-slate-600">${idx + 1}</td>
                              <td class="px-4 py-3 text-slate-900 font-bold">${unit.unit_fileno || 'N/A'}</td>
                              <td class="px-4 py-3 text-slate-600">
                                  <span class="px-2.5 py-1 bg-purple-50 text-purple-700 border border-purple-100 rounded-full text-[10px] font-bold uppercase whitespace-nowrap inline-block">ST Assignment</span>
                              </td>
                              <td class="px-4 py-3 text-slate-600">${party1}</td>
                              <td class="px-4 py-3 text-slate-600">${unit.party2_name || 'N/A'}</td>
                          </tr>
                      `;
          });
        }

        openStUnitsModal();
        globalDropdown.style.visibility = 'hidden';
      }
    });

    // Close dropdown on scroll
    window.addEventListener('scroll', () => {
      if (globalDropdown && globalDropdown.style.visibility === 'visible') {
        globalDropdown.style.visibility = 'hidden';
      }
    }, true);

    // Kept as state rather than read off the cells: the table redraws its rows
    // on every page, and a new page must come up in the same mode.
    window.deedsSelectionMode = false;

    window.toggleSelectionMode = function() {
        var cols = document.querySelectorAll('.checkbox-col');
        var btn = document.getElementById('btn-toggle-selection');
        var isShowing = !window.deedsSelectionMode;
        window.deedsSelectionMode = isShowing;

        cols.forEach(function(col) {
            col.classList.toggle('hidden', !isShowing);
        });
        if (!isShowing) {
            // Uncheck checkboxes when disabling selection mode
            var masterCheckbox = document.getElementById('check-all-masters');
            if (masterCheckbox) masterCheckbox.checked = false;
            document.querySelectorAll('.master-row-checkbox').forEach(function(cb) {
                cb.checked = false;
            });
        }

        if (btn) {
            if (isShowing) {
                btn.innerHTML = '<i data-lucide="x" class="w-3.5 h-3.5 text-red-600"></i> Cancel Selection';
                btn.classList.replace('bg-white', 'bg-red-50');
                btn.classList.replace('text-slate-700', 'text-red-700');
                btn.classList.replace('border-slate-200', 'border-red-200');
            } else {
                btn.innerHTML = '<i data-lucide="check-square" class="w-3.5 h-3.5 text-blue-600"></i> Start Master Deletion';
                btn.classList.replace('bg-red-50', 'bg-white');
                btn.classList.replace('text-red-700', 'text-slate-700');
                btn.classList.replace('border-red-200', 'border-slate-200');
            }
            if (typeof lucide !== 'undefined' && typeof lucide.createIcons === 'function') {
                lucide.createIcons();
            }
        }

        updateBulkDeleteButtonState();
    };

    window.toggleSelectAllMasters = function(masterCheckbox) {
        var checkboxes = document.querySelectorAll('.master-row-checkbox');
        checkboxes.forEach(function(cb) {
            cb.checked = masterCheckbox.checked;
        });
        updateBulkDeleteButtonState();
    };

    window.updateBulkDeleteButtonState = function() {
        var checkedCount = document.querySelectorAll('.master-row-checkbox:checked').length;
        var bulkBtn = document.getElementById('btn-bulk-delete-master');
        if (bulkBtn) {
            if (checkedCount > 0) {
                bulkBtn.classList.remove('hidden');
            } else {
                bulkBtn.classList.add('hidden');
            }
        }
    };

    window.deleteSelectedMasters = function() {
        var checkedBoxes = document.querySelectorAll('.master-row-checkbox:checked');
        if (checkedBoxes.length === 0) {
            Swal.fire({
                icon: 'warning',
                title: 'No Selection',
                text: 'Please select at least one record to delete.'
            });
            return;
        }

        var recordsToDelete = [];
        checkedBoxes.forEach(function(cb) {
            recordsToDelete.push({
                id: cb.getAttribute('data-id'),
                fileNo: cb.getAttribute('data-file-no')
            });
        });

        var fileNumbersText = recordsToDelete.map(r => r.fileNo).join(', ');

        // First Warning
        Swal.fire({
            title: 'First Warning: Are you sure?',
            html: "You are about to perform a <strong>Bulk Master Delete</strong> on <strong>" + recordsToDelete.length + "</strong> record(s): <br><strong style='color:#e11d48;'>" + fileNumbersText + "</strong>.<br><br>" +
                  "This will delete their Deeds Consent Application records permanently.",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#e11d48',
            cancelButtonColor: '#475569',
            confirmButtonText: 'Yes, I am sure (1/2)',
            cancelButtonText: 'Cancel'
        }).then(function(res1) {
            if (res1.isConfirmed) {
                // Second Warning
                Swal.fire({
                    title: 'Second Warning: FINAL CONFIRMATION!',
                    html: "<strong>CRITICAL ACTION!</strong> Are you absolutely 100% certain you want to proceed? " +
                          "This action is permanent and <strong>CANNOT BE UNDONE</strong>.",
                    icon: 'error',
                    showCancelButton: true,
                    confirmButtonColor: '#b91c1c',
                    cancelButtonColor: '#475569',
                    confirmButtonText: 'YES, DELETE PERMANENTLY (2/2)',
                    cancelButtonText: 'Abort'
                }).then(function(res2) {
                    if (res2.isConfirmed) {
                        Swal.fire({
                            title: 'Deleting...',
                            html: 'Processing bulk deletion. Please wait.',
                            allowOutsideClick: false,
                            didOpen: function() {
                                Swal.showLoading();
                            }
                        });

                        var csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
                        var bulkDeleteUrl = '{{ route("deeds-applications.delete-master-bulk") }}';
                        var ids = recordsToDelete.map(r => r.id);

                        fetch(bulkDeleteUrl, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': csrfToken,
                                'Accept': 'application/json'
                            },
                            body: JSON.stringify({
                                _method: 'DELETE',
                                ids: ids
                            })
                        })
                        .then(function(res) {
                            return res.json();
                        })
                        .then(function(result) {
                            if (result.success) {
                                Swal.fire({
                                    icon: 'success',
                                    title: 'Bulk Deletion Complete!',
                                    text: result.message || 'Selected records deleted successfully.',
                                    timer: 2500,
                                    showConfirmButton: false
                                }).then(function() {
                                    location.reload();
                                });
                            } else {
                                Swal.fire({
                                    icon: 'error',
                                    title: 'Failed',
                                    text: result.message || 'Failed to complete bulk deletion.'
                                });
                            }
                        })
                        .catch(function(err) {
                            console.error('Bulk delete master error:', err);
                            Swal.fire({
                                icon: 'error',
                                title: 'Error',
                                text: 'A network error occurred. Please try again.'
                            });
                        });
                    }
                });
            }
        });
    };

    window.confirmResetPrintMaster = function(id, fileNo) {
        if (!id || !fileNo) return;

        // Close actions dropdown first
        var globalDropdown = document.getElementById('deeds-global-dropdown');
        if (globalDropdown) globalDropdown.style.visibility = 'hidden';

        Swal.fire({
            title: 'Master Reset: Are you sure?',
            html: "You are about to <strong>reset the print count</strong> for application: <br><strong style='color:#d97706;'>" + fileNo + "</strong>.<br><br>" +
                  "This will allow the application to be edited and printed again.",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d97706',
            cancelButtonColor: '#475569',
            confirmButtonText: 'Yes, reset it',
            cancelButtonText: 'Cancel'
        }).then(function(res) {
            if (res.isConfirmed) {
                Swal.fire({
                    title: 'Resetting...',
                    html: 'Processing print reset. Please wait.',
                    allowOutsideClick: false,
                    didOpen: function() {
                        Swal.showLoading();
                    }
                });

                var csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
                var resetUrl = '/deeds-applications/reset-print-master/' + id;

                fetch(resetUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({})
                })
                .then(function(res) {
                    return res.json();
                })
                .then(function(result) {
                    if (result.success) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Print Reset Complete!',
                            text: result.message || 'Print count reset successfully.',
                            timer: 2500,
                            showConfirmButton: false
                        }).then(function() {
                            location.reload();
                        });
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Failed',
                            text: result.message || 'Failed to reset print count.'
                        });
                    }
                })
                .catch(function(err) {
                    console.error('Reset print master error:', err);
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: 'A network error occurred. Please try again.'
                    });
                });
            }
        });
    };

    window.confirmSingleDeleteMaster = function(id, fileNo) {
        if (!id || !fileNo) return;

        // Close actions dropdown first
        var globalDropdown = document.getElementById('deeds-global-dropdown');
        if (globalDropdown) globalDropdown.style.visibility = 'hidden';

        // First Warning
        Swal.fire({
            title: 'First Warning: Are you sure?',
            html: "You are about to perform a <strong>Master Delete</strong> on application: <br><strong style='color:#e11d48;'>" + fileNo + "</strong>.<br><br>" +
                  "This will delete this Deeds Consent Application record permanently.",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#e11d48',
            cancelButtonColor: '#475569',
            confirmButtonText: 'Yes, I am sure (1/2)',
            cancelButtonText: 'Cancel'
        }).then(function(res1) {
            if (res1.isConfirmed) {
                // Second Warning
                Swal.fire({
                    title: 'Second Warning: FINAL CONFIRMATION!',
                    html: "<strong>CRITICAL ACTION!</strong> Are you absolutely 100% certain you want to proceed? " +
                          "This action is permanent and <strong>CANNOT BE UNDONE</strong>.",
                    icon: 'error',
                    showCancelButton: true,
                    confirmButtonColor: '#b91c1c',
                    cancelButtonColor: '#475569',
                    confirmButtonText: 'YES, DELETE PERMANENTLY (2/2)',
                    cancelButtonText: 'Abort'
                }).then(function(res2) {
                    if (res2.isConfirmed) {
                        Swal.fire({
                            title: 'Deleting...',
                            html: 'Processing deletion. Please wait.',
                            allowOutsideClick: false,
                            didOpen: function() {
                                Swal.showLoading();
                            }
                        });

                        var csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
                        var deleteUrl = '/deeds-applications/delete-master/' + id;

                        fetch(deleteUrl, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': csrfToken,
                                'Accept': 'application/json'
                            },
                            body: JSON.stringify({
                                _method: 'DELETE'
                            })
                        })
                        .then(function(res) {
                            return res.json();
                        })
                        .then(function(result) {
                            if (result.success) {
                                Swal.fire({
                                    icon: 'success',
                                    title: 'Deletion Complete!',
                                    text: result.message || 'Record deleted successfully.',
                                    timer: 2500,
                                    showConfirmButton: false
                                }).then(function() {
                                    location.reload();
                                });
                            } else {
                                Swal.fire({
                                    icon: 'error',
                                    title: 'Failed',
                                    text: result.message || 'Failed to delete record.'
                                });
                            }
                        })
                        .catch(function(err) {
                            console.error('Delete master error:', err);
                            Swal.fire({
                                icon: 'error',
                                title: 'Error',
                                text: 'A network error occurred. Please try again.'
                            });
                        });
                    }
                });
            }
        });
    };

    // Properties Modal Logic
    const propertiesModal = document.getElementById('properties-modal');
    const propertiesModalContent = document.getElementById('properties-modal-content');
    const propertiesModalList = document.getElementById('properties-modal-list');
    const overlay = document.querySelector('.properties-modal-overlay');
    const closeBtns = document.querySelectorAll('.close-properties-modal');

    function closePropertiesModal() {
        if (!propertiesModal) return;
        propertiesModalContent.classList.remove('scale-100', 'opacity-100');
        propertiesModalContent.classList.add('scale-95', 'opacity-0');
        setTimeout(() => {
            propertiesModal.classList.add('hidden');
        }, 200);
    }

    closeBtns.forEach(btn => btn.addEventListener('click', closePropertiesModal));
    if (overlay) overlay.addEventListener('click', closePropertiesModal);

    // Using event delegation since table might be paginated/re-rendered by DataTables if applicable
    document.addEventListener('click', function(e) {
        const btn = e.target.closest('.view-properties-btn');
        if (btn) {
            e.preventDefault();
            e.stopPropagation();
            
            const mainFile = btn.dataset.mainFile;
            const mainDesc = btn.dataset.mainDesc || 'No description available';
            const mainApplicant = btn.dataset.mainApplicant || 'N/A';
            let additional = [];
            try {
                additional = JSON.parse(btn.dataset.additional || '[]');
            } catch(e) {}

            let html = `
                <div class="p-4 rounded-xl border border-indigo-100 bg-indigo-50/30 space-y-2">
                    <div class="flex items-center justify-between">
                        <span class="text-[10px] font-bold text-indigo-500 uppercase tracking-widest">Primary Property</span>
                        <span class="px-2 py-1 bg-white border border-indigo-100 rounded text-xs font-mono font-bold text-indigo-700">${mainFile}</span>
                    </div>
                    <div class="mt-2 text-sm">
                        <p class="font-bold text-slate-800 uppercase mb-1">${mainApplicant}</p>
                        <p class="font-medium text-slate-700">${mainDesc}</p>
                    </div>
                </div>
            `;

            if (additional && additional.length > 0) {
                additional.forEach((prop, index) => {
                    const propApplicant = prop.applicant_name || prop.applicant || 'N/A';
                    html += `
                        <div class="p-4 rounded-xl border border-slate-100 bg-slate-50 space-y-2">
                            <div class="flex items-center justify-between">
                                <span class="text-[10px] font-bold text-slate-500 uppercase tracking-widest">Additional Property ${index + 1}</span>
                                <span class="px-2 py-1 bg-white border border-slate-200 rounded text-xs font-mono font-bold text-slate-700">${prop.file_number}</span>
                            </div>
                            <div class="mt-2 text-sm">
                                <p class="font-bold text-slate-800 uppercase mb-1">${propApplicant}</p>
                                <p class="font-medium text-slate-700">${prop.description || 'No description available'}</p>
                            </div>
                        </div>
                    `;
                });
            }

            if (propertiesModalList) propertiesModalList.innerHTML = html;
            
            if (propertiesModal) {
                propertiesModal.classList.remove('hidden');
                // trigger reflow
                void propertiesModal.offsetWidth;
                propertiesModalContent.classList.remove('scale-95', 'opacity-0');
                propertiesModalContent.classList.add('scale-100', 'opacity-100');
            }
        }
    });

  });
</script>

<!-- Properties Modal -->
<div id="properties-modal" class="fixed inset-0 z-[60] hidden flex items-center justify-center p-4">
    <div class="fixed inset-0 bg-slate-900/40 backdrop-blur-sm transition-opacity properties-modal-overlay"></div>
    <div class="relative bg-white rounded-3xl shadow-2xl w-full max-w-xl max-h-[85vh] flex flex-col overflow-hidden border border-slate-100 transform transition-all scale-95 opacity-0 duration-200" id="properties-modal-content">
        <div class="px-6 py-5 border-b border-slate-100 flex items-center justify-between bg-white shrink-0">
            <div>
                <h3 class="text-xl font-bold text-slate-900">Properties Involved</h3>
                <p class="text-xs text-slate-500 mt-1 uppercase tracking-widest font-semibold">List of file numbers and property descriptions</p>
            </div>
            <button type="button" class="close-properties-modal text-slate-400 hover:text-slate-600 p-2 hover:bg-slate-50 rounded-xl transition">
                <i data-lucide="x" class="h-6 w-6"></i>
            </button>
        </div>
        <div class="p-6 overflow-y-auto space-y-4" id="properties-modal-list">
            <!-- Populated via JS -->
        </div>
    </div>
</div>

<!-- Global Actions Dropdown placed at the very end of the body to escape all stacking contexts -->
<div id="deeds-global-dropdown" class="deeds-action-dropdown"
  style="position: fixed; z-index: 2147483647; visibility: hidden; background: white;">
  <div class="px-4 py-2 text-[10px] font-bold text-slate-400 uppercase tracking-widest border-b border-slate-50 mb-1">
    Actions</div>
  <div id="deeds-dropdown-content"></div>
</div>
@endpush