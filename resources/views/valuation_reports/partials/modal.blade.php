<div x-data="valuationWizard" x-show="open" x-cloak @open-valuation-modal.window="openModal($event.detail)"
    class="fixed inset-0 z-[100] overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
    @php
        $landUses = \App\Models\LandUse::with(['purposes', 'prefixes'])->orderBy('landuse')->get();
        $purposes = \App\Models\Purpose::orderBy('name')->get();
        $districts = DB::connection('sqlsrv')->table('districts')->where('is_active', 1)->orderBy('name')->get();
        $lgas = DB::connection('sqlsrv')->table('StatLGAs')
            ->join('States', 'StatLGAs.StateID', '=', 'States.StateID')
            ->where('States.StateName', 'Kano')
            ->orderBy('LGAName')
            ->get();
        $streetNames = \App\Models\StreetName::orderBy('name')->get();
    @endphp

    <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:p-0">
        <!-- Overlay -->
        <div x-show="open" x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100" x-transition:leave="ease-in duration-200"
            x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0" @click="closeModal()"
            class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm transition-opacity" aria-hidden="true"></div>

        <!-- Modal Content -->
        <div x-show="open" x-transition:enter="ease-out duration-300"
            x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
            x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100" x-transition:leave="ease-in duration-200"
            x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
            x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
            class="relative flex flex-col w-full max-h-[90vh] bg-white rounded-3xl text-left shadow-2xl transform transition-all sm:my-8 sm:max-w-4xl border border-slate-100 overflow-hidden">

            <!-- Sticky Header -->
            <div
                class="bg-gradient-to-r from-slate-50 to-white border-b border-slate-100 px-8 py-5 flex items-center justify-between sticky top-0 z-10 transition-all">
                <div>
                    <h3 class="text-xl font-black text-slate-900 tracking-tight" id="modal-title"
                        x-text="isEdit ? 'Edit Valuation Report' : 'Generate New Valuation Report'"></h3>
                </div>
                <button @click="closeModal()"
                    class="p-2.5 text-slate-400 hover:text-rose-600 hover:bg-rose-50 rounded-xl transition duration-200">
                    <i data-lucide="x" class="h-6 w-6"></i>
                </button>
            </div>

            <!-- Form Body -->
            <div class="px-8 py-8 flex-1 min-h-0 overflow-y-auto custom-scrollbar">
                {{-- Keep the file workflow at the top of the modal card, ahead of
                     the valuation wizard. It is populated when a file is selected
                     and remains visible throughout the report workflow. --}}
                <div id="valuation-pipeline-strip"
                     class="mb-5 p-5 rounded-2xl border border-slate-200 bg-white shadow-sm"
                     style="display:none"></div>

                {{-- Keep the step context and progress directly beneath the
                     workflow, before any valuation form fields. --}}
                <div class="flex items-center gap-2 mb-4">
                    <span
                        class="px-2 py-0.5 bg-blue-100 text-blue-700 text-[10px] font-black rounded-lg uppercase tracking-wider">Step
                        <span x-text="step"></span> of 5</span>
                    <span class="text-xs font-bold text-slate-500" x-text="stepTitles[step-1]"></span>
                </div>

                <div class="mb-8">
                    <div class="flex items-center gap-2">
                        <template x-for="i in 5">
                            <div class="relative flex-1 group">
                                <div class="h-2 rounded-full transition-all duration-700 shadow-sm"
                                    :class="step >= i ? 'bg-blue-600' : 'bg-slate-100'"></div>
                                <!-- Active Step Glow -->
                                <div x-show="step === i"
                                    class="absolute inset-0 bg-blue-400 blur-md rounded-full opacity-40 animate-pulse">
                                </div>
                            </div>
                        </template>
                    </div>
                </div>

                <form id="report-modal-form" @submit.prevent="submitForm">

                    <!-- STEP 1: Section A - Owner & Property Details -->
                    <div x-show="step === 1" x-transition:enter="transition ease-out duration-200"
                        x-transition:enter-start="opacity-0 translate-x-4"
                        x-transition:enter-end="opacity-100 translate-x-0">

                        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 items-start">

                            <!-- 1. Owner & Inspection Context -->
                            <div class="space-y-6">
                                <div class="bg-slate-50/50 border border-slate-100 p-5 rounded-2xl space-y-4 shadow-sm">
                                    <div class="flex items-center gap-2 mb-2">
                                        <div class="p-1.5 bg-blue-100 rounded-lg text-blue-600">
                                            <i data-lucide="info" class="h-4 w-4"></i>
                                        </div>
                                        <h4 class="text-xs font-black text-slate-700 uppercase tracking-widest">1 & 2. Inspection Context</h4>
                                    </div>
                                    <div class="grid grid-cols-2 gap-4">
                                        <div class="space-y-1">
                                            <label class="text-[10px] font-bold uppercase text-slate-500">1. Inspection Date</label>
                                            <input type="date" x-model="formData.inspection_date" required class="w-full px-4 py-1.5 bg-white border border-slate-200 rounded-xl focus:border-blue-500 transition outline-none font-bold text-black uppercase text-xs">
                                        </div>
                                        <div class="space-y-1">
                                            <label class="text-[10px] font-bold uppercase text-slate-500">2. Purpose of Inspection</label>
                                            <input type="text" x-model="formData.valuation_purpose" placeholder="Purpose of Inspection" class="w-full px-4 py-1.5 bg-white border border-slate-200 rounded-xl focus:border-blue-500 transition outline-none font-bold text-black text-xs uppercase">
                                        </div>
                                        <div class="space-y-1">
                                            <label class="text-[10px] font-bold uppercase text-slate-500">Valuation Type (Registration)</label>
                                            <select x-model="formData.valuation_type" class="w-full px-4 py-1.5 bg-white border border-slate-200 rounded-xl focus:border-blue-500 transition outline-none font-bold text-black text-xs uppercase">
                                                <option value="">Select Type</option>
                                                <option value="Assignment">Assignment</option>
                                                <option value="Mortgage">Mortgage</option>
                                                <option value="Gift">Gift</option>
                                                <option value="Sub-division">Sub-division Valuation</option>
                                            </select>
                                        </div>
                                        <div class="col-span-2 space-y-1">
                                            <label class="text-[10px] font-bold uppercase text-slate-500">File Number</label>
                                            <div class="flex gap-2">
                                                {{-- id is what the Deeds workflow watcher reads the
                                                     selected file from; Alpine's x-model writes the
                                                     value without firing an input event. --}}
                                                <input type="text" id="valuation_file_number" x-model="formData.file_number" readonly placeholder="NO FILE" class="flex-1 px-3 py-1.5 bg-slate-100 border border-slate-200 rounded-xl text-slate-600 font-mono font-bold outline-none text-xs">
                                                <button type="button" @click="selectFile()" class="px-3 py-1.5 bg-blue-600 text-white font-bold rounded-xl hover:bg-blue-700 transition shadow-sm text-xs uppercase">
                                                    Select
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="bg-slate-50/50 border border-slate-100 p-5 rounded-2xl space-y-4 shadow-sm">
                                    <div class="flex items-center gap-2 mb-2">
                                        <div class="p-1.5 bg-emerald-100 rounded-lg text-emerald-600">
                                            <i data-lucide="user" class="h-4 w-4"></i>
                                        </div>
                                        <h4 class="text-xs font-black text-slate-700 uppercase tracking-widest">3. Owner Details</h4>
                                    </div>
                                    <div class="space-y-3">
                                        <div class="space-y-1">
                                            <label class="text-[10px] font-bold uppercase text-slate-500">a) Name</label>
                                            <input type="text" x-model="formData.full_name" class="w-full px-4 py-1.5 bg-white border border-slate-200 rounded-xl focus:border-blue-500 transition outline-none font-bold uppercase text-black">
                                        </div>
                                        <!-- Owner Address Components -->
                                        <div class="p-4 bg-white/50 border border-slate-200 rounded-2xl space-y-3">
                                            <label class="text-[10px] font-bold uppercase text-slate-500 block underline decoration-slate-200 underline-offset-4">b) Address</label>
                                            <div class="grid grid-cols-2 gap-3">
                                                <div class="space-y-1">
                                                    <label class="text-[9px] font-bold text-slate-400 uppercase tracking-tighter"> House No. (c)</label>
                                                    <input type="text" x-model="formData.addr_house_no" @input="concatAddress()" placeholder="House No." class="w-full px-3 py-1.5 bg-white border border-slate-200 rounded-xl outline-none font-semibold text-xs text-black">
                                                </div>
                                                <div class="space-y-1">
                                                    <label class="text-[9px] font-bold text-slate-400 uppercase tracking-tighter"> Plot No.</label>
                                                    <input type="text" x-model="formData.plot_no" placeholder="Plot No." class="w-full px-3 py-1.5 bg-white border border-slate-200 rounded-xl outline-none font-semibold text-xs text-black uppercase">
                                                </div>
                                                <div class="space-y-1">
                                                    <label class="text-[9px] font-bold text-slate-400 uppercase tracking-tighter"> Street Name (d)</label>
                                                    <input type="text" x-model="formData.addr_street" @input="concatAddress()" placeholder="Street Name" class="w-full px-3 py-1.5 bg-white border border-slate-200 rounded-xl outline-none font-semibold text-xs text-black uppercase">
                                                </div>
                                                <div class="space-y-1">
                                                    <label class="text-[9px] font-bold text-slate-400 uppercase tracking-tighter"> Quarters/Estate (e)</label>
                                                    <input type="text" x-model="formData.estate_quarters" placeholder="Estate/Quarters" class="w-full px-3 py-1.5 bg-white border border-slate-200 rounded-xl outline-none font-semibold text-xs text-black uppercase">
                                                </div>
                                                <div class="space-y-1">
                                                    <label class="text-[9px] font-bold text-slate-400 uppercase tracking-tighter"> Neighborhood (f)</label>
                                                    <input type="text" x-model="formData.neighborhood_characteristics" placeholder="Neighborhood" class="w-full px-3 py-1.5 bg-white border border-slate-200 rounded-xl outline-none font-semibold text-xs text-black uppercase">
                                                </div>
                                                <div class="space-y-1">
                                                    <label class="text-[9px] font-bold text-slate-400 uppercase tracking-tighter"> Town/City (g)</label>
                                                    <select x-model="formData.addr_district" @change="concatAddress()" class="w-full px-3 py-1.5 bg-white border border-slate-200 rounded-xl outline-none font-semibold text-xs text-black">
                                                        <option value="">District/Town</option>
                                                        @foreach($districts as $district)
                                                            <option value="{{ $district->name }}">{{ $district->name }}</option>
                                                        @endforeach
                                                        <option value="Other">Other (Specify)</option>
                                                    </select>
                                                    <!-- Conditional Other Input -->
                                                    <div x-show="formData.addr_district === 'Other'" class="mt-1.5 transition-all">
                                                        <input type="text" x-model="formData.custom_district" @input="concatAddress()" placeholder="Specify Town/City" 
                                                            class="w-full px-3 py-1.5 bg-slate-50 border border-blue-200 rounded-xl outline-none font-semibold text-xs text-blue-700 placeholder:text-blue-300">
                                                    </div>
                                                </div>
                                                <div class="space-y-1">
                                                    <label class="text-[9px] font-bold text-slate-400 uppercase tracking-tighter"> L.G.A (h)</label>
                                                    <select x-model="formData.addr_lga" @change="concatAddress()" class="w-full px-3 py-1.5 bg-white border border-slate-200 rounded-xl outline-none font-semibold text-xs text-black">
                                                        <option value="">LGA</option>
                                                        @foreach($lgas as $lga)
                                                            <option value="{{ $lga->LGAName }}">{{ $lga->LGAName }}</option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                                <div class="col-span-2 space-y-1">
                                                    <label class="text-[9px] font-bold text-slate-400 uppercase tracking-tighter"> Full Address Preview</label>
                                                    <div class="text-[10px] text-slate-700 font-bold bg-slate-50 px-3 py-2 rounded-xl border border-slate-100 italic" x-text="formData.address || 'Address Preview...'"></div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="space-y-1">
                                            <label class="text-[10px] font-bold uppercase text-slate-500">i) Phone Number <span class="text-red-500">*</span></label>
                                            <input type="tel" x-model="formData.phone" required inputmode="tel" maxlength="30"
                                                placeholder="e.g. 08031234567"
                                                class="w-full px-4 py-1.5 bg-white border border-slate-200 rounded-xl focus:border-blue-500 transition outline-none font-bold text-black">
                                            <p class="text-[9px] text-slate-400 font-semibold">Carried forward to Consent and Registration when this file is selected there.</p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="space-y-6">

                                <div class="bg-slate-50/50 border border-slate-100 p-5 rounded-2xl space-y-4 shadow-sm">
                                    <div class="flex items-center gap-2 mb-2">
                                        <div class="p-1.5 bg-purple-100 rounded-lg text-purple-600">
                                            <i data-lucide="home" class="h-4 w-4"></i>
                                        </div>
                                        <h4 class="text-xs font-black text-slate-700 uppercase tracking-widest">4. Property Details</h4>
                                    </div>
                                    <div class="grid grid-cols-2 gap-4">
                                        <div class="col-span-2 space-y-1">
                                            <label class="text-[10px] font-bold uppercase text-slate-500">Property</label>
                                            <input type="text" x-model="formData.property_desc" placeholder="Property Description" class="w-full px-4 py-1.5 bg-white border border-slate-200 rounded-xl outline-none font-bold text-black text-xs uppercase">
                                        </div>
                                        <div class="space-y-1">
                                            <label class="text-[10px] font-bold uppercase text-slate-500">a) Property Type</label>
                                            <select x-model="formData.property_type" 
                                                @change="formData.current_use = $event.target.value; formData.land_use_purpose = ''"
                                                class="w-full px-4 py-1.5 bg-white border border-slate-200 rounded-xl outline-none font-bold text-black text-xs uppercase">
                                                <option value="">Select Property Type</option>
                                                @foreach($landUses as $use)
                                                    <option value="{{ $use->landuse }}">{{ $use->landuse }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="space-y-1">
                                            <label class="text-[10px] font-bold uppercase text-slate-500">Land Use Purposes</label>
                                            <select x-model="formData.land_use_purpose" class="w-full px-4 py-1.5 bg-white border border-slate-200 rounded-xl outline-none font-bold text-black text-xs uppercase">
                                                <option value="">Select Purpose</option>
                                                <template x-for="p in filteredPurposes" :key="p.id">
                                                    <option :value="p.name" x-text="p.name"></option>
                                                </template>
                                            </select>
                                        </div>
                                        <div class="space-y-1">
                                            <label class="text-[10px] font-bold uppercase text-slate-500">b) Number of Block</label>
                                            <input type="text" x-model="formData.num_blocks" class="w-full px-4 py-1.5 bg-white border border-slate-200 rounded-xl outline-none font-bold text-black text-xs">
                                        </div>
                                        <div class="space-y-1">
                                            <label class="text-[10px] font-bold uppercase text-slate-500">c) Storey Height</label>
                                            <input type="text" x-model="formData.storey_height" class="w-full px-4 py-1.5 bg-white border border-slate-200 rounded-xl outline-none font-bold text-black text-xs">
                                        </div>
                                        <div class="space-y-1">
                                            <label class="text-[10px] font-bold uppercase text-slate-500">d) Number per Block</label>
                                            <input type="text" x-model="formData.units_per_block" class="w-full px-4 py-1.5 bg-white border border-slate-200 rounded-xl outline-none font-bold text-black text-xs">
                                        </div>
                                        <div class="space-y-1">
                                            <label class="text-[10px] font-bold uppercase text-slate-500">e) Number of Bedroom Units</label>
                                            <input type="text" x-model="formData.bedroom_units" class="w-full px-4 py-1.5 bg-white border border-slate-200 rounded-xl outline-none font-bold text-black text-xs">
                                        </div>
                                        <div class="col-span-2 space-y-1">
                                            <label class="text-[10px] font-bold uppercase text-slate-500">f) Ancillary Accommodation</label>
                                            <input type="text" x-model="formData.ancillary_bldgs" placeholder="e.g. BQ, Gatehouse" class="w-full px-4 py-1.5 bg-white border border-slate-200 rounded-xl outline-none font-bold text-black text-xs">
                                        </div>
                                        <div class="col-span-2 space-y-1">
                                            <label class="text-[10px] font-bold uppercase text-slate-500">g) Use</label>
                                            <input type="text" x-model="formData.use" placeholder="Enter property use..." class="w-full px-4 py-1.5 bg-white border border-slate-200 rounded-xl outline-none font-bold text-black text-xs uppercase">
                                        </div>
                                    </div>
                                </div>
                            </div>

                        </div>
                    </div>

                    <!-- STEP 2: Section B - Structural Details -->
                    <div x-show="step === 2" x-transition:enter="transition ease-out duration-200"
                        x-transition:enter-start="opacity-0 translate-x-4"
                        x-transition:enter-end="opacity-100 translate-x-0">
                        <div class="space-y-6">
                            
                            <!-- 5. Use & Foundation -->
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div class="bg-slate-50/50 border border-slate-100 p-5 rounded-2xl space-y-4 shadow-sm">
                                    <div class="flex items-center gap-2 mb-2">
                                        <div class="p-1.5 bg-blue-100 rounded-lg text-blue-600">
                                            <i data-lucide="activity" class="h-4 w-4"></i>
                                        </div>
                                        <h4 class="text-xs font-black text-slate-700 uppercase tracking-widest">Use & Foundation</h4>
                                    </div>
                                    <div class="space-y-3">
                                        <div class="space-y-1 text-[10px] font-bold text-slate-400 border-b border-slate-100 pb-1">6. Foundation</div>
                                        <div class="grid grid-cols-2 gap-2">
                                            <div class="space-y-1">
                                                <label class="text-[10px] font-bold uppercase text-slate-500">a) Type</label>
                                                <input type="text" x-model="formData.foundation_type" placeholder="e.g. Strip" class="w-full px-4 py-1.5 bg-white border border-slate-200 rounded-xl outline-none font-bold text-black text-xs uppercase">
                                            </div>
                                            <div class="space-y-1">
                                                <label class="text-[10px] font-bold uppercase text-slate-500">b) Stage</label>
                                                <input type="text" x-model="formData.foundation_stage" placeholder="e.g. DPC" class="w-full px-4 py-1.5 bg-white border border-slate-200 rounded-xl outline-none font-bold text-black text-xs uppercase">
                                            </div>
                                        </div>
                                        
                                        <div class="space-y-1 text-[10px] font-bold text-slate-400 border-b border-slate-100 pb-1 mt-2">7. Floor</div>
                                        <div class="grid grid-cols-2 gap-2">
                                            <div class="space-y-1">
                                                <label class="text-[10px] font-bold uppercase text-slate-500">a) Type</label>
                                                <input type="text" x-model="formData.floor_type" class="w-full px-4 py-1.5 bg-white border border-slate-200 rounded-xl outline-none font-bold text-black text-xs uppercase">
                                            </div>
                                            <div class="space-y-1">
                                                <label class="text-[10px] font-bold uppercase text-slate-500">b) Finishing</label>
                                                <input type="text" x-model="formData.floor_finish" class="w-full px-4 py-1.5 bg-white border border-slate-200 rounded-xl outline-none font-bold text-black text-xs uppercase">
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Walls -->
                                <div class="bg-slate-50/50 border border-slate-100 p-5 rounded-2xl space-y-4 shadow-sm">
                                    <div class="flex items-center gap-2 mb-2">
                                        <div class="p-1.5 bg-amber-100 rounded-lg text-amber-600">
                                            <i data-lucide="columns" class="h-4 w-4"></i>
                                        </div>
                                        <h4 class="text-xs font-black text-slate-700 uppercase tracking-widest">Walls</h4>
                                    </div>
                                    <div class="space-y-3">
                                        <div class="space-y-1 text-[10px] font-bold text-slate-400 border-b border-slate-100 pb-1">8. Main Walls</div>
                                        <div class="grid grid-cols-3 gap-2">
                                            <div class="space-y-1">
                                                <label class="text-[10px] font-bold uppercase text-slate-500">a) Type</label>
                                                <input type="text" x-model="formData.walls_type" class="w-full px-4 py-1.5 bg-white border border-slate-200 rounded-xl outline-none font-bold text-black text-xs uppercase">
                                            </div>
                                            <div class="space-y-1">
                                                <label class="text-[10px] font-bold uppercase text-slate-500">b) Stage</label>
                                                <input type="text" x-model="formData.walls_stage" class="w-full px-4 py-1.5 bg-white border border-slate-200 rounded-xl outline-none font-bold text-black text-xs uppercase">
                                            </div>
                                            <div class="space-y-1">
                                                <label class="text-[10px] font-bold uppercase text-slate-500">c) Finishing</label>
                                                <input type="text" x-model="formData.walls_finish" class="w-full px-4 py-1.5 bg-white border border-slate-200 rounded-xl outline-none font-bold text-black text-xs uppercase">
                                            </div>
                                        </div>
                                        <div class="space-y-1 text-[10px] font-bold text-slate-400 border-b border-slate-100 pb-1 mt-2">9. Boundary Walls</div>
                                        <div class="grid grid-cols-3 gap-2">
                                            <div class="space-y-1">
                                                <label class="text-[10px] font-bold uppercase text-slate-500">a) Type</label>
                                                <input type="text" x-model="formData.boundary_wall_type" class="w-full px-4 py-1.5 bg-white border border-slate-200 rounded-xl outline-none font-bold text-black text-xs uppercase">
                                            </div>
                                            <div class="space-y-1">
                                                <label class="text-[10px] font-bold uppercase text-slate-500">b) Height</label>
                                                <input type="text" x-model="formData.boundary_wall_height" class="w-full px-4 py-1.5 bg-white border border-slate-200 rounded-xl outline-none font-bold text-black text-xs uppercase">
                                            </div>
                                            <div class="space-y-1">
                                                <label class="text-[10px] font-bold uppercase text-slate-500">c) Finishing</label>
                                                <input type="text" x-model="formData.boundary_wall_finish" class="w-full px-4 py-1.5 bg-white border border-slate-200 rounded-xl outline-none font-bold text-black text-xs uppercase">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- 10. Roof & Doors/Windows -->
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div class="bg-slate-50/50 border border-slate-100 p-5 rounded-2xl space-y-4 shadow-sm">
                                    <div class="flex items-center gap-2 mb-2">
                                        <div class="p-1.5 bg-purple-100 rounded-lg text-purple-600">
                                            <i data-lucide="umbrella" class="h-4 w-4"></i>
                                        </div>
                                        <h4 class="text-xs font-black text-slate-700 uppercase tracking-widest">Roof & Ceiling</h4>
                                    </div>
                                    <div class="grid grid-cols-2 gap-4">
                                        <div class="col-span-2 space-y-1 text-[10px] font-bold text-slate-400 border-b border-slate-100 pb-1">10. Roof</div>
                                        <div class="space-y-1">
                                            <label class="text-[10px] font-bold uppercase text-slate-500">a) Type</label>
                                            <input type="text" x-model="formData.roof_type" class="w-full px-4 py-1.5 bg-white border border-slate-200 rounded-xl outline-none font-bold text-black text-xs uppercase">
                                        </div>
                                        <div class="space-y-1">
                                            <label class="text-[10px] font-bold uppercase text-slate-500">b) Materials</label>
                                            <input type="text" x-model="formData.roof_material" class="w-full px-4 py-1.5 bg-white border border-slate-200 rounded-xl outline-none font-bold text-black text-xs uppercase">
                                        </div>
                                        <div class="col-span-2 space-y-1 pt-2">
                                            <div class="space-y-1 text-[10px] font-bold text-slate-400 border-b border-slate-100 pb-1">11. Ceiling</div>
                                            <input type="text" x-model="formData.ceiling_detail" placeholder="Enter ceiling details..." class="w-full px-4 py-1.5 bg-white border border-slate-200 rounded-xl outline-none font-bold text-black text-xs uppercase">
                                        </div>
                                    </div>
                                </div>

                                <div class="bg-slate-50/50 border border-slate-100 p-5 rounded-2xl space-y-4 shadow-sm">
                                    <div class="flex items-center gap-2 mb-2">
                                        <div class="p-1.5 bg-rose-100 rounded-lg text-rose-600">
                                            <i data-lucide="door-open" class="h-4 w-4"></i>
                                        </div>
                                        <h4 class="text-xs font-black text-slate-700 uppercase tracking-widest">Doors & Windows</h4>
                                    </div>
                                    <div class="grid grid-cols-2 gap-4">
                                        <div class="col-span-2 space-y-1 text-[10px] font-bold text-slate-400 border-b border-slate-100 pb-1">12. Doors</div>
                                        <div class="space-y-1">
                                            <label class="text-[10px] font-bold uppercase text-slate-500">a) Gate</label>
                                            <input type="text" x-model="formData.gate_type" class="w-full px-4 py-1.5 bg-white border border-slate-200 rounded-xl outline-none font-bold text-black text-xs uppercase">
                                        </div>
                                        <div class="space-y-1">
                                            <label class="text-[10px] font-bold uppercase text-slate-500">b) External</label>
                                            <input type="text" x-model="formData.ext_doors" class="w-full px-4 py-1.5 bg-white border border-slate-200 rounded-xl outline-none font-bold text-black text-xs uppercase">
                                        </div>
                                        <div class="space-y-1">
                                            <label class="text-[10px] font-bold uppercase text-slate-500">c) Internal</label>
                                            <input type="text" x-model="formData.int_doors" class="w-full px-4 py-1.5 bg-white border border-slate-200 rounded-xl outline-none font-bold text-black text-xs uppercase">
                                        </div>
                                        <div class="space-y-1">
                                            <label class="text-[10px] font-bold uppercase text-slate-500">d) Proof</label>
                                            <input type="text" x-model="formData.door_security_proof" class="w-full px-4 py-1.5 bg-white border border-slate-200 rounded-xl outline-none font-bold text-black text-xs uppercase">
                                        </div>
                                        
                                        <div class="col-span-2 space-y-1 text-[10px] font-bold text-slate-400 border-b border-slate-100 pb-1 mt-2">13. Windows</div>
                                        <div class="space-y-1">
                                            <label class="text-[10px] font-bold uppercase text-slate-500">a) Type</label>
                                            <input type="text" x-model="formData.window_type" class="w-full px-4 py-1.5 bg-white border border-slate-200 rounded-xl outline-none font-bold text-black text-xs uppercase">
                                        </div>
                                        <div class="space-y-1">
                                            <label class="text-[10px] font-bold uppercase text-slate-500">b) Proof</label>
                                            <input type="text" x-model="formData.burglary_proof" class="w-full px-4 py-1.5 bg-white border border-slate-200 rounded-xl outline-none font-bold text-black text-xs uppercase">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- STEP 3: Section C - Services & Utilities -->
                    <div x-show="step === 3" x-transition:enter="transition ease-out duration-200"
                        x-transition:enter-start="opacity-0 translate-x-4"
                        x-transition:enter-end="opacity-100 translate-x-0">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 items-start">
                            <!-- Card 1: Toilet -->
                            <div class="bg-slate-50/50 border border-slate-100 p-5 rounded-2xl space-y-4 shadow-sm h-full">
                                <div class="flex items-center gap-2 mb-2">
                                    <div class="p-1.5 bg-cyan-100 rounded-lg text-cyan-600">
                                        <i data-lucide="bath" class="h-4 w-4"></i>
                                    </div>
                                    <h4 class="text-xs font-black text-slate-700 uppercase tracking-widest">14. Toilet Facility</h4>
                                </div>
                                <div class="space-y-1">
                                    <label class="text-[10px] font-bold uppercase text-slate-500">Description</label>
                                    <input type="text" x-model="formData.toilet_bath" class="w-full px-4 py-1.5 bg-white border border-slate-200 rounded-xl outline-none font-bold text-black text-xs uppercase" placeholder="e.g. Modern Water Closet">
                                </div>
                            </div>

                            <!-- Card 2: Water Supply -->
                            <div class="bg-slate-50/50 border border-slate-100 p-5 rounded-2xl space-y-4 shadow-sm h-full">
                                <div class="flex items-center gap-2 mb-2">
                                    <div class="p-1.5 bg-blue-100 rounded-lg text-blue-600">
                                        <i data-lucide="droplet" class="h-4 w-4"></i>
                                    </div>
                                    <h4 class="text-xs font-black text-slate-700 uppercase tracking-widest">15. Pipe Borne Water</h4>
                                </div>
                                <div class="flex flex-col gap-2 bg-white border border-slate-200 rounded-xl p-3">
                                    <label class="flex items-center gap-2 cursor-pointer">
                                        <input type="radio" name="water" value="Available" x-model="formData.water_supply" class="text-blue-600 focus:ring-blue-500 h-4 w-4">
                                        <span class="text-xs font-bold text-slate-700">a) Available</span>
                                    </label>
                                    <label class="flex items-center gap-2 cursor-pointer">
                                        <input type="radio" name="water" value="Not Available" x-model="formData.water_supply" class="text-blue-600 focus:ring-blue-500 h-4 w-4">
                                        <span class="text-xs font-bold text-slate-700">b) Not Available</span>
                                    </label>
                                    <div class="flex items-center gap-2">
                                        <input type="radio" name="water" value="Supplement" x-model="formData.water_supply" class="text-blue-600 focus:ring-blue-500 h-4 w-4">
                                        <span class="text-xs font-bold text-slate-700 whitespace-nowrap">c) Supplement:</span>
                                        <input type="text" x-model="formData.water_supply" @focus="formData.water_supply = (formData.water_supply === 'Available' || formData.water_supply === 'Not Available') ? '' : formData.water_supply" placeholder="Specify..." class="flex-1 px-2 py-0.5 text-xs border-b border-slate-200 outline-none bg-transparent">
                                    </div>
                                </div>
                            </div>

                            <!-- Card 3: Electricity -->
                            <div class="bg-slate-50/50 border border-slate-100 p-5 rounded-2xl space-y-4 shadow-sm h-full">
                                <div class="flex items-center gap-2 mb-2">
                                    <div class="p-1.5 bg-yellow-100 rounded-lg text-yellow-600">
                                        <i data-lucide="zap" class="h-4 w-4"></i>
                                    </div>
                                    <h4 class="text-xs font-black text-slate-700 uppercase tracking-widest">16. Electricity</h4>
                                </div>
                                <div class="flex flex-col gap-2 bg-white border border-slate-200 rounded-xl p-3">
                                    <label class="flex items-center gap-2 cursor-pointer">
                                        <input type="radio" name="electricity" value="Available" x-model="formData.electricity" class="text-blue-600 focus:ring-blue-500 h-4 w-4">
                                        <span class="text-xs font-bold text-slate-700">a) Available</span>
                                    </label>
                                    <label class="flex items-center gap-2 cursor-pointer">
                                        <input type="radio" name="electricity" value="Not Available" x-model="formData.electricity" class="text-blue-600 focus:ring-blue-500 h-4 w-4">
                                        <span class="text-xs font-bold text-slate-700">b) Not Available</span>
                                    </label>
                                    <div class="flex items-center gap-2">
                                        <input type="radio" name="electricity" value="Supplement" x-model="formData.electricity" class="text-blue-600 focus:ring-blue-500 h-4 w-4">
                                        <span class="text-xs font-bold text-slate-700 whitespace-nowrap">c) Supplement:</span>
                                        <input type="text" x-model="formData.electricity" @focus="formData.electricity = (formData.electricity === 'Available' || formData.electricity === 'Not Available') ? '' : formData.electricity" placeholder="Specify..." class="flex-1 px-2 py-0.5 text-xs border-b border-slate-200 outline-none bg-transparent">
                                    </div>
                                </div>
                            </div>

                            <!-- Card 4: Drainage -->
                            <div class="bg-slate-50/50 border border-slate-100 p-5 rounded-2xl space-y-4 shadow-sm h-full">
                                <div class="flex items-center gap-2 mb-2">
                                    <div class="p-1.5 bg-emerald-100 rounded-lg text-emerald-600">
                                        <i data-lucide="waves" class="h-4 w-4"></i>
                                    </div>
                                    <h4 class="text-xs font-black text-slate-700 uppercase tracking-widest">17. Drainage</h4>
                                </div>
                                <div class="space-y-1">
                                    <label class="text-[10px] font-bold uppercase text-slate-500">Description</label>
                                    <input type="text" x-model="formData.drainage" class="w-full px-4 py-1.5 bg-white border border-slate-200 rounded-xl outline-none font-bold text-black text-xs uppercase" placeholder="e.g. Concrete Channels">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- STEP 4: Section D - Fittings & Fixtures -->
                    <div x-show="step === 4" x-transition:enter="transition ease-out duration-200"
                        x-transition:enter-start="opacity-0 translate-x-4"
                        x-transition:enter-end="opacity-100 translate-x-0">
                            <div class="space-y-6">
                                <div class="bg-slate-50/50 border border-slate-100 p-5 rounded-2xl space-y-4 shadow-sm">
                                    <div class="flex items-center gap-2 mb-2">
                                        <div class="p-1.5 bg-indigo-100 rounded-lg text-indigo-600">
                                            <i data-lucide="lamp" class="h-4 w-4"></i>
                                        </div>
                                        <h4 class="text-xs font-black text-slate-700 uppercase tracking-widest">18. Fitting & Fixture</h4>
                                    </div>
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-4">
                                        <div class="space-y-1">
                                            <label class="text-[10px] font-bold uppercase text-slate-500">a) Sitting Room</label>
                                            <input type="text" x-model="formData.fittings_sitting" class="w-full px-4 py-1.5 bg-white border border-slate-200 rounded-xl outline-none font-bold text-black text-xs uppercase">
                                        </div>
                                        <div class="space-y-1">
                                            <label class="text-[10px] font-bold uppercase text-slate-500">b) Dining</label>
                                            <input type="text" x-model="formData.fittings_dining" class="w-full px-4 py-1.5 bg-white border border-slate-200 rounded-xl outline-none font-bold text-black text-xs uppercase">
                                        </div>
                                        <div class="space-y-1">
                                            <label class="text-[10px] font-bold uppercase text-slate-500">c) Bed</label>
                                            <input type="text" x-model="formData.fittings_bedroom" class="w-full px-4 py-1.5 bg-white border border-slate-200 rounded-xl outline-none font-bold text-black text-xs uppercase">
                                        </div>
                                        <div class="space-y-1">
                                            <label class="text-[10px] font-bold uppercase text-slate-500">d) Toilet</label>
                                            <input type="text" x-model="formData.fittings_toilet" class="w-full px-4 py-1.5 bg-white border border-slate-200 rounded-xl outline-none font-bold text-black text-xs uppercase">
                                        </div>
                                        <div class="space-y-1">
                                            <label class="text-[10px] font-bold uppercase text-slate-500">e) Bath</label>
                                            <input type="text" x-model="formData.fittings_bath" class="w-full px-4 py-1.5 bg-white border border-slate-200 rounded-xl outline-none font-bold text-black text-xs uppercase">
                                        </div>
                                        <div class="space-y-1">
                                            <label class="text-[10px] font-bold uppercase text-slate-500">f) Kitchen</label>
                                            <input type="text" x-model="formData.fittings_kitchen" class="w-full px-4 py-1.5 bg-white border border-slate-200 rounded-xl outline-none font-bold text-black text-xs uppercase">
                                        </div>
                                        <div class="space-y-1">
                                            <label class="text-[10px] font-bold uppercase text-slate-500">g) Store</label>
                                            <input type="text" x-model="formData.fittings_store" class="w-full px-4 py-1.5 bg-white border border-slate-200 rounded-xl outline-none font-bold text-black text-xs uppercase">
                                        </div>
                                        <div class="space-y-1">
                                            <label class="text-[10px] font-bold uppercase text-slate-500">h) B/Q</label>
                                            <input type="text" x-model="formData.fittings_bq" class="w-full px-4 py-1.5 bg-white border border-slate-200 rounded-xl outline-none font-bold text-black text-xs uppercase">
                                        </div>
                                    </div>
                                </div>

                                <div class="bg-slate-50/50 border border-slate-100 p-5 rounded-2xl space-y-4 shadow-sm">
                                    <div class="flex items-center gap-2 mb-2">
                                        <div class="p-1.5 bg-orange-100 rounded-lg text-orange-600">
                                            <i data-lucide="info" class="h-4 w-4"></i>
                                        </div>
                                        <h4 class="text-xs font-black text-slate-700 uppercase tracking-widest">19. Other Related Information</h4>
                                    </div>
                                    <div class="space-y-1">
                                        <textarea x-model="formData.other_info" rows="3" placeholder="Enter any other relevant information regarding the property or valuation..." class="w-full px-4 py-2 bg-white border border-slate-200 rounded-xl outline-none font-medium text-black text-xs uppercase"></textarea>
                                    </div>
                                </div>
                            </div>
                    </div>

                    <!-- STEP 5: Section E - Valuation & Recommendation -->
                    <div x-show="step === 5" x-transition:enter="transition ease-out duration-200"
                        x-transition:enter-start="opacity-0 translate-x-4"
                        x-transition:enter-end="opacity-100 translate-x-0">
                        <div class="space-y-6">
                            
                            <!-- Valuation Details -->
                            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 items-start">
                                <!-- Valuation Figures -->
                                <div class="bg-blue-50/50 border border-blue-100 p-5 rounded-2xl space-y-4 shadow-sm">
                                    <div class="flex items-center gap-2 mb-2">
                                        <div class="p-1.5 bg-blue-600 rounded-lg text-white shadow-sm">
                                            <i data-lucide="calculator" class="h-4 w-4"></i>
                                        </div>
                                        <h4 class="text-xs font-black text-blue-700 uppercase tracking-widest">Section E - Valuation</h4>
                                    </div>
                                    <div class="space-y-4">
                                        <div class="space-y-1">
                                            <label class="text-[10px] font-bold uppercase text-blue-600/70">20. Basis of Valuation</label>
                                            <input type="text" x-model="formData.valuation_basis" placeholder="e.g. Depreciated Replacement Cost" class="w-full px-4 py-1.5 bg-white border border-blue-100 rounded-xl outline-none font-bold text-black text-xs uppercase focus:border-blue-500">
                                        </div>
                                        <div class="space-y-1">
                                            <label class="text-[10px] font-bold uppercase text-blue-600/70">21. Opinion/Recommendation</label>
                                            <div class="space-y-3 p-3 bg-white border border-blue-100 rounded-xl">
                                           
                                                <div class="space-y-1">
                                                    <label class="text-[9px] font-bold uppercase text-slate-400">a) N;</label>
                                                    <div class="relative">
                                                        <span class="absolute left-3 top-1/2 -translate-y-1/2 font-bold text-blue-700 text-sm">₦</span>
                                                        <input type="text" x-model="formData.value_figures" @input="updateValueInWords()" @blur="formatCurrency()" placeholder="0.00" class="w-full pl-8 pr-4 py-2 bg-blue-50/50 border border-blue-200 rounded-xl outline-none font-black text-xl text-blue-700 tracking-tight focus:ring-4 focus:ring-blue-500/10">
                                                    </div>


                                                         <div class="space-y-1">
                                                    <label class="text-[9px] font-bold uppercase text-slate-400">b) Amount in Words</label>
                                                    <textarea x-model="formData.value_words" rows="2" class="w-full px-3 py-1.5 bg-slate-50 border-none rounded-lg outline-none font-bold text-blue-800 text-xs uppercase" readonly></textarea>
                                                </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Certification & Signing -->
                                <div class="bg-slate-50/50 border border-slate-100 p-5 rounded-2xl space-y-4 shadow-sm">
                                    <div class="flex items-center gap-2 mb-2">
                                        <div class="p-1.5 bg-slate-200 rounded-lg text-slate-600">
                                            <i data-lucide="award" class="h-4 w-4"></i>
                                        </div>
                                        <h4 class="text-xs font-black text-slate-700 uppercase tracking-widest">Certification</h4>
                                    </div>
                                    <div class="space-y-4">
                                        <div class="space-y-1">
                                            <label class="text-[10px] font-bold text-slate-500 uppercase">22. Surveyor</label>
                                            <div class="grid grid-cols-1 gap-2 p-3 bg-white border border-slate-200 rounded-xl">
                                                <div class="space-y-1">
                                                    <label class="text-[9px] font-bold uppercase text-slate-400">a) Name</label>
                                                     <select x-model="formData.surveyor_name" @change="applySurveyorRank()" class="w-full px-3 py-1 bg-slate-50 rounded-lg outline-none font-bold text-black text-xs uppercase appearance-none">
                                                         <option value="">Select Surveyor</option>
                                                         @foreach($valuationOfficers as $officer)
                                                             <option value="{{ $officer->name }}">{{ $officer->name }}</option>
                                                         @endforeach
                                                     </select>
                                                </div>
                                                <div class="space-y-1">
                                                    <label class="text-[9px] font-bold uppercase text-slate-400">b) Rank / Reg No.</label>
                                                    <input type="text" x-model="formData.surveyor_rank" placeholder="e.g. Registered Estate Surveyor" class="w-full px-3 py-1 bg-slate-50 rounded-lg outline-none font-bold text-black text-xs uppercase" readonly
                                                </div>
                                                <div class="space-y-1 pt-2 border-t border-slate-100">
                                                    <label class="text-[9px] font-bold uppercase text-slate-400">c) O/C VALUATION</label>
                                                     <select x-model="formData.chief_valuer" class="w-full px-3 py-1 bg-slate-50 rounded-lg outline-none font-bold text-black text-xs uppercase appearance-none">
                                                         <option value="">Select O/C Valuation</option>
                                                         @foreach($valuationOfficers as $officer)
                                                             <option value="{{ $officer->name }}">{{ $officer->name }}</option>
                                                         @endforeach
                                                     </select>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            </div>

                            <!-- Remarks & Summary -->
                            <div class="bg-slate-50/50 border border-slate-100 p-5 rounded-2xl space-y-4 shadow-sm">
                                <div class="flex items-center gap-2 mb-2">
                                    <div class="p-1.5 bg-gray-100 rounded-lg text-gray-600">
                                        <i data-lucide="file-text" class="h-4 w-4"></i>
                                    </div>
                                    <h4 class="text-xs font-black text-slate-700 uppercase tracking-widest">Opinion/Recommendation</h4>
                                </div>
                                <div class="space-y-1">
                                    <textarea x-model="formData.remarks" rows="3" placeholder="Enter general remarks regarding the property, neighborhood, and recommendation..." class="w-full px-4 py-2 bg-white border border-slate-200 rounded-xl outline-none font-medium text-black text-xs"></textarea>
                                </div>
                            </div>

                            <!-- System Metadata (Display Only) -->
                            <div class="bg-slate-100/50 border border-slate-200 p-5 rounded-2xl space-y-4 shadow-sm">
                                <div class="flex items-center gap-2 mb-2">
                                    <div class="p-1.5 bg-slate-300 rounded-lg text-slate-700">
                                        <i data-lucide="info" class="h-4 w-4"></i>
                                    </div>
                                    <h4 class="text-xs font-black text-slate-700 uppercase tracking-widest">
                                        System Metadata</h4>
                                </div>
                                <div class="grid grid-cols-3 gap-4">
                                    <div class="space-y-1">
                                        <label class="text-[10px] font-bold uppercase text-slate-500">Time
                                            Generated</label>
                                        <div
                                            class="w-full px-4 py-2 bg-slate-200/50 border border-slate-200 rounded-xl text-xs font-bold text-slate-600">
                                            {{ date('h:i A') }}
                                        </div>
                                    </div>
                                    <div class="space-y-1">
                                        <label class="text-[10px] font-bold uppercase text-slate-500">Date
                                            Generated</label>
                                        <div
                                            class="w-full px-4 py-2 bg-slate-200/50 border border-slate-200 rounded-xl text-xs font-bold text-slate-600">
                                            {{ date('d-m-Y') }}
                                        </div>
                                    </div>
                                    <div class="space-y-1">
                                        <label class="text-[10px] font-bold uppercase text-slate-500">Generated
                                            By</label>
                                        <div
                                            class="w-full px-4 py-2 bg-slate-200/50 border border-slate-200 rounded-xl text-xs font-bold text-slate-600">
                                            {{ auth()->user()->first_name }} {{ auth()->user()->last_name }}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                    </div>
                </form>
            </div>

            <!-- Footer Navigation -->
            <div class="bg-slate-50 border-t border-slate-100 px-8 py-5 flex items-center justify-between rounded-b-3xl shrink-0 z-10 relative">
                <div class="flex items-center gap-3">
                    <button x-show="step === 1" @click="closeModal()" type="button"
                        class="px-5 py-2 text-slate-500 font-bold hover:text-slate-800 transition text-sm">Cancel</button>
                    <button type="button" x-show="step > 1" @click="step--"
                        class="flex items-center gap-2 px-5 py-2 bg-white border border-slate-200 text-slate-600 font-bold rounded-xl hover:bg-slate-50 transition shadow-sm text-sm">
                        <i data-lucide="arrow-left" class="h-4 w-4"></i>
                        Previous
                    </button>
                </div>

                <div class="flex items-center gap-3">
                    <button type="button" x-show="step < 5" @click="nextStep()"
                        class="flex items-center gap-2 px-6 py-2 bg-slate-900 text-white font-bold rounded-xl hover:bg-slate-800 transition shadow-xl shadow-slate-200 text-sm">
                        Next Step
                        <i data-lucide="arrow-right" class="h-4 w-4"></i>
                    </button>

                    <button type="button" x-show="step === 5" @click="submitForm()" :disabled="submitting"
                        class="flex items-center gap-2 px-8 py-2 bg-blue-600 text-white font-black rounded-xl hover:bg-blue-700 disabled:bg-blue-300 transition shadow-xl shadow-blue-200 text-sm">
                        <template x-if="!submitting">
                            <span class="flex items-center gap-2">
                                <i data-lucide="check-circle" class="h-5 w-5"></i>
                                <span x-text="isEdit ? 'Update Report' : 'Generate Report'"></span>
                            </span>
                        </template>
                        <template x-if="submitting">
                            <span class="flex items-center gap-2">
                                <i data-lucide="loader-2" class="h-5 w-5 animate-spin"></i>
                                Saving...
                            </span>
                        </template>
                    </button>
                </div>
            </div>

        </div>
    </div>
