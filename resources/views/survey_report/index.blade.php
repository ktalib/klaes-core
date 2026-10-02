@extends('layouts.app')

@section('page-title')
    {{ __('Lands 12 - Request for Survey Report') }}
@endsection

@section('content')
    <div class="flex-1 overflow-auto bg-slate-50/60">
        @include('admin.header', [
            'PageTitle' => 'Lands 12 - Request for Survey Report',
            'PageDescription' => 'Create, manage, and print Lands 12 survey report requests.'
        ])

        <div class="py-8 bg-slate-50 min-h-screen">
            <div class="max-w-[95%] mx-auto px-4 sm:px-6 lg:px-8">
                {{-- Page Header --}}
                <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-8">
                    <div>
                        <h1 class="text-2xl font-extrabold text-slate-900 tracking-tight">Survey Report Requests</h1>
                        <p class="text-slate-500 text-sm mt-1">Manage Lands 12 requests and print templates.</p>
                    </div>
                    <button type="button"
                        class="customModal inline-flex items-center gap-2 px-5 py-2.5 bg-emerald-600 text-white rounded-xl font-bold hover:bg-emerald-700 transition-all shadow-sm hover:shadow-md active:scale-95"
                        data-size="lg"
                        data-title="Create Survey Report Request"
                        data-url="{{ route('survey-report.create') }}?url={{ $urlContext ?? '' }}"
                        @if(($urlContext ?? '') === 'cadastral') style="display:none" @endif>
                        <i data-lucide="plus" class="h-4 w-4"></i>
                        {{ in_array(($urlContext ?? ''), ['lands', 'lands12']) ? 'Create' : 'Generate' }}
                    </button>
                </div>

                {{-- Statistics Cards --}}
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                    <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm hover:shadow-md transition-all group overflow-hidden relative">
                        <div class="absolute -right-4 -bottom-4 opacity-[0.03] group-hover:scale-110 transition-transform duration-500">
                            <i data-lucide="clipboard-list" class="h-28 w-28 text-emerald-600"></i>
                        </div>
                        <div class="flex items-center gap-4 relative z-10">
                            <div class="p-3 bg-emerald-50 text-emerald-600 rounded-2xl border border-emerald-100 shadow-sm">
                                <i data-lucide="clipboard-list" class="h-5 w-5"></i>
                            </div>
                            <div>
                                <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Total Requests</p>
                                <h3 class="text-2xl font-black text-slate-800 tracking-tight">{{ $requests->count() }}</h3>
                            </div>
                        </div>
                    </div>
                    <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm hover:shadow-md transition-all group overflow-hidden relative">
                        <div class="absolute -right-4 -bottom-4 opacity-[0.03] group-hover:scale-110 transition-transform duration-500">
                            <i data-lucide="check-circle" class="h-28 w-28 text-blue-600"></i>
                        </div>
                        <div class="flex items-center gap-4 relative z-10">
                            <div class="p-3 bg-blue-50 text-blue-600 rounded-2xl border border-blue-100 shadow-sm">
                                <i data-lucide="check-circle" class="h-5 w-5"></i>
                            </div>
                            <div>
                                <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Generated</p>
                                <h3 class="text-2xl font-black text-slate-800 tracking-tight">{{ $requests->where('status', 'Generated')->count() }}</h3>
                            </div>
                        </div>
                    </div>
                    <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm hover:shadow-md transition-all group overflow-hidden relative">
                        <div class="absolute -right-4 -bottom-4 opacity-[0.03] group-hover:scale-110 transition-transform duration-500">
                            <i data-lucide="calendar" class="h-28 w-28 text-indigo-600"></i>
                        </div>
                        <div class="flex items-center gap-4 relative z-10">
                            <div class="p-3 bg-indigo-50 text-indigo-600 rounded-2xl border border-indigo-100 shadow-sm">
                                <i data-lucide="calendar" class="h-5 w-5"></i>
                            </div>
                            <div>
                                <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Created Today</p>
                                <h3 class="text-2xl font-black text-slate-800 tracking-tight">{{ $requests->filter(fn($r) => $r->created_at && $r->created_at->isToday())->count() }}</h3>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Records Table --}}
                <div class="bg-white rounded-2xl shadow-sm border border-slate-200">
                    <div class="bg-slate-50 px-6 py-4 border-b border-slate-200 flex items-center gap-2">
                        <i data-lucide="list" class="h-4 w-4 text-emerald-600"></i>
                        <h3 class="font-bold text-slate-800">All Requests</h3>
                    </div>
                    <div class="p-4 overflow-visible">
                        <table id="survey-report-table" class="w-full text-left border-collapse display" style="width:100%">
                            <thead>
                                <tr class="bg-slate-50/50 border-b border-slate-200 text-[10px] font-black text-slate-500 uppercase tracking-widest">
                                    <th class="px-5 py-4 whitespace-nowrap">File Number</th>
                                    <th class="px-5 py-4 whitespace-nowrap">File Title</th>
                                    @if(($urlContext ?? '') !== 'lands')
                                    <th class="px-5 py-4 whitespace-nowrap">ROFO No</th>
                                    <th class="px-5 py-4 whitespace-nowrap">Item G No</th>
                                    @endif
                                    @if(($urlContext ?? '') !== 'lands')
                                    <th class="px-5 py-4 whitespace-nowrap">Director Name</th>
                                    @endif
                                    <th class="px-5 py-4 text-center whitespace-nowrap">Status</th>
                                    <th class="px-5 py-4 text-center whitespace-nowrap">Created At</th>
                                    <th class="px-5 py-4 text-right whitespace-nowrap">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 text-sm text-slate-600">
                                @foreach($requests as $requestRecord)
                                    <tr class="hover:bg-slate-50/50 transition-colors">
                                        <td class="px-5 py-4 whitespace-nowrap">
                                            @if($requestRecord->file_number)
                                                <span class="inline-flex items-center gap-1.5 rounded-lg bg-blue-50 px-2.5 py-1 text-xs font-bold text-blue-700 border border-blue-100">
                                                    <i data-lucide="file" class="h-3 w-3"></i>
                                                    {{ $requestRecord->file_number }}
                                                </span>
                                            @else
                                                <span class="text-slate-300 text-xs">—</span>
                                            @endif
                                        </td>
                                        <td class="px-5 py-4 whitespace-nowrap font-medium text-slate-700">{{ \Illuminate\Support\Str::limit($requestRecord->file_title, 40) ?? '—' }}</td>
                                        @if(($urlContext ?? '') !== 'lands')
                                        <td class="px-5 py-4 whitespace-nowrap font-mono text-xs font-bold text-slate-600">{{ $requestRecord->rofo_no ?? '—' }}</td>
                                        <td class="px-5 py-4 whitespace-nowrap font-mono text-xs font-bold text-slate-600">{{ $requestRecord->item_g_no ?? '—' }}</td>
                                        @endif
                                        @if(($urlContext ?? '') !== 'lands')
                                        <td class="px-5 py-4 whitespace-nowrap font-medium text-slate-700">{{ \Illuminate\Support\Str::limit($requestRecord->director_name, 30) ?? '—' }}</td>
                                        @endif
                                        <td class="px-5 py-4 text-center whitespace-nowrap">
                                            @php
                                                $status = $requestRecord->status ?? 'Draft';
                                                $statusLabel = ($status === 'Sent to Cadastral' && ($urlContext ?? '') === 'cadastral') ? 'Sent from Land' : $status;
                                            @endphp
                                            @if($status === 'Generated')
                                                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-[9px] font-black bg-emerald-50 text-emerald-700 border border-emerald-100 uppercase tracking-widest">
                                                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 mr-1.5"></span>
                                                    {{ $statusLabel }}
                                                </span>
                                            @elseif($status === 'Completed')
                                                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-[9px] font-black bg-blue-50 text-blue-700 border border-blue-100 uppercase tracking-widest">
                                                    <span class="w-1.5 h-1.5 rounded-full bg-blue-500 mr-1.5"></span>
                                                    {{ $statusLabel }}
                                                </span>
                                            @else
                                                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-[9px] font-black bg-amber-50 text-amber-700 border border-amber-100 uppercase tracking-widest">
                                                    <span class="w-1.5 h-1.5 rounded-full bg-amber-500 mr-1.5"></span>
                                                    {{ $statusLabel }}
                                                </span>
                                            @endif
                                        </td>
                                        <td class="px-5 py-4 text-center whitespace-nowrap">
                                            <span class="px-2 py-1 bg-slate-50 rounded-md border border-slate-100 font-bold text-slate-500 text-[11px]">
                                                {{ $requestRecord->created_at ? $requestRecord->created_at->format('d M, Y') : '—' }}
                                            </span>
                                        </td>
                                        <td class="px-5 py-4 text-right whitespace-nowrap">
                                            @php
                                                $isCompleted = ($requestRecord->status ?? '') === 'Completed';
                                            @endphp
                                            <div class="relative inline-block sr-action-menu">
                                                <button type="button"
                                                    onclick="toggleSrMenu(this)"
                                                    class="p-1.5 hover:bg-slate-100 rounded-lg transition-colors">
                                                    <i data-lucide="more-vertical" class="h-4 w-4 text-slate-400"></i>
                                                </button>
                                                <div class="sr-menu hidden absolute right-0 top-full mt-1 w-40 bg-white border border-slate-200 rounded-xl shadow-xl z-50 overflow-hidden py-1">
                                                    <button type="button"
                                                        class="customModal w-full text-left px-4 py-2 text-xs font-bold text-slate-700 hover:bg-blue-50 hover:text-blue-600 flex items-center gap-2 transition-colors"
                                                        data-size="lg"
                                                        data-title="View Survey Report Request"
                                                        data-url="{{ route('survey-report.show', $requestRecord->id) }}?url={{ $urlContext ?? '' }}">
                                                        <i data-lucide="eye" class="h-3.5 w-3.5"></i>
                                                        View
                                                    </button>
                                                    <button type="button"
                                                        class="{{ $isCompleted
                                                            ? 'w-full text-left px-4 py-2 text-xs font-bold text-slate-300 bg-slate-50 cursor-not-allowed flex items-center gap-2'
                                                            : 'customModal w-full text-left px-4 py-2 text-xs font-bold text-slate-700 hover:bg-blue-50 hover:text-blue-600 flex items-center gap-2 transition-colors' }}"
                                                        data-size="lg"
                                                        data-title="{{ ($urlContext ?? '') === 'cadastral' ? 'Complete Land12' : 'Edit Survey Report Request' }}"
                                                        data-url="{{ route('survey-report.edit', $requestRecord->id) }}?url={{ $urlContext ?? '' }}"
                                                        @disabled($isCompleted)
                                                        aria-disabled="{{ $isCompleted ? 'true' : 'false' }}"
                                                        title="{{ $isCompleted ? 'Completed requests cannot be modified.' : '' }}">
                                                        <i data-lucide="{{ ($urlContext ?? '') === 'cadastral' ? 'check-circle' : 'edit-3' }}" class="h-3.5 w-3.5"></i>
                                                        {{ ($urlContext ?? '') === 'cadastral' ? 'Complete Land12' : 'Edit' }}
                                                    </button>
                                                    @if(in_array(($urlContext ?? ''), ['lands', 'lands12']))
                                                    <a href="{{ route('survey-report.print', $requestRecord->id) }}"
                                                        target="_blank"
                                                        class="w-full text-left px-4 py-2 text-xs font-bold text-slate-700 hover:bg-emerald-50 hover:text-emerald-600 flex items-center gap-2 transition-colors">
                                                        <i data-lucide="printer" class="h-3.5 w-3.5"></i>
                                                        Print
                                                    </a>
                                                    @endif
                                                    <div class="border-t border-slate-100 my-1"></div>
                                                    <button type="button"
                                                        class="{{ $isCompleted
                                                            ? 'w-full text-left px-4 py-2 text-xs font-bold text-slate-300 bg-slate-50 cursor-not-allowed flex items-center gap-2'
                                                            : 'js-delete-survey-report w-full text-left px-4 py-2 text-xs font-bold text-red-600 hover:bg-red-50 flex items-center gap-2 transition-colors' }}"
                                                        @if(!$isCompleted)
                                                            data-delete-url="{{ route('survey-report.destroy', $requestRecord->id) }}"
                                                        @endif
                                                        @disabled($isCompleted)
                                                        aria-disabled="{{ $isCompleted ? 'true' : 'false' }}"
                                                        title="{{ $isCompleted ? 'Completed requests cannot be deleted.' : '' }}">
                                                        <i data-lucide="trash-2" class="h-3.5 w-3.5"></i>
                                                        Delete
                                                    </button>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        @include('admin.footer')
    </div>

    {{-- Global File Number Selector Modal --}}
    @include('components.global-fileno-modal')
    <script src="{{ asset('js/global-fileno-modal.js') }}"></script>
