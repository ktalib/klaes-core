@extends('layouts.app')

@section('content')
@push('styles')
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
@endpush
@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="{{ asset('js/global-fileno-modal.js') }}"></script>
@endpush
<div class="flex-1 overflow-auto bg-slate-50/60" x-data='mlsMatchingGenerator({
    districts: @json($districts),
    lgas: @json($lgas),
    csrfToken: "{{ csrf_token() }}",
    availableUrl: "{{ route('mls-file-no-matching.available') }}",
    detailsUrl: "{{ route('mls-file-no-matching.get-file-details') }}",
    worldTimeUrl: "{{ route('world-time') }}",
    storeUrl: "{{ route('mls-file-no-matching.store') }}",
    currentDate: "{{ date('Y-m-d') }}",
    currentTimeOnly: "{{ date('H:i:s') }}",
    currentYear: "{{ date('Y') }}"
})'>
    @include('admin.header')
 
    <div class="py-12 bg-slate-50 min-h-screen">
        <div class="max-w-[95%] mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-8">
                <div>
                    <h1 class="text-3xl font-extrabold text-slate-900 tracking-tight">Match Existing FileNo (MLSFileNo)</h1>
                    <p class="text-slate-500 text-sm mt-1">Manage and match existing MLPP file numbers.</p>
                </div>
                <div class="flex items-center gap-3 w-full md:w-auto">
                    <form action="{{ route('mls-file-no-matching.index') }}" method="GET" class="relative group flex-1 md:w-80">
                        <i data-lucide="search" class="absolute left-3.5 top-1/2 -translate-y-1/2 h-4 w-4 text-slate-400 group-focus-within:text-blue-500 transition-colors"></i>
                        <input type="text" 
                               name="search" 
                               value="{{ request('search') }}"
                               placeholder="Search file, title, or location..." 
                               class="w-full pl-10 pr-4 py-2.5 bg-white border border-slate-200 rounded-xl text-sm focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 outline-none transition-all shadow-sm">
                    </form>
                    <button 
                        @click="showGenerateModal = true" 
                        type="button"
                        class="inline-flex items-center gap-2 px-6 py-3 bg-blue-600 text-white font-bold rounded-xl hover:bg-blue-700 transition-all shadow-lg shadow-blue-100 whitespace-nowrap"
                    >
                        <i data-lucide="plus-circle" class="h-5 w-5"></i>
                        <span>Match Existing FileNo (MLSFileNo) </span>
                    </button>
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
                            <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Daily Matching</p>
                            <h3 class="text-2xl font-black text-slate-800 tracking-tight">{{ number_format($stats['today']) }}</h3>
                        </div>
                    </div>
                    <div class="mt-4 pt-4 border-t border-slate-50 flex items-center justify-between text-[10px] font-bold text-slate-400 uppercase tracking-widest">
                        <span>New Matches Today</span>
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
                            <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Monthly Matching</p>
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
                        <span>All Indexed Records</span>
                        <span class="px-2 py-0.5 bg-white/20 text-white rounded-lg border border-white/20">CSF</span>
                    </div>
                </div>
            </div>

            @include('components.indexed-files-table', ['config' => [
                'isCorrespondingFile' => true,
                'tableVariant' => 'cadastral',
                'columns' => [
                    ['key' => 'sn',                   'sort' => null,            'default' => 'S/N'],
                    ['key' => 'shelf_location',        'sort' => 'shelf_location','default' => 'Shelf/Rack'],
                    ['key' => 'file_title',            'sort' => 'file_title',   'default' => 'File Title'],
                    ['key' => 'file_number',           'sort' => 'file_number',  'default' => 'File Number'],
                    ['key' => 'corresponding_fileno',  'sort' => null,           'default' => 'Corresponding FileNo'],
                    ['key' => 'related_fileno_action', 'sort' => null,           'default' => 'Related FileNo'],
                    ['key' => 'land_use_type',         'sort' => 'land_use_type','default' => 'Land Use'],
                    ['key' => 'plot_number',           'sort' => 'plot_number',  'default' => 'Plot No'],
                    ['key' => 'tp_no',                 'sort' => 'tp_no',        'default' => 'TP No'],
                    ['key' => 'lpkn_no',               'sort' => 'lpkn_no',      'default' => 'LPKN No'],
                    ['key' => 'district',              'sort' => 'district',     'default' => 'District'],
                    ['key' => 'lga',                   'sort' => 'lga',          'default' => 'LGA'],
                    ['key' => 'indexed_by',            'sort' => 'created_at',   'default' => 'Indexed By'],
                    ['key' => 'indexed_date',          'sort' => 'created_at',   'default' => 'Indexed Date'],
                    ['key' => 'status',                'sort' => null,           'default' => 'Status'],
                ],
                'hiddenColumns' => [],
                'columnLabels' => [
                    'file_number'          => 'File Number',
                    'corresponding_fileno' => 'Corresponding FileNo',
                    'related_fileno_action'=> 'Related FileNo',
                ],
                'hideActions' => false,
                'enableCommissioningSheet' => true,
            ]])

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
                <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
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
                        <div class="bg-slate-50 flex flex-col max-h-[85vh] shadow-2xl rounded-3xl border border-slate-200 overflow-hidden">
                            <!-- Modal Header -->
                            <div class="bg-white px-8 py-5 border-b border-slate-200 flex justify-between items-center">
                                <div>
                                    <h2 class="text-xl font-bold text-slate-900">Match MLPP File Numbers</h2>
                                    <p class="text-sm text-slate-500">Configure and match existing serial-based records</p>
                                </div>
                                <div class="flex items-center gap-6 hidden">
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

                            <div class="flex-1 overflow-y-auto p-8 relative min-h-[400px]">
                                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                                    <!-- Card 1: File Details -->
                                    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
                                        <div class="bg-slate-50 px-6 py-3 border-b border-slate-200 flex items-center gap-2">
                                            <i data-lucide="file-text" class="h-4 w-4 text-blue-600"></i>
                                            <h2 class="font-bold text-sm text-slate-800 uppercase tracking-wide">File Details</h2>
                                        </div>
                                        <div class="p-5 space-y-4">
                                            <div>
                                                <label class="block text-xs font-bold text-slate-600 mb-1 uppercase">Select File Number <span class="text-red-500">*</span></label>
                                                <div class="relative group/input">
                                                     <input type="text" 
                                                           x-model="formData.full_file_number" 
                                                           readonly 
                                                           :disabled="dataFetched"
                                                           @click="if(!dataFetched) openFileSelector()"
                                                           placeholder="Click to select file..." 
                                                           class="w-full bg-slate-50 border border-slate-300 rounded-xl px-4 py-2.5 text-sm font-bold text-blue-600 cursor-pointer hover:bg-slate-100 transition-all shadow-sm font-mono disabled:opacity-75 disabled:cursor-not-allowed">
                                                     <div class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 group-hover/input:text-blue-500 transition-colors" x-show="!dataFetched">
                                                         <i data-lucide="search" class="h-4 w-4"></i>
                                                     </div>
                                                     <div class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-auto" x-show="dataFetched">
                                                         <button type="button" @click="resetForm()" class="p-1 hover:bg-slate-100 rounded-lg text-slate-400 hover:text-red-500 transition-all" title="Reset Selection">
                                                             <i data-lucide="refresh-cw" class="h-4 w-4"></i>
                                                         </button>
                                                     </div>
                                                 </div>
                                             </div>
                                             <!-- customer type  -->
                                              <!-- Not-indexed warning -->
                                             <div x-show="fileIndexed === false"
                                                  x-transition
                                                  class="flex items-start gap-2 rounded-lg border border-red-200 bg-red-50 px-4 py-3">
                                                 <i data-lucide="alert-triangle" class="h-4 w-4 text-red-500 mt-0.5 shrink-0"></i>
                                                 <p class="text-xs font-bold text-red-700">
                                                     This file number is <span class="underline">not found</span> in the system. Matching is disabled until a valid indexed file number is selected.
                                                 </p>
                                             </div>
                                             <div x-show="dataFetched">
                                                <label class="block text-xs font-bold text-slate-600 mb-1 uppercase">Customer Type</label>
                                                <div class="px-4 py-2 bg-slate-50 border border-slate-200 rounded-lg text-xs text-slate-700 font-bold" x-text="formData.customer_type || 'N/A'"></div>
                                             </div>
                                             <div>
                                                <label class="block text-xs font-bold text-slate-600 mb-1 uppercase">File Title <span class="text-red-500 ">*</span></label>
                                                <input type="text" x-model="formData.file_title" :disabled="titleLocked" placeholder="Enter File Title" class="w-full bg-white border border-slate-300 rounded-xl px-4 py-2.5 text-sm font-medium text-slate-700 placeholder:text-slate-400 focus:outline-none focus:ring-4 focus:ring-blue-500/10 focus:border-blue-500 transition-all hover:border-slate-400 shadow-sm disabled:bg-slate-50 disabled:text-slate-400">
                                            </div>


                                        </div>
                                    </div>



                                    <!-- Card 3: Location Details -->
                                    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
                                        <div class="bg-slate-50 px-6 py-3 border-b border-slate-200 flex items-center justify-between">
                                            <div class="flex items-center gap-2">
                                                <i data-lucide="map-pin" class="h-4 w-4 text-blue-600"></i>
                                                <h2 class="font-bold text-sm text-slate-800 uppercase tracking-wide">Location Details</h2>
                                            </div>
                                            <template x-if="batchMode && !editMode">
                                                <div class="flex items-center gap-2">
                                                    <span class="px-2 py-0.5 bg-blue-100 text-blue-700 text-[10px] font-black rounded-full shadow-sm">Entry <span x-text="currentEntryIndex + 1"></span> of <span x-text="formData.quantity"></span></span>
                                                    <div class="flex items-center gap-1.5 ml-1">
                                                        <button @click="previousEntry()" :disabled="currentEntryIndex === 0" type="button" class="w-7 h-7 flex items-center justify-center bg-blue-600 hover:bg-blue-700 text-white rounded-full disabled:bg-slate-200 disabled:text-slate-400 transition-all shadow-md active:scale-90">
                                                            <i data-lucide="chevron-left" class="h-4 w-4 stroke-[3]"></i>
                                                        </button>
                                                        <button @click="nextEntry()" :disabled="currentEntryIndex >= locationEntries.length - 1" type="button" class="w-7 h-7 flex items-center justify-center bg-blue-600 hover:bg-blue-700 text-white rounded-full disabled:bg-slate-200 disabled:text-slate-400 transition-all shadow-md active:scale-90">
                                                            <i data-lucide="chevron-right" class="h-4 w-4 stroke-[3]"></i>
                                                        </button>
                                                    </div>
                                                </div>
                                            </template>
                                        </div>
                                        <div class="p-5">
                                            <!-- Batch Sync Controls -->
                                            <template x-if="batchMode && !editMode">
                                                <div class="mb-5 space-y-3 pb-4 border-b border-slate-100">
                                                    <label class="flex items-center gap-2 cursor-pointer group">
                                                        <input type="checkbox" x-model="applyLocationToAll" class="w-4 h-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500 cursor-pointer">
                                                        <span class="text-xs font-bold text-slate-700 group-hover:text-blue-600 transition-colors uppercase tracking-tight">Apply Location to All Files in Batch</span>
                                                    </label>
                                                    
                                                    <button @click="applyLocationToBatch()" type="button" class="w-full flex items-center justify-center gap-2.5 py-2.5 bg-blue-600 hover:bg-blue-700 text-white text-[11px] font-black uppercase tracking-wider rounded-xl transition-all active:scale-[0.98] shadow-lg shadow-blue-100/50">
                                                        <i data-lucide="layers" class="h-4 w-4"></i>
                                                        <span>Apply Current Location to All <span x-text="formData.quantity"></span> Files</span>
                                                    </button>
                                                </div>
                                            </template>

                                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                                <div>
                                                    <label class="block text-xs font-bold text-slate-600 mb-1 uppercase">Plot Number</label>
                                                     <input type="text" 
                                                           x-model="current.plot_number"
                                                           :disabled="viewMode || dataFetched"
                                                           @input="if(applyLocationToAll) syncToAll('plot_number')"
                                                           placeholder="e.g., 402" 
                                                           class="w-full bg-white border border-slate-300 rounded-xl px-4 py-2.5 text-sm font-medium text-slate-700 placeholder:text-slate-400 focus:outline-none focus:ring-4 focus:ring-blue-500/10 focus:border-blue-500 transition-all hover:border-slate-400 shadow-sm disabled:bg-slate-50 disabled:text-slate-400">
                                                 </div>
                                                <div>
                                                    <label class="block text-xs font-bold text-slate-600 mb-1 uppercase">TP Number</label>
                                                     <input type="text" 
                                                           x-model="current.tp_no"
                                                           :disabled="viewMode || dataFetched"
                                                           @input="if(applyLocationToAll) syncToAll('tp_no')"
                                                           placeholder="e.g., TP/1024" 
                                                           class="w-full bg-white border border-slate-300 rounded-xl px-4 py-2.5 text-sm font-medium text-slate-700 placeholder:text-slate-400 focus:outline-none focus:ring-4 focus:ring-blue-500/10 focus:border-blue-500 transition-all hover:border-slate-400 shadow-sm disabled:bg-slate-50 disabled:text-slate-400">
                                                 </div>
                                            </div>

                                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                                                <div>
                                                    <label class="block text-xs font-bold text-slate-600 mb-1 uppercase text-left">District</label>
                                                     <select x-model="current.district_id" 
                                                             :disabled="viewMode || dataFetched"
                                                             @change="handleDistrictSelectChange(current)"
                                                             class="w-full bg-white border border-slate-300 rounded-xl px-4 py-2.5 text-sm font-medium text-slate-700 focus:outline-none focus:ring-4 focus:ring-blue-500/10 focus:border-blue-500 transition-all hover:border-slate-400 shadow-sm disabled:bg-slate-50 disabled:text-slate-400 appearance-none bg-no-repeat bg-[right_1rem_center] bg-[length:1em_1em]"
                                                             style="background-image: url('data:image/svg+xml;charset=utf-8,%3Csvg xmlns=%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22 fill=%22none%22 viewBox=%220 0 20 20%22%3E%3Cpath stroke=%22%236b7280%22 stroke-linecap=%22round%22 stroke-linejoin=%22round%22 stroke-width=%221.5%22 d=%22m6 8 4 4 4-4%22%2F%3E%3C%2Fsvg%3E')">
                                                        <option value="">Select District</option>
                                                        <template x-for="district in districts" :key="district.id">
                                                            <option :value="district.id" x-text="district.name"></option>
                                                        </template>
                                                    </select>
                                                    <div x-cloak x-show="isOtherDistrict(current.district_id)" class="mt-3">
                                                        <label class="block text-xs font-bold text-slate-600 mb-1 uppercase">Specify District <span class="text-red-500">*</span></label>
                                                        <input type="text"
                                                               x-model="current.district_other"
                                                               :disabled="viewMode || dataFetched"
                                                               @input="if(applyLocationToAll) syncToAll('district_other')"
                                                               placeholder="Enter district name"
                                                               class="w-full bg-white border border-slate-300 rounded-xl px-4 py-2.5 text-sm font-medium text-slate-700 placeholder:text-slate-400 focus:outline-none focus:ring-4 focus:ring-blue-500/10 focus:border-blue-500 transition-all hover:border-slate-400 shadow-sm disabled:bg-slate-50 disabled:text-slate-400">
                                                    </div>
                                                </div>
                                                <div>
                                                    <label class="block text-xs font-bold text-slate-600 mb-1 uppercase text-left">LGA</label>
                                                     <select x-model="current.lga_id" 
                                                             :disabled="viewMode || dataFetched"
                                                             @change="if(applyLocationToAll) syncToAll('lga_id')"
                                                             class="w-full bg-white border border-slate-300 rounded-xl px-4 py-2.5 text-sm font-medium text-slate-700 focus:outline-none focus:ring-4 focus:ring-blue-500/10 focus:border-blue-500 transition-all hover:border-slate-400 shadow-sm disabled:bg-slate-50 disabled:text-slate-400 appearance-none bg-no-repeat bg-[right_1rem_center] bg-[length:1em_1em]"
                                                             style="background-image: url('data:image/svg+xml;charset=utf-8,%3Csvg xmlns=%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22 fill=%22none%22 viewBox=%220 0 20 20%22%3E%3Cpath stroke=%22%236b7280%22 stroke-linecap=%22round%22 stroke-linejoin=%22round%22 stroke-width=%221.5%22 d=%22m6 8 4 4 4-4%22%2F%3E%3C%2Fsvg%3E')">
                                                        <option value="">Select LGA</option>
                                                        <template x-for="lga in lgas" :key="lga.id">
                                                            <option :value="lga.id" x-text="lga.name"></option>
                                                        </template>
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="md:col-span-2">
                                                    <label class="block text-xs font-bold text-slate-600 mb-1 uppercase tracking-tight">Location Preview (Auto-Generated)</label>
                                                    <textarea x-text="location" rows="1" readonly class="w-full bg-slate-50 border-slate-200 rounded-lg text-slate-500 px-4 py-2.5 text-xs font-medium italic shadow-inner outline-none"></textarea>
                                                </div>
                                            </div>
                                        </div>

                                     <!-- Card 4: Metadata -->
                                    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden lg:col-span-2">
                                        <div class="bg-slate-50 px-6 py-3 border-b border-slate-200 flex items-center gap-2">
                                            <i data-lucide="info" class="h-4 w-4 text-blue-600"></i>
                                            <h2 class="font-bold text-sm text-slate-800 uppercase tracking-wide">Matching Metadata</h2>
                                        </div>
                                        <div class="p-5">
                                            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                                                <div>
                                                    <label class="block text-xs font-bold text-slate-600 mb-1 uppercase">Date Matched</label>
                                                    <div class="px-4 py-2 bg-slate-50 border border-slate-200 rounded-lg text-xs text-slate-600 flex items-center gap-2">
                                                        <i data-lucide="calendar" class="h-3 w-3"></i>
                                                        <span x-text="currentDate"></span>
                                                    </div>
                                                </div>
                                                <div>
                                                    <label class="block text-xs font-bold text-slate-600 mb-1 uppercase">Time Matched</label>
                                                    <div class="px-4 py-2 bg-slate-50 border border-slate-200 rounded-lg text-xs text-slate-600 flex items-center gap-2">
                                                        <i data-lucide="clock" class="h-3 w-3"></i>
                                                        <span x-text="currentTimeOnly"></span>
                                                    </div>
                                                </div>
                                                <div>
                                                    <label class="block text-xs font-bold text-slate-600 mb-1 uppercase">Matched By</label>
                                                    <div class="px-4 py-2 bg-slate-50 border border-slate-200 rounded-lg text-xs text-slate-600 flex items-center gap-2">
                                                        <i data-lucide="user" class="h-3 w-3"></i>
                                                        <span>{{ $user->name ?? $user->email }}</span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="px-8 py-5 border-t border-slate-100 bg-white flex items-center justify-end gap-3 font-[Inter] sticky bottom-0 z-10">
                                    <button @click="showGenerateModal = false" class="px-6 py-2.5 text-[11px] font-black uppercase tracking-wider text-slate-500 hover:text-slate-700 hover:bg-slate-50 rounded-xl transition-all">
                                        <span>Cancel</span>
                                    </button>
                                    <button @click="generateMls()" :disabled="loading || !formData.full_file_number || fileIndexed === false" class="px-8 py-2.5 bg-blue-600 hover:bg-blue-700 disabled:bg-slate-400 disabled:cursor-not-allowed text-white text-[11px] font-black uppercase tracking-widest rounded-xl transition-all active:scale-95 shadow-lg shadow-blue-100 flex items-center gap-2 min-w-[140px] justify-center">
                                        <template x-if="loading">
                                             <i data-lucide="loader-2" class="h-4 w-4 animate-spin"></i>
                                        </template>
                                        <span x-text="loading ? 'MATCHING...' : 'MATCH'">MATCH</span>
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
                                    <h2 class="text-xl font-bold text-slate-900" x-text="viewMode ? 'View MLPP Record' : 'Edit MLPP Record'"></h2>
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
                                             <!-- customer type  -->
                                              <div>
                                                <label class="block text-xs font-bold text-slate-600 mb-1 uppercase">Customer Type</label>
                                                <div class="px-4 py-2 bg-slate-50 border border-slate-200 rounded-lg text-xs text-slate-700 font-bold" x-text="formData.customer_type || 'N/A'"></div>
                                             </div>
                                               
                                            <div>
                                                <label class="block text-xs font-bold text-slate-600 mb-1 uppercase">File Title <span class="text-red-500">*</span></label>
                                                <input type="text" x-model="formData.file_title" :disabled="viewMode" placeholder="Enter File Title" class="w-full bg-white border border-slate-300 rounded-xl px-4 py-2.5 text-sm font-medium text-slate-700 placeholder:text-slate-400 focus:outline-none focus:ring-4 focus:ring-blue-500/10 focus:border-blue-500 transition-all hover:border-slate-400 shadow-sm disabled:bg-slate-50">
                                            </div>


                                        </div>
                                    </div>

                                    <!-- Card 2: Location Details -->
                                    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
                                        <div class="bg-slate-50 px-6 py-3 border-b border-slate-200 flex items-center gap-2">
                                            <i data-lucide="map-pin" class="h-4 w-4 text-blue-600"></i>
                                            <h2 class="font-bold text-sm text-slate-800 uppercase tracking-wide">Location Details</h2>
                                        </div>
                                        <div class="p-5 space-y-4">
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
                                            <div class="grid grid-cols-2 gap-4">
                                                <div>
                                                    <label class="block text-xs font-bold text-slate-600 mb-1 uppercase">District</label>
                                                    <select x-model="formData.district_id" @change="handleDistrictSelectChange(formData)" :disabled="viewMode" class="w-full bg-white border border-slate-300 rounded-xl px-4 py-2.5 text-sm font-medium text-slate-700 focus:outline-none focus:ring-4 focus:ring-blue-500/10 focus:border-blue-500 transition-all hover:border-slate-400 shadow-sm disabled:bg-slate-50">
                                                        <option value="">Select District</option>
                                                        <template x-for="district in districts" :key="district.id">
                                                            <option :value="district.id" x-text="district.name"></option>
                                                        </template>
                                                    </select>
                                                    <div x-cloak x-show="isOtherDistrict(formData.district_id)" class="mt-3">
                                                        <label class="block text-xs font-bold text-slate-600 mb-1 uppercase">Specify District <span class="text-red-500">*</span></label>
                                                        <input type="text" x-model="formData.district_other" :disabled="viewMode" placeholder="Enter district name" class="w-full bg-white border border-slate-300 rounded-xl px-4 py-2.5 text-sm font-medium text-slate-700 placeholder:text-slate-400 focus:outline-none focus:ring-4 focus:ring-blue-500/10 focus:border-blue-500 transition-all hover:border-slate-400 shadow-sm disabled:bg-slate-50">
                                                    </div>
                                                </div>
                                                <div>
                                                    <label class="block text-xs font-bold text-slate-600 mb-1 uppercase">LGA</label>
                                                    <select x-model="formData.lga_id" :disabled="viewMode" class="w-full bg-white border border-slate-300 rounded-xl px-4 py-2.5 text-sm font-medium text-slate-700 focus:outline-none focus:ring-4 focus:ring-blue-500/10 focus:border-blue-500 transition-all hover:border-slate-400 shadow-sm disabled:bg-slate-50">
                                                        <option value="">Select LGA</option>
                                                        <template x-for="lga in lgas" :key="lga.id">
                                                            <option :value="lga.id" x-text="lga.name"></option>
                                                        </template>
                                                    </select>
                                                </div>
                                            </div>
                                            <div>
                                                <label class="block text-xs font-bold text-slate-600 mb-1 uppercase italic opacity-60">Location Preview</label>
                                                <textarea x-text="location" rows="1" readonly class="w-full bg-slate-50 border-slate-100 rounded-lg text-slate-400 px-4 py-2 text-xs font-medium italic outline-none"></textarea>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                </div>

                                <div class="mt-8 px-8 py-5 border-t border-slate-100 bg-white flex items-center justify-end gap-3 font-[Inter] sticky bottom-0 z-10 rounded-b-3xl">
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
                            <h2 class="text-lg font-black text-slate-800 uppercase tracking-tight" x-text="batchNo + ' Members'"></h2>
                            <p class="text-xs text-slate-400 font-bold uppercase tracking-widest">Listing all records in this batch</p>
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

    @include('admin.footer')
</div>

@push('scripts')
<script src="{{ asset('js/mls_file_no_matching.js') }}?v={{ filemtime(public_path('js/mls_file_no_matching.js')) }}"></script>

<style>
    .select2-container--default .select2-selection--single {
        border-radius: 0.75rem !important;
        border: 1px solid #e2e8f0 !important;
        height: 42px !important;
        padding: 6px 12px !important;
        background-color: white !important;
    }
    .select2-container--default .select2-selection--single .select2-selection__rendered {
        line-height: normal !important;
        font-size: 0.75rem !important;
        font-weight: 700 !important;
        color: #334155 !important;
        padding-top: 4px !important;
    }
    .select2-container--default .select2-selection--single .select2-selection__arrow {
        height: 40px !important;
    }
    .select2-dropdown {
        border-radius: 0.75rem !important;
        border: 1px solid #e2e8f0 !important;
        box-shadow: 0 10px 15px -3px rgb(0 0 0 / 0.1) !important;
        overflow: hidden !important;
        z-index: 9999 !important;
    }
</style>
@include('components.global-fileno-modal')
@endpush
@endsection
