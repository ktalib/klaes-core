    <style>
        #valuation-form input[type="text"], 
    #valuation-form textarea, 
    #valuation-form input[type="search"] {
        text-transform: uppercase !important;
    }

    /* Select2 Premium Styling for Valuation Modal */
    .select2-container--default .select2-selection--multiple {
        border-radius: 0.75rem !important;
        border: 1px solid #e2e8f0 !important;
        background-color: #f8fafc !important;
        padding: 4px 8px !important;
        min-height: 46px !important;
        transition: all 0.2s ease !important;
    }
    .select2-container--default.select2-container--focus .select2-selection--multiple {
        border-color: #3b82f6 !important;
        background-color: #fff !important;
        box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.1) !important;
    }
    .select2-container--default .select2-selection--multiple .select2-selection__choice {
        background-color: #eff6ff !important;
        border: 1px solid #bfdbfe !important;
        color: #1e40af !important;
        border-radius: 0.5rem !important;
        padding: 2px 8px !important;
        font-weight: 700 !important;
        font-size: 11px !important;
        margin-top: 6px !important;
    }
    .select2-container--default .select2-selection--multiple .select2-selection__choice__remove {
        color: #3b82f6 !important;
        margin-right: 5px !important;
    }
    .select2-dropdown {
        border-radius: 1rem !important;
        border: 1px solid #e2e8f0 !important;
        box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1), 0 8px 10px -6px rgba(0, 0, 0, 0.1) !important;
        overflow: hidden !important;
    }
    </style>
