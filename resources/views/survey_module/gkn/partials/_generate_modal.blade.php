{{-- Generate Application File Numbers, opened on GKN > Applications (user, 2026-10-30). A copy of the dialog on
     the GKN file number generator page (gkn_generation/index), driven by the same gknGeneratorFixed component
     and saving through gkn-generation.store. --}}
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
                                    <h2 class="text-xl font-bold text-slate-900">Generate Application File Numbers</h2>
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

                            <div class="flex-1 overflow-y-auto p-8">
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
                                                <select x-model="formData.type" class="w-full bg-white border border-slate-300 rounded-xl px-4 py-2.5 text-sm font-medium text-slate-700 focus:outline-none focus:ring-4 focus:ring-blue-500/10 focus:border-blue-500 transition-all hover:border-slate-400 shadow-sm">
                                                    <option value="">Select Prefix</option>
                                                    <option value="GKN">GKN</option>
                                                    <option value="MISCS">MISC KN</option>
                                                    <option value="LPKN">LPKN</option>
                                                    <option value="LPPL">LPPL</option>
                                                    <option value="RDKN">RDKN</option>
                                                </select>
                                            </div>
                                            <div>
                                                <label class="block text-xs font-bold text-slate-600 mb-1 uppercase">Customer Type <span class="text-red-500">*</span></label>
                                                <select x-model="formData.customer_type" class="w-full bg-white border border-slate-300 rounded-xl px-4 py-2.5 text-sm font-medium text-slate-700 focus:outline-none focus:ring-4 focus:ring-blue-500/10 focus:border-blue-500 transition-all hover:border-slate-400 shadow-sm">
                                                     <template x-for="ct in customerTypes" :key="ct.customer_type_id">
                                                         <option :value="ct.customer_type_name" x-text="ct.customer_type_name"></option>
                                                     </template>
                                                 </select>
                                             </div>
                                            <div x-show="formData.customer_type === 'Other'" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 -translate-y-2" x-transition:enter-end="opacity-100 translate-y-0" class="mt-2 text-left">
                                                <label class="block text-xs font-bold text-slate-600 mb-1 uppercase">Please Specify Type <span class="text-red-500">*</span></label>
                                                <input type="text" x-model="formData.other_customer_type" placeholder="e.g. NGO, Embassy..." class="w-full bg-white border border-slate-300 rounded-xl px-4 py-2.5 text-sm font-medium text-slate-700 placeholder:text-slate-400 focus:outline-none focus:ring-4 focus:ring-blue-500/10 focus:border-blue-500 transition-all hover:border-slate-400 shadow-sm">
                                            </div>
                                            <div>
                                                <label class="block text-xs font-bold text-slate-600 mb-1 uppercase">File Title <span class="text-red-500">*</span></label>
                                                <input type="text" x-model="formData.file_title" placeholder="Enter File Title" class="w-full bg-white border border-slate-300 rounded-xl px-4 py-2.5 text-sm font-medium text-slate-700 placeholder:text-slate-400 focus:outline-none focus:ring-4 focus:ring-blue-500/10 focus:border-blue-500 transition-all hover:border-slate-400 shadow-sm">
                                            </div>
                                            <div class="grid grid-cols-2 gap-4">
                                                <div>
                                                    <label class="block text-xs font-bold text-slate-600 mb-1 uppercase">Land Use <span class="text-red-500">*</span></label>
                                                    <select x-model="formData.land_use_id" class="w-full bg-white border border-slate-300 rounded-xl px-4 py-2.5 text-sm font-medium text-slate-700 focus:outline-none focus:ring-4 focus:ring-blue-500/10 focus:border-blue-500 transition-all hover:border-slate-400 shadow-sm">
                                                        <option value="">Select Land Use</option>
                                                        <template x-for="lu in landUses" :key="lu.id">
                                                            <option :value="lu.id" x-text="lu.landuse"></option>
                                                        </template>
                                                    </select>
                                                </div>
                                                <div>
                                                    <label class="block text-xs font-bold text-slate-600 mb-1 uppercase">Purpose <span class="text-red-500">*</span></label>
                                                    <select x-model="formData.purpose_id" class="w-full bg-white border border-slate-300 rounded-xl px-4 py-2.5 text-sm font-medium text-slate-700 focus:outline-none focus:ring-4 focus:ring-blue-500/10 focus:border-blue-500 transition-all hover:border-slate-400 shadow-sm">
                                                        <option value="">Select Purpose</option>
                                                        <template x-for="p in filteredPurposes" :key="p.id">
                                                            <option :value="p.id" x-text="p.name"></option>
                                                        </template>
                                                    </select>
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
                                                    <label class="block text-xs font-bold text-slate-600 mb-1 uppercase">Serial Number</label>
                                                    <div class="px-4 py-2 bg-slate-50 border border-slate-200 rounded-lg font-mono text-xs text-slate-600 shadow-inner" 
                                                         x-text="batchMode ? formData.serial_number + ' - ' + (parseInt(formData.serial_number) + parseInt(formData.quantity) - 1) : formData.serial_number"></div>
                                                </div>
                                                <div>
                                                     <label class="block text-xs font-bold text-slate-600 mb-1 uppercase" x-text="(editMode ? 'Full File Number' : (batchMode ? 'Number Range' : 'Full File Number'))"></label>
                                                      <div class="px-4 py-2 bg-blue-50 border border-blue-200 rounded-lg font-mono text-xs text-blue-700 font-bold shadow-inner"
                                                           :class="editMode ? 'opacity-50 select-none' : ''" 
                                                           x-text="editMode ? formData.full_file_number : (batchMode ? (formData.prefix + formData.serial_number + ' - ' + formData.prefix + (parseInt(formData.serial_number) + parseInt(formData.quantity) - 1)) : (formData.prefix + formData.serial_number))"></div>
                                                 </div>
                                            </div>
 
                                            <div>
                                                <label class="block text-xs font-bold text-slate-600 mb-1 uppercase">Tracking ID</label>
                                                <div class="px-4 py-2 bg-red-50 border border-red-100 rounded-lg font-mono text-xs text-red-600 font-extrabold shadow-inner" 
                                                     x-text="loadingTrackingId ? 'Searching...' : (nextGkn ? (batchMode ? nextGkn.tracking_id + ' (start)' : nextGkn.tracking_id) : 'Not Available')"></div>
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

                                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                                                @include('gkn_generation.partials._district_picker', ['ctx' => 'current'])
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
                                            <div class="md:col-span-2">
                                                    <label class="block text-xs font-bold text-slate-600 mb-1 uppercase tracking-tight">Location Preview (Auto-Generated)</label>
                                                    <textarea x-text="location" rows="1" readonly class="w-full bg-slate-50 border-slate-200 rounded-lg text-slate-500 px-4 py-2.5 text-xs font-medium italic shadow-inner outline-none"></textarea>
                                                </div>
                                            </div>
                                        </div>

                                    <!-- Card 4: Metadata -->
                                    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
                                        <div class="bg-slate-50 px-6 py-3 border-b border-slate-200 flex items-center gap-2">
                                            <i data-lucide="info" class="h-4 w-4 text-blue-600"></i>
                                            <h2 class="font-bold text-sm text-slate-800 uppercase tracking-wide">Generation Metadata</h2>
                                        </div>
                                        <div class="p-5 space-y-4">
                                            <div>
                                                <label class="block text-xs font-bold text-slate-600 mb-1 uppercase">Commissioning Date</label>
                                                <div class="px-4 py-2 bg-slate-50 border border-slate-200 rounded-lg text-xs text-slate-600 flex items-center gap-2">
                                                    <i data-lucide="calendar" class="h-3 w-3"></i>
                                                    <span x-text="currentDate"></span>
                                                </div>
                                            </div>
                                            <div>
                                                <label class="block text-xs font-bold text-slate-600 mb-1 uppercase">Commissioning Time</label>
                                                <div class="px-4 py-2 bg-slate-50 border border-slate-200 rounded-lg text-xs text-slate-600 flex items-center gap-2">
                                                    <i data-lucide="clock" class="h-3 w-3"></i>
                                                    <span x-text="currentTimeOnly"></span>
                                                </div>
                                            </div>
                                            <div>
                                                <label class="block text-xs font-bold text-slate-600 mb-1 uppercase">Commissioned By</label>
                                                <div class="px-4 py-2 bg-slate-50 border border-slate-200 rounded-lg text-xs text-slate-600 flex items-center gap-2">
                                                    <i data-lucide="user" class="h-3 w-3"></i>
                                                    <span>{{ auth()->user()?->name ?? auth()->user()?->email }}</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div> 
                                </div>
                            </div> <!-- End of Modal Body (overflow-y-auto) -->

                            <!-- Modal Footer -->
                            <div class="px-8 py-5 border-t border-slate-100 bg-white flex items-center justify-end gap-3 sticky bottom-0 z-10 rounded-b-3xl">
                                <button @click="showGenerateModal = false" type="button" class="px-6 py-2.5 text-[11px] font-black uppercase tracking-wider text-slate-500 hover:text-slate-700 hover:bg-slate-50 rounded-xl transition-all">
                                    <span>Cancel</span>
                                </button>
                                <button @click="generateGkn()" :disabled="loading || !nextGkn || nextGkn.tracking_id === 'Not Found' || nextGkn.tracking_id === 'Error' || loadingTrackingId" type="button" class="px-8 py-2.5 bg-blue-600 hover:bg-blue-700 disabled:bg-slate-400 disabled:cursor-not-allowed text-white text-[11px] font-black uppercase tracking-widest rounded-xl transition-all active:scale-95 shadow-lg shadow-blue-100 flex items-center gap-2 min-w-[140px] justify-center">
                                    <template x-if="loading">
                                            <i data-lucide="loader-2" class="h-4 w-4 animate-spin"></i>
                                    </template>
                                    <span x-text="loading ? 'GENERATING...' : 'GENERATE'">GENERATE GKN FileNo</span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
