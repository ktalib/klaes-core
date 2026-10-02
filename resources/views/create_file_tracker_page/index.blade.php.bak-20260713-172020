@extends('layouts.app')
@section('page-title')
    {{ __('Create File Tracker') }}
@endsection
{{-- ikkdt --}}
@section('content')
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css">
    <link rel="stylesheet" href="{{ asset('css/create-file-tracker.css') }}">

    <div class="flex-1 overflow-y-auto overflow-x-hidden">
        <!-- Header --> 
        @include('admin.header')
        @if(in_array(($module ?? ''), ['kangis', 'new_kangis']))
        <div class="bg-gradient-to-r from-orange-600 via-orange-500 to-orange-700 px-6 py-3 flex items-center gap-3 shadow-sm">
            <i data-lucide="map" class="h-5 w-5 text-white shrink-0"></i>
            <div class="flex items-center gap-2">
            <span class="text-white font-bold text-sm uppercase tracking-widest">{{ ($module ?? '') === 'new_kangis' ? 'NEW KANGIS' : 'KANGIS' }}</span>
                <span class="text-orange-100 text-sm">·</span>
                <span class="text-white text-sm font-medium">Digital Archive</span>
                <span class="text-orange-100 text-sm">·</span>
                <span class="text-orange-100 text-sm">Log a File</span>
            </div>
        </div>
        @elseif(($module ?? '') === 'sltr')
        <div class="bg-gradient-to-r from-lime-600 via-green-500 to-lime-600 px-6 py-3 flex items-center gap-3 shadow-sm">
            <i data-lucide="landmark" class="h-5 w-5 text-white shrink-0"></i>
            <div class="flex items-center gap-2">
                <span class="text-white font-bold text-sm uppercase tracking-widest">Systematic Land Titling and Registration</span>
                <span class="text-lime-100 text-sm">·</span>
                <span class="text-white text-sm font-medium">e-Registry</span>
                <span class="text-lime-100 text-sm">·</span>
                <span class="text-lime-100 text-sm">Log a File</span>
            </div>
        </div>
        @elseif(($module ?? '') === 'st')
        <div class="bg-gradient-to-r from-blue-600 via-sky-500 to-blue-700 px-6 py-3 flex items-center gap-3 shadow-sm">
            <i data-lucide="building-2" class="h-5 w-5 text-white shrink-0"></i>
            <div class="flex items-center gap-2">
                <span class="text-white font-bold text-sm uppercase tracking-widest">Sectional Titling</span>
                <span class="text-blue-100 text-sm">·</span>
                <span class="text-white text-sm font-medium">e-Registry</span>
                <span class="text-blue-100 text-sm">·</span>
                <span class="text-blue-100 text-sm">Log a File</span>
            </div>
        </div>
        @elseif(($module ?? '') === 'dgis')
        <div class="bg-gradient-to-r from-indigo-500 via-blue-500 to-indigo-600 px-6 py-3 flex items-center gap-3 shadow-sm">
            <i data-lucide="user-cog" class="h-5 w-5 text-white shrink-0"></i>
            <div class="flex items-center gap-2">
                <span class="text-white font-bold text-sm uppercase tracking-widest">Director GIS</span>
                <span class="text-indigo-100 text-sm">·</span>
                <span class="text-white text-sm font-medium">File Approvals</span>
                <span class="text-indigo-100 text-sm">·</span>
                <span class="text-indigo-100 text-sm">Recommendation</span>
            </div>
        </div>
        @elseif(($module ?? '') === 'dg')
        <div class="bg-gradient-to-r from-emerald-600 via-green-500 to-emerald-600 px-6 py-3 flex items-center gap-3 shadow-sm">
            <i data-lucide="shield-check" class="h-5 w-5 text-white shrink-0"></i>
            <div class="flex items-center gap-2">
                <span class="text-white font-bold text-sm uppercase tracking-widest">Director General, KANGIS</span>
                <span class="text-emerald-100 text-sm">·</span>
                <span class="text-white text-sm font-medium">File Approvals</span>
                <span class="text-emerald-100 text-sm">·</span>
                <span class="text-emerald-100 text-sm">Approval</span>
            </div>
        </div>
        @elseif(($module ?? '') === 'dciv')
        <div class="bg-gradient-to-r from-emerald-900 via-emerald-800 to-emerald-950 px-6 py-3 flex items-center gap-3 shadow-sm">
            <i data-lucide="layers" class="h-5 w-5 text-white shrink-0"></i>
            <div class="flex items-center gap-2">
                <span class="text-white font-bold text-sm uppercase tracking-widest">DEPARTMENT OF COMPLAINT INVESTIGATION AND VERIFICATION</span>
                <span class="text-emerald-100 text-sm">·</span>
                <span class="text-white text-sm font-medium">e-Registry</span>
                <span class="text-emerald-100 text-sm">·</span>
                <span class="text-emerald-100 text-sm">Log a File</span>
            </div>
        </div>
        @elseif(($module ?? '') === 'survey')
        <div class="bg-gradient-to-r from-pink-600 via-pink-500 to-pink-700 px-6 py-3 flex items-center gap-3 shadow-sm" style="background-image: linear-gradient(to right, #db2777, #ec4899, #be185d);">
            <i data-lucide="compass" class="h-5 w-5 text-white shrink-0"></i>
            <div class="flex items-center gap-2">
                <span class="text-white font-bold text-sm uppercase tracking-widest">Survey</span>
                <span class="text-pink-100 text-sm">·</span>
                <span class="text-white text-sm font-medium">Digital Archive</span>
                <span class="text-pink-100 text-sm">·</span>
                <span class="text-pink-100 text-sm">Log a File</span>
            </div>
        </div>
        @elseif(in_array(strtolower($module ?? ''), ['cadastral']))
        <div class="bg-gradient-to-r from-[#6f4e37] via-[#8B4513] to-[#5c4033] px-6 py-3 flex items-center gap-3 shadow-sm">
            <i data-lucide="compass" class="h-5 w-5 text-white shrink-0"></i>
            <div class="flex items-center gap-2">
                <span class="text-white font-bold text-sm uppercase tracking-widest">CADASTRAL DEPARTMENT</span>
                <span class="text-amber-100 text-sm">·</span>
                <span class="text-white text-sm font-medium">e-Registry</span>
                <span class="text-amber-100 text-sm">·</span>
                <span class="text-amber-100 text-sm">Log a File</span>
            </div>
        </div>
        @elseif(in_array(strtolower($module ?? ''), ['digital_request', 'digital-request']))
        @if(request()->has('kangis'))
        <div class="bg-gradient-to-r from-orange-600 via-orange-500 to-orange-700 px-6 py-3 flex items-center gap-3 shadow-sm">
            <i data-lucide="send" class="h-5 w-5 text-white shrink-0"></i>
            <div class="flex items-center gap-2">
                <span class="text-white font-bold text-sm uppercase tracking-widest">KANGIS</span>
                <span class="text-orange-100 text-sm">·</span>
                <span class="text-white text-sm font-medium">Digital File Request</span>
                <span class="text-orange-100 text-sm">·</span>
                <span class="text-orange-100 text-sm">Request a Physical File</span>
            </div>
        </div>
        @else
        <div class="bg-gradient-to-r from-[#450a0a] via-[#6b1010] to-[#450a0a] px-6 py-3 flex items-center gap-3 shadow-sm">
            <i data-lucide="send" class="h-5 w-5 text-white shrink-0"></i>
            <div class="flex items-center gap-2">
                <span class="text-white font-bold text-sm uppercase tracking-widest">Digital File Request</span>
                <span class="text-red-200 text-sm">·</span>
                <span class="text-white text-sm font-medium">e-Registry</span>
                <span class="text-red-200 text-sm">·</span>
                <span class="text-red-200 text-sm">Request a Physical File</span>
            </div>
        </div>
        @endif
        @else
        <div class="bg-gradient-to-r from-red-950 via-red-800 to-red-950 px-6 py-3 flex items-center gap-3 shadow-sm">
            <i data-lucide="file-text" class="h-5 w-5 text-white shrink-0"></i>
            <div class="flex items-center gap-2">
                <span class="text-white font-bold text-sm uppercase tracking-widest">LAND DEPARTMENT</span>
                <span class="text-red-100 text-sm">·</span>
                <span class="text-white text-sm font-medium">e-Registry</span>
                <span class="text-red-100 text-sm">·</span>
                <span class="text-red-100 text-sm">Log a File</span>
            </div>
        </div>
        @endif
        <!-- Dashboard Content-->

  
        <div class="p-6">

            <div class="container mx-auto py-6 space-y-6">



                <div id="page-toast-container"
                    class="fixed inset-x-0 top-24 z-50 flex flex-col items-center gap-2 pointer-events-none"></div>

                    <!-- Page Header  for dg and dg gis -->

                {{-- ── Mode Switcher Card (base create-file-tracker + KANGIS + digital_request) ── --}}
                @php
                    $isDfr = (($module ?? '') === 'digital_request');
                    // KANGIS context = on the KANGIS page, or on a KANGIS-flavoured Digital Request (?url=digital_request&kangis).
                    $isKangisCtx = (($module ?? '') === 'kangis') || ($isDfr && request()->has('kangis'));
                    // On KANGIS, "Manual Request" returns to the KANGIS page; elsewhere to the base page.
                    $manualUrl = $isKangisCtx
                        ? route('create-file-tracker.index', ['url' => 'kangis'])
                        : route('create-file-tracker.index');
                    // From KANGIS, Digital Request keeps the KANGIS name + colour via the kangis flag.
                    $digitalUrl = $isKangisCtx
                        ? route('create-file-tracker.index', ['url' => 'digital_request', 'kangis' => 1])
                        : route('create-file-tracker.index', ['url' => 'digital_request']);
                    $digitalActiveClass = ($isDfr && request()->has('kangis'))
                        ? 'bg-orange-600 text-white shadow-sm'
                        : 'bg-[#450a0a] text-white shadow-sm';
                @endphp
                @if(in_array(($module ?? ''), ['', 'kangis', 'digital_request']))
                <div class="bg-white border border-gray-200 rounded-xl px-5 py-3 flex items-center gap-3 shadow-sm">
                    <span class="text-[11px] font-semibold text-gray-400 uppercase tracking-wider">Request Mode</span>
                    <div class="w-px h-5 bg-gray-200"></div>
                    <div class="inline-flex items-center rounded-xl border border-gray-200 bg-gray-100 p-0.5 gap-0.5">
                        <a href="{{ $manualUrl }}"
                           class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-[10px] text-xs font-semibold transition-all
                                  {{ !$isDfr
                                      ? 'bg-white text-gray-800 shadow-sm border border-gray-200'
                                      : 'text-gray-400 hover:text-gray-600 hover:bg-white/60' }}"
                           title="Manual Request">
                            <i data-lucide="file-text" class="h-3.5 w-3.5"></i>
                            Manual Request
                        </a>
                        <a href="{{ $digitalUrl }}"
                           class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-[10px] text-xs font-semibold transition-all
                                  {{ $isDfr
                                      ? $digitalActiveClass
                                      : 'text-gray-400 hover:text-gray-600 hover:bg-white/60' }}"
                           title="Digital File Request">
                            <i data-lucide="monitor" class="h-3.5 w-3.5"></i>
                            Digital Request
                        </a>
                    </div>

                    <div class="ml-auto flex items-center gap-2">
                        {{-- Quick Search is now a standalone page (Digital Archive → Quick Search). Kept here, commented out.
                        <button type="button" class="js-qs-open inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold text-indigo-700 bg-indigo-50 border border-indigo-200 hover:bg-indigo-100 transition-all"
                            title="Quick Search & File Location">
                            <i data-lucide="map-pin" class="h-3.5 w-3.5"></i>
                            Quick Search &amp; Location
                        </button>
                        --}}
                        {{-- Hidden: Send File Search Request is now handled elsewhere (Quick Search / SCB flow).
                        @unless($isKangisCtx)
                        <button type="button" class="js-fr-open inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold text-white bg-blue-600 border border-blue-600 hover:bg-blue-700 transition-all"
                            title="Send a File Request to SCB Monitors">
                            <i data-lucide="send" class="h-3.5 w-3.5"></i>
                            Send File Search Request
                        </button>
                        @endunless
                        --}}
                    </div>
                </div>
                @endif

                <header class="bg-white border-b border-gray-200 px-6 py-4">
                    @php
                        $currentModule = $module ?? '';
                        $isModuleNav = in_array($currentModule, ['kangis', 'dgis', 'dg', 'new_kangis']);

                        $navBaseClass = 'inline-flex items-center px-4 py-2 border rounded-md shadow-sm text-sm font-medium transition-all duration-150';
                        $navActiveExtra = 'ring-2 ring-offset-1 shadow-md scale-[1.02]';
                        $navStyles = [
                            'kangis'     => ['active' => 'border-blue-600 text-white bg-blue-600 hover:bg-blue-700 ring-blue-300',           'idle' => 'border-blue-300 text-blue-700 bg-blue-50 hover:bg-blue-100'],
                            'new_kangis' => ['active' => 'border-emerald-600 text-white bg-emerald-600 hover:bg-emerald-700 ring-emerald-300', 'idle' => 'border-emerald-300 text-emerald-700 bg-emerald-50 hover:bg-emerald-100'],
                            'dgis'       => ['active' => 'border-indigo-600 text-white bg-indigo-600 hover:bg-indigo-700 ring-indigo-300',     'idle' => 'border-indigo-300 text-indigo-700 bg-indigo-50 hover:bg-indigo-100'],
                            'dg'         => ['active' => 'border-emerald-600 text-white bg-emerald-600 hover:bg-emerald-700 ring-emerald-300', 'idle' => 'border-emerald-300 text-emerald-700 bg-emerald-50 hover:bg-emerald-100'],
                            'dciv'       => ['active' => 'border-slate-700 text-white bg-slate-700 hover:bg-slate-800 ring-slate-300',         'idle' => 'border-slate-300 text-slate-700 bg-slate-50 hover:bg-slate-100'],
                            'st'         => ['active' => 'border-blue-600 text-white bg-blue-600 hover:bg-blue-700 ring-blue-300',           'idle' => 'border-blue-300 text-blue-700 bg-blue-50 hover:bg-blue-100'],
                        ];
                        $navClass = function ($key) use ($currentModule, $navBaseClass, $navActiveExtra, $navStyles) {
                            $tone = $navStyles[$key] ?? ['active' => '', 'idle' => ''];
                            $isActive = $currentModule === $key;
                            return $navBaseClass . ' ' . ($isActive ? $tone['active'] . ' ' . $navActiveExtra : $tone['idle']);
                        };

                        $crossRegistryUser = auth()->user();
                        $crossRegistryAssignRoles = collect(explode(',', (string) ($crossRegistryUser->assign_role ?? '')))
                            ->map(fn ($r) => trim($r))
                            ->filter()
                            ->values();
                        $canInitiateCrossRegistry = $crossRegistryUser && (
                            ($crossRegistryUser->type ?? null) === 'super admin'
                            || $crossRegistryAssignRoles->contains('Supper Admin')
                            || $crossRegistryAssignRoles->contains('DG')
                            || $crossRegistryAssignRoles->contains('DGIS')
                        );

                        $isKangisGroup = in_array($currentModule, ['kangis', 'new_kangis']);
                        $isDgGroup     = in_array($currentModule, ['dgis', 'dg']);
                        
                        // Modules that participate in the KANGIS/Approval cross-registry workflow
                        $eligibleModules = ['kangis', 'new_kangis', 'dgis', 'dg'];
                        $isEligible      = in_array($currentModule, $eligibleModules);

                        // Privileged users see BOTH header rows ONLY when in an eligible context
                        $showBothGroups  = $canInitiateCrossRegistry && $isEligible;
                        $showKangisGroup = $showBothGroups || $isKangisGroup;
                        
                        // Show DG group row if in DG/DGIS, if both groups are allowed, 
                        // or as a fallback standard row for other modules (SLTR, ST)
                        $showDgGroup     = $showBothGroups || $isDgGroup || !$isEligible;
                    @endphp

                    {{-- Header row 1 — DG group: Back · Reset · Preview · DGIS · DG --}}
                    @if($showDgGroup)
                        <div class="flex items-center justify-between flex-wrap gap-3">
                            <div class="flex items-center gap-2 flex-wrap">
                                @if($isModuleNav)
                                    <a href="{{ route('create-file-tracker.index') }}"
                                        id="smartBackBtn"
                                        data-fallback-url="{{ route('create-file-tracker.index') }}"
                                        class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50"
                                        title="Back">
                                        <i data-lucide="arrow-left" class="h-4 w-4 mr-2"></i>
                                        <span data-back-label>Back</span>
                                    </a>
                                @endif

                                <button id="resetBtn"
                                    class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                                    <i data-lucide="refresh-cw" class="h-4 w-4 mr-2"></i>
                                    Reset Form
                                </button>
                                <button id="previewBtn"
                                    class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                                    <i data-lucide="eye" class="h-4 w-4 mr-2"></i>
                                    Preview
                                </button>

                                @if(in_array(strtolower($module ?? ''), ['digital_request','digital-request']))
                                <a href="{{ $isKangisCtx ? route('digital-request.index', ['kangis' => 1]) : route('digital-request.index') }}"
                                    class="inline-flex items-center px-4 py-2 border border-[#450a0a] rounded-md shadow-sm text-sm font-medium text-[#450a0a] bg-white hover:bg-red-50 transition-colors">
                                    <i data-lucide="list" class="h-4 w-4 mr-2"></i>
                                    View Requests
                                </a>
                                @endif

                                @if($isEligible)
                                <a href="{{ route('create-file-tracker.index', ['url' => 'dgis']) }}"
                                    class="{{ $navClass('dgis') }}"
                                    @if($currentModule === 'dgis') aria-current="page" @endif>
                                    <i data-lucide="user-cog" class="h-4 w-4 mr-2"></i>
                                    DGIS
                                    @if($currentModule === 'dgis')
                                        <span class="ml-2 inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider bg-amber-300 text-amber-900 border border-amber-400 shadow-sm animate-pulse">Active</span>
                                    @endif
                                </a>
                                <a href="{{ route('create-file-tracker.index', ['url' => 'dg']) }}"
                                    class="{{ $navClass('dg') }}"
                                    @if($currentModule === 'dg') aria-current="page" @endif>
                                    <i data-lucide="shield-check" class="h-4 w-4 mr-2"></i>
                                    DG
                                    @if($currentModule === 'dg')
                                        <span class="ml-2 inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider bg-amber-300 text-amber-900 border border-amber-400 shadow-sm animate-pulse">Active</span>
                                    @endif
                                </a>
                                @endif
                            </div>

                            @if($isModuleNav)
                                <img src="{{ asset('assets/logo/kangis.jpg') }}" alt="KANGIS" class="h-10 w-auto object-contain opacity-90">
                            @endif
                        </div>
                    @endif

                    {{-- Header row 2 — KANGIS group: KANGIS · KANGIS New Files · Request File (KANGIS only, privileged) --}}
                    @if($showKangisGroup)
                        <div class="flex items-center justify-between flex-wrap gap-3 {{ $showDgGroup ? 'mt-3 pt-3 border-t border-gray-100' : '' }}">
                            <div class="flex items-center gap-2 flex-wrap">
                                {{-- Smart Back rendered here only when row 1 (DG group) is hidden --}}
                                @if($isModuleNav && !$showDgGroup)
                                    <a href="{{ route('create-file-tracker.index') }}"
                                        id="smartBackBtn"
                                        data-fallback-url="{{ route('create-file-tracker.index') }}"
                                        class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50"
                                        title="Back">
                                        <i data-lucide="arrow-left" class="h-4 w-4 mr-2"></i>
                                        <span data-back-label>Back</span>
                                    </a>
                                @endif

                                @unless($showDgGroup)
                                    <button id="resetBtn"
                                        class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                                        <i data-lucide="refresh-cw" class="h-4 w-4 mr-2"></i>
                                        Reset Form
                                    </button>
                                    <button id="previewBtn"
                                        class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                                        <i data-lucide="eye" class="h-4 w-4 mr-2"></i>
                                        Preview
                                    </button>
                                @endunless

                                <span class="hidden sm:inline-block w-px h-6 bg-gray-200 mx-1"></span>

                                @unless($isDgGroup)
                                    <a href="{{ route('create-file-tracker.index', ['url' => 'kangis']) }}"
                                        class="{{ $navClass('kangis') }}"
                                        @if($currentModule === 'kangis') aria-current="page" @endif>
                                        <i data-lucide="building-2" class="h-4 w-4 mr-2"></i>
                                        Track Existing File
                                        @if($currentModule === 'kangis')
                                            <span class="ml-2 inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider bg-amber-300 text-amber-900 border border-amber-400 shadow-sm animate-pulse">Active</span>
                                        @endif
                                    </a>
                                    <a href="{{ route('create-file-tracker.index', ['url' => 'new_kangis']) }}"
                                        class="{{ $navClass('new_kangis') }}"
                                        @if($currentModule === 'new_kangis') aria-current="page" @endif>
                                        <i data-lucide="star" class="h-4 w-4 mr-2"></i>
                                        Track New File
                                        @if($currentModule === 'new_kangis')
                                            <span class="ml-2 inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider bg-amber-300 text-amber-900 border border-amber-400 shadow-sm animate-pulse">Active</span>
                                        @endif
                                    </a>
                                @endunless

                                @if(in_array($currentModule, ['kangis', 'dgis', 'dg']) && $canInitiateCrossRegistry)
                                    {{-- Request Land File moved to the left sidebar (Director GIS Recommendation / DG Approval submenus). --}}
                                    {{-- Hidden stub kept so JS handlers that programmatically click #requestFileBtn (e.g. ?action=request-land) still work. --}}
                                    <button id="requestFileBtn" type="button" class="hidden" aria-hidden="true" tabindex="-1"
                                        title="Initiate a cross-registry file request (DG / DGIS / Supper Admin only)">
                                        Request Land File
                                    </button>
                                @endif
                            </div>

                            @if($isModuleNav && !$showDgGroup)
                                <img src="{{ asset('assets/logo/kangis.jpg') }}" alt="KANGIS" class="h-10 w-auto object-contain opacity-90">
                            @endif
                        </div>
                    @endif
                </header>


                <div class="flex flex-1 p-6">
                    <!-- Two-Tab System for Create File Tracker -->
                    <div class="w-full">
                        <!-- Tab Navigation -->
                        <div class="bg-white rounded-t-lg border border-gray-200">
                            <div class="border-b border-gray-200">
                                <nav class="-mb-px flex space-x-8 px-6" aria-label="Tabs">
                                    @if(!in_array($module ?? '', ['dgis', 'dg']))
                                    <button id="tab-create"
                                        class="main-tab {{ in_array($module ?? '', ['dgis', 'dg']) ? '' : 'active border-b-2 border-blue-500 text-blue-600' }} border-b-2 border-transparent py-4 px-1 text-sm font-medium text-gray-500 hover:text-gray-700 hover:border-gray-300"
                                        data-tab="create">
                                        <i data-lucide="file-plus" class="h-4 w-4 inline mr-2"></i>
                                        Create File Tracker
                                    </button>
                                    @endif
                                    <button id="tab-logs"
                                        class="main-tab {{ in_array($module ?? '', ['dgis', 'dg']) ? 'active border-b-2 border-blue-500 text-blue-600' : '' }} border-b-2 border-transparent py-4 px-1 text-sm font-medium text-gray-500 hover:text-gray-700 hover:border-gray-300"
                                        data-tab="logs"
                                        @if(in_array($module ?? '', ['dgis', 'dg'])) data-log-tab="logs" @endif>
                                        <i data-lucide="database" class="h-4 w-4 inline mr-2"></i>
                                        {{ in_array($module ?? '', ['dgis', 'dg']) ? 'Track Existing File' : 'File Tracker Log' }}
                                        <span id="log-count"
                                            class="ml-2 bg-blue-100 text-blue-800 py-1 px-2 rounded-full text-xs">0</span>
                                    </button>
                                    @if(in_array($module ?? '', ['dgis', 'dg']))
                                        <button id="tab-track-new"
                                            class="main-tab border-b-2 border-transparent py-4 px-1 text-sm font-medium text-gray-500 hover:text-gray-700 hover:border-gray-300"
                                            data-tab="logs"
                                            data-log-tab="track-new">
                                            <i data-lucide="star" class="h-4 w-4 inline mr-2"></i>
                                            Track New File
                                            <span id="track-new-count"
                                                class="ml-2 bg-emerald-100 text-emerald-800 py-1 px-2 rounded-full text-xs">0</span>
                                        </button>
                                        <button id="tab-request-land"
                                            class="main-tab border-b-2 border-transparent py-4 px-1 text-sm font-medium text-gray-500 hover:text-gray-700 hover:border-gray-300"
                                            data-tab="logs"
                                            data-log-tab="request-land">
                                            <i data-lucide="send" class="h-4 w-4 inline mr-2"></i>
                                            Request Land File
                                            <span id="request-land-count"
                                                class="ml-2 bg-purple-100 text-purple-800 py-1 px-2 rounded-full text-xs">0</span>
                                        </button>
                                    @endif
                                    @if(false) {{-- Registry Checkout hidden for now --}}
                                    @if(in_array($module ?? '', ['kangis', 'sltr', 'st']))
                                    <button id="tab-checkout"
                                        class="main-tab border-b-2 border-transparent py-4 px-1 text-sm font-medium text-gray-500 hover:text-amber-600 hover:border-amber-400"
                                        data-tab="checkout">
                                        <i data-lucide="shield-check" class="h-4 w-4 inline mr-2"></i>
                                        Registry Checkout
                                        <span id="checkout-pending-count"
                                            class="ml-2 bg-amber-100 text-amber-800 py-1 px-2 rounded-full text-xs hidden">0</span>
                                    </button>
                                    @endif
                                    @endif
                                </nav>
                            </div>
                        </div>

                        <!-- Tab Content -->
                        <div class="bg-white rounded-b-lg border-l border-r border-b border-gray-200 shadow-sm">
                            <!-- Create File Tracker Tab -->
                            @if(!in_array($module ?? '', ['dgis', 'dg']))
                            <div id="content-create" class="main-tab-content active p-6">

                                {{-- ── SLTR: External / In-process tracking scope ──────────────── --}}
                                @if(($module ?? '') === 'sltr')
                                <div id="sltr-scope-toggle" class="rounded-lg border shadow-sm p-4 mb-6 overflow-hidden transition-all duration-300 border-green-200 bg-gradient-to-br from-green-50 via-emerald-50 to-white">
                                    <div id="sltr-scope-accent-bar" class="h-1 -mx-4 -mt-4 mb-4 bg-gradient-to-r from-green-600 via-emerald-500 to-green-400 transition-all duration-300"></div>
                                    <div class="flex flex-col sm:flex-row sm:items-center gap-3">
                                        <div class="flex items-center gap-3 shrink-0">
                                            <div id="sltr-scope-icon-wrap" class="w-10 h-10 rounded-full bg-green-100 flex items-center justify-center transition-colors">
                                                <i id="sltr-scope-icon" data-lucide="route" class="h-5 w-5 text-green-600"></i>
                                            </div>
                                            <div>
                                                <label for="sltr-tracking-scope" class="block text-sm font-semibold text-gray-800">Tracking Scope</label>
                                                <p class="text-xs text-gray-500">Where is this file headed?</p>
                                            </div>
                                        </div>

                                        <select id="sltr-tracking-scope" onchange="sltrSetScope(this.value)"
                                            class="w-full sm:w-52 px-3 py-2 border rounded-md shadow-sm font-medium focus:outline-none focus:ring-2 transition-colors border-green-400 bg-green-50 text-green-800 focus:ring-green-500 focus:border-green-500">
                                            <option value="External" selected>External</option>
                                            <option value="Internal">In-process</option>
                                        </select>

                                        <p id="sltr-external-pill" class="flex items-center gap-1.5 text-xs text-green-700 sm:ml-1">
                                            <i data-lucide="info" class="h-3.5 w-3.5 text-green-500 shrink-0"></i>
                                            Standard workflow · file logged out to a destination department
                                        </p>
                                        <p id="sltr-internal-pill" class="hidden flex items-center gap-1.5 text-xs text-indigo-700 sm:ml-1">
                                            <i data-lucide="info" class="h-3.5 w-3.5 text-indigo-500 shrink-0"></i>
                                            Internal SLTR movement · file routed to an SLTR section
                                        </p>
                                    </div>
                                </div>
                                @endif

                                {{-- ===== DIGITAL REQUEST: Steps Illustration ===== --}}
                                @if(in_array(strtolower($module ?? ''), ['digital_request','digital-request']))

                                {{-- ── Physical / Digital toggle ──────────────────────────────── --}}
                                <div class="flex items-center gap-2 mb-4" id="dfr-type-toggle">
                                    <span class="text-xs font-semibold text-gray-500 uppercase tracking-wide mr-1">Request Type</span>
                                    <button type="button" id="dfr-btn-physical"
                                        onclick="dfrSetType('Physical')"
                                        class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl border-2 text-sm font-semibold transition-all
                                               border-green-500 bg-green-600 text-white shadow-sm">
                                        <i data-lucide="package" class="h-4 w-4"></i> Physical File
                                    </button>
                                    <button type="button" id="dfr-btn-digital"
                                        onclick="dfrSetType('Digital')"
                                        class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl border-2 text-sm font-semibold transition-all
                                               border-gray-200 bg-white text-gray-500 hover:border-blue-300 hover:text-blue-600">
                                        <i data-lucide="monitor" class="h-4 w-4"></i> Digital Access
                                    </button>
                                    <input type="hidden" id="dfr-request-type" name="dfr_request_type" value="Physical">
                                    {{-- Digital info pill --}}
                                    <span id="dfr-digital-pill" class="hidden ml-2 inline-flex items-center gap-1 text-xs text-blue-700 bg-blue-50 border border-blue-200 px-2.5 py-1 rounded-full">
                                        <i data-lucide="info" class="h-3 w-3 text-blue-500"></i>
                                        Temporary copy · view-only · 5 working days
                                    </span>
                                </div>

                                <div class="mb-6">

                                    {{-- Header card --}}
                                    <div id="dfr-illustration-card" class="rounded-xl border border-green-200 bg-gradient-to-br from-green-50 via-emerald-50 to-white shadow-sm overflow-hidden transition-all duration-300">

                                        {{-- Top accent bar --}}
                                        <div id="dfr-accent-bar" class="h-1 w-full bg-gradient-to-r from-green-600 via-emerald-500 to-green-400 transition-all duration-300"></div>

                                        <div class="px-6 pt-5 pb-4">
                                            {{-- Title row --}}
                                            <div class="flex items-center justify-between flex-wrap gap-3 mb-5">
                                                <div class="flex items-center gap-3">
                                                    <div id="dfr-header-icon-wrap" class="flex-shrink-0 w-10 h-10 rounded-full bg-green-600 flex items-center justify-center shadow transition-all duration-300">
                                                        <i id="dfr-header-icon" data-lucide="send" class="h-5 w-5 text-white"></i>
                                                    </div>
                                                    <div>
                                                        <h2 id="dfr-card-title" class="text-base font-bold text-green-900 leading-tight transition-colors duration-300">Digital File Request</h2>
                                                        <p id="dfr-card-subtitle" class="text-xs text-green-600 transition-colors duration-300">Request a physical file to be sent to your office</p>
                                                    </div>
                                                </div>
                                                <a href="{{ $isKangisCtx ? '/digital-request?kangis=1' : '/digital-request' }}"
                                                    class="inline-flex items-center gap-1.5 text-xs font-medium text-green-700 bg-green-100 hover:bg-green-200 border border-green-200 px-3 py-1.5 rounded-full transition">
                                                    <i data-lucide="layout-list" class="h-3.5 w-3.5"></i>
                                                    View All Requests
                                                </a>
                                            </div>

                                            {{-- Steps row --}}
                                            <div id="dfr-steps-row" class="relative flex items-start gap-0">

                                                {{-- Connector line (behind steps) --}}
                                                <div id="dfr-connector" class="absolute top-5 left-[calc(10%+20px)] right-[calc(10%+20px)] h-0.5 bg-green-200 z-0 transition-colors duration-300"></div>

                                                @php
                                                    $drSteps = [
                                                        ['n'=>1,'icon'=>'search',  'label'=>'Search File',       'desc'=>'Enter file number to look up',             'color'=>'bg-green-500','ring'=>'ring-green-200','text'=>'text-green-700','state'=>'done'],
                                                        ['n'=>2,'icon'=>'file-check','label'=>'Details Auto-filled','desc'=>'Title, your office & officer pre-loaded', 'color'=>'bg-green-500','ring'=>'ring-green-200','text'=>'text-green-700','state'=>'done'],
                                                        ['n'=>3,'icon'=>'send',    'label'=>'Send Request',       'desc'=>'Click "Send Request" to submit',            'color'=>'bg-green-600','ring'=>'ring-green-400','text'=>'text-green-800','state'=>'current'],
                                                        ['n'=>4,'icon'=>'bell',    'label'=>'Await Approval',     'desc'=>'Receiver approves via notification',        'color'=>'bg-gray-300', 'ring'=>'ring-gray-100', 'text'=>'text-gray-500','state'=>'upcoming'],
                                                        ['n'=>5,'icon'=>'archive', 'label'=>'File Dispatched',    'desc'=>'File is sent to your office',               'color'=>'bg-gray-300', 'ring'=>'ring-gray-100', 'text'=>'text-gray-500','state'=>'upcoming'],
                                                    ];
                                                @endphp

                                                @foreach($drSteps as $step)
                                                <div class="relative z-10 flex flex-col items-center flex-1 px-1" data-step="{{ $step['n'] }}">

                                                    {{-- Step circle --}}
                                                    <div class="dfr-step-circle w-10 h-10 rounded-full {{ $step['color'] }} ring-4 {{ $step['ring'] }} flex items-center justify-center shadow-sm transition-all
                                                        @if($step['state']==='current') ring-green-300 shadow-green-200 shadow-md scale-110 @endif">
                                                        @if($step['state'] === 'done')
                                                            <i data-lucide="check" class="h-4 w-4 text-white"></i>
                                                        @elseif($step['state'] === 'current')
                                                            <i data-lucide="{{ $step['icon'] }}" class="h-4 w-4 text-white"></i>
                                                        @else
                                                            <span class="text-xs font-bold text-white">{{ $step['n'] }}</span>
                                                        @endif
                                                    </div>

                                                    {{-- Labels --}}
                                                    <div class="mt-2 text-center">
                                                        <p class="dfr-step-label text-xs font-semibold {{ $step['text'] }} leading-tight
                                                            @if($step['state']==='current') font-bold @endif">
                                                            <span class="dfr-step-label-text">{{ $step['label'] }}</span>
                                                            @if($step['state']==='current')
                                                                <span class="dfr-pulse ml-1 inline-block w-1.5 h-1.5 rounded-full bg-green-500 animate-pulse align-middle"></span>
                                                            @endif
                                                        </p>
                                                        <p class="dfr-step-desc text-[10px] text-gray-400 leading-snug mt-0.5 hidden sm:block">{{ $step['desc'] }}</p>
                                                    </div>
                                                </div>
                                                @endforeach

                                            </div>{{-- end steps row --}}
                                        </div>

                                        {{-- Info footer strip --}}
                                        <div id="dfr-info-footer" class="bg-green-50 border-t border-green-100 px-6 py-2.5 flex items-center gap-4 flex-wrap transition-all duration-300">
                                            <div class="flex items-center gap-1.5 text-xs text-green-700">
                                                <i data-lucide="user-check" class="h-3.5 w-3.5 text-green-500 shrink-0"></i>
                                                <span><strong>Requester:</strong> {{ auth()->user()->first_name }} {{ auth()->user()->last_name }}</span>
                                            </div>
                                            <div class="flex items-center gap-1.5 text-xs text-green-700">
                                                <i data-lucide="building-2" class="h-3.5 w-3.5 text-green-500 shrink-0"></i>
                                                <span><strong>Your Department:</strong> {{ $userDepartmentName ?? 'Not assigned' }}</span>
                                            </div>
                                            <div id="dfr-footer-notice" class="flex items-center gap-1.5 text-xs text-amber-700 ml-auto transition-all duration-300">
                                                <i id="dfr-footer-notice-icon" data-lucide="info" class="h-3.5 w-3.5 text-amber-500 shrink-0"></i>
                                                <span id="dfr-footer-notice-text">The receiver will be notified and must approve before the file is dispatched.</span>
                                            </div>
                                        </div>

                                    </div>{{-- end card --}}
                                </div>
                                @endif
                                {{-- ===== END DIGITAL REQUEST STEPS ===== --}}

                                <div class="grid grid-cols-1 xl:grid-cols-[1fr,320px] gap-6">
                                    <!-- Main Form -->
                                    <div class="space-y-6">
                                        <!-- Requesters for this file (shown when logging a file that has open requests) -->
                                        <div id="file-requesters-panel" class="hidden bg-white rounded-lg border border-indigo-200 shadow-sm overflow-hidden">
                                            <div class="px-5 py-3 border-b border-indigo-100 bg-indigo-50 flex items-center gap-2">
                                                <i data-lucide="users" class="h-4 w-4 text-indigo-600"></i>
                                                <h3 class="text-sm font-bold text-indigo-900">Requesters for this file</h3>
                                                <span class="text-xs text-indigo-500">Honor the most senior first</span>
                                            </div>
                                            <div id="file-requesters-list" class="divide-y divide-gray-100"></div>
                                        </div>

                                        <!-- File Details Card -->
                                        <div class="bg-white rounded-lg border border-gray-200 shadow-sm">
                                            <div class="px-6 py-4 border-b border-gray-200">
                                                <h3 class="text-lg font-semibold text-gray-900 flex items-center gap-2">
                                                    <i data-lucide="file-text" class="h-5 w-5"></i>
                                                    File Details
                                                </h3>
                                                <p class="text-sm text-gray-600 mt-1">Enter the basic file information</p>
                                            </div>
                                            <div class="p-6 space-y-4">
                                                <div class="grid grid-cols-3 gap-4">
                                                    @php
                                                        // Every file must pass through Quick Search before it can be
                                                        // logged here. On the "Log a File" registries the smart file
                                                        // selector stays locked at ALL times — the file number is
                                                        // always pre-filled by the Quick Search redirect (?file_number=…),
                                                        // so the selector is never needed and must not re-enable after a
                                                        // submit or form reset. Track New File (new_kangis), Digital
                                                        // Request and DGIS/DG keep their own selector.
                                                        $logAFileModules = ['', 'land', 'kangis', 'sltr', 'st', 'dciv', 'cadastral', 'survey'];
                                                        $isLogAFileCtx   = in_array(strtolower($module ?? ''), $logAFileModules, true);
                                                        $lockFileSelector = $isLogAFileCtx;
                                                    @endphp
                                                    <div class="space-y-2">
                                                        <label for="file-no"
                                                            class="block text-sm font-medium text-gray-700">File No
                                                            *</label>
                                                        <div class="relative">
                                                            <input type="text" id="file-no" name="file_number"
                                                                placeholder="{{ $lockFileSelector ? 'Loaded from Quick Search' : 'e.g. RES-2015-4859' }}" readonly
                                                                class="block w-full px-3 py-2 pr-12 border border-gray-300 rounded-md shadow-sm bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                                            <button type="button" id="fileno-selector-btn"
                                                                @if($lockFileSelector) disabled aria-disabled="true" @endif
                                                                class="absolute inset-y-0 right-0 flex items-center px-3 border-l border-gray-300 transition-colors rounded-r-md {{ $lockFileSelector ? 'text-gray-300 cursor-not-allowed' : 'text-gray-500 hover:text-blue-600 hover:bg-blue-50' }}"
                                                                title="{{ $lockFileSelector ? 'Files are logged through Quick Search' : 'Select from existing file numbers' }}">
                                                                <i data-lucide="search" class="h-4 w-4"></i>
                                                            </button>
                                                        </div>
                                                        @unless($lockFileSelector)
                                                        <p class="text-xs text-gray-500">Use the smart file number selector
                                                            <i data-lucide="search" class="h-3 w-3 inline"></i> to search
                                                            and select
                                                        </p>
                                                        @endunless
                                                        <p id="file-indexing-status" class="hidden text-xs font-medium"></p>
                                                    </div>
                                                    <div class="space-y-2">
                                                        <label for="tracking-id"
                                                            class="block text-sm font-medium text-gray-700">File Tracking
                                                            ID</label>
                                                        <input type="text" id="tracking-id" readonly
                                                            class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm bg-gray-50 font-mono text-sm tracking-wide text-amber-700">
                                                        {{-- Hidden field carries the real (un-encrypted) value to the backend --}}
                                                        <input type="hidden" id="tracking-id-real">
                                                        <p class="text-xs text-gray-500">Auto-fetched from File Indexing
                                                            after selecting a file number</p>
                                                    </div>
                                                    <div class="space-y-2">
                                                        <label for="registry-code"
                                                            class="block text-sm font-medium text-gray-700">Registry
                                                            Code</label>
                                                        <input type="text" id="registry-code" name="origin_registry_code"
                                                            readonly disabled placeholder="—"
                                                            class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm bg-gray-100 font-mono text-sm tracking-wide text-gray-700 cursor-not-allowed">
                                                        {{-- Disabled fields are not submitted; mirror value here for the backend --}}
                                                        <input type="hidden" id="registry-code-real" name="origin_registry_code_hidden">
                                                        <p class="text-xs text-gray-500">Derived from the selected Registry
                                                            (Origin)</p>
                                                    </div>
                                                </div>

                                                {{-- File No / Tracking ID notices — kept in one shared row so the three
                                                     columns above stay the same height instead of File No's stacked
                                                     boxes pushing everything below it out of alignment. --}}
                                                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 items-start">
                                                    @if($lockFileSelector)
                                                    <div class="flex items-start gap-2 rounded-lg border border-indigo-200 bg-indigo-50 px-3 py-2">
                                                        <i data-lucide="info" class="h-4 w-4 text-indigo-500 shrink-0 mt-0.5"></i>
                                                        <p class="text-xs text-indigo-700">Files are logged through
                                                            <a href="{{ route('create-file-tracker.quick-search') }}" class="font-semibold underline hover:text-indigo-900">Quick Search</a>
                                                            — the smart selector is disabled here. Search a file in Quick Search and click <span class="font-semibold">Log File</span> to load it.</p>
                                                    </div>
                                                    {{-- In-transit override: lets the user re-enable the smart file
                                                         selector to manually pick a file that is already out (in
                                                         transit) without going back through Quick Search. --}}
                                                    <label for="fileno-selector-override"
                                                        class="flex items-start gap-2 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 cursor-pointer">
                                                        <input type="checkbox" id="fileno-selector-override"
                                                            class="mt-0.5 h-3.5 w-3.5 rounded border-gray-300 text-amber-600 focus:ring-amber-500">
                                                        <span class="text-xs text-amber-700">Override for in-transit file —
                                                            enable the file number selector.</span>
                                                    </label>
                                                    @endif
                                                    {{-- Temporary file toggle — appends the TMP code to the Tracking ID
                                                         so the temporary file is tracked as a standalone file. --}}
                                                    <label for="is-temp-file"
                                                        class="flex items-start gap-2 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 cursor-pointer">
                                                        <input type="checkbox" id="is-temp-file"
                                                            class="mt-0.5 h-3.5 w-3.5 rounded border-gray-300 text-amber-600 focus:ring-amber-500">
                                                        <span class="text-xs text-amber-700">Is Temporary File —
                                                            appends the <span class="font-mono font-semibold">TMP</span> code to the
                                                            Tracking ID.</span>
                                                    </label>
                                                </div>

                                                {{-- Logged-out warning — shown by JS when the selected file is already logged out --}}
                                                <div id="file-logout-warning" class="hidden flex items-start gap-3 rounded-lg border border-amber-300 bg-amber-50 px-4 py-3">
                                                    <i data-lucide="alert-triangle" class="h-5 w-5 text-amber-500 shrink-0 mt-0.5"></i>
                                                    <div class="flex-1 min-w-0">
                                                        <p class="text-sm font-semibold text-amber-800">File Already Logged Out</p>
                                                        <p id="file-logout-warning-text" class="text-sm text-amber-700 mt-0.5"></p>
                                                        <p class="text-xs text-amber-600 mt-1">Please log this file back into the registry first, then you can log it out to a new office.</p>
                                                    </div>
                                                    <button type="button" id="file-logout-warning-dismiss" class="shrink-0 text-amber-500 hover:text-amber-700 transition-colors" title="Dismiss">
                                                        <i data-lucide="x" class="h-4 w-4"></i>
                                                    </button>
                                                </div>

                                                @if(false) {{-- Related Files panel hidden for now (mirrors Tracking Sheet / File Log Table) --}}
                                                {{-- Related files (parcel-update / merger lineage) — shown by JS when the
                                                     selected file belongs to a Subdivision / Merger / Change of Purpose /
                                                     Extension / Separation group. Lets the clerk see every related file
                                                     (e.g. all source files that fed a merger) at a glance. --}}
                                                <div id="related-files-panel" class="hidden rounded-lg border border-indigo-200 bg-indigo-50 px-4 py-3">
                                                    <div class="flex items-center gap-2 mb-1">
                                                        <i data-lucide="git-merge" class="h-4 w-4 text-indigo-500 shrink-0"></i>
                                                        <p id="related-files-title" class="text-sm font-semibold text-indigo-800">Related Files</p>
                                                    </div>
                                                    <p id="related-files-subtitle" class="text-xs text-indigo-600 mb-2">This file is part of a parcel-update lineage.</p>
                                                    <ul id="related-files-list" class="divide-y divide-indigo-100"></ul>
                                                </div>
                                                @endif

                                                <div class="space-y-2">
                                                    <label for="file-name"
                                                        class="block text-sm font-medium text-gray-700">File Title *</label>
                                                    <input type="text" id="file-name"
                                                        placeholder="e.g. Alhaji Ibrahim Dantata"
                                                        class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                                    <p class="text-xs text-gray-500">Auto-filled from File Indexing; reset
                                                        the form to unlock if a change is required</p>
                                                </div>

                                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                                    <!-- Priority Selection -->
                                                    <div class="space-y-2">
                                                        <label for="file-priority"
                                                            class="block text-sm font-medium text-gray-700">File
                                                            Priority</label>
                                                        <select id="file-priority"
                                                            class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                                            <option value="LOW">Low Priority</option>
                                                            <option value="MEDIUM">Medium Priority</option>
                                                            <option value="HIGH" selected>High Priority</option>
                                                        </select>
                                                        <p class="text-xs text-gray-500">Set the priority level for this file
                                                        </p>
                                                    </div>

                                                    <!-- Status Update Section -->
                                                    <div class="space-y-2">
                                                        <label for="file-status"
                                                            class="block text-sm font-medium text-gray-700">Status
                                                            Update</label>
                                                        <select id="file-status" name="status"
                                                            class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                                            <option value="Log-out" selected>Log-out</option>
                                                            <option value="Log-in">Log-in</option>
                                                            <option value="Canceled">Canceled</option>
                                                        </select>
                                                        <p class="text-xs text-gray-500">Update the file tracker status</p>
                                                    </div>
                                                </div>

                                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                                    <!-- Request Purpose -->
                                                    <div class="space-y-2">
                                                        <label for="request-purpose"
                                                            class="block text-sm font-medium text-gray-700">Request Purpose <span class="text-red-500">*</span></label>
                                                        <select id="request-purpose" name="request_purpose_id" required
                                                            class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                                            <option value="">Select the reason this file is being requested</option>
                                                            @isset($requestPurposes)
                                                                @foreach($requestPurposes as $purpose)
                                                                    <option value="{{ $purpose->id }}" data-turnaround-days="{{ $purpose->turnaround_days }}">{{ $purpose->name }}</option>
                                                                @endforeach
                                                            @endisset
                                                            <option value="in_transit">In-Transit</option>
                                                            <option value="other">Other</option>
                                                        </select>
                                                        <p class="text-xs text-gray-500">Drives the file's expected turnaround (timeline) and its Green/Amber/Red status. "In-Transit" skips the Timeline/Expected Return Date requirement.</p>
                                                        <div id="request-purpose-other-wrap" class="hidden">
                                                            <label for="request-purpose-other" class="sr-only">Specify Purpose</label>
                                                            <input type="text" id="request-purpose-other" name="request_purpose_other"
                                                                placeholder="Specify the reason this file is being requested"
                                                                class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                                        </div>
                                                    </div>

                                                    <!-- Timeline (Days) -->
                                                    <div class="space-y-2" id="request-timeline-days-wrap">
                                                        <label for="request-timeline-days"
                                                            class="block text-sm font-medium text-gray-700">Timeline (Days)</label>
                                                        <input type="number" id="request-timeline-days" min="0" max="365"
                                                            placeholder="e.g. 5"
                                                            class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                                        <p class="text-xs text-gray-500">How many days should this file be with the requesting office before it's due back? Sets the Expected Return Date below.</p>
                                                        <div class="flex items-center gap-2 flex-wrap">
                                                            <p id="request-deadline-days" class="hidden text-xs font-medium text-gray-600"></p>
                                                            <p id="request-timeline-preview" class="hidden inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-medium"></p>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                                    <!-- Expected Return Date -->
                                                    <div class="space-y-2" id="request-deadline-wrap">
                                                        <label for="request-deadline"
                                                            class="block text-sm font-medium text-gray-700">Expected Return Date <span class="text-red-500">*</span></label>
                                                        <input type="date" id="request-deadline" name="deadline" disabled
                                                            class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm bg-gray-100 text-gray-500 cursor-not-allowed">
                                                        <p class="text-xs text-gray-500">Auto-calculated from the Request Purpose or Timeline (Days) above — not manually editable.</p>
                                                    </div>

                                                    <!-- Number of Pages -->
                                                    <div class="space-y-2">
                                                        <label for="num-pages"
                                                            class="block text-sm font-medium text-gray-700">Number of Pages <span class="text-red-500">*</span></label>
                                                        <input type="number" id="num-pages" name="num_pages" min="1" max="99999" required
                                                            placeholder="Enter total pages…"
                                                            class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                                        <p id="num-pages-hint" class="text-xs text-gray-500">Count the total pages in the physical file before logging out.</p>
                                                        <p id="num-pages-error" class="hidden text-xs text-red-600">Please enter a valid number of pages (1–99,999).</p>
                                                    </div>
                                                </div>

                                                <!-- Notes/Remarks Field -->
                                                <div class="space-y-2">
                                                    @if(in_array(strtolower($module ?? ''), ['digital_request','digital-request']))
                                                    <label for="office-notes"
                                                        class="block text-sm font-medium text-gray-700">
                                                        Request Reason <span class="text-red-500">*</span>
                                                    </label>
                                                    <textarea id="office-notes" name="remarks" rows="3" required
                                                        placeholder="State the reason for requesting this file…"
                                                        class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500"></textarea>
                                                    <p class="text-xs text-gray-500">This reason will be visible to the approving officer.</p>
                                                    @else
                                                    <label for="office-notes"
                                                        class="block text-sm font-medium text-gray-700">Notes/Remarks</label>
                                                    <textarea id="office-notes" name="remarks" rows="3"
                                                        placeholder="Reason why the file is in this office..."
                                                        class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"></textarea>
                                                    <p class="text-xs text-gray-500">Enter the reason for the file being in
                                                        this office</p>
                                                    @endif
                                                </div>

                                            </div>
                                        </div>

                                        {{-- Cross-Registry Request File Preview (Property + Folder) --}}
                                        <div id="cross-request-preview-panel" class="bg-white rounded-xl border border-blue-100 shadow-sm hidden overflow-hidden">
                                            <div class="px-6 py-4 border-b border-blue-100 bg-gradient-to-r from-blue-50 to-indigo-50 flex items-center gap-3">
                                                <div class="w-9 h-9 rounded-full bg-blue-600 flex items-center justify-center flex-shrink-0 shadow">
                                                    <i data-lucide="file-search" class="h-4 w-4 text-white"></i>
                                                </div>
                                                <div>
                                                    <h3 class="text-base font-bold text-blue-900">Requested File Preview</h3>
                                                    <p class="text-xs text-blue-600">Property record &amp; scanned document folder</p>
                                                </div>
                                            </div>
                                            <div class="p-6">
                                                <div id="cross-request-preview-empty" class="flex flex-col items-center justify-center gap-2 py-8 text-gray-400 border border-dashed border-gray-300 rounded-lg bg-gray-50">
                                                    <i data-lucide="folder-open" class="h-8 w-8 text-gray-300"></i>
                                                    <p class="text-sm">Activate Request File mode and select a file number to load preview.</p>
                                                </div>

                                                <div id="cross-request-preview-loading" class="hidden flex items-center gap-3 py-6 justify-center text-blue-600">
                                                    <svg class="animate-spin h-5 w-5 text-blue-500" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"></path></svg>
                                                    <span class="text-sm font-medium">Loading property details and folder files...</span>
                                                </div>

                                                <div id="cross-request-preview-grid" class="hidden grid grid-cols-1 xl:grid-cols-2 gap-5">
                                                    <div class="border border-indigo-100 rounded-xl overflow-hidden shadow-sm">
                                                        <div class="px-4 py-2.5 bg-gradient-to-r from-indigo-50 to-blue-50 border-b border-indigo-100 flex items-center gap-2">
                                                            <i data-lucide="building-2" class="h-4 w-4 text-indigo-500"></i>
                                                            <h4 class="text-sm font-bold text-indigo-800">Property Details</h4>
                                                        </div>
                                                        <div id="cross-request-property-details" class="p-4 text-sm text-gray-700 space-y-2"></div>
                                                    </div>

                                                    <div class="border border-amber-100 rounded-xl overflow-hidden shadow-sm">
                                                        <div class="px-4 py-2.5 bg-gradient-to-r from-amber-50 to-orange-50 border-b border-amber-100 flex items-center justify-between">
                                                            <div class="flex items-center gap-2">
                                                                <i data-lucide="images" class="h-4 w-4 text-amber-500"></i>
                                                                <h4 class="text-sm font-bold text-amber-800">Folder Files (A4)</h4>
                                                                <span id="cross-request-folder-path" class="text-xs text-amber-600 font-normal"></span>
                                                            </div>
                                                            <span id="cross-request-files-access" class="text-xs px-2 py-1 rounded-full bg-amber-100 text-amber-800 border border-amber-200">Restricted</span>
                                                        </div>
                                                        <div class="p-4">
                                                            <ul id="cross-request-files-list" class="space-y-2"></ul>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        {{-- Cross-Registry File Request Workflow Panel (Dynamically shown) --}}
                                        <div id="cross-module-workflow-panel" class="p-4 bg-gradient-to-r from-blue-50 to-indigo-50 rounded-lg border border-blue-200 hidden">
                                            <div class="flex items-center justify-between mb-4">
                                                <div class="flex items-center gap-3">
                                                    <div class="w-8 h-8 rounded-full bg-blue-100 flex items-center justify-center flex-shrink-0">
                                                        <i data-lucide="arrow-left-right" class="h-4 w-4 text-blue-600"></i>
                                                    </div>
                                                    <div>
                                                        <h4 class="text-sm font-semibold text-blue-900" id="cm-workflow-title">Cross-Registry File Request</h4>
                                                        <p class="text-xs text-blue-700" id="cm-workflow-subtitle">Following the formal inter-departmental request chain</p>
                                                    </div>
                                                </div>
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800 border border-blue-200">
                                                    Active Request Mode
                                                </span>
                                            </div>

                                            <div class="mt-4">
                                                <div class="flex items-center justify-between gap-1">
                                                    {{-- Step 1: Recommendation --}}
                                                    <div class="workflow-step flex-1 text-center">
                                                        <div class="workflow-step-dot mx-auto w-10 h-10 rounded-full bg-indigo-400 flex items-center justify-center shadow-md ring-4 ring-white">
                                                            <i data-lucide="file-signature" class="h-5 w-5 text-white"></i>
                                                        </div>
                                                        <p class="mt-2 text-xs font-bold text-indigo-700" id="cm-step1-title">Registry</p>
                                                    </div>
                                                    {{-- Arrow 1 --}}
                                                    <div class="flex flex-col items-center flex-shrink-0 px-1">
                                                        <p class="text-[10px] text-gray-500 font-medium mb-1">Step 1: Recommendation</p>
                                                        <div class="flex items-center">
                                                            <div class="w-12 h-0.5 bg-blue-200"></div>
                                                            <i data-lucide="chevron-right" class="h-3 w-3 text-blue-400 -ml-1"></i>
                                                        </div>
                                                    </div>

                                                    {{-- Step 2: Approval --}}
                                                    <div class="workflow-step flex-1 text-center">
                                                        <div class="workflow-step-dot mx-auto w-10 h-10 rounded-full bg-blue-500 flex items-center justify-center shadow-md ring-4 ring-white">
                                                            <i data-lucide="shield-check" class="h-5 w-5 text-white"></i>
                                                        </div>
                                                        <p class="mt-2 text-xs font-bold text-blue-700" id="cm-step2-title">Director / DG</p>
                                                    </div>
                                                    {{-- Arrow 2 --}}
                                                    <div class="flex flex-col items-center flex-shrink-0 px-1">
                                                        <p class="text-[10px] text-gray-500 font-medium mb-1">Step 2: Approval</p>
                                                        <div class="flex items-center">
                                                            <div class="w-12 h-0.5 bg-blue-200"></div>
                                                            <i data-lucide="chevron-right" class="h-3 w-3 text-blue-400 -ml-1"></i>
                                                        </div>
                                                    </div>

                                                    {{-- Step 3: Route --}}
                                                    <div class="workflow-step flex-1 text-center">
                                                        <div class="workflow-step-dot mx-auto w-10 h-10 rounded-full bg-emerald-500 flex items-center justify-center shadow-md ring-4 ring-white">
                                                            <i data-lucide="folder-output" class="h-5 w-5 text-white"></i>
                                                        </div>
                                                        <p class="mt-2 text-xs font-bold text-emerald-700" id="cm-step3-title">Destination</p>
                                                    </div>
                                                </div>
                                                <p class="mt-4 text-xs text-blue-700 text-center font-medium" id="cm-workflow-footer">
                                                    File will be transferred upon final approval.
                                                </p>
                                            </div>
                                        </div>

                                        <!-- Office Details Card -->
                                        <div id="office-details-card" class="bg-white rounded-lg border border-gray-200 shadow-sm">
                                            <div class="px-6 py-4 border-b border-gray-200">
                                                <h3 class="text-lg font-semibold text-gray-900 flex items-center gap-2">
                                                    <i data-lucide="building" class="h-5 w-5"></i>
                                                    Office Details
                                                </h3>
                                                <p class="text-sm text-gray-600 mt-1">Select the current office location</p>
                                            </div>
                                            <div class="p-6 space-y-4">
                                                {{-- Office Details is locked until the in-transit override checkbox
                                                     is checked, so an already-out file's office fields can't be
                                                     edited via a stale/locked file selector. --}}
                                                <fieldset id="office-details-fieldset" @if($lockFileSelector) disabled @endif class="space-y-4 @if($lockFileSelector) opacity-60 @endif">
                                                @if(($module ?? '') === 'kangis')
                                                <!-- Step 1 label -->
                                                <div class="flex items-center gap-2 -mb-1">
                                                    <span class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-indigo-600 text-white text-xs font-bold flex-shrink-0">1</span>
                                                    <span class="text-sm font-semibold text-indigo-800">Step 1: Submission &mdash; KANGIS Registry</span>
                                                </div>
                                                @endif
                                                <!-- Row 1: Registry + Destination + Recommendation (3-col for kangis, 2-col otherwise) -->
                                                <div id="dfr-dest-office-row" class="grid @if(($module ?? '') === 'kangis') grid-cols-3 @else grid-cols-2 @endif gap-4 @if(($module ?? '') === 'new_kangis') hidden @endif" @if(($module ?? '') === 'new_kangis') aria-hidden="true" @endif>
                                                    <div class="space-y-2">
                                                        <label for="origin-office" class="block text-sm font-medium text-gray-700">Registry (Origin)</label>
                                                        <select id="origin-office"
                                                            class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 {{ in_array(strtolower($module ?? ''), ['sltr', 'dciv', 'st']) ? 'bg-gray-100 text-gray-700 cursor-not-allowed' : '' }}"
                                                            @if(in_array(($module ?? ''), ['kangis', 'new_kangis'])) data-default="KANGIS Registry" @elseif(($module ?? '') === 'sltr') data-default="SLTR Registry" @elseif(($module ?? '') === 'st') data-default="ST Registry" @elseif(($module ?? '') === 'dciv') data-default="DCIV Registry" @elseif(strtolower($module ?? '') === 'cadastral') data-default="Registry 1 - Cadastral" @endif
                                                            {{ in_array(strtolower($module ?? ''), ['sltr', 'dciv', 'st']) ? 'disabled' : '' }}>
                                                            <option value="">Select Registry (Origin)</option>
                                                            @foreach ($registries as $registry)
                                                                @php
                                                                    $registryDisplay = ($registry->name === 'Registry 1 - Lands')
                                                                        ? 'Registry 1 - Land'
                                                                        : $registry->name;
                                                                    $isCadastral = str_contains(strtolower($registry->name), 'cadastr');
                                                                    $isLand = str_contains(strtolower($registry->name), 'land');
                                                                    $isKangis = str_contains(strtolower($registry->name), 'kangis');
                                                                    $isSltr = str_contains(strtolower($registry->name), 'sltr');
                                                                    $currentModule = strtolower($module ?? '');
                                                                    $isDefaultLandPage = in_array($currentModule, ['', 'land'], true);
                                                                    $isKangisModule = in_array($currentModule, ['kangis', 'new_kangis'], true);
                                                                    $isSelected = (
                                                                        ($currentModule === 'sltr' && $registry->name === 'SLTR Registry') ||
                                                                        ($currentModule === 'st'   && $registry->name === 'ST Registry')
                                                                    );
                                                                @endphp
                                                                {{-- Cadastral module shows only its own registries; the KANGIS
                                                                     module shows only the KANGIS Registry; the SLTR module shows
                                                                     only the SLTR Registry; the default /create-file-tracker page
                                                                     shows only Land registries; every other module context lists
                                                                     all registries. --}}
                                                                @continue($currentModule === 'cadastral' && !$isCadastral)
                                                                @continue($isKangisModule && !$isKangis)
                                                                @continue($currentModule === 'sltr' && !$isSltr)
                                                                @continue($isDefaultLandPage && !$isLand)
                                                                <option value="{{ $registry->name }}"
                                                                    data-registry-code="{{ $registry->registry_code }}"
                                                                    {{ $isSelected ? 'selected' : '' }}>{{ $registryDisplay }}</option>
                                                            @endforeach
                                                        </select>                 </select>
                                                        <p class="text-xs text-gray-500">Defaults to Officers as the origin
                                                            registry</p>
                                                    </div>
                                                    <div class="space-y-2">
                                                        <label for="current-office"
                                                            class="block text-sm font-medium text-gray-700">Destination
                                                            Office (@if(($module ?? '') === 'sltr') Units @else Departments @endif) *</label>
                                                        @php
                                                            $isDigitalRequestModule = in_array(strtolower($module ?? ''), ['digital_request','digital-request']);
                                                        @endphp
                                                        <select id="current-office" name="receiving_office_name"
                                                            class="block w-full px-3 py-2 border rounded-md shadow-sm focus:outline-none {{ $isDigitalRequestModule ? 'border-green-300 bg-green-50 text-green-900 pointer-events-none cursor-default' : 'border-gray-300 focus:ring-2 focus:ring-blue-500 focus:border-blue-500' }}">
                                                            <option value="">Select destination</option>
                                                            @foreach ($departments as $dept)
                                                                <option value="{{ $dept->department }}"
                                                                    @if($isDigitalRequestModule && ($userDepartmentName ?? '') === $dept->department) selected @endif>
                                                                    {{ $dept->department }}
                                                                </option>
                                                            @endforeach
                                                        </select>
                                                        @if($isDigitalRequestModule)
                                                            <p class="text-xs text-green-600">Auto-set to your department</p>
                                                        @else
                                                            <p class="text-xs text-gray-500">Select the destination department/office</p>
                                                        @endif
                                                        @if(($module ?? '') === 'sltr')
                                                        {{-- Internal scope: shown when "Other" is picked as the internal SLTR section --}}
                                                        <div id="sltr-internal-other-wrapper" class="hidden space-y-1">
                                                            <input type="text" id="sltr-internal-other-name"
                                                                placeholder="Specify the destination…"
                                                                class="block w-full px-3 py-2 border border-indigo-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                                                            <p class="text-xs text-indigo-600">Specify where this file is going within SLTR.</p>
                                                        </div>
                                                        @endif
                                                    </div>
                                                    @if(($module ?? '') === 'kangis')
                                                    <div class="space-y-2">
                                                        <label for="recommendation-for-approval"
                                                            class="block text-sm font-medium text-gray-700">Recommendation for Approval</label>
                                                        <input type="text" id="recommendation-for-approval"
                                                            value="Director GIS"
                                                            data-default-value="Director GIS"
                                                            readonly
                                                            class="block w-full px-3 py-2 border border-indigo-200 rounded-md shadow-sm bg-indigo-50 text-indigo-800 font-medium">
                                                        <p class="text-xs text-indigo-600">Initial routing target for approval workflow.</p>
                                                    </div>
                                                    @endif
                                                </div>

                                                @if(($module ?? '') === 'new_kangis')
                                                @php
                                                    $newKangisSteps = [
                                                        ['n' => 1, 'label' => 'Origin (Customer Service) → One Stop Shop (OSS)', 'from' => 'Origin (Customer Service)', 'to' => 'One Stop Shop (OSS)',  'badge' => 'bg-amber-600',   'title' => 'text-amber-800',   'note' => '&mdash; origin, initiated after Indexing Tracking Sheet'],
                                                        ['n' => 2, 'label' => 'One Stop Shop (OSS) → Verification',        'from' => 'One Stop Shop (OSS)',  'to' => 'Verification',         'badge' => 'bg-teal-600',    'title' => 'text-teal-800',    'note' => '&mdash; auto via QR scan, display only'],
                                                        ['n' => 3, 'label' => 'Verification → Geometry (GIS)',             'from' => 'Verification',         'to' => 'Geometry (GIS)',       'badge' => 'bg-sky-500',     'title' => 'text-sky-800',     'note' => '&mdash; auto via QR scan, display only'],
                                                        ['n' => 4, 'label' => 'Geometry (GIS) → Vetting Committee',        'from' => 'Geometry (GIS)',       'to' => 'Vetting Committee',    'badge' => 'bg-blue-500',    'title' => 'text-blue-800',    'note' => '&mdash; auto via QR scan, display only'],
                                                        ['n' => 5, 'label' => 'Vetting Committee → Pagination (Deeds)',    'from' => 'Vetting Committee',    'to' => 'Pagination (Deeds)',   'badge' => 'bg-emerald-500', 'title' => 'text-emerald-800', 'note' => '&mdash; auto via QR scan, display only'],
                                                        ['n' => 6, 'label' => 'Pagination (Deeds) → Production (GIS)',     'from' => 'Pagination (Deeds)',   'to' => 'Production (GIS)',     'badge' => 'bg-violet-500',  'title' => 'text-violet-800',  'note' => '&mdash; auto via QR scan, display only'],
                                                        ['n' => 7, 'label' => 'Production (GIS) → Collection (DG)',        'from' => 'Production (GIS)',     'to' => 'Collection (DG)',      'badge' => 'bg-indigo-500',  'title' => 'text-indigo-800',  'note' => '&mdash; auto via QR scan, display only'],
                                                        ['n' => 8, 'label' => 'Collection (DG) → KANGIS Registry Log-In',  'from' => 'Collection (DG)',      'to' => 'KANGIS Registry',      'badge' => 'bg-rose-500',    'title' => 'text-rose-800',    'note' => '&mdash; auto via QR scan, display only'],
                                                    ];
                                                @endphp
                                                <details class="kn-steps-group">
                                                    <summary class="cursor-pointer list-none select-none flex items-center gap-2 py-2 border-b border-gray-200">
                                                        <i data-lucide="list-ordered" class="h-4 w-4 text-amber-600"></i>
                                                        <span class="text-sm font-semibold text-gray-800">New KANGIS — 8-Step Tracking Workflow</span>
                                                        <span class="text-xs text-gray-400">(click to expand / collapse all steps)</span>
                                                        <i data-lucide="chevron-down" class="kn-steps-group-chevron h-4 w-4 text-gray-400 ml-auto transition-transform"></i>
                                                    </summary>
                                                    <div class="pt-3 space-y-0">
                                                @foreach($newKangisSteps as $s)
                                                <!-- Step {{ $s['n'] }}: {{ $s['label'] }} (display only) -->
                                                <div class="@if(!$loop->first) border-t border-gray-100 pt-4 mt-4 @endif">
                                                    <div class="flex items-center gap-2 mb-3">
                                                        <span class="inline-flex items-center justify-center w-6 h-6 rounded-full {{ $s['badge'] }} text-white text-xs font-bold flex-shrink-0">{{ $s['n'] }}</span>
                                                        <span class="text-sm font-semibold {{ $s['title'] }}">Step {{ $s['n'] }}: {{ $s['label'] }}</span>
                                                        <span class="text-xs text-gray-400 ml-1">{!! $s['note'] !!}</span>
                                                    </div>
                                                    <div class="grid grid-cols-2 gap-4 pl-8">
                                                        <div class="space-y-1">
                                                            <label class="block text-xs font-medium text-gray-500 uppercase tracking-wide">From</label>
                                                            <input type="text" value="{{ $s['from'] }}" disabled
                                                                class="block w-full px-3 py-2 border border-gray-200 rounded-md bg-gray-100 text-gray-600 text-sm cursor-not-allowed">
                                                        </div>
                                                        <div class="space-y-1">
                                                            <label class="block text-xs font-medium text-gray-500 uppercase tracking-wide">To</label>
                                                            <input type="text" value="{{ $s['to'] }}" disabled
                                                                class="block w-full px-3 py-2 border border-gray-200 rounded-md bg-gray-100 text-gray-600 text-sm cursor-not-allowed">
                                                        </div>
                                                    </div>
                                                </div>
                                                @endforeach
                                                    </div>
                                                </details>
                                                <style>
                                                    details.kn-steps-group > summary::-webkit-details-marker { display: none; }
                                                    details.kn-steps-group[open] > summary .kn-steps-group-chevron { transform: rotate(180deg); }
                                                </style>
                                                @endif

                                                <!-- Row 2: Receiving Office + Receiving Officer (2-col) -->
                                                @if(($module ?? '') === 'kangis')

                                                <!-- Step 2: Recommendation (display only) -->
                                                <div id="kangis-step2-block" class="border-t border-gray-100 pt-4">
                                                    <div class="flex items-center gap-2 mb-3">
                                                        <span class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-indigo-500 text-white text-xs font-bold flex-shrink-0">2</span>
                                                        <span class="text-sm font-semibold text-indigo-800">Step 2: Recommendation</span>
                                                        <span id="kangis-step2-subtitle" class="text-xs text-gray-400 ml-1" data-default-text="&mdash; handled by Director GIS, display only">&mdash; handled by Director GIS, display only</span>
                                                    </div>
                                                    <div class="grid grid-cols-3 gap-4">
                                                        <div class="space-y-1">
                                                            <p class="text-xs font-medium text-gray-500 uppercase tracking-wide">Recommendated</p>
                                                            <div class="flex items-center gap-2 px-3 py-2 bg-gray-50 border border-gray-200 rounded-md">
                                                                <i data-lucide="user-cog" class="h-4 w-4 text-indigo-400 flex-shrink-0"></i>
                                                                <span id="kangis-step2-recommended" class="text-sm text-gray-700" data-default-text="Director GIS">Director GIS</span>
                                                                <i data-lucide="arrow-right" class="h-3 w-3 text-gray-400 ml-auto"></i>
                                                            </div>
                                                        </div>
                                                        <div class="space-y-1">
                                                            <p class="text-xs font-medium text-gray-500 uppercase tracking-wide">Recommendated For Approval</p>
                                                            <div class="flex items-center gap-2 px-3 py-2 bg-gray-50 border border-gray-200 rounded-md">
                                                                <i data-lucide="arrow-right" class="h-4 w-4 text-indigo-400 flex-shrink-0"></i>
                                                                <span id="kangis-step2-recommended-for" class="text-sm text-gray-700" data-default-text="DS KANGIS">DS KANGIS</span>
                                                                <i data-lucide="arrow-right" class="h-3 w-3 text-gray-400 ml-auto"></i>
                                                            </div>
                                                        </div>
                                                        <div class="space-y-1">
                                                            <p class="text-xs font-medium text-gray-500 uppercase tracking-wide">Destination (Departments)</p>
                                                            <div id="step2-destination-display" class="flex items-center gap-2 px-3 py-2 bg-gray-50 border border-dashed border-gray-300 rounded-md">
                                                                <i data-lucide="help-circle" class="h-4 w-4 text-gray-400 flex-shrink-0"></i>
                                                                <span id="step2-destination-text" class="text-sm text-gray-400 italic">Destination (Dept)</span>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>

                                                <!-- Step 3: Approval (display only) -->
                                                <div id="kangis-step3-block" class="border-t border-gray-100 pt-4">
                                                    <div class="flex items-center gap-2 mb-3">
                                                        <span class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-green-600 text-white text-xs font-bold flex-shrink-0">3</span>
                                                        <span class="text-sm font-semibold text-green-800">Step 3: Approval</span>
                                                        <span class="text-xs text-gray-400 ml-1">&mdash; handled by DG KANGIS, display only</span>
                                                    </div>
                                                    <div class="grid grid-cols-3 gap-4">
                                                        <div class="space-y-1">
                                                            <p class="text-xs font-medium text-gray-500 uppercase tracking-wide">Approval</p>
                                                            <div class="flex items-center gap-2 px-3 py-2 bg-gray-50 border border-gray-200 rounded-md">
                                                                <i data-lucide="shield-check" class="h-4 w-4 text-green-500 flex-shrink-0"></i>
                                                                <span class="text-sm text-gray-700">DG KANGIS</span>
                                                                <i data-lucide="arrow-right" class="h-3 w-3 text-gray-400 ml-auto"></i>
                                                            </div>
                                                        </div>
                                                        <div class="space-y-1">
                                                            <p class="text-xs font-medium text-gray-500 uppercase tracking-wide">Decision (Pending&nbsp;/&nbsp;Approved&nbsp;/&nbsp;Not Approved)</p>
                                                            <div class="flex items-center gap-2 px-3 py-2 bg-gray-50 border border-dashed border-gray-300 rounded-md">
                                                                <i data-lucide="arrow-right" class="h-4 w-4 text-gray-400 flex-shrink-0"></i>
                                                                <span class="text-sm text-gray-400 italic">Approval &rarr;</span>
                                                            </div>
                                                        </div>
                                                        <div class="space-y-1">
                                                            <p class="text-xs font-medium text-gray-500 uppercase tracking-wide">Destination (Depts) &amp; Receiving Officer (If Approved)</p>
                                                            <div class="flex items-center gap-2 px-3 py-2 bg-gray-50 border border-dashed border-gray-300 rounded-md">
                                                                <i data-lucide="help-circle" class="h-4 w-4 text-gray-400 flex-shrink-0"></i>
                                                                <span class="text-sm text-gray-400 italic">Receiving Officer</span>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>

                                                <!-- Step 4 label -->
                                                <div id="kangis-step4-block" class="border-t border-gray-100 pt-4">
                                                    <div class="flex items-center gap-2 mb-3">
                                                        <span class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-rose-600 text-white text-xs font-bold flex-shrink-0">4</span>
                                                        <span class="text-sm font-semibold text-rose-800">Step 4: Receiving Officer</span>
                                                        <span class="text-xs text-amber-600 font-medium ml-1">&mdash; enabled after approval</span>
                                                    </div>
                                                @endif
                                                <div class="grid grid-cols-2 gap-4">
                                                    <!-- Hidden ID fields -->
                                                    <div class="space-y-2 hidden" aria-hidden="true">
                                                        <label for="origin-office-id" class="sr-only">Origin Office ID</label>
                                                        <input type="text" id="origin-office-id" readonly
                                                            class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm bg-gray-50 font-mono text-sm">
                                                    </div>
                                                    <div class="space-y-2 hidden" aria-hidden="true">
                                                        <label for="current-office-id" class="sr-only">Destination Office ID</label>
                                                        <input type="text" id="current-office-id" readonly
                                                            class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm bg-gray-50 font-mono text-sm">
                                                    </div>
                                                    <!-- New Receiving Office Dropdown -->
                                                    <div id="receiving-office-group" class="space-y-2 @if(($module ?? '') === 'new_kangis') hidden @endif" @if(($module ?? '') === 'new_kangis') aria-hidden="true" @endif>
                                                        <label for="receiving-office"
                                                            class="block text-sm font-medium text-gray-700">Receiving Office
                                                            *</label>

                                                        @if(in_array(strtolower($module ?? ''), ['digital_request','digital-request']))
                                                            {{-- Digital Request: dropdown scoped to the user's department --}}
                                                            @if($userDepartmentOffices->isEmpty())
                                                                <div class="flex items-center gap-2 px-3 py-2 bg-amber-50 border border-amber-200 rounded-md">
                                                                    <i data-lucide="alert-triangle" class="h-4 w-4 text-amber-500 shrink-0"></i>
                                                                    <span class="text-xs text-amber-700">No offices found for your department. Contact admin.</span>
                                                                </div>
                                                                <select id="receiving-office" name="receiving_office_code" class="hidden" aria-hidden="true">
                                                                    <option value=""></option>
                                                                </select>
                                                            @else
                                                                <select id="receiving-office" name="receiving_office_code"
                                                                    class="block w-full px-3 py-2 border border-green-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500 bg-white">
                                                                    <option value="">— Select your office —</option>
                                                                    @foreach($userDepartmentOffices as $deptOffice)
                                                                        <option value="{{ $deptOffice->office_code }}"
                                                                            data-name="{{ $deptOffice->office_name }}"
                                                                            data-department="{{ $deptOffice->department }}"
                                                                            @if($loop->first) selected @endif>
                                                                            {{ $deptOffice->office_name }}
                                                                        </option>
                                                                    @endforeach
                                                                </select>
                                                                <p class="text-xs text-green-600">Offices under your department: <strong>{{ $userDepartmentName }}</strong></p>
                                                            @endif
                                                        @else
                                                            <select id="receiving-office" name="receiving_office_code"
                                                                class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                                                <option value="">Select receiving office</option>
                                                                @foreach ($offices as $office)
                                                                    <option value="{{ $office->office_code }}"
                                                                        data-name="{{ $office->office_name }}"
                                                                        data-department="{{ $office->department }}">
                                                                        {{ $office->office_name }}
                                                                    </option>
                                                                @endforeach
                                                            </select>
                                                        @endif
                                                    </div>
                                                    <div id="receiving-officer-group" class="space-y-2 @if(($module ?? '') === 'new_kangis') hidden @endif" @if(($module ?? '') === 'new_kangis') aria-hidden="true" @endif>
                                                        <label for="receiving-officer"
                                                            class="block text-sm font-medium text-gray-700">Receiving Officer *</label>

                                                        @if(in_array(strtolower($module ?? ''), ['digital_request','digital-request']))
                                                            {{-- Digital Request: show read-only field only; NO <select id="receiving-officer"> so Select2 never initialises --}}
                                                            <input type="text"
                                                                value="{{ auth()->user()->first_name }} {{ auth()->user()->last_name }}"
                                                                class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm bg-gray-50 focus:outline-none"
                                                                readonly>
                                                            <input type="hidden" name="receiving_officer_id" value="{{ auth()->id() }}">
                                                            <input type="hidden" name="receiving_officer_name" value="{{ auth()->user()->first_name }} {{ auth()->user()->last_name }}">
                                                            <p class="text-xs text-gray-500">The officer receiving this file (Current User)</p>
                                                        @else
                                                            <select id="receiving-officer"
                                                                class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                                                <option value="">Select receiving officer</option>
                                                                @foreach ($receivingOfficers as $officer)
                                                                    <option value="{{ $officer->id }}">
                                                                        {{ $officer->first_name }} {{ $officer->last_name }}
                                                                    </option>
                                                                @endforeach
                                                                <option value="__ADD_OTHER__" class="font-bold text-blue-600">+ Add Other Officer...</option>
                                                            </select>
                                                            <p id="receiving-officer-hint" class="text-xs text-gray-500">Select a MLPP officer</p>
                                                        @endif
                                                    </div>
                                                    @if(($module ?? '') === 'kangis')
                                                    <p id="receiving-details-lock-note" class="hidden text-xs text-amber-600 font-medium mt-1">
                                                        Receiving Office and Officer unlock after DGIS and DG approvals.
                                                    </p>
                                                    @endif
                                                </div>
                                                @if(($module ?? '') === 'kangis')
                                                </div>{{-- close step-4 border-t wrapper --}}
                                                @endif


                                                <!-- Sending Officer / Requester Section -->
                                                <div class="space-y-2">
                                                    <label for="sending-officer-name"
                                                        class="block text-sm font-medium text-gray-700">
                                                        @if(in_array(strtolower($module ?? ''), ['digital_request','digital-request']))
                                                            Requester
                                                        @else
                                                            Sending Officer
                                                        @endif
                                                    </label>
                                                    <input type="text" id="sending-officer-name"
                                                        value="{{ auth()->user()->first_name }} {{ auth()->user()->last_name }}"
                                                        class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm bg-gray-50 focus:outline-none"
                                                        readonly>
                                                    <input type="hidden" name="sending_officer_id"
                                                        value="{{ auth()->user()->id }}">
                                                    <p class="text-xs text-gray-500">
                                                        @if(in_array(strtolower($module ?? ''), ['digital_request','digital-request']))
                                                            The officer requesting this file (Current User)
                                                        @else
                                                            The officer sending this file (Current User)
                                                        @endif
                                                    </p>
                                                </div>
                                                <div id="office-info" class="hidden p-3 bg-gray-50 rounded-lg">
                                                    <div class="flex items-center gap-2 mb-1">
                                                        <span id="office-badge"
                                                            class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800"></span>
                                                        <span id="office-code-badge"
                                                            class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800"></span>
                                                    </div>
                                                    <p id="office-department" class="text-sm text-gray-600"></p>
                                                </div>
                                                <div id="origin-office-info" class="hidden p-3 mt-3 bg-gray-50 rounded-lg">
                                                    <div class="flex items-center gap-2 mb-1">
                                                        <span id="origin-office-badge"
                                                            class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800"></span>
                                                        <span id="origin-office-code-badge"
                                                            class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800"></span>
                                                    </div>
                                                    <p id="origin-office-department" class="text-sm text-gray-600"></p>
                                                </div>

                                                {{-- KANGIS 4-Step Approval Workflow Toggle --}}
                                                @if(($module ?? '') === 'kangis')
                                                <div id="workflow-toggle-panel" class="mt-4 p-4 bg-gradient-to-r from-amber-50 to-yellow-50 rounded-lg border border-amber-200">
                                                    <div class="flex items-center justify-between">
                                                        <div class="flex items-center gap-3">
                                                            <div class="w-8 h-8 rounded-full bg-amber-100 flex items-center justify-center">
                                                                <i data-lucide="git-branch" class="h-4 w-4 text-amber-600"></i>
                                                            </div>
                                                            <div>
                                                                <h4 class="text-sm font-semibold text-amber-900">4-Step Approval Workflow</h4>
                                                                <p class="text-xs text-amber-700">Submission → Recommendation → Approval → Department Processing</p>
                                                            </div>
                                                        </div>
                                                        <label class="relative inline-flex items-center cursor-not-allowed" title="This workflow is required for KANGIS">
                                                            <input type="checkbox" id="workflow-3step-toggle" class="sr-only peer" checked disabled>
                                                            <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-amber-300 rounded-full peer peer-checked:after:translate-x-full rtl:peer-checked:after:-translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-amber-500"></div>
                                                        </label>
                                                    </div>

                                                    {{-- 4-Step Stepper (shown when toggle is ON) --}}
                                                    <div id="workflow-stepper" class="hidden mt-4">
                                                        <div class="flex items-center justify-between gap-1">
                                                            {{-- Step 1: Submission --}}
                                                            <div class="workflow-step flex-1 text-center" data-step="origin">
                                                                <div class="workflow-step-dot mx-auto w-10 h-10 rounded-full bg-pink-400 flex items-center justify-center shadow-md">
                                                                    <i data-lucide="building-2" class="h-5 w-5 text-white"></i>
                                                                </div>
                                                                <p class="mt-1 text-xs font-semibold text-pink-600">KANGIS Registry</p>
                                                            </div>
                                                            {{-- Arrow 1 --}}
                                                            <div class="flex flex-col items-center flex-shrink-0 px-1">
                                                                <p class="text-[10px] text-gray-500 font-medium mb-0.5">Step 1: Submission</p>
                                                                <div class="flex items-center">
                                                                    <div class="w-8 h-0.5 bg-gray-300"></div>
                                                                    <i data-lucide="chevron-right" class="h-3 w-3 text-gray-400 -ml-0.5"></i>
                                                                </div>
                                                            </div>
                                                            {{-- Step 2: Recommendation --}}
                                                            <div class="workflow-step flex-1 text-center" data-step="1">
                                                                <div class="workflow-step-dot mx-auto w-10 h-10 rounded-full bg-indigo-400 flex items-center justify-center shadow-md">
                                                                    <i data-lucide="user-cog" class="h-5 w-5 text-white"></i>
                                                                </div>
                                                                <p class="mt-1 text-xs font-semibold text-indigo-600">Director GIS</p>
                                                            </div>
                                                            {{-- Arrow 2 --}}
                                                            <div class="flex flex-col items-center flex-shrink-0 px-1">
                                                                <p class="text-[10px] text-gray-500 font-medium mb-0.5">Step 2: Recommendation</p>
                                                                <div class="flex items-center">
                                                                    <div class="w-8 h-0.5 bg-gray-300"></div>
                                                                    <i data-lucide="chevron-right" class="h-3 w-3 text-gray-400 -ml-0.5"></i>
                                                                </div>
                                                            </div>
                                                            {{-- Step 3: Approval --}}
                                                            <div class="workflow-step flex-1 text-center" data-step="2">
                                                                <div class="workflow-step-dot mx-auto w-10 h-10 rounded-full bg-green-400 flex items-center justify-center shadow-md">
                                                                    <i data-lucide="shield-check" class="h-5 w-5 text-white"></i>
                                                                </div>
                                                                <p class="mt-1 text-xs font-semibold text-green-600">DG KANGIS</p>
                                                            </div>
                                                            {{-- Arrow 3 --}}
                                                            <div class="flex flex-col items-center flex-shrink-0 px-1">
                                                                <p class="text-[10px] text-gray-500 font-medium mb-0.5">Step 3: Approval</p>
                                                                <div class="flex items-center">
                                                                    <div class="w-8 h-0.5 bg-gray-300"></div>
                                                                    <i data-lucide="chevron-right" class="h-3 w-3 text-gray-400 -ml-0.5"></i>
                                                                </div>
                                                            </div>
                                                            {{-- Step 4: Processing --}}
                                                            <div class="workflow-step flex-1 text-center" data-step="3">
                                                                <div class="workflow-step-dot mx-auto w-10 h-10 rounded-full bg-rose-300 flex items-center justify-center shadow-md">
                                                                    <i data-lucide="layers" class="h-5 w-5 text-white"></i>
                                                                </div>
                                                                <p class="mt-1 text-xs font-semibold text-rose-500">Department Queue</p>
                                                            </div>
                                                        </div>
                                                        <p class="mt-3 text-xs text-amber-700 text-center">
                                                            Files follow a controlled progression and move into Department Queue after DG approval.
                                                        </p>
                                                    </div>
                                                </div>
                                                @endif
                                                </fieldset>

                                            </div>
                                        </div>

                                        @if(($module ?? '') === 'new_kangis')
                                        {{-- New KANGIS 8-Step Units Workflow (mirrors NewKangisFTUnitsWorkFlow.php) --}}
                                        <div id="new-kangis-workflow-panel" class="p-4 bg-gradient-to-r from-amber-50 to-yellow-50 rounded-lg border border-amber-200 hidden">
                                            <div class="flex items-center gap-3 mb-4">
                                                <div class="w-8 h-8 rounded-full bg-amber-100 flex items-center justify-center flex-shrink-0">
                                                    <i data-lucide="git-branch" class="h-4 w-4 text-amber-600"></i>
                                                </div>
                                                <div>
                                                    <h4 class="text-sm font-semibold text-amber-900">New KANGIS File — 8-Step Tracking Workflow</h4>
                                                    <p class="text-xs text-amber-700">QR code scan auto-advances the file to the next stage &amp; triggers SMS to applicant</p>
                                                </div>
                                            </div>
                                            <ol class="relative border-l-2 border-amber-300 ml-3 space-y-3">
                                                <li class="ml-6">
                                                    <span class="absolute -left-3.5 flex items-center justify-center w-7 h-7 rounded-full bg-amber-600 text-white text-xs font-bold ring-2 ring-white">1</span>
                                                    <div class="flex flex-col">
                                                        <span class="text-xs font-semibold text-amber-900 uppercase tracking-wide">Origin</span>
                                                        <span class="text-sm text-gray-700">Customer Service <span class="text-gray-400">→ One Stop Shop (OSS)</span></span>
                                                        <span class="text-xs text-gray-500">Scan QR · Log Out · SMS sent to applicant</span>
                                                    </div>
                                                </li>
                                                <li class="ml-6">
                                                    <span class="absolute -left-3.5 flex items-center justify-center w-7 h-7 rounded-full bg-teal-600 text-white text-xs font-bold ring-2 ring-white">2</span>
                                                    <div class="flex flex-col">
                                                        <span class="text-xs font-semibold text-teal-900 uppercase tracking-wide">One Stop Shop (OSS) <span class="text-gray-400 font-normal">→ Verification</span></span>
                                                        <span class="text-xs text-gray-500">Scan QR · Log Out · SMS sent to applicant</span>
                                                    </div>
                                                </li>
                                                <li class="ml-6">
                                                    <span class="absolute -left-3.5 flex items-center justify-center w-7 h-7 rounded-full bg-sky-500 text-white text-xs font-bold ring-2 ring-white">3</span>
                                                    <div class="flex flex-col">
                                                        <span class="text-xs font-semibold text-sky-900 uppercase tracking-wide">Verification <span class="text-gray-400 font-normal">→ Geometry (GIS)</span></span>
                                                        <span class="text-xs text-gray-500">Scan QR · Log Out · SMS sent to applicant</span>
                                                    </div>
                                                </li>
                                                <li class="ml-6">
                                                    <span class="absolute -left-3.5 flex items-center justify-center w-7 h-7 rounded-full bg-blue-500 text-white text-xs font-bold ring-2 ring-white">4</span>
                                                    <div class="flex flex-col">
                                                        <span class="text-xs font-semibold text-blue-900 uppercase tracking-wide">Geometry (GIS) <span class="text-gray-400 font-normal">→ Vetting Committee</span></span>
                                                        <span class="text-xs text-gray-500">Scan QR · Log Out · SMS sent to applicant</span>
                                                    </div>
                                                </li>
                                                <li class="ml-6">
                                                    <span class="absolute -left-3.5 flex items-center justify-center w-7 h-7 rounded-full bg-emerald-500 text-white text-xs font-bold ring-2 ring-white">5</span>
                                                    <div class="flex flex-col">
                                                        <span class="text-xs font-semibold text-emerald-900 uppercase tracking-wide">Vetting Committee <span class="text-gray-400 font-normal">→ Pagination (Deeds)</span></span>
                                                        <span class="text-xs text-gray-500">Scan QR · Log Out · SMS sent to applicant</span>
                                                    </div>
                                                </li>
                                                <li class="ml-6">
                                                    <span class="absolute -left-3.5 flex items-center justify-center w-7 h-7 rounded-full bg-violet-500 text-white text-xs font-bold ring-2 ring-white">6</span>
                                                    <div class="flex flex-col">
                                                        <span class="text-xs font-semibold text-violet-900 uppercase tracking-wide">Pagination (Deeds) <span class="text-gray-400 font-normal">→ Production (GIS)</span></span>
                                                        <span class="text-xs text-gray-500">Scan QR · Log Out · SMS sent to applicant</span>
                                                    </div>
                                                </li>
                                                <li class="ml-6">
                                                    <span class="absolute -left-3.5 flex items-center justify-center w-7 h-7 rounded-full bg-indigo-500 text-white text-xs font-bold ring-2 ring-white">7</span>
                                                    <div class="flex flex-col">
                                                        <span class="text-xs font-semibold text-indigo-900 uppercase tracking-wide">Production (GIS) <span class="text-gray-400 font-normal">→ Collection (DG)</span></span>
                                                        <span class="text-xs text-gray-500">Scan QR · Log Out · SMS sent to applicant</span>
                                                    </div>
                                                </li>
                                                <li class="ml-6">
                                                    <span class="absolute -left-3.5 flex items-center justify-center w-7 h-7 rounded-full bg-rose-600 text-white text-xs font-bold ring-2 ring-white">8</span>
                                                    <div class="flex flex-col">
                                                        <span class="text-xs font-semibold text-rose-900 uppercase tracking-wide">Collection (DG) <span class="text-gray-400 font-normal">→ KANGIS Registry Log-In</span></span>
                                                        <span class="text-xs text-gray-500">Scan QR · Log In · SMS sent to applicant · Workflow complete</span>
                                                    </div>
                                                </li>
                                            </ol>
                                            <p class="mt-4 text-xs text-amber-700 flex items-center gap-1">
                                                <i data-lucide="info" class="h-3 w-3 flex-shrink-0"></i>
                                                When the SMS API key is not configured, a confirmation alert will show instead.
                                            </p>
                                        </div>
                                        @endif

                                        <!-- File Log Session Card -->
                                        <div class="bg-white rounded-lg border border-gray-200 shadow-sm">
                                            <div class="px-6 py-4 border-b border-gray-200">
                                                <h3 class="text-lg font-semibold text-gray-900 flex items-center gap-2">
                                                    <i data-lucide="hash" class="h-5 w-5"></i>
                                                    File Log Session
                                                </h3>
                                                <p class="text-sm text-gray-600 mt-1">Initial log entry details
                                                    (auto-populated)</p>
                                            </div>
                                            <div class="p-6 space-y-4">
                                                <div class="grid grid-cols-2 gap-4">
                                                    <div class="space-y-2">
                                                        <label for="log-id"
                                                            class="block text-sm font-medium text-gray-700">Log ID</label>
                                                        <input type="text" id="log-id" readonly
                                                            class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm bg-gray-50 font-mono text-sm">
                                                        <p class="text-xs text-gray-500">Auto-generated log identifier</p>
                                                    </div>
                                                    <div class="space-y-2">
                                                        <label for="log-in-time"
                                                            class="block text-sm font-medium text-gray-700">Log In
                                                            Time</label>
                                                        <input type="text" id="log-in-time" readonly
                                                            class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm bg-gray-50">
                                                        <p class="text-xs text-gray-500">Current time (auto-updated)</p>
                                                    </div>
                                                </div>
                                                <div class="grid grid-cols-2 gap-4">
                                                    <div class="space-y-2">
                                                        <label for="log-in-date"
                                                            class="block text-sm font-medium text-gray-700">Log In
                                                            Date</label>
                                                        <input type="text" id="log-in-date" readonly
                                                            class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm bg-gray-50">
                                                        <p class="text-xs text-gray-500">Current date (auto-updated)</p>
                                                    </div>
                                                    <div class="space-y-2">
                                                        <label for="log-out-time"
                                                            class="block text-sm font-medium text-gray-700">Log Out
                                                            Time</label>
                                                        <input type="text" id="log-out-time" readonly
                                                            class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm bg-gray-50">
                                                        <p class="text-xs text-gray-500">Will be set when file is logged out
                                                        </p>
                                                    </div>
                                                </div>
                                                <div class="space-y-2">
                                                    <label for="log-out-date"
                                                        class="block text-sm font-medium text-gray-700">Log Out Date</label>
                                                    <input type="text" id="log-out-date" readonly
                                                        class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm bg-gray-50">
                                                    <p class="text-xs text-gray-500">Will be set when file is logged out</p>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Action Buttons -->
                                        <div class="flex justify-end gap-2">
                                            <button id="resetFormBtn"
                                                class="px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                                                Reset
                                            </button>
                                            @if(($module ?? '') === 'kangis')
                                            <button id="saveFileLogBtn"
                                                class="inline-flex items-center px-6 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 min-w-[170px]">
                                                <i data-lucide="send" class="h-4 w-4 mr-2"></i>
                                                Send to Director GIS
                                            </button>
                                            @elseif(in_array(strtolower($module ?? ''), ['digital_request','digital-request']))
                                            <button id="saveFileLogBtn"
                                                class="inline-flex items-center px-6 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-violet-600 hover:bg-violet-700 min-w-[150px]">
                                                <i data-lucide="send" class="h-4 w-4 mr-2"></i>
                                                Send Request
                                            </button>
                                            @else
                                            <button id="saveFileLogBtn"
                                                class="inline-flex items-center px-6 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 min-w-[150px]">
                                                <i data-lucide="save" class="h-4 w-4 mr-2"></i>
                                                Save File Log
                                            </button>
                                            @endif
                                        </div>
                                    </div>

                                    <!-- Sidebar -->
                                    <div class="w-80 space-y-6">
                                        <!-- Current Date & Time Card -->
                                        <div class="bg-white rounded-lg border border-gray-200 shadow-sm">
                                            <div class="px-6 py-4 border-b border-gray-200">
                                                <h3 class="text-lg font-semibold text-gray-900 flex items-center gap-2">
                                                    <i data-lucide="clock" class="h-5 w-5"></i>
                                                    Current Date & Time
                                                </h3>
                                                <p class="text-sm text-gray-600 mt-1">Live timestamp for log entries</p>
                                            </div>
                                            <div class="p-6 space-y-4">
                                                <div class="text-center">
                                                    <div id="current-time" class="text-2xl font-mono font-bold">00:00:00
                                                    </div>
                                                    <div id="current-date" class="text-lg text-gray-600">0000-00-00</div>
                                                </div>
                                                <hr class="border-gray-200">
                                                <div class="space-y-3">
                                                    <div class="flex flex-col space-y-2">
                                                        <span class="text-sm font-medium text-gray-600">File No:</span>
                                                        <span id="sidebar-file-no"
                                                            class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-800 font-mono w-fit">-</span>
                                                    </div>

                                                    <div class="flex flex-col space-y-2">
                                                        <span class="text-sm font-medium text-gray-600">Log ID:</span>
                                                        <span id="sidebar-log-id"
                                                            class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-purple-100 text-purple-800 font-mono w-fit"></span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        @include('create_file_tracker_page.partials.quick-actions')
                                    </div>
                                </div>
                            </div>
                            @endif
                            <!-- File Tracker Log Tab -->
                            <div id="content-logs" class="main-tab-content {{ in_array($module ?? '', ['dgis', 'dg']) ? 'active' : 'hidden' }} p-6 space-y-6">
                                <!-- Header with User Info -->
                                <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                                    <div>
                                        <h4 class="text-xl font-semibold text-gray-900">All File Tracker Logs</h4>
                                        <p class="text-sm text-gray-600">Track and manage assigned files</p>
                                    </div>
                                    <div class="flex items-center gap-4 px-4 py-2 bg-white border border-gray-200 rounded-lg">
                                        <div class="w-8 h-8 rounded-full bg-blue-100 flex items-center justify-center overflow-hidden">
                                                @if(Auth::user()->profile)
              <img src="{{ asset('storage') . '/' . auth()->user()->profile }}" alt="Profile"
                class="w-full h-full object-cover">
            @else
              <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-gray-500" fill="none" viewBox="0 0 24 24"
                stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
              </svg>
            @endif
                                        </div>
                                        <div>
                                            <p class="text-sm font-medium">Current Handler</p>
                                            <p class="text-sm text-gray-600">{{ auth()->user()->first_name }} {{ auth()->user()->last_name }} (You)</p>
                                        </div>
                                    </div>
                                </div>

                                <!-- Stats Summary -->
                                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
                                    <div class="bg-white rounded-lg border border-gray-200 p-6">
                                        <div class="flex items-center justify-between">
                                            <div>
                                                <p class="text-sm text-gray-600">My Files</p>
                                                <p class="text-2xl font-bold text-blue-600" id="total-trackers">0</p>
                                            </div>
                                            <div class="w-12 h-12 rounded-full bg-blue-100 flex items-center justify-center">
                                                <i data-lucide="folder" class="h-6 w-6 text-blue-600"></i>
                                            </div>
                                        </div>
                                        <p class="text-xs text-gray-500 mt-2">Files currently assigned to you</p>
                                    </div>
                                    <div class="bg-white rounded-lg border border-gray-200 p-6">
                                        <div class="flex items-center justify-between">
                                            <div>
                                                <p class="text-sm text-gray-600">Awaiting Acceptance</p>
                                                <p class="text-2xl font-bold text-green-600" id="active-trackers">0</p>
                                            </div>
                                            <div class="w-12 h-12 rounded-full bg-green-100 flex items-center justify-center">
                                                <i data-lucide="clock" class="h-6 w-6 text-green-600"></i>
                                            </div>
                                        </div>
                                        <p class="text-xs text-gray-500 mt-2">Files pending your acceptance</p>
                                    </div>
                                    <div class="bg-white rounded-lg border border-gray-200 p-6">
                                        <div class="flex items-center justify-between">
                                            <div>
                                                <p class="text-sm text-gray-600">Other Handler's Files</p>
                                                <p class="text-2xl font-bold text-red-600" id="high-priority">0</p>
                                            </div>
                                            <div class="w-12 h-12 rounded-full bg-red-100 flex items-center justify-center">
                                                <i data-lucide="users" class="h-6 w-6 text-red-600"></i>
                                            </div>
                                        </div>
                                        <p class="text-xs text-gray-500 mt-2">Files assigned to other handlers</p>
                                    </div>
                                    <div class="bg-white rounded-lg border border-gray-200 p-6">
                                        <div class="flex items-center justify-between">
                                            <div>
                                                <p class="text-sm text-gray-600">Completed</p>
                                                <p class="text-2xl font-bold text-gray-600" id="total-movements">0</p>
                                            </div>
                                            <div class="w-12 h-12 rounded-full bg-gray-100 flex items-center justify-center">
                                                <i data-lucide="check-circle" class="h-6 w-6 text-gray-600"></i>
                                            </div>
                                        </div>
                                        <p class="text-xs text-gray-500 mt-2">Files marked as completed</p>
                                    </div>
                                </div>

                                @if(($module ?? '') === 'dg')
                                <!-- DG dashboard — file streams reaching the Director General -->
                                <div class="mt-4">
                                    <div class="flex items-center gap-2 mb-2">
                                        <i data-lucide="layout-dashboard" class="h-4 w-4 text-emerald-700"></i>
                                        <h3 class="text-sm font-semibold text-emerald-800 uppercase tracking-wide">DG File Streams</h3>
                                        <span class="text-xs text-gray-500">Breakdown by source</span>
                                    </div>
                                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3">
                                        <!-- KANGIS (general / legacy) -->
                                        <div class="bg-white rounded-lg border border-blue-200 p-6">
                                            <div class="flex items-center justify-between">
                                                <div>
                                                    <p class="text-sm text-gray-600">KANGIS</p>
                                                    <p class="text-2xl font-bold text-blue-600" id="dg-kangis-count">0</p>
                                                </div>
                                                <div class="w-12 h-12 rounded-full bg-blue-100 flex items-center justify-center">
                                                    <i data-lucide="building-2" class="h-6 w-6 text-blue-600"></i>
                                                </div>
                                            </div>
                                            <p class="text-xs text-gray-500 mt-2">General KANGIS approval flow (3-step)</p>
                                        </div>

                                        <!-- KANGIS New Files (9-stage) -->
                                        <div class="bg-white rounded-lg border border-emerald-200 p-6">
                                            <div class="flex items-center justify-between">
                                                <div>
                                                    <p class="text-sm text-gray-600">KANGIS New Files</p>
                                                    <p class="text-2xl font-bold text-emerald-600" id="dg-new-kangis-count">0</p>
                                                </div>
                                                <div class="w-12 h-12 rounded-full bg-emerald-100 flex items-center justify-center">
                                                    <i data-lucide="star" class="h-6 w-6 text-emerald-600"></i>
                                                </div>
                                            </div>
                                            <p class="text-xs text-gray-500 mt-2">9-stage New KANGIS workflow (KN-prefixed files)</p>
                                        </div>

                                        <!-- Cross-Registry Requests -->
                                        <div class="bg-white rounded-lg border border-purple-200 p-6">
                                            <div class="flex items-center justify-between">
                                                <div>
                                                    <div class="flex items-center gap-2">
                                                        <p class="text-sm text-gray-600">Cross-Registry Requests</p>
                                                        <span class="text-[10px] font-semibold uppercase tracking-wide text-purple-700 bg-purple-100 px-1.5 py-0.5 rounded">Files</span>
                                                    </div>
                                                    <p class="text-2xl font-bold text-purple-600" id="dg-cross-registry-count">0</p>
                                                </div>
                                                <div class="w-12 h-12 rounded-full bg-purple-100 flex items-center justify-center">
                                                    <i data-lucide="send" class="h-6 w-6 text-purple-600"></i>
                                                </div>
                                            </div>
                                            <p class="text-xs text-gray-500 mt-2">Number of bilateral Land ↔ KANGIS file-tracker records</p>
                                        </div>
                                    </div>
                                </div>
                                @endif

                                @if(in_array(($module ?? ''), ['new_kangis', 'kangis', 'dg', 'dgis'], true))
                                <!-- Extra dashboard metrics (New KANGIS / KANGIS / DG / DGIS) -->
                                <div class="mt-4 flex items-center gap-2 mb-2">
                                    <i data-lucide="activity" class="h-4 w-4 text-indigo-700"></i>
                                    <h3 class="text-sm font-semibold text-indigo-800 uppercase tracking-wide">Tracking Activity</h3>
                                    <span class="text-xs text-gray-500">Volume &amp; people</span>
                                </div>
                                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3">
                                    <div class="bg-white rounded-lg border border-amber-200 p-6">
                                        <div class="flex items-center justify-between">
                                            <div>
                                                <p class="text-sm text-gray-600">Files Tracked Today</p>
                                                <p class="text-2xl font-bold text-amber-600" id="kn-tracked-today">0</p>
                                            </div>
                                            <div class="w-12 h-12 rounded-full bg-amber-100 flex items-center justify-center">
                                                <i data-lucide="calendar-clock" class="h-6 w-6 text-amber-600"></i>
                                            </div>
                                        </div>
                                        <p class="text-xs text-gray-500 mt-2">No. of files tracked per day (today)</p>
                                    </div>
                                    <div class="bg-white rounded-lg border border-indigo-200 p-6">
                                        <div class="flex items-center justify-between">
                                            <div>
                                                <p class="text-sm text-gray-600">Total Files Tracked</p>
                                                <p class="text-2xl font-bold text-indigo-600" id="kn-total-tracked">0</p>
                                            </div>
                                            <div class="w-12 h-12 rounded-full bg-indigo-100 flex items-center justify-center">
                                                <i data-lucide="layers" class="h-6 w-6 text-indigo-600"></i>
                                            </div>
                                        </div>
                                        <p class="text-xs text-gray-500 mt-2">Total no. of files tracked</p>
                                    </div>
                                    <div class="bg-white rounded-lg border border-emerald-200 p-6">
                                        <div class="flex items-center justify-between">
                                            <div>
                                                <div class="flex items-center gap-2">
                                                    <p class="text-sm text-gray-600">Unique Requesters</p>
                                                    <span class="text-[10px] font-semibold uppercase tracking-wide text-emerald-700 bg-emerald-100 px-1.5 py-0.5 rounded">People</span>
                                                </div>
                                                <p class="text-2xl font-bold text-emerald-600" id="kn-unique-requesters">0</p>
                                            </div>
                                            <div class="w-12 h-12 rounded-full bg-emerald-100 flex items-center justify-center">
                                                <i data-lucide="user-check" class="h-6 w-6 text-emerald-600"></i>
                                            </div>
                                        </div>
                                        <p class="text-xs text-gray-500 mt-2">Distinct people/offices who originated tracked files (not file count)</p>
                                    </div>
                                </div>
                                @endif

                                <!-- Control Panel -->
                                <div class="bg-white rounded-lg border border-gray-200 shadow-sm p-6">
                                    <div class="flex flex-col md:flex-row md:items-center justify-between mb-6 gap-4">
                                        <div>
                                            <h3 class="text-lg font-semibold text-gray-900">Control Panel</h3>
                                            <p class="text-sm text-gray-600 mt-1">Manage your file trackers and filter results</p>
                                        </div>
                                        <div class="flex flex-wrap items-end gap-2">
                                            <div class="flex flex-col">
                                                <label for="mr-date-from" class="text-xs font-medium text-gray-600 mb-1">From Date</label>
                                                <input type="date" id="mr-date-from"
                                                    class="px-3 py-2 border border-gray-300 rounded-md shadow-sm text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                            </div>
                                            <div class="flex flex-col">
                                                <label for="mr-date-to" class="text-xs font-medium text-gray-600 mb-1">To Date</label>
                                                <input type="date" id="mr-date-to"
                                                    class="px-3 py-2 border border-gray-300 rounded-md shadow-sm text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                            </div>
                                            <!-- Export dropdown -->
                                            <div class="relative" id="export-dropdown-wrapper">
                                                <button id="export-dropdown-btn"
                                                    class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 transition-colors">
                                                    <i data-lucide="download" class="h-4 w-4 mr-2"></i>
                                                    Export
                                                    <i data-lucide="chevron-down" class="h-3 w-3 ml-2"></i>
                                                </button>
                                                <div id="export-dropdown-menu"
                                                     class="hidden absolute right-0 mt-2 w-48 bg-white rounded-lg shadow-lg border border-gray-100 z-30">
                                                    <button id="export-logs"
                                                        class="flex w-full items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-slate-50 rounded-t-lg">
                                                        <i data-lucide="file-text" class="h-4 w-4 text-emerald-500"></i>
                                                        Export CSV
                                                    </button>
                                                    <button id="export-pdf-logs"
                                                        class="flex w-full items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-slate-50 rounded-b-lg">
                                                        <i data-lucide="file" class="h-4 w-4 text-rose-500"></i>
                                                        Export PDF
                                                    </button>
                                                </div>
                                            </div>
                                            <button id="refresh-logs"
                                                class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 transition-colors">
                                                <i data-lucide="refresh-cw" class="h-4 w-4 mr-2"></i>
                                                Refresh
                                            </button>
                                        </div>
                                    </div>
                                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                                        <!-- File Status Filter Card -->
                                        <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                                            <h4 class="text-sm font-medium text-gray-700 mb-3 flex items-center gap-2">
                                                <i data-lucide="user-check" class="h-4 w-4"></i>
                                                File Status Filter
                                            </h4>
                                            <div class="space-y-2">
                                                <select id="status-filter" class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                                    <option value="all">All Files</option>
                                                    <option value="my-files">My Files Only</option>
                                                    <option value="awaiting">Awaiting My Acceptance</option>
                                                    <option value="other-handlers">Other Handler's Files</option>
                                                    <option value="completed">Completed Files</option>
                                                </select>
                                                <div class="text-xs text-gray-500 mt-2">
                                                    <p>• <span class="text-green-600 font-medium">Green cards</span>: Files awaiting your acceptance</p>
                                                    <p>• <span class="text-red-600 font-medium">Red cards</span>: Files belonging to other handlers</p>
                                                    <p>• <span class="text-blue-600 font-medium">Blue cards</span>: Files assigned to you</p>
                                                    <p>• <span class="text-gray-600 font-medium">Gray cards</span>: Completed files</p>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Priority Filters Card -->
                                        <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                                            <h4 class="text-sm font-medium text-gray-700 mb-3 flex items-center gap-2">
                                                <i data-lucide="filter" class="h-4 w-4"></i>
                                                Priority Filters
                                            </h4>
                                            <div class="flex flex-wrap gap-2">
                                                <button class="filter-btn px-3 py-1.5 text-xs bg-gray-100 text-gray-800 rounded-md hover:bg-gray-200 transition-colors ring-2 ring-offset-1 ring-blue-500" data-priority="all">
                                                    All
                                                </button>
                                                <button class="filter-btn px-3 py-1.5 text-xs bg-green-100 text-green-800 rounded-md hover:bg-green-200 transition-colors" data-priority="LOW">
                                                    Low Priority
                                                </button>
                                                <button class="filter-btn px-3 py-1.5 text-xs bg-yellow-100 text-yellow-800 rounded-md hover:bg-yellow-200 transition-colors" data-priority="MEDIUM">
                                                    Medium
                                                </button>
                                                <button class="filter-btn px-3 py-1.5 text-xs bg-red-100 text-red-800 rounded-md hover:bg-red-200 transition-colors" data-priority="HIGH">
                                                    High Priority
                                                </button>
                                            </div>
                                        </div>

                                        <!-- Search Card -->
                                        <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                                            <h4 class="text-sm font-medium text-gray-700 mb-3 flex items-center gap-2">
                                                <i data-lucide="search" class="h-4 w-4"></i>
                                                Search Files
                                            </h4>
                                            <div class="relative">
                                                <input type="text" id="search-logs" placeholder="Search by file name, number, or handler..."
                                                    autocomplete="off"
                                                    class="w-full px-3 py-2 pl-10 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors">
                                                <i data-lucide="search" class="absolute left-3 top-2.5 h-4 w-4 text-gray-400"></i>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- File Trackers Table Section -->
                                <div class="bg-white rounded-lg border border-gray-200 shadow-sm">
                                    <div class="px-6 py-4 border-b border-gray-200 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                                        <div>
                                            <h3 class="text-lg font-semibold text-gray-900 flex items-center gap-2">
                                                <i data-lucide="file-text" class="h-5 w-5"></i>
                                                File Log Table
                                            </h3>
                                            <p class="text-sm text-gray-600 mt-1">All file trackers with handler assignment status</p>
                                            <div class="mt-2 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-gray-500">
                                                <span class="font-medium text-gray-600">Status Indicators:</span>
                                                <span class="inline-flex items-center gap-1"><span class="h-2 w-2 rounded-full bg-green-500"></span>Green — still within timeline</span>
                                                <span class="inline-flex items-center gap-1"><span class="h-2 w-2 rounded-full bg-amber-500"></span>Amber — about to timeout</span>
                                                <span class="inline-flex items-center gap-1"><span class="h-2 w-2 rounded-full bg-red-500"></span>Red — overdue</span>
                                            </div>
                                        </div>
                                        <div class="flex items-center gap-4">
                                            <div class="text-sm text-gray-500">
                                                <span id="tracker-count">0</span> files found
                                            </div>
                                            <div class="flex items-center gap-2">
                                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-blue-50 text-blue-700 border border-blue-100">My Files</span>
                                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-50 text-green-700 border border-green-100">Awaiting</span>
                                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-red-50 text-red-700 border border-red-100">Other's Files</span>
                                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-700 border border-gray-200">Completed</span>
                                            </div>
                                        </div>
                                    </div>
                                    <!-- Sub-tabs: Active vs Completed (logged back in) files -->
                                    <div class="px-6 pt-4 border-b border-gray-200">
                                        <nav class="-mb-px flex gap-6" aria-label="File log views">
                                            <button type="button" id="file-log-view-active" data-log-view="active"
                                                class="file-log-view-tab whitespace-nowrap border-b-2 border-blue-600 px-1 pb-3 text-sm font-semibold text-blue-600">
                                                Active Files
                                                <span id="file-log-active-count" class="ml-1 inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-blue-50 text-blue-700 border border-blue-100">0</span>
                                            </button>
                                            <button type="button" id="file-log-view-completed" data-log-view="completed"
                                                class="file-log-view-tab whitespace-nowrap border-b-2 border-transparent px-1 pb-3 text-sm font-medium text-gray-500 hover:text-gray-700 hover:border-gray-300">
                                                Completed Files
                                                <span id="file-log-completed-count" class="ml-1 inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-700 border border-gray-200">0</span>
                                            </button>
                                        </nav>
                                    </div>
                                    <div id="trackers-container" class="p-6 space-y-6">
                                        <!-- File trackers will be dynamically injected here -->
                                    </div>
                                </div>
                            </div>
                        </div>

                        @if(false) {{-- Registry Checkout hidden for now --}}
                        @if(in_array($module ?? '', ['kangis', 'sltr', 'st']))
                        <!-- Registry Checkout Approval Tab -->
                        <div id="content-checkout" class="main-tab-content hidden">
                            @include('create_file_tracker_page.partials.checkout-approval')
                        </div>
                        @endif
                        @endif

                        <!-- Preview Dialog -->
                        <div id="preview-dialog" class="dialog-overlay">
                            <div class="dialog-content max-w-7xl">
                                <div class="flex items-center justify-between pb-4 border-b border-gray-200">
                                    <div>
                                        <h2 class="text-xl font-bold text-gray-900">File Tracker Preview</h2>
                                        <p class="text-sm text-gray-600 mt-1">Review all details before saving</p>
                                    </div>
                                    <button id="close-preview" class="text-gray-400 hover:text-gray-600 p-1">
                                        <i data-lucide="x" class="h-6 w-6"></i>
                                    </button>
                                </div>
                                <div id="preview-content" class="py-4">
                                    <!-- Preview content will be dynamically generated -->
                                </div>

                                @if(in_array(strtolower($module ?? ''), ['digital_request','digital-request']))
                                {{-- ── Digital Signature Verification Panel (Digital Request only) ── --}}
                                <div id="dr-sig-panel" class="border-t border-green-100 pt-4 mt-1">
                                    <div class="flex items-center gap-2 mb-3">
                                        <div class="flex h-8 w-8 items-center justify-center rounded-xl bg-green-100 shrink-0">
                                            <i data-lucide="shield-check" class="h-4 w-4 text-green-600"></i>
                                        </div>
                                        <div>
                                            <h4 class="text-sm font-bold text-slate-800">Digital Signature Verification</h4>
                                            <p class="text-xs text-slate-500">Verify your identity to sign and send this request.</p>
                                        </div>
                                    </div>

                                    <div class="rounded-xl border border-green-100 bg-gradient-to-br from-green-50/80 to-white p-4 space-y-3">
                                        <input type="hidden" id="dr-sig-verified" value="0">

                                        <div class="grid gap-3 sm:grid-cols-3">
                                            {{-- Method --}}
                                            <div class="space-y-1">
                                                <label class="block text-xs font-semibold text-slate-600 uppercase tracking-wide">Verification Method</label>
                                                <select id="dr-sig-method"
                                                    class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 focus:border-green-500 focus:ring-2 focus:ring-green-100">
                                                    <option value="">Select Method</option>
                                                    <option value="email">Email OTP</option>
                                                    <option value="sms">SMS OTP</option>
                                                    <option value="password">Password Confirmation</option>
                                                </select>
                                            </div>

                                            {{-- OTP Code --}}
                                            <div class="space-y-1" id="dr-sig-otp-wrapper" style="display:none">
                                                <label class="block text-xs font-semibold text-slate-600 uppercase tracking-wide">OTP Code</label>
                                                <div class="flex gap-2">
                                                    <input type="text" id="dr-sig-otp-code" maxlength="6" placeholder="Enter OTP"
                                                        class="flex-1 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:border-green-500 focus:ring-2 focus:ring-green-100">
                                                    <button type="button" id="dr-sig-send-otp"
                                                        class="inline-flex items-center justify-center rounded-lg bg-indigo-600 hover:bg-indigo-700 px-3 py-2 text-xs font-bold text-white whitespace-nowrap shadow-sm">
                                                        Send OTP
                                                    </button>
                                                </div>
                                            </div>

                                            {{-- Password --}}
                                            <div class="space-y-1" id="dr-sig-pwd-wrapper" style="display:none">
                                                <label class="block text-xs font-semibold text-slate-600 uppercase tracking-wide">Confirm Password</label>
                                                <input type="password" id="dr-sig-password" placeholder="Your account password"
                                                    class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:border-green-500 focus:ring-2 focus:ring-green-100">
                                            </div>
                                        </div>

                                        <div class="flex items-center gap-3 flex-wrap">
                                            <button type="button" id="dr-sig-verify-btn"
                                                class="inline-flex items-center gap-1.5 rounded-lg bg-green-600 hover:bg-green-700 px-4 py-2 text-xs font-bold text-white shadow-sm disabled:opacity-50 disabled:cursor-not-allowed">
                                                <i data-lucide="shield-check" class="h-3.5 w-3.5"></i>
                                                <span id="dr-sig-verify-text">Verify</span>
                                            </button>
                                            <span id="dr-sig-feedback" class="text-xs font-medium text-slate-500"></span>

                                            <div id="dr-sig-img-wrap" class="hidden ml-auto flex items-center gap-2">
                                                <img id="dr-sig-img" src="" alt="Your Signature"
                                                    class="h-9 object-contain border border-green-300 rounded bg-white px-2 py-1">
                                                <span class="inline-flex items-center gap-1 text-[10px] font-black text-green-700 uppercase tracking-wide">
                                                    <i data-lucide="check-circle" class="h-3 w-3"></i> Verified
                                                </span>
                                            </div>
                                        </div>

                                        <div class="text-right text-xs text-slate-400 italic border-t border-slate-100 pt-2">
                                            Signing as: <strong class="text-slate-600">{{ trim(auth()->user()->first_name . ' ' . auth()->user()->last_name) }}</strong>
                                        </div>
                                    </div>
                                </div>
                                @endif

                                <div class="flex justify-end gap-3 pt-4 border-t border-gray-200 mt-2">
                                    <button id="close-preview-btn"
                                        class="px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 transition-colors">
                                        Cancel
                                    </button>
                                    @if(in_array(strtolower($module ?? ''), ['digital_request','digital-request']))
                                    <button id="save-tracker-btn" disabled
                                        class="inline-flex items-center px-6 py-2 border border-transparent rounded-md shadow-md text-sm font-medium text-white bg-green-600 hover:bg-green-700 transition-colors opacity-50 cursor-not-allowed">
                                        <i data-lucide="send" class="h-4 w-4 mr-2"></i>
                                        Send Request
                                    </button>
                                    @else
                                    <button id="save-tracker-btn"
                                        class="inline-flex items-center px-6 py-2 border border-transparent rounded-md shadow-md text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 transition-colors">
                                        <i data-lucide="save" class="h-4 w-4 mr-2"></i>
                                        Save File Tracker
                                    </button>
                                    @endif
                                </div>
                            </div>
                        </div>

                        <!-- Notes Dialog for Adding New Log Entries -->
                        <div id="notes-dialog" class="dialog-overlay">
                            <div class="dialog-content max-w-md">
                                <div class="flex items-center justify-between mb-4">
                                    <div>
                                        <h2 class="text-lg font-semibold text-gray-900">Update Movement</h2>
                                        <p class="text-sm text-gray-600 mt-1">Confirm the next office and receiving officer
                                            before handing over the file.</p>
                                    </div>
                                    <button id="close-notes" class="text-gray-400 hover:text-gray-600">
                                        <i data-lucide="x" class="h-6 w-6"></i>
                                    </button>
                                </div>
                                <div class="space-y-4">
                                    <div
                                        class="rounded-lg border border-blue-100 bg-blue-50 px-4 py-3 text-sm text-blue-800">
                                        <p class="font-semibold text-blue-900">Destination</p>
                                        <p id="movement-destination-label" class="mt-1 text-blue-900">Select a destination
                                            office to continue.</p>
                                        <p class="mt-2 text-xs text-blue-700">Transfers only unlock after you have accepted
                                            the file. We'll block further transfers once it leaves your office.</p>
                                    </div>
                                    <div class="space-y-2">
                                        <label for="movement-receiving-officer"
                                            class="block text-sm font-medium text-gray-700">New receiving officer *</label>
                                        <select id="movement-receiving-officer"
                                            class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                            <option value="">Select receiving officer</option>
                                        </select>
                                        <p class="text-xs text-gray-500">This officer will approve the file before it can
                                            move again.</p>
                                    </div>
                                    <div class="space-y-2">
                                        <label for="new-log-notes" class="block text-sm font-medium text-gray-700">Transfer
                                            notes</label>
                                        <textarea id="new-log-notes" rows="4"
                                            placeholder="Reason the file is heading to this office..."
                                            class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"></textarea>
                                        <p class="text-xs text-gray-500">These notes are shared with the next officer.</p>
                                    </div>
{{--                                     <div class="rounded-md border border-amber-200 bg-amber-50 px-4 py-3">
                                        <label class="flex items-start gap-2 text-sm text-amber-800">
                                            <input type="checkbox" id="movement-override-toggle"
                                                class="mt-1 rounded border-amber-300 text-amber-600 focus:ring-amber-500">
                                            <span>
                                                Receiver is beside me — mark this file as accepted immediately.
                                                <span id="movement-override-help"
                                                    class="block text-xs text-amber-700 mt-1">Only use this when the
                                                    receiving officer physically confirms the handover in person.</span>
                                            </span>
                                        </label>
                                    </div> --}}
                                    <div id="movement-error"
                                        class="hidden rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">
                                    </div>
                                </div>
                                <div class="flex justify-end gap-2 mt-6">
                                    <button id="cancel-notes"
                                        class="px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                                        Cancel
                                    </button>
                                    <button id="confirm-notes"
                                        class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700">
                                        Log movement
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Update Log Dialog -->
                        <div id="update-log-dialog" class="dialog-overlay">
                            <div class="dialog-content max-w-xl">
                                <div class="flex items-start justify-between pb-4 border-b border-gray-200">
                                    <div>
                                        <h2 class="text-xl font-bold text-gray-900">Correct Log Entry</h2>
                                        <p class="text-sm text-gray-600 mt-1">Fix the receiving officer or office for this
                                            movement.</p>
                                    </div>
                                    <button id="update-log-close" class="text-gray-400 hover:text-gray-600">
                                        <i data-lucide="x" class="h-6 w-6"></i>
                                    </button>
                                </div>

                                <div class="space-y-4 py-4">
                                    <div
                                        class="rounded-lg border border-gray-200 bg-gray-50 px-4 py-3 text-sm text-gray-600">
                                        <p class="font-semibold text-gray-800">
                                            File <span id="update-log-file-label" class="font-mono text-blue-600"></span>
                                        </p>
                                        <p class="mt-1">
                                            Log Entry <span id="update-log-entry-label"
                                                class="font-mono text-gray-700"></span>
                                        </p>
                                    </div>

                                    <div class="space-y-2">
                                        <label for="update-log-office"
                                            class="block text-sm font-medium text-gray-700">Current Office *</label>
                                        <select id="update-log-office"
                                            class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/40">
                                            <option value="">Select office</option>
                                        </select>
                                    </div>

                                    <div class="space-y-2">
                                        <label for="update-log-receiving-officer"
                                            class="block text-sm font-medium text-gray-700">Receiving Officer *</label>
                                        <select id="update-log-receiving-officer"
                                            class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/40">
                                            <option value="">Select receiving officer</option>
                                        </select>
                                    </div>

                                    <div id="update-log-error"
                                        class="hidden rounded-md border border-red-200 bg-red-50 px-4 py-2 text-sm text-red-700">
                                    </div>
                                </div>

                                <div class="flex justify-end gap-3 border-t border-gray-200 pt-4">
                                    <button id="update-log-cancel"
                                        class="px-4 py-2 text-sm font-medium text-gray-700 border border-gray-300 rounded-md shadow-sm bg-white hover:bg-gray-50">
                                        Cancel
                                    </button>
                                    <button id="update-log-save"
                                        class="inline-flex items-center px-5 py-2 text-sm font-semibold text-white bg-blue-600 rounded-md shadow-sm hover:bg-blue-700">
                                        <i data-lucide="save" class="mr-2 h-4 w-4"></i>
                                        Update Log
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Create Receiving Officer Dialog -->
                        <div id="new-officer-dialog" class="dialog-overlay">
                            <div class="dialog-content max-w-md">
                                <div class="flex items-center justify-between pb-4 border-b border-gray-200">
                                    <div>
                                        <h2 class="text-xl font-bold text-gray-900">Add Receiving Officer</h2>
                                        <p class="text-sm text-gray-600 mt-1">Create the officer record without leaving this
                                            page.</p>
                                    </div>
                                    <button id="new-officer-close" class="text-gray-400 hover:text-gray-600">
                                        <i data-lucide="x" class="h-6 w-6"></i>
                                    </button>
                                </div>
                                <form id="new-officer-form" class="space-y-4 py-4">
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <div class="space-y-2">
                                            <label for="new-officer-first-name"
                                                class="block text-sm font-medium text-gray-700">First Name *</label>
                                            <input type="text" id="new-officer-first-name"
                                                class="block w-full rounded-md border border-gray-300 px-3 py-2 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/40"
                                                placeholder="e.g. Aisha">
                                        </div>
                                        <div class="space-y-2">
                                            <label for="new-officer-last-name"
                                                class="block text-sm font-medium text-gray-700">Last Name *</label>
                                            <input type="text" id="new-officer-last-name"
                                                class="block w-full rounded-md border border-gray-300 px-3 py-2 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/40"
                                                placeholder="e.g. Musa">
                                        </div>
                                    </div>
                                    {{-- Optional fields hidden for now --}}
                                    <div class="hidden">
                                        <input type="text" id="new-officer-phone">
                                        <input type="email" id="new-officer-email">
                                        <input type="text" id="new-officer-office">
                                        <input type="text" id="new-officer-designation">
                                        <textarea id="new-officer-notes"></textarea>
                                    </div>
                                    <div id="new-officer-error"
                                        class="hidden rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">
                                    </div>
                                    <div class="flex justify-end gap-3 pt-2 border-t border-gray-200 pt-4">
                                        <button type="button" id="new-officer-cancel"
                                            class="px-4 py-2 text-sm font-medium text-gray-700 border border-gray-300 rounded-md shadow-sm bg-white hover:bg-gray-50">Cancel</button>
                                        <button type="submit" id="new-officer-submit"
                                            class="inline-flex items-center px-5 py-2 text-sm font-semibold text-white bg-blue-600 rounded-md shadow-sm hover:bg-blue-700">
                                            <i data-lucide="user-plus" class="mr-2 h-4 w-4"></i>
                                            Create Officer
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>

                        <!-- Assignment Workflow Dialog -->
                        <div id="assignment-dialog" class="dialog-overlay">
                            <div class="dialog-content max-w-3xl">
                                <div class="flex items-center justify-between pb-4 border-b border-gray-200">
                                    <div>
                                        <h2 class="text-xl font-bold text-gray-900">Complete Logout &amp; Assign</h2>
                                        <p class="text-sm text-gray-600 mt-1">Finalize the handover and notify the next
                                            handler immediately.</p>
                                    </div>
                                    <button id="assignment-close" class="text-gray-400 hover:text-gray-600">
                                        <i data-lucide="x" class="h-6 w-6"></i>
                                    </button>
                                </div>

                                <div id="assignment-status"
                                    class="hidden mt-4 rounded-md border border-blue-100 bg-blue-50 px-4 py-3 text-sm text-blue-700">
                                </div>

                                <div id="assignment-loading" class="py-12 text-center text-gray-600">
                                    <div
                                        class="mx-auto mb-4 h-12 w-12 animate-spin rounded-full border-2 border-blue-200 border-t-blue-600">
                                    </div>
                                    <p class="text-sm font-medium">Loading assignment directory...</p>
                                </div>

                                <div id="assignment-form" class="hidden space-y-6">
                                    <div id="assignment-summary"
                                        class="rounded-lg border border-gray-200 bg-gray-50 p-4 text-sm text-gray-700">
                                    </div>

                                    <div class="grid gap-4 md:grid-cols-2">
                                        <div class="space-y-2">
                                            <label for="assignment-office" class="text-sm font-medium text-gray-700">Forward
                                                to Office *</label>
                                            <select id="assignment-office"
                                                class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/40">
                                                <option value="">Select office</option>
                                            </select>
                                        </div>
                                        <div class="space-y-2">
                                            <label for="assignment-user" class="text-sm font-medium text-gray-700">Assign to
                                                Officer *</label>
                                            <select id="assignment-user"
                                                class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/40">
                                                <option value="">Select officer</option>
                                            </select>
                                            <p id="assignment-user-hint" class="text-xs text-gray-500">Type to search by
                                                name or department.</p>
                                        </div>
                                    </div>

                                    <div class="space-y-2">
                                        <label for="assignment-note" class="text-sm font-medium text-gray-700">Instructions
                                            for Recipient</label>
                                        <textarea id="assignment-note" rows="3"
                                            class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/40"
                                            placeholder="Highlight next steps or special requirements..."></textarea>
                                    </div>

                                    <div class="flex flex-wrap items-center gap-4">
                                        <label class="inline-flex items-center gap-2 text-sm text-gray-600">
                                            <input type="checkbox" id="assignment-email"
                                                class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                            Send email notification
                                        </label>
                                        <label class="inline-flex items-center gap-2 text-sm text-gray-600">
                                            <input type="checkbox" id="assignment-sms"
                                                class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                            Send SMS notification
                                        </label>
                                    </div>

                                    <div id="assignment-alert"
                                        class="hidden rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                                    </div>
                                </div>

                                <div
                                    class="mt-6 flex flex-wrap items-center justify-end gap-3 border-t border-gray-200 pt-4">
                                    <button id="assignment-cancel" type="button"
                                        class="px-4 py-2 text-sm font-medium text-gray-700 border border-gray-300 rounded-md shadow-sm bg-white hover:bg-gray-50">
                                        Cancel
                                    </button>
                                    <button id="assignment-skip" type="button"
                                        class="px-3 py-2 text-sm font-medium text-gray-500 hover:text-gray-700">
                                        Complete without assignment
                                    </button>
                                    <button id="assignment-submit" type="button"
                                        class="inline-flex items-center px-6 py-2 text-sm font-semibold text-white bg-blue-600 rounded-md shadow-sm hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-60">
                                        <i data-lucide="send" class="mr-2 h-4 w-4"></i>
                                        Complete &amp; Assign
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- View Details Dialog -->
                        <div id="details-dialog" class="dialog-overlay">
                            <div class="dialog-content details-dialog">
                                <div class="flex items-center justify-between mb-4">
                                    <h2 class="text-lg font-semibold text-gray-900">File Tracker Details</h2>
                                    <button id="close-details" class="text-gray-400 hover:text-gray-600">
                                        <i data-lucide="x" class="h-6 w-6"></i>
                                    </button>
                                </div>
                                <div id="details-content">
                                    <!-- Details content will be dynamically generated -->
                                </div>
                                <div class="flex justify-end gap-2 mt-6">
                                    <button id="close-details-btn"
                                         class="px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                                         Close
                                     </button>
                                     <button id="print-details-btn"
                                         class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed">
                                         <i data-lucide="printer" class="h-4 w-4 mr-2"></i>
                                         Print Details
                                     </button>
                                     <a id="print-html-request-sheet-btn" href="/klaes/file_request_sheet.html" target="_blank"
                                         class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-red-600 hover:bg-red-700"
                                         style="display: none;">
                                         <i data-lucide="printer" class="h-4 w-4 mr-2"></i>
                                         Print Request Sheet
                                     </a>
                                 </div>
                            </div>
                        </div>
                        <style>
                            /* Tab display fixes */
                            .main-tab-content {
                                display: none !important;
                            }

                            .main-tab-content.active {
                                display: block !important;
                            }

                            .main-tab-content.hidden {
                                display: none !important;
                            }

                            /* Preview Dialog Styling */
                            .dialog-overlay {
                                background: rgba(0, 0, 0, 0.5) !important;
                            }

                            .dialog-content {
                                background: white !important;
                                border-radius: 12px !important;
                                box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04) !important;
                                max-width: none !important;
                                width: 95% !important;
                            }

                            /* Priority Badge Styling */
                            .priority-LOW {
                                background-color: #dcfce7 !important;
                                color: #166534 !important;
                                border: 1px solid #bbf7d0 !important;
                            }

                            .priority-MEDIUM {
                                background-color: #fef3c7 !important;
                                color: #92400e !important;
                                border: 1px solid #fde68a !important;
                            }

                            .priority-HIGH {
                                background-color: #fee2e2 !important;
                                color: #991b1b !important;
                                border: 1px solid #fecaca !important;
                            }

                            /* Custom Select2 styling for batch selection */
                            .select2-container--default .select2-selection--single {
                                height: 42px !important;
                                border: 1px solid #d1d5db !important;
                                border-radius: 0.375rem !important;
                                padding: 0 12px !important;
                                display: flex !important;
                                align-items: center !important;
                            }

                            .select2-container--default .select2-selection--single .select2-selection__rendered {
                                color: #374151 !important;
                                line-height: 42px !important;
                                padding-left: 0 !important;
                            }

                            .select2-container--default .select2-selection--single .select2-selection__arrow {
                                height: 40px !important;
                                right: 8px !important;
                            }

                            .select2-dropdown {
                                border: 1px solid #d1d5db !important;
                                border-radius: 0.375rem !important;
                                box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05) !important;
                            }

                            .select2-container--default .select2-results__option--highlighted[aria-selected] {
                                background-color: #3b82f6 !important;
                                color: white !important;
                            }

                            .select2-container--default .select2-results__option[aria-selected=true] {
                                background-color: #eff6ff !important;
                                color: #1d4ed8 !important;
                            }

                            .tracker-highlight {
                                border-color: #2563eb !important;
                                box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.2) !important;
                                animation: trackerHighlightPulse 1.4s ease-in-out 2;
                            }

                            @keyframes trackerHighlightPulse {
                                0% {
                                    box-shadow: 0 0 0 0 rgba(37, 99, 235, 0.45);
                                }

                                50% {
                                    box-shadow: 0 0 0 10px rgba(37, 99, 235, 0);
                                }

                                100% {
                                    box-shadow: 0 0 0 0 rgba(37, 99, 235, 0);
                                }
                            }

                            .cofo-status-content {
                                display: inline-flex;
                                align-items: center;
                                gap: 0.5rem;
                            }

                            .cofo-status-icon {
                                display: inline-flex;
                                align-items: center;
                                justify-content: center;
                                width: 16px;
                                height: 16px;
                                font-size: 0.75rem;
                            }

                            .loading-spinner {
                                display: inline-block;
                                width: 14px;
                                height: 14px;
                                border: 2px solid rgba(37, 99, 235, 0.3);
                                border-top-color: rgba(37, 99, 235, 0.9);
                                border-radius: 50%;
                                animation: spin 0.6s linear infinite;
                            }

                            .autofill-locked {
                                background-color: #fdf2f8 !important;
                                color: #b91c1c !important;
                                border-color: #f87171 !important;
                                cursor: not-allowed !important;
                            }

                            .autofill-locked::placeholder {
                                color: #fca5a5 !important;
                            }

                            select.autofill-locked {
                                pointer-events: none !important;
                            }

                            input.autofill-locked:focus,
                            select.autofill-locked:focus,
                            textarea.autofill-locked:focus {
                                outline: none !important;
                                box-shadow: none !important;
                            }

                            /* Shelf location input styling */
                            #shelf-location {
                                transition: all 0.2s ease-in-out;
                            }

                            #shelf-location:focus {
                                border-color: #3b82f6;
                                box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
                            }

                            /* Loading state for shelf input */
                            #shelf-location.loading {
                                background-image: url("data:image/svg+xml,%3csvg width='20' height='20' viewBox='0 0 20 20' fill='none' xmlns='http://www.w3.org/2000/svg'%3e%3cpath d='M10 3V6M10 14V17M17 10H14M6 10H3M15.364 4.636L13.536 6.464M6.464 13.536L4.636 15.364M15.364 15.364L13.536 13.536M6.464 6.464L4.636 4.636' stroke='%236b7280' stroke-width='2' stroke-linecap='round'/%3e%3c/svg%3e");
                                background-repeat: no-repeat;
                                background-position: right 12px center;
                                animation: spin 1s linear infinite;
                            }

                            @keyframes spin {
                                from {
                                    transform: rotate(0deg);
                                }

                                to {
                                    transform: rotate(360deg);
                                }
                            }

                            /* Success state styling */
                            .success-border {
                                border-color: #10b981 !important;
                                box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.1) !important;
                            }

                            /* Error state styling */
                            .error-border {
                                border-color: #ef4444 !important;
                                box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.1) !important;
                            }

                            /* Select2 loading and result states */
                            .select2-results__message {
                                color: #6b7280 !important;
                                font-style: italic !important;
                                padding: 8px 12px !important;
                            }

                            .select2-results__option.loading-results {
                                color: #6b7280 !important;
                                font-style: italic !important;
                            }

                            /* Batch selection feedback */
                            .batch-selection-feedback {
                                margin-top: 0.25rem;
                                font-size: 0.75rem;
                                color: #6b7280;
                            }

                            .batch-selection-feedback.success {
                                color: #10b981;
                            }

                            .batch-selection-feedback.error {
                                color: #ef4444;
                            }
                        </style>


                        @include('components.global-fileno-modal')

                        <!-- Core JS dependencies -->
                        <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
                        <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
                        <script src="{{ asset('js/global-fileno-modal.js') }}?v={{ @filemtime(public_path('js/global-fileno-modal.js')) }}"></script>
                        <script src="{{ asset('js/fileindexing/create-indexing-dialog.js') }}?v={{ @filemtime(public_path('js/fileindexing/create-indexing-dialog.js')) }}"></script>

                        <!-- Footer -->
                        @include('admin.footer')


                        <!-- Add Lucide Icons -->
                        <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>

                        @php
                            $authUser = auth()->user();
                            $canAssignFiles = $authUser && (
                                $authUser->type === 'super admin'
                                || $authUser->can('manage file tracker')
                                || $authUser->can('assign file tracker')
                            );

                            $currentUserPayload = [
                                'id' => $authUser?->id,
                                'name' => $authUser?->name,
                                'first_name' => $authUser?->first_name,
                                'last_name' => $authUser?->last_name,
                                'type' => $authUser?->type,
                                'department_id' => $authUser?->department_id,
                                'assign_role' => $authUser?->assign_role,
                                'roles' => $authUser?->assignedRoleNames(),
                            ];

                            $assignmentPermissionsPayload = [
                                'canAssign' => $canAssignFiles,
                            ];


                        @endphp
                        <script>
                            window.currentUser = @json($currentUserPayload);
                            window.assignmentPermissions = @json($assignmentPermissionsPayload);
                            window.isKangisModule = {{ in_array(strtolower($module ?? ''), ['kangis', 'new_kangis', 'sltr', 'st', 'dgis', 'dg', 'dciv', 'cadastral']) ? 'true' : 'false' }};
                            window.isNewKangisMode = {{ ($module ?? '') === 'new_kangis' ? 'true' : 'false' }};
                            window.currentModule = '{{ $module ?? '' }}';
                            window.isApprovalModule = {{ in_array($module ?? '', ['dgis', 'dg']) ? 'true' : 'false' }};
                            window.isDigitalRequestModule = {{ in_array(strtolower($module ?? ''), ['digital_request', 'digital-request']) ? 'true' : 'false' }};
                        </script>

                        @include('create_file_tracker_page.partials.quick-actions-js')
                        @include('create_file_tracker_page.partials.js')
                        @include('create_file_tracker_page.partials.assignment-workflow-js')
                        @include('create_file_tracker_page.partials.workflow-3step-js')
                        @if(in_array(strtolower($module ?? ''), ['kangis', 'sltr', 'st', 'dciv', 'cadastral']))
                        @include('create_file_tracker_page.partials.checkout-approval-js')
                        @endif
                    </div>

@endsection
