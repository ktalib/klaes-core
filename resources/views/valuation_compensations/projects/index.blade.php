@extends('layouts.app')

@section('page-title')
    {{ __('Project Manager Console') }}
@endsection

@section('content')
<div class="flex-1 overflow-auto bg-slate-50/60">
    @include('admin.header', [
        'PageTitle' => 'Project Manager Console',
        'PageDescription' => 'Create and manage VFC projects and worker assignments'
    ])

    <div class="p-6 space-y-8 max-w-7xl mx-auto">
        <!-- Header Actions -->
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
            <div>
                <h1 class="text-3xl font-bold text-slate-900">VFC Projects</h1>
                <p class="text-slate-500 mt-1">Manage project-based valuation workflows</p>
            </div>
            <div class="flex gap-3">
                <a href="{{ route('valuation-compensations.index') }}" class="inline-flex items-center gap-2 px-6 py-3 rounded-xl bg-white border border-slate-200 text-slate-700 font-semibold shadow-sm hover:bg-slate-50 transition">
                    <i data-lucide="arrow-left" class="h-5 w-5"></i>
                    <span>Back to VFC</span>
                </a>
                <button type="button" onclick="openProjectModal()" class="inline-flex items-center gap-2 px-6 py-3 rounded-xl bg-blue-600 text-white font-semibold shadow-lg shadow-blue-100 hover:bg-blue-700 transition">
                    <i data-lucide="folder-plus" class="h-5 w-5"></i>
                    <span>Create New Project</span>
                </button>
            </div>
        </div>

        <!-- Project List -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            @forelse($projects as $project)
            <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden hover:shadow-md transition group">
                <div class="p-6 border-b border-slate-50 bg-slate-50/30">
                    <div class="flex justify-between items-start">
                        <div>
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-blue-100 text-blue-800 uppercase tracking-wider mb-2">
                                {{ $project->project_type }}
                            </span>
                            <h3 class="text-xl font-bold text-slate-900">{{ $project->project_name }}</h3>
                            <div class="flex items-center gap-4 mt-2">
                                <div class="flex items-center gap-2">
                                    <i data-lucide="hash" class="h-3 w-3 text-indigo-500"></i>
                                    <span class="text-[13px] font-black font-mono text-indigo-600 bg-indigo-50/50 px-2 py-0.5 rounded border border-indigo-100/50 shadow-sm">{{ $project->project_code }}</span>
                                </div>
                                @if($project->our_reference)
                                <div class="flex items-center gap-2 border-l border-slate-200 pl-4">
                                    <span class="text-[10px] font-bold text-slate-400 uppercase">Our Ref:</span>
                                    <span class="text-[11px] font-bold text-slate-700 bg-slate-100 px-2 py-0.5 rounded">{{ $project->our_reference }}</span>
                                </div>
                                @endif
                                @if($project->your_reference)
                                <div class="flex items-center gap-2 border-l border-slate-200 pl-4">
                                    <span class="text-[10px] font-bold text-slate-400 uppercase">Your Ref:</span>
                                    <span class="text-[11px] font-bold text-slate-700 bg-slate-100 px-2 py-0.5 rounded">{{ $project->your_reference }}</span>
                                </div>
                                @endif

                            </div>
                        </div>
                        <!-- Template Rows Removed -->
                    </div>
                </div>
                <div class="p-6">
                    <div class="flex items-center justify-between mb-4">
                        <div class="flex items-center gap-4">
                            <div class="flex items-center gap-2">
                                <i data-lucide="users" class="h-4 w-4 text-slate-400"></i>
                                <span class="text-xs font-bold text-slate-500 uppercase">Workers ({{ $project->workers->count() }})</span>
                            </div>
                            <div class="flex items-center gap-2 border-l border-slate-200 pl-4">
                                <i data-lucide="layers" class="h-4 w-4 text-slate-400"></i>
                                <span class="text-xs font-bold text-slate-500 uppercase">Sub-Projects ({{ $project->subProjects->count() }})</span>
                            </div>
                            <div class="flex items-center gap-2 border-l border-slate-200 pl-4">
                                <i data-lucide="clipboard-list" class="h-4 w-4 text-slate-400"></i>
                                <span class="text-xs font-bold text-slate-500 uppercase">Valuations ({{ $project->valuations_count }})</span>
                            </div>
                        </div>
                        <button type="button" 
                            data-project-id="{{ $project->id }}"
                            data-project-name="{{ $project->project_name }}"
                            data-workers="{{ json_encode($project->workers->map(function($w) { return ["id" => $w->id, "name" => ($w->user->first_name ?? 'Unknown') . " " . ($w->user->last_name ?? 'Worker'), "code" => $w->worker_code]; })) }}"
                            onclick="openManageWorkersModal(this.getAttribute('data-project-id'), this.getAttribute('data-project-name'), JSON.parse(this.getAttribute('data-workers')))" 
                            class="text-[10px] font-bold text-blue-600 hover:bg-blue-50 px-2 py-1 rounded transition flex items-center gap-1">
                            <i data-lucide="user-cog" class="h-3 w-3"></i> Manage
                        </button>
                    </div>
                    <div class="flex flex-wrap gap-2 mb-6">
                        @foreach($project->workers->take(3) as $worker)
                        @if($worker->user)
                        <div class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg bg-slate-50 border border-slate-100" title="{{ $worker->user->phone_number }}">
                            <div class="w-6 h-6 rounded-full bg-blue-600 flex items-center justify-center text-[10px] text-white font-bold">
                                {{ substr($worker->user->first_name, 0, 1) }}{{ substr($worker->user->last_name, 0, 1) }}
                            </div>
                            <span class="text-xs font-semibold text-slate-700">{{ $worker->user->first_name }} {{ $worker->user->last_name }}</span>
                        </div>
                        @else
                        <div class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg bg-red-50 border border-red-100" title="User record missing">
                            <div class="w-6 h-6 rounded-full bg-red-600 flex items-center justify-center text-[10px] text-white font-bold">?</div>
                            <span class="text-xs font-semibold text-red-700">Deleted User</span>
                        </div>
                        @endif
                        @endforeach

                        @if($project->workers->count() > 3)
                        <div id="more-workers-{{ $project->id }}" class="hidden flex flex-wrap gap-2 w-full mt-2">
                            @foreach($project->workers->slice(3) as $worker)
                            @if($worker->user)
                            <div class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg bg-slate-50 border border-slate-100" title="{{ $worker->user->phone_number }}">
                                <div class="w-6 h-6 rounded-full bg-blue-600 flex items-center justify-center text-[10px] text-white font-bold">
                                    {{ substr($worker->user->first_name, 0, 1) }}{{ substr($worker->user->last_name, 0, 1) }}
                                </div>
                                <span class="text-xs font-semibold text-slate-700">{{ $worker->user->first_name }} {{ $worker->user->last_name }}</span>
                            </div>
                            @else
                            <div class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg bg-red-50 border border-red-100">
                                <div class="w-6 h-6 rounded-full bg-red-600 flex items-center justify-center text-[10px] text-white font-bold">?</div>
                                <span class="text-xs font-semibold text-red-700">Deleted User</span>
                            </div>
                            @endif
                            @endforeach
                        </div>
                        <button type="button" onclick="toggleWorkers({{ $project->id }}, this)" class="text-[11px] font-bold text-blue-600 hover:text-blue-700 mt-2 flex items-center gap-1 transition-all py-1 px-2 rounded-lg hover:bg-blue-50">
                            <i data-lucide="plus-circle" class="h-3.5 w-3.5"></i>
                            <span>View {{ $project->workers->count() - 3 }} More</span>
                        </button>
                        @endif
                    </div>

                    @if($project->subProjects->count() > 0)
                    <div class="mb-4">
                        <div class="flex items-center gap-2 mb-2">
                            <i data-lucide="layers" class="h-3.5 w-3.5 text-indigo-400"></i>
                            <span class="text-[10px] font-bold text-indigo-500 uppercase tracking-wider">Sub-Projects</span>
                        </div>
                        <div class="flex flex-wrap gap-1.5">
                            @foreach($project->subProjects as $subProject)
                            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-[10px] font-bold bg-indigo-50 text-indigo-700 border border-indigo-100">
                                {{ $subProject->name }}
                            </span>
                            @endforeach
                        </div>
                    </div>
                    @endif

                    <div class="flex items-center justify-between pt-4 border-t border-slate-50">
                        <div class="flex items-center gap-2 text-xs text-slate-400">
                            <i data-lucide="calendar" class="h-3 w-3"></i>
                            <span>Created {{ $project->created_at->format('d M, Y') }}</span>
                        </div>
                        <div class="flex gap-2">
                            <button type="button" 
                                data-project="{{ json_encode($project) }}"
                                onclick="editProject(JSON.parse(this.getAttribute('data-project')))" 
                                class="p-2 text-slate-400 hover:text-blue-600 hover:bg-blue-50 rounded-lg transition" title="Edit Project">
                                <i data-lucide="edit-3" class="h-5 w-5"></i>
                            </button>
                            <a href="{{ route('valuation-compensations.projects.templates', $project->id) }}" target="_blank" class="p-2 text-slate-400 hover:text-blue-600 hover:bg-blue-50 rounded-lg transition" title="Generate Templates">
                                <i data-lucide="download-cloud" class="h-5 w-5"></i>
                            </a>
                            <button class="p-2 text-slate-400 hover:text-emerald-600 hover:bg-emerald-50 rounded-lg transition" title="View Report">
                                <i data-lucide="bar-chart-3" class="h-5 w-5"></i>
                            </button>
                            <button type="button"
                                data-project-id="{{ $project->id }}"
                                data-project-name="{{ $project->project_name }}"
                                data-project-code="{{ $project->project_code }}"
                                onclick="deleteProject(this.getAttribute('data-project-id'), this.getAttribute('data-project-name'), this.getAttribute('data-project-code'))"
                                class="p-2 text-slate-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition" title="Delete Project">
                                <i data-lucide="trash-2" class="h-5 w-5"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            @empty
            <div class="lg:col-span-2 py-20 text-center bg-white rounded-3xl border-2 border-dashed border-slate-200">
                <div class="w-20 h-20 bg-slate-50 rounded-full flex items-center justify-center mx-auto mb-4">
                    <i data-lucide="folder-search" class="h-10 w-10 text-slate-300"></i>
                </div>
                <h3 class="text-xl font-bold text-slate-900">No Projects Found</h3>
                <p class="text-slate-500 mt-2 max-w-md mx-auto">Create your first project to start managing structured valuation workflows and worker assignments.</p>
                <button type="button" onclick="openProjectModal()" class="mt-6 px-6 py-3 rounded-xl bg-blue-600 text-white font-bold shadow-lg shadow-blue-100 hover:bg-blue-700 transition">
                    Get Started
                </button>
            </div>
            @endforelse
        </div>
    </div>