</div>

<script>
    document.addEventListener('alpine:init', () => {
        Alpine.data('valuationWizard', () => ({
            open: false,
            step: 1,
            isEdit: false,
            submitting: false,
            getToday() {
                const d = new Date();
                return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
            },
            stepTitles: [
                'Section A: Owner & Property Details',
                'Section B: Structural Details',
                'Section C: Services & Utilities',
                'Section D: Fittings & Fixtures',
                'Section E: Valuation & Recommendation'
            ],
            landUsesWithPurposes: @json($landUses),
            districts: @json($districts->pluck('name')),
            valuationOfficers: @json($valuationOfficers),
            get filteredPurposes() {
                if (!this.formData.property_type) return [];
                const selectedUse = this.landUsesWithPurposes.find(u => u.landuse === this.formData.property_type);
                return selectedUse ? selectedUse.purposes : [];
            },
            formData: {
                id: '',
                inspection_date: new Date().toISOString().split('T')[0],
                land_use_purpose: '',
                valuation_purpose: '',
                valuation_type: '',
                file_number: '',
                full_name: '',
                address: '',
                phone: '',
                addr_house_no: '',
                addr_street: '',
                addr_district: '',
                custom_district: '',
                addr_lga: '',
                addr_state: 'Kano',
                
                // Section A
                property_no: '',
                plot_no: '',
                street_name: '',
                estate_quarters: '',
                neighborhood_characteristics: '',
                town_city: '',
                lga: '',
                property_type: '',
                num_blocks: '',
                storey_height: '',
                units_per_block: '',
                bedroom_units: '',
                ancillary_bldgs: '',
                use: '',
                property_desc: '',

                // Section B
                current_use: '',
                foundation_type: '',
                foundation_stage: '',
                floor_type: '',
                floor_finish: '',
                walls_type: '',
                walls_stage: '',
                walls_finish: '',
                boundary_wall_type: '',
                boundary_wall_height: '',
                boundary_wall_finish: '',
                roof_type: '',
                roof_material: '',
                ceiling_detail: '',
                gate_type: '',
                ext_doors: '',
                int_doors: '',
                door_security_proof: '',
                window_type: '',
                burglary_proof: '',

                // Section C
                toilet_bath: '',
                water_supply: '',
                electricity: '',
                drainage: '',

                // Section D
                fittings_sitting: '',
                fittings_dining: '',
                fittings_bedroom: '',
                fittings_toilet: '',
                fittings_bath: '',
                fittings_kitchen: '',
                fittings_store: '',
                fittings_bq: '',
                fittings_summary: '', // Keep for backward compatibility or summary

                // Section E
                remarks: '',
                valuation_basis: 'Market Comparison Method',
                value_words: '',
                value_figures: '',
                surveyor_name: '',
                surveyor_rank: '',
                chief_valuer: ''
            },
            applySurveyorRank() {
                const officer = this.valuationOfficers.find((item) => item.name === this.formData.surveyor_name);
                this.formData.surveyor_rank = officer ? (officer.rank || '') : '';
            },

            openModal(data = null) {
                this.step = 1;
                if (data && data.id) {
                    this.isEdit = true;
                    this.formData = { ...data };
                    // Format date if exists, otherwise default to today
                    if (this.formData.inspection_date) {
                        const d = new Date(this.formData.inspection_date);
                        this.formData.inspection_date = d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
                    } else {
                        this.formData.inspection_date = this.getToday();
                    }

                    // Try to parse address for components if editing
                    this.parseAddress(this.formData.address);

                    // Stored columns win over whatever was parsed out of the address string
                    if (this.formData.property_no) this.formData.addr_house_no = this.formData.property_no;
                    if (this.formData.street_name) this.formData.addr_street = this.formData.street_name;
                    if (this.formData.lga) this.formData.addr_lga = this.formData.lga;
                    if (this.formData.town_city) {
                        this.formData.addr_district = this.districts.includes(this.formData.town_city)
                            ? this.formData.town_city
                            : 'Other';
                        if (this.formData.addr_district === 'Other') {
                            this.formData.custom_district = this.formData.town_city;
                        }
                    }
                } else {
                    this.isEdit = false;
                    this.resetForm();
                    this.formData.inspection_date = this.getToday();
                }
                this.applySurveyorRank();
                this.open = true;
                this.$nextTick(() => { if (window.lucide) window.lucide.createIcons(); });
            },

            parseAddress(fullAddress) {
                if (!fullAddress) return;
                // Basic attempt to split address: "House No Street, District, LGA, State"
                // This is just a fallback for editing old records
                const parts = fullAddress.split(',').map(p => p.trim());
                if (parts.length >= 4) {
                    this.formData.addr_state = parts.pop();
                    this.formData.addr_lga = parts.pop();
                    const districtVal = parts.pop();
                    if (districtVal && !this.districts.includes(districtVal)) {
                        this.formData.addr_district = 'Other';
                        this.formData.custom_district = districtVal;
                    } else {
                        this.formData.addr_district = districtVal;
                    }
                    const streetPart = parts.join(', ');
                    const houseMatch = streetPart.match(/^No\.?\s*([^\s,]+)\s*(.*)/i);
                    if (houseMatch) {
                        this.formData.addr_house_no = houseMatch[1];
                        this.formData.addr_street = houseMatch[2];
                    } else {
                        this.formData.addr_street = streetPart;
                    }
                } else {
                    this.formData.addr_street = fullAddress;
                }
            },

            concatAddress() {
                let addr = '';
                if (this.formData.addr_house_no) addr += `No. ${this.formData.addr_house_no} `;
                addr += this.formData.addr_street;
                
                let district = this.formData.addr_district;
                if (district === 'Other' && this.formData.custom_district) {
                    district = this.formData.custom_district;
                }

                if (district) addr += `, ${district}`;
                if (this.formData.addr_lga) addr += `, ${this.formData.addr_lga}`;
                if (this.formData.addr_state) addr += `, ${this.formData.addr_state}`;
                this.formData.address = addr.trim();

                // Mirror the address components onto the persisted columns the
                // report template prints as c) Plot/Prop No, d) Street Name,
                // g) Town/City and h) L.G.A — without this they save empty.
                this.formData.property_no = this.formData.addr_house_no || '';
                this.formData.street_name = this.formData.addr_street || '';
                this.formData.town_city = district || '';
                this.formData.lga = this.formData.addr_lga || '';
            },

            closeModal() {
                this.open = false;
                this.resetForm();
            },

            resetForm() {
                this.propertyLandUse = '';
                this.formData = {
                    id: '',
                    inspection_date: this.getToday(),
                    land_use_purpose: '',
                    valuation_purpose: '',
                    valuation_type: '',
                    file_number: '',
                    full_name: '',
                    address: '',
                    phone: '',
                    addr_house_no: '',
                    addr_street: '',
                    addr_district: '',
                    custom_district: '',
                    addr_lga: '',
                    addr_state: 'Kano',
                    property_no: '',
                    plot_no: '',
                    street_name: '',
                    estate_quarters: '',
                    town_city: '',
                    lga: '',
                    property_type: '',
                    num_blocks: '',
                    storey_height: '',
                    bedroom_units: '',
                    ancillary_bldgs: '',
                    current_use: '',
                    
                    foundation_type: '',
                    foundation_stage: '',
                    floor_type: '',
                    floor_finish: '',
                    walls_type: '',
                    walls_stage: '',
                    walls_finish: '',
                    
                    boundary_wall_type: '',
                    boundary_wall_height: '',
                    boundary_wall_finish: '',
                    
                    roof_type: '',
                    roof_material: '',
                    ceiling_detail: '',
                    
                    gate_type: '',
                    ext_doors: '',
                    int_doors: '',
                    door_security_proof: '',
                    window_type: '',
                    burglary_proof: '',
                    
                    toilet_bath: '',
                    water_supply: '',
                    electricity: '',
                    drainage: '',
                    
                    fittings_sitting: '',
                    fittings_dining: '',
                    fittings_bedroom: '',
                    fittings_toilet: '',
                    fittings_bath: '',
                    fittings_kitchen: '',
                    fittings_store: '',
                    fittings_bq: '',
                    
                    other_info: '',
                    remarks: '',
                    valuation_basis: '',
                    value_words: '',
                    value_figures: '',
                    surveyor_name: '',
                    surveyor_rank: '',
                    chief_valuer: ''
                };
            },

            selectFile() {
                if (window.GlobalFileNoModal) {
                    window.GlobalFileNoModal.open({
                        callback: (data) => {
                            this.formData.file_number = data.fileNumber;
                            if (data.record) {
                                if (!this.formData.full_name) this.formData.full_name = data.record.file_name;
                                if (!this.formData.address) this.formData.address = data.record.location;
                            }
                        }
                    });
                }
            },

            // Watch for changes in value_figures to format them
            formatCurrency() {
                let val = this.formData.value_figures.toString().replace(/[^0-9.]/g, '');
                if (val) {
                    this.formData.value_figures = new Intl.NumberFormat('en-NG', {
                        minimumFractionDigits: 2,
                        maximumFractionDigits: 2
                    }).format(parseFloat(val));
                }
            },

            updateValueInWords() {
                let val = this.formData.value_figures.toString().replace(/[^0-9.]/g, '');
                if (val && !isNaN(val)) {
                    this.formData.value_words = this.numberToWords(parseFloat(val));
                } else {
                    this.formData.value_words = '';
                }
            },

            numberToWords(num) {
                if (num === 0) return 'Zero Naira Only';
                if (!num) return '';

                const ones = ['', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine'];
                const tens = ['', '', 'Twenty', 'Thirty', 'Forty', 'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety'];
                const teens = ['Ten', 'Eleven', 'Twelve', 'Thirteen', 'Fourteen', 'Fifteen', 'Sixteen', 'Seventeen', 'Eighteen', 'Nineteen'];

                const convert_hundreds = (n) => {
                    let str = '';
                    if (n >= 100) {
                        str += ones[Math.floor(n / 100)] + ' Hundred ';
                        n %= 100;
                    }
                    if (n >= 10 && n <= 19) {
                        str += teens[n - 10];
                    } else if (n >= 20) {
                        str += tens[Math.floor(n / 10)] + (n % 10 !== 0 ? '-' + ones[n % 10] : '');
                    } else if (n > 0) {
                        str += ones[n];
                    }
                    return str.trim();
                };

                const convert_section = (n, unit) => {
                    if (n === 0) return '';
                    return convert_hundreds(n) + ' ' + unit + ' ';
                };

                let n = Math.floor(Math.abs(num));
                let kobo = Math.round((Math.abs(num) - n) * 100);

                let parts = [];
                // Handle billions, millions, thousands
                if (n >= 1000000000) {
                    parts.push(convert_section(Math.floor(n / 1000000000), 'Billion'));
                    n %= 1000000000;
                }
                if (n >= 1000000) {
                    parts.push(convert_section(Math.floor(n / 1000000), 'Million'));
                    n %= 1000000;
                }
                if (n >= 1000) {
                    parts.push(convert_section(Math.floor(n / 1000), 'Thousand'));
                    n %= 1000;
                }
                parts.push(convert_hundreds(n));

                let res = parts.filter(p => p !== '').join(' ').replace(/\s+/g, ' ').trim();
                if (!res || res === '') res = 'Zero';

                res = res + ' Naira';

                if (kobo > 0) {
                    res += ' and ' + convert_hundreds(kobo) + ' Kobo';
                }

                return res + ' Only';
            },
            nextStep() {
                this.step++;
                this.$nextTick(() => { if (window.lucide) window.lucide.createIcons(); });
            },

            async submitForm() {
                if (this.submitting) return;

                // Final validation check for all required fields
                const requiredFields = [
                    { key: 'inspection_date', label: 'Inspection Date' },
                    { key: 'file_number', label: 'File Number' },
                    { key: 'valuation_purpose', label: 'Purpose of Valuation' },
                    { key: 'phone', label: 'Phone Number' },
                    { key: 'value_figures', label: 'Valuation Amount (Figures)' },
                    { key: 'value_words', label: 'Valuation Amount (Words)' }
                ];

                const missing = requiredFields.filter(f => !this.formData[f.key]);

                if (missing.length > 0) {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Missing Required Information',
                        html: `Please provide: <br><b>${missing.map(m => m.label).join(', ')}</b>`,
                        confirmButtonColor: '#3b82f6'
                    });
                    return;
                }

                this.submitting = true;
                const url = this.isEdit ? `/valuation-reports/${this.formData.id}` : '/valuation-reports';
                const method = this.isEdit ? 'PUT' : 'POST';

                try {
                    const response = await fetch(url, {
                        method: 'POST', // Use POST with _method spoofing for Laravel
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                        },
                        body: JSON.stringify({
                            ...this.formData,
                            addr_district: this.formData.addr_district === 'Other' ? this.formData.custom_district : this.formData.addr_district,
                            _method: method
                        })
                    });

                    const result = await response.json();

                    if (result.success) {
                        Swal.fire({
                            icon: 'success',
                            title: this.isEdit ? 'Updated!' : 'Generated!',
                            text: result.message,
                            timer: 1500,
                            showConfirmButton: false
                        }).then(() => {
                            window.location.reload();
                        });
                    } else {
                        throw new Error(result.message || 'Validation failed');
                    }
                } catch (err) {
                    Swal.fire('Error', err.message, 'error');
                } finally {
                    this.submitting = false;
                }
            }
        }));
    });
</script>

<style>
    .custom-scrollbar::-webkit-scrollbar {
        width: 6px;
    }

    .custom-scrollbar::-webkit-scrollbar-track {
        background: #f1f5f9;
    }

    .custom-scrollbar::-webkit-scrollbar-thumb {
        background: #cbd5e1;
        border-radius: 10px;
    }

    .custom-scrollbar::-webkit-scrollbar-thumb:hover {
        background: #94a3b8;
    }
</style>
