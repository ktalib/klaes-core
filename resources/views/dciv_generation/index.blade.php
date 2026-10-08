@extends('layouts.app')

@section('content')
@push('styles')
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
@endpush
@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="{{ asset('js/global-fileno-modal.js') }}"></script>
    <script src="{{ asset('js/master-delete.js') }}"></script>
    <script src="{{ asset('js/dciv-master-delete.js') }}"></script>
@endpush
<div class="flex-1 overflow-auto bg-slate-50/60"
    x-data='dcivGenerator({
    districts: @json($districts), 
    lgas: @json($lgas),
    landUses: @json($landUses),
    purposes: @json($purposes),
    csrfToken: "{{ csrf_token() }}",
    availableUrl: "{{ route('dciv-generation.available') }}",
    worldTimeUrl: "{{ route('world-time') }}",
    storeUrl: "{{ route('dciv-generation.store') }}",
    commissioningSheetStoreUrl: "{{ route('commissioning-sheet.store') }}",
    commissioningSheetGeneratePrintUrl: "{{ route('commissioning-sheet.generate-print') }}",
    currentDate: "{{ date('Y-m-d') }}",
    currentTimeOnly: "{{ date('H:i:s') }}",
    serialStatuses: @json($serialStatuses),
    serialYears: @json($serialYears),
    currentYear: "{{ date('Y') }}",
    userName: "{{ auth()->user()->first_name }} {{ auth()->user()->last_name }}",
    isSupperAdmin: {{ (isset($user->assign_role) && str_contains($user->assign_role, 'Supper Admin')) ? 'true' : 'false' }}
})'
    @edit-record="editRecord($event.detail)"
    @view-record="viewRecord($event.detail)"
    @open-commissioning-sheet="openCommissioningSheetModal($event.detail)"
    @open-printer-manager="openPrinterManagerFromMenu($event.detail)"
    @open-batch="openBatchModal($event.detail)"
    @open-related-files="openRelatedFilesModal($event.detail)"
    @open-related-files.window="openRelatedFilesModal($event.detail)"
