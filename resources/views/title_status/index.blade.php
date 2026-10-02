@extends('layouts.app')

@section('styles')
<style>
    #title-status-table_wrapper .dataTables_filter,
    #title-status-table_wrapper .dataTables_length { display: none; }
    #title-status-table_wrapper .dataTables_info {
        font-size: 0.8125rem; color: rgb(100 116 139); padding: 0.75rem 1rem;
    }
    #title-status-table_wrapper .dataTables_paginate { padding: 0.5rem 1rem 0.75rem; }
    #title-status-table thead th {
        white-space: nowrap; vertical-align: middle;
        border-bottom: 2px solid rgb(226 232 240);
    }
    #title-status-table tbody td {
        white-space: nowrap; vertical-align: middle;
        border-bottom: 1px solid rgb(241 245 249);
    }
    #title-status-table tbody td:nth-child(2),
    #title-status-table tbody td:nth-child(4) {
        white-space: normal; min-width: 160px; max-width: 240px;
    }
    .swal2-container { z-index: 20000 !important; }
    .ts-file-card {
        border: 2px dashed #cbd5e1;
        border-radius: 0.75rem;
        padding: 1rem 1.25rem;
        background: #f8fafc;
        cursor: pointer;
        transition: border-color 0.2s, background 0.2s;
    }
    .ts-file-card:hover { border-color: #6366f1; background: #eef2ff; }
    .ts-file-card.selected { border-color: #10b981; background: #f0fdf4; cursor: default; }
</style>
@endsection

@section('content')
<div class="flex-1 overflow-auto bg-slate-50/60">
    @include('admin.header', [
        'PageTitle'       => 'Title Status',
        'PageDescription' => 'Manage Title Status applications.'
    ])

    <div class="py-10 bg-slate-50 min-h-screen">
        <div class="max-w-[95%] mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

            {{-- Page Header --}}
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                <div>
                    <p class="text-[11px] font-black uppercase tracking-[0.4em] text-slate-400">{{ $urlLabel }}</p>
                    <h1 class="text-3xl font-extrabold text-slate-900 mt-1">Title Status</h1>
                    <p class="text-slate-500 mt-1 text-sm">{{ number_format($records->total()) }} record(s) found</p>
                </div>
                <div class="flex items-center gap-3 flex-wrap">
                    <div class="relative">
                        <i data-lucide="search" class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-slate-400"></i>
                        <input type="text" id="ts-search" placeholder="Search applications..."
                            class="w-72 bg-white border border-slate-200 rounded-xl pl-10 pr-4 py-2 text-sm focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500">
                    </div>
                    <select id="ts-length" class="bg-white border border-slate-200 rounded-xl px-4 py-2 text-sm focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500">
                        @foreach([25, 50, 100, 150, 200] as $option)
                            <option value="{{ $option }}" @selected($limit == $option)>{{ $option }} rows</option>
                        @endforeach
                    </select>  
                    {{-- Create is only available on registries that raise title statuses (land). --}}
                    @if(!$readOnly && ($url === 'land' || $url === 'dciv'))
                    <button type="button" onclick="tsOpenTypeSelect()"
                        class="inline-flex items-center gap-2 px-5 py-2 bg-blue-600 text-white rounded-xl text-sm font-semibold shadow-sm hover:bg-blue-700 transition">
                        <i data-lucide="plus" class="w-4 h-4"></i>
                        Create Title Status
                    </button>
                    @endif
                </div>
            </div>

            {{-- Data Table --}}
            <div class="bg-white border border-slate-200 rounded-2xl shadow-sm overflow-hidden">
                <div class="overflow-x-auto">
                    <table id="title-status-table" class="w-full text-sm">
                        <thead>
                            <tr class="text-[10px] font-bold uppercase tracking-wider text-slate-500 bg-slate-50/80">
                                <th class="px-4 py-3 text-left">#</th>
                                <th class="px-4 py-3 text-left">File No</th>
                                <th class="px-4 py-3 text-left">Applicant Name</th>
                                <th class="px-4 py-3 text-left">Type</th>
                                <th class="px-4 py-3 text-left">Initiated By</th>
                                <th class="px-4 py-3 text-left">Plot No</th>
                                <th class="px-4 py-3 text-left">Date</th>
                                <th class="px-4 py-3 text-center">Status</th>
                                @unless($readOnly)
                                <th class="px-4 py-3 text-center">Actions</th>
                                @endunless
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @php
                                // Normalize free-text display: strip stray wrapping quotes/whitespace
                                // and present consistent Title Case regardless of how it was stored.
                                $tsName = function ($v) {
                                    $v = trim((string) $v);
                                    $v = trim($v, "\"'“”‘’ \t");
                                    return $v === '' ? '—' : \Illuminate\Support\Str::title($v);
                                };
                            @endphp
                            @forelse($records as $index => $record)
                            <tr class="hover:bg-slate-50/60 transition-colors" data-record='@json($record)'>
                                <td class="px-4 py-3 text-slate-500 text-xs">{{ $records->firstItem() + $index }}</td>
                                <td class="px-4 py-3 font-semibold text-slate-800">{{ $record->file_no }}</td>
                                <td class="px-4 py-3 text-slate-700">{{ $tsName($record->applicant_name) }}</td>
                                <td class="px-4 py-3 text-slate-600 text-xs">{{ $record->title_type }}</td>
                                <td class="px-4 py-3 text-slate-600 text-xs">{{ $record->initiated_by ?? $record->authority ?? '—' }}</td>
                                <td class="px-4 py-3 text-slate-600">{{ $record->plot_no ?? '—' }}</td>
                                <td class="px-4 py-3 text-slate-600 text-xs">{{ $record->created_at ? \Carbon\Carbon::parse($record->created_at)->format('d/m/Y') : '—' }}</td>
                                <td class="px-4 py-3 text-center">
                                    @if($record->status === 'approved')
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-700">Approved</span>
                                    @elseif($record->status === 'rejected')
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-red-100 text-red-700">Rejected</span>
                                    @else
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-amber-100 text-amber-700">Pending</span>
                                    @endif
                                </td>
                                @unless($readOnly)
                                <td class="px-4 py-3 text-center">
                                    <div class="ts-actions relative inline-block text-left">
                                        <button type="button" onclick="tsToggleMenu(this, event)"
                                            class="p-1.5 rounded-lg text-slate-600 hover:bg-slate-100 transition" title="Actions">
                                            <i data-lucide="more-vertical" class="w-4 h-4"></i>
                                        </button>
                                        <div class="ts-actions-menu hidden absolute right-0 z-50 mt-1 w-52 origin-top-right rounded-xl bg-white border border-slate-200 shadow-lg ring-1 ring-black/5 overflow-hidden">
                                            <button type="button" onclick="tsView(this); tsCloseAllMenus();"
                                                class="w-full flex items-center gap-2 px-3 py-2 text-sm text-slate-700 hover:bg-slate-50 transition">
                                                <i data-lucide="eye" class="w-4 h-4 text-blue-600"></i> View
                                            </button>
                                            @if($record->title_type === 'Revoke (CofO)')
                                            <button type="button" onclick="window.open('{{ route('title-status.certificate-revocation', $record->id) }}', '_blank'); tsCloseAllMenus();"
                                                class="w-full flex items-center gap-2 px-3 py-2 text-sm text-slate-700 hover:bg-red-50 transition">
                                                <i data-lucide="printer" class="w-4 h-4 text-red-600"></i> Print Certificate
                                            </button>
                                            @endif
                                            @if($record->status === 'pending')
                                            <button type="button" onclick="tsOpenEdit(this); tsCloseAllMenus();"
                                                class="w-full flex items-center gap-2 px-3 py-2 text-sm text-slate-700 hover:bg-slate-50 transition">
                                                <i data-lucide="pencil" class="w-4 h-4 text-slate-600"></i> Edit
                                            </button>
                                            <button type="button" onclick="tsApprove({{ $record->id }}); tsCloseAllMenus();"
                                                class="w-full flex items-center gap-2 px-3 py-2 text-sm text-slate-700 hover:bg-slate-50 transition">
                                                <i data-lucide="check-circle" class="w-4 h-4 text-emerald-600"></i> Approve
                                            </button>
                                            <button type="button" onclick="tsReject({{ $record->id }}); tsCloseAllMenus();"
                                                class="w-full flex items-center gap-2 px-3 py-2 text-sm text-slate-700 hover:bg-slate-50 transition">
                                                <i data-lucide="x-circle" class="w-4 h-4 text-red-500"></i> Reject
                                            </button>
                                            <button type="button" onclick="tsDelete({{ $record->id }}); tsCloseAllMenus();"
                                                class="w-full flex items-center gap-2 px-3 py-2 text-sm text-red-600 hover:bg-red-50 transition border-t border-slate-100">
                                                <i data-lucide="trash-2" class="w-4 h-4 text-red-500"></i> Delete
                                            </button>
                                            @endif
                                        </div>
                                    </div>
                                </td>
                                @endunless
                            </tr>
                            @empty
                            <tr>
                                <td colspan="{{ $readOnly ? 8 : 9 }}" class="px-4 py-12 text-center text-slate-400">
                                    <i data-lucide="inbox" class="w-8 h-8 mx-auto mb-2 opacity-40"></i>
                                    <p class="text-sm">No records found.</p>
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if($records->hasPages())
                <div class="px-4 py-3 border-t border-slate-100">
                    {{ $records->appends(request()->query())->links() }}
                </div>
                @endif
            </div>

        </div>
    </div>
</div>

{{-- Type Selection Modal --}}
<div id="ts-type-modal" class="fixed inset-0 z-[9999] hidden">
    <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" onclick="tsCloseTypeModal()"></div>
    <div class="relative flex items-center justify-center min-h-screen p-4">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-2xl overflow-hidden">
            <div class="relative px-8 pt-7 pb-6" style="background: linear-gradient(135deg, #3730a3 0%, #4f46e5 60%, #6366f1 100%);">
                <button onclick="tsCloseTypeModal()" class="absolute top-4 right-4 w-7 h-7 flex items-center justify-center rounded-full bg-white/20 hover:bg-white/30 transition text-white text-lg leading-none">&times;</button>
                <div class="flex items-center gap-3">
                    <div class="w-9 h-9 rounded-lg bg-white/20 flex items-center justify-center">
                        <i data-lucide="badge-check" class="w-5 h-5 text-white"></i>
                    </div>
                    <div>
                        <h2 class="text-lg font-bold text-white">Create Title Status</h2>
                        <p class="text-indigo-200 text-xs mt-0.5">Select the type of title status to proceed.</p>
                    </div>
                </div>
            </div>
            <div class="px-6 py-6">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <button type="button" onclick="tsSelectType('Withdrawal (Application)')"
                        class="ts-type-card text-left flex items-start gap-4 p-4 rounded-xl border-2 border-slate-100 hover:border-indigo-400 hover:bg-indigo-50/40 transition group">
                        <div class="w-11 h-11 shrink-0 rounded-xl bg-indigo-100 flex items-center justify-center group-hover:bg-indigo-200 transition">
                            <i data-lucide="undo-2" class="w-5 h-5 text-indigo-600"></i>
                        </div>
                        <div>
                            <p class="font-semibold text-slate-800 text-sm">Withdrawal (Application)</p>
                            <p class="text-xs text-slate-500 mt-0.5">New Files</p>
                        </div>
                    </button>
                    <button type="button" onclick="tsSelectType('Cancellation (RofO)')"
                        class="ts-type-card text-left flex items-start gap-4 p-4 rounded-xl border-2 border-slate-100 hover:border-amber-400 hover:bg-amber-50/40 transition group">
                        <div class="w-11 h-11 shrink-0 rounded-xl bg-amber-100 flex items-center justify-center group-hover:bg-amber-200 transition">
                            <i data-lucide="ban" class="w-5 h-5 text-amber-600"></i>
                        </div>
                        <div>
                            <p class="font-semibold text-slate-800 text-sm">Cancellation (RofO)</p>
                            <p class="text-xs text-slate-500 mt-0.5">Existing or New Files</p>
                        </div>
                    </button>
                    <button type="button" onclick="tsSelectType('Revoke (CofO)')"
                        class="ts-type-card text-left flex items-start gap-4 p-4 rounded-xl border-2 border-slate-100 hover:border-red-400 hover:bg-red-50/40 transition group">
                        <div class="w-11 h-11 shrink-0 rounded-xl bg-red-100 flex items-center justify-center group-hover:bg-red-200 transition">
                            <i data-lucide="shield-off" class="w-5 h-5 text-red-600"></i>
                        </div>
                        <div>
                            <p class="font-semibold text-slate-800 text-sm">Revocation (CofO)</p>
                            <p class="text-xs text-slate-500 mt-0.5">Existing Files</p>
                        </div>
                    </button>
                    <button type="button" onclick="tsSelectType('Litigation')"
                        class="ts-type-card text-left flex items-start gap-4 p-4 rounded-xl border-2 border-slate-100 hover:border-rose-400 hover:bg-rose-50/40 transition group">
                        <div class="w-11 h-11 shrink-0 rounded-xl bg-rose-100 flex items-center justify-center group-hover:bg-rose-200 transition">
                            <i data-lucide="scale" class="w-5 h-5 text-rose-600"></i>
                        </div>
                        <div>
                            <p class="font-semibold text-slate-800 text-sm">Litigation</p>
                            <p class="text-xs text-slate-500 mt-0.5">Existing or New Files</p>
                        </div>
                    </button>
                    <button type="button" onclick="tsSelectType('Amendment/Reconsideration (Application/RofO/CofO)')"
                        class="ts-type-card text-left flex items-start gap-4 p-4 rounded-xl border-2 border-slate-100 hover:border-emerald-400 hover:bg-emerald-50/40 transition group">
                        <div class="w-11 h-11 shrink-0 rounded-xl bg-emerald-100 flex items-center justify-center group-hover:bg-emerald-200 transition">
                            <i data-lucide="file-edit" class="w-5 h-5 text-emerald-600"></i>
                        </div>
                        <div>
                            <p class="font-semibold text-slate-800 text-sm">Amendment / Reconsideration</p>
                            <p class="text-xs text-slate-500 mt-0.5">Application / RofO / CofO</p>
                        </div>
                    </button>
                    <button type="button" onclick="tsSelectType('Surrender')"
                        class="ts-type-card text-left flex items-start gap-4 p-4 rounded-xl border-2 border-slate-100 hover:border-sky-400 hover:bg-sky-50/40 transition group">
                        <div class="w-11 h-11 shrink-0 rounded-xl bg-sky-100 flex items-center justify-center group-hover:bg-sky-200 transition">
                            <i data-lucide="flag" class="w-5 h-5 text-sky-600"></i>
                        </div>
                        <div>
                            <p class="font-semibold text-slate-800 text-sm">Surrender</p>
                            <p class="text-xs text-slate-500 mt-0.5">Voluntary relinquishment</p>
                        </div>
                    </button>
                    <button type="button" onclick="tsSelectType('Re-grant')"
                        class="ts-type-card text-left flex items-start gap-4 p-4 rounded-xl border-2 border-slate-100 hover:border-teal-400 hover:bg-teal-50/40 transition group">
                        <div class="w-11 h-11 shrink-0 rounded-xl bg-teal-100 flex items-center justify-center group-hover:bg-teal-200 transition">
                            <i data-lucide="refresh-cw" class="w-5 h-5 text-teal-600"></i>
                        </div>
                        <div>
                            <p class="font-semibold text-slate-800 text-sm">Re-grant</p>
                            <p class="text-xs text-slate-500 mt-0.5">Links to an additional file</p>
                        </div>
                    </button>
                    <button type="button" onclick="tsSelectType('Resettlement')"
                        class="ts-type-card text-left flex items-start gap-4 p-4 rounded-xl border-2 border-slate-100 hover:border-violet-400 hover:bg-violet-50/40 transition group">
                        <div class="w-11 h-11 shrink-0 rounded-xl bg-violet-100 flex items-center justify-center group-hover:bg-violet-200 transition">
                            <i data-lucide="map-pin" class="w-5 h-5 text-violet-600"></i>
                        </div>
                        <div>
                            <p class="font-semibold text-slate-800 text-sm">Resettlement</p>
                            <p class="text-xs text-slate-500 mt-0.5">Existing or New Files</p>
                        </div>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Create / Edit Modal --}}
