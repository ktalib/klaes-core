@extends('layouts.app')

@section('content')
<div class="flex-1 overflow-auto bg-slate-50/60">
    @include('admin.header')
    <div class="py-12 bg-slate-50 min-h-screen">
        <div class="max-w-[95%] mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-8">
                <div>
                    <h1 class="text-3xl font-extrabold text-slate-900 tracking-tight">OP Verifications</h1>
                    <p class="text-slate-500 text-sm mt-1">Manage and verify OP authenticities.</p>
                </div>
            </div>

            <!-- Table section -->
            <div class="bg-white rounded-3xl border border-slate-200 shadow-sm overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-slate-50/50 border-b border-slate-200 text-[11px] uppercase tracking-wider text-slate-500 font-bold">
                                <th class="px-6 py-4">Applicant</th>
                                <th class="px-6 py-4">OP Details</th>
                                <th class="px-6 py-4">Location</th>
                                <th class="px-6 py-4">Status</th>
                                <th class="px-6 py-4 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="text-sm divide-y divide-slate-100">
                            @forelse($verifications as $v)
                                <tr class="hover:bg-slate-50/50 transition-colors group">
                                    <td class="px-6 py-4">
                                        <div class="font-bold text-slate-900">{{ $v->applicant_name }}</div>
                                        <div class="text-slate-500 text-xs">{{ $v->applicant_phone }}</div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="font-bold text-blue-600">{{ $v->op_number }}</div>
                                        <div class="text-slate-500 text-xs">Plot: {{ $v->plot_no }} | Plan: {{ $v->plan_no }}</div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="text-slate-600 max-w-xs truncate">{{ $v->location }}</div>
                                    </td>
                                    <td class="px-6 py-4">
                                        @if($v->recommendation === 'Recommended')
                                            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-emerald-100 text-emerald-800">
                                                Recommended
                                            </span>
                                        @elseif($v->recommendation === 'Not Recommended')
                                            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                                Not Recommended
                                            </span>
                                        @else
                                            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-amber-100 text-amber-800">
                                                Pending
                                            </span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 text-right">
                                        <button type="button" onclick="openVerifyModal({{ $v->id }}, '{{ addslashes($v->recommendation) }}', '{{ addslashes($v->chairman_name) }}')"
                                            class="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm font-medium text-blue-700 bg-blue-50 rounded-lg hover:bg-blue-100 transition-colors">
                                            <i data-lucide="check-square" class="h-4 w-4"></i>
                                            Verify
                                        </button>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-6 py-12 text-center text-slate-500">
                                        No OP verifications found.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Verify Modal -->