>
    @include('admin.header')

    <div class="py-12 bg-slate-50 min-h-screen">
        <div class="max-w-[95%] mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-8">
                <div>
                    <h1 class="text-3xl font-extrabold text-slate-900 tracking-tight">DCIV File Number Generation</h1>
                    <p class="text-slate-500 text-sm mt-1">Manage and generate DCIV file numbers.</p>
                </div>
                <div class="flex items-center gap-3 w-full md:w-auto">
                    <div class="flex flex-wrap gap-4">
                        <button 
                            @click="showGenerateModal = true"
                            class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg flex items-center space-x-2 transition-all shadow-sm hover:shadow-md active:scale-95">
                            <i data-lucide="plus" class="w-4 h-4"></i>
                            <span class="font-bold">Generate DCIV FileNo</span>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Statistics Cards -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-10">
                <!-- Daily Stats -->
                <div class="bg-white p-6 rounded-3xl border border-slate-200 shadow-sm hover:shadow-md transition-all group overflow-hidden relative">
                    <div class="absolute -right-4 -bottom-4 opacity-[0.03] group-hover:scale-110 transition-transform duration-500">
                        <i data-lucide="calendar-check" class="h-32 w-32 text-blue-600"></i>
                    </div>
                    <div class="flex items-center gap-4 relative z-10">
                        <div class="p-3 bg-blue-50 text-blue-600 rounded-2xl border border-blue-100 shadow-sm">
                            <i data-lucide="calendar-check" class="h-6 w-6"></i>
                        </div>
                        <div>
                            <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Daily Generation</p>
                            <h3 class="text-2xl font-black text-slate-800 tracking-tight">{{ number_format($stats['today']) }}</h3>
                        </div>
                    </div>
                    <div class="mt-4 pt-4 border-t border-slate-50 flex items-center justify-between text-[10px] font-bold text-slate-400 uppercase tracking-widest">
                        <span>New Files Today</span>
                        <span class="text-emerald-500 flex items-center gap-1">
                            Live Update
                            <span class="relative flex h-2 w-2">
                                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                                <span class="relative inline-flex rounded-full h-2 w-2 bg-emerald-500"></span>
                            </span>
                        </span>
                    </div>
                </div>

                <!-- Monthly Stats -->
                <div class="bg-white p-6 rounded-3xl border border-slate-200 shadow-sm hover:shadow-md transition-all group overflow-hidden relative">
                    <div class="absolute -right-4 -bottom-4 opacity-[0.03] group-hover:scale-110 transition-transform duration-500">
                        <i data-lucide="bar-chart-3" class="h-32 w-32 text-indigo-600"></i>
                    </div>
                    <div class="flex items-center gap-4 relative z-10">
                        <div class="p-3 bg-indigo-50 text-indigo-600 rounded-2xl border border-indigo-100 shadow-sm">
                            <i data-lucide="bar-chart-3" class="h-6 w-6"></i>
                        </div>
                        <div>
                            <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Monthly Generation</p>
                            <h3 class="text-2xl font-black text-slate-800 tracking-tight">{{ number_format($stats['month']) }}</h3>
                        </div>
                    </div>
                    <div class="mt-4 pt-4 border-t border-slate-50 flex items-center justify-between text-[10px] font-bold text-slate-400 uppercase tracking-widest">
                        <span>Total this month</span>
                        <span class="text-indigo-500">{{ date('F Y') }}</span>
                    </div>
                </div>

                <!-- Total Stats -->
                <div class="p-6 rounded-3xl shadow-sm hover:shadow-md transition-all group overflow-hidden relative text-white bg-gradient-to-br from-blue-600 to-blue-800 border-none">
                    <div class="absolute -right-4 -bottom-4 opacity-10 group-hover:scale-110 transition-transform duration-500">
                        <i data-lucide="database" class="h-32 w-32 text-white"></i>
                    </div>
                    <div class="flex items-center gap-4 relative z-10">
                        <div class="p-3 bg-white/20 text-white rounded-2xl border border-white/30 shadow-sm backdrop-blur-md">
                            <i data-lucide="database" class="h-6 w-6"></i>
                        </div>
                        <div>
                            <p class="text-[10px] font-black text-blue-100 uppercase tracking-widest">Total</p>
                            <h3 class="text-2xl font-black tracking-tight text-white">{{ number_format($stats['total']) }}</h3>
                        </div>
                    </div>
                    <div class="mt-4 pt-4 border-t border-white/10 flex items-center justify-between text-[10px] font-bold text-blue-100 uppercase tracking-widest">
                        <span>All DCIV Records</span>
                        <span class="px-2 py-0.5 bg-white/20 text-white rounded-lg border border-white/20">DCIV</span>
                    </div>
                </div>
            </div>

            <!-- Existing Records Table -->
            <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
                <div class="bg-slate-50 px-6 py-4 border-b border-slate-200 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
                    <h3 class="font-bold text-slate-800">Generated DCIV Records</h3>
                    <!-- Source legend -->
                    <div class="flex items-center gap-3 flex-wrap">
                        <span class="text-[9px] font-black text-slate-400 uppercase tracking-widest">Source Key:</span>
                        <span class="inline-flex items-center gap-1.5">
                            <span class="w-2.5 h-2.5 rounded-sm bg-emerald-200 border border-emerald-300 flex-shrink-0"></span>
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[9px] font-black bg-emerald-50 text-emerald-700 border border-emerald-100 uppercase tracking-widest">Commissioned</span>
                            <span class="text-[9px] text-slate-400 hidden sm:inline">dciv_file_no</span>
                        </span>
                        <span class="inline-flex items-center gap-1.5">
                            <span class="w-2.5 h-2.5 rounded-sm bg-amber-200 border border-amber-300 flex-shrink-0"></span>
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[9px] font-black bg-amber-50 text-amber-700 border border-amber-100 uppercase tracking-widest">Indexed File</span>
                            <span class="text-[9px] text-slate-400 hidden sm:inline">file_indexings</span>
                        </span>
                    </div>
                </div>
                <div class="overflow-x-auto p-4">
                    <table id="dciv-records-table" class="w-full text-left border-collapse display" style="width:100%">
                        <thead>
                            <tr class="bg-slate-50/50 border-b border-slate-200 text-[10px] font-black text-slate-500 uppercase tracking-widest">
                                <th class="px-6 py-4 text-center whitespace-nowrap">S/N</th>
                                <th class="px-6 py-4 whitespace-nowrap">File Number</th>
                                <th class="px-6 py-4 text-center whitespace-nowrap">
                                    Related FileNo
                                    <div class="text-[8px] font-bold text-slate-300 normal-case tracking-normal mt-0.5">dciv_link &middot; file_indexing_links</div>
                                </th>
                                <th class="px-6 py-4 whitespace-nowrap">File Title</th>
                                <th class="px-6 py-4 text-center whitespace-nowrap">TP No</th>
                                <th class="px-6 py-4 text-center whitespace-nowrap">Plot No</th>
                                <th class="px-6 py-4 whitespace-nowrap">LGA</th>
                                <th class="px-6 py-4 whitespace-nowrap">Location</th>
                                <th class="px-6 py-4 whitespace-nowrap">Commissioned By</th>
                                <th class="px-6 py-4 text-center whitespace-nowrap">Time Commissioned</th>
                                <th class="px-6 py-4 text-center whitespace-nowrap">Date Commissioned</th>
                                <th class="px-6 py-4 text-center whitespace-nowrap">Source</th>
                                <th class="px-6 py-4 text-right whitespace-nowrap">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-sm text-slate-600">
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Generation Modal ->
            <!-- Generation Modal -->
            <div 
                x-show="showGenerateModal" 
                class="fixed inset-0 z-[100] overflow-y-auto"
                x-transition:enter="transition ease-out duration-300"
                x-transition:enter-start="opacity-0"
                x-transition:enter-end="opacity-100"
                x-transition:leave="transition ease-in duration-200"
                x-transition:leave-start="opacity-100"
                x-transition:leave-end="opacity-0"
                style="display: none;"
                @keydown.escape.window="showGenerateModal = false"
            >
                <div class="flex items-center justify-center min-h-screen px-4 py-6 text-center">
                    <div class="fixed inset-0 transition-opacity bg-slate-900/60 backdrop-blur-sm" @click="showGenerateModal = false"></div>

                    <div 
                        class="inline-block w-full max-w-4xl my-8 overflow-hidden text-left align-middle transition-all transform bg-transparent rounded-3xl"
                        x-show="showGenerateModal"
                        x-transition:enter="transition ease-out duration-300"
                        x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                        x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
                        x-transition:leave="transition ease-in duration-200"
                        x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
                        x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                    >
                        <div class="bg-slate-50 flex flex-col max-h-[90vh] shadow-2xl rounded-3xl border border-slate-200 overflow-hidden">
                            <!-- Modal Header -->
                            <div class="bg-white px-8 py-5 border-b border-slate-200 flex justify-between items-center">
                                <div>
                                    <h2 class="text-xl font-bold text-slate-900">Generate DCIV File Numbers</h2>
                                    <p class="text-sm text-slate-500">Configure and generate new serial-based records</p>
                                </div>
                                <div class="flex items-center gap-6">
                                    <div class="flex items-center gap-3 bg-slate-50 px-4 py-2 rounded-xl border border-slate-200 shadow-sm">
                                        <span class="text-xs font-bold text-slate-600 uppercase tracking-tight">Batch Mode</span>
                                        <button 
                                            @click="batchMode = !batchMode" 
                                            type="button"
                                            :class="batchMode ? 'bg-blue-600' : 'bg-slate-200'"
                                            class="relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none"
                                        >
                                            <span :class="batchMode ? 'translate-x-5' : 'translate-x-0'" class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out"></span>
                                        </button>
                                    </div>
                                    <button @click="showGenerateModal = false" class="text-slate-400 hover:text-red-500 transition-colors">
                                        <i data-lucide="x-circle" class="h-8 w-8"></i>
                                    </button>
                                </div>
                            </div>

                            <div class="flex-1 overflow-y-auto px-8 py-6 relative">
                                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                                    <!-- Card 1: File Details -->
                                    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
                                        <div class="bg-slate-50 px-6 py-3 border-b border-slate-200 flex items-center gap-2">
                                            <i data-lucide="file-text" class="h-4 w-4 text-blue-600"></i>
                                            <h2 class="font-bold text-sm text-slate-800 uppercase tracking-wide">File Details</h2>
                                        </div>
                                        <div class="p-5 space-y-4">
                                            <div>
                                                <label class="block text-xs font-bold text-slate-600 mb-1 uppercase">Prefix <span class="text-red-500">*</span></label>
                                                <select x-model="formData.prefix" class="w-full bg-white border border-slate-300 rounded-xl px-4 py-2.5 text-sm font-medium text-slate-700 focus:outline-none focus:ring-4 focus:ring-blue-500/10 focus:border-blue-500 transition-all hover:border-slate-400 shadow-sm">
                                                    <option value="DCIV">DCIV</option>
                                                    <option value="LPCC">LPCC</option>
                                                </select>
                                            </div>

                                            <div>
                                                <label class="block text-xs font-bold text-slate-600 mb-1 uppercase">File Title <span class="text-red-500 ">*</span></label>
                                                <input type="text" x-model="formData.file_title" placeholder="Enter File Title" class="w-full bg-white border border-slate-300 rounded-xl px-4 py-2.5 text-sm font-medium text-slate-700 placeholder:text-slate-400 focus:outline-none focus:ring-4 focus:ring-blue-500/10 focus:border-blue-500 transition-all hover:border-slate-400 shadow-sm">
                                            </div>  



                                             <!-- Reason why the file is under investigation  -->
                                             <div>
                                                <label class="block text-xs font-bold text-slate-600 mb-1 uppercase">Reason why the file is under investigation <span class="text-red-500 ">*</span></label>
                                                <input type="text" x-model="formData.reason" placeholder="Enter Reason" class="w-full bg-white border border-slate-300 rounded-xl px-4 py-2.5 text-sm font-medium text-slate-700 placeholder:text-slate-400 focus:outline-none focus:ring-4 focus:ring-blue-500/10 focus:border-blue-500 transition-all hover:border-slate-400 shadow-sm">
                                            </div>

                                            <!-- Location Details -->
                                            <div class="mt-4 pt-4 border-t border-slate-100">
                                                <div class="flex items-center justify-between mb-3">
                                                    <div class="flex items-center gap-2">
                                                        <i data-lucide="map-pin" class="h-4 w-4 text-blue-600"></i>
                                                        <h2 class="font-bold text-[10px] text-slate-500 uppercase tracking-widest">Location Details</h2>
                                                    </div>
                                                    <template x-if="batchMode && !editMode">
                                                        <div class="flex items-center gap-2">
                                                            <span class="px-2 py-0.5 bg-blue-100 text-blue-700 text-[10px] font-black rounded-full shadow-sm">Entry <span x-text="currentEntryIndex + 1"></span> of <span x-text="formData.quantity"></span></span>
                                                            <div class="flex items-center gap-1.5 ml-1">
                                                                <button @click="previousEntry()" :disabled="currentEntryIndex === 0" type="button" class="w-6 h-6 flex items-center justify-center bg-blue-600 hover:bg-blue-700 text-white rounded-full disabled:bg-slate-200 disabled:text-slate-400 transition-all shadow-md active:scale-90">
                                                                    <i data-lucide="chevron-left" class="h-3 w-3 stroke-[3]"></i>
                                                                </button>
                                                                <button @click="nextEntry()" :disabled="currentEntryIndex >= locationEntries.length - 1" type="button" class="w-6 h-6 flex items-center justify-center bg-blue-600 hover:bg-blue-700 text-white rounded-full disabled:bg-slate-200 disabled:text-slate-400 transition-all shadow-md active:scale-90">
                                                                    <i data-lucide="chevron-right" class="h-3 w-3 stroke-[3]"></i>
                                                                </button>
                                                            </div>
                                                        </div>
                                                    </template>
                                                </div>

                                                <!-- Batch Sync Controls -->
                                                <template x-if="batchMode && !editMode">
                                                    <div class="mb-4 space-y-2 pb-3 border-b border-slate-50">
                                                        <label class="flex items-center gap-2 cursor-pointer group">
                                                            <input type="checkbox" x-model="applyLocationToAll" class="w-3.5 h-3.5 rounded border-slate-300 text-blue-600 focus:ring-blue-500 cursor-pointer">
                                                            <span class="text-[10px] font-bold text-slate-600 group-hover:text-blue-600 transition-colors uppercase tracking-tight">Apply Location to All Files in Batch</span>
                                                        </label>
                                                    </div>
                                                </template>

                                                <div class="grid grid-cols-2 gap-4">
                                                    <div>
                                                        <label class="block text-xs font-bold text-slate-600 mb-1 uppercase">Plot Number</label>
                                                        <input type="text" 
                                                               x-model="current.plot_number"
                                                               :disabled="viewMode"
                                                               @input="if(applyLocationToAll) syncToAll('plot_number')"
                                                               placeholder="e.g., 402" 
                                                               class="w-full bg-white border border-slate-300 rounded-xl px-4 py-2.5 text-sm font-medium text-slate-700 placeholder:text-slate-400 focus:outline-none focus:ring-4 focus:ring-blue-500/10 focus:border-blue-500 transition-all hover:border-slate-400 shadow-sm disabled:bg-slate-50 disabled:text-slate-500">
                                                    </div>
                                                    <div>
                                                        <label class="block text-xs font-bold text-slate-600 mb-1 uppercase">TP Number</label>
                                                        <input type="text" 
                                                               x-model="current.tp_no"
                                                               :disabled="viewMode"
                                                               @input="if(applyLocationToAll) syncToAll('tp_no')"
                                                               placeholder="e.g., TP/1024" 
                                                               class="w-full bg-white border border-slate-300 rounded-xl px-4 py-2.5 text-sm font-medium text-slate-700 placeholder:text-slate-400 focus:outline-none focus:ring-4 focus:ring-blue-500/10 focus:border-blue-500 transition-all hover:border-slate-400 shadow-sm disabled:bg-slate-50 disabled:text-slate-500">
                                                    </div>
                                                </div>

                                                <div class="grid grid-cols-2 gap-4 mt-4">
                                                    <div>
                                                        <label class="block text-xs font-bold text-slate-600 mb-1 uppercase text-left">District</label>
                                                        <select x-model="current.district_id" 
                                                                :disabled="viewMode"
                                                                @change="if(applyLocationToAll) syncToAll('district_id')"
                                                                class="w-full bg-white border border-slate-300 rounded-xl px-4 py-2.5 text-sm font-medium text-slate-700 focus:outline-none focus:ring-4 focus:ring-blue-500/10 focus:border-blue-500 transition-all hover:border-slate-400 shadow-sm disabled:bg-slate-50 disabled:text-slate-500 appearance-none bg-no-repeat bg-[right_1rem_center] bg-[length:1em_1em]"
                                                                style="background-image: url('data:image/svg+xml;charset=utf-8,%3Csvg xmlns=%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22 fill=%22none%22 viewBox=%220 0 20 20%22%3E%3Cpath stroke=%22%236b7280%22 stroke-linecap=%22round%22 stroke-linejoin=%22round%22 stroke-width=%221.5%22 d=%22m6 8 4 4 4-4%22%2F%3E%3C%2Fsvg%3E')">
                                                            <option value="">Select District</option>
                                                            <template x-for="district in districts" :key="district.id">
                                                                <option :value="district.id" x-text="district.name"></option>
                                                            </template>
                                                            <option value="Other">Other (Specify)</option>
                                                        </select>
                                                        <!-- Custom District Input -->
                                                        <div x-show="current.district_id === 'Other'" class="mt-2 animate-in fade-in slide-in-from-top-1 duration-200">
                                                            <input type="text" 
                                                                   x-model="current.custom_district" 
                                                                   @input="if(applyLocationToAll) syncToAll('custom_district')"
                                                                   placeholder="Specify District" 
                                                                   class="w-full bg-white border border-slate-300 rounded-xl px-4 py-2 text-xs font-bold text-blue-600 placeholder:text-slate-400 focus:outline-none focus:ring-4 focus:ring-blue-500/10 focus:border-blue-500 transition-all shadow-sm">
                                                        </div>
                                                        </select>
                                                    </div>
                                                    <div>
                                                        <label class="block text-xs font-bold text-slate-600 mb-1 uppercase text-left">LGA</label>
                                                        <select x-model="current.lga_id" 
                                                                :disabled="viewMode"
                                                                @change="if(applyLocationToAll) syncToAll('lga_id')"
                                                                class="w-full bg-white border border-slate-300 rounded-xl px-4 py-2.5 text-sm font-medium text-slate-700 focus:outline-none focus:ring-4 focus:ring-blue-500/10 focus:border-blue-500 transition-all hover:border-slate-400 shadow-sm disabled:bg-slate-50 disabled:text-slate-500 appearance-none bg-no-repeat bg-[right_1rem_center] bg-[length:1em_1em]"
                                                                style="background-image: url('data:image/svg+xml;charset=utf-8,%3Csvg xmlns=%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22 fill=%22none%22 viewBox=%220 0 20 20%22%3E%3Cpath stroke=%22%236b7280%22 stroke-linecap=%22round%22 stroke-linejoin=%22round%22 stroke-width=%221.5%22 d=%22m6 8 4 4 4-4%22%2F%3E%3C%2Fsvg%3E')">
                                                            <option value="">Select LGA</option>
                                                            <template x-for="lga in lgas" :key="lga.id">
                                                                <option :value="lga.id" x-text="lga.name"></option>
                                                            </template>
                                                        </select>
                                                    </div>
                                                </div>
                                                <div class="mt-4">
                                                    <label class="block text-xs font-bold text-slate-600 mb-1 uppercase tracking-tight">Location Preview (Auto-Generated)</label>
                                                    <textarea x-text="location" rows="1" readonly class="w-full bg-slate-50 border-slate-200 rounded-lg text-slate-500 px-4 py-2.5 text-xs font-medium italic shadow-inner outline-none"></textarea>
                                                </div>
                                            </div>

       
                                            <!-- Dynamic Related Files Section -->
                                            <div class="mt-4 pt-4 border-t border-slate-100">
                                                <div class="flex items-center justify-between mb-3">
                                                    <label class="text-[10px] font-black text-slate-500 uppercase tracking-widest flex items-center gap-2 cursor-pointer select-none">
                                                        <input type="checkbox" x-model="enableRelatedFiles" class="w-4 h-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500 cursor-pointer">
                                                        <i data-lucide="link" class="h-3 w-3"></i> Related Files
                                                    </label>
                                                    <button type="button" @click="addRelatedFile()" x-show="enableRelatedFiles" class="inline-flex items-center gap-1.5 px-3 py-1.5 text-[10px] font-bold bg-blue-50 text-blue-600 rounded-lg hover:bg-blue-600 hover:text-white transition-all shadow-sm border border-blue-100 hover:border-blue-600">
                                                        <i data-lucide="plus" class="h-3 w-3"></i> Add Row
                                                    </button>
                                                </div>

                                                <div class="space-y-4" x-show="enableRelatedFiles" x-transition>
                                                    <template x-for="(rel, index) in formData.relatedFiles" :key="index">
                                                        <div class="bg-slate-50/50 p-4 rounded-2xl border border-slate-200 group animate-in fade-in slide-in-from-top-2 duration-300">
                                                            <div class="flex items-start gap-4">
                                                                <div class="flex-1 space-y-4">
                                                                    <div>
                                                                        <label class="block text-[10px] font-black text-slate-500 mb-1.5 uppercase tracking-wider">Related File Number <span class="text-red-500">*</span></label>
                                                                        <div class="relative group/input">
                                                                            <input type="text" 
                                                                                   x-model="rel.file_number" 
                                                                                   readonly 
                                                                                   @click="openRelatedFileSelector(index)"
                                                                                   placeholder="Click to select file..." 
                                                                                   :class="enableRelatedFiles && !rel.file_number ? 'border-red-300 ring-2 ring-red-100' : 'border-slate-300'"
                                                                                   class="w-full bg-slate-100 rounded-xl px-4 py-2.5 text-xs font-bold text-slate-700 cursor-pointer hover:bg-slate-200 transition-all shadow-sm">
                                                                            <div class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 group-hover/input:text-blue-500 transition-colors">
                                                                                <i data-lucide="search" class="h-4 w-4"></i>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                    <div>
                                                                        <label class="block text-[10px] font-black text-slate-500 mb-1.5 uppercase tracking-wider">Related File Title <span class="text-red-500">*</span></label>
                                                                        <input type="text" 
                                                                               x-model="rel.secondary_title" 
                                                                               :readonly="rel.is_found"
                                                                               :class="[rel.is_found ? 'bg-slate-100 cursor-not-allowed' : 'bg-white', enableRelatedFiles && !rel.secondary_title ? 'border-red-300 ring-2 ring-red-100' : 'border-slate-300']"
                                                                               placeholder="Enter Secondary Title" 
                                                                               class="w-full rounded-xl px-4 py-2.5 text-xs font-medium text-slate-700 placeholder:text-slate-400 focus:outline-none focus:ring-4 focus:ring-blue-500/10 focus:border-blue-500 transition-all hover:border-slate-400 shadow-sm">
                                                                    </div>
                                                                </div>
                                                                <div class="pt-6">
                                                                    <button type="button" @click="removeRelatedFile(index)" class="p-2.5 text-slate-400 hover:text-red-500 bg-white hover:bg-red-50 rounded-xl border border-slate-200 hover:border-red-200 transition-all shadow-sm">
                                                                        <i data-lucide="trash-2" class="h-4 w-4"></i>
                                                                    </button>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </template>

                                                    <div x-show="formData.relatedFiles.length === 0" class="hidden">
                                                    </div>
                                                </div>
                                            </div>



                                        </div>
                                    </div>

                                    <!-- Card 2: File Number Details -->
                                    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
                                        <div class="bg-slate-50 px-6 py-3 border-b border-slate-200 flex items-center gap-2">
                                            <i data-lucide="hash" class="h-4 w-4 text-blue-600"></i>
                                            <h2 class="font-bold text-sm text-slate-800 uppercase tracking-wide">File Number Details</h2>
                                        </div>
                                        <div class="p-5 space-y-4">
                                            <div x-show="batchMode" x-transition>
                                                <label class="block text-xs font-bold text-slate-600 mb-1 uppercase">Batch Quantity</label>
                                                <input type="number" x-model="formData.quantity" min="1" max="50" class="w-full bg-white border border-slate-300 rounded-xl px-4 py-2.5 text-sm font-medium text-slate-700 placeholder:text-slate-400 focus:outline-none focus:ring-4 focus:ring-blue-500/10 focus:border-blue-500 transition-all hover:border-slate-400 shadow-sm">
                                            </div>

                                            <div class="grid grid-cols-2 gap-4">
                                                <div>
                                                    <label class="block text-xs font-bold text-slate-600 mb-1 uppercase">Year</label>
                                                    {{-- The serial series is per year, so a manual serial picks its year too:
                                                         DCIV-2025-400 and DCIV-2026-400 are different numbers issued by
                                                         different counters. Only years with an initialized counter for the
                                                         selected prefix are offered. Auto is always the current year. --}}
                                                    <template x-if="serialMode === 'manual' && !editMode">
                                                        <select x-model="formData.year" @change="onYearPicked()"
                                                            class="w-full bg-white border border-slate-300 rounded-xl px-4 py-2.5 text-sm font-medium text-slate-700 focus:outline-none focus:ring-4 focus:ring-blue-500/10 focus:border-blue-500 transition-all hover:border-slate-400 shadow-sm">
                                                            <template x-for="y in availableYears" :key="y.year">
                                                                <option :value="String(y.year)"
                                                                        x-text="y.initialized ? y.year : y.year + ' — no counter'"></option>
                                                            </template>
                                                        </select>
                                                    </template>
                                                    <template x-if="!(serialMode === 'manual' && !editMode)">
                                                        <input type="number" x-model="formData.year" disabled class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-sm font-medium text-slate-500 focus:outline-none shadow-sm cursor-not-allowed" placeholder="Year">
                                                    </template>
                                                </div>
                                                <div>
                                                    {{-- Manual is the default: the operator types the serial and the
                                                         tracking ID is looked up for that exact number. Auto hands the
                                                         choice back to dciv_serial_control (last_serial + 1). --}}
                                                    <div class="flex items-center justify-between mb-1 gap-2">
                                                        <label class="block text-xs font-bold text-slate-600 uppercase">Serial Number</label>
                                                        <div x-show="!editMode" class="inline-flex rounded-lg bg-slate-100 p-0.5 border border-slate-200">
                                                            <button type="button" @click="setSerialMode('manual')"
                                                                :class="serialMode === 'manual' ? 'bg-white text-blue-700 shadow-sm' : 'text-slate-500 hover:text-slate-700'"
                                                                class="px-2 py-0.5 rounded-md text-[9px] font-black uppercase tracking-widest transition">Manual</button>
                                                            <button type="button" @click="setSerialMode('auto')"
                                                                :class="serialMode === 'auto' ? 'bg-white text-blue-700 shadow-sm' : 'text-slate-500 hover:text-slate-700'"
                                                                class="px-2 py-0.5 rounded-md text-[9px] font-black uppercase tracking-widest transition">Auto</button>
                                                        </div>
                                                    </div>
                                                    <input type="number" min="1" x-model="formData.serial_number"
                                                        @input.debounce.400ms="onSerialTyped()"
                                                        :disabled="serialMode === 'auto' || editMode"
                                                        :class="(serialMode === 'auto' || editMode)
                                                            ? 'bg-slate-50 border-slate-200 text-slate-500 cursor-not-allowed'
                                                            : 'bg-white border-slate-300 text-slate-700 hover:border-slate-400 focus:ring-4 focus:ring-blue-500/10 focus:border-blue-500'"
                                                        class="w-full border rounded-xl px-4 py-2.5 text-sm font-medium focus:outline-none transition-all shadow-sm" placeholder="Serial">
                                                    <p x-show="serialMode === 'auto' && !editMode" class="mt-1 text-[10px] font-semibold text-slate-400 uppercase tracking-wide">Assigned by the system</p>
                                                </div>
                                            </div>
                                            <div>
                                                <label class="block text-xs font-bold text-slate-600 mb-1 uppercase" x-text="(editMode ? 'Full File Number' : (batchMode ? 'Number Range' : 'Full File Number'))"></label>
                                                <div class="px-4 py-2 bg-blue-50 border border-blue-200 rounded-lg font-mono text-xs text-blue-700 font-bold shadow-inner"
                                                     :class="editMode ? 'opacity-50 select-none' : ''" 
                                                     x-text="editMode ? formData.full_file_number : (batchMode ? (formData.prefix + '-' + formData.year + '-' + formData.serial_number + ' - ' + formData.prefix + '-' + formData.year + '-' + (parseInt(formData.serial_number) + parseInt(formData.quantity) - 1)) : (formData.prefix + '-' + formData.year + '-' + formData.serial_number))"></div>
                                            </div>

                                            <div>
                                                <label class="block text-xs font-bold text-slate-600 mb-1 uppercase">Tracking ID</label>
                                                <div class="px-4 py-2 bg-red-50 border border-red-100 rounded-lg font-mono text-xs text-red-600 font-extrabold shadow-inner" 
                                                     x-text="loadingTrackingId ? 'Searching...' : (nextDciv ? (batchMode ? nextDciv.tracking_id + ' (start)' : nextDciv.tracking_id) : 'Not Available')"></div>
                                            </div>
                                        </div>
                                    </div>


                                    <!-- Card 4: Metadata -->
                                    <div class="lg:col-span-2 bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
                                        <div class="bg-slate-50 px-6 py-3 border-b border-slate-200 flex items-center gap-2">
                                            <i data-lucide="info" class="h-4 w-4 text-blue-600"></i>
                                            <h2 class="font-bold text-sm text-slate-800 uppercase tracking-wide">Generation Metadata</h2>
                                        </div>
                                        <div class="p-5 grid grid-cols-1 md:grid-cols-3 gap-6">
                                            <div class="bg-slate-50/50 p-4 rounded-xl border border-slate-100">
                                                <label class="block text-[10px] font-black text-slate-400 mb-2 uppercase tracking-widest">Commissioning Date</label>
                                                <div class="flex items-center gap-3 text-slate-700">
                                                    <div class="p-2 bg-white rounded-lg shadow-sm border border-slate-200">
                                                        <i data-lucide="calendar" class="h-4 w-4 text-blue-600"></i>
                                                    </div>
                                                    <span class="text-sm font-bold" x-text="currentDate"></span>
                                                </div>
                                            </div>
                                            <div class="bg-slate-50/50 p-4 rounded-xl border border-slate-100">
                                                <label class="block text-[10px] font-black text-slate-400 mb-2 uppercase tracking-widest">Commissioning Time</label>
                                                <div class="flex items-center gap-3 text-slate-700">
                                                    <div class="p-2 bg-white rounded-lg shadow-sm border border-slate-200">
                                                        <i data-lucide="clock" class="h-4 w-4 text-blue-600"></i>
                                                    </div>
                                                    <span class="text-sm font-bold" x-text="currentTimeOnly"></span>
                                                </div>
                                            </div>
                                            <div class="bg-slate-50/50 p-4 rounded-xl border border-slate-100">
                                                <label class="block text-[10px] font-black text-slate-400 mb-2 uppercase tracking-widest">Commissioned By</label>
                                                <div class="flex items-center gap-3 text-slate-700">
                                                    <div class="p-2 bg-white rounded-lg shadow-sm border border-slate-200">
                                                        <i data-lucide="user" class="h-4 w-4 text-blue-600"></i>
                                                    </div>
                                                    <span class="text-sm font-bold">{{ $user->name ?? $user->email }}</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="px-8 py-5 border-t border-slate-100 bg-white flex items-center justify-end gap-3 font-[Inter] sticky bottom-0 z-10">
                                    <button @click="showGenerateModal = false" class="px-6 py-2.5 text-[11px] font-black uppercase tracking-wider text-slate-500 hover:text-slate-700 hover:bg-slate-50 rounded-xl transition-all">
                                        <span>Cancel</span>
                                    </button>
                                    <button @click="generateDciv()" :disabled="loading || !nextDciv || nextDciv.tracking_id === 'Not Found' || nextDciv.tracking_id === 'Already Used' || nextDciv.tracking_id === 'Error' || loadingTrackingId" class="px-8 py-2.5 bg-blue-600 hover:bg-blue-700 disabled:bg-slate-400 disabled:cursor-not-allowed text-white text-[11px] font-black uppercase tracking-widest rounded-xl transition-all active:scale-95 shadow-lg shadow-blue-100 flex items-center gap-2 min-w-[140px] justify-center">
                                        <template x-if="loading">
                                             <i data-lucide="loader-2" class="h-4 w-4 animate-spin"></i>
                                        </template>
                                        <span x-text="loading ? 'GENERATING...' : 'GENERATE'">GENERATE</span>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Edit/View Modal -->
            <div 
                x-show="showEditModal" 
                class="fixed inset-0 z-[100] overflow-y-auto"
                x-transition:enter="transition ease-out duration-300"
                x-transition:enter-start="opacity-0"
                x-transition:enter-end="opacity-100"
                x-transition:leave="transition ease-in duration-200"
                x-transition:leave-start="opacity-100"
                x-transition:leave-end="opacity-0"
                style="display: none;"
                @keydown.escape.window="showEditModal = false"
            >
                <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
                    <div class="fixed inset-0 transition-opacity bg-slate-900/60 backdrop-blur-sm" @click="showEditModal = false"></div>

                    <div 
                        class="inline-block w-full max-w-4xl my-8 overflow-hidden text-left align-middle transition-all transform bg-transparent rounded-3xl"
                        x-show="showEditModal"
                        x-transition:enter="transition ease-out duration-300"
                        x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                        x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
                        x-transition:leave="transition ease-in duration-200"
                        x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
                        x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                    >
                        <div class="bg-slate-50 flex flex-col max-h-[85vh] shadow-2xl rounded-3xl border border-slate-200 overflow-hidden">
                            <!-- Modal Header -->
                            <div class="bg-white px-8 py-5 border-b border-slate-200 flex justify-between items-center">
                                <div>
                                    <h2 class="text-xl font-bold text-slate-900" x-text="viewMode ? 'View DCIV Record' : 'Edit DCIV Record'"></h2>
                                    <p class="text-sm text-slate-500" x-text="viewMode ? 'Viewing metadata for ' + formData.full_file_number : 'Updating metadata for ' + formData.full_file_number"></p>
                                </div>
                                <button @click="showEditModal = false" class="text-slate-400 hover:text-red-500 transition-colors">
                                    <i data-lucide="x-circle" class="h-8 w-8"></i>
                                </button>
                            </div>

                            <div class="flex-1 overflow-y-auto p-8 relative min-h-[400px]">
                                <!-- Loading Overlay -->
                                <div x-show="loading" 
                                     class="absolute inset-0 z-50 bg-white/80 backdrop-blur-[2px] flex flex-col items-center justify-center gap-4 transition-all"
                                     x-transition:enter="transition ease-out duration-300"
                                     x-transition:enter-start="opacity-0"
                                     x-transition:enter-end="opacity-100"
                                >
                                    <div class="relative">
                                        <div class="w-16 h-16 border-4 border-blue-100 border-t-blue-600 rounded-full animate-spin"></div>
                                        <div class="absolute inset-0 flex items-center justify-center">
                                            <i data-lucide="file-text" class="h-6 w-6 text-blue-600 animate-pulse"></i>
                                        </div>
                                    </div>
                                    <div class="flex flex-col items-center">
                                        <span class="text-sm font-black text-slate-800 uppercase tracking-widest">Fetching Data</span>
                                        <span class="text-[10px] font-bold text-slate-400 uppercase tracking-tighter mt-1">Please wait a moment...</span>
                                    </div>
                                </div>

                                <div x-show="!loading" x-transition>
                                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                                    <!-- Card 1: File Details -->
                                    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
                                        <div class="bg-slate-50 px-6 py-3 border-b border-slate-200 flex items-center gap-2">
                                            <i data-lucide="file-text" class="h-4 w-4 text-blue-600"></i>
                                            <h2 class="font-bold text-sm text-slate-800 uppercase tracking-wide">File Details</h2>
                                        </div>
                                        <div class="p-5 space-y-4">
                                            <div>
                                                <label class="block text-xs font-bold text-slate-600 mb-1 uppercase">Full File Number</label>
                                                <div class="px-4 py-2 bg-blue-50 border border-blue-100 rounded-lg font-mono text-xs text-blue-700 font-bold shadow-inner" x-text="formData.full_file_number"></div>
                                            </div>

                                            <div>
                                                <label class="block text-xs font-bold text-slate-600 mb-1 uppercase">File Title <span class="text-red-500">*</span></label>
                                                <input type="text" x-model="formData.file_title" :disabled="viewMode" placeholder="Enter File Title" class="w-full bg-white border border-slate-300 rounded-xl px-4 py-2.5 text-sm font-medium text-slate-700 placeholder:text-slate-400 focus:outline-none focus:ring-4 focus:ring-blue-500/10 focus:border-blue-500 transition-all hover:border-slate-400 shadow-sm disabled:bg-slate-50">
                                            </div>

                                            <div>
                                                <label class="block text-xs font-bold text-slate-600 mb-1 uppercase">Reason why the file is under investigation</label>
                                                <input type="text" x-model="formData.reason" :disabled="viewMode" placeholder="Enter Reason" class="w-full bg-white border border-slate-300 rounded-xl px-4 py-2.5 text-sm font-medium text-slate-700 placeholder:text-slate-400 focus:outline-none focus:ring-4 focus:ring-blue-500/10 focus:border-blue-500 transition-all hover:border-slate-400 shadow-sm disabled:bg-slate-50">
                                            </div>

                                            <!-- Location Details -->
                                            <div class="mt-4 pt-4 border-t border-slate-100">
                                                <label class="text-[10px] font-black text-slate-500 uppercase tracking-widest block mb-3">
                                                    <i data-lucide="map-pin" class="inline h-3 w-3 mr-1 text-blue-600"></i> Location Details
                                                </label>
                                                <div class="grid grid-cols-2 gap-4">
                                                    <div>
                                                        <label class="block text-xs font-bold text-slate-600 mb-1 uppercase">Plot Number</label>
                                                        <input type="text" x-model="formData.plot_number" :disabled="viewMode" placeholder="e.g., 402" class="w-full bg-white border border-slate-300 rounded-xl px-4 py-2.5 text-sm font-medium text-slate-700 placeholder:text-slate-400 focus:outline-none focus:ring-4 focus:ring-blue-500/10 focus:border-blue-500 transition-all hover:border-slate-400 shadow-sm disabled:bg-slate-50">
                                                    </div>
                                                    <div>
                                                        <label class="block text-xs font-bold text-slate-600 mb-1 uppercase">TP Number</label>
                                                        <input type="text" x-model="formData.tp_no" :disabled="viewMode" placeholder="e.g., TP/1024" class="w-full bg-white border border-slate-300 rounded-xl px-4 py-2.5 text-sm font-medium text-slate-700 placeholder:text-slate-400 focus:outline-none focus:ring-4 focus:ring-blue-500/10 focus:border-blue-500 transition-all hover:border-slate-400 shadow-sm disabled:bg-slate-50">
                                                    </div>
                                                </div>
                                                <div class="grid grid-cols-2 gap-4 mt-4">
                                                    <div>
                                                        <label class="block text-xs font-bold text-slate-600 mb-1 uppercase">District</label>
                                                        <select x-model="formData.district_id" :disabled="viewMode" class="w-full bg-white border border-slate-300 rounded-xl px-4 py-2 text-sm font-medium text-slate-700 focus:outline-none focus:ring-4 focus:ring-blue-500/10 focus:border-blue-500 transition-all hover:border-slate-400 shadow-sm disabled:bg-slate-50">
                                                            <option value="">Select District</option>
                                                            <template x-for="district in districts" :key="district.id">
                                                                <option :value="district.id" x-text="district.name"></option>
                                                            </template>
                                                            <option value="Other">Other (Specify)</option>
                                                        </select>
                                                        <!-- Custom District Input for Edit -->
                                                        <div x-show="formData.district_id === 'Other'" class="mt-2">
                                                            <input type="text" 
                                                                   x-model="formData.custom_district" 
                                                                   :disabled="viewMode"
                                                                   placeholder="Specify District" 
                                                                   class="w-full bg-white border border-slate-300 rounded-xl px-4 py-2 text-xs font-bold text-blue-600 placeholder:text-slate-400 focus:outline-none focus:ring-4 focus:ring-blue-500/10 focus:border-blue-500 transition-all shadow-sm disabled:bg-slate-50">
                                                        </div>
                                                        </select>
                                                    </div>
                                                    <div>
                                                        <label class="block text-xs font-bold text-slate-600 mb-1 uppercase">LGA</label>
                                                        <select x-model="formData.lga_id" :disabled="viewMode" class="w-full bg-white border border-slate-300 rounded-xl px-4 py-2 text-sm font-medium text-slate-700 focus:outline-none focus:ring-4 focus:ring-blue-500/10 focus:border-blue-500 transition-all hover:border-slate-400 shadow-sm disabled:bg-slate-50">
                                                            <option value="">Select LGA</option>
                                                            <template x-for="lga in lgas" :key="lga.id">
                                                                <option :value="lga.id" x-text="lga.name"></option>
                                                            </template>
                                                        </select>
                                                    </div>
                                                </div>
                                                <div class="mt-4">
                                                    <label class="block text-[10px] font-black text-slate-400 mb-1 uppercase italic tracking-tight">Location Preview</label>
                                                    <textarea x-text="location" rows="1" readonly class="w-full bg-slate-50 border-slate-200 rounded-lg text-slate-500 px-3 py-2 text-[11px] italic shadow-inner outline-none"></textarea>
                                                </div>
                                            </div>

                                            <div class="mt-4 pt-4 border-t border-slate-100">
                                                <label class="text-[10px] font-black text-slate-500 uppercase tracking-widest block mb-2">Related Files</label>
                                                <div class="space-y-2">
                                                    <template x-for="(rel, index) in formData.relatedFiles" :key="index">
                                                        <div class="flex items-center gap-2 p-2 bg-slate-50 rounded-lg border border-slate-200">
                                                            <div class="flex-1">
                                                                <span class="text-[10px] font-bold text-blue-600 block" x-text="rel.file_number"></span>
                                                                <span class="text-[9px] text-slate-500 italic block" x-text="rel.secondary_title || 'No Secondary Title'"></span>
                                                            </div>
                                                        </div>
                                                    </template>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Card 2: Record Metadata -->
                                    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden h-fit">
                                        <div class="bg-slate-50 px-6 py-3 border-b border-slate-200 flex items-center gap-2">
                                            <i data-lucide="calendar-clock" class="h-4 w-4 text-blue-600"></i>
                                            <h2 class="font-bold text-sm text-slate-800 uppercase tracking-wide">Record Metadata</h2>
                                        </div>
                                        <div class="p-5 space-y-4">
                                            <div>
                                                <label class="block text-xs font-bold text-slate-600 mb-1 uppercase">Tracking ID</label>
                                                <div class="px-4 py-2 bg-red-50 border border-red-100 rounded-lg font-mono text-xs text-red-600 font-extrabold shadow-inner" x-text="formData.tracking_id || 'N/A'"></div>
                                            </div>

                                            <div class="grid grid-cols-2 gap-4">
                                                <div>
                                                    <label class="block text-xs font-bold text-slate-600 mb-1 uppercase">Date Commissioned</label>
                                                    <input type="date" x-model="formData.date_created" disabled class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-sm font-medium text-slate-500 focus:outline-none shadow-sm cursor-not-allowed">
                                                </div>
                                                <div>
                                                    <label class="block text-xs font-bold text-slate-600 mb-1 uppercase">Time Commissioned</label>
                                                    <input type="time" x-model="formData.time_created" disabled class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-sm font-medium text-slate-500 focus:outline-none shadow-sm cursor-not-allowed">
                                                </div>
                                            </div>

                                            <div>
                                                <label class="block text-xs font-bold text-slate-600 mb-1 uppercase">Commissioned By</label>
                                                <input type="text" x-model="formData.created_by" disabled class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-sm font-medium text-slate-500 focus:outline-none shadow-sm cursor-not-allowed">
                                            </div>

                                            <div class='hidden'>
                                                <label class="block text-xs font-bold text-slate-600 mb-1 uppercase">Status</label>
                                                <input type="text" x-model="formData.status" disabled class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-sm font-bold text-slate-600 focus:outline-none shadow-sm cursor-not-allowed uppercase">
                                            </div>
                                        </div>
                                    </div>

                                </div>
                                </div>
                            </div>

                            <div class="px-8 py-5 border-t border-slate-100 bg-white flex items-center justify-end gap-3 font-[Inter]">
                                <button @click="showEditModal = false" class="px-6 py-2.5 text-[11px] font-black uppercase tracking-wider text-slate-500 hover:text-slate-700 hover:bg-slate-50 rounded-xl transition-all">
                                    <span x-text="viewMode ? 'Close' : 'Cancel'"></span>
                                </button>
                                <button x-show="!viewMode" @click="updateRecord()" :disabled="loading" class="px-8 py-2.5 bg-blue-600 hover:bg-blue-700 disabled:bg-slate-400 disabled:cursor-not-allowed text-white text-[11px] font-black uppercase tracking-widest rounded-xl transition-all active:scale-95 shadow-lg shadow-blue-100 flex items-center gap-2 min-w-[140px] justify-center">
                                    <template x-if="loading">
                                         <i data-lucide="loader-2" class="h-4 w-4 animate-spin"></i>
                                    </template>
                                    <span x-text="loading ? 'UPDATING...' : 'UPDATE'">UPDATE</span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Batch Members Modal -->
    <div x-show="batchModalOpen" 
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm" style="display: none;">
        
        <div class="bg-white rounded-3xl shadow-2xl w-full max-w-4xl max-h-[90vh] flex flex-col overflow-hidden" @click.away="batchModalOpen = false">
            <div class="px-8 py-5 border-b border-slate-100 flex items-center justify-between bg-white sticky top-0 z-10">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-2xl bg-blue-50 flex items-center justify-center">
                        <i data-lucide="layers" class="h-5 w-5 text-blue-600"></i>
                    </div>
                        <div>
                            <h2 class="text-lg font-black text-slate-800 uppercase tracking-tight" x-text="viewMode ? 'View DCIV Record' : (editMode ? 'Edit DCIV Record' : 'Generate DCIV FileNo')"></h2>
                            <p class="text-xs text-slate-400 font-bold uppercase tracking-widest" x-text="viewMode ? 'Details for ' + formData.full_file_number : (editMode ? 'Updating metadata for ' + formData.full_file_number : 'Registering new DCIV entries')"></p>
                        </div>
                </div>
                <button @click="batchModalOpen = false" class="p-2.5 hover:bg-slate-100 rounded-xl transition-all text-slate-400 hover:text-slate-600">
                    <i data-lucide="x" class="h-5 w-5"></i>
                </button>
            </div>

            <div class="flex-1 overflow-y-auto p-8">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-slate-50/50 border-b border-slate-200 text-[9px] font-black text-slate-500 uppercase tracking-widest">
                            <th class="px-4 py-3 whitespace-nowrap">File Number</th>
                            <th class="px-4 py-3 text-center whitespace-nowrap">Plot No</th>
                            <th class="px-4 py-3 whitespace-nowrap">Location</th>
                            <th class="px-4 py-3 whitespace-nowrap">LGA</th>
                            <th class="px-4 py-3 text-center whitespace-nowrap">Time</th>
                            <th class="px-4 py-3 text-center whitespace-nowrap">Date</th>
                            <th class="px-4 py-3 text-right whitespace-nowrap">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-[11px]">
                        <template x-for="member in batchMembers" :key="member.id">
                            <tr class="hover:bg-slate-50 transition-colors">
                                <td class="px-4 py-3 font-mono font-bold text-blue-600 whitespace-nowrap" x-text="member.full_file_number"></td>
                                <td class="px-4 py-3 text-center font-mono whitespace-nowrap" x-text="member.plot_no || '—'"></td>
                                <td class="px-4 py-3 text-slate-500 whitespace-nowrap" x-text="member.location"></td>
                                <td class="px-4 py-3 font-bold whitespace-nowrap" x-text="member.lga || '—'"></td>
                                <td class="px-4 py-3 text-center whitespace-nowrap">
                                    <span class="px-1.5 py-0.5 bg-slate-100 rounded text-slate-600 font-bold" x-text="member.formatted_time"></span>
                                </td>
                                <td class="px-4 py-3 text-center whitespace-nowrap">
                                    <span class="px-1.5 py-0.5 bg-blue-50 rounded text-blue-600 font-bold" x-text="member.formatted_date"></span>
                                </td>
                                <td class="px-4 py-3 text-right whitespace-nowrap">
                                    <div class="flex justify-end gap-2">
                                        <button @click="editRecordFromBatch(member.id)" class="p-1.5 bg-blue-50 text-blue-600 rounded-lg hover:bg-blue-600 hover:text-white transition-all shadow-sm">
                                            <i data-lucide="edit-3" class="h-3 w-3"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Related File Numbers Modal -->
    <div x-show="relatedFilesModalOpen" 
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm" style="display: none;">
        
        <div class="bg-white rounded-3xl shadow-2xl w-full max-w-2xl max-h-[90vh] flex flex-col overflow-hidden" @click.away="relatedFilesModalOpen = false">
            <div class="px-8 py-5 border-b border-slate-100 flex items-center justify-between bg-white sticky top-0 z-10">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-2xl bg-amber-50 flex items-center justify-center">
                        <i data-lucide="link-2" class="h-5 w-5 text-amber-600"></i>
                    </div>
                    <div>
                        <h2 class="text-lg font-black text-slate-800 uppercase tracking-tight">Related File Numbers</h2>
                        <p class="text-xs text-slate-400 font-bold uppercase tracking-widest" x-text="'For ' + relatedFileNumber"></p>
                    </div>
                </div>
                <button @click="relatedFilesModalOpen = false" class="p-2.5 hover:bg-slate-100 rounded-xl transition-all text-slate-400 hover:text-slate-600">
                    <i data-lucide="x" class="h-5 w-5"></i>
                </button>
            </div>

            <div class="flex-1 overflow-y-auto p-8">
                <!-- Loading State -->
                <div x-show="relatedFilesLoading" class="flex flex-col items-center justify-center py-12 text-slate-400">
                    <i data-lucide="loader-2" class="h-8 w-8 animate-spin mb-3"></i>
                    <span class="text-sm font-bold">Loading related files...</span>
                </div>

                <!-- Empty State -->
                <div x-show="!relatedFilesLoading && relatedFilesData.length === 0" class="flex flex-col items-center justify-center py-12 text-slate-400">
                    <i data-lucide="file-x" class="h-10 w-10 mb-3"></i>
                    <span class="text-sm font-bold">No related file numbers found.</span>
                </div>

                <!-- Data Table -->
                <div x-show="!relatedFilesLoading && relatedFilesData.length > 0">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-slate-50/50 border-b border-slate-200 text-[9px] font-black text-slate-500 uppercase tracking-widest">
                                <th class="px-4 py-3 whitespace-nowrap">#</th>
                                <th class="px-4 py-3 whitespace-nowrap">Related File Number</th>
                                <th class="px-4 py-3 whitespace-nowrap" x-text="relatedFilesSource === 'fi' ? 'File Title' : 'Secondary Title'">Secondary Title</th>
                                <template x-if="relatedFilesSource === 'fi'">
                                    <th class="px-4 py-3 whitespace-nowrap">Plot No</th>
                                </template>
                                <template x-if="relatedFilesSource === 'fi'">
                                    <th class="px-4 py-3 whitespace-nowrap">TP No</th>
                                </template>
                                <template x-if="relatedFilesSource === 'fi'">
                                    <th class="px-4 py-3 whitespace-nowrap">LPKN No</th>
                                </template>
                                <template x-if="relatedFilesSource === 'fi'">
                                    <th class="px-4 py-3 whitespace-nowrap">Location</th>
                                </template>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-[11px]">
                            <template x-for="(file, index) in relatedFilesData" :key="file.id || index">
                                <tr class="hover:bg-slate-50 transition-colors">
                                    <td class="px-4 py-3 font-bold text-slate-400 whitespace-nowrap" x-text="index + 1"></td>
                                    <td class="px-4 py-3 font-mono font-bold text-blue-600 whitespace-nowrap" x-text="file.related_file_number"></td>
                                    <td class="px-4 py-3 text-slate-600 whitespace-nowrap" x-text="file.secondary_title || '—'"></td>
                                    <template x-if="relatedFilesSource === 'fi'">
                                        <td class="px-4 py-3 text-slate-600 whitespace-nowrap" x-text="file.plot_number || '—'"></td>
                                    </template>
                                    <template x-if="relatedFilesSource === 'fi'">
                                        <td class="px-4 py-3 text-slate-600 whitespace-nowrap" x-text="file.tp_no || '—'"></td>
                                    </template>
                                    <template x-if="relatedFilesSource === 'fi'">
                                        <td class="px-4 py-3 text-slate-600 whitespace-nowrap" x-text="file.lpkn_no || '—'"></td>
                                    </template>
                                    <template x-if="relatedFilesSource === 'fi'">
                                        <td class="px-4 py-3 text-slate-600 whitespace-nowrap" x-text="file.location || '—'"></td>
                                    </template>
                                </tr>
                            </template>
                        </tbody>
                    </table>

                    <div class="mt-4 pt-4 border-t border-slate-100 flex items-center justify-between">
                        <span class="text-[10px] font-black text-slate-400 uppercase tracking-widest" x-text="relatedFilesData.length + ' related file(s)'"></span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @include('admin.footer')
