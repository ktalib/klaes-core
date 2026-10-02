@extends('layouts.app')

 @section('styles')
<link rel="stylesheet" href="{{ asset('css/global-fileno-modal.css') }}">
<style>
    /* Table styles matching other modules */
    #tf-table_wrapper .dataTables_filter,
    #tf-table_wrapper .dataTables_length { display: none; }
    #tf-table_wrapper .dataTables_info {
        font-size: 0.8125rem; color: rgb(100 116 139); padding: 0.75rem 1rem;
    }
    #tf-table_wrapper .dataTables_paginate { padding: 0.5rem 1rem 0.75rem; }
    #tf-table thead th {
        white-space: nowrap; vertical-align: middle; border-bottom: 2px solid rgb(226 232 240);
    }
    #tf-table tbody td {
        white-space: nowrap; vertical-align: middle; border-bottom: 1px solid rgb(241 245 249);
    }
    .swal2-container { z-index: 20000 !important; }
</style>
@endsection

@section('content')<div class="flex-1 overflow-auto bg-slate-50/60">
    @include('admin.header', [
        'PageTitle' => 'Temporary File',
        'PageDescription' => 'Capture and manage Temporary File records.'
    ])

    <div class="py-10 bg-slate-50 min-h-screen">
        <div class="max-w-[95%] mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                <div>
                    <h1 class="text-3xl font-extrabold text-slate-900 mt-1">Temporary File</h1>
                    <p class="text-slate-500 mt-1 text-sm">{{ number_format($records->count()) }} record(s) loaded</p>
                </div>
                <div class="flex items-center gap-3">
                    <div class="relative">
                        <i data-lucide="search" class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-slate-400"></i>
                        <input type="text" id="tf-search" placeholder="Search applications..."
                            class="w-72 bg-white border border-slate-200 rounded-xl pl-10 pr-4 py-2 text-sm focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500">
                    </div>
                    <select id="tf-length" class="bg-white border border-slate-200 rounded-xl px-4 py-2 text-sm focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500">
                        @foreach([25, 50, 100, 150, 200] as $option)
                            <option value="{{ $option }}" @selected($limit == $option)>{{ $option }} rows</option>
                        @endforeach
                    </select>
                    <button type="button" onclick="tfOpenCreate()"
                        class="inline-flex items-center gap-2 px-5 py-2 bg-blue-600 text-white rounded-xl text-sm font-semibold shadow-sm hover:bg-blue-700 transition">
                        <i data-lucide="plus" class="w-4 h-4"></i>
                        New Application
                    </button>
                </div>
            </div>

            <!-- Data Table -->
            <div class="bg-white border border-slate-200 rounded-2xl shadow-sm overflow-hidden">
                <div class="overflow-x-auto">
                    <table id="tf-table" class="w-full text-sm">
                        <thead>
                            <tr class="text-[10px] font-bold uppercase tracking-wider text-slate-500 bg-slate-50/80">
                                <th class="px-4 py-3 text-left">#</th>
                                <th class="px-4 py-3 text-left">Applicant Name</th>
                                <th class="px-4 py-3 text-left">File No</th>
                                <th class="px-4 py-3 text-left">Plot No</th>
                                <th class="px-4 py-3 text-left">District</th>
                                <th class="px-4 py-3 text-left">Reason</th>                                  <th class="px-4 py-3 text-center">Status</th>                                <th class="px-4 py-3 text-left">Date</th>
                                <th class="px-4 py-3 text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($records as $record)
                                <tr data-record='@json($record)'>
                                    <td class="px-4 py-2.5 text-xs text-slate-400 font-mono">{{ $record->id }}</td>
                                    <td class="px-4 py-2.5 font-semibold text-slate-700">{{ $record->applicant_name }}</td>
                                    <td class="px-4 py-2.5 font-mono text-xs text-blue-600 font-semibold">{{ $record->file_no ?: '—' }}</td>
                                    <td class="px-4 py-2.5 text-slate-600 text-xs">{{ $record->plot_no ?: '—' }}</td>
                                    <td class="px-4 py-2.5 text-slate-600 text-xs">{{ $record->district ?: '—' }}</td>
                                    <td class="px-4 py-2.5 text-slate-600 text-xs truncate max-w-xs">{{ $record->remarks ?: '—' }}</td>                                      <td class="px-4 py-2.5 text-center">
                                          @if($record->status === 'Approved')
                                              <span class="px-2.5 py-1 rounded-full bg-green-50 text-green-700 text-[10px] font-bold uppercase tracking-wider">Approved</span>
                                          @else
                                              <span class="px-2.5 py-1 rounded-full bg-slate-100 text-slate-600 text-[10px] font-bold uppercase tracking-wider">{{ $record->status ?: 'Pending' }}</span>
                                          @endif
                                      </td>                                    <td class="px-4 py-2.5 font-mono text-xs text-slate-600">
                                        {{ $record->created_at ? \Carbon\Carbon::parse($record->created_at)->format('M d, Y') : '—' }}
                                    </td>
                                    <td class="px-4 py-2.5 text-center">
                                        @php
                                            $isApproved  = $record->status === 'Approved';
                                            $recGenerated = !empty($record->recommendation_generated_at);
                                        @endphp
                                        <button type="button"
                                            class="tf-action-toggle w-8 h-8 flex items-center justify-center rounded-lg hover:bg-slate-100 text-slate-500 hover:text-slate-800 transition"
                                            data-id="{{ $record->id }}"
                                            data-approved="{{ $isApproved ? '1' : '0' }}"
                                            data-rec-generated="{{ $recGenerated ? '1' : '0' }}"
                                            data-print-url="{{ route('temporary-file.print-recommendation', $record->id) }}">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                                                <circle cx="12" cy="5" r="1.5"/><circle cx="12" cy="12" r="1.5"/><circle cx="12" cy="19" r="1.5"/>
                                            </svg>
                                        </button>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                      <td colspan="9" class="px-4 py-8 text-center text-slate-500">No applications found.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Capture/Edit Modal -->