<div id="verifyModal" class="fixed inset-0 z-[100] hidden items-center justify-center">
    <!-- Backdrop -->
    <div class="absolute inset-0 bg-slate-900/40 backdrop-blur-sm transition-opacity opacity-0" id="verifyModalBackdrop"></div>
    
    <!-- Modal Content -->
    <div class="relative bg-white rounded-3xl w-full max-w-md shadow-2xl scale-95 opacity-0 transition-all duration-300 transform" id="verifyModalContent">
        <div class="px-6 py-5 border-b border-slate-100 flex items-center justify-between bg-slate-50/50 rounded-t-3xl">
            <h3 class="text-lg font-bold text-slate-800 flex items-center gap-2">
                <i data-lucide="check-square" class="h-5 w-5 text-blue-600"></i>
                Verify OP
            </h3>
            <button onclick="closeVerifyModal()" type="button" class="text-slate-400 hover:text-slate-600 transition-colors bg-white hover:bg-slate-100 p-2 rounded-xl shadow-sm border border-slate-200">
                <i data-lucide="x" class="h-4 w-4"></i>
            </button>
        </div>
        
        <form id="verifyForm" method="POST" action="">
            @csrf
            <div class="p-6 space-y-6">
                <!-- Recommendation -->
                <div>
                    <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-2">Recommendation</label>
                    <div class="grid grid-cols-2 gap-3">
                        <label class="relative flex items-center gap-3 p-3 border border-slate-200 rounded-xl cursor-pointer hover:bg-slate-50 transition-colors has-[:checked]:border-emerald-500 has-[:checked]:bg-emerald-50 has-[:checked]:ring-1 has-[:checked]:ring-emerald-500">
                            <input type="radio" name="recommendation" value="Recommended" class="h-4 w-4 text-emerald-600 border-slate-300 focus:ring-emerald-600">
                            <span class="text-sm font-semibold text-slate-700">Recommended</span>
                        </label>
                        <label class="relative flex items-center gap-3 p-3 border border-slate-200 rounded-xl cursor-pointer hover:bg-slate-50 transition-colors has-[:checked]:border-red-500 has-[:checked]:bg-red-50 has-[:checked]:ring-1 has-[:checked]:ring-red-500">
                            <input type="radio" name="recommendation" value="Not Recommended" class="h-4 w-4 text-red-600 border-slate-300 focus:ring-red-600">
                            <span class="text-sm font-semibold text-slate-700">Not Recommended</span>
                        </label>
                    </div>
                </div>

                <!-- Chairman Name -->
                <div>
                    <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-2">Chairman Name</label>
                    <div class="relative">
                        <i data-lucide="user" class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-slate-400"></i>
                        <input type="text" name="chairman_name" id="chairman_name" required
                               class="w-full pl-10 pr-4 py-2.5 bg-white border border-slate-200 rounded-xl text-sm focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 outline-none transition-all shadow-sm"
                               placeholder="Enter Chairman's Name">
                    </div>
                </div>
            </div>
            
            <div class="px-6 py-4 bg-slate-50 rounded-b-3xl flex justify-end gap-3 border-t border-slate-100">
                <button type="button" onclick="closeVerifyModal()" class="px-5 py-2.5 text-sm font-bold text-slate-600 hover:text-slate-800 bg-white border border-slate-200 rounded-xl hover:bg-slate-50 transition-colors shadow-sm">
                    Cancel
                </button>
                <button type="submit" class="px-5 py-2.5 text-sm font-bold text-white bg-blue-600 hover:bg-blue-700 rounded-xl transition-all shadow-md shadow-blue-200 flex items-center gap-2">
                    <i data-lucide="save" class="h-4 w-4"></i>
                    Save Verification
                </button>
            </div>
        </form>
    </div>
</div>

@push('scripts')
<script>
    function openVerifyModal(id, currentRecommendation, currentChairman) {
        const modal = document.getElementById('verifyModal');
        const backdrop = document.getElementById('verifyModalBackdrop');
        const content = document.getElementById('verifyModalContent');
        const form = document.getElementById('verifyForm');
        
        // Setup form action
        form.action = `/oss-verifications/${id}/verify`;
        
        // Fill current data
        document.getElementById('chairman_name').value = currentChairman || '';
        if (currentRecommendation) {
            const radio = form.querySelector(`input[name="recommendation"][value="${currentRecommendation}"]`);
            if (radio) radio.checked = true;
        } else {
            // Reset
            const radios = form.querySelectorAll('input[name="recommendation"]');
            radios.forEach(r => r.checked = false);
        }
        
        // Show modal
        modal.classList.remove('hidden');
        modal.classList.add('flex');
        
        // Animate in
        setTimeout(() => {
            backdrop.classList.remove('opacity-0');
            backdrop.classList.add('opacity-100');
            content.classList.remove('opacity-0', 'scale-95');
            content.classList.add('opacity-100', 'scale-100');
        }, 10);
    }
    
    function closeVerifyModal() {
        const modal = document.getElementById('verifyModal');
        const backdrop = document.getElementById('verifyModalBackdrop');
        const content = document.getElementById('verifyModalContent');
        
        // Animate out
        backdrop.classList.remove('opacity-100');
        backdrop.classList.add('opacity-0');
        content.classList.remove('opacity-100', 'scale-100');
        content.classList.add('opacity-0', 'scale-95');
        
        setTimeout(() => {
            modal.classList.remove('flex');
            modal.classList.add('hidden');
        }, 300);
    }
</script>
@endpush
@endsection
