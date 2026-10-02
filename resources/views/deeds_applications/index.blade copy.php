@php use App\Models\ConsentApplication; @endphp
@extends('layouts.app')

@section('page-title', 'Applications for Consent')

@push('css')
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
    display: block;
    width: 100%;
    text-align: left;
    padding: 0.5rem 1rem;
    font-size: 0.75rem;
    color: rgb(100 116 139);
    transition: all 0.15s;
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
@endpush

@section('content')
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

    {{-- Stats cards --}}
    <div class="grid grid-cols-2 lg:grid-cols-5 gap-3 md:gap-4">
      <div class="bg-white p-4 md:p-5 rounded-2xl border border-slate-100 shadow-sm">
        <p class="text-[10px] md:text-xs font-semibold text-slate-500 uppercase tracking-wider">Total Applications</p>
        <p class="text-2xl md:text-3xl font-bold text-slate-900 mt-1.5">{{ $applications->count() }}</p>
      </div>
      <div class="bg-white p-4 md:p-5 rounded-2xl border border-slate-100 shadow-sm">
        <p class="text-[10px] md:text-xs font-semibold text-blue-600 uppercase tracking-wider">Assignment</p>
        <p class="text-2xl md:text-3xl font-bold text-blue-700 mt-1.5">{{ $applications->where('consent_type',
          'Assignment')->count() }}</p>
      </div>
      <div class="bg-white p-4 md:p-5 rounded-2xl border border-slate-100 shadow-sm">
        <p class="text-[10px] md:text-xs font-semibold text-emerald-600 uppercase tracking-wider">Gift</p>
        <p class="text-2xl md:text-3xl font-bold text-emerald-700 mt-1.5">{{ $applications->where('consent_type',
          'Gift')->count() }}</p>
      </div>
      <div class="bg-white p-4 md:p-5 rounded-2xl border border-slate-100 shadow-sm">
        <p class="text-[10px] md:text-xs font-semibold text-amber-600 uppercase tracking-wider">Mortgage</p>
        <p class="text-2xl md:text-3xl font-bold text-amber-700 mt-1.5">{{ $applications->where('consent_type',
          'Mortgage')->count() }}</p>
      </div>
      <div class="bg-white p-4 md:p-5 rounded-2xl border border-slate-100 shadow-sm">
        <p class="text-[10px] md:text-xs font-semibold text-purple-600 uppercase tracking-wider">ST Assignment</p>
        <p class="text-2xl md:text-3xl font-bold text-purple-700 mt-1.5">{{ $applications->where('consent_type', 'ST
          Assignment')->count() }}</p>
      </div>
    </div>

    <!-- Data Table -->
    <div class="bg-white rounded-3xl border border-slate-100 shadow-sm overflow-hidden">
      <div class="overflow-x-auto text-sm">
        <table class="w-full text-left border-collapse" id="consent-table">
          <thead class="bg-slate-50 border-b border-slate-100">
            <tr>
              <th class="px-6 py-4 font-semibold text-slate-700 uppercase tracking-wider text-[11px]">S/N</th>
              <th class="px-6 py-4 font-semibold text-slate-700 uppercase tracking-wider text-[11px] whitespace-nowrap">
                File Number</th>
              <th class="px-6 py-4 font-semibold text-slate-700 uppercase tracking-wider text-[11px]">Consent Type</th>
              <th class="px-6 py-4 font-semibold text-slate-700 uppercase tracking-wider text-[11px]">Party 1</th>
              <th class="px-6 py-4 font-semibold text-slate-700 uppercase tracking-wider text-[11px]">Party 2</th>
              <th class="px-6 py-4 font-semibold text-slate-700 uppercase tracking-wider text-[11px]">Party 3</th>
              <th class="px-6 py-4 font-semibold text-slate-700 uppercase tracking-wider text-[11px]">Generated By</th>
              <th class="px-6 py-4 font-semibold text-slate-700 uppercase tracking-wider text-[11px]">App. Date</th>
              <th class="px-6 py-4 font-semibold text-slate-700 uppercase tracking-wider text-[11px]">Time Generated
              </th>
              <th class="px-6 py-4 font-semibold text-slate-700 uppercase tracking-wider text-[11px]">Date Generated
              </th>
              <th class="px-6 py-4 font-semibold text-slate-700 uppercase tracking-wider text-[11px]">Prints</th>
              <th class="px-6 py-4 font-semibold text-slate-700 uppercase tracking-wider text-[11px] text-center">
                Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100">
            @forelse($applications as $application)
            <tr class="hover:bg-slate-50/50 transition duration-200">
              <td class="px-6 py-4 text-slate-600 font-bold italic">{{ $loop->iteration }}</td>
              <td class="px-6 py-4 text-slate-900 font-bold whitespace-nowrap">
                {{ $application->file_number }}
              </td>
              <td class="px-6 py-4">
                @php
                $badgeClass = match($application->consent_type) {
                'Assignment' => 'bg-blue-50 text-blue-700 border-blue-100',
                'Gift' => 'bg-emerald-50 text-emerald-700 border-emerald-100',
                'Mortgage' => 'bg-amber-50 text-amber-700 border-amber-100',
                'ST Assignment' => 'bg-purple-50 text-purple-700 border-purple-100',
                'Sublease' => 'bg-pink-50 text-pink-700 border-pink-100',
                'Devolution' => 'bg-indigo-50 text-indigo-700 border-indigo-100',
                default => 'bg-slate-50 text-slate-700 border-slate-100'
                };
                @endphp
                <span
                  class="px-2.5 py-1 rounded-full text-[11px] font-bold uppercase border whitespace-nowrap inline-block {{ $badgeClass }}">
                  {{ $application->consent_type }}
                </span>
              </td>
              <td class="px-6 py-4 text-slate-900 font-semibold uppercase">{{ $application->applicant_name }}</td>
              <td class="px-6 py-4 text-slate-900 font-semibold uppercase">{{ $application->party_name }}</td>
              @php
              $extraApplicants = collect($application->additional_applicants ?? [])->pluck('name')->filter()->values();
              $extraParties = collect($application->additional_parties ?? [])->pluck('name')->filter()->values();
              $otherPartyNames = $extraApplicants->merge($extraParties)->values();
              @endphp
              <td class="px-6 py-4 text-slate-900 font-semibold uppercase">{{ $otherPartyNames->get(0, 'N/A') }}</td>
              <td class="px-6 py-4">
                <div class="flex items-center gap-2">
                  <div class="w-7 h-7 rounded-lg bg-slate-100 flex items-center justify-center text-slate-500">
                    <i data-lucide="user-check" class="h-3.5 w-3.5"></i>
                  </div>
                  <span class="text-slate-600 text-sm font-medium uppercase">{{ $application->created_by ?:
                    ($application->user ? $application->user->first_name . ' ' . $application->user->last_name :
                    'System') }}</span>
                </div>
              </td>
              <td class="px-6 py-4 text-slate-600 font-medium whitespace-nowrap text-xs">
                {{ ($application->application_submitted_date ?? $application->application_date) ?
                \Carbon\Carbon::parse($application->application_submitted_date ??
                $application->application_date)->format('M d, Y') : 'N/A' }}
              </td>
              <td class="px-6 py-4 text-slate-600 font-medium whitespace-nowrap text-xs">
                {{ $application->created_at->format('h:i A') }}
              </td>
              <td class="px-6 py-4 text-slate-600 font-medium whitespace-nowrap text-xs">
                {{ $application->created_at->format('M d, Y') }}
              </td>
              <td class="px-6 py-4">
                <div class="flex items-center gap-2">
                  <span
                    class="w-2 h-2 rounded-full {{ $application->print_count > 0 ? 'bg-emerald-500' : 'bg-slate-300' }}"></span>
                  <span class="font-bold text-slate-700">{{ $application->print_count }}</span>
                </div>
              </td>
              <td class="px-6 py-4">
                <div class="flex items-center justify-center">
                  <button type="button"
                    class="deeds-action-trigger p-2 text-slate-400 hover:text-slate-600 hover:bg-slate-100 rounded-xl transition duration-200"
                    data-app="{{ json_encode($application) }}"
                    data-is-st="{{ (isset($application->is_st_assignment) && $application->is_st_assignment) ? 'true' : 'false' }}">
                    <i data-lucide="more-horizontal" class="h-5 w-5"></i>
                  </button>
                </div>
              </td>

            </tr>
            @empty
            <tr>
              <td colspan="12" class="px-6 py-12 text-center text-slate-500 italic">
                No applications found. Click "Generate" to create your first one.
              </td>
            </tr>
            @endforelse
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
<script src="{{ asset('js/consent_applications.js') }}"></script>
<script>
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
                  <td class="px-4 py-3 text-slate-900 font-bold">${fileNo}</td>
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

    // DataTable Initialization
    if (typeof jQuery !== 'undefined' && jQuery.fn.DataTable) {
      const $table = jQuery('#consent-table');
      if (!jQuery.fn.DataTable.isDataTable('#consent-table')) {
        const dt = $table.DataTable({
          pageLength: 10,
          lengthMenu: [[10, 25, 50, -1], [10, 25, 50, "All"]],
          ordering: true,
          autoWidth: false,
          language: {
            search: "",
            searchPlaceholder: "Search...",
          },
          initComplete: function () {
            const api = this.api();

            // Find or create a wrapper for the custom filter next to the search box
            const filterWrapper = jQuery('<div class="inline-flex items-center ml-4"></div>')
              .appendTo(jQuery('#consent-table_filter'));

            const select = jQuery('<select class="border border-slate-200 rounded-lg text-sm px-3 py-1.5 outline-none focus:border-blue-500"><option value="">All Consent Types</option></select>')
              .appendTo(filterWrapper)
              .on('change', function () {
                var val = jQuery.fn.dataTable.util.escapeRegex(jQuery(this).val());
                api.column(2).search(val ? '^' + val + '$' : '', true, false).draw();
              });

            // Populate the select with unique values from the Consent Type column
            api.column(2).data().unique().sort().each(function (d, j) {
              const tempDiv = document.createElement('div');
              tempDiv.innerHTML = d;
              const text = tempDiv.textContent || tempDiv.innerText || "";
              if (text.trim() !== "") {
                select.append('<option value="' + text.trim() + '">' + text.trim() + '</option>');
              }
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
          const canEdit = app.print_count == 0;
          const canPrint = app.print_count < 2;

          if (canEdit) {
            html += `
                          <button type="button" class="edit-consent-btn flex items-center gap-3 w-full px-4 py-2.5 text-xs text-amber-600 hover:bg-amber-50 transition font-bold"
                              data-id="${app.id}" data-app='${JSON.stringify(app)}'>
                              <i data-lucide="edit-3" class="h-4 w-4"></i>
                              <span>Edit Application</span>
                          </button>
                      `;
          } else {
            html += `
                          <div class="flex items-center gap-3 w-full px-4 py-2.5 text-xs text-slate-300 cursor-not-allowed font-bold" title="Cannot edit after printing">
                              <i data-lucide="lock" class="h-4 w-4"></i>
                              <span>Edit</span>
                          </div>
                      `;
          }

          if (canPrint) {
            html += `
                          <a href="/consent-applications/${app.id}" target="_blank" class="flex items-center gap-3 px-4 py-2.5 text-xs text-blue-600 hover:bg-blue-50 transition font-bold">
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
                              <td class="px-4 py-3 text-slate-900 font-bold">${fileNo}</td>
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


  });
</script>

<!-- Global Actions Dropdown placed at the very end of the body to escape all stacking contexts -->
<div id="deeds-global-dropdown" class="deeds-action-dropdown"
  style="position: fixed; z-index: 2147483647; visibility: hidden; background: white;">
  <div class="px-4 py-2 text-[10px] font-bold text-slate-400 uppercase tracking-widest border-b border-slate-50 mb-1">
    Actions</div>
  <div id="deeds-dropdown-content"></div>
</div>
@endpush