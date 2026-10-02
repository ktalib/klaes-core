@php
    $isView = $isView ?? false;
    $record = $requestRecord ?? null;
    $isEdit = !is_null($record) && !$isView;
    $actionUrl = $isEdit ? route('survey-report.update', $record->id) : route('survey-report.store');
    $modalTitle = $isView ? 'View Survey Report Request' : ($isEdit ? 'Edit Survey Report Request' : 'Generate Survey Report Request');
    $districtOptions = $districtOptions ?? [];
    $lgaOptions = $lgaOptions ?? [];
    $streetOptions = $streetOptions ?? [];
    $urlContext = $urlContext ?? '';
    $prefillFileNo = $prefillFileNo ?? '';
    $prefillFileTitle = $prefillFileTitle ?? '';
    $isLands = in_array($urlContext, ['lands', 'lands12']);
    $isCadastral = $urlContext === 'cadastral';
    $officers = $isLands ? ($landOfficers ?? []) : ($cadastralOfficers ?? []);
    $authUser = auth()->user();
    $currentOfficerRecord = collect($officers)->firstWhere('user_id', $authUser->id ?? null)
        ?? collect($officers)->firstWhere('id', $authUser->id ?? null);
    $currentOfficerName = trim((string) (($authUser->first_name ?? '') . ' ' . ($authUser->last_name ?? '')));
    if ($currentOfficerName === '') {
        $currentOfficerName = (string) ($authUser->name ?? ($record->lands_section_name ?? ''));
    }
    $currentOfficerId = $currentOfficerRecord->id ?? ($record->land_officer_id ?? ($authUser->id ?? ''));
    $totalSteps = $isCadastral ? 5 : 4;
@endphp