@endsection

@push('styles')
<style>
    /* Keep row action dropdowns outside table bounds */
    .sr-action-menu {
        position: relative;
        z-index: 30;
    }
    .sr-menu {
        z-index: 9999;
    }
    #survey-report-table_wrapper,
    #survey-report-table_wrapper .dataTables_scroll,
    #survey-report-table_wrapper .dataTables_scrollBody,
    #survey-report-table_wrapper .dataTables_scrollHead,
    #survey-report-table_wrapper .dataTables_scrollHeadInner {
        overflow: visible !important;
    }

    /* Survey Report DataTable Custom Styling */
    #survey-report-table_wrapper .dataTables_filter input {
        padding: 0.625rem 1rem 0.625rem 2.5rem;
        background-color: white;
        border: 1px solid rgb(226 232 240);
        border-radius: 0.75rem;
        font-size: 0.875rem;
        outline: none;
        box-shadow: 0 1px 2px 0 rgb(0 0 0 / 0.05);
        transition: all 0.2s;
    }
    #survey-report-table_wrapper .dataTables_filter input:focus {
        border-color: rgb(59 130 246);
        box-shadow: 0 0 0 3px rgb(59 130 246 / 0.1);
    }
    #survey-report-table_wrapper .dataTables_filter label {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        font-size: 0;
    }
    #survey-report-table_wrapper .dataTables_length select {
        background-color: white;
        border: 1px solid rgb(226 232 240);
        border-radius: 0.5rem;
        padding: 0.5rem 2rem 0.5rem 0.75rem;
        font-size: 0.875rem;
        outline: none;
        box-shadow: 0 1px 2px 0 rgb(0 0 0 / 0.05);
    }
    #survey-report-table_wrapper .dataTables_length label {
        font-size: 0.7rem;
        font-weight: 700;
        color: rgb(100 116 139);
        text-transform: uppercase;
        letter-spacing: 0.05em;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }
    #survey-report-table_wrapper .dataTables_info {
        font-size: 0.7rem;
        font-weight: 700;
        color: rgb(148 163 184);
        text-transform: uppercase;
        letter-spacing: 0.05em;
        padding-top: 0.5rem;
    }
    #survey-report-table_wrapper .dataTables_paginate {
        display: flex;
        align-items: center;
        gap: 0.25rem;
        padding-top: 0.5rem;
    }
    #survey-report-table_wrapper .dataTables_paginate .paginate_button {
        padding: 0.375rem 0.75rem;
        font-size: 0.75rem;
        font-weight: 700;
        color: rgb(100 116 139);
        border-radius: 0.5rem;
        cursor: pointer;
        transition: all 0.15s;
        border: none !important;
        background: transparent !important;
    }
    #survey-report-table_wrapper .dataTables_paginate .paginate_button:hover:not(.disabled):not(.current) {
        background: rgb(239 246 255) !important;
        color: rgb(37 99 235) !important;
    }
    #survey-report-table_wrapper .dataTables_paginate .paginate_button.current {
        background: rgb(37 99 235) !important;
        color: white !important;
        border-radius: 0.5rem;
        border: none !important;
    }
    #survey-report-table_wrapper .dataTables_paginate .paginate_button.disabled {
        color: rgb(203 213 225) !important;
        cursor: not-allowed;
        opacity: 0.5;
    }
    #survey-report-table_wrapper .dataTables_processing {
        background: rgba(255,255,255,0.9) !important;
        border: 1px solid rgb(226 232 240) !important;
        border-radius: 0.75rem;
        box-shadow: 0 10px 15px -3px rgb(0 0 0 / 0.1);
    }
    #survey-report-table tbody tr:hover {
        background-color: rgb(248 250 252) !important;
    }
    #survey-report-table tbody td {
        padding: 1rem 1.25rem;
    }
    table.dataTable.no-footer {
        border-bottom: none !important;
    }
    table.dataTable thead th {
        border-bottom: 1px solid rgb(226 232 240) !important;
    }
    .dt-buttons {
        display: none !important;
    }