<div id="ts-modal" class="fixed inset-0 z-[9999] hidden">
    <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" onclick="tsCloseModal()"></div>
    <div class="relative flex items-center justify-center min-h-screen p-4">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-2xl max-h-[90vh] overflow-y-auto">

            {{-- Themed Header --}}
            <div id="ts-modal-header" class="relative px-6 pt-6 pb-5" style="background: linear-gradient(135deg,#3730a3 0%,#4f46e5 60%,#6366f1 100%);">
                <div class="flex items-center justify-between">
                    <button type="button" onclick="tsGoBack()"
                        class="flex items-center gap-1.5 text-white/80 hover:text-white text-xs font-semibold transition">
                        <i data-lucide="arrow-left" class="w-4 h-4"></i> Back
                    </button>
                    <button type="button" onclick="tsCloseModal()"
                        class="w-7 h-7 flex items-center justify-center rounded-full bg-white/20 hover:bg-white/30 transition text-white text-lg leading-none">&times;</button>
                </div>
                <div class="flex items-center gap-3 mt-4">
                    <div id="ts-modal-icon-wrap" class="w-10 h-10 rounded-xl bg-white/20 flex items-center justify-center shrink-0">
                        <i id="ts-modal-icon" data-lucide="badge-check" class="w-5 h-5 text-white"></i>
                    </div>
                    <div>
                        <p id="ts-modal-type-label" class="text-white/70 text-xs font-semibold uppercase tracking-widest"></p>
                        <h2 id="ts-modal-title" class="text-lg font-bold text-white mt-0.5"></h2>
                    </div>
                </div>
            </div>

            <form id="ts-form" class="px-6 py-5 space-y-5">
                <input type="hidden" id="ts-id">
                <input type="hidden" id="ts-file_no"       name="file_no">
                <input type="hidden" id="ts-file_title"    name="file_title">
                <input type="hidden" id="ts-plot_no"       name="plot_no">
                <input type="hidden" id="ts-location"      name="location">
                <input type="hidden" id="ts-land_use"      name="land_use">
                <input type="hidden" id="ts-district"      name="district">
                <input type="hidden" id="ts-lga"           name="lga">
                <input type="hidden" id="ts-source_table"  name="source_table">
                <input type="hidden" id="ts-source_id"     name="source_id">

                {{-- File Number Search --}}
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-2">
                        File Number <span class="text-red-500">*</span>
                    </label>

                    {{-- Search card (click to open modal) --}}
                    <div id="ts-file-card" class="ts-file-card" onclick="tsOpenFileSearch()">
                        <div id="ts-file-placeholder" class="flex items-center gap-3 text-slate-400">
                            <i data-lucide="search" class="w-5 h-5 shrink-0"></i>
                            <span class="text-sm">Click to search and select a file number...</span>
                        </div>
                        <div id="ts-file-selected" class="hidden">
                            <div class="flex items-start justify-between gap-2">
                                <div class="flex items-center gap-2">
                                    <i data-lucide="file-text" class="w-4 h-4 text-emerald-600 shrink-0"></i>
                                    <span id="ts-file-no-display" class="font-bold text-slate-800 text-sm"></span>
                                </div>
                                <button type="button" onclick="tsClearFileSelection(event)"
                                    class="shrink-0 text-slate-400 hover:text-red-500 transition">
                                    <i data-lucide="x" class="w-4 h-4"></i>
                                </button>
                            </div>
                        </div>
                    </div>

                    {{-- File summary (shown after selection) --}}
                    <div id="ts-file-summary" class="hidden mt-3 bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 space-y-1.5">
                        <p class="text-[10px] font-bold uppercase tracking-widest text-slate-400 mb-2">File Summary</p>
                        <div class="grid grid-cols-2 gap-x-6 gap-y-1.5 text-xs">
                            <div><span class="text-slate-400">File Title</span><p id="ts-sum-title" class="font-semibold text-slate-700 mt-0.5"></p></div>
                            <div><span class="text-slate-400">Holder</span><p id="ts-sum-holder" class="font-semibold text-slate-700 mt-0.5"></p></div>
                            <div><span class="text-slate-400">Plot No</span><p id="ts-sum-plot" class="font-semibold text-slate-700 mt-0.5"></p></div>
                            <div><span class="text-slate-400">Location</span><p id="ts-sum-location" class="font-semibold text-slate-700 mt-0.5"></p></div>
                            <div><span class="text-slate-400">Land Use</span><p id="ts-sum-landuse" class="font-semibold text-slate-700 mt-0.5"></p></div>
                            <div><span class="text-slate-400">LGA</span><p id="ts-sum-lga" class="font-semibold text-slate-700 mt-0.5"></p></div>
                        </div>
                    </div>
                </div>

                {{-- See / Additional File Number (shown for Re-grant). Name is provisional and can be relabeled later. --}}
                <div id="ts-see-section" class="hidden">
                    <input type="hidden" id="ts-see_fileno" name="see_fileno">
                    <label class="block text-xs font-semibold text-slate-600 mb-2">
                        Initial FileNo
                    </label>
                    <div id="ts-see-card" class="ts-file-card" onclick="tsOpenSeeFileSearch()">
                        <div id="ts-see-placeholder" class="flex items-center gap-3 text-slate-400">
                            <i data-lucide="search" class="w-5 h-5 shrink-0"></i>
                            <span class="text-sm">Click to search and select the additional file number...</span>
                        </div>
                        <div id="ts-see-selected" class="hidden">
                            <div class="flex items-start justify-between gap-2">
                                <div class="flex items-center gap-2">
                                    <i data-lucide="file-symlink" class="w-4 h-4 text-teal-600 shrink-0"></i>
                                    <span id="ts-see-no-display" class="font-bold text-slate-800 text-sm"></span>
                                </div>
                                <button type="button" onclick="tsClearSeeSelection(event)"
                                    class="shrink-0 text-slate-400 hover:text-red-500 transition">
                                    <i data-lucide="x" class="w-4 h-4"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                    <p class="text-[10px] text-slate-400 mt-1">The related file number written as "See ..." on the physical file.</p>
                </div>

                {{-- Certificate of Occupancy Details (shown for Revoke / CofO actions; maps to the Certificate of Revocation) --}}
                <div id="ts-cofo-section" class="hidden border border-slate-200 rounded-xl p-4 bg-slate-50/60 space-y-4">
                    <p class="text-[10px] font-bold uppercase tracking-widest text-slate-400 flex items-center gap-1.5">
                        <i data-lucide="file-badge" class="w-3.5 h-3.5"></i>
                        Certificate of Occupancy Details
                    </p>

                    {{-- Allottee / Holder name (printed on the certificate) --}}
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 mb-1">Allottee / Holder Name</label>
                        <input type="text" id="ts-applicant_name" name="applicant_name"
                            placeholder="Name of the certificate holder..."
                            class="w-full border border-slate-200 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500">
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        {{-- Certificate of Occupancy No. --}}
                        <div>
                            <label class="block text-xs font-semibold text-slate-600 mb-1">Certificate of Occupancy No.</label>
                            <input type="text" id="ts-cofo_number" name="cofo_number"
                                placeholder="e.g. 494 -- RES-2026-2116"
                                class="w-full border border-slate-200 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500">
                            <p class="text-[10px] text-slate-400 mt-1">Leave blank to auto-build from Plot No. and File No.</p>
                        </div>

                        {{-- Dated (date of issue) --}}
                        <div>
                            <label class="block text-xs font-semibold text-slate-600 mb-1">Dated (Date of Issue)</label>
                            <input type="date" id="ts-date_of_issue" name="date_of_issue"
                                class="w-full border border-slate-200 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500">
                        </div>

                        {{-- Registered as No --}}
                        <div>
                            <label class="block text-xs font-semibold text-slate-600 mb-1">Registered as No.</label>
                            <input type="text" id="ts-title_no" name="title_no"
                                placeholder="Registration number"
                                class="w-full border border-slate-200 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500">
                        </div>

                        {{-- At Page --}}
                        <div>
                            <label class="block text-xs font-semibold text-slate-600 mb-1">At Page</label>
                            <input type="text" id="ts-reg_page" name="reg_page"
                                placeholder="Page"
                                class="w-full border border-slate-200 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500">
                        </div>

                        {{-- In Volume --}}
                        <div>
                            <label class="block text-xs font-semibold text-slate-600 mb-1">In Volume</label>
                            <input type="text" id="ts-reg_volume" name="reg_volume"
                                placeholder="Volume"
                                class="w-full border border-slate-200 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500">
                        </div>
                    </div>
                </div>

                {{-- Initiated By --}}
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Initiated By <span class="text-red-500">*</span></label>
                    <select id="ts-initiated_by" name="initiated_by" onchange="tsRefreshRemark()"
                        class="w-full border border-slate-200 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500">
                        <option value="">— Select —</option>
                        @foreach($initiatedByOptions as $opt)
                            <option value="{{ $opt }}">{{ $opt }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Reason --}}
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Reason <span class="text-red-500">*</span></label>
                    <textarea id="ts-reason" name="reason" rows="2" oninput="tsRefreshRemark()"
                        placeholder="Reason for this title status action..."
                        class="w-full border border-slate-200 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500"></textarea>
                </div>

                {{-- Auto-generated Remark --}}
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Remark / Comment</label>
                    <textarea id="ts-remark" name="remark" rows="3"
                        class="w-full border border-slate-200 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500"></textarea>
                </div>

                <div class="flex justify-end gap-3 pt-2">
                    <button type="button" onclick="tsGoBack()"
                        class="px-5 py-2 rounded-xl border border-slate-200 text-sm font-semibold text-slate-600 hover:bg-slate-50 transition">
                        Cancel
                    </button>
                    <button type="button" onclick="tsSubmit()"
                        class="px-5 py-2 rounded-xl bg-blue-600 text-white text-sm font-semibold shadow-sm hover:bg-blue-700 transition">
                        Save
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- View Modal --}}
<div id="ts-view-modal" class="fixed inset-0 z-[9999] hidden">
    <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" onclick="tsCloseViewModal()"></div>
    <div class="relative flex items-center justify-center min-h-screen p-4">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-2xl max-h-[90vh] overflow-y-auto">
            <div class="flex items-center justify-between px-6 py-4 border-b border-slate-100">
                <h2 class="text-lg font-bold text-slate-800">Application Details</h2>
                <button onclick="tsCloseViewModal()" class="p-1.5 rounded-lg hover:bg-slate-100 transition">
                    <i data-lucide="x" class="w-5 h-5 text-slate-500"></i>
                </button>
            </div>
            <div id="ts-view-body" class="px-6 py-5 space-y-3 text-sm text-slate-700"></div>
        </div>
    </div>
</div>

@include('components.global-fileno-modal')
@endsection

@push('scripts')
<script src="{{ asset('js/global-fileno-modal.js') }}"></script>
<script>
    const TS_URL             = '{{ $url }}';
    const TS_STORE_URL       = '{{ route('title-status.store') }}';
    const TS_BASE_URL        = '{{ url('title-status') }}';
    const TS_REMARK_URL      = '{{ route('title-status.generate-remark') }}';
    const TS_FILE_INFO_URL   = '{{ route('title-status.file-info') }}';
    const TS_CSRF            = '{{ csrf_token() }}';
    const TS_INITIATED_BY_BY_TYPE = @json($initiatedByByType);
    const TS_INITIATED_BY_DEFAULT = { 'Revoke (CofO)': 'Ministry' };
</script>
<script src="{{ asset('js/title_status.js') }}?v={{ filemtime(public_path('js/title_status.js')) }}"></script>
@endpush