<div class="bg-white rounded-2xl" data-survey-report-modal
     x-data="{
        currentStep: 1,
        totalSteps: {{ $totalSteps }},
        isView: {{ $isView ? 'true' : 'false' }},
        submitting: false,
        stepTitles: {!! $isCadastral ? "['Lands Section', 'Report Metadata', 'Property Location Details', 'Survey & Approval', 'Digital Signature']" : "['Lands Section', 'Report Metadata', 'Property Location Details', 'Survey & Approval']" !!},
        stepIcons: {!! $isCadastral ? "['building-2', 'clipboard-list', 'map-pin', 'check-circle', 'shield-check']" : "['building-2', 'clipboard-list', 'map-pin', 'check-circle']" !!},
        nextStep() {
            if (this.currentStep < this.totalSteps) this.currentStep++;
        },
        prevStep() {
            if (this.currentStep > 1) this.currentStep--;
        },
        goToStep(step) {
            @if($isLands)
                return; // Navigation disabled for Lands context
            @else
                if (this.isView || step <= this.currentStep) this.currentStep = step;
            @endif
        },
        get isFirstStep() { return this.currentStep === 1; },
        get isLastStep() { return this.currentStep === this.totalSteps; },
        get progressPercent() { return Math.round((this.currentStep / this.totalSteps) * 100); }
     }">

    {{-- Modal Header --}}
    <div class="border-b border-slate-100 px-6 py-5">
        <div class="flex items-start justify-between gap-4">
            <div>
                <p class="text-[10px] font-black uppercase tracking-[0.15em] text-emerald-600">Lands 12</p>
                <h3 class="text-lg font-bold text-slate-900 mt-0.5">{{ $modalTitle }}</h3>
            </div>
            <button type="button"
                class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-slate-400 hover:text-slate-600 hover:bg-slate-100 transition"
                aria-label="Close modal" data-dismiss="modal">
                <i data-lucide="x" class="h-4 w-4"></i>
            </button>
        </div>

        {{-- Step Indicator --}}
        <div class="mt-5">
            {{-- Progress Bar --}}
            <div class="relative h-1 w-full rounded-full bg-slate-100 overflow-hidden mb-4">
                <div class="absolute inset-y-0 left-0 rounded-full bg-emerald-500 transition-all duration-500 ease-out"
                     :style="'width: ' + progressPercent + '%'"></div>
            </div>

            <div class="flex items-center justify-between">
                @for($i = 1; $i <= $totalSteps; $i++)
                    <button type="button"
                        @click="goToStep({{ $i }})"
                        :class="{
                            @if($isLands)
                            'cursor-default': true,
                            @else
                            'cursor-pointer': isView || {{ $i }} <= currentStep,
                            'cursor-default': !isView && {{ $i }} > currentStep
                            @endif
                        }"
                        class="flex items-center gap-2 group focus:outline-none {{ $isLands ? 'pointer-events-none' : '' }}">
                        <span class="flex h-7 w-7 items-center justify-center rounded-full text-xs font-bold transition-all duration-300 shrink-0"
                              :class="currentStep > {{ $i }}
                                  ? 'bg-emerald-500 text-white shadow-sm'
                                  : (currentStep === {{ $i }}
                                      ? 'bg-emerald-600 text-white shadow-md ring-4 ring-emerald-100'
                                      : 'bg-slate-100 text-slate-400')">
                            <template x-if="currentStep > {{ $i }}">
                                <i data-lucide="check" class="h-3.5 w-3.5"></i>
                            </template>
                            <template x-if="currentStep <= {{ $i }}">
                                <span>{{ $i }}</span>
                            </template>
                        </span>
                        <span class="text-xs font-semibold transition-colors hidden sm:inline"
                              :class="currentStep >= {{ $i }} ? 'text-slate-700' : 'text-slate-400'"
                              x-text="stepTitles[{{ $i - 1 }}]"></span>
                    </button>
                    @if($i < $totalSteps)
                        <div class="flex-1 mx-2 h-px transition-colors duration-300"
                             :class="currentStep > {{ $i }} ? 'bg-emerald-300' : 'bg-slate-200'"></div>
                    @endif
                @endfor
                </div> 
        </div>
    </div>

    <form action="{{ $actionUrl }}" method="post" class="js-survey-report-form"
        @submit="if ($el.__validateSurveyReportForm && !$el.__validateSurveyReportForm()) { submitting = false; $event.preventDefault(); } else { submitting = true; }">
        @csrf
        @if($isEdit) @method('PUT') @endif
        <input type="hidden" name="url_context" value="{{ $urlContext }}">

        <div class="modal-body p-0">
            {{-- ==================== STEP 1: Lands Section ==================== --}}
            <div x-show="currentStep === 1" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 translate-x-4" x-transition:enter-end="opacity-100 translate-x-0" class="p-6 space-y-5">

                <div class="flex items-center gap-3 mb-1">
                    <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600">
                        <i data-lucide="building-2" class="h-4.5 w-4.5"></i>
                    </div>
                    <div>
                        <h4 class="text-sm font-bold text-slate-800">Lands Section</h4>
                        <p class="text-xs text-slate-500">Director-Cadastral information and request authorization.</p>
                    </div>
                </div>

                <div class="rounded-2xl border border-emerald-100 bg-gradient-to-br from-emerald-50/80 to-white p-5 shadow-sm space-y-4">
                    {{-- Section Header --}}
                    <div class="border-b border-slate-200 pb-3 mb-2">
                        <h5 class="text-sm font-black text-slate-800 uppercase tracking-wide">Director-Cadastral</h5>
                        <div class="mt-2 inline-block rounded-md bg-red-100 px-3 py-1">
                            <span class="text-xs font-bold text-red-700 uppercase tracking-wide">Request for Survey Report</span>
                        </div>
                    </div>

                    <p class="text-xs text-slate-600 leading-relaxed">
                        Please find enclosed hereby the following:<br>
                        <span class="ml-4">a. Completed application forms</span><br>
                        <span class="ml-4">b. Four copies of site-plan and</span><br>
                        <span class="ml-4">c. Local Government Conformation</span>
                    </p>
                    <p class="text-xs text-slate-600 leading-relaxed">
                        You are to record the application and prepare survey/Cadastral report in due course.
                    </p>

                    {{-- File Number & Title (Lands context - submits as file_number/file_title) --}}
                    @if($isLands)
                    <div class="grid md:grid-cols-2 gap-x-5 gap-y-4 bg-slate-50 p-4 rounded-xl border border-slate-100">
                        <div class="space-y-1.5">
                            <label class="block text-xs font-semibold text-slate-600 uppercase tracking-wide">File Number</label>
                            <div class="flex gap-2">
                                <input type="text" name="file_number" data-lands-file-number placeholder="Select file number" readonly
                                    class="flex-1 rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm text-slate-900 placeholder-slate-400 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100 disabled:bg-slate-50 disabled:text-slate-200"
                                    value="{{ $record->file_number ?? $prefillFileNo }}" @if($isView) disabled @endif>
                                @if(!$isView)
                                <button type="button" data-lands-select-fileno
                                    class="inline-flex items-center justify-center rounded-xl bg-indigo-600 px-4 py-2 text-xs font-bold text-white transition hover:bg-indigo-700 shadow-sm">
                                    Select
                                </button>
                                @endif
                            </div>
                        </div>
                        <div class="space-y-1.5">
                            <label class="block text-xs font-semibold text-slate-600 uppercase tracking-wide">File Title</label>
                            <input type="text" name="file_title" data-lands-file-title placeholder="Auto-filled title" readonly
                                class="w-full rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm text-slate-900 placeholder-slate-400 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100 disabled:bg-slate-50 disabled:text-slate-200"
                                value="{{ $record->file_title ?? $prefillFileTitle }}" @if($isView) disabled @endif>
                        </div>
                    </div>
                    @endif

                    <div class="grid gap-x-5 gap-y-4 md:grid-cols-3 mt-3">
                        {{-- Date --}}
                        <div class="space-y-1.5">
                            <label class="block text-xs font-semibold text-slate-600 uppercase tracking-wide">Date</label>
                            <input type="date" name="lands_section_date"
                                class="w-full rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm text-slate-900 transition focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100 disabled:bg-slate-50 disabled:text-slate-500"
                                value="{{ $record->lands_section_date ?? '' }}" @if($isView || $isCadastral) disabled @endif>
                        </div> 

                        {{-- Officer auto-filled with currently logged-in user --}}
                        <div class="space-y-1.5">
                            <label class="block text-xs font-semibold text-slate-600 uppercase tracking-wide">Officer</label>
                            <input type="text" name="lands_section_name" data-officer-select
                                class="w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-2.5 text-sm text-slate-900 transition focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100 disabled:bg-slate-50 disabled:text-slate-500"
                                value="{{ $currentOfficerName }}" readonly @if($isView || $isCadastral) disabled @endif>
                            <input type="hidden" name="land_officer_id" data-land-officer-id value="{{ $currentOfficerId }}" @if($isView || $isCadastral) disabled @endif>
                        </div>
 
                        {{-- Rank --}}
                        <div class="space-y-1.5">
                            <label class="block text-xs font-semibold text-slate-600 uppercase tracking-wide">Rank</label>
                            <select name="lands_section_rank" data-rank-select
                                class="w-full rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm text-slate-900 transition focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100 disabled:bg-slate-50 disabled:text-slate-500"
                                @if($isView || $isCadastral) disabled @endif>
                                <option value="">Select Rank</option>
                                <option value="Assistant Director"
                                    {{ ($record->lands_section_rank ?? '') === 'Assistant Director' ? 'selected' : '' }}>
                                    Assistant Director
                                </option>
                                <option value="Deputy Director"
                                    {{ ($record->lands_section_rank ?? '') === 'Deputy Director' ? 'selected' : '' }}>
                                    Deputy Director
                                </option>
                            </select>
                        </div>
                    </div>

                    @if($isLands && !$isView)
                    <input type="hidden" name="signature_verified" value="0" data-signature-verified>
                    <div class="grid gap-x-5 gap-y-4 md:grid-cols-3 mt-1">
                        <div class="space-y-1.5">
                            <label class="block text-xs font-semibold text-slate-600 uppercase tracking-wide">Verification Method</label>
                            <select name="signature_verification_method" data-signature-method
                                class="w-full rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm text-slate-900 transition focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100">
                                <option value="">Select Method</option>
                                <option value="email">Email OTP</option>
                                <option value="sms">SMS OTP</option>
                                <option value="password">Password Confirmation</option>
                            </select>
                        </div>
                        <div class="space-y-1.5" data-otp-wrapper>
                            <label class="block text-xs font-semibold text-slate-600 uppercase tracking-wide">OTP Code</label>
                            <div class="flex gap-2">
                                <input type="text" name="verification_code" maxlength="6" placeholder="Enter OTP"
                                    class="w-full rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm text-slate-900 transition focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100">
                                <button type="button" data-send-officer-otp
                                    class="inline-flex items-center justify-center rounded-xl bg-indigo-600 px-4 py-2 text-xs font-bold text-white transition hover:bg-indigo-700 shadow-sm whitespace-nowrap">
                                    Send OTP
                                </button>
                            </div>
                        </div>
                        <div class="space-y-1.5 hidden" data-password-wrapper>
                            <label class="block text-xs font-semibold text-slate-600 uppercase tracking-wide">Confirm Password</label>
                            <input type="password" name="verification_password" placeholder="Officer account password"
                                class="w-full rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm text-slate-900 transition focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100">
                        </div>
                    </div>
                    {{-- Verify button row --}}
                    <div class="mt-3 flex items-center gap-3" data-verify-row>
                        <button type="button" data-verify-signature-btn
                            class="inline-flex items-center gap-1.5 rounded-xl bg-indigo-600 px-4 py-2 text-xs font-bold text-white transition hover:bg-indigo-700 shadow-sm disabled:opacity-50 disabled:cursor-not-allowed">
                            <i data-lucide="shield-check" class="h-3.5 w-3.5"></i>
                            <span data-verify-btn-text>Verify</span>
                        </button>
                        <span data-verify-feedback class="text-xs font-medium text-slate-500"></span>
                    </div>
                    @endif

                    <div class="mt-3">
                        @if(!empty($record?->lands_section_signature))
                            <div class="mb-2 flex justify-end">
                                <img src="{{ \Illuminate\Support\Facades\Storage::url($record->lands_section_signature) }}"
                                    alt="Officer Signature"
                                    class="h-10 object-contain border border-slate-200 rounded bg-white px-2 py-1">
                            </div>
                        @endif
                        @if($isLands && !$isView)
                        <div class="mb-2 flex justify-end items-center gap-2 hidden" data-ajax-sig-wrap>
                            <img src="" alt="Verified Signature" class="h-10 object-contain border border-emerald-300 rounded bg-white px-2 py-1" data-ajax-sig-img>
                            <span class="inline-flex items-center gap-1 text-[10px] font-black text-emerald-600 uppercase tracking-wide">
                                <i data-lucide="check-circle" class="h-3 w-3"></i> Verified
                            </span>
                        </div>
                        @endif
                        <div class="text-right">
                            <span class="text-xs font-bold text-slate-700 italic">For: Director Land</span>
                        </div>
                    </div>
                </div>
            </div>

            {{-- ==================== STEP 2: Report Metadata ==================== --}}
            <div x-show="currentStep === 2" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 translate-x-4" x-transition:enter-end="opacity-100 translate-x-0" class="p-6 space-y-5">

                <div class="flex items-center gap-3 mb-1">
                    <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600">
                        <i data-lucide="clipboard-list" class="h-4.5 w-4.5"></i>
                    </div>
                    <div>
                        <h4 class="text-sm font-bold text-slate-800">Survey Report Metadata</h4>
                        <p class="text-xs text-slate-500">Fill in the report header information for the Lands 12 template.</p>
                    </div>
                </div>

                <div class="grid gap-x-5 gap-y-4 md:grid-cols-2">
                    {{-- File Number & Title --}}
                    <div class="md:col-span-2 grid md:grid-cols-2 gap-x-5 gap-y-4 bg-slate-50 p-4 rounded-xl border border-slate-100 mb-2">
                        <div class="space-y-1.5">
                            <label class="block text-xs font-semibold text-slate-600 uppercase tracking-wide">File Number</label>
                            <div class="flex gap-2">
                                <input type="text" name="file_number" id="sr-file-number" placeholder="Select file number" readonly
                                    class="flex-1 rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm text-slate-900 placeholder-slate-400 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100 disabled:bg-slate-50 disabled:text-slate-200"
                                    value="{{ $record->file_number ?? '' }}" @if($isView || $isLands) disabled @endif>
                                @if(!$isView && !$isLands)
                                <button type="button" id="sr-select-fileno"
                                    class="inline-flex items-center justify-center rounded-xl bg-indigo-600 px-4 py-2 text-xs font-bold text-white transition hover:bg-indigo-700 shadow-sm">
                                    Select
                                </button>
                                @endif
                            </div>
                        </div>
                        <div class="space-y-1.5">
                            <label class="block text-xs font-semibold text-slate-600 uppercase tracking-wide">File Title</label>
                            <input type="text" name="file_title" id="sr-file-title" placeholder="Auto-filled title" readonly
                                class="w-full rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm text-slate-900 placeholder-slate-400 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100 disabled:bg-slate-50 disabled:text-slate-200"
                                value="{{ $record->file_title ?? '' }}" @if($isView || $isLands) disabled @endif>
                        </div>
                    </div>

                    {{-- Director-Cadastral Date --}}
                    <div class="space-y-1.5 hidden">
                        <label class="block text-xs font-semibold text-slate-600 uppercase tracking-wide">Director-Cadastral Date</label>
                        <input type="date" name="director_cadastral_date"
                            class="w-full rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm text-slate-900 transition focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100 disabled:bg-slate-50 disabled:text-slate-500"
                            value="{{ $record && $record->director_cadastral_date ? $record->director_cadastral_date->format('Y-m-d') : '' }}" @if($isView || $isLands) disabled @endif>
                    </div>

                    {{-- Director Name --}}
                    <div class="space-y-1.5 hidden">
                        <label class="block text-xs font-semibold text-slate-600 uppercase tracking-wide">Director Name</label>
                        <input type="text" name="director_name" placeholder="e.g., Musa Abdullahi"
                            class="w-full rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm text-slate-900 placeholder-slate-400 transition focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100 disabled:bg-slate-50 disabled:text-slate-500"
                            value="{{ $record->director_name ?? '' }}" @if($isView || $isLands) disabled @endif>
                    </div>

                    {{-- Director Rank --}}
                    <div class="space-y-1.5 hidden">
                        <label class="block text-xs font-semibold text-slate-600 uppercase tracking-wide">Director Rank</label>
                        <input type="text" name="director_rank" placeholder="e.g., Deputy Director"
                            class="w-full rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm text-slate-900 placeholder-slate-400 transition focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100 disabled:bg-slate-50 disabled:text-slate-500"
                            value="{{ $record->director_rank ?? '' }}" @if($isView || $isLands) disabled @endif>
                    </div>

                    {{-- Director Signature --}}
                    <div class="space-y-1.5 hidden">
                        <label class="block text-xs font-semibold text-slate-600 uppercase tracking-wide">Director Signature</label>
                        <input type="text" name="director_signature" placeholder="Director's signature"
                            class="w-full rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm text-slate-900 placeholder-slate-400 transition focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100 disabled:bg-slate-50 disabled:text-slate-500"
                            value="{{ $record->director_signature ?? '' }}" @if($isView || $isLands) disabled @endif>
                    </div>

                    {{-- ROFO No --}}
                    <div class="space-y-1.5">
                        <label class="block text-xs font-semibold text-slate-600 uppercase tracking-wide">ROFO No</label>
                        <input type="text" name="rofo_no" placeholder="e.g., ROFO/2026/123"
                            class="w-full rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm text-slate-900 placeholder-slate-400 transition focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100 disabled:bg-slate-50 disabled:text-slate-500"
                            value="{{ $record->rofo_no ?? '' }}" @if($isView || $isLands) disabled @endif>
                    </div>

                </div>
            </div>

            {{-- ==================== STEP 3: Location Details ==================== --}}
            <div x-show="currentStep === 3" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 translate-x-4" x-transition:enter-end="opacity-100 translate-x-0" class="p-6 space-y-5">

                <div class="flex items-center gap-3 mb-1">
                    <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600">
                        <i data-lucide="map-pin" class="h-4.5 w-4.5"></i>
                    </div>
                    <div>
                        <h4 class="text-sm font-bold text-slate-800">Location Details</h4>
                        <p class="text-xs text-slate-500">Provide the plot location for the Lands 12 template.</p>
                    </div>
                </div>

                <div class="rounded-2xl border border-emerald-100 bg-gradient-to-br from-emerald-50/80 to-white p-5 shadow-sm space-y-4">
                    <div class="grid gap-x-5 gap-y-4 md:grid-cols-2">
                        {{-- Item G No --}}
                        <div class="space-y-1.5">
                            <label class="block text-xs font-semibold text-slate-600 uppercase tracking-wide">Item G No</label>
                            <input type="text" name="item_g_no" placeholder="e.g., G/2026/045"
                                class="w-full rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm text-slate-900 placeholder-slate-400 transition focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100 disabled:bg-slate-50 disabled:text-slate-500"
                                value="{{ $record->item_g_no ?? '' }}" @if($isView || $isLands) disabled @endif>
                        </div>

                        {{-- Plot Number --}}
                        <div class="space-y-1.5">
                            <label class="block text-xs font-semibold text-slate-600 uppercase tracking-wide">Plot Number</label>
                            <input type="text" name="plot_number_meta"
                                data-location-field="plot" placeholder="e.g., 402"
                                class="w-full rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm text-slate-900 placeholder-slate-400 transition focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100 disabled:bg-slate-50 disabled:text-slate-500"
                                value="" @if($isView || $isLands) disabled @endif>
                        </div>

                        {{-- TP Number --}}
                        <div class="space-y-1.5">
                            <label class="block text-xs font-semibold text-slate-600 uppercase tracking-wide">TP Number</label>
                            <input type="text" name="tp_number_meta"
                                data-location-field="tp" placeholder="e.g., TP/1024"
                                class="w-full rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm text-slate-900 placeholder-slate-400 transition focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100 disabled:bg-slate-50 disabled:text-slate-500"
                                value="" @if($isView || $isLands) disabled @endif>
                        </div>
    {{-- Street --}}
                        <div class="space-y-1.5" x-data="{ streetIsOther: false }">
                            <label class="block text-xs font-semibold text-slate-600 uppercase tracking-wide">Street</label>
                            <select name="street_meta" data-location-field="street"
                                x-on:change="streetIsOther = ($event.target.value === 'Other')"
                                class="w-full rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm text-slate-900 transition focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100 disabled:bg-slate-50 disabled:text-slate-500"
                                @if($isView || $isLands) disabled @endif>
                                <option value="">Select Street</option>
                                @foreach($streetOptions as $street)
                                    <option value="{{ $street }}">{{ $street }}</option>
                                @endforeach
                                <option value="Other">Other</option>
                            </select>
                            <div class="mt-1.5" x-show="streetIsOther" x-transition data-street-other-wrapper>
                                <input type="text" placeholder="Specify street" @if($isView || $isLands) disabled @endif
                                    data-location-field="street-other"
                                    class="w-full rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm text-slate-900 placeholder-slate-400 transition focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100 disabled:bg-slate-50 disabled:text-slate-500">
                            </div>
                        </div>
                        {{-- District --}}
                        <div class="space-y-1.5" x-data="{ districtIsOther: false }">
                            <label class="block text-xs font-semibold text-slate-600 uppercase tracking-wide">District</label>
                            <select name="district_meta" data-location-field="district"
                                x-on:change="districtIsOther = ($event.target.value === 'Other')"
                                class="w-full rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm text-slate-900 transition focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100 disabled:bg-slate-50 disabled:text-slate-500"
                                @if($isView || $isLands) disabled @endif>
                                <option value="">Select District</option>
                                @foreach($districtOptions as $district)
                                    <option value="{{ $district }}">{{ $district }}</option>
                                @endforeach
                                <option value="Other">Other</option>
                            </select>
                            <div class="mt-1.5" x-show="districtIsOther" x-transition data-district-other-wrapper>
                                <input type="text" placeholder="Specify district" @if($isView || $isLands) disabled @endif
                                    data-location-field="district-other"
                                    class="w-full rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm text-slate-900 placeholder-slate-400 transition focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100 disabled:bg-slate-50 disabled:text-slate-500">
                            </div>
                        </div>

                        {{-- LGA --}}
                        <div class="space-y-1.5">
                            <label class="block text-xs font-semibold text-slate-600 uppercase tracking-wide">LGA</label>
                            <select name="lga_meta" data-location-field="lga"
                                class="w-full rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm text-slate-900 transition focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100 disabled:bg-slate-50 disabled:text-slate-500"
                                @if($isView || $isLands) disabled @endif>
                                <option value="">Select LGA</option>
                                @foreach($lgaOptions as $lga)
                                    <option value="{{ $lga }}">{{ $lga }}</option>
                                @endforeach
                            </select>
                        </div>

                    

                        {{-- Location Preview --}}
                        <div class="space-y-1.5">
                            <label class="block text-xs font-semibold text-slate-600 uppercase tracking-wide">
                                Location Preview
                                <span class="text-emerald-500 lowercase font-normal tracking-normal">(auto-generated)</span>
                            </label>
                            <textarea name="plot_description" rows="3" data-location-preview
                                class="w-full rounded-xl border border-dashed border-emerald-300 bg-emerald-50/50 px-4 py-2.5 text-sm font-semibold uppercase tracking-wide text-emerald-800 placeholder-emerald-400 transition focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100 disabled:bg-slate-50"
                                readonly >{{ $record->plot_description ?? '' }}</textarea>
                            <p class="text-[11px] text-emerald-600 flex items-center gap-1">
                                <i data-lucide="info" class="h-3 w-3 shrink-0"></i>
                                This preview is saved as the official plot description on the LAND 12 template.
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            {{-- ==================== STEP 4: Survey & Approval ==================== --}}
            <div x-show="currentStep === 4" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 translate-x-4" x-transition:enter-end="opacity-100 translate-x-0" class="p-6 space-y-5">

                <div class="flex items-center gap-3 mb-1">
                    <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600">
                        <i data-lucide="shield-check" class="h-4.5 w-4.5"></i>
                    </div>
                    <div>
                        <h4 class="text-sm font-bold text-slate-800">Survey & Approval</h4>
                        <p class="text-xs text-slate-500">Complete survey details and approval dates.</p>
                    </div>
                </div>

                <div class="grid gap-x-5 gap-y-4 md:grid-cols-2">
                    {{-- Survey Necessary --}}
                    <div class="space-y-1.5 md:col-span-2">
                        <label class="block text-xs font-semibold text-slate-600 uppercase tracking-wide">Survey Necessary</label>
                        <input type="text" name="survey_necessary" placeholder="Describe why survey is necessary"
                            class="w-full rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm text-slate-900 placeholder-slate-400 transition focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100 disabled:bg-slate-50 disabled:text-slate-500"
                            value="{{ $record->survey_necessary ?? '' }}" @if($isView || $isLands) disabled @endif>
                    </div>

                    {{-- Beacon Numbers --}}
                    <div class="space-y-1.5 md:col-span-2">
                        <label class="block text-xs font-semibold text-slate-600 uppercase tracking-wide">Beacon Numbers</label>
                        <textarea name="beacon_numbers" rows="3" placeholder="Enter beacon numbers, one per line"
                            class="w-full rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm text-slate-900 placeholder-slate-400 transition focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100 disabled:bg-slate-50 disabled:text-slate-500"
                            @if($isView || $isLands) disabled @endif>{{ $record->beacon_numbers ?? '' }}</textarea>
                    </div>

                    {{-- Director Cadastral Approval Date --}}
                    <div class="space-y-1.5 hidden">
                        <label class="block text-xs font-semibold text-slate-600 uppercase tracking-wide">Director Cadastral Approval Date</label>
                        <input type="date" name="director_cadastral_approval_date"
                            class="w-full rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm text-slate-900 transition focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100 disabled:bg-slate-50 disabled:text-slate-500"
                            value="{{ $record && $record->director_cadastral_approval_date ? $record->director_cadastral_approval_date->format('Y-m-d') : '' }}" @if($isView || $isLands) disabled @endif>
                    </div>

                    {{-- Commissioner Date --}}
                    <div class="space-y-1.5 hidden">
                        <label class="block text-xs font-semibold text-slate-600 uppercase tracking-wide">Commissioner Date</label>
                        <input type="date" name="commissioner_date"
                            class="w-full rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm text-slate-900 transition focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100 disabled:bg-slate-50 disabled:text-slate-500"
                            value="{{ $record && $record->commissioner_date ? $record->commissioner_date->format('Y-m-d') : '' }}" @if($isView || $isLands) disabled @endif>
                    </div>
                </div>

                {{-- Summary Card (only on last step when not cadastral, non-view) --}}
                @if(!$isView && !$isCadastral)
                <div class="mt-2 rounded-2xl border border-amber-100 bg-amber-50/60 p-4">
                    <div class="flex items-start gap-3">
                        <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-amber-100 text-amber-600 shrink-0 mt-0.5">
                            <i data-lucide="alert-circle" class="h-4 w-4"></i>
                        </div>
                        <div>
                            <p class="text-xs font-bold text-amber-800">Ready to submit?</p>
                            <p class="text-xs text-amber-700 mt-0.5">Review all steps before submitting. You can navigate back to any step using the indicators above.</p>
                        </div>
                    </div>
                </div>
                @endif
            </div>

            @if($isCadastral)
            {{-- ==================== STEP 5: Digital Signature (Cadastral only) ==================== --}}
            <div x-show="currentStep === 5" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 translate-x-4" x-transition:enter-end="opacity-100 translate-x-0" class="p-6 space-y-5">

                <div class="flex items-center gap-3 mb-1">
                    <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600">
                        <i data-lucide="shield-check" class="h-4.5 w-4.5"></i>
                    </div>
                    <div>
                        <h4 class="text-sm font-bold text-slate-800">Digital Signature Verification</h4>
                        <p class="text-xs text-slate-500">Verify your identity to apply the Director Cadastral digital signature.</p>
                    </div>
                </div>

                <div class="rounded-2xl border border-emerald-100 bg-gradient-to-br from-emerald-50/80 to-white p-5 shadow-sm space-y-4">
                    <div class="border-b border-slate-200 pb-3 mb-2">
                        <h5 class="text-sm font-black text-slate-800 uppercase tracking-wide">Director Cadastral</h5>
                        <p class="text-xs text-slate-500 mt-1">Select a verification method to digitally sign this Survey Report Request.</p>
                    </div>

                    @if(!$isView)
                    <input type="hidden" name="director_cadastral_signature_verified" value="0" data-dc-signature-verified>

                    <div class="grid gap-x-5 gap-y-4 md:grid-cols-3">
                        <div class="space-y-1.5">
                            <label class="block text-xs font-semibold text-slate-600 uppercase tracking-wide">Verification Method</label>
                            <select name="director_cadastral_signature_method" data-dc-signature-method
                                class="w-full rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm text-slate-900 transition focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100">
                                <option value="">Select Method</option>
                                <option value="email" {{ ($record->director_cadastral_signature_method ?? '') === 'email' ? 'selected' : '' }}>Email OTP</option>
                                <option value="sms" {{ ($record->director_cadastral_signature_method ?? '') === 'sms' ? 'selected' : '' }}>SMS OTP</option>
                                <option value="password" {{ ($record->director_cadastral_signature_method ?? '') === 'password' ? 'selected' : '' }}>Password Confirmation</option>
                            </select>
                        </div>
                        <div class="space-y-1.5" data-dc-otp-wrapper>
                            <label class="block text-xs font-semibold text-slate-600 uppercase tracking-wide">OTP Code</label>
                            <div class="flex gap-2">
                                <input type="text" name="director_cadastral_verification_code" maxlength="6" placeholder="Enter OTP"
                                    class="w-full rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm text-slate-900 transition focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100">
                                <button type="button" data-dc-send-otp
                                    class="inline-flex items-center justify-center rounded-xl bg-indigo-600 px-4 py-2 text-xs font-bold text-white transition hover:bg-indigo-700 shadow-sm whitespace-nowrap">
                                    Send OTP
                                </button>
                            </div>
                        </div>
                        <div class="space-y-1.5 hidden" data-dc-password-wrapper>
                            <label class="block text-xs font-semibold text-slate-600 uppercase tracking-wide">Confirm Password</label>
                            <input type="password" name="director_cadastral_verification_password" placeholder="Your account password"
                                class="w-full rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm text-slate-900 transition focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100">
                        </div>
                    </div>

                    <div class="mt-3 flex items-center gap-3" data-dc-verify-row>
                        <button type="button" data-dc-verify-btn
                            class="inline-flex items-center gap-1.5 rounded-xl bg-indigo-600 px-4 py-2 text-xs font-bold text-white transition hover:bg-indigo-700 shadow-sm disabled:opacity-50 disabled:cursor-not-allowed">
                            <i data-lucide="shield-check" class="h-3.5 w-3.5"></i>
                            <span data-dc-verify-btn-text>Verify Signature</span>
                        </button>
                        <span data-dc-verify-feedback class="text-xs font-medium text-slate-500"></span>
                    </div>

                    <div class="mb-2 flex justify-end items-center gap-2 hidden" data-dc-sig-wrap>
                        <img src="" alt="Director Cadastral Signature" class="h-10 object-contain border border-emerald-300 rounded bg-white px-2 py-1" data-dc-sig-img>
                        <span class="inline-flex items-center gap-1 text-[10px] font-black text-emerald-600 uppercase tracking-wide">
                            <i data-lucide="check-circle" class="h-3 w-3"></i> Verified
                        </span>
                    </div>
                    @else
                    @if(!empty($record?->director_cadastral_signature))
                        <div class="mb-2 flex justify-end">
                            <img src="{{ \Illuminate\Support\Facades\Storage::url($record->director_cadastral_signature) }}"
                                alt="Director Cadastral Signature"
                                class="h-10 object-contain border border-slate-200 rounded bg-white px-2 py-1">
                        </div>
                    @endif
                    @endif

                    <div class="text-right">
                        <span class="text-xs font-bold text-slate-700 italic">For: Director Cadastral</span>
                    </div>

                    {{-- Summary Card --}}
                    <div class="mt-2 rounded-2xl border border-amber-100 bg-amber-50/60 p-4">
                        <div class="flex items-start gap-3">
                            <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-amber-100 text-amber-600 shrink-0 mt-0.5">
                                <i data-lucide="alert-circle" class="h-4 w-4"></i>
                            </div>
                            <div>
                                <p class="text-xs font-bold text-amber-800">Ready to submit?</p>
                                <p class="text-xs text-amber-700 mt-0.5">Verify your signature above before submitting. You can navigate back to any step using the indicators above.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            @endif

        </div>

        {{-- Footer Navigation --}}
        @if(!$isView)
            <div class="modal-footer flex items-center justify-between border-t border-slate-100 px-6 py-4">
                <div>
                    <button type="button"
                        x-show="!isFirstStep"
                        x-transition
                        @click="prevStep()"
                        class="inline-flex items-center gap-1.5 rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-semibold text-slate-600 hover:bg-slate-50 transition">
                        <i data-lucide="arrow-left" class="h-3.5 w-3.5"></i>
                        Back
                    </button>
                </div>

                <div class="flex items-center gap-3">
                    <span class="text-xs text-slate-400 font-medium" x-text="'Step ' + currentStep + ' of ' + totalSteps"></span>

                    @if($isLands)
                    <button type="submit"
                        id="sr-send-cadastral-btn"
                        data-send-cadastral-btn
                        :disabled="submitting"
                        disabled
                        class="inline-flex items-center gap-1.5 rounded-xl bg-emerald-600 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-emerald-700 shadow-sm disabled:opacity-50 disabled:cursor-not-allowed">
                        <i data-lucide="send" class="h-3.5 w-3.5"></i>
                        Send To Cadastral
                    </button>
                    @else
                    <button type="button"
                        x-show="!isLastStep"
                        @click="if (!$el.form.__validateSurveyReportStep || $el.form.__validateSurveyReportStep(currentStep)) nextStep()"
                        class="inline-flex items-center gap-1.5 rounded-xl bg-emerald-600 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-emerald-700 shadow-sm">
                        Next
                        <i data-lucide="arrow-right" class="h-3.5 w-3.5"></i>
                    </button>

                    <button type="submit"
                        x-show="isLastStep"
                        x-transition
                        :disabled="submitting"
                        class="inline-flex items-center gap-1.5 rounded-xl bg-emerald-600 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-emerald-700 shadow-sm disabled:opacity-60">
                        <i data-lucide="check" class="h-3.5 w-3.5"></i>
                        {{ $isEdit ? 'Update Request' : 'Generate Request' }}
                    </button>
                    @endif
                </div>
            </div>
        @else
            <div class="modal-footer flex items-center justify-between border-t border-slate-100 px-6 py-4">
                <div>
                    <button type="button" x-show="!isFirstStep" @click="prevStep()"
                        class="inline-flex items-center gap-1.5 rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-semibold text-slate-600 hover:bg-slate-50 transition">
                        <i data-lucide="arrow-left" class="h-3.5 w-3.5"></i> Back
                    </button>
                </div>
                <div class="flex items-center gap-3">
                    <span class="text-xs text-slate-400 font-medium" x-text="'Step ' + currentStep + ' of ' + totalSteps"></span>
                    <button type="button" x-show="!isLastStep" @click="nextStep()"
                        class="inline-flex items-center gap-1.5 rounded-xl bg-slate-600 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-slate-700 shadow-sm">
                        Next <i data-lucide="arrow-right" class="h-3.5 w-3.5"></i>
                    </button>
                    <button type="button" x-show="isLastStep" data-dismiss="modal"
                        class="inline-flex items-center gap-1.5 rounded-xl border border-slate-200 px-5 py-2.5 text-sm font-semibold text-slate-600 hover:bg-slate-50 transition">
                        Close
                    </button>
                </div>
            </div>
        @endif
    </form>
