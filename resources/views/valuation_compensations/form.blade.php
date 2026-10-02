@extends('layouts.app')

@section('page-title')
    {{ isset($record) ? 'Edit Valuation' : 'New Valuation' }}
@endsection

@section('content')
<div x-data="{}" class="flex-1 overflow-auto bg-slate-50/60">
    @include('admin.header', [
        'PageTitle' => isset($record) ? 'Edit Valuation' : 'New Valuation',
        'PageDescription' => 'Fill in the details for the compensation report'
    ])

    <div class="p-6 max-w-4xl mx-auto space-y-6">
        <!-- Breadcrumbs -->
        <nav class="flex text-sm text-slate-500" aria-label="Breadcrumb">
        <ol class="flex items-center space-x-2">
            <li><a href="{{ route('valuation-compensations.index') }}" class="hover:text-teal-600">Valuations</a></li>
            <li><i data-lucide="chevron-right" class="h-4 w-4"></i></li>
            <li class="font-medium text-slate-800">{{ isset($record) ? 'Edit Record' : 'Create New' }}</li>
        </ol>
    </nav>

    <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
        <div class="p-8 border-b border-slate-100 bg-slate-50/50">
            <h1 class="text-2xl font-bold text-slate-800">{{ isset($record) ? 'Edit Valuation for Compensation' : 'New Valuation for Compensation' }}</h1>
            <p class="text-slate-500 mt-1">Fill in the details for the compensation report</p>
        </div>

        <form id="valuationForm" class="p-8 space-y-8">
            @csrf
            @if(isset($record))
                @method('PUT')
            @endif

            <!-- Reference & Date -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-2">Our Ref</label>
                    <input type="text" name="our_ref" value="{{ $record->our_ref ?? 'LS/VAL/FGE/5KM' }}" 
                        class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-lg focus:ring-2 focus:ring-teal-500/20 focus:border-teal-500 outline-none transition-all"
                        placeholder="LS/VAL/FGE/5KM">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-2">Your Ref</label>
                    <input type="text" name="your_ref" value="{{ $record->your_ref ?? '' }}" 
                        class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-lg focus:ring-2 focus:ring-teal-500/20 focus:border-teal-500 outline-none transition-all"
                        placeholder="Optional">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-2 text-rose-600">Valuation Date *</label>
                    <input type="date" name="valuation_date" value="{{ isset($record) ? $record->valuation_date->format('Y-m-d') : date('Y-m-d') }}" required
                        class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-lg focus:ring-2 focus:ring-teal-500/20 focus:border-teal-500 outline-none transition-all">
                </div>
            </div>

            <!-- Owner & Location -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-2 text-rose-600">Name of Owner *</label>
                    <input type="text" name="owner_name" value="{{ $record->owner_name ?? '' }}" required
                        class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-lg focus:ring-2 focus:ring-teal-500/20 focus:border-teal-500 outline-none transition-all"
                        placeholder="Musa Yakubu">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-2 text-rose-600">Location *</label>
                    <input type="text" name="location" value="{{ $record->location ?? '' }}" required
                        class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-lg focus:ring-2 focus:ring-teal-500/20 focus:border-teal-500 outline-none transition-all"
                        placeholder="5KM ROAD PROJECT, FAGGE LGA, KANO">
                </div>
            </div>

            <!-- Building Details -->
            <div class="bg-slate-50 p-6 rounded-xl space-y-6">
                <h3 class="text-sm font-bold text-slate-400 uppercase tracking-widest">Building Specifications</h3>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-2 text-rose-600">Type of Building *</label>
                        <select name="building_type" required
                            class="w-full px-4 py-2.5 bg-white border border-slate-200 rounded-lg focus:ring-2 focus:ring-teal-500/20 focus:border-teal-500 outline-none transition-all">
                            <option value="">Select Type</option>
                            <option value="Residential" {{ (isset($record) && $record->building_type == 'Residential') ? 'selected' : '' }}>Residential</option>
                            <option value="Commercial" {{ (isset($record) && $record->building_type == 'Commercial') ? 'selected' : '' }}>Commercial</option>
                            <option value="Industrial" {{ (isset($record) && $record->building_type == 'Industrial') ? 'selected' : '' }}>Industrial</option>
                            <option value="Shops" {{ (isset($record) && $record->building_type == 'Shops') ? 'selected' : '' }}>Shops</option>
                            <option value="Others" {{ (isset($record) && $record->building_type == 'Others') ? 'selected' : '' }}>Others</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-2">No. of Building</label>
                        <input type="number" name="building_count" id="building_count" value="{{ $record->building_count ?? 1 }}" min="1"
                            class="w-full px-4 py-2.5 bg-white border border-slate-200 rounded-lg focus:ring-2 focus:ring-teal-500/20 focus:border-teal-500 outline-none transition-all">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-2">Area Covered (M²)</label>
                        <input type="number" step="0.01" name="area_covered" id="area_covered" value="{{ $record->area_covered ?? 0 }}" min="0"
                            class="w-full px-4 py-2.5 bg-white border border-slate-200 rounded-lg focus:ring-2 focus:ring-teal-500/20 focus:border-teal-500 outline-none transition-all">
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-2">Rate of Cost (₦)</label>
                        <input type="number" step="0.01" name="rate_of_cost" id="rate_of_cost" value="{{ $record->rate_of_cost ?? 0 }}" min="0"
                            class="w-full px-4 py-2.5 bg-white border border-slate-200 rounded-lg focus:ring-2 focus:ring-teal-500/20 focus:border-teal-500 outline-none transition-all">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-2 text-teal-600 font-bold">Amount of Compensation (₦) *</label>
                        <div class="relative">
                            <span class="absolute left-4 top-1/2 -translate-y-1/2 text-slate-400">₦</span>
                            <input type="number" step="0.01" name="compensation_amount" id="compensation_amount" value="{{ $record->compensation_amount ?? 0 }}" required
                                class="w-full pl-10 pr-4 py-2.5 bg-white border border-teal-200 rounded-lg focus:ring-2 focus:ring-teal-500/20 focus:border-teal-500 outline-none transition-all font-bold text-teal-700 text-lg">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Account & Contact -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-2 text-rose-600">Account Number *</label>
                    <input type="text" name="account_number" value="{{ $record->account_number ?? '' }}" required
                        class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-lg focus:ring-2 focus:ring-teal-500/20 focus:border-teal-500 outline-none transition-all"
                        placeholder="0123456789">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-2 text-rose-600">Phone Number *</label>
                    <input type="text" name="phone_number" value="{{ $record->phone_number ?? '' }}" required
                        class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-lg focus:ring-2 focus:ring-teal-500/20 focus:border-teal-500 outline-none transition-all"
                        placeholder="08030000000">
                </div>
            </div>

            <!-- Remarks -->
            <div>
                <label class="block text-sm font-semibold text-slate-700 mb-2">Remarks</label>
                <textarea name="remarks" rows="3"
                    class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-lg focus:ring-2 focus:ring-teal-500/20 focus:border-teal-500 outline-none transition-all"
                    placeholder="Enter any additional information...">{{ $record->remarks ?? '' }}</textarea>
            </div>

            <!-- Form Actions -->
            <div class="flex items-center justify-end gap-4 pt-4 border-t border-slate-100">
                <a href="{{ route('valuation-compensations.index') }}" class="px-6 py-2.5 text-slate-600 hover:bg-slate-100 rounded-lg transition-all font-medium">Cancel</a>
                <button type="submit" id="saveBtn" class="flex items-center gap-2 bg-teal-600 hover:bg-teal-700 text-white px-8 py-2.5 rounded-lg transition-all shadow-md font-bold disabled:opacity-50">
                    <i data-lucide="save" class="h-5 w-5"></i>
                    <span>{{ isset($record) ? 'Update Record' : 'Save Record' }}</span>
                </button>
            </div>
        </form>
    </div>