</style>
@endpush

@push('scripts')
    <script src="{{ asset('js/survey-report.js') }}?v={{ time() }}"></script>
    <script>
        // Toggle action menu for table rows
        function toggleSrMenu(btn) {
            var menu = btn.parentElement.querySelector('.sr-menu');
            document.querySelectorAll('.sr-action-menu .sr-menu').forEach(function(m) {
                if (m !== menu) m.classList.add('hidden');
            });
            menu.classList.toggle('hidden');
        }
        document.addEventListener('click', function(e) {
            if (!e.target.closest('.sr-action-menu')) {
                document.querySelectorAll('.sr-action-menu .sr-menu').forEach(function(m) {
                    m.classList.add('hidden');
                });
            }
        });

        document.addEventListener('DOMContentLoaded', function() {

            // Initialize DataTable with custom config (override advance-datatable defaults)
            if ($.fn.DataTable.isDataTable('#survey-report-table')) {
                $('#survey-report-table').DataTable().destroy();
            }
            $('#survey-report-table').DataTable({
                pageLength: 20,
                order: [[6, 'desc']],
                scrollX: false,
                dom: '<"flex flex-col sm:flex-row justify-between items-center gap-3 mb-4"lf>rt<"flex flex-col sm:flex-row justify-between items-center gap-3 mt-4 px-2"ip>',
                language: {
                    emptyTable: '<div class="py-12 text-center"><div class="text-slate-300 mb-3"><svg class="mx-auto h-12 w-12" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg></div><p class="text-slate-400 text-sm font-medium">No requests found</p><p class="text-slate-300 text-xs mt-1">Click "New Request" to create your first survey report request.</p></div>',
                    info: 'Showing _START_ to _END_ of _TOTAL_ requests',
                    infoFiltered: '(filtered from _MAX_ total)',
                    lengthMenu: 'Show _MENU_ per page',
                    search: '',
                    searchPlaceholder: 'Search requests...',
                    paginate: {
                        first: '«',
                        last: '»',
                        next: '›',
                        previous: '‹'
                    }
                },
                columnDefs: [
                    { orderable: false, targets: [7] }
                ]
            });
        });
    </script>