</div>

<!-- Commissioning Sheet Modal -->
<div 
    x-show="showCommissioningModal"
    x-transition:enter="transition ease-out duration-300"
    x-transition:enter-start="opacity-0"
    x-transition:enter-end="opacity-100"
    x-transition:leave="transition ease-in duration-200"
    x-transition:leave-start="opacity-100"
    x-transition:leave-end="opacity-0"
    class="fixed inset-0 z-[150] flex items-center justify-center px-4 py-6 bg-slate-900/60 backdrop-blur-sm"
    style="display: none;"
    @keydown.escape.window="closeCommissioningModal()"
    @click.self="closeCommissioningModal()"
>
    <div class="relative w-full max-w-4xl bg-white rounded-3xl shadow-2xl border border-slate-200 overflow-hidden">
        <div class="px-8 py-5 border-b border-slate-100 flex items-center justify-between bg-slate-50">
            <div>
                <h3 class="text-xl font-bold text-slate-900">File Number Commissioning Sheet</h3>
                <p class="text-sm text-slate-500">Review and capture commissioning details before printing.</p>
            </div>
            <button @click="closeCommissioningModal()" class="p-2 text-slate-400 hover:text-red-500 rounded-xl hover:bg-red-50 transition-colors">
                <i data-lucide="x" class="h-5 w-5"></i>
            </button>
        </div>

        <form @submit.prevent="generateCommissioningSheet" class="p-8 space-y-6">
            <div class="text-center border border-slate-200 rounded-2xl px-6 py-4 bg-gradient-to-br from-white to-slate-50">
                <p class="text-sm font-black text-slate-700 uppercase tracking-widest">Ministry of Land &amp; Physical Planning</p>
                <p class="text-sm font-semibold text-slate-600">Department of Complaint Investigation and Verification</p>
                <p class="text-base font-bold text-slate-800 mt-2">File Commissioning Sheet</p>
            </div>

            <div x-show="selectedRecord" class="flex items-start gap-3 p-4 border border-blue-100 rounded-2xl bg-blue-50/70 text-sm text-blue-700" x-cloak>
                <div class="p-2 rounded-xl bg-blue-100 text-blue-600">
                    <i data-lucide="info" class="h-4 w-4"></i>
                </div>
                <div>
                    <p class="font-semibold">Loaded from {{ __('DCIV Registry') }}</p>
                    <p class="text-xs text-blue-600/80" x-text="selectedRecord ? 'File: ' + selectedRecord.full_file_number + ' • Tracking: ' + (selectedRecord.tracking_id || 'N/A') : ''"></p>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase mb-2">File Number</label>
                    <input type="text" x-model="commissioningForm.file_number" class="w-full border border-slate-200 rounded-xl px-4 py-2.5 text-sm focus:ring-4 focus:ring-blue-500/10 focus:border-blue-500" placeholder="e.g., DCIV-2025-120" required>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase mb-2">File Name</label>
                    <input type="text" x-model="commissioningForm.file_name" @input="commissioningForm.name_or_allottee = commissioningForm.file_name" class="w-full border border-slate-200 rounded-xl px-4 py-2.5 text-sm focus:ring-4 focus:ring-blue-500/10 focus:border-blue-500" placeholder="Enter File Name" required>
                </div>
                <div class="md:col-span-2">
                    <label class="block text-xs font-bold text-slate-500 uppercase mb-2">Allottee</label>
                    <input type="text" x-model="commissioningForm.name_or_allottee" class="w-full border border-slate-200 rounded-xl px-4 py-2.5 text-sm bg-slate-50" readonly>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase mb-2">Reason</label>
                    <input type="text" x-model="commissioningForm.plot_number" class="w-full border border-slate-200 rounded-xl px-4 py-2.5 text-sm focus:ring-4 focus:ring-blue-500/10 focus:border-blue-500" placeholder="Describe reason for commissioning">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase mb-2">TP Number</label>
                    <input type="text" x-model="commissioningForm.tp_number" class="w-full border border-slate-200 rounded-xl px-4 py-2.5 text-sm focus:ring-4 focus:ring-blue-500/10 focus:border-blue-500" placeholder="TP Reference">
                </div>
                <div class="md:col-span-2">
                    <label class="block text-xs font-bold text-slate-500 uppercase mb-2">LGA (optional)</label>
                    <input type="text" x-model="commissioningForm.lga" class="w-full border border-slate-200 rounded-xl px-4 py-2.5 text-sm focus:ring-4 focus:ring-blue-500/10 focus:border-blue-500" placeholder="Enter LGA (optional)">
                </div>

                <div class="md:col-span-2">
                    <label class="block text-xs font-bold text-slate-500 uppercase mb-2">Location</label>
                    <textarea x-model="commissioningForm.location" rows="2" class="w-full border border-slate-200 rounded-xl px-4 py-2.5 text-sm focus:ring-4 focus:ring-blue-500/10 focus:border-blue-500" placeholder="District, LGA, State"></textarea>
                </div>
                
                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase mb-2">Time Commissioned</label>
                    <input type="time" x-model="commissioningForm.time_created" class="w-full border border-slate-200 rounded-xl px-4 py-2.5 text-sm focus:ring-4 focus:ring-blue-500/10 focus:border-blue-500">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase mb-2">Date Commissioned</label>
                    <input type="date" x-model="commissioningForm.date_created" class="w-full border border-slate-200 rounded-xl px-4 py-2.5 text-sm focus:ring-4 focus:ring-blue-500/10 focus:border-blue-500">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase mb-2">Commissioned By</label>
                    <input type="text" x-model="commissioningForm.created_by" class="w-full border border-slate-200 rounded-xl px-4 py-2.5 text-sm focus:ring-4 focus:ring-blue-500/10 focus:border-blue-500" placeholder="Officer Name">
                </div>
            </div>

            <input type="hidden" x-model="commissioningForm.tracking_id">

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 border-t border-slate-100 pt-6">
                <div class="text-center">
                    <p class="text-xs font-bold text-slate-500 uppercase mb-3">Created By Signature</p>
                    <div class="h-24 border-2 border-dashed border-slate-200 rounded-2xl flex items-center justify-center text-slate-400 text-xs">Signature Area</div>
                </div>
                <div class="text-center">
                    <p class="text-xs font-bold text-slate-500 uppercase mb-3">Approved By Signature</p>
                    <div class="h-24 border-2 border-dashed border-slate-200 rounded-2xl flex items-center justify-center text-slate-400 text-xs">Signature Area</div>
                </div>
            </div>

            <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-100">
                <button type="button" @click="closeCommissioningModal()" class="px-5 py-2.5 text-sm font-semibold text-slate-500 hover:text-slate-700 hover:bg-slate-50 rounded-xl transition-colors">
                    Cancel
                </button>
                <button type="submit" :disabled="commissioningLoading" class="px-6 py-2.5 rounded-xl text-sm font-bold text-white bg-emerald-600 hover:bg-emerald-700 disabled:bg-slate-400 disabled:cursor-not-allowed flex items-center gap-2 shadow-lg shadow-emerald-100">
                    <span x-show="commissioningLoading" class="flex items-center">
                        <i data-lucide="loader-2" class="h-4 w-4 animate-spin"></i>
                    </span>
                    <span x-show="!commissioningLoading" class="flex items-center">
                        <i data-lucide="printer" class="h-4 w-4"></i>
                    </span>
                    <span x-text="commissioningLoading ? 'Processing...' : 'Generate & Print'"></span>
                </button>
            </div>
        </form>
    </div>