</div>

@push('scripts')
<script>
    $(document).ready(function() {
        // Auto-calculate logic (Area * Rate)
        $('#area_covered, #rate_of_cost').on('input', function() {
            const area = parseFloat($('#area_covered').val()) || 0;
            const rate = parseFloat($('#rate_of_cost').val()) || 0;
            const amount = area * rate;
            $('#compensation_amount').val(amount.toFixed(2));
        });

        $('#valuationForm').on('submit', function(e) {
            e.preventDefault();
            
            const btn = $('#saveBtn');
            btn.prop('disabled', true).html('<i data-lucide="loader-2" class="h-5 w-5 animate-spin"></i><span>Saving...</span>');
            lucide.createIcons();

            const url = "{{ isset($record) ? route('valuation-compensations.update', $record->id) : route('valuation-compensations.store') }}";
            const method = "{{ isset($record) ? 'PUT' : 'POST' }}";

            $.ajax({
                url: url,
                type: 'POST', // Laravel SPOOFING
                data: $(this).serialize() + (method === 'PUT' ? '&_method=PUT' : ''),
                success: function(response) {
                    if (response.success) {
                        Swal.fire({
                            title: 'Success!',
                            text: response.message,
                            icon: 'success',
                            confirmButtonColor: '#0d9488'
                        }).then(() => {
                            window.location.href = "{{ route('valuation-compensations.index') }}";
                        });
                    }
                },
                error: function(xhr) {
                    btn.prop('disabled', false).html('<i data-lucide="save" class="h-5 w-5"></i><span>Save Record</span>');
                    lucide.createIcons();
                    
                    if (xhr.status === 422) {
                        const errors = xhr.responseJSON.errors;
                        let errorMsg = '';
                        Object.keys(errors).forEach(key => {
                            errorMsg += `${errors[key][0]}\n`;
                        });
                        Swal.fire('Validation Error', errorMsg, 'error');
                    } else {
                        Swal.fire('Error', 'Something went wrong on the server.', 'error');
                    }
                }
            });
        });
    });
</script>
@endpush
@endsection