</div>

<script>
(() => {
    // Find modal root reliably — document.currentScript is null when injected via AJAX
    const allModals = document.querySelectorAll('[data-survey-report-modal]');
    const modalRoot = allModals.length > 0 ? allModals[allModals.length - 1] : null;
    if (!modalRoot) {
        console.warn('Survey report modal root not found');
        return;
    }

    const form = modalRoot.querySelector('.js-survey-report-form');
    if (!form) {
        return;
    }

    // ── Officer dropdown sync → auto-fill Rank ──
    const officerSelect = form.querySelector('[data-officer-select]');
    const rankSelect = form.querySelector('[data-rank-select]');
    const landOfficerIdInput = form.querySelector('[data-land-officer-id]');
    const methodSelect = form.querySelector('[data-signature-method]');
    const otpWrapper = form.querySelector('[data-otp-wrapper]');
    const passwordWrapper = form.querySelector('[data-password-wrapper]');
    const sendOtpBtn = form.querySelector('[data-send-officer-otp]');
    const verificationCodeInput = form.querySelector('[name="verification_code"]');
    const verificationPasswordInput = form.querySelector('[name="verification_password"]');

    if (officerSelect && rankSelect) {
        if (officerSelect.tagName === 'SELECT') {
            officerSelect.addEventListener('change', function () {
                const selected = this.options[this.selectedIndex];
                const rank = selected.getAttribute('data-rank') || '';
                const officerId = selected.getAttribute('data-officer-id') || '';
                if (rank) {
                    rankSelect.value = rank;
                }
                if (landOfficerIdInput) {
                    landOfficerIdInput.value = officerId;
                }
            });

            // Set initial officer ID in edit/view state
            const selected = officerSelect.options[officerSelect.selectedIndex];
            if (selected && landOfficerIdInput && !landOfficerIdInput.value) {
                landOfficerIdInput.value = selected.getAttribute('data-officer-id') || '';
            }
        }
    }

    function syncVerificationUi() {
        if (!methodSelect) return;
        const method = methodSelect.value;
        if (otpWrapper) otpWrapper.classList.toggle('hidden', !(method === 'email' || method === 'sms'));
        if (passwordWrapper) passwordWrapper.classList.toggle('hidden', method !== 'password');
    }

    if (methodSelect) {
        methodSelect.addEventListener('change', syncVerificationUi);
        syncVerificationUi();
    }

    if (sendOtpBtn) {
        sendOtpBtn.addEventListener('click', function () {
            const officerId = landOfficerIdInput ? landOfficerIdInput.value : '';
            const method = methodSelect ? methodSelect.value : '';

            if (!officerId) {
                if (window.Swal) Swal.fire('Required', 'Please select a signing officer first.', 'warning');
                return;
            }
            if (!(method === 'email' || method === 'sms')) {
                if (window.Swal) Swal.fire('Required', 'Select Email OTP or SMS OTP to request code.', 'warning');
                return;
            }

            fetch('{{ route("survey-report.send-land-officer-otp") }}', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                    'Accept': 'application/json',
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    land_officer_id: officerId,
                    method: method
                })
            })
            .then(function (res) { return res.json(); })
            .then(function (result) {
                if (result.success) {
                    if (window.Swal) Swal.fire('Success', result.message, 'success');
                } else {
                    if (window.Swal) Swal.fire('Error', result.message || 'Could not send OTP.', 'error');
                }
            })
            .catch(function () {
                if (window.Swal) Swal.fire('Error', 'Network error. Please try again.', 'error');
            });
        });
    }

    // ── Send Button Gate: requires file number + verified signature (Lands context) ──
    const landsFileNoInput  = form.querySelector('[data-lands-file-number]');
    const sendCadastralBtn  = form.querySelector('[data-send-cadastral-btn]');
    const sigVerifiedInput  = form.querySelector('[data-signature-verified]');
    const ajaxSigWrap       = form.querySelector('[data-ajax-sig-wrap]');
    const ajaxSigImg        = form.querySelector('[data-ajax-sig-img]');
    const verifyFeedbackEl  = form.querySelector('[data-verify-feedback]');
    const verifyBtnTextEl   = form.querySelector('[data-verify-btn-text]');
    const verifySignBtn     = form.querySelector('[data-verify-signature-btn]');
    const landsFileTitleInput = form.querySelector('[data-lands-file-title]');
    const landsSectionDateInput = form.querySelector('[name="lands_section_date"]');
    const rofoInput = form.querySelector('[name="rofo_no"]');
    const itemGInput = form.querySelector('[name="item_g_no"]');
    const surveyNecessaryInput = form.querySelector('[name="survey_necessary"]');
    const srFileNumberInput = form.querySelector('#sr-file-number');
    const srFileTitleInput = form.querySelector('#sr-file-title');

    function resetLandsVerificationState() {
        if (sigVerifiedInput) sigVerifiedInput.value = '0';
        if (ajaxSigWrap) ajaxSigWrap.classList.add('hidden');
        if (ajaxSigImg) ajaxSigImg.removeAttribute('src');
        if (verifySignBtn) {
            verifySignBtn.disabled = false;
            verifySignBtn.classList.remove('bg-green-600', 'hover:bg-green-700');
            verifySignBtn.classList.add('bg-indigo-600', 'hover:bg-indigo-700');
        }
        if (verifyBtnTextEl) verifyBtnTextEl.textContent = 'Verify';
        if (verifyFeedbackEl) {
            verifyFeedbackEl.textContent = '';
            verifyFeedbackEl.className = 'text-xs font-medium text-slate-500';
        }
        refreshSendBtn();
    }

    function refreshSendBtn() {
        if (!sendCadastralBtn) return;
        const fileSelected = !!(landsFileNoInput && landsFileNoInput.value.trim());
        const titleSelected = !!(landsFileTitleInput && landsFileTitleInput.value.trim());
        const sigVerified  = !!(sigVerifiedInput && sigVerifiedInput.value === '1');
        sendCadastralBtn.disabled = !(fileSelected && titleSelected && sigVerified);
    }

    if (landsFileNoInput) {
        landsFileNoInput.addEventListener('input',  refreshSendBtn);
        landsFileNoInput.addEventListener('change', refreshSendBtn);
    }

    if (landsFileTitleInput) {
        landsFileTitleInput.addEventListener('input', refreshSendBtn);
        landsFileTitleInput.addEventListener('change', refreshSendBtn);
    }

    if (methodSelect) {
        methodSelect.addEventListener('change', resetLandsVerificationState);
    }

    if (verificationCodeInput) {
        verificationCodeInput.addEventListener('input', resetLandsVerificationState);
    }

    if (verificationPasswordInput) {
        verificationPasswordInput.addEventListener('input', resetLandsVerificationState);
    }

    if (verifySignBtn) {
        verifySignBtn.addEventListener('click', async function () {
            const method   = methodSelect       ? methodSelect.value       : '';
            const officerId = landOfficerIdInput ? landOfficerIdInput.value : '';
            const code     = verificationCodeInput ? verificationCodeInput.value : '';
            const password = verificationPasswordInput ? verificationPasswordInput.value : '';

            if (!method) {
                if (window.Swal) Swal.fire('Required', 'Please select a verification method.', 'warning');
                return;
            }
            if ((method === 'email' || method === 'sms') && !code) {
                if (window.Swal) Swal.fire('Required', 'Please enter the OTP code sent to you.', 'warning');
                return;
            }
            if (method === 'password' && !password) {
                if (window.Swal) Swal.fire('Required', 'Please enter your password to verify.', 'warning');
                return;
            }

            verifySignBtn.disabled = true;
            if (verifyBtnTextEl)  verifyBtnTextEl.textContent = 'Verifying…';
            if (verifyFeedbackEl) { verifyFeedbackEl.textContent = ''; verifyFeedbackEl.className = 'text-xs font-medium text-slate-500'; }

            try {
                const res  = await fetch('{{ route("survey-report.verify-signature") }}', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') || {}).content || '',
                        'Accept':       'application/json',
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        verification_scope:          'lands',
                        land_officer_id:               officerId,
                        signature_verification_method: method,
                        verification_code:             code,
                        verification_password:         password,
                    }),
                });
                const data = await res.json();

                if (data.success) {
                    if (sigVerifiedInput) sigVerifiedInput.value = '1';
                    if (ajaxSigImg && data.signature_url) ajaxSigImg.src = data.signature_url;
                    if (ajaxSigWrap) ajaxSigWrap.classList.remove('hidden');
                    verifySignBtn.classList.remove('bg-indigo-600', 'hover:bg-indigo-700');
                    verifySignBtn.classList.add('bg-green-600', 'hover:bg-green-700');
                    if (verifyBtnTextEl)  verifyBtnTextEl.textContent = 'Verified ✓';
                    if (verifyFeedbackEl) { verifyFeedbackEl.textContent = 'Signature verified.'; verifyFeedbackEl.className = 'text-xs font-medium text-green-600'; }
                    if (typeof lucide !== 'undefined' && lucide.createIcons) lucide.createIcons();
                    refreshSendBtn();
                } else {
                    verifySignBtn.disabled = false;
                    if (verifyBtnTextEl)  verifyBtnTextEl.textContent = 'Retry Verify';
                    if (verifyFeedbackEl) { verifyFeedbackEl.textContent = data.message || 'Verification failed.'; verifyFeedbackEl.className = 'text-xs font-medium text-red-600'; }
                }
            } catch (err) {
                verifySignBtn.disabled = false;
                if (verifyBtnTextEl)  verifyBtnTextEl.textContent = 'Retry Verify';
                if (verifyFeedbackEl) { verifyFeedbackEl.textContent = 'Network error. Please try again.'; verifyFeedbackEl.className = 'text-xs font-medium text-red-600'; }
            }
        });
    }

    refreshSendBtn();

    // ── Director Cadastral Signature wiring (cadastral context step 5) ──
    const dcMethodSelect      = form.querySelector('[data-dc-signature-method]');
    const dcOtpWrapper        = form.querySelector('[data-dc-otp-wrapper]');
    const dcPasswordWrapper   = form.querySelector('[data-dc-password-wrapper]');
    const dcSendOtpBtn        = form.querySelector('[data-dc-send-otp]');
    const dcVerifyBtn         = form.querySelector('[data-dc-verify-btn]');
    const dcVerifyFeedback    = form.querySelector('[data-dc-verify-feedback]');
    const dcSigWrap           = form.querySelector('[data-dc-sig-wrap]');
    const dcSigImg            = form.querySelector('[data-dc-sig-img]');
    const dcSigVerifiedInput  = form.querySelector('[data-dc-signature-verified]');
    const dcVerificationCodeInput = form.querySelector('[name="director_cadastral_verification_code"]');
    const dcVerificationPasswordInput = form.querySelector('[name="director_cadastral_verification_password"]');

    function resetDcVerificationState() {
        if (dcSigVerifiedInput) dcSigVerifiedInput.value = '0';
        if (dcSigWrap) dcSigWrap.classList.add('hidden');
        if (dcSigImg) dcSigImg.removeAttribute('src');
        if (dcVerifyBtn) {
            dcVerifyBtn.disabled = false;
            dcVerifyBtn.classList.remove('bg-green-600', 'hover:bg-green-700');
            dcVerifyBtn.classList.add('bg-indigo-600', 'hover:bg-indigo-700');
        }
        const dcBtnText = dcVerifyBtn ? dcVerifyBtn.querySelector('[data-dc-verify-btn-text]') : null;
        if (dcBtnText) dcBtnText.textContent = 'Verify Signature';
        if (dcVerifyFeedback) {
            dcVerifyFeedback.textContent = '';
            dcVerifyFeedback.className = 'text-xs font-medium text-slate-500';
        }
    }

    function syncDcVerificationUi() {
        if (!dcMethodSelect) return;
        const method = dcMethodSelect.value;
        if (dcOtpWrapper)      dcOtpWrapper.classList.toggle('hidden',      !(method === 'email' || method === 'sms'));
        if (dcPasswordWrapper) dcPasswordWrapper.classList.toggle('hidden', method !== 'password');
    }

    if (dcMethodSelect) {
        dcMethodSelect.addEventListener('change', syncDcVerificationUi);
        dcMethodSelect.addEventListener('change', resetDcVerificationState);
        syncDcVerificationUi();
    }

    if (dcVerificationCodeInput) {
        dcVerificationCodeInput.addEventListener('input', resetDcVerificationState);
    }

    if (dcVerificationPasswordInput) {
        dcVerificationPasswordInput.addEventListener('input', resetDcVerificationState);
    }

    if (dcSendOtpBtn) {
        dcSendOtpBtn.addEventListener('click', function () {
            const method = dcMethodSelect ? dcMethodSelect.value : '';
            if (!(method === 'email' || method === 'sms')) {
                if (window.Swal) Swal.fire('Required', 'Select Email OTP or SMS OTP to request a code.', 'warning');
                return;
            }
            fetch('{{ route("survey-report.send-land-officer-otp") }}', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') || {}).getAttribute?.('content') || '',
                    'Accept': 'application/json',
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ land_officer_id: {{ Auth::id() }}, method: method })
            })
            .then(function (res) { return res.json(); })
            .then(function (result) {
                if (result.success) {
                    if (window.Swal) Swal.fire('Success', result.message, 'success');
                } else {
                    if (window.Swal) Swal.fire('Error', result.message || 'Could not send OTP.', 'error');
                }
            })
            .catch(function () {
                if (window.Swal) Swal.fire('Error', 'Network error. Please try again.', 'error');
            });
        });
    }

    if (dcVerifyBtn) {
        dcVerifyBtn.addEventListener('click', async function () {
            const method   = dcMethodSelect ? dcMethodSelect.value : '';
            const code     = dcVerificationCodeInput ? dcVerificationCodeInput.value : '';
            const password = dcVerificationPasswordInput ? dcVerificationPasswordInput.value : '';

            if (!method) {
                if (window.Swal) Swal.fire('Required', 'Please select a verification method.', 'warning');
                return;
            }
            if ((method === 'email' || method === 'sms') && !code) {
                if (window.Swal) Swal.fire('Required', 'Please enter the OTP code sent to you.', 'warning');
                return;
            }
            if (method === 'password' && !password) {
                if (window.Swal) Swal.fire('Required', 'Please enter your password to verify.', 'warning');
                return;
            }

            dcVerifyBtn.disabled = true;
            const dcBtnText = dcVerifyBtn.querySelector('[data-dc-verify-btn-text]');
            if (dcBtnText) dcBtnText.textContent = 'Verifying…';
            if (dcVerifyFeedback) { dcVerifyFeedback.textContent = ''; dcVerifyFeedback.className = 'text-xs font-medium text-slate-500'; }

            try {
                const res  = await fetch('{{ route("survey-report.verify-signature") }}', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') || {}).content || '',
                        'Accept':       'application/json',
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        verification_scope:          'cadastral',
                        land_officer_id:               {{ Auth::id() }},
                        signature_verification_method: method,
                        verification_code:             code,
                        verification_password:         password,
                    }),
                });
                const dcData = await res.json();

                if (dcData.success) {
                    if (dcSigVerifiedInput) dcSigVerifiedInput.value = '1';
                    if (dcSigImg && dcData.signature_url) dcSigImg.src = dcData.signature_url;
                    if (dcSigWrap) dcSigWrap.classList.remove('hidden');
                    dcVerifyBtn.classList.remove('bg-indigo-600', 'hover:bg-indigo-700');
                    dcVerifyBtn.classList.add('bg-green-600', 'hover:bg-green-700');
                    if (dcBtnText) dcBtnText.textContent = 'Verified ✓';
                    if (dcVerifyFeedback) { dcVerifyFeedback.textContent = 'Signature verified.'; dcVerifyFeedback.className = 'text-xs font-medium text-green-600'; }
                    if (typeof lucide !== 'undefined' && lucide.createIcons) lucide.createIcons();
                } else {
                    dcVerifyBtn.disabled = false;
                    if (dcBtnText) dcBtnText.textContent = 'Retry Verify';
                    if (dcVerifyFeedback) { dcVerifyFeedback.textContent = dcData.message || 'Verification failed.'; dcVerifyFeedback.className = 'text-xs font-medium text-red-600'; }
                }
            } catch (err) {
                dcVerifyBtn.disabled = false;
                const dcBtnTextFallback = dcVerifyBtn.querySelector('[data-dc-verify-btn-text]');
                if (dcBtnTextFallback) dcBtnTextFallback.textContent = 'Retry Verify';
                if (dcVerifyFeedback) { dcVerifyFeedback.textContent = 'Network error. Please try again.'; dcVerifyFeedback.className = 'text-xs font-medium text-red-600'; }
            }
        });
    }

    const previewField = form.querySelector('[data-location-preview]');
    if (!previewField) {
        return;
    }

    const inputs = {
        plot: form.querySelector('[data-location-field="plot"]'),
        tp: form.querySelector('[data-location-field="tp"]'),
        district: form.querySelector('[data-location-field="district"]'),
        districtOther: form.querySelector('[data-location-field="district-other"]'),
        street: form.querySelector('[data-location-field="street"]'),
        streetOther: form.querySelector('[data-location-field="street-other"]'),
        lga: form.querySelector('[data-location-field="lga"]'),
    };

    const wrappers = {
        district: form.querySelector('[data-district-other-wrapper]'),
        street: form.querySelector('[data-street-other-wrapper]'),
    };

    const isOther = (value) => (value || '').toLowerCase() === 'other';

    function toggleWrapper(selectEl, wrapperEl, otherInput) {
        if (!wrapperEl || !selectEl) return;
        const shouldShow = isOther(selectEl.value) || (!!otherInput && otherInput.value.trim().length > 0);
        wrapperEl.classList.toggle('hidden', !shouldShow);
    }

    function getResolvedValue(selectEl, otherInput) {
        if (!selectEl) return '';
        const value = (selectEl.value || '').trim();
        if (isOther(value)) {
            return (otherInput?.value || '').trim();
        }
        return value;
    }

    function buildPreview() {
        const parts = [];
        const plot = inputs.plot ? inputs.plot.value.trim() : '';
        const street = getResolvedValue(inputs.street, inputs.streetOther);
        const district = getResolvedValue(inputs.district, inputs.districtOther);
        const lga = inputs.lga ? inputs.lga.value.trim() : '';

        if (plot) {
            parts.push(`PLOT ${plot}`);
        }
        if (street) {
            parts.push(street);
        }
        if (district) {
            parts.push(district);
        }
        if (lga) {
            parts.push(lga);
        }

        previewField.value = parts.length ? parts.map((part) => part.toUpperCase()).join(' | ') : '';
    }

    let hasUserInteraction = false;

    function scheduleRebuild() {
        hasUserInteraction = true;
        buildPreview();
    }

    function bindInput(el, eventName = 'input') {
        if (!el || el.disabled) return;
        el.addEventListener(eventName, scheduleRebuild);
    }

    bindInput(inputs.plot);
    bindInput(inputs.tp);
    bindInput(inputs.lga, 'change');
    bindInput(inputs.districtOther);
    bindInput(inputs.streetOther);

    if (inputs.district && !inputs.district.disabled) {
        inputs.district.addEventListener('change', () => {
            syncWrappers();
            scheduleRebuild();
        });
    }

    if (inputs.street && !inputs.street.disabled) {
        inputs.street.addEventListener('change', () => {
            syncWrappers();
            scheduleRebuild();
        });
    }

    function syncWrappers() {
        toggleWrapper(inputs.district, wrappers.district, inputs.districtOther);
        toggleWrapper(inputs.street, wrappers.street, inputs.streetOther);
    }

    function hydrateFromDescription() {
        const description = (previewField.value || '').trim();
        if (!description || !description.includes('|')) {
            syncWrappers();
            return;
        }

        const segments = description.split('|').map((segment) => segment.trim()).filter(Boolean);

        const plotSegmentIndex = segments.findIndex((segment) => /^PLOT\s+/i.test(segment));
        if (plotSegmentIndex >= 0 && inputs.plot) {
            inputs.plot.value = segments.splice(plotSegmentIndex, 1)[0].replace(/^PLOT\s+/i, '').trim();
        }

        function assignSelect(selectEl, otherInput) {
            if (!selectEl) return;
            const availableOptions = Array.from(selectEl.options || []).map((opt) => opt.value).filter(Boolean);
            const matchIndex = segments.findIndex((segment) => availableOptions.some((opt) => opt.toUpperCase() === segment.toUpperCase()));

            if (matchIndex >= 0) {
                const matchedValue = segments.splice(matchIndex, 1)[0];
                const option = availableOptions.find((opt) => opt.toUpperCase() === matchedValue.toUpperCase());
                if (option) {
                    selectEl.value = option;
                }
                return;
            }

            if (segments.length && otherInput) {
                selectEl.value = 'Other';
                otherInput.value = segments.shift();
            }
        }

        assignSelect(inputs.street, inputs.streetOther);
        assignSelect(inputs.district, inputs.districtOther);

        const lgaOptions = Array.from(inputs.lga?.options || []).map((opt) => opt.value).filter(Boolean);
        const lgaMatchIndex = segments.findIndex((segment) => lgaOptions
            .some((opt) => opt.toUpperCase() === segment.toUpperCase()));
        if (lgaMatchIndex >= 0 && inputs.lga) {
            const matchedSegment = segments.splice(lgaMatchIndex, 1)[0];
            const matchedValue = lgaOptions.find((opt) => opt.toUpperCase() === matchedSegment.toUpperCase());
            if (matchedValue) {
                inputs.lga.value = matchedValue;
            }
        }

        syncWrappers();
    }

    hydrateFromDescription();

    const validationTargets = new Map();

    function setModalStep(step) {
        if (typeof window.Alpine === 'undefined' || typeof window.Alpine.$data !== 'function') {
            return;
        }

        const component = window.Alpine.$data(modalRoot);
        if (component && typeof component.currentStep !== 'undefined') {
            component.currentStep = step;
        }
    }

    function firstEnabledField(...fields) {
        return fields.find((field) => field && !field.disabled) || fields.find(Boolean) || null;
    }

    function getFieldHost(field) {
        return field ? (field.closest('.space-y-1.5') || field.parentElement) : null;
    }

    function clearFieldError(key) {
        const field = validationTargets.get(key);
        if (field) {
            field.classList.remove('border-red-300', 'ring-2', 'ring-red-100');
            field.removeAttribute('aria-invalid');
        }

        const errorEl = form.querySelector(`[data-validation-error="${key}"]`);
        if (errorEl) {
            errorEl.remove();
        }

        validationTargets.delete(key);
    }

    function clearAllValidationErrors() {
        Array.from(validationTargets.keys()).forEach(clearFieldError);
    }

    function showFieldError(key, field, message) {
        if (!field) {
            return;
        }

        clearFieldError(key);
        field.classList.add('border-red-300', 'ring-2', 'ring-red-100');
        field.setAttribute('aria-invalid', 'true');
        validationTargets.set(key, field);

        const host = getFieldHost(field);
        if (!host) {
            return;
        }

        const errorEl = document.createElement('p');
        errorEl.dataset.validationError = key;
        errorEl.className = 'mt-1 text-xs font-medium text-red-600';
        errorEl.textContent = message;
        host.appendChild(errorEl);
    }

    function addError(errors, step, key, message, field) {
        if (errors.some((error) => error.key === key)) {
            return;
        }

        errors.push({ step, key, message, field });
    }

    function requireValue(errors, step, key, field, message) {
        if (!field || field.disabled) {
            return;
        }

        if (!String(field.value || '').trim()) {
            addError(errors, step, key, message, field);
        }
    }

    function requireResolvedSelect(errors, step, key, selectField, otherField, emptyMessage, otherMessage) {
        if (!selectField || selectField.disabled) {
            return;
        }

        const selectedValue = String(selectField.value || '').trim();
        if (!selectedValue) {
            addError(errors, step, key, emptyMessage, selectField);
            return;
        }

        if (isOther(selectedValue) && !String(otherField?.value || '').trim()) {
            addError(errors, step, key, otherMessage, otherField || selectField);
        }
    }

    function collectValidationErrors(steps) {
        const errors = [];

        steps.forEach((step) => {
            if (step === 1) {
                requireValue(errors, 1, 'lands_section_date', landsSectionDateInput, 'Select the Lands section date.');
                requireValue(errors, 1, 'lands_section_name', officerSelect, 'Officer details are required.');
                requireValue(errors, 1, 'lands_section_rank', rankSelect, 'Select the Lands officer rank.');

                const activeFileNumberField = firstEnabledField(landsFileNoInput, srFileNumberInput);
                const activeFileTitleField = firstEnabledField(landsFileTitleInput, srFileTitleInput);

                if (landsFileNoInput && !landsFileNoInput.disabled) {
                    requireValue(errors, 1, 'file_number', activeFileNumberField, 'Select a file number before continuing.');
                    requireValue(errors, 1, 'file_title', activeFileTitleField, 'File title is required.');
                    requireValue(errors, 1, 'signature_verification_method', methodSelect, 'Select how the Lands officer signature will be verified.');

                    if (sigVerifiedInput && sigVerifiedInput.value !== '1') {
                        addError(errors, 1, 'signature_verified', 'Verify the Lands officer signature before sending the request to Cadastral.', methodSelect || verifySignBtn);
                    }
                }
            }

            if (step === 2) {
                requireValue(errors, 2, 'file_number', srFileNumberInput, 'Select a file number before continuing.');
                requireValue(errors, 2, 'file_title', srFileTitleInput, 'File title is required.');
                requireValue(errors, 2, 'rofo_no', rofoInput, 'ROFO number is required.');
            }

            if (step === 3) {
                requireValue(errors, 3, 'item_g_no', itemGInput, 'Item G number is required.');
                requireValue(errors, 3, 'plot_description', inputs.plot, 'Enter the plot number.');
                requireValue(errors, 3, 'lga_meta', inputs.lga, 'Select the LGA.');

                if (previewField && !previewField.disabled && !String(previewField.value || '').trim()) {
                    addError(errors, 3, 'plot_preview', 'Complete the property location details so the plot description can be generated.', inputs.plot || previewField);
                }
            }

            if (step === 4) {
                requireValue(errors, 4, 'survey_necessary', surveyNecessaryInput, 'State whether survey will be necessary.');
            }

            if (step === 5) {
                requireValue(errors, 5, 'director_cadastral_signature_method', dcMethodSelect, 'Select how the Director Cadastral signature will be verified.');

                if (dcMethodSelect && !dcMethodSelect.disabled && dcSigVerifiedInput && dcSigVerifiedInput.value !== '1') {
                    addError(errors, 5, 'director_cadastral_signature_verified', 'Verify the Director Cadastral signature before submitting this request.', dcMethodSelect || dcVerifyBtn);
                }
            }
        });

        return errors;
    }

    function presentValidationErrors(errors, shouldAlert = true) {
        clearAllValidationErrors();

        if (!errors.length) {
            return true;
        }

        errors.forEach((error) => {
            showFieldError(error.key, error.field, error.message);
        });

        const firstError = errors[0];
        setModalStep(firstError.step);

        if (firstError.field && typeof firstError.field.focus === 'function') {
            setTimeout(() => firstError.field.focus(), 120);
        }

        if (shouldAlert) {
            if (window.Swal) {
                Swal.fire('Incomplete form', firstError.message, 'warning');
            } else {
                alert(firstError.message);
            }
        }

        return false;
    }

    function resolveServerField(name) {
        switch (name) {
            case 'file_number':
                return firstEnabledField(landsFileNoInput, srFileNumberInput);
            case 'file_title':
                return firstEnabledField(landsFileTitleInput, srFileTitleInput);
            case 'lands_section_date':
                return landsSectionDateInput;
            case 'lands_section_name':
            case 'land_officer_id':
                return officerSelect;
            case 'lands_section_rank':
                return rankSelect;
            case 'rofo_no':
                return rofoInput;
            case 'item_g_no':
                return itemGInput;
            case 'plot_description':
                return inputs.plot || previewField;
            case 'survey_necessary':
                return surveyNecessaryInput;
            case 'signature_verification_method':
            case 'signature_verified':
                return methodSelect;
            case 'director_cadastral_signature_method':
            case 'director_cadastral_signature_verified':
                return dcMethodSelect;
            default:
                return form.querySelector(`[name="${name}"]`);
        }
    }

    function resolveServerStep(name) {
        switch (name) {
            case 'file_number':
            case 'file_title':
                return landsFileNoInput && !landsFileNoInput.disabled ? 1 : 2;
            case 'lands_section_date':
            case 'lands_section_name':
            case 'land_officer_id':
            case 'lands_section_rank':
            case 'signature_verification_method':
            case 'signature_verified':
                return 1;
            case 'rofo_no':
                return 2;
            case 'item_g_no':
            case 'plot_description':
                return 3;
            case 'survey_necessary':
                return 4;
            case 'director_cadastral_signature_method':
            case 'director_cadastral_signature_verified':
                return 5;
            default:
                return 1;
        }
    }

    form.__clearSurveyReportErrors = clearAllValidationErrors;
    form.__validateSurveyReportStep = function (step) {
        return presentValidationErrors(collectValidationErrors([step]));
    };
    form.__validateSurveyReportForm = function () {
        return presentValidationErrors(collectValidationErrors([1, 2, 3, 4, 5]));
    };
    form.__applySurveyReportServerErrors = function (errors) {
        const normalizedErrors = [];

        Object.entries(errors || {}).forEach(([name, messages]) => {
            const message = Array.isArray(messages) ? messages[0] : messages;
            if (!message) {
                return;
            }

            normalizedErrors.push({
                step: resolveServerStep(name),
                key: name,
                message,
                field: resolveServerField(name),
            });
        });

        return presentValidationErrors(normalizedErrors, false);
    };

    [
        [landsFileNoInput, 'file_number', 'change'],
        [landsFileTitleInput, 'file_title', 'input'],
        [srFileNumberInput, 'file_number', 'change'],
        [srFileTitleInput, 'file_title', 'input'],
        [landsSectionDateInput, 'lands_section_date', 'change'],
        [officerSelect, 'lands_section_name', 'input'],
        [officerSelect, 'land_officer_id', 'input'],
        [rankSelect, 'lands_section_rank', 'change'],
        [methodSelect, 'signature_verification_method', 'change'],
        [rofoInput, 'rofo_no', 'input'],
        [itemGInput, 'item_g_no', 'input'],
        [inputs.plot, 'plot_description', 'input'],
        [inputs.lga, 'lga_meta', 'change'],
        [surveyNecessaryInput, 'survey_necessary', 'input'],
        [dcMethodSelect, 'director_cadastral_signature_method', 'change'],
    ].forEach(([field, key, eventName]) => {
        if (!field) {
            return;
        }

        field.addEventListener(eventName, () => {
            clearFieldError(key);

            if (key === 'plot_description') {
                clearFieldError('plot_preview');
            }

            if (key === 'signature_verification_method') {
                clearFieldError('signature_verified');
            }

            if (key === 'director_cadastral_signature_method') {
                clearFieldError('director_cadastral_signature_verified');
            }
        });
    });

    form.addEventListener('reset', () => {
        setTimeout(() => {
            hasUserInteraction = false;
            clearAllValidationErrors();
            if (previewField) {
                previewField.value = '';
            }
            if (inputs.plot) inputs.plot.value = '';
            if (inputs.tp) inputs.tp.value = '';
            if (inputs.street) inputs.street.value = '';
            if (inputs.streetOther) inputs.streetOther.value = '';
            if (inputs.district) inputs.district.value = '';
            if (inputs.districtOther) inputs.districtOther.value = '';
            if (inputs.lga) inputs.lga.value = '';
            syncWrappers();
        }, 0);
    });

    if (!hasUserInteraction) {
        syncWrappers();
    }

    // Re-initialize lucide icons after Alpine renders steps
    if (typeof lucide !== 'undefined' && lucide.createIcons) {
        setTimeout(() => lucide.createIcons(), 100);
    }
})();
</script>
 