</div>

@push('scripts')
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<script src="{{ asset('js/dciv_generation.js') }}?v={{ filemtime(public_path('js/dciv_generation.js')) }}"></script>
@include('components.global-fileno-modal')
<script>
// Toggle action menu for DataTable rows
function toggleDcivMenu(btn) {
    var menu = btn.parentElement.querySelector('.dciv-menu');
    // Close all other open menus first
    document.querySelectorAll('.dciv-action-menu .dciv-menu').forEach(function(m) {
        if (m !== menu) m.classList.add('hidden');
    });
    menu.classList.toggle('hidden');
}
// Close menus on click-away
document.addEventListener('click', function(e) {
    if (!e.target.closest('.dciv-action-menu')) {
        document.querySelectorAll('.dciv-action-menu .dciv-menu').forEach(function(m) {
            m.classList.add('hidden');
        });
    }
});

$(document).ready(function() {
    var dcivTable = $('#dciv-records-table').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: '{{ route("dciv-generation.data") }}',
            type: 'GET'
        },
        pageLength: 20,
        order: [[10, 'desc']],
        columns: [
            { data: null, name: 'sn', orderable: false, searchable: false, className: 'text-center font-bold text-slate-400 text-xs whitespace-nowrap', render: function(data, type, row, meta) { return meta.row + meta.settings._iDisplayStart + 1; } },
            { data: 'file_number', name: 'full_file_number', orderable: true },
            { data: 'related_fileno', name: 'related_fileno', orderable: false, searchable: false, className: 'text-center' },
            { data: 'file_name', name: 'file_name', orderable: true, className: 'font-medium text-slate-700 whitespace-nowrap' },
            { data: 'tp_no', name: 'tp_no', orderable: true, className: 'text-center font-mono text-xs whitespace-nowrap' },
            { data: 'plot_no', name: 'plot_no', orderable: true, className: 'text-center font-mono text-xs whitespace-nowrap' },
            { data: 'lga', name: 'lga', orderable: true, className: 'text-xs font-bold text-slate-600 whitespace-nowrap' },
            { data: 'location', name: 'location', orderable: true, className: 'text-xs text-slate-500 whitespace-nowrap' },
           
            { data: 'commissioned_by', name: 'created_by', orderable: true, className: 'text-xs whitespace-nowrap' },
            { data: 'time', name: 'commissioning_time', orderable: true, className: 'text-center whitespace-nowrap' },
            { data: 'date', name: 'commissioning_date', orderable: true, className: 'text-center whitespace-nowrap' },
            {
                data: 'source',
                name: 'source_table',
                orderable: false,
                searchable: false,
                className: 'text-center whitespace-nowrap'
            },
            { data: 'actions', name: 'actions', orderable: false, searchable: false, className: 'text-right' }
        ],
        createdRow: function(row, data) {
            const sourceTable = String(data.source_table || '').toLowerCase();
            if (sourceTable.indexOf('file_index') !== -1) {
                $(row).addClass('dciv-row-indexed');
            } else if (sourceTable.indexOf('dciv_file_no') !== -1) {
                $(row).addClass('dciv-row-commissioned');
            }
        },
        drawCallback: function() {
            // Re-initialize Lucide icons for dynamically loaded rows
            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }
            // Re-initialize Alpine for x-data in action dropdowns
            if (typeof Alpine !== 'undefined') {
                document.querySelectorAll('#dciv-records-table [x-data]').forEach(function(el) {
                    if (!el._x_dataStack) {
                        Alpine.initTree(el);
                    }
                });
            }
        },
        language: {
            processing: '<div class="flex items-center justify-center gap-3 py-4"><div class="w-5 h-5 border-2 border-blue-100 border-t-blue-600 rounded-full animate-spin"></div><span class="text-sm font-bold text-slate-500">Loading records...</span></div>',
            emptyTable: '<div class="py-8 text-center text-slate-400 italic">No records found. Click "Generate DCIV FileNo" to start.</div>',
            info: 'Showing _START_ to _END_ of _TOTAL_ records',
            infoFiltered: '(filtered from _MAX_ total)',
            lengthMenu: 'Show _MENU_ per page',
            search: '',
            searchPlaceholder: 'Search file, title, or location...',
            paginate: {
                first: '<i data-lucide="chevrons-left" class="h-4 w-4"></i>',
                last: '<i data-lucide="chevrons-right" class="h-4 w-4"></i>',
                next: '<i data-lucide="chevron-right" class="h-4 w-4"></i>',
                previous: '<i data-lucide="chevron-left" class="h-4 w-4"></i>'
            }
        },
        dom: '<"flex flex-col sm:flex-row justify-between items-center gap-3 mb-4"lf>rt<"flex flex-col sm:flex-row justify-between items-center gap-3 mt-4 px-2"ip>'
    });

    // Expose the table instance so Alpine/dcivGenerator can refresh it after generating new records
    window.dcivDataTable = dcivTable;

    // Handle "View Related FileNo(s)" button clicks using data attributes
    $(document).on('click', '.view-related-files-btn', function(e) {
        e.preventDefault();
        const fileNumber = $(this).data('file-number');
        const source = $(this).data('source') || 'dciv';
        const id = $(this).data('id');
        
        const detail = {
            fileNumber: fileNumber,
            source: source
        };
        if (id) {
            detail.id = id;
        }
        
        // Dispatch from the Alpine root so @open-related-files consistently receives it.
        const event = new CustomEvent('open-related-files', {
            bubbles: true,
            detail: detail
        });
        const alpineRoot = document.querySelector('[x-data^="dcivGenerator"]');
        if (alpineRoot) {
            alpineRoot.dispatchEvent(event);
        } else {
            window.dispatchEvent(event);
        }
    });

    // DCIV EDMS View Files
    $(document).on('click', '.dciv-view-files-btn', function(e) {
        e.preventDefault();
        const fileNumber = $(this).data('file-number');
        openDcivEdmsModal(fileNumber);
    });
});

