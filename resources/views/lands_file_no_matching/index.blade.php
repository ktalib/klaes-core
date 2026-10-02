@extends('layouts.app')

@section('content')
@push('styles')
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
@endpush
@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="{{ asset('js/global-fileno-modal.js') }}"></script>
@endpush
<div class="flex-1 overflow-auto bg-slate-50/60" x-data='(() => {
    const state = mlsMatchingGenerator({
        districts: @json($districts),
        lgas: @json($lgas),
        csrfToken: "{{ csrf_token() }}",
        availableUrl: "{{ route('lands-file-no-matching.available') }}",
        detailsUrl: "{{ route('lands-file-no-matching.details') }}",
        worldTimeUrl: "{{ route('world-time') }}",
        storeUrl: "{{ route('lands-file-no-matching.store') }}",
        editUrl: "{{ url('lands-file-no-matching') }}",
        updateUrl: "{{ url('lands-file-no-matching') }}",
        currentDate: "{{ date('Y-m-d') }}",
        currentTimeOnly: "{{ date('H:i:s') }}",
        currentYear: "{{ date('Y') }}",
        excludeMatchedType: "lands",
        requireLga: false
    });
    state.tab = "matching";
    state.showFolderModal = false;
    state.folderFileNumber = "";
    state.folderFiles = [];
    state.folderLoading = false;
    state.folderError = "";
    state.folderPreview = null;
    state.folderFilesUrl = "{{ route('lands-file-no-matching.folder-files') }}";
    state.openFolderModal = function(fileNumber) {
        const safeFileNumber = fileNumber || "";
        this.folderFileNumber = safeFileNumber;
        this.folderFiles = [];
        this.folderError = "";
        this.folderPreview = null;
        this.showFolderModal = true;
        this.loadFolderFiles();
    };
    state.loadFolderFiles = function() {
        const safeFileNumber = this.folderFileNumber || "";
        if (!safeFileNumber) {
            this.folderError = "No file number provided.";
            return;
        }
        this.folderLoading = true;
        this.folderError = "";
        fetch(`${this.folderFilesUrl}?folder_name=${encodeURIComponent(safeFileNumber)}`, {
            headers: { "Accept": "application/json", "X-Requested-With": "XMLHttpRequest" }
        })
        .then(async (res) => {
            const data = await res.json().catch(() => ({}));
            if (!res.ok || !data.success) {
                this.folderFiles = [];
                this.folderError = data.message || "Unable to load files for this folder.";
                return;
            }
            this.folderFiles = data.files || [];
            if (this.folderFiles.length === 0) {
                this.folderError = "This folder does not contain any files.";
            }
        })
        .catch(() => {
            this.folderFiles = [];
            this.folderError = "An error occurred while loading files.";
        })
        .finally(() => {
            this.folderLoading = false;
            this.$nextTick(() => { if (window.lucide) window.lucide.createIcons(); });
        });
    };
    state.closeFolderModal = function() {
        this.showFolderModal = false;
        this.folderFileNumber = "";
        this.folderFiles = [];
        this.folderError = "";
        this.folderPreview = null;
    };
    return state;
})()'>
    @include('admin.header')
 
    <div class="py-12 bg-slate-50 min-h-screen">
        <div class="max-w-[95%] mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-4">
                <div>
                    <h1 class="text-3xl font-extrabold text-slate-900 tracking-tight">Match Existing FileNo (Lands)</h1>
                    <p class="text-slate-500 text-sm mt-1">Manage and match existing Lands file numbers.</p>
                </div>
                <div class="flex items-center gap-3 w-full md:w-auto">
                    <form action="{{ route('lands-file-no-matching.index') }}" method="GET" class="relative group flex-1 md:w-80">
                        <i data-lucide="search" class="absolute left-3.5 top-1/2 -translate-y-1/2 h-4 w-4 text-slate-400 group-focus-within:text-emerald-500 transition-colors"></i>
                        <input type="text" 
                               name="search" 
                               value="{{ request('search') }}"
                               placeholder="Search file, title, or location..." 
                               class="w-full pl-10 pr-4 py-2.5 bg-white border border-slate-200 rounded-xl text-sm focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 outline-none transition-all shadow-sm">
                    </form>
                    <button 
                        @click="showGenerateModal = true" 
                        type="button"
                        class="inline-flex items-center gap-2 px-6 py-3 bg-emerald-600 text-white font-bold rounded-xl hover:bg-emerald-700 transition-all shadow-lg shadow-emerald-100 whitespace-nowrap"
                    >
                        <i data-lucide="plus-circle" class="h-5 w-5"></i>
                        <span>Match Existing FileNo (Lands) </span>
                    </button>
                </div>
            </div>

            <div class="flex flex-wrap gap-3 mb-8">
                {{-- <button @click="tab = 'matching'" :class="tab === 'matching' ? 'bg-emerald-600 text-white shadow-lg shadow-emerald-200' : 'bg-white text-slate-700 border border-slate-200'" class="px-5 py-2.5 rounded-xl text-sm font-bold transition-all">Match/Create</button> --}}
                <a href="{{ route('fileindexing.create', ['url' => 'lands']) }}" class="px-5 py-2.5 rounded-xl text-sm font-bold transition-all bg-white text-slate-700 border border-slate-200 hover:border-emerald-300 hover:text-emerald-700">Index New File</a>
            </div>

            <div x-show="tab === 'matching'" x-cloak>
            <!-- Statistics Cards -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-10">
                <!-- Daily Stats -->
                <div class="bg-white p-6 rounded-3xl border border-slate-200 shadow-sm hover:shadow-md transition-all group overflow-hidden relative">
                    <div class="absolute -right-4 -bottom-4 opacity-[0.03] group-hover:scale-110 transition-transform duration-500">
                        <i data-lucide="calendar-check" class="h-32 w-32 text-emerald-600"></i>
                    </div>
                    <div class="flex items-center gap-4 relative z-10">
                        <div class="p-3 bg-emerald-50 text-emerald-600 rounded-2xl border border-emerald-100 shadow-sm">
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
                <div class="p-6 rounded-3xl shadow-sm hover:shadow-md transition-all group overflow-hidden relative text-white bg-gradient-to-br from-emerald-600 to-emerald-800 border-none">
                    <div class="absolute -right-4 -bottom-4 opacity-10 group-hover:scale-110 transition-transform duration-500">
                        <i data-lucide="database" class="h-32 w-32 text-white"></i>
                    </div>
                    <div class="flex items-center gap-4 relative z-10">
                        <div class="p-3 bg-white/20 text-white rounded-2xl border border-white/30 shadow-sm backdrop-blur-md">
                            <i data-lucide="database" class="h-6 w-6"></i>
                        </div>
                        <div>
                            <p class="text-[10px] font-black text-emerald-100 uppercase tracking-widest">Total</p>
                            <h3 class="text-2xl font-black tracking-tight text-white">{{ number_format($stats['total']) }}</h3>
                        </div>
                    </div>
                    <div class="mt-4 pt-4 border-t border-white/10 flex items-center justify-between text-[10px] font-bold text-emerald-100 uppercase tracking-widest">
                        <span>All Lands Shadow Files</span>
                        <span class="px-2 py-0.5 bg-white/20 text-white rounded-lg border border-white/20">LSF</span>
                    </div>
                </div>
            </div>

            <!-- Existing Records Table -->
            <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
                <div class="bg-slate-50 px-6 py-4 border-b border-slate-200">
                    <h3 class="font-bold text-slate-800">Matched Lands File Records</h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-slate-50/50 border-b border-slate-200 text-[10px] font-black text-slate-500 uppercase tracking-widest">
                                <th class="px-6 py-4 whitespace-nowrap">File Number</th>
                                <th class="px-6 py-4 whitespace-nowrap">Title / Name</th>
                                <th class="px-6 py-4 text-center whitespace-nowrap">Plot No</th>
                                <th class="px-6 py-4 whitespace-nowrap">Location</th>
                                <th class="px-6 py-4 whitespace-nowrap">LGA</th>
                                <th class="px-6 py-4 text-center whitespace-nowrap">Source</th>
                                <th class="px-6 py-4 whitespace-nowrap">Matched By</th>
                                <th class="px-6 py-4 text-center whitespace-nowrap">Time Matched</th>
                                <th class="px-6 py-4 text-center whitespace-nowrap">Date Matched</th>
                                <th class="px-6 py-4 text-center whitespace-nowrap">Status</th>
                                <th class="px-6 py-4 text-right whitespace-nowrap">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-sm text-slate-600">
                            @forelse($records as $record)
                            <tr class="hover:bg-slate-50/50 transition-colors group">
                                 <td class="px-6 py-4 whitespace-nowrap text-xs">
                                    <span class="font-mono font-bold text-emerald-600">{{ $record->file_no }}</span>
                                </td>
                                <td class="px-6 py-4 font-medium text-slate-700 whitespace-nowrap">{{ Str::limit($record->title, 30) }}</td>
                                <td class="px-6 py-4 text-center font-mono text-xs whitespace-nowrap">{{ $record->plot_no ?? '—' }}</td>
                                <td class="px-6 py-4 text-xs text-slate-500 whitespace-nowrap">{{ Str::limit($record->location, 40) }}</td>
                                <td class="px-6 py-4 text-xs font-bold text-slate-600 whitespace-nowrap">{{ $record->lga ?? '—' }}</td>
                                <td class="px-6 py-4 text-center whitespace-nowrap">
                                    @if(($record->source ?? '') === 'Indexed')
                                    <span class="inline-flex items-center px-3 py-1 rounded-full text-[9px] font-black bg-blue-50 text-blue-700 border border-blue-100 uppercase tracking-widest shadow-sm">
                                        Indexed
                                    </span>
                                    @else
                                    <span class="inline-flex items-center px-3 py-1 rounded-full text-[9px] font-black bg-amber-50 text-amber-700 border border-amber-100 uppercase tracking-widest shadow-sm">
                                        Matched
                                    </span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 text-xs whitespace-nowrap">
                                    <div class="flex items-center gap-2">
                                        <div class="w-6 h-6 rounded-lg bg-emerald-50 flex items-center justify-center text-[10px] font-black text-emerald-600 border border-emerald-100">
                                            S
                                        </div>
                                        <span class="font-bold text-slate-600">System</span>
                                    </div>
                                </td>
                                <td class="px-6 py-4 text-center font-black text-slate-500 text-[11px] whitespace-nowrap">
                                    <span class="px-2 py-1 bg-slate-100 rounded-md border border-slate-200">{{ $record->time_matched ? \Carbon\Carbon::parse($record->time_matched)->format('H:i:s') : '—' }}</span>
                                </td>
                                <td class="px-6 py-4 text-center font-black text-emerald-600 text-[11px] whitespace-nowrap">
                                    <span class="px-2 py-1 bg-emerald-50 rounded-md border border-emerald-100">{{ $record->date_matched ? \Carbon\Carbon::parse($record->date_matched)->format('jS M, Y') : '—' }}</span>
                                </td>
                                <td class="px-6 py-4 text-center whitespace-nowrap">
                                    <span class="inline-flex items-center px-3 py-1 rounded-full text-[9px] font-black bg-emerald-50 text-emerald-700 border border-emerald-100 uppercase tracking-widest shadow-sm">
                                        LSF MATCHED
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <div class="relative inline-block" x-data="{ open: false }">
                                        <button @click="open = !open" @click.away="open = false" class="p-1 hover:bg-slate-100 rounded-md transition-colors">
                                            <i data-lucide="more-vertical" class="h-4 w-4 text-slate-400"></i>
                                        </button>
                                        <div x-show="open" 
                                             x-transition:enter="transition ease-out duration-100"
                                             x-transition:enter-start="transform opacity-0 scale-95"
                                             x-transition:enter-end="transform opacity-100 scale-100"
                                             class="absolute right-0 {{ $loop->remaining < 2 ? 'bottom-full mb-1' : 'top-full mt-1' }} w-32 bg-white border border-slate-200 rounded-lg shadow-xl z-50 overflow-hidden">
                                            <button @click="viewRecord({{ $record->id }})" class="w-full px-4 py-2 text-left text-xs font-bold text-slate-700 hover:bg-emerald-50 hover:text-emerald-600 flex items-center gap-2">
                                                <i data-lucide="eye" class="h-3.5 w-3.5"></i>
                                                View
                                            </button>
                                            <button @click.prevent="openFolderModal('{{ addslashes($record->file_no) }}')" class="w-full px-4 py-2 text-left text-xs font-bold text-slate-700 hover:bg-blue-50 hover:text-blue-600 flex items-center gap-2">
                                                <i data-lucide="folder-open" class="h-3.5 w-3.5"></i>
                                                Open File
                                            </button>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="12" class="px-6 py-12 text-center text-slate-400 italic">No records found. Click "Match Existing FileNo (Lands) " to start.</td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if($records->hasPages())
                <div class="px-6 py-4 border-t border-slate-100">
                    {{ $records->links() }}
                </div>
                @endif
            </div>

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
                                    <h2 class="text-xl font-bold text-slate-900">Match Lands File Numbers</h2>
                                    <p class="text-sm text-slate-500">Configure and match existing serial-based records</p>
                                </div>
                                <button @click="showGenerateModal = false" class="text-slate-400 hover:text-red-500 transition-colors">
                                    <i data-lucide="x-circle" class="h-8 w-8"></i>
                                </button>
                            </div>

                            <div class="flex-1 overflow-y-auto p-8 relative min-h-[400px]">
                                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                                    <!-- Card 1: File Details -->
                                    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
                                        <div class="bg-slate-50 px-6 py-3 border-b border-slate-200 flex items-center gap-2">
                                            <i data-lucide="file-text" class="h-4 w-4 text-emerald-600"></i>
                                            <h2 class="font-bold text-sm text-slate-800 uppercase tracking-wide">File Details</h2>
                                        </div>
                                        <div class="p-5 space-y-4">
                                            <div>
                                                <label class="block text-xs font-bold text-slate-600 mb-1 uppercase">Select File Number <span class="text-red-500">*</span></label>
                                                <div class="relative group/input">
                                                     <input type="text" 
                                                           x-model="formData.full_file_number" 
                                                           readonly 
                                                           @click="openFileSelector()"
                                                           placeholder="Click to select file..." 
                                                           class="w-full bg-slate-50 border border-slate-300 rounded-xl px-4 py-2.5 text-sm font-bold text-emerald-600 cursor-pointer hover:bg-slate-100 transition-all shadow-sm font-mono disabled:opacity-75 disabled:cursor-not-allowed">
                                                     <div class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 group-hover/input:text-emerald-500 transition-colors" x-show="!dataFetched">
                                                         <i data-lucide="search" class="h-4 w-4"></i>
                                                     </div>
                                                     <div class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-auto" x-show="dataFetched">
                                                         <button type="button" @click="resetForm()" class="p-1 hover:bg-slate-100 rounded-lg text-slate-400 hover:text-red-500 transition-all" title="Reset Selection">
                                                             <i data-lucide="refresh-cw" class="h-4 w-4"></i>
                                                         </button>
                                                     </div>
                                                 </div>
                                             </div>
                                              <div x-show="dataFetched">
                                                <label class="block text-xs font-bold text-slate-600 mb-1 uppercase">Customer Type</label>
                                                <div class="px-4 py-2 bg-slate-50 border border-slate-200 rounded-lg text-xs text-slate-700 font-bold" x-text="formData.customer_type || 'N/A'"></div>
                                             </div>
                                             <div>
                                                <label class="block text-xs font-bold text-slate-600 mb-1 uppercase">File Title <span class="text-red-500 ">*</span></label>
                                                <input type="text" x-model="formData.file_title" placeholder="Enter File Title" class="w-full bg-white border border-slate-300 rounded-xl px-4 py-2.5 text-sm font-medium text-slate-700 placeholder:text-slate-400 focus:outline-none focus:ring-4 focus:ring-emerald-500/10 focus:border-emerald-500 transition-all hover:border-slate-400 shadow-sm disabled:bg-slate-50 disabled:text-slate-400">
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Card 3: Location Details -->
                                    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
                                        <div class="bg-slate-50 px-6 py-3 border-b border-slate-200 flex items-center justify-between">
                                            <div class="flex items-center gap-2">
                                                <i data-lucide="map-pin" class="h-4 w-4 text-emerald-600"></i>
                                                <h2 class="font-bold text-sm text-slate-800 uppercase tracking-wide">Location Details</h2>
                                            </div>
                                        </div>
                                        <div class="p-5">
                                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                                <div>
                                                    <label class="block text-xs font-bold text-slate-600 mb-1 uppercase">Plot Number</label>
                                                     <input type="text" 
                                                           x-model="current.plot_number"
                                                           :disabled="viewMode"
                                                           placeholder="e.g., 402" 
                                                           class="w-full bg-white border border-slate-300 rounded-xl px-4 py-2.5 text-sm font-medium text-slate-700 placeholder:text-slate-400 focus:outline-none focus:ring-4 focus:ring-emerald-500/10 focus:border-emerald-500 transition-all hover:border-slate-400 shadow-sm disabled:bg-slate-50 disabled:text-slate-400">
                                                 </div>
                                            </div>

                                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                                                <div>
                                                    <label class="block text-xs font-bold text-slate-600 mb-1 uppercase text-left">District</label>
                                                     <select x-model="current.district_id" 
                                                              :disabled="viewMode"
                                                              @change="handleDistrictSelectChange(current)"
                                                              class="w-full bg-white border border-slate-300 rounded-xl px-4 py-2.5 text-sm font-medium text-slate-700 focus:outline-none focus:ring-4 focus:ring-emerald-500/10 focus:border-emerald-500 transition-all hover:border-slate-400 shadow-sm disabled:bg-slate-50 disabled:text-slate-400 appearance-none bg-no-repeat bg-[right_1rem_center] bg-[length:1em_1em]"
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
                                                                :disabled="viewMode"
                                                                @input="if(applyLocationToAll) syncToAll('district_other')"
                                                                placeholder="Enter district name"
                                                                class="w-full bg-white border border-slate-300 rounded-xl px-4 py-2.5 text-sm font-medium text-slate-700 placeholder:text-slate-400 focus:outline-none focus:ring-4 focus:ring-emerald-500/10 focus:border-emerald-500 transition-all hover:border-slate-400 shadow-sm disabled:bg-slate-50 disabled:text-slate-400">
                                                     </div>
                                                </div>
                                                <div>
                                                    <label class="block text-xs font-bold text-slate-600 mb-1 uppercase text-left">LGA</label>
                                                     <select x-model="current.lga_id" 
                                                              :disabled="viewMode"
                                                              class="w-full bg-white border border-slate-300 rounded-xl px-4 py-2.5 text-sm font-medium text-slate-700 focus:outline-none focus:ring-4 focus:ring-emerald-500/10 focus:border-emerald-500 transition-all hover:border-slate-400 shadow-sm disabled:bg-slate-50 disabled:text-slate-400 appearance-none bg-no-repeat bg-[right_1rem_center] bg-[length:1em_1em]"
                                                              style="background-image: url('data:image/svg+xml;charset=utf-8,%3Csvg xmlns=%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22 fill=%22none%22 viewBox=%220 0 20 20%22%3E%3Cpath stroke=%22%236b7280%22 stroke-linecap=%22round%22 stroke-linejoin=%22round%22 stroke-width=%221.5%22 d=%22m6 8 4 4 4-4%22%2F%3E%3C%2Fsvg%3E')">
                                                        <option value="">Select LGA</option>
                                                        <template x-for="lga in lgas" :key="lga.id">
                                                            <option :value="lga.id" x-text="lga.name"></option>
                                                        </template>
                                                     </select>
                                                </div>
                                            </div>
                                            <div class="md:col-span-2 mt-4">
                                                    <label class="block text-xs font-bold text-slate-600 mb-1 uppercase tracking-tight">Location Preview (Auto-Generated)</label>
                                                    <textarea x-text="location" rows="1" readonly class="w-full bg-slate-50 border-slate-200 rounded-lg text-slate-500 px-4 py-2.5 text-xs font-medium italic shadow-inner outline-none"></textarea>
                                                </div>
                                            </div>
                                        </div>

                                     <!-- Card 4: Metadata -->
                                    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden lg:col-span-2">
                                        <div class="bg-slate-50 px-6 py-3 border-b border-slate-200 flex items-center gap-2">
                                            <i data-lucide="info" class="h-4 w-4 text-emerald-600"></i>
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
                                    <button @click="generateMls()" :disabled="loading || !formData.full_file_number" class="px-8 py-2.5 bg-emerald-600 hover:bg-emerald-700 disabled:bg-slate-400 disabled:cursor-not-allowed text-white text-[11px] font-black uppercase tracking-widest rounded-xl transition-all active:scale-95 shadow-lg shadow-emerald-100 flex items-center gap-2 min-w-[140px] justify-center">
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
                                    <h2 class="text-xl font-bold text-slate-900" x-text="viewMode ? 'View Lands Record' : 'Edit Lands Record'"></h2>
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
                                         <div class="w-16 h-16 border-4 border-emerald-100 border-t-emerald-600 rounded-full animate-spin"></div>
                                         <div class="absolute inset-0 flex items-center justify-center">
                                             <i data-lucide="file-text" class="h-6 w-6 text-emerald-600 animate-pulse"></i>
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
                                            <i data-lucide="file-text" class="h-4 w-4 text-emerald-600"></i>
                                            <h2 class="font-bold text-sm text-slate-800 uppercase tracking-wide">File Details</h2>
                                        </div>
                                        <div class="p-5 space-y-4">
                                            <div>
                                                <label class="block text-xs font-bold text-slate-600 mb-1 uppercase">Full File Number</label>
                                                <div class="px-4 py-2 bg-emerald-50 border border-emerald-100 rounded-lg font-mono text-xs text-emerald-700 font-bold shadow-inner" x-text="formData.full_file_number"></div>
                                            </div>
                                              <div>
                                                <label class="block text-xs font-bold text-slate-600 mb-1 uppercase">Customer Type</label>
                                                <div class="px-4 py-2 bg-slate-50 border border-slate-200 rounded-lg text-xs text-slate-700 font-bold" x-text="formData.customer_type || 'N/A'"></div>
                                             </div>
                                               
                                            <div>
                                                <label class="block text-xs font-bold text-slate-600 mb-1 uppercase">File Title <span class="text-red-500">*</span></label>
                                                <input type="text" x-model="formData.file_title" :disabled="viewMode" placeholder="Enter File Title" class="w-full bg-white border border-slate-300 rounded-xl px-4 py-2.5 text-sm font-medium text-slate-700 placeholder:text-slate-400 focus:outline-none focus:ring-4 focus:ring-emerald-500/10 focus:border-emerald-500 transition-all hover:border-slate-400 shadow-sm disabled:bg-slate-50">
                                            </div>


                                        </div>
                                    </div>

                                    <!-- Card 2: Location Details -->
                                    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
                                        <div class="bg-slate-50 px-6 py-3 border-b border-slate-200 flex items-center gap-2">
                                            <i data-lucide="map-pin" class="h-4 w-4 text-emerald-600"></i>
                                            <h2 class="font-bold text-sm text-slate-800 uppercase tracking-wide">Location Details</h2>
                                        </div>
                                        <div class="p-5 space-y-4">
                                            <div class="grid grid-cols-2 gap-4">
                                                <div>
                                                    <label class="block text-xs font-bold text-slate-600 mb-1 uppercase">Plot Number</label>
                                                    <input type="text" x-model="formData.plot_number" :disabled="viewMode" placeholder="e.g., 402" class="w-full bg-white border border-slate-300 rounded-xl px-4 py-2.5 text-sm font-medium text-slate-700 placeholder:text-slate-400 focus:outline-none focus:ring-4 focus:ring-emerald-500/10 focus:border-emerald-500 transition-all hover:border-slate-400 shadow-sm disabled:bg-slate-50">
                                                </div>
                                            </div>
                                            <div class="grid grid-cols-2 gap-4">
                                                <div>
                                                    <label class="block text-xs font-bold text-slate-600 mb-1 uppercase">District</label>
                                                    <select x-model="formData.district_id" @change="handleDistrictSelectChange(formData)" :disabled="viewMode" class="w-full bg-white border border-slate-300 rounded-xl px-4 py-2.5 text-sm font-medium text-slate-700 focus:outline-none focus:ring-4 focus:ring-emerald-500/10 focus:border-emerald-500 transition-all hover:border-slate-400 shadow-sm disabled:bg-slate-50">
                                                        <option value="">Select District</option>
                                                        <template x-for="district in districts" :key="district.id">
                                                            <option :value="district.id" x-text="district.name"></option>
                                                        </template>
                                                    </select>
                                                    <div x-cloak x-show="isOtherDistrict(formData.district_id)" class="mt-3">
                                                        <label class="block text-xs font-bold text-slate-600 mb-1 uppercase">Specify District <span class="text-red-500">*</span></label>
                                                        <input type="text" x-model="formData.district_other" :disabled="viewMode" placeholder="Enter district name" class="w-full bg-white border border-slate-300 rounded-xl px-4 py-2.5 text-sm font-medium text-slate-700 placeholder:text-slate-400 focus:outline-none focus:ring-4 focus:ring-emerald-500/10 focus:border-emerald-500 transition-all hover:border-slate-400 shadow-sm disabled:bg-slate-50">
                                                    </div>
                                                </div>
                                                <div>
                                                    <label class="block text-xs font-bold text-slate-600 mb-1 uppercase">LGA</label>
                                                    <select x-model="formData.lga_id" :disabled="viewMode" class="w-full bg-white border border-slate-300 rounded-xl px-4 py-2.5 text-sm font-medium text-slate-700 focus:outline-none focus:ring-4 focus:ring-emerald-500/10 focus:border-emerald-500 transition-all hover:border-slate-400 shadow-sm disabled:bg-slate-50">
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
                                    <button x-show="!viewMode" @click="updateRecord()" :disabled="loading" class="px-8 py-2.5 bg-emerald-600 hover:bg-emerald-700 disabled:bg-slate-400 disabled:cursor-not-allowed text-white text-[11px] font-black uppercase tracking-widest rounded-xl transition-all active:scale-95 shadow-lg shadow-emerald-100 flex items-center gap-2 min-w-[140px] justify-center">
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
            <div x-show="tab === 'indexed'" x-cloak class="space-y-6">
                @include('components.indexed-files-table')
            </div>

            <div 
                x-show="showFolderModal" 
                class="fixed inset-0 z-[130] overflow-y-auto"
                x-transition:enter="transition ease-out duration-300"
                x-transition:enter-start="opacity-0"
                x-transition:enter-end="opacity-100"
                x-transition:leave="transition ease-in duration-200"
                x-transition:leave-start="opacity-100"
                x-transition:leave-end="opacity-0"
                style="display: none;"
                @keydown.escape.window="closeFolderModal()"
            >
                <div class="flex items-center justify-center min-h-screen px-4 py-6">
                    <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm" @click="closeFolderModal()"></div>

                    <div class="relative z-10 w-full max-w-6xl bg-white rounded-2xl shadow-2xl border border-slate-200 overflow-hidden flex flex-col max-h-[85vh]">
                        <div class="px-6 py-4 border-b border-slate-200 flex items-center justify-between">
                            <div class="flex items-center gap-3">
                                <div class="p-2 bg-blue-50 text-blue-600 rounded-xl border border-blue-100">
                                    <i data-lucide="folder-open" class="h-5 w-5"></i>
                                </div>
                                <div>
                                    <h3 class="text-base font-bold text-slate-800">File Documents</h3>
                                    <p class="text-xs text-slate-500 font-mono" x-text="folderFileNumber"></p>
                                </div>
                            </div>
                            <button type="button" @click="closeFolderModal()" class="text-slate-400 hover:text-red-500 transition-colors">
                                <i data-lucide="x" class="h-5 w-5"></i>
                            </button>
                        </div>

                        <div class="flex-1 overflow-y-auto p-6 bg-slate-50/60">
                            <!-- Loading -->
                            <div x-show="folderLoading" class="flex flex-col items-center justify-center py-20 gap-4">
                                <div class="w-12 h-12 border-4 border-blue-100 border-t-blue-600 rounded-full animate-spin"></div>
                                <span class="text-sm font-bold text-slate-500 uppercase tracking-widest">Loading files...</span>
                            </div>

                            <!-- Error / Empty -->
                            <div x-show="!folderLoading && folderError" class="flex flex-col items-center justify-center py-20 gap-3 text-center">
                                <div class="p-4 bg-slate-100 text-slate-400 rounded-2xl">
                                    <i data-lucide="folder-x" class="h-10 w-10"></i>
                                </div>
                                <p class="text-sm font-bold text-slate-500" x-text="folderError"></p>
                            </div>

                            <!-- Files grid -->
                            <div x-show="!folderLoading && !folderError && folderFiles.length > 0"
                                 class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-4">
                                <template x-for="file in folderFiles" :key="file.name">
                                    <div class="group bg-white rounded-xl border border-slate-200 shadow-sm hover:shadow-md hover:border-blue-300 transition-all overflow-hidden cursor-pointer"
                                         @click="folderPreview = file">
                                        <div class="aspect-square bg-slate-100 flex items-center justify-center overflow-hidden">
                                            <template x-if="file.is_image">
                                                <img :src="file.url" :alt="file.name" loading="lazy"
                                                     class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300">
                                            </template>
                                            <template x-if="!file.is_image">
                                                <div class="flex flex-col items-center gap-2 text-slate-400">
                                                    <i :data-lucide="file.is_pdf ? 'file-text' : 'file'" class="h-10 w-10"></i>
                                                    <span class="text-[10px] font-black uppercase" x-text="file.ext"></span>
                                                </div>
                                            </template>
                                        </div>
                                        <div class="px-3 py-2 border-t border-slate-100">
                                            <p class="text-[11px] font-medium text-slate-600 truncate" :title="file.name" x-text="file.name"></p>
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </div>

                    <!-- File preview lightbox -->
                    <div x-show="folderPreview" x-cloak
                         class="fixed inset-0 z-[140] flex items-center justify-center p-4"
                         @keydown.escape.window="folderPreview = null">
                        <div class="fixed inset-0 bg-black/80 backdrop-blur-sm" @click="folderPreview = null"></div>
                        <div class="relative z-10 max-w-5xl w-full max-h-[90vh] flex flex-col items-center">
                            <div class="w-full flex items-center justify-between text-white mb-3">
                                <span class="text-sm font-mono truncate" x-text="folderPreview?.name"></span>
                                <div class="flex items-center gap-3">
                                    <a :href="folderPreview?.url" target="_blank" rel="noopener noreferrer"
                                       class="inline-flex items-center gap-1.5 text-xs font-bold px-3 py-1.5 bg-white/10 hover:bg-white/20 rounded-lg transition-colors">
                                        <i data-lucide="external-link" class="h-3.5 w-3.5"></i> Open
                                    </a>
                                    <a :href="folderPreview?.url" :download="folderPreview?.name"
                                       class="inline-flex items-center gap-1.5 text-xs font-bold px-3 py-1.5 bg-white/10 hover:bg-white/20 rounded-lg transition-colors">
                                        <i data-lucide="download" class="h-3.5 w-3.5"></i> Download
                                    </a>
                                    <button type="button" @click="folderPreview = null" class="text-white/70 hover:text-white transition-colors">
                                        <i data-lucide="x" class="h-6 w-6"></i>
                                    </button>
                                </div>
                            </div>
                            <template x-if="folderPreview?.is_image">
                                <img :src="folderPreview?.url" :alt="folderPreview?.name"
                                     class="max-w-full max-h-[80vh] rounded-xl shadow-2xl object-contain bg-white">
                            </template>
                            <template x-if="folderPreview && !folderPreview.is_image">
                                <iframe :src="folderPreview?.url" class="w-full h-[80vh] rounded-xl bg-white shadow-2xl"></iframe>
                            </template>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @include('admin.footer')
</div>

@push('scripts')
<script src="{{ asset('js/mls_file_no_matching.js') }}"></script>

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
