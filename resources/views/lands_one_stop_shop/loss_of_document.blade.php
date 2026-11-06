@extends('layouts.app')

@section('styles')
<style>
    #lod-table_wrapper .dataTables_filter,
    #lod-table_wrapper .dataTables_length { display: none; }

    #lod-table_wrapper .dataTables_info {
        font-size: 0.8125rem;
        color: rgb(100 116 139);
        padding: 0.75rem 1rem;
    }

    #lod-table_wrapper .dataTables_paginate {
        padding: 0.5rem 1rem 0.75rem;
    }

    #lod-table thead th {
        white-space: nowrap;
        vertical-align: middle;
        border-bottom: 2px solid rgb(226 232 240);
    }

    #lod-table tbody td {
        white-space: nowrap;
        vertical-align: middle;
        border-bottom: 1px solid rgb(241 245 249);
    }

    #lod-table tbody td:nth-child(2),
    #lod-table tbody td:nth-child(10) {
        white-space: normal;
        min-width: 180px;
        max-width: 260px;
    }

    /* SweetAlert z-index fix — keep above modal */
    .swal2-container { z-index: 20000 !important; }
</style>
@endsection

@section('content')
<div class="flex-1 overflow-auto bg-slate-50/60">
    @include('admin.header', [
        'PageTitle' => 'Loss of Document (Land FC)',
        'PageDescription' => 'Capture and manage Land FC Loss of Document applications.'
    ])

    <div class="py-10 bg-slate-50 min-h-screen">
        <div class="max-w-[95%] mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            {{-- Page Header --}}
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                <div>
                    <p class="text-[11px] font-black uppercase tracking-[0.4em] text-slate-400">Land FC</p>
                    <h1 class="text-3xl font-extrabold text-slate-900 mt-1">Loss of Document (Land FC)</h1>
                    <p class="text-slate-500 mt-1 text-sm">{{ number_format($records->count()) }} record(s) loaded</p>
                </div>
                <div class="flex items-center gap-3">
                    <div class="relative">
                        <i data-lucide="search" class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-slate-400"></i>
                        <input type="text" id="lod-search" placeholder="Search applications..."
                            class="w-72 bg-white border border-slate-200 rounded-xl pl-10 pr-4 py-2 text-sm focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500">
                    </div>
                    <select id="lod-length" class="bg-white border border-slate-200 rounded-xl px-4 py-2 text-sm focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500">
                        @foreach([25, 50, 100, 150, 200] as $option)
                            <option value="{{ $option }}" @selected($limit == $option)>{{ $option }} rows</option>
                        @endforeach
                    </select>
                    <button type="button" onclick="lodOpenCreate()"
                        class="inline-flex items-center gap-2 px-5 py-2 bg-blue-600 text-white rounded-xl text-sm font-semibold shadow-sm hover:bg-blue-700 transition">
                        <i data-lucide="plus" class="w-4 h-4"></i>
                        New Application
                    </button>
                </div>
            </div>

            {{-- Data Table --}}
            <div class="bg-white border border-slate-200 rounded-2xl shadow-sm overflow-hidden">
                <div class="overflow-x-auto">
                    <table id="lod-table" class="w-full text-sm" style="min-width:1400px">
                        <thead>
                            <tr class="text-[10px] font-bold uppercase tracking-wider text-slate-500 bg-slate-50/80">
                                <th class="px-4 py-3 text-left">S/N</th>
                                <th class="px-4 py-3 text-left">Applicant</th>
                                <th class="px-4 py-3 text-left">Type</th>
                                <th class="px-4 py-3 text-left">File No</th>
                                <th class="px-4 py-3 text-left">OP Serial</th>
                                <th class="px-4 py-3 text-left">Land Use</th>
                                <th class="px-4 py-3 text-left">Plot No</th>
                                <th class="px-4 py-3 text-left">District</th>
                                <th class="px-4 py-3 text-left">LGA</th>
                                <th class="px-4 py-3 text-left">Location</th>
                                <th class="px-4 py-3 text-left">Phone</th>
                                <th class="px-4 py-3 text-left">Date</th>
                                <th class="px-4 py-3 text-center"><span class="sr-only">Actions</span></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($records as $record)
                                <tr data-record='@json($record)'>
                                    <td class="px-4 py-2.5 text-xs text-slate-400 font-mono"></td>
                                    <td class="px-4 py-2.5 font-semibold text-slate-700">{{ $record->applicant_name }}</td>
                                    <td class="px-4 py-2.5 text-slate-700">{{ $record->document_kind === 'op' ? 'OP' : 'Regular File' }}</td>
                                    <td class="px-4 py-2.5 text-slate-700">{{ $record->file_no ?: '—' }}</td>
                                    <td class="px-4 py-2.5 text-slate-700">{{ $record->op_serial_number ?: '—' }}</td>
                                    <td class="px-4 py-2.5 text-slate-700">{{ $record->land_use ?: '—' }}</td>
                                    <td class="px-4 py-2.5 text-slate-700">{{ $record->plot_no ?: '—' }}</td>
                                    <td class="px-4 py-2.5 text-slate-700">{{ $record->district ?: '—' }}</td>
                                    <td class="px-4 py-2.5 text-slate-700">{{ $record->lga ?: '—' }}</td>
                                    <td class="px-4 py-2.5 text-slate-600 text-xs leading-snug">{{ $record->location ?: '—' }}</td>
                                    <td class="px-4 py-2.5 text-slate-700">{{ $record->phone ?: '—' }}</td>
                                    <td class="px-4 py-2.5 font-mono text-xs text-slate-600" data-order="{{ $record->created_at ? \Carbon\Carbon::parse($record->created_at)->format('YmdHis') : '' }}">
                                        {{ $record->created_at ? \Carbon\Carbon::parse($record->created_at)->format('M d, Y') : '—' }}
                                    </td>
                                    <td class="px-4 py-2.5 text-center">
                                        <button type="button" aria-label="Actions" onclick="lodToggleMenu(event, this)"
                                            class="p-2 hover:bg-slate-100 text-slate-600 rounded-full transition">
                                            <i data-lucide="more-vertical" class="w-5 h-5"></i>
                                        </button>
                                        <div class="lod-action-menu hidden fixed z-[999] w-max rounded-xl bg-white shadow-xl ring-1 ring-black/5 py-1 text-left">
                                            <button type="button" onclick="lodView(this)" class="flex items-center gap-2 w-full px-4 py-2.5 text-sm text-slate-700 hover:bg-slate-50">
                                                <i data-lucide="eye" class="w-4 h-4 text-blue-500"></i> View Details
                                            </button>
                                            <button type="button" onclick="lodEdit(this)" class="flex items-center gap-2 w-full px-4 py-2.5 text-sm text-slate-700 hover:bg-slate-50">
                                                <i data-lucide="pencil" class="w-4 h-4 text-amber-500"></i> Edit
                                            </button>
                                            <button type="button" onclick="lodDelete(this)" class="flex items-center gap-2 w-full px-4 py-2.5 text-sm text-red-600 hover:bg-red-50">
                                                <i data-lucide="trash-2" class="w-4 h-4"></i> Delete
                                            </button>
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

    {{-- ═══════════════════════════════════════════════════════
         WIZARD MODAL
         ═══════════════════════════════════════════════════════ --}}
    <div id="lod-modal" class="fixed inset-0 z-[9999] hidden flex items-center justify-center p-4">
        <div class="fixed inset-0 bg-slate-900/40 backdrop-blur-sm" onclick="lodCloseModal()"></div>

        <div class="relative bg-white rounded-3xl shadow-2xl w-full max-w-4xl max-h-[90vh] flex flex-col overflow-hidden border border-slate-100">
            {{-- Header --}}
            <div class="px-8 py-6 border-b border-slate-100 flex items-center justify-between bg-white shrink-0">
                <div>
                    <h3 id="lod-modal-title" class="text-xl font-bold text-slate-900">New Loss of Document Application</h3>
                    <p class="text-xs text-slate-500 mt-1 uppercase tracking-widest font-semibold">File Selection &amp; Applicant Details</p>
                </div>
                <button type="button" class="text-slate-400 hover:text-slate-600 p-2 hover:bg-slate-50 rounded-xl transition" onclick="lodCloseModal()">
                    <i data-lucide="x" class="h-6 w-6"></i>
                </button>
            </div>

            {{-- Form Body --}}
            <form id="lod-form" class="flex-1 overflow-y-auto" enctype="multipart/form-data">
                @csrf
                <input type="hidden" id="lod-id">

                <div class="px-8 py-8 space-y-8">
                    {{-- Wizard Progress Indicator --}}
                    <div class="wizard-progress mb-2">
                        <div class="flex items-center justify-between">
                            @foreach([
                                ['step' => 1, 'label' => 'File & Applicant', 'icon' => 'file-text'],
                                ['step' => 2, 'label' => 'Addresses',        'icon' => 'map-pin'],
                                ['step' => 3, 'label' => 'Property',         'icon' => 'map'],
                                ['step' => 4, 'label' => 'Review',           'icon' => 'check-circle'],
                            ] as $index => $s)
                                @if($index > 0)
                                    <div class="flex-1 h-0.5 mx-2 lod-wizard-connector" data-before-step="{{ $s['step'] }}">
                                        <div class="h-full bg-slate-200 rounded transition-colors duration-300 connector-bar"></div>
                                    </div>
                                @endif
                                <div class="flex flex-col items-center lod-wizard-step-indicator" data-step-indicator="{{ $s['step'] }}">
                                    <div class="w-10 h-10 rounded-full flex items-center justify-center border-2 transition-all duration-300 {{ $s['step'] === 1 ? 'border-blue-500 bg-blue-500 text-white' : 'border-slate-300 bg-white text-slate-400' }}"
                                         id="lod-wizard-circle-{{ $s['step'] }}">
                                        <i data-lucide="{{ $s['icon'] }}" class="w-4 h-4"></i>
                                    </div>
                                    <span class="text-xs font-medium mt-2 transition-colors duration-300 {{ $s['step'] === 1 ? 'text-blue-600' : 'text-slate-400' }}"
                                          id="lod-wizard-label-{{ $s['step'] }}">{{ $s['label'] }}</span>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    {{-- ═══════ STEP 1: File & Applicant ═══════ --}}
                    <div class="lod-step" data-step="1">
                        <div class="space-y-4">
                            <div class="flex items-center gap-3 mb-2">
                                <div class="w-8 h-8 rounded-full bg-blue-50 flex items-center justify-center text-blue-600">
                                    <i data-lucide="file-text" class="h-4 w-4"></i>
                                </div>
                                <h4 class="text-sm font-bold text-slate-700 uppercase tracking-wider">File &amp; Applicant Info</h4>
                            </div>

                            {{-- Regular file or OP: a lost OP is found by serial number, not by file number --}}
                            <div>
                                <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">Type <span class="text-red-500">*</span></label>
                                <div class="flex items-center gap-6">
                                    <label class="inline-flex items-center gap-2 text-sm font-medium text-slate-700 cursor-pointer">
                                        <input type="radio" name="document_kind" value="regular" onchange="lodKindChanged()" class="w-4 h-4 text-blue-600 border-slate-300">
                                        Regular File
                                    </label>
                                    <label class="inline-flex items-center gap-2 text-sm font-medium text-slate-700 cursor-pointer">
                                        <input type="radio" name="document_kind" value="op" onchange="lodKindChanged()" class="w-4 h-4 text-blue-600 border-slate-300">
                                        OP
                                    </label>
                                </div>
                            </div>

                            <div id="lod-op-panel" class="hidden rounded-xl border border-slate-200 p-4 space-y-3">
                                <div class="flex flex-col md:flex-row md:items-end gap-3">
                                    <div class="flex-1">
                                        <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">OP Serial Number <span class="text-red-500">*</span></label>
                                        <input id="lod-op-serial" type="text" autocomplete="off"
                                            onkeydown="if (event.key === 'Enter') { event.preventDefault(); lodFindOp(); }"
                                            class="w-full px-4 py-3 rounded-xl border border-slate-200 bg-slate-50 focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 transition"
                                            placeholder="Enter the OP serial number">
                                    </div>
                                    <button type="button" onclick="lodFindOp()"
                                        class="inline-flex items-center justify-center gap-2 px-5 py-3 bg-blue-600 text-white rounded-xl text-sm font-semibold hover:bg-blue-700 transition">
                                        <i data-lucide="search" class="w-4 h-4"></i> Find OP
                                    </button>
                                </div>
                                <div id="lod-op-results" class="space-y-2"></div>
                            </div>

                            {{-- Passport Photo Upload (first) --}}
                            <div>
                                <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">Passport Photograph</label>
                                <div class="flex items-start gap-4">
                                    <div id="lod-passport-preview" class="w-28 h-28 rounded-xl border-2 border-dashed border-slate-300 bg-slate-50 flex items-center justify-center overflow-hidden shrink-0">
                                        <div id="lod-passport-placeholder" class="text-center">
                                            <i data-lucide="user" class="w-8 h-8 mx-auto text-slate-300"></i>
                                            <span class="text-[10px] text-slate-400 block mt-1">No photo</span>
                                        </div>
                                        <img id="lod-passport-img" src="" alt="Passport" class="hidden w-full h-full object-cover">
                                    </div>
                                    <div class="flex-1">
                                        <input type="file" id="lod-passport-file" name="passport_photo" accept="image/jpeg,image/png,image/jpg"
                                            class="block w-full text-sm text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
                                        <p class="text-[10px] text-slate-400 mt-1">JPEG or PNG, max 2MB</p>
                                    </div>
                                </div>
                            </div>

                            {{-- File Number Selector --}}
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                                <div>
                                    <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">File Number</label>
                                    <div class="flex items-center gap-2">
                                        <div class="relative flex-1">
                                            <input type="text" id="lod-file-no" name="file_no" readonly
                                                class="w-full px-4 py-3 rounded-xl border border-slate-200 bg-slate-50 text-sm font-bold font-mono text-slate-700"
                                                placeholder="Select file...">
                                            <div id="lod-file-indicator" class="hidden absolute right-3 top-1/2 -translate-y-1/2">
                                                <i data-lucide="check-circle" class="h-5 w-5 text-emerald-500"></i>
                                            </div>
                                        </div>
                                        <button type="button" id="lod-select-file-btn" style="display:none"
                                            class="p-3 rounded-xl bg-blue-50 text-blue-600 font-bold border border-blue-100 hover:bg-blue-100 transition flex items-center justify-center shrink-0"
                                            title="Select File Number">
                                            <i data-lucide="search" class="h-5 w-5"></i>
                                        </button>
                                    </div>
                                </div>

                                <div>
                                    <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">Applicant Full Name <span class="text-red-500">*</span></label>
                                    <input id="lod-applicant-name" name="applicant_name" type="text" required
                                        class="w-full px-4 py-3 rounded-xl border border-slate-200 bg-slate-50 focus:border-blue-500 focus:ring-2 focus:ring-blue-100 transition focus:bg-white text-sm font-medium"
                                        placeholder="e.g. Musa Ali">
                                </div>

                                <div>
                                    <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">Nationality</label>
                                    <input id="lod-nationality" name="nationality" type="text"
                                        class="w-full px-4 py-3 rounded-xl border border-slate-200 bg-slate-50 focus:border-blue-500 focus:ring-2 focus:ring-blue-100 transition focus:bg-white text-sm font-medium"
                                        placeholder="e.g. Nigerian" value="Nigerian">
                                </div>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                                <div>
                                    <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">Occupation</label>
                                    <input id="lod-occupation" name="occupation" type="text"
                                        class="w-full px-4 py-3 rounded-xl border border-slate-200 bg-slate-50 focus:border-blue-500 focus:ring-2 focus:ring-blue-100 transition focus:bg-white text-sm font-medium"
                                        placeholder="e.g. Civil Servant">
                                </div>
                                <div>
                                    <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">Phone</label>
                                    <input id="lod-phone" name="phone" type="text"
                                        class="w-full px-4 py-3 rounded-xl border border-slate-200 bg-slate-50 focus:border-blue-500 focus:ring-2 focus:ring-blue-100 transition focus:bg-white text-sm font-medium"
                                        placeholder="e.g. 08012345678">
                                </div>
                                <div>
                                    <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">Email</label>
                                    <input id="lod-email" name="email" type="email"
                                        class="w-full px-4 py-3 rounded-xl border border-slate-200 bg-slate-50 focus:border-blue-500 focus:ring-2 focus:ring-blue-100 transition focus:bg-white text-sm font-medium"
                                        placeholder="e.g. musa@example.com">
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- ═══════ STEP 2: Addresses ═══════ --}}
                    <div class="lod-step hidden" data-step="2">
                        <div class="space-y-6">
                            <div class="flex items-center gap-3 mb-2">
                                <div class="w-8 h-8 rounded-full bg-green-50 flex items-center justify-center text-green-600">
                                    <i data-lucide="map-pin" class="h-4 w-4"></i>
                                </div>
                                <h4 class="text-sm font-bold text-slate-700 uppercase tracking-wider">Addresses</h4>
                            </div>

                            {{-- Residential Address Builder --}}
                            <div>
                                <label class="block text-xs font-bold text-slate-700 mb-1">Residential Address <span class="text-slate-400 text-[10px] font-normal">(P.O. Box must not be given)</span></label>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-3 p-3 rounded-lg bg-green-50/60 border border-green-200 mt-1">
                                    <div>
                                        <label class="block text-[11px] font-semibold text-slate-500 mb-1">House No</label>
                                        <input type="text" id="lod_res_addr_house" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-green-500/20 focus:border-green-500" placeholder="e.g., 12A">
                                    </div>
                                    <div>
                                        <label class="block text-[11px] font-semibold text-slate-500 mb-1">Plot No</label>
                                        <input type="text" id="lod_res_addr_plot" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-green-500/20 focus:border-green-500" placeholder="e.g., 45">
                                    </div>
                                    <div>
                                        <label class="block text-[11px] font-semibold text-slate-500 mb-1">Street Name</label>
                                        <select id="lod_res_addr_street" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-green-500/20 focus:border-green-500">
                                            <option value="">SELECT STREET</option>
                                            @foreach($streetNames as $street)
                                                @continue(in_array(strtolower(trim((string) $street->name)), ['other', 'others'], true))
                                                <option value="{{ $street->name }}">{{ \Illuminate\Support\Str::upper((string) $street->name) }}</option>
                                            @endforeach
                                            <option value="Other">OTHER</option>
                                        </select>
                                    </div>
                                    <div id="lod-res-addr-street-other-wrapper" class="md:col-span-2 hidden">
                                        <label class="block text-[11px] font-semibold text-slate-500 mb-1">Specify Street Name</label>
                                        <input type="text" id="lod_res_addr_street_other" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-green-500/20 focus:border-green-500" placeholder="Type street name">
                                    </div>
                                    <div>
                                        <label class="block text-[11px] font-semibold text-slate-500 mb-1">District</label>
                                        <select id="lod_res_addr_district" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-green-500/20 focus:border-green-500">
                                            <option value="">SELECT DISTRICT</option>
                                            @foreach($districts as $district)
                                                @continue(in_array(strtolower(trim((string) $district->name)), ['other', 'others'], true))
                                                <option value="{{ $district->name }}">{{ \Illuminate\Support\Str::upper((string) $district->name) }}</option>
                                            @endforeach
                                            <option value="Other">OTHER</option>
                                        </select>
                                    </div>
                                    <div id="lod-res-addr-district-other-wrapper" class="hidden">
                                        <label class="block text-[11px] font-semibold text-slate-500 mb-1">Specify District</label>
                                        <input type="text" id="lod_res_addr_district_other" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-green-500/20 focus:border-green-500" placeholder="Type district name">
                                    </div>
                                    <div>
                                        <label class="block text-[11px] font-semibold text-slate-500 mb-1">LGA</label>
                                        <select id="lod_res_addr_lga" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-green-500/20 focus:border-green-500">
                                            <option value="">SELECT LGA</option>
                                            @foreach($lgas as $lga)
                                                <option value="{{ $lga->name }}">{{ \Illuminate\Support\Str::upper((string) $lga->name) }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div>
                                        <label class="block text-[11px] font-semibold text-slate-500 mb-1">State</label>
                                        <select id="lod_res_addr_state" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-green-500/20 focus:border-green-500">
                                            <option value="">SELECT STATE</option>
                                            @foreach($states as $state)
                                                <option value="{{ $state->StateName }}" {{ $state->StateName === 'Kano' ? 'selected' : '' }}>{{ \Illuminate\Support\Str::upper((string) $state->StateName) }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                                <textarea id="lod_residential_address" name="residential_address" rows="2" readonly
                                    placeholder="Address will be built automatically..."
                                    class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm bg-gray-50 text-slate-600 mt-2 resize-none"></textarea>
                            </div>

                            {{-- Correspondence Address Builder --}}
                            <div>
                                <label class="block text-xs font-bold text-slate-700 mb-1">Correspondence Address</label>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-3 p-3 rounded-lg bg-blue-50/60 border border-blue-200 mt-1">
                                    <div>
                                        <label class="block text-[11px] font-semibold text-slate-500 mb-1">House No</label>
                                        <input type="text" id="lod_corr_addr_house" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500" placeholder="e.g., 5B">
                                    </div>
                                    <div>
                                        <label class="block text-[11px] font-semibold text-slate-500 mb-1">Plot No</label>
                                        <input type="text" id="lod_corr_addr_plot" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500" placeholder="e.g., 45">
                                    </div>
                                    <div>
                                        <label class="block text-[11px] font-semibold text-slate-500 mb-1">Street Name</label>
                                        <select id="lod_corr_addr_street" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500">
                                            <option value="">SELECT STREET</option>
                                            @foreach($streetNames as $street)
                                                @continue(in_array(strtolower(trim((string) $street->name)), ['other', 'others'], true))
                                                <option value="{{ $street->name }}">{{ \Illuminate\Support\Str::upper((string) $street->name) }}</option>
                                            @endforeach
                                            <option value="Other">OTHER</option>
                                        </select>
                                    </div>
                                    <div id="lod-corr-addr-street-other-wrapper" class="md:col-span-2 hidden">
                                        <label class="block text-[11px] font-semibold text-slate-500 mb-1">Specify Street Name</label>
                                        <input type="text" id="lod_corr_addr_street_other" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500" placeholder="Type street name">
                                    </div>
                                    <div>
                                        <label class="block text-[11px] font-semibold text-slate-500 mb-1">District</label>
                                        <select id="lod_corr_addr_district" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500">
                                            <option value="">SELECT DISTRICT</option>
                                            @foreach($districts as $district)
                                                @continue(in_array(strtolower(trim((string) $district->name)), ['other', 'others'], true))
                                                <option value="{{ $district->name }}">{{ \Illuminate\Support\Str::upper((string) $district->name) }}</option>
                                            @endforeach
                                            <option value="Other">OTHER</option>
                                        </select>
                                    </div>
                                    <div id="lod-corr-addr-district-other-wrapper" class="hidden">
                                        <label class="block text-[11px] font-semibold text-slate-500 mb-1">Specify District</label>
                                        <input type="text" id="lod_corr_addr_district_other" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500" placeholder="Type district name">
                                    </div>
                                    <div>
                                        <label class="block text-[11px] font-semibold text-slate-500 mb-1">LGA</label>
                                        <select id="lod_corr_addr_lga" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500">
                                            <option value="">SELECT LGA</option>
                                            @foreach($lgas as $lga)
                                                <option value="{{ $lga->name }}">{{ \Illuminate\Support\Str::upper((string) $lga->name) }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div>
                                        <label class="block text-[11px] font-semibold text-slate-500 mb-1">State</label>
                                        <select id="lod_corr_addr_state" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500">
                                            <option value="">SELECT STATE</option>
                                            @foreach($states as $state)
                                                <option value="{{ $state->StateName }}" {{ $state->StateName === 'Kano' ? 'selected' : '' }}>{{ \Illuminate\Support\Str::upper((string) $state->StateName) }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                                <textarea id="lod_correspondence_address" name="correspondence_address" rows="2" readonly
                                    placeholder="Address will be built automatically..."
                                    class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm bg-gray-50 text-slate-600 mt-2 resize-none"></textarea>
                            </div>
                        </div>
                    </div>

                    {{-- ═══════ STEP 3: Property Details ═══════ --}}
                    <div class="lod-step hidden" data-step="3">
                        <div class="space-y-4">
                            <div class="flex items-center gap-3 mb-2">
                                <div class="w-8 h-8 rounded-full bg-indigo-50 flex items-center justify-center text-indigo-600">
                                    <i data-lucide="map" class="h-4 w-4"></i>
                                </div>
                                <h4 class="text-sm font-bold text-slate-700 uppercase tracking-wider">Property Details</h4>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                                <div>
                                    <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">Land Use</label>
                                    <input id="lod-land-use" name="land_use" type="text"
                                        class="w-full px-4 py-3 rounded-xl border border-slate-200 bg-slate-50 focus:border-blue-500 focus:ring-2 focus:ring-blue-100 transition focus:bg-white text-sm font-medium"
                                        placeholder="e.g. Residential">
                                </div>
                                <div>
                                    <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">Plot No</label>
                                    <input id="lod-plot-no" name="plot_no" type="text"
                                        class="w-full px-4 py-3 rounded-xl border border-slate-200 bg-slate-50 focus:border-blue-500 focus:ring-2 focus:ring-blue-100 transition focus:bg-white text-sm font-medium"
                                        placeholder="e.g. 123">
                                </div>
                                <div>
                                    <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">Plan No</label>
                                    <input id="lod-plan-no" name="plan_no" type="text"
                                        class="w-full px-4 py-3 rounded-xl border border-slate-200 bg-slate-50 focus:border-blue-500 focus:ring-2 focus:ring-blue-100 transition focus:bg-white text-sm font-medium"
                                        placeholder="e.g. KN/123">
                                </div>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">District</label>
                                    <input id="lod-district" name="district" type="text"
                                        class="w-full px-4 py-3 rounded-xl border border-slate-200 bg-slate-50 focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 transition">
                                </div>
                                <div>
                                    <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">LGA</label>
                                    <input id="lod-lga" name="lga" type="text"
                                        class="w-full px-4 py-3 rounded-xl border border-slate-200 bg-slate-50 focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 transition">
                                </div>
                            </div>

                            <div>
                                <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">Property Location</label>
                                <textarea id="lod-location" name="location" rows="3"
                                    class="w-full px-4 py-3 rounded-xl border border-slate-200 bg-slate-50 focus:border-blue-500 focus:ring-2 focus:ring-blue-100 transition focus:bg-white text-sm font-medium resize-none"
                                    placeholder="Describe the location of the property"></textarea>
                            </div>
                        </div>
                    </div>

                    {{-- ═══════ STEP 4: Review ═══════ --}}
                    <div class="lod-step hidden" data-step="4">
                        <div class="space-y-4">
                            <div class="flex items-center gap-3 mb-2">
                                <div class="w-8 h-8 rounded-full bg-emerald-50 flex items-center justify-center text-emerald-600">
                                    <i data-lucide="check-circle" class="h-4 w-4"></i>
                                </div>
                                <h4 class="text-sm font-bold text-slate-700 uppercase tracking-wider">Review &amp; Submit</h4>
                            </div>

                            <div>
                                <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">Notes / Remarks</label>
                                <textarea id="lod-remarks" name="remarks" rows="3"
                                    class="w-full px-4 py-3 rounded-xl border border-slate-200 bg-slate-50 focus:border-blue-500 focus:ring-2 focus:ring-blue-100 transition focus:bg-white text-sm font-medium resize-none"
                                    placeholder="Any additional notes..."></textarea>
                            </div>

                            <div class="bg-slate-50 border border-slate-200 rounded-xl p-4 text-sm text-slate-700">
                                <p class="font-semibold mb-2">Please confirm the entered details before submitting.</p>
                                <div id="lod-review-summary" class="space-y-1 text-xs text-slate-600"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </form>

            {{-- Footer: Navigation Buttons --}}
            <div class="px-8 py-4 border-t border-slate-100 flex items-center justify-between bg-white shrink-0">
                <button type="button" id="lod-prev-btn" class="px-4 py-2 rounded-lg border border-slate-300 text-slate-700 text-sm" onclick="lodPrevStep()" disabled>Previous</button>
                <div class="flex items-center gap-2">
                    <button type="button" id="lod-next-btn" class="px-4 py-2 rounded-lg bg-slate-800 text-white text-sm" onclick="lodNextStep()">Next</button>
                    <button type="button" id="lod-save-btn" class="hidden px-4 py-2 rounded-lg bg-blue-600 text-white text-sm" onclick="lodSubmitForm()">Save Application</button>
                </div>
            </div>
        </div>
    </div>

    {{-- Global File Number Modal Component --}}
    @include('components.global-fileno-modal')

    @include('admin.footer')
</div>
@endsection

@section('footer-scripts')
<script src="{{ asset('js/global-fileno-modal.js') }}"></script>
<script>
    const lodCsrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    const LOD_TOTAL_STEPS = 4;
    let lodStep = 1;
    const LOD_CAPTURE_OP_URL = '{{ route("instruments.index") }}';
    const LOD_FILE_LOOKUP_URL = '{{ route("lands-one-stop-shop.all-applications.lookup-file-indexing") }}';
    let lodOpMatches = [];      // the OPs the last serial search returned
    let lodOpKey = '';          // the OP picked from them; what the save sends

    /* ─── Address Builder ─── */
    var lodAddrDefs = [
        { prefix: 'lod_res_addr',  output: 'lod_residential_address' },
        { prefix: 'lod_corr_addr', output: 'lod_correspondence_address' }
    ];

    function lodIsOther(val) { return (val || '').trim().toLowerCase() === 'other'; }

    function lodToggleOther(selectEl, wrapperId, otherEl) {
        var wrapper = document.getElementById(wrapperId);
        if (!wrapper || !selectEl) return;
        var show = lodIsOther(selectEl.value);
        wrapper.classList.toggle('hidden', !show);
        if (!show && otherEl) otherEl.value = '';
    }

    function lodSelectedOrOther(selectEl, otherEl) {
        if (!selectEl) return '';
        if (lodIsOther(selectEl.value)) return (otherEl?.value || '').trim();
        return (selectEl.value || '').trim();
    }

    function lodBuildAddress(prefix) {
        var house   = (document.getElementById(prefix + '_house')?.value || '').trim();
        var plot    = (document.getElementById(prefix + '_plot')?.value || '').trim();
        var street  = document.getElementById(prefix + '_street');
        var streetO = document.getElementById(prefix + '_street_other');
        var dist    = document.getElementById(prefix + '_district');
        var distO   = document.getElementById(prefix + '_district_other');
        var lga     = (document.getElementById(prefix + '_lga')?.value || '').trim();
        var state   = (document.getElementById(prefix + '_state')?.value || '').trim();
        var streetVal = lodSelectedOrOther(street, streetO);
        var distVal   = lodSelectedOrOther(dist, distO);
        // House No and Plot No are separate entries. A house number belongs in
        // front of the street it is on ("12A ZOO ROAD"), while a plot number is
        // a cadastral reference that stands on its own. The plot is labelled in
        // the output because "12A ZOO ROAD, 45, NASSARAWA" would otherwise read
        // as a second house number.
        //
        // The old line read `plot ? plot : streetVal`, which silently dropped
        // the street from the address whenever a plot number was entered.
        var streetLine = [house, streetVal].filter(Boolean).join(' ');
        var plotLine = plot ? (/^plot\b/i.test(plot) ? plot : 'PLOT ' + plot) : '';
        return [streetLine, plotLine, distVal, lga, state].filter(Boolean).join(', ').toUpperCase();
    }

    function lodUpdateAddr(prefix, outputId) {
        var el = document.getElementById(outputId);
        if (el) el.value = lodBuildAddress(prefix);
    }

    function lodInitAddressBuilders() {
        lodAddrDefs.forEach(function (def) {
            var p = def.prefix, o = def.output;
            var houseEl   = document.getElementById(p + '_house');
            var plotEl    = document.getElementById(p + '_plot');
            var streetEl  = document.getElementById(p + '_street');
            var streetOEl = document.getElementById(p + '_street_other');
            var distEl    = document.getElementById(p + '_district');
            var distOEl   = document.getElementById(p + '_district_other');
            var lgaEl     = document.getElementById(p + '_lga');
            var stateEl   = document.getElementById(p + '_state');
            var dashP     = p.replace(/_/g, '-');

            [houseEl, plotEl, streetEl, streetOEl, distEl, distOEl, lgaEl, stateEl].forEach(function (el) {
                if (!el) return;
                el.addEventListener('input', function () { lodUpdateAddr(p, o); });
                el.addEventListener('change', function () { lodUpdateAddr(p, o); });
            });

            if (streetEl) streetEl.addEventListener('change', function () {
                lodToggleOther(streetEl, dashP + '-street-other-wrapper', streetOEl);
                lodUpdateAddr(p, o);
            });
            if (distEl) distEl.addEventListener('change', function () {
                lodToggleOther(distEl, dashP + '-district-other-wrapper', distOEl);
                lodUpdateAddr(p, o);
            });
        });
    }

    /* ─── Passport Preview ─── */
    function lodInitPassportPreview() {
        var fileInput = document.getElementById('lod-passport-file');
        if (!fileInput) return;
        fileInput.addEventListener('change', function () {
            var placeholder = document.getElementById('lod-passport-placeholder');
            var img = document.getElementById('lod-passport-img');
            if (this.files && this.files[0]) {
                var reader = new FileReader();
                reader.onload = function (e) {
                    img.src = e.target.result;
                    img.classList.remove('hidden');
                    img.style.display = 'block';
                    if (placeholder) placeholder.classList.add('hidden');
                };
                reader.readAsDataURL(this.files[0]);
            } else {
                img.classList.add('hidden');
                img.src = '';
                if (placeholder) placeholder.classList.remove('hidden');
            }
        });
    }

    /* ─── Wizard ─── */
    function lodSetStep(step) {
        lodStep = step;
        document.querySelectorAll('.lod-step').forEach(function (el) {
            el.classList.toggle('hidden', parseInt(el.dataset.step, 10) !== step);
        });

        for (var i = 1; i <= LOD_TOTAL_STEPS; i++) {
            var circle = document.getElementById('lod-wizard-circle-' + i);
            var label  = document.getElementById('lod-wizard-label-' + i);
            if (circle) {
                circle.className = 'w-10 h-10 rounded-full flex items-center justify-center border-2 transition-all duration-300 ' +
                    (i <= step ? 'border-blue-500 bg-blue-500 text-white' : 'border-slate-300 bg-white text-slate-400');
            }
            if (label) {
                label.className = 'text-xs font-medium mt-2 transition-colors duration-300 ' +
                    (i <= step ? 'text-blue-600' : 'text-slate-400');
            }
        }
        document.querySelectorAll('.lod-wizard-connector').forEach(function (conn) {
            var before = parseInt(conn.dataset.beforeStep, 10);
            var bar = conn.querySelector('.connector-bar');
            if (bar) bar.style.backgroundColor = before <= step ? 'rgb(59 130 246)' : 'rgb(226 232 240)';
        });

        document.getElementById('lod-prev-btn').disabled = step === 1;
        document.getElementById('lod-next-btn').classList.toggle('hidden', step === LOD_TOTAL_STEPS);
        document.getElementById('lod-save-btn').classList.toggle('hidden', step !== LOD_TOTAL_STEPS);

        if (step === LOD_TOTAL_STEPS) lodPopulateReview();
    }

    function lodNextStep() {
        if (lodStep === 1) {
            var kind = lodKind();
            if (!kind) {
                Swal.fire({ icon: 'warning', title: 'Select Regular File or OP' });
                return;
            }
            if (kind === 'regular' && !document.getElementById('lod-file-no').value.trim()) {
                Swal.fire({ icon: 'warning', title: 'Select the file number' });
                return;
            }
            if (kind === 'op' && !lodOpKey) {
                Swal.fire({ icon: 'warning', title: 'Find and select the OP' });
                return;
            }
            if (!document.getElementById('lod-applicant-name').value.trim()) {
                Swal.fire({ icon: 'warning', title: 'Applicant name is required' });
                return;
            }
        }
        if (lodStep < LOD_TOTAL_STEPS) lodSetStep(lodStep + 1);
    }

    function lodPrevStep() {
        if (lodStep > 1) lodSetStep(lodStep - 1);
    }

    function lodPopulateReview() {
        var container = document.getElementById('lod-review-summary');
        if (!container) return;
        var rows = [
            ['Type', lodKind() === 'op' ? 'OP' : 'Regular File'],
            ['OP Serial', lodKind() === 'op' ? document.getElementById('lod-op-serial')?.value : ''],
            ['File No', document.getElementById('lod-file-no')?.value || (lodKind() === 'op' ? 'Temporary number on save' : '')],
            ['Applicant', document.getElementById('lod-applicant-name')?.value],
            ['Nationality', document.getElementById('lod-nationality')?.value],
            ['Occupation', document.getElementById('lod-occupation')?.value],
            ['Phone', document.getElementById('lod-phone')?.value],
            ['Email', document.getElementById('lod-email')?.value],
            ['Residential Address', document.getElementById('lod_residential_address')?.value],
            ['Correspondence Address', document.getElementById('lod_correspondence_address')?.value],
            ['Land Use', document.getElementById('lod-land-use')?.value],
            ['Plan No', document.getElementById('lod-plan-no')?.value],
            ['Plot No', document.getElementById('lod-plot-no')?.value],
            ['District', document.getElementById('lod-district')?.value],
            ['LGA', document.getElementById('lod-lga')?.value],
            ['Location', document.getElementById('lod-location')?.value],
            ['Passport Photo', document.getElementById('lod-passport-file')?.files?.length ? 'Attached' : 'None'],
        ];
        container.innerHTML = rows.map(function (r) {
            return '<div><strong>' + r[0] + ':</strong> ' + lodSafe(r[1]) + '</div>';
        }).join('');
    }

    /* ─── Regular file or OP ─── */
    function lodKind() {
        return document.querySelector('input[name="document_kind"]:checked')?.value || '';
    }

    function lodShowKindPanels() {
        var kind = lodKind();
        document.getElementById('lod-op-panel').classList.toggle('hidden', kind !== 'op');
        // An OP's file number comes from the OP, so the selector is only for a regular file.
        document.getElementById('lod-select-file-btn').style.display = kind === 'regular' ? '' : 'none';
    }

    // Changing the type starts the record again: nothing of the previous one is kept.
    function lodKindChanged() {
        lodClearDetails();
        lodOpMatches = [];
        lodOpKey = '';
        lodOpResults('');
        document.getElementById('lod-op-serial').value = '';
        lodShowKindPanels();
    }

    function lodOpResults(html) {
        document.getElementById('lod-op-results').innerHTML = html;
    }

    // The fields a file or OP fills in, emptied so the last selection leaves nothing behind.
    function lodClearDetails() {
        ['lod-file-no', 'lod-applicant-name', 'lod-phone', 'lod-land-use', 'lod-plot-no', 'lod-plan-no',
            'lod-district', 'lod-lga', 'lod-location', 'lod_residential_address'].forEach(function (id) {
            document.getElementById(id).value = '';
        });
        document.getElementById('lod-file-indicator')?.classList.add('hidden');
    }

    function lodFill(details) {
        var set = function (id, value) {
            document.getElementById(id).value = (value || '').toString().trim();
        };
        set('lod-applicant-name', details.applicant);
        set('lod-phone', details.phone);
        set('lod_residential_address', details.address);
        set('lod-land-use', details.land_use);
        set('lod-plot-no', details.plot_no);
        set('lod-plan-no', details.plan_no);
        set('lod-district', details.district);
        set('lod-lga', details.lga);
        set('lod-location', details.location);
    }

    async function lodFindOp() {
        var serial = document.getElementById('lod-op-serial').value.trim();
        lodClearDetails();
        lodOpMatches = [];
        lodOpKey = '';
        if (!serial) {
            lodOpResults('<div class="text-sm text-red-600">Enter the OP serial number.</div>');
            return;
        }
        lodOpResults('<div class="text-sm text-slate-500">Searching...</div>');
        try {
            var response = await fetch('{{ route("loss-of-document.op-search") }}?serial=' + encodeURIComponent(serial), {
                headers: { 'Accept': 'application/json' }
            });
            var payload = await response.json();
            if (!response.ok || !payload.success) throw new Error(payload.message || 'Search failed.');
            lodOpMatches = payload.data || [];
        } catch (err) {
            lodOpResults('<div class="text-sm text-red-600">' + lodSafe(err.message) + '</div>');
            return;
        }

        if (!lodOpMatches.length) {
            lodOpResults('<div class="rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800">'
                + 'No OP with serial ' + lodSafe(serial) + ' is in the system. Capture the OP first, then come back to this application.'
                + '<div class="mt-2"><a href="' + LOD_CAPTURE_OP_URL + '" target="_blank" rel="noopener" class="font-semibold underline">Go to Capture OP</a></div></div>');
            return;
        }
        if (lodOpMatches.length === 1) {
            lodPickOp(0);
            return;
        }
        lodOpResults('<div class="text-xs font-semibold text-slate-600">' + lodOpMatches.length + ' OPs carry this serial. Select the right one.</div>'
            + lodOpMatches.map(function (op, i) {
                return '<button type="button" onclick="lodPickOp(' + i + ')" class="block w-full text-left rounded-lg border border-slate-200 p-3 text-sm hover:bg-blue-50">'
                    + '<span class="font-semibold text-slate-800">' + lodSafe(op.allottee) + '</span>'
                    + '<span class="block text-xs text-slate-500">' + lodSafe(op.type) + ' · File: ' + lodSafe(op.file_number)
                    + ' · Plot ' + lodSafe(op.plot_no) + ' · ' + lodSafe(op.location) + '</span></button>';
            }).join(''));
    }

    function lodPickOp(index) {
        var op = lodOpMatches[index];
        if (!op) return;
        lodClearDetails();
        lodOpKey = op.key;
        lodFill({
            applicant: op.allottee, phone: op.phone, address: op.address, land_use: op.land_use, plot_no: op.plot_no,
            plan_no: op.tp_no, district: op.district, lga: op.lga, location: op.location
        });
        document.getElementById('lod-file-no').value = (op.file_number || '').toUpperCase();
        if (op.file_number) document.getElementById('lod-file-indicator')?.classList.remove('hidden');
        lodOpResults('<div class="rounded-lg border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-800">'
            + 'Selected: <strong>' + lodSafe(op.allottee) + '</strong> (' + lodSafe(op.type) + '). '
            + (op.file_number ? 'File number ' + lodSafe(op.file_number) + '.' : 'It has no file number, so a temporary number is given on save.')
            + '</div>');
    }

    // Regular file: fill what the records hold, reusing the OSS form's lookup.
    async function lodBackfillFromFile(fileNo, fallbackName) {
        try {
            var response = await fetch(LOD_FILE_LOOKUP_URL + '?file_no=' + encodeURIComponent(fileNo), {
                headers: { 'Accept': 'application/json' }
            });
            var payload = await response.json();
            if (!response.ok || !payload.success) return;
            var d = payload.data || {};
            lodFill({
                applicant: d.file_name || fallbackName, phone: d.phone, address: d.address, land_use: d.land_use,
                plot_no: d.plot_no, plan_no: d.plan_no || d.tp_no, district: d.op_district, lga: d.lga, location: d.location
            });
        } catch (err) {
            // A failed lookup leaves the fields for the officer to fill.
        }
    }

    /* ─── Row action menu ─── */
    var lodOpenMenu = null;     // { menu, button } while a row menu is open

    function lodCloseMenus() {
        document.querySelectorAll('.lod-action-menu').forEach(function (m) { m.classList.add('hidden'); });
        lodOpenMenu = null;
    }

    // Fixed, so the table's overflow does not clip it. Flips up near the bottom of the screen.
    function lodPlaceMenu() {
        if (!lodOpenMenu) return;
        var rect = lodOpenMenu.button.getBoundingClientRect();
        // The table scrolled the button out of sight: nothing left to hang the menu from.
        if (rect.bottom < 0 || rect.top > window.innerHeight || rect.right < 0 || rect.left > window.innerWidth) {
            lodCloseMenus();
            return;
        }
        var menu = lodOpenMenu.menu;
        var top = rect.bottom + 4;
        if (top + menu.offsetHeight > window.innerHeight) top = rect.top - menu.offsetHeight - 4;
        menu.style.top = Math.max(8, top) + 'px';
        menu.style.left = Math.max(8, rect.right - menu.offsetWidth) + 'px';
    }

    function lodToggleMenu(event, button) {
        event.stopPropagation();
        var menu = button.parentElement.querySelector('.lod-action-menu');
        var wasOpen = !menu.classList.contains('hidden');
        lodCloseMenus();
        if (wasOpen) return;
        menu.classList.remove('hidden');
        lodOpenMenu = { menu: menu, button: button };
        lodPlaceMenu();
    }

    /* ─── CRUD ─── */
    function lodOpenCreate() {
        lodResetForm();
        document.getElementById('lod-modal-title').textContent = 'New Loss of Document Application';
        lodShowModal();
    }

    function lodView(btn) {
        var data = lodRowData(btn);
        if (!data) return;
        var passportHtml = '';
        if (data.passport_photo) {
            passportHtml = '<div><strong>Passport:</strong> <img src="' + lodSafe(data.passport_photo.replace('public/', '/storage/')) + '" style="max-width:100px;max-height:100px;border-radius:8px;margin-top:4px;" alt="Passport"></div>';
        }
        Swal.fire({
            title: 'Loss of Document Record',
            html: '<div style="text-align:left;font-size:13px;line-height:1.7;">' +
                '<div><strong>Type:</strong> ' + (data.document_kind === 'op' ? 'OP' : 'Regular File') + '</div>' +
                '<div><strong>OP Serial:</strong> ' + lodSafe(data.op_serial_number) + '</div>' +
                '<div><strong>Applicant:</strong> ' + lodSafe(data.applicant_name) + '</div>' +
                '<div><strong>Nationality:</strong> ' + lodSafe(data.nationality) + '</div>' +
                '<div><strong>Occupation:</strong> ' + lodSafe(data.occupation) + '</div>' +
                '<div><strong>Phone:</strong> ' + lodSafe(data.phone) + '</div>' +
                '<div><strong>Email:</strong> ' + lodSafe(data.email) + '</div>' +
                '<div><strong>File No:</strong> ' + lodSafe(data.file_no) + '</div>' +
                '<div><strong>Land Use:</strong> ' + lodSafe(data.land_use) + '</div>' +
                '<div><strong>Plot No:</strong> ' + lodSafe(data.plot_no) + '</div>' +
                '<div><strong>District:</strong> ' + lodSafe(data.district) + '</div>' +
                '<div><strong>LGA:</strong> ' + lodSafe(data.lga) + '</div>' +
                '<div><strong>Location:</strong> ' + lodSafe(data.location) + '</div>' +
                '<div><strong>Remarks:</strong> ' + lodSafe(data.remarks) + '</div>' +
                passportHtml +
                '</div>',
            width: 700,
            confirmButtonText: 'Close'
        });
    }

    function lodEdit(btn) {
        var data = lodRowData(btn);
        if (!data) return;
        lodResetForm();
        document.getElementById('lod-modal-title').textContent = 'Edit Loss of Document Application';
        document.getElementById('lod-id').value = data.id || '';
        document.getElementById('lod-applicant-name').value = data.applicant_name || '';
        document.getElementById('lod-nationality').value = data.nationality || '';
        document.getElementById('lod-occupation').value = data.occupation || '';
        document.getElementById('lod-phone').value = data.phone || '';
        document.getElementById('lod-email').value = data.email || '';
        document.getElementById('lod-file-no').value = data.file_no || '';
        document.getElementById('lod-land-use').value = data.land_use || '';
        document.getElementById('lod-plot-no').value = data.plot_no || '';
        document.getElementById('lod-plan-no').value = data.plan_no || '';
        document.getElementById('lod-district').value = data.district || '';
        document.getElementById('lod-lga').value = data.lga || '';
        document.getElementById('lod-location').value = data.location || '';
        document.getElementById('lod-remarks').value = data.remarks || '';
        document.getElementById('lod_residential_address').value = data.residential_address || '';
        document.getElementById('lod_correspondence_address').value = data.correspondence_address || '';

        if (data.file_no) document.getElementById('lod-file-indicator')?.classList.remove('hidden');

        var kindRadio = document.querySelector('input[name="document_kind"][value="' + data.document_kind + '"]');
        if (kindRadio) kindRadio.checked = true;
        lodShowKindPanels();
        if (data.document_kind === 'op') {
            document.getElementById('lod-op-serial').value = data.op_serial_number || '';
            lodOpKey = data.op_source && data.op_id ? data.op_source + '-' + data.op_id : '';
            if (lodOpKey) {
                lodOpResults('<div class="text-sm text-emerald-700">Saved against OP ' + lodSafe(data.op_serial_number)
                    + '. Search again to pick a different OP.</div>');
            }
        }

        if (data.passport_photo) {
            var img = document.getElementById('lod-passport-img');
            var placeholder = document.getElementById('lod-passport-placeholder');
            img.src = data.passport_photo.replace('public/', '/storage/');
            img.classList.remove('hidden');
            img.style.display = 'block';
            if (placeholder) placeholder.classList.add('hidden');
        }

        lodShowModal();
    }

    async function lodDelete(btn) {
        var data = lodRowData(btn);
        if (!data || !data.id) return;

        var res = await Swal.fire({
            title: 'Delete record?',
            text: 'This action cannot be undone.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Yes, delete',
            cancelButtonText: 'Cancel'
        });
        if (!res.isConfirmed) return;

        try {
            var response = await fetch('{{ route("loss-of-document.destroy", ["id" => 0]) }}'.replace('/0', '/' + data.id), {
                method: 'DELETE',
                headers: { 'X-CSRF-TOKEN': lodCsrf, 'Accept': 'application/json' }
            });
            var payload = await response.json();
            if (!response.ok || !payload.success) throw new Error(payload.message || 'Delete failed.');
            await Swal.fire({ icon: 'success', title: 'Deleted', timer: 1200, showConfirmButton: false });
            location.reload();
        } catch (err) {
            Swal.fire({ icon: 'error', title: 'Failed', text: err.message || 'Delete failed.' });
        }
    }

    function lodShowModal() {
        document.getElementById('lod-modal').classList.remove('hidden');
        lodSetStep(1);
    }

    function lodCloseModal() {
        document.getElementById('lod-modal').classList.add('hidden');
    }

    function lodResetForm() {
        document.getElementById('lod-form').reset();
        document.getElementById('lod-id').value = '';
        document.getElementById('lod-file-indicator')?.classList.add('hidden');
        lodOpMatches = [];
        lodOpKey = '';
        lodOpResults('');
        lodShowKindPanels();
        var img = document.getElementById('lod-passport-img');
        var placeholder = document.getElementById('lod-passport-placeholder');
        if (img) { img.classList.add('hidden'); img.src = ''; }
        if (placeholder) placeholder.classList.remove('hidden');
        document.querySelectorAll('[id$="-other-wrapper"]').forEach(function (w) { w.classList.add('hidden'); });
        lodSetStep(1);
    }

    async function lodSubmitForm() {
        var id = document.getElementById('lod-id').value;
        var formData = new FormData();

        formData.append('document_kind', lodKind());
        formData.append('op_key', lodOpKey);
        formData.append('applicant_name', document.getElementById('lod-applicant-name').value);
        formData.append('residential_address', document.getElementById('lod_residential_address').value);
        formData.append('correspondence_address', document.getElementById('lod_correspondence_address').value);
        formData.append('nationality', document.getElementById('lod-nationality').value);
        formData.append('occupation', document.getElementById('lod-occupation').value);
        formData.append('phone', document.getElementById('lod-phone').value);
        formData.append('email', document.getElementById('lod-email').value);
        formData.append('file_no', document.getElementById('lod-file-no').value);
        formData.append('land_use', document.getElementById('lod-land-use').value);
        formData.append('plot_no', document.getElementById('lod-plot-no').value);
        formData.append('plan_no', document.getElementById('lod-plan-no').value);
        formData.append('district', document.getElementById('lod-district').value);
        formData.append('lga', document.getElementById('lod-lga').value);
        formData.append('location', document.getElementById('lod-location').value);
        formData.append('remarks', document.getElementById('lod-remarks').value);

        var passportFile = document.getElementById('lod-passport-file')?.files?.[0];
        if (passportFile) {
            formData.append('passport_photo', passportFile);
        }

        var endpoint, method;
        if (id) {
            endpoint = '{{ route("loss-of-document.update", ["id" => 0]) }}'.replace('/0', '/' + id);
            formData.append('_method', 'PUT');
            method = 'POST';
        } else {
            endpoint = '{{ route("loss-of-document.store") }}';
            method = 'POST';
        }

        try {
            var response = await fetch(endpoint, {
                method: method,
                headers: { 'X-CSRF-TOKEN': lodCsrf, 'Accept': 'application/json' },
                body: formData
            });
            var payload = await response.json();
            if (!response.ok || !payload.success) {
                var msg = payload.message || (payload.errors ? Object.values(payload.errors)[0][0] : 'Save failed.');
                throw new Error(msg);
            }
            await Swal.fire({ icon: 'success', title: 'Saved successfully', timer: 1200, showConfirmButton: false });
            location.reload();
        } catch (err) {
            Swal.fire({ icon: 'error', title: 'Failed', text: err.message || 'Save failed.' });
        }
    }

    function lodRowData(btn) {
        var tr = btn.closest('tr');
        if (!tr) return null;
        try { return JSON.parse(tr.getAttribute('data-record')); } catch (e) { return null; }
    }

    function lodSafe(value) {
        return (value || '—').toString().replace(/[&<>"']/g, function (m) {
            return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' })[m];
        });
    }

    /* ─── Init ─── */
    document.addEventListener('DOMContentLoaded', function () {
        /* DataTable */
        var table = $('#lod-table').DataTable({
            dom: '<"overflow-x-auto"t><"px-4 py-3 flex items-center justify-between border-t border-slate-100"ip>',
            pageLength: parseInt(document.getElementById('lod-length')?.value || '50', 10),
            lengthChange: false,
            order: [[11, 'desc']],
            columnDefs: [{ orderable: false, targets: [0, 12] }],
            language: {
                info: 'Showing _START_ to _END_ of _TOTAL_ loss of document records',
                emptyTable: 'No loss of document applications found.',
                paginate: { previous: 'Prev', next: 'Next' }
            }
        });

        // S/N follows the rows as shown, across search, sort and paging.
        table.on('draw.dt', function () {
            var start = table.page.info().start;
            table.column(0, { page: 'current', search: 'applied', order: 'applied' }).nodes().each(function (cell, i) {
                cell.textContent = start + i + 1;
            });
            lodCloseMenus();
            if (window.lucide) window.lucide.createIcons();
        });
        table.draw(false);

        document.addEventListener('click', lodCloseMenus);
        window.addEventListener('scroll', lodPlaceMenu, true);
        window.addEventListener('resize', lodPlaceMenu);

        document.getElementById('lod-search')?.addEventListener('input', function () {
            table.search(this.value).draw();
        });
        document.getElementById('lod-length')?.addEventListener('change', function () {
            table.page.len(parseInt(this.value, 10)).draw();
        });

        /* Address builders */
        lodInitAddressBuilders();

        /* Passport preview */
        lodInitPassportPreview();

        /* File Number Selector */
        if (window.GlobalFileNoModal) {
            GlobalFileNoModal.init();
        }

        document.getElementById('lod-select-file-btn')?.addEventListener('click', function () {
            if (!window.GlobalFileNoModal) return;
            GlobalFileNoModal.open({
                callback: function (data) {
                    if (data.fileNumber) {
                        lodClearDetails();
                        document.getElementById('lod-file-no').value = data.fileNumber.toUpperCase();
                        document.getElementById('lod-file-indicator')?.classList.remove('hidden');
                        var name = data.record && data.record.file_name ? data.record.file_name.toUpperCase() : '';
                        document.getElementById('lod-applicant-name').value = name;
                        lodBackfillFromFile(data.fileNumber, name);
                    }
                }
            });
            // Raise file-no modal above the form modal (use setTimeout to ensure it applies after open() internals)
            setTimeout(function() {
                $('#global-fileno-modal').css({ 'z-index': '99999', 'display': 'flex' });
            }, 50);
        });

        if (window.lucide) window.lucide.createIcons();
    });
</script>
@endsection