function openDcivEdmsModal(fileNumber) {
    const modal = document.getElementById('dciv-edms-files-modal');
    const body = document.getElementById('dciv-edms-modal-body');
    const filenoEl = document.getElementById('dciv-edms-modal-fileno');
    if (!modal) return;

    filenoEl.textContent = fileNumber;
    body.innerHTML = '<div class="flex items-center justify-center py-12"><div class="w-6 h-6 border-2 border-teal-100 border-t-teal-600 rounded-full animate-spin"></div><span class="ml-3 text-sm font-bold text-slate-400">Loading files...</span></div>';
    modal.classList.remove('hidden');

    const url = '{{ route("dciv-generation.edms-files", ["fileNumber" => "__FN__"]) }}'.replace('__FN__', encodeURIComponent(fileNumber));

    fetch(url)
        .then(function(res) { return res.json(); })
        .then(function(data) {
            if (!data.has_files || !data.files || data.files.length === 0) {
                body.innerHTML = '<div class="flex flex-col items-center justify-center py-12 text-slate-400"><i data-lucide="folder-x" class="h-10 w-10 mb-3 opacity-30"></i><p class="text-sm font-bold">No EDMS files found for this file number.</p></div>';
                if (typeof lucide !== 'undefined') lucide.createIcons();
                return;
            }
            const images = data.files.filter(function(f) { return f.type === 'image'; });
            const pdfs   = data.files.filter(function(f) { return f.type === 'pdf'; });
            const others = data.files.filter(function(f) { return f.type === 'other'; });
            let html = '';
            if (images.length) {
                html += '<p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-3">Images (' + images.length + ')</p>';
                html += '<div class="grid grid-cols-2 sm:grid-cols-3 gap-3 mb-6">';
                images.forEach(function(f) {
                    html += '<a href="' + f.url + '" target="_blank" class="group block rounded-xl overflow-hidden border border-slate-200 hover:border-teal-300 transition-all shadow-sm">'
                        + '<img src="' + f.url + '" alt="' + f.name + '" class="w-full h-32 object-cover bg-slate-50">'
                        + '<div class="px-2 py-1.5 bg-white text-[9px] font-bold text-slate-500 truncate group-hover:text-teal-600">' + f.name + '</div>'
                        + '</a>';
                });
                html += '</div>';
            }
            if (pdfs.length) {
                html += '<p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2">PDFs (' + pdfs.length + ')</p>';
                html += '<ul class="space-y-1.5 mb-4">';
                pdfs.forEach(function(f) {
                    html += '<li><a href="' + f.url + '" target="_blank" class="flex items-center gap-2 px-3 py-2 rounded-lg bg-slate-50 hover:bg-teal-50 border border-slate-200 hover:border-teal-200 text-xs font-bold text-slate-700 hover:text-teal-700 transition-all">'
                        + '<i data-lucide="file-text" class="h-3.5 w-3.5 text-teal-400 flex-shrink-0"></i>'
                        + '<span class="truncate">' + f.name + '</span>'
                        + '<i data-lucide="external-link" class="h-3 w-3 ml-auto flex-shrink-0 text-slate-300"></i>'
                        + '</a></li>';
                });
                html += '</ul>';
            }
            if (others.length) {
                html += '<p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2">Other Files (' + others.length + ')</p>';
                html += '<ul class="space-y-1.5">';
                others.forEach(function(f) {
                    html += '<li><a href="' + f.url + '" target="_blank" class="flex items-center gap-2 px-3 py-2 rounded-lg bg-slate-50 hover:bg-teal-50 border border-slate-200 hover:border-teal-200 text-xs font-bold text-slate-700 hover:text-teal-700 transition-all">'
                        + '<i data-lucide="file" class="h-3.5 w-3.5 text-slate-400 flex-shrink-0"></i>'
                        + '<span class="truncate">' + f.name + '</span>'
                        + '</a></li>';
                });
                html += '</ul>';
            }
            body.innerHTML = html;
            if (typeof lucide !== 'undefined') lucide.createIcons();
        })
        .catch(function() {
            body.innerHTML = '<div class="py-8 text-center text-red-400 text-sm font-bold">Failed to load files. Please try again.</div>';
        });
}