</div>

<!-- Manage Workers Modal -->
<div id="manage-workers-modal" class="fixed inset-0 z-[101] hidden flex items-center justify-center p-4">
    <div class="fixed inset-0 bg-slate-900/40 backdrop-blur-sm transition-opacity" onclick="closeManageWorkersModal()"></div>
    <div class="relative bg-white rounded-3xl shadow-2xl w-full max-w-xl overflow-hidden transform transition-all scale-95 opacity-0 duration-300" id="manage-modal-container">
        <div class="px-8 py-6 border-b border-slate-100 flex items-center justify-between">
            <div>
                <h3 class="text-xl font-bold text-slate-900" id="manage-modal-title">Manage Project Workers</h3>
                <p class="text-xs text-slate-500 mt-0.5" id="manage-modal-subtitle"></p>
            </div>
            <button type="button" onclick="closeManageWorkersModal()" class="text-slate-400 hover:text-slate-600 p-2 hover:bg-slate-50 rounded-xl transition">
                <i data-lucide="x" class="h-6 w-6"></i>
            </button>
        </div>
        
        <div class="px-8 py-6 space-y-6 max-h-[70vh] overflow-y-auto">
            <!-- Add New Worker Section -->
            <div class="bg-blue-50/50 p-4 rounded-2xl border border-blue-100">
                <label class="block text-[10px] font-bold text-blue-600 uppercase tracking-widest mb-3">Assign Worker from Pool</label>
                <form id="add-worker-to-project-form" class="flex gap-2">
                    <select id="pool_worker_select" name="user_id" required class="flex-1 px-4 py-2.5 rounded-xl border border-blue-200 bg-white text-sm font-medium focus:ring-0 focus:border-blue-500 transition">
                        <option value="">Select a worker from pool...</option>
                        @foreach($workers as $worker)
                        <option value="{{ $worker->id }}">{{ $worker->first_name }} {{ $worker->last_name }} ({{ $worker->vfc_worker_id ?? 'VFC' }})</option>
                        @endforeach
                    </select>
                    <button type="submit" class="px-5 py-2.5 rounded-xl bg-blue-600 text-white font-bold text-sm hover:bg-blue-700 transition flex items-center gap-2">
                        <i data-lucide="plus" class="h-4 w-4"></i> Add
                    </button>
                </form>
            </div>

            <!-- Current Workers List -->
            <div>
                <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-4">Currently Assigned</label>
                <div id="current-project-workers" class="space-y-3">
                    <!-- Dynamic rows here -->
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Project Modal -->
<div id="project-modal" class="fixed inset-0 z-[100] hidden flex items-center justify-center p-4">
    <div class="fixed inset-0 bg-slate-900/40 backdrop-blur-sm transition-opacity" id="modal-overlay"></div>
    <div class="relative bg-white rounded-3xl shadow-2xl w-full max-w-4xl max-h-[90vh] flex flex-col overflow-hidden border border-slate-100 transform transition-all">
        <!-- Header -->
        <div class="px-8 py-6 border-b border-slate-100 flex items-center justify-between bg-white shrink-0">
            <div>
                <h3 class="text-xl font-bold text-slate-900" id="modal-title-text">Create Project</h3>
                <p class="text-xs text-slate-500 mt-1 uppercase tracking-widest font-semibold">Define project scope and assign workers</p>
            </div>
            <button type="button" onclick="closeModal()" class="text-slate-400 hover:text-slate-600 p-2 hover:bg-slate-50 rounded-xl transition">
                <i data-lucide="x" class="h-6 w-6"></i>
            </button>
        </div>

        <!-- Body -->
        <form id="project-form" class="flex-1 overflow-y-auto">
            @csrf
            <input type="hidden" name="id" id="project-id">
            <div class="px-8 py-8 space-y-8">
                <!-- Project Info -->
                <div class="space-y-6">
                    <div class="flex items-center gap-3 mb-2">
                        <div class="w-8 h-8 rounded-full bg-blue-50 flex items-center justify-center text-blue-600">
                            <i data-lucide="info" class="h-4 w-4"></i>
                        </div>
                        <h4 class="text-sm font-bold text-slate-700 uppercase tracking-wider">General Information</h4>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">Project Type <span class="text-red-500">*</span></label>
                            <select name="project_type" id="project_type" required class="w-full px-4 py-3 rounded-xl border border-slate-200 bg-slate-50 focus:border-blue-500 focus:bg-white transition text-sm font-medium">
                                <option value="Ministry" selected>Ministry</option>
                                <option value="LGA">LGA</option>
                                <option value="Court">Court</option>
                                <option value="Other">Other</option>
                            </select>
                            <input type="text" name="project_type_other" id="project_type_other" class="hidden mt-3 w-full px-4 py-3 rounded-xl border border-blue-200 bg-blue-50/30 text-sm font-medium" placeholder="Specify project type...">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">Project Title <span class="text-red-500">*</span></label>
                            <input type="text" name="project_name" required class="w-full px-4 py-3 rounded-xl border border-slate-200 bg-slate-50 focus:border-blue-500 focus:bg-white transition text-sm font-medium" placeholder="e.g. Zaria Road Expansion Phase 1">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">Project FileNo <span id="fileno-auto-label" class="text-slate-400 font-normal italic">(Auto-generated)</span></label>
                            <input type="text" name="project_fileno" id="project_fileno" readonly class="w-full px-4 py-3 rounded-xl border border-blue-100 bg-blue-50/50 text-sm font-bold font-mono text-blue-600 shadow-sm" placeholder="Generating...">
                            <label id="use_existing_fileno_wrap" class="flex items-center gap-2 mt-2 text-[11px] font-medium text-slate-500 cursor-pointer select-none">
                                <input type="checkbox" name="use_existing_fileno" id="use_existing_fileno" value="1" class="rounded border-slate-300 text-blue-600 focus:ring-blue-500">
                                Continue with an existing (old) file number
                            </label>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">Project Code <span class="text-slate-400 font-normal italic">(Auto-generated)</span></label>
                            <input type="text" name="project_code" id="project_code" readonly class="w-full px-4 py-3 rounded-xl border border-indigo-100 bg-indigo-50/50 text-sm font-bold font-mono text-indigo-600 shadow-sm" placeholder="Generating...">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">Our Reference <span class="text-red-500">*</span></label>
                            <input type="text" name="our_reference" id="our_reference" required class="w-full px-4 py-3 rounded-xl border border-slate-200 bg-slate-50 focus:border-blue-500 focus:bg-white transition text-sm font-medium" placeholder="e.g. LS/VAL/FGE/5KM">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">Your Reference</label>
                            <input type="text" name="your_reference" class="w-full px-4 py-3 rounded-xl border border-slate-200 bg-slate-50 focus:border-blue-500 focus:bg-white transition text-sm font-medium" placeholder="e.g. MH/VFC/2026/045">
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">Addressed To <span class="text-slate-400 font-normal italic">(Default if empty)</span></label>
                            <textarea name="addressed_to" rows="3" class="w-full px-4 py-3 rounded-xl border border-slate-200 bg-slate-50 focus:border-blue-500 focus:bg-white transition text-sm font-medium" placeholder="The Honourable Commissioner&#10;Ministry of Land and Physical&#10;Planning, Kano State">The Honourable Commissioner