<div id="tf-modal" class="fixed inset-0 z-50 hidden" aria-labelledby="modal-title" role="dialog" aria-modal="true">
    <div class="fixed inset-0 transition-opacity bg-slate-900/50 backdrop-blur-sm" aria-hidden="true" onclick="tfCloseModal()"></div>
    <div class="fixed inset-0 z-10 w-screen overflow-y-auto">
        <div class="flex items-center justify-center min-h-full p-4 text-center sm:p-0">
            <div class="relative overflow-hidden text-left transition-all transform bg-white rounded-2xl shadow-xl sm:my-8 sm:w-full sm:max-w-2xl">
                
                <div class="px-6 py-4 border-b border-slate-200 bg-slate-50 flex items-center justify-between">
                    <div>
                        <h3 class="text-lg font-bold text-slate-900" id="modal-title">New Temporary File</h3>
                        <p class="text-sm text-slate-500 mt-1">Fill in the details below</p>
                    </div>
                    <button type="button" onclick="tfCloseModal()" class="text-slate-400 hover:text-slate-500 transition-colors">
                        <i data-lucide="x" class="w-5 h-5"></i>
                    </button>
                </div>

                <div class="px-6 py-6">
                    <form id="temporary-file-form" class="space-y-5">
                        @csrf
                        <input type="hidden" name="_method" value="POST" id="method-override">
                        <input type="hidden" id="tf-id">

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">

                             <div>
                                <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-1.5">Original File Number</label>
                                <div class="relative">
                                    <input type="text" name="file_no" id="tf-file_no"
                                        placeholder="e.g. RES-2005-123"
                                        class="w-full px-4 py-3 pr-20 rounded-xl border border-slate-200 bg-slate-50 focus:border-blue-500 focus:ring-2 focus:ring-blue-100 transition focus:bg-white text-sm font-medium uppercase">
                                    <button type="button" onclick="tfOpenFileModal()"
                                        class="absolute right-2 top-1/2 -translate-y-1/2 px-3 py-1.5 text-xs font-semibold bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition">
                                        Select
                                    </button>
                                </div>
                            </div>
                            
                            <div>
                                <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-1.5">Applicant Name <span class="text-red-500">*</span></label>
                                <input type="text" name="applicant_name" id="tf-applicant_name" required
                                    placeholder="e.g. Musa Sani"
                                    class="w-full px-4 py-3 rounded-xl border border-slate-200 bg-slate-50 focus:border-blue-500 focus:ring-2 focus:ring-blue-100 transition focus:bg-white text-sm font-medium">
                            </div>

                            <div>
                                <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-1.5">Plot No</label>
                                <input type="text" name="plot_no" id="tf-plot_no"
                                    placeholder="e.g. 12A"
                                    class="w-full px-4 py-3 rounded-xl border border-slate-200 bg-slate-50 focus:border-blue-500 focus:ring-2 focus:ring-blue-100 transition focus:bg-white text-sm font-medium">
                            </div>

                            <div>
                                <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-1.5">District</label>
                                <select name="district" id="tf-district"
                                    class="w-full px-4 py-3 rounded-xl border border-slate-200 bg-slate-50 focus:border-blue-500 focus:ring-2 focus:ring-blue-100 transition text-sm font-medium">
                                    <option value="">Select District</option>
                                    @foreach($districts as $district)
                                        @if(strtoupper($district->name) !== 'OTHER')
                                            <option value="{{ $district->name }}">{{ Str::upper($district->name) }}</option>
                                        @endif
                                    @endforeach
                                    <option value="OTHER">OTHER</option>
                                </select>
                            </div>

                            <div id="tf-district-specify-wrapper" class="hidden">
                                <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-1.5">Specify District <span class="text-red-500">*</span></label>
                                <input type="text" name="district_specify" id="tf-district_specify"
                                    placeholder="Enter district name..."
                                    class="w-full px-4 py-3 rounded-xl border border-slate-200 bg-slate-50 focus:border-blue-500 focus:ring-2 focus:ring-blue-100 transition focus:bg-white text-sm font-medium uppercase">
                            </div>

                            <div>
                                <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-1.5">LGA</label>
                                <select name="lga" id="tf-lga"
                                    class="w-full px-4 py-3 rounded-xl border border-slate-200 bg-slate-50 focus:border-blue-500 focus:ring-2 focus:ring-blue-100 transition text-sm font-medium">
                                    <option value="">Select LGA</option>
                                    @foreach($lgas as $lga)
                                        <option value="{{ $lga->name }}">{{ Str::upper($lga->name) }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="md:col-span-2">
                                <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-1.5">Property Location</label>
                                <input type="text" name="address" id="tf-address" readonly
                                    placeholder="Auto-composed from Plot No, District & LGA"
                                    class="w-full px-4 py-3 rounded-xl border border-slate-200 bg-slate-100 text-sm font-medium text-slate-600 cursor-not-allowed">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-1.5">Phone Number</label>
                                <input type="text" name="phone" id="tf-phone"
                                    placeholder="e.g. 08012345678"
                                    class="w-full px-4 py-3 rounded-xl border border-slate-200 bg-slate-50 focus:border-blue-500 focus:ring-2 focus:ring-blue-100 transition focus:bg-white text-sm font-medium">
                            </div>

                            <div>
                                <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-1.5">Reason <span class="text-red-500">*</span></label>
                                <input type="text" name="remarks" id="tf-remarks" required
                                    placeholder="Brief reason for temporary file..."
                                    class="w-full px-4 py-3 rounded-xl border border-slate-200 bg-slate-50 focus:border-blue-500 focus:ring-2 focus:ring-blue-100 transition focus:bg-white text-sm font-medium">
                            </div>

                        
                        </div>

                        <div class="grid grid-cols-2 gap-4 pt-1 border-t border-slate-100">
                            <div>
                                <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1">System Time / Date</label>
                                <input type="text" readonly value="{{ now()->format('H:i | Y-m-d') }}"
                                    class="w-full px-4 py-2.5 rounded-xl border border-slate-200 bg-slate-100 text-sm text-slate-500 cursor-not-allowed">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1">Created By</label>
                                <input type="text" readonly value="{{ auth()->user()->name ?? 'Admin' }}"
                                    class="w-full px-4 py-2.5 rounded-xl border border-slate-200 bg-slate-100 text-sm text-slate-500 cursor-not-allowed">
                            </div>
                        </div>
                    </form>
                </div>

                <div class="px-6 py-4 border-t border-slate-200 bg-slate-50 flex items-center justify-end gap-3">
                    <button type="button" onclick="tfCloseModal()" class="px-4 py-2 text-sm font-medium text-slate-700 bg-white border border-slate-300 rounded-lg hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-slate-500">
                        Cancel
                    </button>
                    <button type="button" id="tf-save-btn" onclick="tfSubmit()" class="px-4 py-2 text-sm font-medium text-white bg-blue-600 border border-transparent rounded-lg hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 flex items-center">
                        <i data-lucide="save" class="w-4 h-4 mr-2"></i> Save Record
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>


{{-- Shared fixed-position action dropdown --}}
<div id="tf-shared-dropdown"
     class="hidden fixed z-[9999] w-56 bg-white border border-slate-200 rounded-xl shadow-xl py-1"
     style="min-width:220px;">
</div>

@include('components.global-fileno-modal')

@endsection

@push('scripts')<script src="{{ asset('js/global-fileno-modal.js') }}"></script>
<script>
    let dt;
    $(document).ready(function() {
        if (window.GlobalFileNoModal) window.GlobalFileNoModal.init();

        dt = $('#tf-table').DataTable({
            pageLength: {{ $limit }},
            order: [],
            bSort: false,
            language: { emptyTable: "No Temporary File applications found" }
        });

        $('#tf-search').on('keyup', function() {
            let val = $(this).val();
            let url = new URL(window.location.href);
            if(val) url.searchParams.set('search', val);
            else url.searchParams.delete('search');
            window.history.replaceState({}, '', url);
            
            // clear timeout
            clearTimeout(window.tfSearchTimeout);
            window.tfSearchTimeout = setTimeout(() => {
                window.location.reload();
            }, 800);
        });

        $('#tf-length').on('change', function() {
            let val = $(this).val();
            let url = new URL(window.location.href);
            url.searchParams.set('limit', val);
            window.location.href = url.href;
        });
    });

    // Shared fixed-position action dropdown
    $(document).on('click', '.tf-action-toggle', function(e) {
        e.stopPropagation();
        const btn       = $(this);
        const id        = btn.data('id');
        const approved  = btn.data('approved') == '1';
        const recGen    = btn.data('rec-generated') == '1';
        const printUrl  = btn.data('print-url');
        const row       = btn.closest('tr');
        const record    = row.data('record');

        // Build menu items
        const editDisabled    = approved;
        const approveDisabled = approved;
        const genDisabled     = !approved || recGen;
        const printDisabled   = !recGen;
        const deleteDisabled  = approved;

        const menu = $('#tf-shared-dropdown');
        menu.html(`
            <button type="button" class="tf-dd-edit w-full text-left px-4 py-2 text-xs flex items-center gap-2 hover:bg-slate-50 ${ editDisabled ? 'opacity-40 cursor-not-allowed text-slate-400' : 'text-amber-700 cursor-pointer' }" ${ editDisabled ? 'disabled' : '' }>
                <i class="fas fa-edit w-4"></i> Edit
            </button>
            <button type="button" class="tf-dd-approve w-full text-left px-4 py-2 text-xs flex items-center gap-2 hover:bg-slate-50 ${ approveDisabled ? 'opacity-40 cursor-not-allowed text-slate-400' : 'text-emerald-700 cursor-pointer' }" ${ approveDisabled ? 'disabled' : '' }>
                <i class="fas fa-check-circle w-4"></i> Approve
            </button>
            <button type="button" class="tf-dd-gen w-full text-left px-4 py-2 text-xs flex items-center gap-2 hover:bg-slate-50 ${ genDisabled ? 'opacity-40 cursor-not-allowed text-slate-400' : 'text-indigo-700 cursor-pointer' }" ${ genDisabled ? 'disabled' : '' }>
                <i class="fas fa-file-alt w-4"></i> Generate Recommendation
            </button>
            <a href="${ printUrl }" target="_blank" class="tf-dd-print w-full text-left px-4 py-2 text-xs flex items-center gap-2 hover:bg-slate-50 ${ printDisabled ? 'opacity-40 pointer-events-none text-slate-400' : 'text-blue-700' }">
                <i class="fas fa-print w-4"></i> Print Recommendation
            </a>
            <div class="border-t border-slate-100 my-1"></div>
            <button type="button" class="tf-dd-delete w-full text-left px-4 py-2 text-xs flex items-center gap-2 hover:bg-slate-50 ${ deleteDisabled ? 'opacity-40 cursor-not-allowed text-slate-400' : 'text-red-600 cursor-pointer' }" ${ deleteDisabled ? 'disabled' : '' }>
                <i class="fas fa-trash w-4"></i> Delete
            </button>
        `);

        // Bind actions
        if (!editDisabled)    menu.find('.tf-dd-edit').on('click', function() { $('#tf-shared-dropdown').addClass('hidden'); tfEdit(row.find('.tf-action-toggle')[0]); });
        if (!approveDisabled) menu.find('.tf-dd-approve').on('click', function() { $('#tf-shared-dropdown').addClass('hidden'); tfApprove(id); });
        if (!genDisabled)     menu.find('.tf-dd-gen').on('click', function() { $('#tf-shared-dropdown').addClass('hidden'); tfGenerateRecommendation(id); });
        if (!deleteDisabled)  menu.find('.tf-dd-delete').on('click', function() { $('#tf-shared-dropdown').addClass('hidden'); tfDelete(row.find('.tf-action-toggle')[0]); });

        // Position fixed relative to button
        const rect = this.getBoundingClientRect();
        const menuW = 220;
        let left = rect.right - menuW;
        if (left < 8) left = 8;
        menu.css({ top: rect.bottom + window.scrollY + 4, left: left + window.scrollX })
            .toggleClass('hidden');
    });
    $(document).on('click', function(e) {
        if (!$(e.target).closest('#tf-shared-dropdown, .tf-action-toggle').length) {
            $('#tf-shared-dropdown').addClass('hidden');
        }
    });

    function tfBuildLocation() {
        // The plot number is not part of a full property location — it has its
        // own field on this card and was only being repeated here.
        let district  = $('#tf-district').val().trim();
        if (district === 'OTHER') {
            district = $('#tf-district_specify').val().trim();
        }
        const lga     = $('#tf-lga').val().trim();
        const parts   = [];
        if (district) parts.push(district);
        if (lga)      parts.push(lga);
        parts.push('Kano State');
        $('#tf-address').val(parts.join(', '));
    }

    // Auto-compose location whenever plot/district/lga changes
    $(document).on('input', '#tf-plot_no, #tf-district_specify', tfBuildLocation);
    $(document).on('change', '#tf-district, #tf-lga', function() {
        if ($(this).attr('id') === 'tf-district') {
            if ($(this).val() === 'OTHER') {
                $('#tf-district-specify-wrapper').removeClass('hidden');
                $('#tf-district_specify').attr('required', true);
            } else {
                $('#tf-district-specify-wrapper').addClass('hidden');
                $('#tf-district_specify').val('').removeAttr('required');
            }
        }
        tfBuildLocation();
    });

    function tfOpenCreate() {
        $('#temporary-file-form')[0].reset();
        $('#tf-id').val('');
        $('#method-override').val('POST');
        $('#modal-title').text('New Temporary File');
        $('#tf-save-btn').html('<i data-lucide="save" class="w-4 h-4 mr-2"></i> Save Record');
        lucide.createIcons();
        
        $('#tf-modal').removeClass('hidden');
        $('#tf-district-specify-wrapper').addClass('hidden');
        $('#tf-district_specify').removeAttr('required');
        setTimeout(() => $('#tf-applicant_name').focus(), 100);
    }

    function tfEdit(btn) {
        let record = $(btn).closest('tr').data('record');
        $('#temporary-file-form')[0].reset();
        
        $('#tf-id').val(record.id);
        $('#method-override').val('PUT');
        $('#modal-title').text('Edit Temporary File');
        $('#tf-save-btn').html('<i data-lucide="save" class="w-4 h-4 mr-2"></i> Update Record');
        
        // Populate fields
        $('#tf-applicant_name').val(record.applicant_name);
        $('#tf-file_no').val(record.file_no);
        $('#tf-plot_no').val(record.plot_no);
        $('#tf-lga').val(record.lga);
        $('#tf-phone').val(record.phone);
        $('#tf-remarks').val(record.remarks);
        
        // Handle District "OTHER" selection on edit
        const districtExists = $('#tf-district option').filter(function() {
            return $(this).val() === record.district;
        }).length > 0;

        if (record.district && !districtExists && record.district !== '') {
            $('#tf-district').val('OTHER');
            $('#tf-district-specify-wrapper').removeClass('hidden');
            $('#tf-district_specify').val(record.district).attr('required', true);
        } else {
            $('#tf-district').val(record.district || '');
            $('#tf-district-specify-wrapper').addClass('hidden');
            $('#tf-district_specify').val('').removeAttr('required');
        }

        tfBuildLocation();
        $('#tf-address').val(record.address);
        
        lucide.createIcons();
        $('#tf-modal').removeClass('hidden');
    }

    function tfCloseModal() {
        $('#tf-modal').addClass('hidden');
    }

    function tfSubmit() {
        const form = $('#temporary-file-form');
        if (!form[0].checkValidity()) {
            form[0].reportValidity();
            return;
        }

        const id = $('#tf-id').val();
        const url = id 
            ? `{{ route('temporary-file.update', '__ID__') }}`.replace('__ID__', id)
            : `{{ route('temporary-file.store') }}`;

        const btn = $('#tf-save-btn');
        const originalText = btn.html();
        btn.html('<i class="fas fa-spinner fa-spin mr-2"></i> Saving...').prop('disabled', true);

        $.ajax({
            url: url,
            type: 'POST', // method override handles PUT
            data: form.serialize(),
            success: function(res) {
                if (res.success) {
                    Swal.fire({ icon: 'success', title: 'Success', text: res.message, timer: 1500, showConfirmButton: false })
                        .then(() => window.location.reload());
                } else {
                    Swal.fire('Error', res.message || 'An error occurred.', 'error');
                    btn.html(originalText).prop('disabled', false);
                }
            },
            error: function(xhr) {
                btn.html(originalText).prop('disabled', false);
                let msg = 'An error occurred while saving.';
                if (xhr.responseJSON && xhr.responseJSON.errors) {
                    msg = Object.values(xhr.responseJSON.errors).map(e => e.join('\n')).join('\n');
                } else if (xhr.responseJSON && xhr.responseJSON.message) {
                    msg = xhr.responseJSON.message;
                }
                Swal.fire('Error', msg, 'error');
            }
        });
    }

    function tfDelete(btn) {
        let record = $(btn).closest('tr').data('record');
        
        Swal.fire({
            title: 'Delete this record?',
            text: "This action cannot be undone.",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#ef4444',
            cancelButtonColor: '#64748b',
            confirmButtonText: 'Yes, delete it!'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: `{{ route('temporary-file.destroy', '__ID__') }}`.replace('__ID__', record.id),
                    type: 'DELETE',
                    data: { _token: '{{ csrf_token() }}' },
                    success: function(res) {
                        if (res.success) {
                            Swal.fire({ icon: 'success', title: 'Deleted!', text: res.message, timer: 1500, showConfirmButton: false })
                                .then(() => window.location.reload());
                        }
                    },
                    error: function() {
                        Swal.fire('Error', 'Failed to delete record.', 'error');
                    }
                });
            }
        });
    }

    function tfOpenFileModal() {
        if (!window.GlobalFileNoModal) {
            Swal.fire('Error', 'File number modal is not available.', 'error');
            return;
        }
        window.GlobalFileNoModal.open({
            callback: function(data) {
                if (data && data.fileNumber) {
                    $('#tf-file_no').val(data.fileNumber);
                    if (data.record) {
                        const r = data.record;
                        const name = r.file_name || r.file_title || r.current_holder || '';
                        if (name) $('#tf-applicant_name').val(name);
                        if (r.phone) $('#tf-phone').val(r.phone);
                        if (r.plot_no) $('#tf-plot_no').val(r.plot_no);
                        if (r.lga) $('#tf-lga').val(r.lga);
                        tfBuildLocation();
                    }
                }
            }
        });
    }

    function tfGenerateRecommendation(id) {
        Swal.fire({
            title: 'Generate Recommendation?',
            text: "This will mark the recommendation as generated and enable printing.",
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#6366f1',
            cancelButtonColor: '#64748b',
            confirmButtonText: 'Yes, generate it!'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: `{{ route('temporary-file.generate-recommendation', '__ID__') }}`.replace('__ID__', id),
                    type: 'POST',
                    data: { _token: '{{ csrf_token() }}' },
                    success: function(res) {
                        if (res.success) {
                            Swal.fire({ icon: 'success', title: 'Done!', text: res.message, timer: 1500, showConfirmButton: false })
                                .then(() => window.location.reload());
                        }
                    },
                    error: function() {
                        Swal.fire('Error', 'Failed to generate recommendation.', 'error');
                    }
                });
            }
        });
    }

    function tfApprove(id) {
        Swal.fire({
            title: 'Approve Application?',
            text: "This will approve the Temporary File.",
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#10b981',
            cancelButtonColor: '#64748b',
            confirmButtonText: 'Yes, approve it!'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: `{{ route('temporary-file.approve', '__ID__') }}`.replace('__ID__', id),
                    type: 'POST',
                    data: { _token: '{{ csrf_token() }}' },
                    success: function(res) {
                        if (res.success) {
                            Swal.fire({ icon: 'success', title: 'Approved!', text: res.message, timer: 1500, showConfirmButton: false })
                                .then(() => window.location.reload());
                        }
                    },
                    error: function() {
                        Swal.fire('Error', 'Failed to approve application.', 'error');
                    }
                });
            }
        });
    }
</script>
@endpush