function closeDcivEdmsModal() {
    const modal = document.getElementById('dciv-edms-files-modal');
    if (modal) modal.classList.add('hidden');
}

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeDcivEdmsModal();
});
</script>
<style>
    /* DataTable custom styling to match existing design */
    #dciv-records-table_wrapper .dataTables_filter input {
        @apply pl-10 pr-4 py-2.5 bg-white border border-slate-200 rounded-xl text-sm outline-none shadow-sm;
        transition: all 0.2s;
    }
    #dciv-records-table_wrapper .dataTables_filter input:focus {
        @apply ring-2 ring-blue-500/20 border-blue-500;
    }
    #dciv-records-table_wrapper .dataTables_filter {
        position: relative;
    }
    #dciv-records-table_wrapper .dataTables_filter label {
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }
    #dciv-records-table_wrapper .dataTables_length select {
        @apply bg-white border border-slate-200 rounded-lg px-3 py-2 text-sm outline-none shadow-sm;
    }
    #dciv-records-table_wrapper .dataTables_length label {
        @apply text-xs font-bold text-slate-500 uppercase tracking-wider flex items-center gap-2;
    }
    #dciv-records-table_wrapper .dataTables_info {
        @apply text-xs font-bold text-slate-400 uppercase tracking-wider;
    }
    #dciv-records-table_wrapper .dataTables_paginate {
        @apply flex items-center gap-1;
    }
    #dciv-records-table_wrapper .dataTables_paginate .paginate_button {
        @apply px-3 py-1.5 text-xs font-bold text-slate-500 rounded-lg cursor-pointer transition-all;
    }
    #dciv-records-table_wrapper .dataTables_paginate .paginate_button:hover:not(.disabled):not(.current) {
        @apply bg-blue-50 text-blue-600;
        background: rgb(239 246 255) !important;
        color: rgb(37 99 235) !important;
    }
    #dciv-records-table_wrapper .dataTables_paginate .paginate_button.current {
        @apply bg-blue-600 text-white rounded-lg;
        background: rgb(37 99 235) !important;
        color: white !important;
        border: none !important;
    }
    #dciv-records-table_wrapper .dataTables_paginate .paginate_button.disabled {
        @apply text-slate-300 cursor-not-allowed;
        opacity: 0.5;
    }
    #dciv-records-table_wrapper .dataTables_processing {
        @apply bg-white/90 backdrop-blur-sm border border-slate-200 rounded-xl shadow-lg;
        top: 50% !important;
        z-index: 20;
    }
    #dciv-records-table tbody tr {
        @apply transition-colors;
    }
    #dciv-records-table tbody tr:not(.dciv-row-indexed):not(.dciv-row-commissioned):hover {
        background-color: rgb(248 250 252 / 0.5);
    }
    #dciv-records-table tbody tr.dciv-row-indexed {
        background-color: #fffbeb;
    }
    #dciv-records-table tbody tr.dciv-row-commissioned {
        background-color: #f0fdf4;
    }
    #dciv-records-table tbody tr.dciv-row-indexed:hover {
        background-color: #fef3c7 !important;
    }
    #dciv-records-table tbody tr.dciv-row-commissioned:hover {
        background-color: #dcfce7 !important;
    }
    #dciv-records-table tbody td {
        @apply px-6 py-4;
    }
    /* Remove default DataTables border */
    table.dataTable.no-footer {
        border-bottom: none !important;
    }
    table.dataTable thead th {
        border-bottom: 1px solid rgb(226 232 240) !important;
    }