<!-- Valuation for Compensation Modal -->
<div id="valuation-modal" class="fixed inset-0 z-50 hidden flex items-center justify-center p-4">
    <!-- Background overlay -->
    <div class="fixed inset-0 bg-slate-900/40 backdrop-blur-sm transition-opacity" aria-hidden="true" id="modal-overlay"></div>

    <div class="relative bg-white rounded-3xl shadow-2xl w-full max-w-4xl max-h-[90vh] flex flex-col overflow-hidden border border-slate-100 transform transition-all">
        <!-- Header -->
        <div class="px-8 py-6 border-b border-slate-100 flex items-center justify-between bg-white shrink-0">
            <div>
                <h3 class="text-xl font-bold text-slate-900" id="modal-title">Valuation for Compensation Data Entry</h3>
                <p class="text-xs text-slate-500 mt-1 uppercase tracking-widest font-semibold">Record property compensation details</p>
            </div>
            <button type="button" class="close-modal text-slate-400 hover:text-slate-600 p-2 hover:bg-slate-50 rounded-xl transition">
                <i data-lucide="x" class="h-6 w-6"></i>
            </button>
        </div>

        <!-- Form Body -->
        <form id="valuation-form" action="{{ route('valuation-compensations.store') }}" method="POST" class="flex-1 overflow-y-auto">
            @csrf
            <input type="hidden" name="id" id="record_id">
            
            <div class="px-8 py-8 space-y-8">
                <!-- Section 0: Project & Worker (Mandatory for New) -->
                <div class="space-y-6 pb-8 border-b border-slate-100" id="project-selection-section">
                    <div class="flex items-center gap-3 mb-2">
                        <div class="w-8 h-8 rounded-full bg-amber-50 flex items-center justify-center text-amber-600">
                            <i data-lucide="briefcase" class="h-4 w-4"></i>
                        </div>
                        <h4 class="text-sm font-bold text-slate-700 uppercase tracking-wider">Project & Assignment</h4>
                    </div>

                    <div class="space-y-6">
                        <!-- Row 1: Project Selection -->
                        <div>
                            <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">Select Project <span class="text-red-500">*</span></label>
                            <select name="project_id" id="vfc_project_id" required
                                class="w-full px-4 py-3 rounded-xl border border-slate-200 bg-slate-50 focus:border-blue-500 focus:bg-white transition text-sm font-medium">
                                <option value="">Select Project</option>
                            </select>
                        </div>

                        <!-- Row 2: Project Summary (Full Width) -->
                        <div id="project-info" class="hidden p-6 rounded-2xl bg-blue-50/50 border border-blue-100/50">
                            <p class="text-[10px] font-bold text-blue-600 uppercase tracking-widest flex items-center gap-2 mb-4 px-1">
                                <i data-lucide="info" class="h-3 w-3"></i> Project Summary
                            </p>
                            <div class="space-y-6">
                                <!-- Primary Identifiers -->
                                <div class="flex flex-wrap items-start justify-between gap-x-10 gap-y-4 pb-5 border-b border-blue-100/50">
                                    <div class="flex flex-col">
                                        <span class="text-[9px] text-blue-400 font-bold uppercase tracking-widest mb-1.5">Project FileNo</span>
                                        <div class="flex items-center gap-2.5">
                                            <i data-lucide="file-text" class="h-4 w-4 text-blue-500"></i>
                                            <span id="proj_fileno_summary" class="text-[13px] font-black text-blue-900 font-mono whitespace-nowrap">-</span>
                                        </div>
                                    </div>

                                    <div class="flex flex-col">
                                        <span class="text-[9px] text-amber-500/70 font-bold uppercase tracking-widest mb-1.5">Project Code</span>
                                        <div class="flex items-center gap-2.5">
                                            <i data-lucide="tag" class="h-4 w-4 text-amber-600"></i>
                                            <span id="proj_code_summary" class="text-[13px] font-black text-amber-800 font-mono whitespace-nowrap">-</span>
                                        </div>
                                    </div>

                                    <div class="flex flex-col">
                                        <span class="text-[9px] text-slate-400 font-bold uppercase tracking-widest mb-1.5">Project ID</span>
                                        <div class="flex items-center gap-2.5">
                                            <i data-lucide="hash" class="h-4 w-4 text-slate-500"></i>
                                            <span id="proj_id_summary" class="text-[13px] font-black text-slate-700">-</span>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Statistics -->
                                <div class="flex flex-wrap items-center gap-x-16 gap-y-4">
                                    <div class="flex items-center gap-3">
                                        <div class="h-10 w-10 rounded-xl bg-white border border-slate-100 shadow-sm flex items-center justify-center text-indigo-400">
                                            <i data-lucide="users" class="h-5 w-5"></i>
                                        </div>
                                        <div class="flex flex-col">
                                            <span class="text-[9px] text-slate-400 font-bold uppercase tracking-tight">Total Workers</span>
                                            <span id="proj_workers_summary" class="text-sm font-black text-slate-800">0</span>
                                        </div>
                                    </div>

                                    <div class="flex items-center gap-3">
                                        <div class="h-10 w-10 rounded-xl bg-white border border-slate-100 shadow-sm flex items-center justify-center text-emerald-400">
                                            <i data-lucide="check-circle" class="h-5 w-5"></i>
                                        </div>
                                        <div class="flex flex-col">
                                            <span class="text-[9px] text-slate-400 font-bold uppercase tracking-tight">Forms Filled</span>
                                            <span id="proj_valuations_summary" class="text-sm font-black text-emerald-700">0</span>
                                        </div>
                                    </div>

                                    <div class="flex items-center gap-3">
                                        <div class="h-10 w-10 rounded-xl bg-white border border-slate-100 shadow-sm flex items-center justify-center text-amber-400">
                                            <i data-lucide="layers" class="h-5 w-5"></i>
                                        </div>
                                        <div class="flex flex-col">
                                            <span class="text-[9px] text-slate-400 font-bold uppercase tracking-tight">Sub-Projects</span>
                                            <span id="proj_subprojects_summary" class="text-sm font-black text-amber-700">0</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Row 3: Worker & Sub-Project -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">Assigned Worker <span class="text-red-500">*</span></label>
                                <select name="worker_id" id="vfc_worker_id" required
                                    class="w-full px-4 py-3 rounded-xl border border-slate-200 bg-slate-50 focus:border-blue-500 focus:bg-white transition text-sm font-medium">
                                    <option value="">Select Worker (Select Project First)</option>
                                </select>
                                <p class="text-[10px] text-slate-400 mt-1 italic">The Worker ID will be automatically recorded.</p>
                            </div>
                            <div id="sub-project-section" class="hidden">
                                <label class="block text-xs font-bold text-indigo-600 uppercase tracking-wider mb-2">Select Sub-Project <span class="text-slate-400 font-normal normal-case">(optional)</span></label>
                                <select name="sub_project_id" id="vfc_sub_project_id"
                                    class="w-full px-4 py-3 rounded-xl border border-indigo-200 bg-indigo-50/30 focus:border-indigo-500 focus:bg-white transition text-sm font-bold text-indigo-700">
                                    <option value="">Select Sub-Project</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Section 1: File & Owner -->
                <div class="space-y-6">
                    <div class="flex items-center gap-3 mb-2">
                        <div class="w-8 h-8 rounded-full bg-blue-50 flex items-center justify-center text-blue-600">
                            <i data-lucide="file-text" class="h-4 w-4"></i>
                        </div>
                        <h4 class="text-sm font-bold text-slate-700 uppercase tracking-wider">File & Owner Information</h4>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Project References (From Project) -->
                        <div class="md:col-span-2 grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">Project Code <span class="text-slate-400 font-normal italic">(From Project)</span></label>
                                <input type="text" id="project_code_display" readonly
                                    class="w-full px-4 py-3 rounded-xl border border-amber-200 bg-amber-50 text-sm font-bold font-mono text-amber-700 cursor-not-allowed shadow-sm"
                                    placeholder="Select Project First...">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">Project FileNo <span class="text-slate-400 font-normal italic">(From Project)</span></label>
                                <input type="text" id="our_ref" readonly
                                    class="w-full px-4 py-3 rounded-xl border border-blue-200 bg-blue-50 text-sm font-bold font-mono text-blue-700 cursor-not-allowed shadow-sm"
                                    placeholder="Select Project First...">
                                <input type="hidden" name="project_fileno" id="hidden_project_fileno">
                            </div>
                        </div>

                        <!-- References -->
                        <div class="md:col-span-2 grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">Our Reference <span class="text-red-500">*</span></label>
                                <input type="text" name="our_ref" id="manual_our_ref" readonly
                                    class="w-full px-4 py-3 rounded-xl border border-slate-200 bg-slate-100 text-sm font-bold font-mono text-slate-500 cursor-not-allowed shadow-inner"
                                    placeholder="Select Project First...">
                            </div>

                            <div>
                                <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">Your Reference</label>
                                <input type="text" name="your_ref" id="your_ref" readonly
                                    class="w-full px-4 py-3 rounded-xl border border-slate-200 bg-slate-100 text-sm font-medium text-slate-500 cursor-not-allowed shadow-inner"
                                    placeholder="Select Project First...">
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">Owner Name <span class="text-red-500">*</span></label>
                            <input type="text" name="owner_name" id="owner_name" required
                                class="w-full px-4 py-3 rounded-xl border border-slate-200 bg-slate-50 focus:border-blue-500 focus:bg-white transition text-sm font-medium"
                                placeholder="e.g. Musa Yakubu">
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">Valuation Date <span class="text-red-500">*</span></label>
                            <input type="date" name="valuation_date" id="valuation_date" required value="{{ date('Y-m-d') }}"
                                class="w-full px-4 py-3 rounded-xl border border-slate-200 bg-slate-50 focus:border-blue-500 focus:bg-white transition text-sm font-medium">
                        </div>
                    </div>
                </div>

                <!-- Section 2: Building & Compensated Items -->
                <div class="pt-8 border-t border-slate-100">
                    <div class="flex items-center gap-3 mb-6">
                        <div class="w-8 h-8 rounded-full bg-emerald-50 flex items-center justify-center text-emerald-600">
                            <i data-lucide="home" class="h-4 w-4"></i>
                        </div>
                        <h4 class="text-sm font-bold text-slate-700 uppercase tracking-wider">Building & Compensated Items</h4>
                    </div>

                    <div class="space-y-6 mb-6">
                        <!-- Building Count -->
                        <div class="max-w-md">
                            <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2 flex items-center gap-2">
                                <i data-lucide="hash" class="h-3 w-3"></i>
                                Building Count  
                            </label>
                            <input type="number" name="building_count" id="building_count" min="1" value="1"
                                class="w-full px-4 py-3 rounded-xl border border-slate-200 bg-slate-50 focus:border-blue-500 focus:bg-white transition text-sm font-medium">
                        </div>

                        <!-- Building Type(s) & Stages -->
                        <div>
                            <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2 flex items-center gap-2">
                                <i data-lucide="building" class="h-3 w-3"></i>
                                Building Assessment & Cost Details <span class="text-red-500">*</span>
                            </label>
                            <div id="building_types_container" class="grid grid-cols-1 gap-4">
                                <div class="building-type-row p-4 rounded-xl border border-slate-100 bg-slate-50/50">
                                    <div class="text-[10px] font-black text-slate-400 uppercase mb-3 flex items-center gap-2">
                                        <i data-lucide="building" class="h-3 w-3"></i>
                                        Building 1
                                    </div>
                                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                        <div>
                                            <label class="block text-[9px] font-bold text-slate-500 uppercase tracking-wider mb-1">Building Type</label>
                                            <select class="building-type-select w-full px-4 py-3 rounded-xl border border-slate-200 bg-white focus:border-blue-500 transition text-sm font-medium">
                                                <option value="">Select Building Type</option>
                                                @foreach($buildingTypes as $item)
                                                    <option value="{{ $item->name }}">{{ $item->name }}</option>
                                                @endforeach
                                            </select>
                                            <input type="text" class="building-type-other hidden mt-2 w-full px-4 py-2 rounded-xl border border-blue-200 bg-blue-50 focus:border-blue-500 focus:bg-white transition text-xs font-medium" placeholder="Specify type...">
                                        </div>
                                        <div>
                                            <label class="block text-[9px] font-bold text-slate-500 uppercase tracking-wider mb-1">Stage of Completion</label>
                                            <select class="building-stage-select w-full px-4 py-3 rounded-xl border border-slate-200 bg-white focus:border-blue-500 transition text-sm font-medium">
                                                <option value="">Select Stage</option>
                                                @foreach($valuationItems->where('type', 'CompletionStage') as $item)
                                                    <option value="{{ $item->name }}">{{ $item->name }}</option>
                                                @endforeach
                                            </select>
                                            <input type="text" class="building-stage-other hidden mt-2 w-full px-4 py-2 rounded-xl border border-blue-200 bg-blue-50 focus:border-blue-500 focus:bg-white transition text-xs font-medium" placeholder="Specify stage...">
                                        </div>
                                        <div>
                                            <label class="block text-[9px] font-bold text-slate-500 uppercase tracking-wider mb-1">Number of Floors</label>
                                            <input type="number" min="1" step="1" class="building-floors w-full px-4 py-3 rounded-xl border border-slate-200 bg-white focus:border-blue-500 transition text-sm font-medium" placeholder="e.g. 2">
                                        </div>
                                    </div>

                                    <div class="grid grid-cols-1 md:grid-cols-5 gap-4 mt-4 pt-4 border-t border-slate-100">
                                        <div>
                                            <label class="block text-[9px] font-bold text-slate-500 uppercase tracking-wider mb-1">Length (L) (m)</label>
                                            <input type="number" step="0.01" class="building-length w-full px-4 py-2.5 rounded-xl border border-slate-200 bg-white focus:border-blue-500 transition text-xs font-medium" placeholder="0.00">
                                        </div>
                                        <div>
                                            <label class="block text-[9px] font-bold text-slate-500 uppercase tracking-wider mb-1">Breadth (B) (m)</label>
                                            <input type="number" step="0.01" class="building-breadth w-full px-4 py-2.5 rounded-xl border border-slate-200 bg-white focus:border-blue-500 transition text-xs font-medium" placeholder="0.00">
                                        </div>
                                        <div>
                                            <label class="block text-[9px] font-bold text-slate-500 uppercase tracking-wider mb-1">Area Covered (m²)</label>
                                            <input type="number" step="0.01" class="building-area w-full px-4 py-2.5 rounded-xl border border-blue-100 bg-blue-50/50 text-blue-700 font-bold focus:border-blue-500 transition text-xs font-medium shadow-inner" placeholder="0.00">
                                        </div>
                                        <div>
                                            <label class="block text-[9px] font-bold text-slate-500 uppercase tracking-wider mb-1">Rate of Cost (₦)</label>
                                            <input type="number" step="0.01" class="building-rate w-full px-4 py-2.5 rounded-xl border border-slate-200 bg-white focus:border-blue-500 transition text-xs font-medium" placeholder="0.00">
                                        </div>
                                        <div>
                                            <label class="block text-[9px] font-bold text-slate-500 uppercase tracking-wider mb-1">Amount (₦)</label>
                                            <div class="relative">
                                                <input type="number" step="0.01" class="building-comp w-full pl-7 pr-4 py-2.5 rounded-xl border border-blue-200 bg-white font-bold text-blue-700 text-xs shadow-sm" placeholder="0.00" readonly>
                                                <span class="absolute left-3 top-1/2 -translate-y-1/2 text-blue-400 font-bold text-xs">₦</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <input type="hidden" name="building_type" id="building_type_final">
                            <input type="hidden" name="completion_stage" id="completion_stage_final">
                            <input type="hidden" name="number_of_floors" id="number_of_floors_final">
                        </div>
                    </div>

                    <div class="hidden">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div class="md:col-span-2 grid grid-cols-1 md:grid-cols-3 gap-6">
                                <div>
                                    <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">Total Length (L) <span class="text-slate-400 font-normal italic">(m)</span></label>
                                    <input type="number" step="0.01" name="length" id="length" readonly
                                        class="w-full px-4 py-3 rounded-xl border border-slate-200 bg-slate-100 text-sm font-bold text-slate-500 cursor-not-allowed shadow-inner transition"
                                        placeholder="0.00">
                                </div>
                                <div>
                                    <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">Total Breadth (B) <span class="text-slate-400 font-normal italic">(m)</span></label>
                                    <input type="number" step="0.01" name="breadth" id="breadth" readonly
                                        class="w-full px-4 py-3 rounded-xl border border-slate-200 bg-slate-100 text-sm font-bold text-slate-500 cursor-not-allowed shadow-inner transition"
                                        placeholder="0.00">
                                </div>
                                <div>
                                    <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">Total Area Covered (m²) <span class="text-red-500">*</span></label>
                                    <input type="number" step="0.01" name="area_covered" id="area_covered" readonly
                                        class="w-full px-4 py-3 rounded-xl border border-blue-100 bg-blue-50/50 text-sm font-bold text-blue-700 shadow-inner transition cursor-not-allowed"
                                        placeholder="0.00">
                                </div>
                            </div>

                            <div>
                                <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">Average Rate of Cost (₦) <span class="text-red-500">*</span></label>
                                <input type="number" step="0.01" name="rate_of_cost" id="rate_of_cost" readonly
                                    class="w-full px-4 py-3 rounded-xl border border-slate-200 bg-slate-100 text-sm font-bold text-slate-500 cursor-not-allowed shadow-inner transition"
                                    placeholder="0.00">
                            </div>

                            <div>
                                <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">Total Amount of Compensation</label>
                                <div class="relative">
                                    <input type="number" step="0.01" name="compensation_amount" id="compensation_amount" readonly
                                        class="w-full pl-10 pr-4 py-3 rounded-xl border border-blue-200 bg-blue-50/30 font-bold text-blue-700 text-lg focus:ring-0 shadow-inner cursor-not-allowed"
                                        placeholder="0.00">
                                    <div class="absolute left-4 top-1/2 -translate-y-1/2 text-blue-400 font-bold">₦</div>
                                </div>
                            </div>
                        </div>
                    </div>



                    <!-- Compensated Items -->
                    <div class="pt-6 space-y-8">
                        <div class="flex items-center justify-between">
                            <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider">Structures Considered During Valuation</label>
                            <span class="text-[10px] text-slate-400 font-medium italic">Select items and enter measurements where required</span>
                        </div>

                        @php
                            // Items categorized as 'Other' (not in the dropdowns above)
                            $filteredItems = $valuationItems->where('type', 'Other');
                            
                            $linearItemsList = ['Cornstalk Fence', 'Sandcare Wall', 'Wire mesh', 'Mud Wall'];
                            $volumeItemsList = ['Pavement', 'Mass concrete pavement', 'Interlock Court yard', 'DPC', 'Fish pond'];
                            $shedItemsList   = []; 

                            $groups = [
                                [
                                    'title' => 'Linear Measurements (Fencing/Walls)',
                                    'subtitle' => 'L + B × Rate',
                                    'icon' => 'maximize-2',
                                    'color' => 'amber',
                                    'is_exclusive' => false,
                                    'items' => $filteredItems->filter(fn($i) => in_array($i->name, $linearItemsList))
                                ],
                                [
                                    'title' => 'Volume Measurements (Pavement/DPC)',
                                    'subtitle' => 'L × B × Rate',
                                    'icon' => 'box',
                                    'color' => 'indigo',
                                    'is_exclusive' => false,
                                    'items' => $filteredItems->filter(fn($i) => in_array($i->name, $volumeItemsList))
                                ],
                                [
                                    'title' => 'Other Structures',
                                    'subtitle' => 'Standard & Area Based',
                                    'icon' => 'archive',
                                    'color' => 'blue',
                                    'is_exclusive' => false,
                                    'items' => $filteredItems->filter(fn($i) => 
                                        !in_array($i->name, $linearItemsList) && 
                                        !in_array($i->name, $volumeItemsList)
                                    )
                                ]
                            ];
                        @endphp

                        @foreach($groups as $group)
                            @if($group['items']->count() > 0)
                                <div class="space-y-4">
                                    <div class="flex items-center gap-3">
                                        <div class="h-px flex-1 bg-slate-100"></div>
                                        <div class="flex items-center gap-2 px-3 py-1 rounded-full bg-{{ $group['color'] }}-50 border border-{{ $group['color'] }}-100">
                                            <i data-lucide="{{ $group['icon'] }}" class="h-3 w-3 text-{{ $group['color'] }}-500"></i>
                                            <span class="text-[10px] font-bold text-{{ $group['color'] }}-700 uppercase tracking-widest">{{ $group['title'] }}</span>
                                            <span class="text-[9px] font-medium text-{{ $group['color'] }}-400 bg-white px-1.5 py-0.5 rounded border border-{{ $group['color'] }}-50">{{ $group['subtitle'] }}</span>
                                        </div>
                                        <div class="h-px flex-1 bg-slate-100"></div>
                                    </div>

                                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                                        @foreach($group['items'] as $item)
                                            @php
                                                $icon = 'box';
                                                $lower = strtolower($item->name);
                                                if (str_contains($lower, 'building') || str_contains($lower, 'permanent') || str_contains($lower, 'warehouse')) $icon = 'building';
                                                if (str_contains($lower, 'wall') || str_contains($lower, 'fence')) $icon = 'fence';
                                                if (str_contains($lower, 'pavement') || str_contains($lower, 'court yard')) $icon = 'layers';
                                                if (str_contains($lower, 'borehole') || str_contains($lower, 'well')) $icon = 'droplets';
                                                if (str_contains($lower, 'soackaway') || str_contains($lower, 'pitlatrine')) $icon = 'trash-2';
                                                if (str_contains($lower, 'reservoir') || str_contains($lower, 'pond')) $icon = 'container';
                                                if (str_contains($lower, 'hut') || str_contains($lower, 'shed') || str_contains($lower, 'cage')) $icon = 'home';
                                                if (str_contains($lower, 'nest') || str_contains($lower, 'animal') || str_contains($lower, 'fish')) $icon = 'ghost';
                                                if (str_contains($lower, 'granary')) $icon = 'archive';
                                                if (str_contains($lower, 'fuel') || str_contains($lower, 'pump')) $icon = 'fuel';
                                                if (str_contains($lower, 'wire') || str_contains($lower, 'mesh')) $icon = 'grid';
                                                if (str_contains($lower, 'dpc')) $icon = 'maximize';
                                                
                                                // Determine calculation type per item
                                                $calcType = 'standard';
                                                if (in_array($item->name, $linearItemsList)) $calcType = 'linear';
                                                elseif (in_array($item->name, $volumeItemsList)) $calcType = 'volume';
                                                elseif (in_array($item->name, $shedItemsList)) $calcType = 'shed';

                                                $itemColor = $group['color'];
                                                $itemFormulaBadge = null;
                                                
                                                if ($calcType === 'shed') {
                                                    $itemColor = 'emerald';
                                                    $itemFormulaBadge = 'L × W × Rate';
                                                } elseif ($calcType === 'linear') {
                                                    $itemFormulaBadge = 'L + B × Rate';
                                                } elseif ($calcType === 'volume') {
                                                    $itemFormulaBadge = 'L × B × Rate';
                                                }
                                            @endphp

                                            <div class="flex flex-col gap-2 p-3 rounded-xl border border-slate-100 hover:border-{{ $itemColor }}-200 hover:bg-{{ $itemColor }}-50/30 transition group item-container bg-white shadow-sm">
                                                <label class="flex items-center gap-3 cursor-pointer relative">
                                                    <input type="checkbox" name="compensated_items_list[]" value="{{ $item->name }}"
                                                        class="item-checkbox absolute top-0 right-0 w-4 h-4 text-{{ $itemColor }}-600 border-slate-300 rounded focus:ring-{{ $itemColor }}-500">
                                                    <div class="w-8 h-8 rounded-lg bg-slate-50 flex items-center justify-center text-slate-400 group-hover:bg-{{ $itemColor }}-100 group-hover:text-{{ $itemColor }}-600 transition flex-shrink-0">
                                                        <i data-lucide="{{ $icon }}" class="h-4 w-4"></i>
                                                    </div>
                                                    <div class="flex flex-col gap-0.5 pr-5 min-w-0">
                                                        <span class="text-[11px] font-bold text-slate-600 group-hover:text-{{ $itemColor }}-700 leading-tight">{{ $item->name }}</span>
                                                        @if($itemFormulaBadge)
                                                            <span class="text-[8px] font-bold uppercase tracking-wider text-{{ $itemColor }}-500">{{ $itemFormulaBadge }}</span>
                                                        @endif
                                                    </div>
                                                </label>

                                                <div class="item-amount-wrapper hidden mt-1">
                                         @if($calcType === 'linear')
                                        {{-- Linear: Total Length = L + B (Sum: a + b + ... N) --}}
                                        <div class="space-y-2">
                                            <div>
                                                <label class="block text-[9px] font-bold text-amber-500 uppercase tracking-wider mb-1">Dimensions (Sum: L + B + ... N)</label>
                                                <input type="text"
                                                    class="sub-calc-trigger sub-length-formula w-full px-2 py-2 rounded-lg border border-amber-200 bg-white text-[11px] font-bold text-slate-700 focus:border-amber-400 outline-none transition"
                                                    placeholder="e.g. 15.5 + 10" data-item-name="{{ $item->name }}">
                                            </div>
                                            
                                            <div class="grid grid-cols-2 gap-2">
                                                <div class="flex flex-col justify-center px-2 py-1.5 bg-amber-50/50 rounded-lg border border-amber-100">
                                                    <span class="text-[8px] font-bold text-amber-500 uppercase tracking-tight">Total Length (X)</span>
                                                    <span class="sub-total-length-display text-[11px] font-black text-amber-700">0.00m</span>
                                                    <input type="hidden" class="sub-length" value="0">
                                                </div>
                                                <div>
                                                    <label class="block text-[9px] font-bold text-amber-500 uppercase tracking-wider mb-1">Rate (₦/m)</label>
                                                    <div class="relative">
                                                        <input type="number" step="0.01" min="0"
                                                            class="sub-calc-trigger sub-rate w-full pl-5 pr-2 py-2 rounded-lg border border-amber-200 bg-white text-[11px] font-bold text-slate-700 focus:border-amber-400 outline-none transition"
                                                            placeholder="0.00" data-item-name="{{ $item->name }}">
                                                        <span class="absolute left-1.5 top-1/2 -translate-y-1/2 text-[10px] text-amber-400 font-bold">₦</span>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="flex items-center gap-2 px-2 py-1.5 bg-amber-100/50 rounded-lg border border-amber-200">
                                                <div class="flex flex-col">
                                                    <span class="text-[8px] font-black text-amber-600 uppercase">Total Length (L+B) [RATE] = Amount</span>
                                                    <div class="relative mt-0.5">
                                                        <span class="absolute left-0 top-1/2 -translate-y-1/2 text-[10px] text-amber-600 font-bold">₦</span>
                                                        <input type="number" step="0.01" readonly
                                                            class="item-amount-input w-full bg-transparent text-[12px] font-black text-amber-800 outline-none pl-4"
                                                            placeholder="0.00" data-item-name="{{ $item->name }}">
                                                    </div>
                                                </div>
                                            </div>
                                        </div>                                     
                                        @elseif($calcType === 'volume')
                                            {{-- Volume: Area = (L×B) [RATE] = Amount --}}
                                            <div class="space-y-2">
                                                <div class="grid grid-cols-2 gap-1.5">
                                                    <div>
                                                        <label class="block text-[9px] font-bold text-indigo-400 uppercase tracking-wider mb-1">L (m)</label>
                                                        <input type="number" step="0.01" min="0"
                                                            class="sub-calc-trigger sub-length w-full px-2 py-1.5 rounded-lg border border-indigo-200 bg-white text-[11px] font-bold text-slate-700 focus:border-indigo-400 outline-none transition"
                                                            placeholder="0.00" data-item-name="{{ $item->name }}">
                                                    </div>
                                                    <div>
                                                        <label class="block text-[9px] font-bold text-indigo-400 uppercase tracking-wider mb-1">B (m)</label>
                                                        <input type="number" step="0.01" min="0"
                                                            class="sub-calc-trigger sub-width w-full px-2 py-1.5 rounded-lg border border-indigo-200 bg-white text-[11px] font-bold text-slate-700 focus:border-indigo-400 outline-none transition"
                                                            placeholder="0.00" data-item-name="{{ $item->name }}">
                                                    </div>
                                                </div>
                                                
                                                <div class="grid grid-cols-2 gap-2">
                                                    <div class="flex flex-col justify-center px-2 py-1.5 bg-indigo-50/50 rounded-lg border border-indigo-100">
                                                        <span class="text-[8px] font-bold text-indigo-500 uppercase tracking-tight">Area (L×B)</span>
                                                        <span class="sub-total-volume-display text-[11px] font-black text-indigo-700">0.00m²</span>
                                                        <input type="hidden" class="sub-volume-val" value="0">
                                                    </div>
                                                    <div>
                                                        <label class="block text-[9px] font-bold text-indigo-400 uppercase tracking-wider mb-1">Rate (₦/m²)</label>
                                                        <div class="relative">
                                                            <input type="number" step="0.01" min="0"
                                                                class="sub-calc-trigger sub-rate w-full pl-5 pr-2 py-2 rounded-lg border border-indigo-200 bg-white text-[11px] font-bold text-slate-700 focus:border-indigo-400 outline-none transition"
                                                                placeholder="0.00" data-item-name="{{ $item->name }}">
                                                            <span class="absolute left-1.5 top-1/2 -translate-y-1/2 text-[10px] text-indigo-400 font-bold">₦</span>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="flex items-center gap-2 px-2 py-1.5 bg-indigo-100/50 rounded-lg border border-indigo-200">
                                                    <div class="flex flex-col">
                                                        <span class="text-[8px] font-black text-indigo-600 uppercase">Area = (L×B) [RATE] = Amount</span>
                                                        <div class="relative mt-0.5">
                                                            <span class="absolute left-0 top-1/2 -translate-y-1/2 text-[10px] text-indigo-600 font-bold">₦</span>
                                                            <input type="number" step="0.01" readonly
                                                                class="item-amount-input w-full bg-transparent text-[12px] font-black text-indigo-800 outline-none pl-4"
                                                                placeholder="0.00" data-item-name="{{ $item->name }}">
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                                    @else
                                                        <div class="relative">
                                                            <input type="number" step="0.01" class="item-amount-input w-full pl-7 pr-3 py-1.5 rounded-lg border border-slate-200 bg-white text-[11px] font-bold text-blue-600 focus:border-blue-400 outline-none transition"
                                                                placeholder="Amount" data-item-name="{{ $item->name }}">
                                                            <div class="absolute left-2.5 top-1/2 -translate-y-1/2 text-[10px] text-blue-400 font-bold">₦</div>
                                                        </div>
                                                    @endif
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @endif
                        @endforeach

                        <input type="text" name="compensated_items_other" id="compensated_items_other"
                            class="hidden mt-4 w-full px-4 py-3 rounded-xl border border-blue-200 bg-blue-50/30 focus:border-blue-500 focus:bg-white transition text-sm font-medium"
                            placeholder="Specify other items (separate with commas)...">
                        <input type="hidden" name="compensated_items" id="compensated_items_val">
                    </div>
                </div>

                <!-- Section 3: Account & Payment Details -->
                <div class="pt-8 border-t border-slate-100">
                    <div class="flex items-center gap-3 mb-6">
                        <div class="w-8 h-8 rounded-full bg-teal-50 flex items-center justify-center text-teal-600">
                            <i data-lucide="credit-card" class="h-4 w-4"></i>
                        </div>
                        <h4 class="text-sm font-bold text-slate-700 uppercase tracking-wider">Account & Payment Details</h4>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="relative">
                            <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">Bank Name</label>
                            <div class="relative group">
                                <div class="absolute left-4 top-1/2 -translate-y-1/2 w-6 h-6 flex items-center justify-center" id="selected_bank_logo">
                                    <i data-lucide="building-2" class="h-4 w-4 text-slate-400"></i>
                                </div>
                                <input type="text" id="bank_search" autocomplete="off"
                                    class="w-full pl-12 pr-4 py-3 rounded-xl border border-slate-200 bg-slate-50 focus:border-blue-500 focus:bg-white transition text-sm font-medium"
                                    placeholder="Search Nigerian Bank...">
                                <input type="hidden" name="bank_name" id="bank_name_val">
                                
                                <div id="bank_dropdown" class="hidden absolute z-[60] left-0 right-0 mt-2 bg-white border border-slate-200 rounded-2xl shadow-2xl max-h-64 overflow-y-auto overflow-x-hidden">
                                </div>
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">Account Number <span class="text-red-500">*</span></label>
                            <input type="text" name="account_number" id="account_number" required
                                class="w-full px-4 py-3 rounded-xl border border-slate-200 bg-slate-50 focus:border-blue-500 focus:bg-white transition text-sm font-bold tracking-widest"
                                placeholder="e.g. 0123456789" maxlength="10">
                        </div>

                        <div class="md:col-span-2">
                            <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">Account Name <span class="text-red-500">*</span></label>
                            <input type="text" name="account_name" id="account_name" required
                                class="w-full px-4 py-3 rounded-xl border border-slate-200 bg-slate-50 focus:border-blue-500 focus:bg-white transition text-sm font-medium uppercase"
                                placeholder="Full Name as on Bank Account">
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">Phone Number <span class="text-red-500">*</span></label>
                            <input type="text" name="phone_number" id="phone_number" required
                                class="w-full px-4 py-3 rounded-xl border border-slate-200 bg-slate-50 focus:border-blue-500 focus:bg-white transition text-sm font-medium"
                                placeholder="e.g. 08012345678">
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">National Identity Number (NIN)</label>
                            <input type="text" name="nin" id="nin"
                                class="w-full px-4 py-3 rounded-xl border border-slate-200 bg-slate-50 focus:border-blue-500 focus:bg-white transition text-sm font-medium"
                                placeholder="e.g. 12345678901">
                        </div>

                        <div class="md:col-span-2">
                            <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">Remarks</label>
                            <input type="text" name="remarks" id="remarks"
                                class="w-full px-4 py-3 rounded-xl border border-slate-200 bg-slate-50 focus:border-blue-500 focus:bg-white transition text-sm font-medium"
                                placeholder="Any additional notes...">
                        </div>
                    </div>
                </div>

                <!-- Section 4: Property Location -->
                <div class="pt-8 border-t border-slate-100">
                    <div class="flex items-center gap-3 mb-6">
                        <div class="w-8 h-8 rounded-full bg-indigo-50 flex items-center justify-center text-indigo-600">
                            <i data-lucide="map-pin" class="h-4 w-4"></i>
                        </div>
                        <h4 class="text-sm font-bold text-slate-700 uppercase tracking-wider">Property Location</h4>
                    </div>

                    <div class="space-y-6">
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div class="md:col-span-1">
                                <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">Plot Number</label>
                                <input type="text" name="plot_no" id="plot_no"
                                    class="loc-trigger w-full px-4 py-3 rounded-xl border border-slate-200 bg-slate-50 focus:border-blue-500 focus:bg-white transition text-sm font-medium"
                                    placeholder="e.g. Plot 12A">
                            </div>
                            <div class="md:col-span-1">
                                <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">District <span class="text-slate-400 font-normal italic">(Multi-select)</span></label>
                                <select name="district[]" id="loc_district" multiple="multiple" class="loc-trigger vfc-select2 w-full px-4 py-3 rounded-xl border border-slate-200 bg-slate-50 focus:border-blue-500 focus:bg-white transition text-sm font-medium">
                                    @foreach($districts as $district)
                                        <option value="{{ $district->name }}">{{ $district->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="md:col-span-1">
                                <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">LGA <span class="text-red-500">*</span> <span class="text-slate-400 font-normal italic">(Multi-select)</span></label>
                                <select name="lga[]" id="loc_lga" required multiple="multiple" class="loc-trigger vfc-select2 w-full px-4 py-3 rounded-xl border border-slate-200 bg-slate-50 focus:border-blue-500 focus:bg-white transition text-sm font-medium">
                                    @foreach($lgas as $lga)
                                        <option value="{{ $lga->LGAName }}">{{ $lga->LGAName }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">State <span class="text-red-500">*</span></label>
                            <select name="state" id="loc_state" required class="loc-trigger w-full px-4 py-3 rounded-xl border border-slate-200 bg-slate-50 focus:border-blue-500 focus:bg-white transition text-sm font-medium">
                                <option value="">Select State</option>
                                @foreach($states as $state)
                                    <option value="{{ $state->StateName }}" {{ $state->StateName == 'Kano' ? 'selected' : '' }}>{{ $state->StateName }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">Full Location Address <span class="text-red-500">*</span></label>
                            <textarea name="location" id="location" required rows="2"
                                class="w-full px-4 py-3 rounded-xl border border-slate-200 bg-slate-50 focus:border-blue-500 focus:bg-white transition text-sm font-medium"
                                placeholder="Detailed location description..."></textarea>
                            <p class="text-[10px] text-slate-400 mt-1 italic">Automatically built from fields above but can be edited manually.</p>
                        </div>
                    </div>
                </div>

                <!-- Batch Entry Section (Visible when items added) -->
                <div id="batch-list-section" class="hidden pt-10 mt-10 border-t-4 border-slate-900">
                    <div class="flex items-center justify-between mb-6">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-xl bg-slate-900 flex items-center justify-center text-white shadow-lg">
                                <i data-lucide="layers" class="h-5 w-5"></i>
                            </div>
                            <div>
                                <h4 class="text-sm font-black text-slate-900 uppercase tracking-tighter">Staged Records Queue</h4>
                                <p class="text-[10px] text-slate-500 font-bold uppercase tracking-widest"><span id="batch-count">0</span> records pending commitment</p>
                            </div>
                        </div>
                        <button type="button" onclick="VFC.clearBatch()" class="text-[10px] font-black text-slate-400 hover:text-red-600 uppercase tracking-widest border-b-2 border-transparent hover:border-red-100 transition-all pb-0.5">
                            Clear Queue
                        </button>
                    </div>
                    
                    <div class="overflow-hidden rounded-2xl border-2 border-slate-900 bg-white shadow-sm">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="bg-slate-900 text-[10px] font-black text-white uppercase tracking-widest">
                                    <th class="px-6 py-4">Beneficiary / Owner</th>
                                    <th class="px-6 py-4">Asset Type</th>
                                    <th class="px-6 py-4 text-right">Compensation Amount</th>
                                    <th class="px-6 py-4 text-center">Action</th>
                                </tr>
                            </thead>
                            <tbody id="batch-table-body" class="text-xs text-slate-800 divide-y-2 divide-slate-50">
                                <!-- Dynamically populated -->
                            </tbody>
                            <tfoot>
                                <tr class="bg-slate-50 font-black text-slate-900">
                                    <td colspan="2" class="px-6 py-5 text-right text-[10px] uppercase tracking-widest border-t-2 border-slate-900">Total Batch Value:</td>
                                    <td class="px-6 py-5 text-right text-sm border-t-2 border-slate-900 font-mono" id="batch-total-amount">₦0.00</td>
                                    <td class="border-t-2 border-slate-900"></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
        </form>

        <!-- Footer -->
        <div class="px-8 py-6 border-t-2 border-slate-100 flex items-center justify-between bg-white shrink-0">
            <div class="flex items-center gap-2">
                <button type="button" class="close-modal px-4 py-2 rounded-xl text-slate-400 font-black uppercase tracking-widest hover:text-slate-900 transition text-[10px]">
                    Discard
                </button>
            </div>
            <div class="flex items-center gap-3">
                <button type="button" onclick="VFC.addToBatch()" id="add-to-batch-btn"
                    class="px-6 py-3 rounded-xl border-2 border-slate-900 text-slate-900 font-black hover:bg-slate-900 hover:text-white transition-all flex items-center gap-2 text-xs uppercase tracking-widest">
                    <i data-lucide="plus" class="h-4 w-4"></i>
                    <span>Add to List</span>
                </button>
                <button type="button" onclick="VFC.saveAllBatch()" id="save-batch-btn"
                    class="hidden px-8 py-3 rounded-xl bg-slate-900 text-white font-black shadow-xl shadow-slate-200 hover:bg-slate-800 transition-all flex items-center gap-2 text-xs uppercase tracking-widest">
                    <span>Commit Batch to Database</span>
                    <i data-lucide="database" class="h-4 w-4"></i>
                </button>
                <button type="submit" form="valuation-form" id="submit-btn"
                    class="px-8 py-3 rounded-xl bg-slate-900 text-white font-black shadow-xl shadow-slate-200 hover:bg-slate-800 transition-all flex items-center gap-2 text-xs uppercase tracking-widest">
                    <span>Save Single Record</span>
                    <i data-lucide="save" class="h-4 w-4"></i>
                </button>
            </div>
        </div>
    </div>
</div>