Ministry of Land and Physical
Planning, Kano State</textarea>
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">Number of Sub-Projects</label>
                            <input type="number" name="number_of_sub_projects" id="number_of_sub_projects" min="0" value="0" 
                                class="w-full px-4 py-3 rounded-xl border border-slate-200 bg-slate-50 focus:border-blue-500 focus:bg-white transition text-sm font-bold"
                                placeholder="e.g. 5">
                        </div>

                        <div class="md:col-span-2">
                            <!-- Preview Container -->
                            <div id="sub_projects_preview" class="hidden mt-3 p-4 rounded-xl bg-indigo-50/50 border border-indigo-100/50">
                                <p class="text-[10px] font-bold text-indigo-600 uppercase tracking-widest flex items-center gap-2 mb-2">
                                    <i data-lucide="layers" class="h-3 w-3"></i> Sub-Projects to be Created
                                </p>
                                <div id="sub_projects_list" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
                                    <!-- Dynamic items -->
                                </div>
                            </div>
                        </div>
                        <input type="hidden" name="number_of_items" value="10">
                    </div>
                </div>

                <!-- Location -->
                <div class="pt-8 border-t border-slate-100 space-y-6">
                    <div class="flex items-center gap-3 mb-2">
                        <div class="w-8 h-8 rounded-full bg-indigo-50 flex items-center justify-center text-indigo-600">
                            <i data-lucide="map-pin" class="h-4 w-4"></i>
                        </div>
                        <h4 class="text-sm font-bold text-slate-700 uppercase tracking-wider">Location Scope</h4>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">District <span class="text-slate-400 font-normal italic">(Multi-select/Type)</span></label>
                            <select name="district[]" id="proj_district" multiple="multiple" class="vfc-select2 w-full px-4 py-3 rounded-xl border border-slate-200 bg-slate-50 focus:border-blue-500 focus:bg-white transition text-sm font-medium">
                                @foreach($districts as $district)
                                <option value="{{ $district->name }}">{{ $district->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">LGA <span class="text-slate-400 font-normal italic">(Select multiple)</span></label>
                            <select name="lga[]" id="proj_lga" multiple="multiple" class="vfc-select2 w-full px-4 py-3 rounded-xl border border-slate-200 bg-slate-50 focus:border-blue-500 focus:bg-white transition text-sm font-medium">
                                @foreach($lgas as $lga)
                                <option value="{{ $lga->LGAName }}">{{ $lga->LGAName }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">State</label>
                            <input type="text" name="state" readonly value="Kano" class="w-full px-4 py-3 rounded-xl border border-slate-200 bg-slate-100 text-sm font-bold text-slate-500 cursor-not-allowed">
                        </div>
                    </div>
                </div>

                <!-- Worker Assignment -->
                <div id="worker-assignment-section" class="pt-8 border-t border-slate-100 space-y-6">
                    <div class="flex items-center gap-3 mb-2">
                        <div class="w-8 h-8 rounded-full bg-emerald-50 flex items-center justify-center text-emerald-600">
                            <i data-lucide="users" class="h-4 w-4"></i>
                        </div>
                        <h4 class="text-sm font-bold text-slate-700 uppercase tracking-wider">Add Workers</h4>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">Number of Workers <span class="text-red-500">*</span></label>
                        <input type="number" id="num_workers" min="1" max="10" class="w-full px-4 py-3 rounded-xl border border-slate-200 bg-white shadow-inner focus:border-blue-500 transition text-sm font-bold" placeholder="How many workers?">
                        <p class="text-[10px] text-slate-400 mt-1 italic">Enter a number to generate worker assignment cards below.</p>
                    </div>

                    <div id="worker-cards" class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <!-- Dynamic cards inserted here -->
                    </div>
                </div>
            </div>
        </form>

        <!-- Footer -->
        <div class="px-8 py-6 border-t border-slate-100 flex items-center justify-end gap-3 bg-white shrink-0 shadow-inner">
            <button type="button" onclick="closeModal()" class="px-6 py-3 rounded-xl border border-slate-200 text-slate-600 font-bold hover:bg-slate-50 transition">
                Cancel
            </button>
            <button type="submit" form="project-form" id="submit-btn" class="px-8 py-3 rounded-xl bg-blue-600 text-white font-bold shadow-lg shadow-blue-100 hover:bg-blue-700 transition flex items-center gap-2">
                <span>Save Project</span>
                <i data-lucide="save" class="h-4 w-4"></i>
            </button>
        </div>
    </div>
</div>

@push('scripts')
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<style>
    .select2-container {
        width: 100% !important;
    }
    .select2-container--default .select2-selection--single {
        border-radius: 0.75rem;
        height: 46px;
        border: 1px solid #e2e8f0;
        background-color: #f8fafc;
        width: 100%;
    }
    .select2-container--default .select2-selection--single .select2-selection__rendered {
        line-height: 44px;
        padding-left: 1rem;
        font-size: 0.875rem;
        font-weight: 500;
    }
    .select2-container--default .select2-selection--single .select2-selection__arrow {
        height: 44px;
    }
    /* Select2 Custom Premium Styling */
    .select2-container--default .select2-selection--multiple {
        background-color: #f8fafc !important; /* slate-50 */
        border: 1px solid #e2e8f0 !important; /* slate-200 */
        border-radius: 0.75rem !important; /* rounded-xl */
        padding: 4px 8px !important;
        min-height: 48px !important;
        transition: all 0.2s !important;
    }
    .select2-container--default.select2-container--focus .select2-selection--multiple {
        border-color: #3b82f6 !important; /* blue-500 */
        background-color: #ffffff !important;
        box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.1) !important;
    }
    .select2-container--default .select2-selection--multiple .select2-selection__choice {
        background-color: #eff6ff !important; /* blue-50 */
        border: 1px solid #dbeafe !important; /* blue-100 */
        color: #1e40af !important; /* blue-800 */
        border-radius: 0.5rem !important;
        padding: 2px 10px !important;
        font-weight: 600 !important;
        font-size: 12px !important;
        margin-top: 6px !important;
    }
    .select2-container--default .select2-selection--multiple .select2-selection__choice__remove {
        color: #3b82f6 !important;
        margin-right: 5px !important;
        border-right: 0 !important;
    }
    .select2-container--default .select2-selection--multiple .select2-selection__choice__remove:hover {
        background-color: transparent !important;
        color: #ef4444 !important;
    }
    .select2-container--default .select2-search--inline .select2-search__field {
        margin-top: 8px !important;
        font-family: inherit !important;
    }
    #project-form input[type="text"], 
    #project-form textarea {
        text-transform: uppercase !important;
    }
</style>
<script>
    function toggleWorkers(projectId, btn) {
        const container = $(`#more-workers-${projectId}`);
        const isHidden = container.hasClass('hidden');
        
        if (isHidden) {
            container.removeClass('hidden').addClass('flex');
            $(btn).find('span').text('Show Less');
            $(btn).find('i').attr('data-lucide', 'minus-circle');
        } else {
            container.addClass('hidden').removeClass('flex');
            const count = container.children().length;
            $(btn).find('span').text(`View ${count} More`);
            $(btn).find('i').attr('data-lucide', 'plus-circle');
        }
        
        if (window.lucide) window.lucide.createIcons();
    }

    $(document).ready(function() {
        if (window.lucide) window.lucide.createIcons();

        $('#project_type').on('change', function() {
            if ($(this).val() === 'Other') {
                $('#project_type_other').removeClass('hidden').prop('required', true).focus();
            } else {
                $('#project_type_other').addClass('hidden').prop('required', false);
            }
            toggleFileNoInput();
        });

        $('#use_existing_fileno').on('change', function() {
            toggleFileNoInput();
            if ($(this).is(':checked')) $('#project_fileno').focus();
        });

        $('#proj_district').select2({
            dropdownParent: $('#project-modal'),
            width: '100%',
            placeholder: 'Select or type Districts',
            tags: true,
            tokenSeparators: [',']
        });

        $('#proj_lga').select2({
            dropdownParent: $('#project-modal'),
            width: '100%',
            placeholder: 'Select LGAs'
        });

        $('#num_workers').on('input', function() {
            generateWorkerCards($(this).val());
        });



        $('#number_of_sub_projects').on('input', function() {
            generateSubProjectInputs(parseInt($(this).val()) || 0);
        });

        $('#project-form').on('submit', function(e) {
            e.preventDefault();
            saveProject();
        });
    });

    // Holds the auto-generated FileNo fetched on modal open, so it can be
    // restored when the user toggles the Project Type back to Ministry.
    let autoGeneratedFileNo = '';

    function toggleFileNoInput() {
        const $fileno = $('#project_fileno');
        const isEdit = !!$('#project-id').val();
        const projectType = $('#project_type').val();
        const useExisting = $('#use_existing_fileno').is(':checked');

        // The "continue with existing file number" option only makes sense for
        // Ministry projects — every other type already requires a manual FileNo.
        $('#use_existing_fileno_wrap').toggleClass('hidden', projectType !== 'Ministry');

        // FileNo is manually entered when the type isn't Ministry, OR when the
        // user opts to continue with an old Ministry file number.
        const manual = (projectType && projectType !== 'Ministry') || useExisting;
        $('#fileno-auto-label').toggleClass('hidden', manual);

        if (manual) {
            $fileno.prop('readonly', false)
                   .removeClass('border-blue-100 bg-blue-50/50 text-blue-600 font-mono shadow-sm')
                   .addClass('border-slate-200 bg-slate-50 focus:border-blue-500 focus:bg-white')
                   .attr('placeholder', 'Enter existing file number...');
            // Only clear the auto value for a new project; keep an existing
            // project's manual FileNo on edit, and don't wipe what the user typed.
            if (!isEdit && $fileno.val() === autoGeneratedFileNo) {
                $fileno.val('');
                $('#our_reference').val(''); // Was backfilled from the auto FileNo
            }
        } else {
            $fileno.prop('readonly', true)
                   .removeClass('border-slate-200 bg-slate-50 focus:border-blue-500 focus:bg-white')
                   .addClass('border-blue-100 bg-blue-50/50 text-blue-600 font-mono shadow-sm')
                   .attr('placeholder', 'Generating...');
            // Restore the auto-generated FileNo when returning to auto (new projects only).
            if (!isEdit && autoGeneratedFileNo) {
                $fileno.val(autoGeneratedFileNo);
                $('#our_reference').val(autoGeneratedFileNo);
            }
        }
    }

    function generateWorkerCards(num) {
        const container = $('#worker-cards');
        const projectCode = $('#project_code').val() || 'PRJ-XXXX';
        container.empty();

        if (!num || num < 1) return;

        for (let i = 1; i <= num; i++) {
            const workerIndex = String(i).padStart(3, '0');
            const workerCode = `WRK-${projectCode}-${workerIndex}`;
            
            const card = `
                <div class="p-4 rounded-2xl bg-slate-50 border border-slate-100 space-y-4">
                    <div class="flex items-center justify-between">
                        <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Worker #${i}</span>
                        <span class="text-[11px] font-mono font-black text-blue-600 bg-blue-50 px-2 py-0.5 rounded border border-blue-100 shadow-sm">${workerCode}</span>
                    </div>
                    <div>
                        <select name="worker_assignments[${i-1}][user_id]" class="worker-select w-full" required>
                            <option value="">Select Staff</option>
                            @foreach($workers as $worker)
                            <option value="{{ $worker->id }}">{{ $worker->first_name }} {{ $worker->last_name }} ({{ $worker->phone_number }})</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            `;
            container.append(card);
        }

        $('.worker-select').select2({
            dropdownParent: $('#project-modal'),
            width: '100%'
        });

        // Exclusion logic
        $('.worker-select').on('change', function() {
            updateWorkerSelections();
        });
    }

    function updateWorkerSelections() {
        const selectedValues = [];
        $('.worker-select').each(function() {
            const val = $(this).val();
            if (val) selectedValues.push(val);
        });

        $('.worker-select').each(function() {
            const $currentSelect = $(this);
            const currentVal = $currentSelect.val();
            
            $currentSelect.find('option').each(function() {
                const $opt = $(this);
                const optVal = $opt.val();
                if (optVal && optVal !== currentVal && selectedValues.includes(optVal)) {
                    $opt.attr('disabled', 'disabled');
                } else {
                    $opt.removeAttr('disabled');
                }
            });
            
            // Note: Select2 doesn't always live-update disabled status without re-init or trigger
            $currentSelect.trigger('change.select2');
        });
    }

    // Refresh IDs if project code changes
    $('#project_code').on('input', function() {
        const num = $('#num_workers').val();
        if (num > 0) generateWorkerCards(num);
    });

    // Backfill Our Reference from FileNo
    $('#project_fileno').on('input', function() {
        $('#our_reference').val($(this).val());
    });

    function generateSubProjectInputs(count, existingNames = []) {
        const $preview = $('#sub_projects_preview');
        const $list = $('#sub_projects_list');

        if (count > 0) {
            $preview.removeClass('hidden');
            let html = '';
            for (let i = 1; i <= count; i++) {
                const name = existingNames[i-1] || '';
                html += `
                    <input type="text" name="sub_project_names[]" 
                        value="${name}" 
                        class="w-full px-3 py-2 rounded-lg border border-indigo-100 bg-white text-[11px] font-bold text-indigo-700 focus:border-indigo-400 outline-none shadow-sm transition"
                        placeholder="Sub-Project Name #${i}">`;
            }
            $list.html(html);
            if (window.lucide) window.lucide.createIcons();
        } else {
            $preview.addClass('hidden');
            $list.html('');
        }
    }

    function openProjectModal() {
        $('#project-modal').attr('style', '').removeClass('hidden').addClass('flex');
        $('#project-form')[0].reset();
        $('#worker-cards').empty();
        $('#project_type_other').addClass('hidden');
        $('#use_existing_fileno').prop('checked', false);
        toggleFileNoInput();
        $('#project_fileno').val('Generating...');
        $('#project_code').val('Generating...');
        $('#project-id').val('');
        $('#modal-title-text').text('Create Project');
        $('#submit-btn span').text('Save Project');
        $('#worker-assignment-section').show();
        
        // Fetch next available code
        $.get("{{ route('valuation-compensations.projects.next-code') }}", function(data) {
            autoGeneratedFileNo = data.fileno; // Cache for Project Type toggling
            $('#project_code').val(data.code);
            $('#project_fileno').val(data.fileno);
            $('#our_reference').val(data.fileno); // Backfill Our Reference from FileNo
            
            // Refresh worker cards if number already entered
            const num = $('#num_workers').val();
            if (num > 0) generateWorkerCards(num);
        });

        setTimeout(() => $('#modal-overlay').addClass('opacity-100'), 10);
        
        // Trigger sub-projects preview for default count of 1
        $('#number_of_sub_projects').trigger('input');
    }

    function editProject(project) {
        $('#project-modal').attr('style', '').removeClass('hidden').addClass('flex');
        $('#project-form')[0].reset();
        $('#project-id').val(project.id);
        $('#modal-title-text').text('Edit Project: ' + project.project_name);
        $('#submit-btn span').text('Update Project');
        $('#worker-assignment-section').hide(); // Don't allow changing initial workers during simple edit
        $('#use_existing_fileno').prop('checked', false);

        // Populate fields
        $('input[name="project_name"]').val(project.project_name);
        $('#project_fileno').val(project.project_fileno);
        $('#project_code').val(project.project_code);
        $('input[name="our_reference"]').val(project.our_reference);
        $('input[name="your_reference"]').val(project.your_reference);
        $('input[name="number_of_items"]').val(project.number_of_items || 10);
        $('textarea[name="addressed_to"]').val(project.addressed_to);
        
        // Populate Sub-Projects Count and Names
        const subProjects = project.sub_projects || [];
        const subProjectCount = subProjects.length || 0;
        const subProjectNames = subProjects.map(sp => sp.sub_project_name);
        
        $('#number_of_sub_projects').val(subProjectCount);
        generateSubProjectInputs(subProjectCount, subProjectNames);
        
        $('#project_type').val(project.project_type).trigger('change');
        if (project.project_type === 'Other') {
            $('#project_type_other').val(project.project_type_other);
        }
        if (project.district) {
            const districtArray = project.district.split(', ').filter(Boolean);
            $('#proj_district').val(districtArray).trigger('change');
        } else {
            $('#proj_district').val([]).trigger('change');
        }

        if (project.lga) {
            const lgaArray = project.lga.split(', ').filter(Boolean);
            $('#proj_lga').val(lgaArray).trigger('change');
        } else {
            $('#proj_lga').val([]).trigger('change');
        }

        setTimeout(() => $('#modal-overlay').addClass('opacity-100'), 10);
    }

    function closeModal() {
        $('#modal-overlay').removeClass('opacity-100');
        setTimeout(() => {
            $('#project-modal').addClass('hidden').removeClass('flex');
        }, 300);
    }

    let activeProjectId = null;

    function openManageWorkersModal(projectId, projectName, assignedWorkers) {
        activeProjectId = projectId;
        $('#manage-modal-title').text(`Manage Workers: ${projectName}`);
        $('#manage-modal-subtitle').text('Add from pool or remove existing assignments');
        
        renderProjectWorkers(assignedWorkers);

        const $modal = $('#manage-workers-modal');
        const $container = $('#manage-modal-container');
        $modal.removeClass('hidden').addClass('flex');
        setTimeout(() => {
            $container.removeClass('scale-95 opacity-0').addClass('scale-100 opacity-100');
        }, 10);
    }

    function closeManageWorkersModal() {
        const $modal = $('#manage-workers-modal');
        const $container = $('#manage-modal-container');
        $container.addClass('scale-95 opacity-0').removeClass('scale-100 opacity-100');
        setTimeout(() => {
            $modal.addClass('hidden').removeClass('flex').attr('style', 'display: none !important;');
        }, 300);
    }

    function renderProjectWorkers(workers) {
        let html = '';
        if (workers.length === 0) {
            html = '<div class="text-center py-8 text-slate-400 italic text-sm">No workers assigned yet.</div>';
        } else {
            workers.forEach(w => {
                html += `
                    <div class="flex items-center justify-between p-4 rounded-xl bg-white border border-slate-100 hover:border-slate-200 transition group">
                        <div class="flex items-center gap-3">
                            <div class="w-8 h-8 rounded-full bg-slate-100 flex items-center justify-center text-slate-600 font-bold text-[10px]">
                                ${w.name.split(' ').map(n => n[0]).join('')}
                            </div>
                            <div class="flex flex-col">
                                <span class="text-sm font-bold text-slate-700">${w.name}</span>
                                <span class="text-[10px] font-black text-emerald-600 font-mono">${w.code}</span>
                            </div>
                        </div>
                        <button type="button" onclick="removeWorkerFromProject(${activeProjectId}, ${w.id}, '${w.name}')" 
                            class="p-2 text-slate-300 hover:text-red-500 hover:bg-red-50 rounded-lg transition opacity-0 group-hover:opacity-100">
                            <i data-lucide="user-minus" class="h-4 w-4"></i>
                        </button>
                    </div>
                `;
            });
        }
        $('#current-project-workers').html(html);
        if (window.lucide) window.lucide.createIcons();
    }

    $('#add-worker-to-project-form').on('submit', function(e) {
        e.preventDefault();
        const userId = $('#pool_worker_select').val();
        if (!userId) return;

        Swal.fire({
            title: 'Assigning...',
            allowOutsideClick: false,
            didOpen: () => { Swal.showLoading(); }
        });

        $.ajax({
            url: `{{ url('valuation-compensations/projects') }}/${activeProjectId}/workers`,
            type: "POST",
            data: {
                _token: "{{ csrf_token() }}",
                user_id: userId
            },
            success: function(response) {
                if (response.success) {
                    Swal.fire('Success!', response.message, 'success').then(() => {
                        window.location.reload();
                    });
                } else {
                    Swal.fire('Warning', response.message, 'warning');
                }
            },
            error: function() {
                Swal.fire('Error!', 'Could not add worker.', 'error');
            }
        });
    });

    function removeWorkerFromProject(projectId, workerId, name) {
        Swal.fire({
            title: 'Remove Worker?',
            text: `Remove ${name} from this project?`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#ef4444',
            confirmButtonText: 'Yes, remove'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: `{{ url('valuation-compensations/projects') }}/${projectId}/workers/${workerId}`,
                    type: "DELETE",
                    data: { _token: "{{ csrf_token() }}" },
                    success: function(response) {
                        if (response.success) {
                            Swal.fire('Removed!', response.message, 'success').then(() => {
                                window.location.reload();
                            });
                        }
                    }
                });
            }
        });
    }

    /**
     * Deleting a project also deletes every valuation captured against it, so
     * the confirmation pulls the live count first and — when there is anything
     * to lose — makes the user type DELETE before it will go through.
     */
    function deleteProject(projectId, projectName, projectCode) {
        Swal.fire({
            title: 'Checking project...',
            allowOutsideClick: false,
            didOpen: () => { Swal.showLoading(); }
        });

        $.get(`{{ url('valuation-compensations/projects') }}/${projectId}/delete-summary`)
            .done(function(summary) {
                const valuations = summary.valuations_count || 0;
                const workers = summary.workers_count || 0;
                const subProjects = summary.sub_projects_count || 0;

                const lines = `
                    <div style="text-align:left;font-size:13px;line-height:1.6">
                        <p style="margin-bottom:10px">You are about to delete
                           <strong>${projectName}</strong> (${projectCode}).</p>
                        <div style="background:#fef2f2;border:1px solid #fecaca;border-radius:12px;padding:12px">
                            <p style="font-weight:700;color:#b91c1c;margin-bottom:6px">This will also delete:</p>
                            <ul style="margin:0;padding-left:18px;color:#7f1d1d">
                                <li><strong>${valuations}</strong> valuation record(s)</li>
                                <li><strong>${subProjects}</strong> sub-project(s)</li>
                                <li><strong>${workers}</strong> worker assignment(s)</li>
                            </ul>
                        </div>
                        <p style="margin-top:10px;color:#64748b">The valuations will no longer appear in the VFC
                           register, reports or the mobile app.</p>
                    </div>`;

                const confirmOptions = {
                    title: 'Delete this project?',
                    html: lines,
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#ef4444',
                    cancelButtonColor: '#64748b',
                    confirmButtonText: 'Yes, delete project',
                    reverseButtons: true
                };

                // Anything with captured valuations demands a typed confirmation.
                if (valuations > 0) {
                    confirmOptions.input = 'text';
                    confirmOptions.inputPlaceholder = 'Type DELETE to confirm';
                    confirmOptions.inputValidator = (value) => {
                        if ((value || '').trim().toUpperCase() !== 'DELETE') {
                            return 'Type DELETE to confirm this project and its valuations.';
                        }
                    };
                }

                Swal.fire(confirmOptions).then((result) => {
                    if (!result.isConfirmed) return;

                    Swal.fire({
                        title: 'Deleting...',
                        allowOutsideClick: false,
                        didOpen: () => { Swal.showLoading(); }
                    });

                    $.ajax({
                        url: `{{ url('valuation-compensations/projects') }}/${projectId}`,
                        type: 'DELETE',
                        headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                        success: function(response) {
                            if (response.success) {
                                Swal.fire('Deleted!', response.message, 'success').then(() => {
                                    window.location.reload();
                                });
                            } else {
                                Swal.fire('Error!', response.message || 'Could not delete project.', 'error');
                            }
                        },
                        error: function(xhr) {
                            Swal.fire('Error!', xhr.responseJSON?.message || 'Could not delete project.', 'error');
                        }
                    });
                });
            })
            .fail(function() {
                Swal.fire('Error!', 'Could not load the project details.', 'error');
            });
    }

    function saveProject() {
        const projectId = $('#project-id').val();
        const url = projectId 
            ? `{{ url('valuation-compensations/projects') }}/${projectId}`
            : "{{ route('valuation-compensations.projects.store') }}";
        const method = projectId ? "PUT" : "POST";

        Swal.fire({
            title: projectId ? 'Updating Project...' : 'Saving Project...',
            allowOutsideClick: false,
            didOpen: () => { Swal.showLoading(); }
        });

        $.ajax({
            url: url,
            type: method,
            data: $('#project-form').serialize(),
            success: function(response) {
                if (response.success) {
                    Swal.fire('Success!', response.message, 'success').then(() => {
                        window.location.reload();
                    });
                }
            },
            error: function(xhr) {
                let msg = 'Something went wrong.';
                if (xhr.status === 422) {
                    const errors = xhr.responseJSON.errors;
                    msg = Object.values(errors).flat().join('<br>');
                }
                Swal.fire('Error!', msg, 'error');
            }
        });
    }
</script>
@endpush
@endsection