</style>

<!-- DCIV EDMS Files Modal -->
<div id="dciv-edms-files-modal" class="fixed inset-0 z-[200] overflow-y-auto hidden" role="dialog" aria-modal="true">
    <div class="flex items-center justify-center min-h-screen px-4 py-6 text-center">
        <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm" onclick="closeDcivEdmsModal()"></div>
        <div class="relative inline-block w-full max-w-3xl text-left align-middle bg-white rounded-3xl shadow-2xl border border-slate-200 overflow-hidden z-10">
            <!-- Modal Header -->
            <div class="bg-gradient-to-r from-teal-600 to-teal-700 px-6 py-4 flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="p-2 bg-white/20 rounded-xl">
                        <i data-lucide="folder-open" class="h-5 w-5 text-white"></i>
                    </div>
                    <div>
                        <h3 class="text-sm font-black text-white uppercase tracking-widest">EDMS Files</h3>
                        <p id="dciv-edms-modal-fileno" class="text-xs font-bold text-teal-200 mt-0.5"></p>
                    </div>
                </div>
                <button onclick="closeDcivEdmsModal()" class="p-2 hover:bg-white/20 rounded-xl transition-colors">
                    <i data-lucide="x" class="h-5 w-5 text-white"></i>
                </button>
            </div>
            <!-- Modal Body -->
            <div id="dciv-edms-modal-body" class="p-6 max-h-[65vh] overflow-y-auto">
                <div class="flex items-center justify-center py-12">
                    <div class="w-6 h-6 border-2 border-teal-100 border-t-teal-600 rounded-full animate-spin"></div>
                    <span class="ml-3 text-sm font-bold text-slate-400">Loading files...</span>
                </div>
            </div>
        </div>
    </div>
</div>

@endpush
@endsection
