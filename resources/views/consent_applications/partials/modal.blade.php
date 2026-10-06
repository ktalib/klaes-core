@php
    $streetOptions = $streetNames->merge([(object)['name' => 'Other']]);
@endphp
<!-- Generate Consent Letter Modal -->
<div id="generate-modal" class="fixed inset-0 z-50 hidden flex items-center justify-center p-4">
    <!-- Background overlay -->
    <div class="fixed inset-0 bg-slate-900/40 backdrop-blur-sm transition-opacity" aria-hidden="true"
        id="modal-overlay"></div>

    <div
        class="relative bg-white rounded-3xl shadow-2xl w-full max-w-4xl max-h-[90vh] flex flex-col overflow-hidden border border-slate-100 transform transition-all">
        <!-- Header: Fixed -->
        <div class="px-8 py-6 border-b border-slate-100 flex items-center justify-between bg-white shrink-0">
            <div>
                <h3 class="text-xl font-bold text-slate-900" id="modal-title">Generate Letter</h3>
                <p class="text-xs text-slate-500 mt-1 uppercase tracking-widest font-semibold">File Selection & Metadata
                </p>
            </div>
            <button type="button"
                class="close-modal text-slate-400 hover:text-slate-600 p-2 hover:bg-slate-50 rounded-xl transition">
                <i data-lucide="x" class="h-6 w-6"></i>
            </button>
        </div>
 
        <!-- Form Body: Scrollable -->
        <form id="consent-form" class="flex-1 overflow-y-auto">
            @csrf
            <input type="hidden" name="application_id" id="application_id">
            <input type="hidden" name="application_type" id="application_type">
            <div class="px-8 py-8 space-y-8">
                {{-- Keep the file workflow at the top of the modal card, ahead of
                     the application wizard. It is populated as soon as a file is
                     selected and remains visible while the officer works through
                     the form. --}}
                <div id="consent-pipeline-strip"
                     class="p-5 rounded-xl border border-slate-200 bg-white shadow-sm"
                     style="display:none"></div>

                <div class="wizard-progress mb-2">
                    <div class="flex items-center justify-between">
                        @foreach([
                            ['step' => 1, 'label' => 'Basic Info', 'icon' => 'file-text'],
                            ['step' => 2, 'label' => 'Application', 'icon' => 'map'],
                            ['step' => 3, 'label' => 'Parties', 'icon' => 'users'],
                            ['step' => 4, 'label' => 'Payments', 'icon' => 'credit-card'],
                        ] as $index => $s)
                            @if($index > 0)
                                <div class="flex-1 h-0.5 mx-2 wizard-connector" data-before-step="{{ $s['step'] }}">
                                    <div class="h-full bg-slate-200 rounded transition-colors duration-300 connector-bar"></div>
                                </div>
                            @endif
                            <div class="flex flex-col items-center wizard-step-indicator" data-step-indicator="{{ $s['step'] }}">
                                <div
                                    class="w-10 h-10 rounded-full flex items-center justify-center border-2 transition-all duration-300 {{ $s['step'] === 1 ? 'border-teal-500 bg-teal-500 text-white' : 'border-slate-300 bg-white text-slate-400' }}"
                                    id="wizard-circle-{{ $s['step'] }}">
                                    <i data-lucide="{{ $s['icon'] }}" class="w-4 h-4"></i>
                                </div>
                                <span
                                    class="text-xs font-medium mt-2 transition-colors duration-300 {{ $s['step'] === 1 ? 'text-teal-600' : 'text-slate-400' }}"
                                    id="wizard-label-{{ $s['step'] }}">{{ $s['label'] }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>

                <div class="wizard-step" data-step="1">
                <!-- File Selection -->
                <div class="space-y-4">
                    <div class="flex items-center gap-3 mb-2">
                        <div class="w-8 h-8 rounded-full bg-blue-50 flex items-center justify-center text-blue-600">
                            <i data-lucide="file-text" class="h-4 w-4"></i>
                        </div>
                        <h4 class="text-sm font-bold text-slate-700 uppercase tracking-wider">File & Consent Type</h4>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">

                        {{-- Transaction type: its own choice, beside the consent type. It fixes
                             the default rates, the stamp duty payee (KIRS / FIRS) and the letter
                             that prints. Only the party options (Individual / Company) are listed:
                             a Gift or a Mortgage settles the type by itself, so choosing either as
                             the Consent Type locks this field to it (applyConsentTypeToTransaction()).
                             Those implied options stay in the list, hidden, so they can be set.
                             Profiles come from config/consent_bill.php, the same table the server
                             recalculates from. --}}
                        @php
                            $consentTransactionTypes = config('consent_bill.transaction_types', []);
                            $consentPayees = config('consent_bill.payees', []);
                            $consentImpliedTypes = config('consent_bill.consent_type_transactions', []);
                        @endphp
                        <div>
                            <label for="transaction_type" class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">Transfer
                                Type <span class="text-red-500">*</span></label>
                            <div class="relative">
                                <select name="transaction_type" id="transaction_type" required
                                    data-types="{{ json_encode($consentTransactionTypes) }}"
                                    data-payees="{{ json_encode($consentPayees) }}"
                                    data-implied="{{ json_encode($consentImpliedTypes) }}"
                                    class="w-full px-4 py-3 rounded-xl border border-slate-200 bg-slate-50 focus:border-blue-500 focus:ring-2 focus:ring-blue-100 transition focus:bg-white text-sm font-bold text-slate-700 appearance-none cursor-pointer">
                                    <option value="">Select transfer type…</option>
                                    @foreach ($consentTransactionTypes as $typeKey => $typeProfile)
                                        <option value="{{ $typeKey }}" @if (in_array($typeKey, $consentImpliedTypes, true)) hidden data-implied-only="1" @endif>{{ $typeProfile['label'] }}</option>
                                    @endforeach
                                </select>
                                <div
                                    class="absolute right-4 top-1/2 -translate-y-1/2 pointer-events-none text-slate-400">
                                    <i data-lucide="chevron-down" class="h-4 w-4"></i>
                                </div>
                            </div>
                            <p id="transaction_type_error" class="hidden mt-1.5 text-xs font-semibold text-red-600"></p>

                            {{-- What the choice means, filled in by applyTransactionType(). --}}
                            <div id="transaction-type-summary"
                                class="hidden mt-3 p-3 rounded-xl border text-xs leading-relaxed"></div>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">Consent
                                Type <span class="text-red-500">*</span></label>
                            <div class="relative">
                                <select name="consent_type" id="consent_type" required
                                    class="w-full px-4 py-3 rounded-xl border border-slate-200 bg-slate-50 focus:border-blue-500 focus:ring-2 focus:ring-blue-100 transition focus:bg-white text-sm font-bold text-slate-700 appearance-none cursor-pointer">
                                    <option value="Assignment">Assignment</option>
                                    <option value="Gift">Gift </option>
                                    <option value="Mortgage">Mortgage</option>
                                     <option value="Tripartite Mortgage">Tripartite Mortgage</option>
                                </select>
                                <div
                                    class="absolute right-4 top-1/2 -translate-y-1/2 pointer-events-none text-slate-400">
                                    <i data-lucide="chevron-down" class="h-4 w-4"></i>
                                </div>
                            </div>
                        </div>

                        <!-- NEW PROMPT HERE -->
                        <div id="multiple-properties-prompt-container" class="hidden">
                            <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">Multiple Properties?</label>
                            <div class="flex items-center h-[46px]">
                                <label class="flex items-center gap-2 cursor-pointer">
                                    <input type="checkbox" id="has_multiple_properties" class="w-4 h-4 text-blue-600 rounded border-slate-300 focus:ring-blue-500">
                                    <span class="text-sm font-medium text-slate-700">Yes, involves more than one</span>
                                </label>
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">File
                                Number <span class="text-red-500">*</span></label>
                            <div class="flex items-center gap-2">
                                <div class="relative flex-1">
                                    <input type="text" name="file_number" id="file_number" readonly required
                                        class="w-full px-4 py-3 rounded-xl border border-slate-200 bg-slate-50 text-sm font-bold font-mono text-slate-700"
                                        placeholder="Select file...">
                                    <div id="file-selection-indicator"
                                        class="hidden absolute right-3 top-1/2 -translate-y-1/2">
                                        <i data-lucide="check-circle" class="h-5 w-5 text-emerald-500"></i>
                                    </div>
                                </div>
                                <button type="button" id="select-fileno-btn"
                                    class="p-3 rounded-xl bg-blue-50 text-blue-600 font-bold border border-blue-100 hover:bg-blue-100 transition flex items-center justify-center shrink-0"
                                    title="Select File">
                                    <i data-lucide="search" class="h-5 w-5"></i>
                                </button>
                                <button type="button" id="add-fileno-btn"
                                    class="hidden p-3 rounded-xl bg-emerald-50 text-emerald-600 font-bold border border-emerald-100 hover:bg-emerald-100 transition items-center justify-center shrink-0"
                                    title="Add Another File Number">
                                    <i data-lucide="plus" class="h-5 w-5"></i>
                                </button>
                            </div>

                            {{-- The field is readonly, and the browser skips constraint
                                 validation on readonly inputs, so a missing file used to fail
                                 silently. validateWizardStep() writes the reason here. --}}
                            <p id="file_number_error" class="hidden mt-1.5 text-xs font-semibold text-red-600"></p>

                            <div id="additional-file-numbers-container" class="space-y-2 mt-2">
                                <!-- Additional file numbers cloned here -->
                            </div>

                            {{-- Manual valuation override. Rendered only for an
                                 officer holding Approve on Deeds - Consent; the
                                 same permission is re-checked in store(), so
                                 hiding it here is convenience, not the control. --}}
                            @canDo('Deeds - Consent', 'approve')
                                <div id="manual-valuation-box"
                                     class="hidden mt-4 p-4 rounded-xl border border-amber-200 bg-amber-50/60">
                                    <label class="flex items-start gap-2.5 cursor-pointer">
                                        <input type="checkbox" name="manual_valuation" id="manual_valuation" value="1"
                                            class="mt-0.5 rounded text-amber-600 focus:ring-amber-500 w-4 h-4 cursor-pointer">
                                        <span>
                                            <span class="block text-sm font-bold text-amber-900">Manual Valuation</span>
                                            <span class="block text-xs text-amber-800 leading-relaxed mt-0.5">
                                                Accept a valuation carried out before KLAES for this file. Use only
                                                where the valuation was genuinely done and the record exists off
                                                system. It is recorded against your name.
                                            </span>
                                        </span>
                                    </label>

                                    <div id="manual-valuation-fields" class="hidden mt-3 space-y-3">
                                        <p class="text-xs font-semibold text-amber-900 leading-relaxed">
                                            You must also unlock the <strong>Assessed Amount</strong> on the Payments
                                            step and enter what the property was valued at — without it the consent
                                            letter prints with no figures.
                                        </p>
                                    </div>
                                </div>
                            @endcanDo

                            <div class="multi-property-notice hidden mt-3 flex items-start gap-2.5 p-3 rounded-xl bg-amber-50 border border-amber-200">
                                <i data-lucide="info" class="h-4 w-4 text-amber-600 mt-0.5 shrink-0"></i>
                                <p class="text-xs font-semibold text-amber-800 leading-relaxed">
                                    This consent involves <span class="multi-property-count">2</span> properties.
                                    Each file number carries its own property description.
                                </p>
                            </div>
                        </div>

                    </div>
                </div>
                </div>

                <div class="wizard-step hidden" data-step="2">
                <!-- Application Details (Application variant) -->
                <div class="variant-application hidden pt-8 border-t-2 border-indigo-100">
                    <div class="flex items-center gap-3 mb-6">
                        <div class="w-10 h-10 rounded-2xl bg-slate-800 flex items-center justify-center text-white shadow-lg shadow-slate-200">
                            <i data-lucide="file-text" class="h-5 w-5"></i>
                        </div>
                        <div>
                            <h4 class="text-sm font-black text-slate-800 uppercase tracking-widest">Application Details</h4>
                            <p class="text-[10px] text-slate-500 font-bold uppercase">Assignment or mortgage specifics</p>
                        </div>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div id="right-of-occupancy-number-wrapper">
                            <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">Right of
                                Occupancy Number<span id="main-rofo-index"></span></label>
                            <input type="hidden" name="right_of_occupancy_number" id="right_of_occupancy_number">
                            <input type="text" id="right_of_occupancy_number_display" disabled
                                class="w-full px-4 py-3 rounded-xl border border-slate-200 bg-slate-100 text-slate-500 cursor-not-allowed text-sm font-medium"
                                placeholder="Auto-filled from selected file number">
                            <div id="applicant-name-preview-wrapper" class="mt-2 hidden flex items-center gap-2">
                                <span class="text-[10px] font-bold text-slate-500 uppercase tracking-wider">Applicant:</span>
                                <span id="applicant_name_preview"
                                    class="inline-flex items-center px-3 py-1 rounded-full bg-blue-50 text-blue-700 border border-blue-100 text-xs font-semibold"></span>
                            </div>

                            <div id="additional-rofo-container">
                                <!-- One row per additional file number, cloned here -->
                            </div>
                        </div>
                        <div class="hidden">
                            <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">Land Use</label>
                            <select name="right_of_occupancy_landuse" id="right_of_occupancy_landuse"
                                class="w-full px-4 py-3 rounded-xl border border-slate-200 bg-slate-50 focus:border-blue-500 focus:ring-2 focus:ring-blue-100 transition focus:bg-white text-sm font-medium">
                                <option value="">Select Land Use</option>
                                @foreach(($landUseTypes ?? collect()) as $landUseType)
                                    <option value="{{ $landUseType->name }}" data-landuse-id="{{ $landUseType->id }}">{{ $landUseType->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">Purpose of Right of Occupancy</label>
                            <select name="purpose_of_right_of_occupancy" id="purpose_of_right_of_occupancy"
                                class="w-full px-4 py-3 rounded-xl border border-slate-200 bg-slate-50 focus:border-blue-500 focus:ring-2 focus:ring-blue-100 transition focus:bg-white text-sm font-medium">
                                <option value="">Select Land Use</option>
                                <option value="Agricultural">Agricultural</option>
                                <option value="Commercial">Commercial</option>
                                <option value="Industrial">Industrial</option>
                                <option value="Residential">Residential</option>
                            </select>
                        </div>
                        <div class="assignment-only-field hidden">
                            <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">Original Holder Name</label>
                            <input type="text" name="original_holder_name"
                                class="w-full px-4 py-3 rounded-xl border border-slate-200 bg-slate-50 focus:border-blue-500 focus:ring-2 focus:ring-blue-100 transition focus:bg-white text-sm font-medium"
                                placeholder="If different from applicant">
                        </div>
                        <div class="assignment-only-field hidden">
                            <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">Postal Address / GSM</label>
                            <input type="text" name="postal_address_gsm"
                                class="w-full px-4 py-3 rounded-xl border border-slate-200 bg-slate-50 focus:border-blue-500 focus:ring-2 focus:ring-blue-100 transition focus:bg-white text-sm font-medium"
                                placeholder="Postal address or GSM">
                        </div>
                        <div class="assignment-only-field hidden">
                            <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">Nationality</label>
                            <input type="text" name="nationality" value="Nigeria"
                                class="w-full px-4 py-3 rounded-xl border border-slate-200 bg-slate-50 focus:border-blue-500 focus:ring-2 focus:ring-blue-100 transition focus:bg-white text-sm font-medium"
                                placeholder="Nationality">
                        </div>
                        <div class="assignment-only-field hidden">
                            <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">State of Origin</label>
                            <select name="state_of_origin"
                                class="w-full px-4 py-3 rounded-xl border border-slate-200 bg-slate-50 focus:border-blue-500 focus:ring-2 focus:ring-blue-100 transition focus:bg-white text-sm font-medium">
                                <option value="">Select State</option>
                                @foreach($states as $state)
                                    <option value="{{ $state->StateName }}">{{ $state->StateName }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="assignment-only-field hidden">
                            <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">Stage of Development</label>
                            <input type="text" name="stage_of_development"
                                class="w-full px-4 py-3 rounded-xl border border-slate-200 bg-slate-50 focus:border-blue-500 focus:ring-2 focus:ring-blue-100 transition focus:bg-white text-sm font-medium"
                                placeholder="Stage of development">
                        </div>
                        <div class=" hidden">
                            <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">Location of Right of Occupancy</label>
                            <select name="location_of_right_of_occupancy" id="location_rofo_district"
                                class="w-full px-4 py-3 rounded-xl border border-slate-200 bg-slate-50 focus:border-blue-500 focus:ring-2 focus:ring-blue-100 transition focus:bg-white text-sm font-medium" data-fill-options="districts">
                                <option value="">Select District</option>
{{-- options: district list, filled once by the script at the end of this partial --}}
                                <option value="Other">Other</option>
                            </select>
                            <input type="text" id="location_rofo_district_other"
                                class="hidden w-full mt-2 px-4 py-3 rounded-xl border border-slate-200 bg-slate-50 focus:border-blue-500 focus:ring-2 focus:ring-blue-100 transition focus:bg-white text-sm font-medium"
                                placeholder="Specify district...">
                        </div>
                        <div class=" hidden">
                            <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">Date of Grant</label>
                            <input type="date" name="date_of_grant" id="date_of_grant"
                                class="w-full px-4 py-3 rounded-xl border border-slate-200 bg-slate-50 focus:border-blue-500 focus:ring-2 focus:ring-blue-100 transition focus:bg-white text-sm font-medium">
                        </div>
                        <div id="special-mortgage-terms-wrapper" class="md:col-span-2 hidden">
                            <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">Special Mortgage Terms</label>
                            <textarea name="special_mortgage_terms" id="special_mortgage_terms" rows="2"
                                class="w-full px-4 py-3 rounded-xl border border-slate-200 bg-slate-50 focus:border-blue-500 focus:ring-2 focus:ring-blue-100 transition focus:bg-white text-sm font-medium"
                                placeholder="Special mortgage terms (if any)"></textarea>
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">Applicant Address <span class="text-red-500">*</span></label>
                            <div class="space-y-3 p-4 bg-slate-50/50 rounded-2xl border border-slate-100">
                                <div class="grid grid-cols-2 gap-3">
                                    <div>
                                        <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">House No <span class="text-red-500">*</span></label>
                                        <input type="text" id="applicant_house_no" name="applicant_house_no" required
                                            class="address-component-applicant w-full px-3 py-2 rounded-xl border border-slate-200 bg-white text-sm font-medium focus:border-blue-500 transition"
                                            placeholder="e.g. 12">
                                    </div>
                                    <div>
                                        <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">Street</label>
                                        <select id="applicant_street" data-address-type="applicant" data-manual-input="#applicant_street_other"
                                            class="address-component-applicant street-dropdown w-full px-3 py-2 rounded-xl border border-slate-200 bg-white text-sm font-medium focus:border-blue-500 transition" data-fill-options="streets">
                                            <option value="">Select Street</option>
{{-- options: street list, filled once by the script at the end of this partial --}}
                                        </select>
                                        <input type="text" id="applicant_street_other" data-address-type="applicant"
                                            class="manual-street-input hidden address-component-applicant w-full mt-2 px-3 py-2 rounded-xl border border-slate-200 bg-white text-sm font-medium focus:border-blue-500 transition"
                                            placeholder="Specify street...">
                                    </div>
                                </div>
                                <div class="grid grid-cols-2 gap-3">
                                    <div>
                                        <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">District</label>
                                        <select id="applicant_district"
                                            class="address-component-applicant w-full px-3 py-2 rounded-xl border border-slate-200 bg-white text-sm font-medium focus:border-blue-500 transition" data-fill-options="districts">
                                            <option value="">Select District</option>
{{-- options: district list, filled once by the script at the end of this partial --}}
                                            <option value="Other">Other</option>
                                        </select>
                                        <input type="text" id="applicant_district_other"
                                            class="hidden address-component-applicant w-full mt-2 px-3 py-2 rounded-xl border border-slate-200 bg-white text-sm font-medium focus:border-blue-500 transition"
                                            placeholder="Specify district...">
                                    </div>
                                    <div>
                                        <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">LGA <span class="text-red-500">*</span></label>
                                        <select id="applicant_lga" name="applicant_lga" required
                                            class="address-component-applicant w-full px-3 py-2 rounded-xl border border-slate-200 bg-white text-sm font-medium focus:border-blue-500 transition">
                                            <option value="">Select LGA</option>
                                            @foreach($lgas as $lga)
                                                <option value="{{ $lga->LGAName }}">{{ $lga->LGAName }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                                <div>
                                    <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">State <span class="text-red-500">*</span></label>
                                    <select id="applicant_state" name="applicant_state" required
                                        class="address-component-applicant w-full px-3 py-2 rounded-xl border border-slate-200 bg-white text-sm font-medium focus:border-blue-500 transition">
                                        <option value="">Select State</option>
                                        @foreach($states as $state)
                                            <option value="{{ $state->StateName }}" {{ $state->StateName == 'Kano' ? 'selected' : '' }}>{{ $state->StateName }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <input type="hidden" name="applicant_address" id="applicant_address_hidden">
                            <div class="mt-3 p-3 bg-white rounded-xl border border-dashed border-slate-200 text-xs text-slate-500">
                                <span class="font-bold text-slate-400 uppercase text-[10px] block mb-1">Full Address Preview:</span>
                                <span id="applicant_address_preview" class="italic">No address built yet...</span>
                            </div>
                        </div>
                        <div class="md:col-span-2">
                            <div class="flex items-center justify-between mb-2">
                                <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider">Correspondence Address</label>
                                {{-- The two are usually the same address; ticking this copies the
                                     applicant's and keeps it in step while it is edited. --}}
                                <label class="flex items-center gap-2 cursor-pointer select-none">
                                    <input type="checkbox" id="correspondence_same_as_applicant"
                                        class="h-3.5 w-3.5 rounded border-slate-300 text-blue-600 focus:ring-blue-400">
                                    <span class="text-[11px] font-bold text-slate-500 uppercase tracking-wider">Same as Applicant Address</span>
                                </label>
                            </div>
                            <div class="space-y-3 p-4 bg-slate-50/50 rounded-2xl border border-slate-100">
                                <div class="grid grid-cols-2 gap-3">
                                    <div>
                                        <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">House No</label>
                                        <input type="text" id="correspondence_house_no"
                                            class="address-component-correspondence w-full px-3 py-2 rounded-xl border border-slate-200 bg-white text-sm font-medium focus:border-blue-500 transition"
                                            placeholder="e.g. 12">
                                    </div>
                                    <div>
                                        <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">Street</label>
                                        <select id="correspondence_street" data-address-type="correspondence" data-manual-input="#correspondence_street_other"
                                            class="address-component-correspondence street-dropdown w-full px-3 py-2 rounded-xl border border-slate-200 bg-white text-sm font-medium focus:border-blue-500 transition" data-fill-options="streets">
                                            <option value="">Select Street</option>
{{-- options: street list, filled once by the script at the end of this partial --}}
                                        </select>
                                        <input type="text" id="correspondence_street_other" data-address-type="correspondence"
                                            class="manual-street-input hidden address-component-correspondence w-full mt-2 px-3 py-2 rounded-xl border border-slate-200 bg-white text-sm font-medium focus:border-blue-500 transition"
                                            placeholder="Specify street...">
                                    </div>
                                </div>
                                <div class="grid grid-cols-2 gap-3">
                                    <div>
                                        <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">District</label>
                                        <select id="correspondence_district"
                                            class="address-component-correspondence w-full px-3 py-2 rounded-xl border border-slate-200 bg-white text-sm font-medium focus:border-blue-500 transition" data-fill-options="districts">
                                            <option value="">Select District</option>
{{-- options: district list, filled once by the script at the end of this partial --}}
                                            <option value="Other">Other</option>
                                        </select>
                                        <input type="text" id="correspondence_district_other"
                                            class="hidden address-component-correspondence w-full mt-2 px-3 py-2 rounded-xl border border-slate-200 bg-white text-sm font-medium focus:border-blue-500 transition"
                                            placeholder="Specify district...">
                                    </div>
                                    <div>
                                        <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">LGA</label>
                                        <select id="correspondence_lga"
                                            class="address-component-correspondence w-full px-3 py-2 rounded-xl border border-slate-200 bg-white text-sm font-medium focus:border-blue-500 transition">
                                            <option value="">Select LGA</option>
                                            @foreach($lgas as $lga)
                                                <option value="{{ $lga->LGAName }}">{{ $lga->LGAName }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                                <div>
                                    <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">State</label>
                                    <select id="correspondence_state"
                                        class="address-component-correspondence w-full px-3 py-2 rounded-xl border border-slate-200 bg-white text-sm font-medium focus:border-blue-500 transition">
                                        <option value="">Select State</option>
                                        @foreach($states as $state)
                                            <option value="{{ $state->StateName }}" {{ $state->StateName == 'Kano' ? 'selected' : '' }}>{{ $state->StateName }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <input type="hidden" name="correspondence_address" id="correspondence_address_hidden">
                            <div class="mt-3 p-3 bg-white rounded-xl border border-dashed border-slate-200 text-xs text-slate-500">
                                <span class="font-bold text-slate-400 uppercase text-[10px] block mb-1">Full Address Preview:</span>
                                <span id="correspondence_address_preview" class="italic">No address built yet...</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Property Description Section -->
                <div class="pt-8 border-t-2 border-indigo-100">
                    <div class="flex items-center gap-3 mb-6">
                        <div class="w-10 h-10 rounded-2xl bg-indigo-600 flex items-center justify-center text-white shadow-lg shadow-indigo-200">
                            <i data-lucide="map-pin" class="h-5 w-5"></i>
                        </div>
                        <div>
                            <h4 class="text-sm font-black text-slate-800 uppercase tracking-widest">Property
                                Description<span id="main-property-index"></span></h4>
                            <p class="text-[10px] text-slate-500 font-bold uppercase">Location of the subject plot</p>
                        </div>
                    </div>

                    <div class="multi-property-notice hidden mb-6 flex items-start gap-2.5 p-3 rounded-xl bg-amber-50 border border-amber-200">
                        <i data-lucide="info" class="h-4 w-4 text-amber-600 mt-0.5 shrink-0"></i>
                        <p class="text-xs font-semibold text-amber-800 leading-relaxed">
                            This consent involves <span class="multi-property-count">2</span> properties.
                            Each file number carries its own property description.
                        </p>
                    </div>

                    <div>
                        <div class="space-y-3 p-4 bg-slate-50/50 rounded-2xl border border-slate-100">
                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">House No</label>
                                    <input type="text" id="property_house_no" class="address-component-property w-full px-3 py-2 rounded-xl border border-slate-200 bg-white text-sm font-medium focus:border-blue-500 transition" placeholder="e.g. 12">
                                </div>
                                <div>
                                    <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">Plot No</label>
                                    <input type="text" id="property_plot_no" class="address-component-property w-full px-3 py-2 rounded-xl border border-slate-200 bg-white text-sm font-medium focus:border-blue-500 transition" placeholder="e.g. 45">
                                </div>
                            </div>
                            {{-- Street gets the full width now that House No and
                                 Plot No take the row above: street names here are
                                 long enough to truncate in a half-width control. --}}
                            <div class="grid grid-cols-1 gap-3">
                                <div>
                                    <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">Street</label>
                                    <select id="property_street" data-address-type="property" data-manual-input="#property_street_other"
                                        class="address-component-property street-dropdown w-full px-3 py-2 rounded-xl border border-slate-200 bg-white text-sm font-medium focus:border-blue-500 transition" data-fill-options="streets">
                                        <option value="">Select Street</option>
{{-- options: street list, filled once by the script at the end of this partial --}}
                                    </select>
                                    <input type="text" id="property_street_other" data-address-type="property"
                                        class="manual-street-input hidden address-component-property w-full mt-2 px-3 py-2 rounded-xl border border-slate-200 bg-white text-sm font-medium focus:border-blue-500 transition"
                                        placeholder="Specify street...">
                                </div>
                            </div>
                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">District</label>
                                    <select id="property_district" class="address-component-property w-full px-3 py-2 rounded-xl border border-slate-200 bg-white text-sm font-medium focus:border-blue-500 transition" data-fill-options="districts">
                                        <option value="">Select District</option>
{{-- options: district list, filled once by the script at the end of this partial --}}
                                        <option value="Other">Other</option>
                                    </select>
                                    <input type="text" id="property_district_other" class="hidden address-component-property w-full mt-2 px-3 py-2 rounded-xl border border-slate-200 bg-white text-sm font-medium focus:border-blue-500 transition" placeholder="Specify district...">
                                </div>
                                <div>
                                    <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">LGA <span class="text-red-500">*</span></label>
                                    <select id="property_lga" required class="address-component-property w-full px-3 py-2 rounded-xl border border-slate-200 bg-white text-sm font-medium focus:border-blue-500 transition">
                                        <option value="">Select LGA</option>
                                        @foreach($lgas as $lga)
                                            <option value="{{ $lga->LGAName }}">{{ $lga->LGAName }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div>
                                <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">State <span class="text-red-500">*</span></label>
                                <select id="property_state" required class="address-component-property w-full px-3 py-2 rounded-xl border border-slate-200 bg-white text-sm font-medium focus:border-blue-500 transition">
                                    <option value="">Select State</option>
                                    @foreach($states as $state)
                                        <option value="{{ $state->StateName }}" {{ $state->StateName == 'Kano' ? 'selected' : '' }}>{{ $state->StateName }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <input type="hidden" name="property_description" id="property_address_hidden">
                        <div class="mt-3 p-3 bg-white rounded-xl border border-dashed border-slate-200 text-xs text-slate-500">
                            <span class="font-bold text-slate-400 uppercase text-[10px] block mb-1">Full Description Preview:</span>
                            <span id="property_address_preview" class="italic">No description built yet...</span>
                        </div>
                    </div>

                    <div id="additional-properties-container" class="space-y-6">
                        <!-- One block per additional file number, cloned here -->
                    </div>
                </div>
                </div>

                <div class="wizard-step hidden" data-step="3">
                <!-- Dynamic Detail Sections -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-10 pt-8 border-t-2 border-indigo-100 relative">
                    <!-- Vertical Divider for Desktop -->
                    <div class="hidden md:block absolute left-1/2 top-8 bottom-0 w-px bg-indigo-200 -ml-px">
                    </div>

                    <!-- Applicant -->
                    <div class="space-y-4 relative">
                        <div class="flex items-center gap-3 mb-6">
                            <div
                                class="w-10 h-10 rounded-2xl bg-blue-600 flex items-center justify-center text-white shadow-lg shadow-blue-200">
                                <i data-lucide="user" class="h-5 w-5"></i>
                            </div>
                            <div>
                                <h4 class="text-sm font-black text-slate-800 uppercase tracking-widest">Applicant
                                    Details</h4>
                                <p class="text-[10px] text-slate-500 font-bold uppercase">Person making the request</p>
                            </div>
                        </div>
                        <div>
                            <label id="applicant-name-label"
                                class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">Applicant
                                Full Name <span class="text-red-500">*</span></label>
                            <input type="text" name="applicant_name" required
                                class="w-full px-4 py-3 rounded-xl border border-slate-200 bg-slate-50 focus:border-blue-500 focus:ring-2 focus:ring-blue-100 transition focus:bg-white text-sm font-medium"
                                placeholder="e.g. Musa Ali">
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">Phone Number</label>
                            <input type="tel" name="applicant_phone" inputmode="tel" maxlength="30"
                                class="w-full px-4 py-3 rounded-xl border border-slate-200 bg-slate-50 focus:border-blue-500 focus:ring-2 focus:ring-blue-100 transition focus:bg-white text-sm font-medium"
                                placeholder="e.g. 08031234567">
                            <p class="mt-1 text-[10px] text-slate-400 font-semibold">Filled from the file's valuation report when a file number is selected. Carried on to Instrument Capture.</p>
                        </div>

                        <div id="additional-applicants-container" class="space-y-6">
                            <!-- Additional applicants cloned here -->
                        </div>

                        <!-- Add Applicant Action Container -->
                        <div id="add-applicant-action-container" class="pt-2">
                            <button type="button" id="add-applicant-btn" class="w-full py-3 rounded-xl border-2 border-dashed border-blue-200 bg-blue-50/30 text-blue-600 hover:bg-blue-50 hover:border-blue-300 transition flex items-center justify-center gap-2 text-xs font-bold uppercase">
                                <i data-lucide="plus-circle" class="h-4 w-4"></i>
                                <span>Add Another Applicant</span>
                            </button>
                        </div>
                    </div>

                    <!-- Other Party -->
                    <div class="space-y-4 relative">
                        <div class="flex items-center gap-3 mb-6">
                            <div
                                class="w-10 h-10 rounded-2xl bg-fuchsia-600 flex items-center justify-center text-white shadow-lg shadow-fuchsia-200">
                                <i data-lucide="users" class="h-5 w-5"></i>
                            </div>
                            <div class="flex-1">
                                <h4 class="text-sm font-black text-slate-800 uppercase tracking-widest"
                                    id="party-role-label">Assignee Details</h4>
                                <p class="text-[10px] text-slate-500 font-bold uppercase">Target recipient party</p>
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2"
                                id="party-name-label">Assignee Full Name <span class="text-red-500">*</span></label>
                            <input type="text" name="party_name" required
                                class="w-full px-4 py-3 rounded-xl border border-slate-200 bg-slate-50 focus:border-blue-500 focus:ring-2 focus:ring-blue-100 transition focus:bg-white text-sm font-medium"
                                placeholder="Full name of the party">
                            {{-- Live duplicate flag: the assignee may not be the applicant, nor the
                                 party who already holds the title. Populated by consent_applications.js. --}}
                            <p id="party-name-duplicate-warning"
                                class="hidden mt-2 flex items-start gap-1.5 text-xs font-semibold text-red-600">
                                <i data-lucide="alert-triangle" class="h-4 w-4 shrink-0 mt-px"></i>
                                <span id="party-name-duplicate-warning-text"></span>
                            </p>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">Party
                                Address</label>

                            <div class="space-y-3 p-4 bg-slate-50/50 rounded-2xl border border-slate-100">
                                <div class="grid grid-cols-2 gap-3">
                                    <div>
                                        <label
                                            class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">House
                                            No</label>
                                        <input type="text" id="party_house_no"
                                            class="address-component-party w-full px-3 py-2 rounded-xl border border-slate-200 bg-white text-sm font-medium focus:border-blue-500 transition"
                                            placeholder="e.g. 12">
                                    </div>
                                    <div>
                                        <label class="block text-[10px] font-bold text-slate-500 uppercase
                                            tracking-wider mb-1">Street</label>
                                        <select id="party_street" data-address-type="party" data-manual-input="#party_street_other"
                                            class="address-component-party street-dropdown w-full px-3 py-2 rounded-xl border border-slate-200 bg-white text-sm font-medium focus:border-blue-500 transition" data-fill-options="streets">
                                            <option value="">Select Street</option>
{{-- options: street list, filled once by the script at the end of this partial --}}
                                        </select>
                                        <input type="text" id="party_street_other" data-address-type="party"
                                            class="manual-street-input hidden address-component-party w-full mt-2 px-3 py-2 rounded-xl border border-slate-200 bg-white text-sm font-medium focus:border-blue-500 transition"
                                            placeholder="Specify street...">
                                    </div>
                                </div>
                                <div class="grid grid-cols-2 gap-3">
                                    <div>
                                        <label class="block text-[10px] font-bold text-slate-500 uppercase
                                            tracking-wider mb-1">District</label>
                                        <select id="party_district"
                                            class="address-component-party w-full px-3 py-2 rounded-xl border border-slate-200 bg-white text-sm font-medium focus:border-blue-500 transition" data-fill-options="districts">
                                            <option value="">Select District</option>
{{-- options: district list, filled once by the script at the end of this partial --}}
                                            <option value="Other">Other</option>
                                        </select>
                                        <input type="text" id="party_district_other"
                                            class="hidden address-component-party w-full mt-2 px-3 py-2 rounded-xl border border-slate-200 bg-white text-sm font-medium focus:border-blue-500 transition"
                                            placeholder="Specify district...">
                                    </div>
                                    <div>
                                        <label
                                            class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">LGA
                                            <span class="text-red-500">*</span></label>
                                        <select id="party_lga" required
                                            class="address-component-party w-full px-3 py-2 rounded-xl border
                                        border-slate-200 bg-white text-sm font-medium focus:border-blue-500 transition">
                                            <option value="">Select LGA</option>
                                            @foreach($lgas as $lga)
                                                <option value="{{ $lga->LGAName }}">{{ $lga->LGAName }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                                <div>
                                    <label
                                        class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">State
                                        <span class="text-red-500">*</span></label>
                                    <select id="party_state" required
                                        class="address-component-party w-full px-3 py-2 rounded-xl border border-slate-200 bg-white text-sm font-medium focus:border-blue-500 transition">
                                        <option value="">Select State</option>
                                        @foreach($states as $state)
                                            <option value="{{ $state->StateName }}" {{ $state->StateName == 'Kano' ? 'selected' : '' }}>{{ $state->StateName }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <input type="hidden" name="party_address" id="party_address_hidden">
                            <div
                                class="mt-3 p-3 bg-white rounded-xl border border-dashed border-slate-200 text-xs text-slate-500">
                                <span class="font-bold text-slate-400 uppercase text-[10px] block mb-1">Full Address
                                    Preview:</span>
                                <span id="party_address_preview" class="italic">No address built yet...</span>
                            </div>
                        </div>
                        
                        <div id="additional-parties-container" class="space-y-6">
                            <!-- Additional parties cloned here -->
                        </div>

                        <!-- Add Party Action Container -->
                        <div id="add-party-action-container" class="hidden pt-2">
                            <button type="button" id="add-party-btn" class="w-full py-3 rounded-xl border-2 border-dashed border-emerald-200 bg-emerald-50/30 text-emerald-600 hover:bg-emerald-50 hover:border-emerald-300 transition flex items-center justify-center gap-2 text-xs font-bold uppercase">
                                <i data-lucide="plus-circle" class="h-4 w-4"></i>
                                <span>Add Another Mortgagee</span>
                            </button>
                        </div>
                    </div>
                </div>
                </div>

                <div class="wizard-step hidden" data-step="4">
                <!-- Financials and Date -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-8 pt-4 border-t border-slate-50">
                    <div id="financial-section">
                        <div class="flex items-center gap-3 mb-4">
                            <div
                                class="w-8 h-8 rounded-full bg-emerald-50 flex items-center justify-center text-emerald-600">
                                <i data-lucide="banknote" class="h-4 w-4"></i>
                            </div>
                            <h4 class="text-sm font-bold text-slate-700 uppercase tracking-wider" id="financial-title">
                                Consideration</h4>
                        </div>
                        <div class="space-y-4">
                            <div>
                                <div class="flex items-center justify-between mb-2">
                                    <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider">Amount
                                        in Figures (₦) <span class="text-red-500">*</span></label>
                                    {{-- Locked to the valuation by default; the pencil unlocks it.
                                         Editing it rebases Consideration and Stamp Duty. --}}
                                    <button type="button" id="consideration-edit-btn"
                                        class="p-1.5 rounded-lg text-slate-400 hover:text-blue-600 hover:bg-blue-50 transition"
                                        title="Override the assessed amount">
                                        <i data-lucide="pencil" class="h-3.5 w-3.5"></i>
                                    </button>
                                </div>
                                <input type="text" name="consideration" required id="consideration_figures" readonly
                                    class="w-full px-4 py-3 rounded-xl border border-slate-200 bg-slate-100 focus:border-blue-500 focus:ring-2 focus:ring-blue-100 transition text-sm font-medium cursor-not-allowed"
                                    placeholder="e.g. 5,000,000">
                                {{-- Only set to 1 once the operator actually edits the amount, so
                                     the server keeps billing off the valuation otherwise. --}}
                                <input type="hidden" name="bill_basis_overridden" id="bill_basis_overridden" value="">
                                <p class="text-[11px] text-amber-600 mt-1 hidden" id="consideration-override-note">
                                    Overridden — Consideration and Stamp Duty are calculated from this amount.
                                </p>
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2"
                                    id="financial-label">Amount in Words</label>
                                <input type="text" name="consideration_words" required id="consideration_words" readonly
                                    class="w-full px-4 py-3 rounded-xl border border-slate-200 bg-slate-100 focus:border-blue-500 focus:ring-2 focus:ring-blue-100 transition text-sm font-medium cursor-not-allowed"
                                    placeholder="e.g. Five Million Naira Only">
                            </div>
                        </div>
                    </div>

                    <div class="space-y-4">
                        <div>
                            <div class="flex items-center gap-3 mb-4">
                                <div
                                    class="w-8 h-8 rounded-full bg-orange-50 flex items-center justify-center text-orange-600">
                                    <i data-lucide="calendar" class="h-4 w-4"></i>
                                </div>
                                <h4 class="text-sm font-bold text-slate-700 uppercase tracking-wider">Date</h4>
                            </div>
                            <div class="variant-letter">
                                <label
                                    class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">Letter
                                    Date <span class="text-red-500">*</span></label>
                                <input type="date" name="application_date" id="letter_date" value="{{ date('Y-m-d') }}"
                                    class="w-full px-4 py-3 rounded-xl border border-slate-200 bg-slate-50 focus:border-blue-500 focus:ring-2 focus:ring-blue-100 transition focus:bg-white text-sm font-medium">
                            </div>
                            <div class="variant-application hidden">
                                <label
                                    class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">Application
                                    Date <span class="text-red-500">*</span></label>
                                {{-- No default value: the applicant's own application date must be entered by the user. --}}
                                <input type="date" name="application_submitted_date" id="application_submitted_date"
                                    class="w-full px-4 py-3 rounded-xl border border-slate-200 bg-slate-50 focus:border-blue-500 focus:ring-2 focus:ring-blue-100 transition focus:bg-white text-sm font-medium">
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">Application
                                Tracking No</label>
                            <div id="application-tracking-no-display"
                                class="w-full px-4 py-3 rounded-xl border border-emerald-100 bg-emerald-50 text-emerald-700 text-sm font-bold font-mono flex items-center gap-2">
                                <i data-lucide="hash" class="h-4 w-4 text-emerald-500"></i>
                                <span id="application_tracking_no_value">Assigned on save</span>
                            </div>
                        </div>

                        <div class="grid grid-cols-3 gap-4">
                            <div>
                                <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">Time
                                    Created</label>
                                <div
                                    class="w-full px-4 py-3 rounded-xl border border-slate-200 bg-slate-100 text-slate-500 text-[10px] font-bold flex items-center gap-2">
                                    <i data-lucide="clock" class="h-3 w-3 text-emerald-500"></i>
                                    {{ date('h:i A') }}
                                </div>
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">Date
                                    Created</label>
                                <div
                                    class="w-full px-4 py-3 rounded-xl border border-slate-200 bg-slate-100 text-slate-500 text-[10px] font-bold flex items-center gap-2">
                                    <i data-lucide="calendar" class="h-3 w-3 text-emerald-500"></i>
                                    {{ date('d-m-Y') }}
                                </div>
                            </div>
                            <div>
                                <label
                                    class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">Created
                                    By</label>
                                <div
                                    class="w-full px-4 py-3 rounded-xl border border-slate-200 bg-slate-100 text-slate-500 text-[10px] font-bold flex items-center gap-2">
                                    <i data-lucide="user-check" class="h-3 w-3 text-emerald-500"></i>
                                    {{ auth()->user()->first_name }} {{ auth()->user()->last_name }}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Consent bill. Backfilled from the file's valuation as soon as a
                     file is chosen; every figure is read-only because the server
                     recalculates on save and discards anything posted here. --}}
                <div class="pt-6 mt-6 border-t border-slate-100" id="consent-bill-section"
                    data-stamp-duty-rate="{{ config('consent_bill.rates.stamp_duty') }}"
                    data-registration-rate="{{ config('consent_bill.rates.registration') }}"
                    data-processing-fee="{{ config('consent_bill.fixed_fees.processing') }}">
                    <div class="flex items-center gap-3 mb-4">
                        <div class="w-8 h-8 rounded-full bg-blue-50 flex items-center justify-center text-blue-600">
                            <i data-lucide="receipt" class="h-4 w-4"></i>
                        </div>
                        <h4 class="text-sm font-bold text-slate-700 uppercase tracking-wider">Consent Bill</h4>
                        <span id="bill-status" class="text-xs font-medium text-slate-400">Select a file to load the valuation</span>
                    </div>

                    {{-- Shown when the selected file has no usable valuation. The rows
                         below stay at zero in that case, and a zero must never be
                         mistaken for a real assessment. --}}
                    <div id="bill-no-valuation"
                        class="hidden mb-4 px-4 py-3 rounded-xl border border-amber-200 bg-amber-50 flex items-start gap-3">
                        <i data-lucide="alert-triangle" class="h-4 w-4 text-amber-600 mt-0.5 shrink-0"></i>
                        <div>
                            <div class="text-sm font-bold text-amber-800">No valuation found for this file</div>
                            <div class="text-xs text-amber-700 mt-0.5" id="bill-no-valuation-message"></div>
                            <div class="text-xs text-amber-700 mt-1">
                                Generate a valuation report for this file, or override each amount manually below.
                            </div>
                        </div>
                    </div>

                    {{-- Hidden, not removed: the valuation now shows in Consideration
                         above, so repeating it here is noise. The inputs stay in the
                         DOM because loadConsentBill() writes to them and bails early
                         if they are absent, which would stop the fee rows loading. --}}
                    <div class="mb-4 hidden">
                        <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">
                            Valuation Amount (₦) — Section E
                        </label>
                        <input type="text" id="bill_valuation_amount" readonly
                            class="w-full px-4 py-3 rounded-xl border border-blue-100 bg-blue-50 text-blue-800 text-sm font-bold cursor-not-allowed"
                            placeholder="Awaiting valuation">
                        <p class="text-[11px] text-slate-400 mt-1" id="bill_valuation_source"></p>
                    </div>

                    @php
                        $fmtRate = fn($r) => \App\Services\ConsentBillCalculator::formatRate($r);
                        // The Ministry's lines. Stamp duty is rendered separately below
                        // the total: under a transaction type it is paid to KIRS / FIRS,
                        // not to the Ministry, and is not part of the bill.
                        // A percentage line is zero until a valuation is linked; a fixed
                        // fee never depends on one, so it shows its configured amount
                        // from the outset rather than a misleading zero.
                        $ministryRows = [
                            ['id' => 'bill_registration_fee', 'key' => 'registration_fee', 'default' => 0,
                                'label' => 'Registration Fee (' . $fmtRate(config('consent_bill.rates.registration')) . ')',
                                'name' => 'Registration Fee', 'rate_key' => 'registration',
                                'rate' => rtrim($fmtRate(config('consent_bill.rates.registration')), '%'),
                                'note' => 'Percentage of assessed amount'],
                            ['id' => 'bill_processing_fee', 'key' => 'processing_fee', 'label' => 'Processing Fee',
                                'note' => 'Fixed fee', 'default' => config('consent_bill.fixed_fees.processing')],
                        ];
                        $stampRow = ['id' => 'bill_stamp_duty', 'key' => 'stamp_duty_amount', 'default' => 0,
                            'label' => 'Stamp Duty (' . $fmtRate(config('consent_bill.rates.stamp_duty')) . ')',
                            'name' => 'Stamp Duty', 'rate_key' => 'stamp_duty',
                            'rate' => rtrim($fmtRate(config('consent_bill.rates.stamp_duty')), '%'),
                            'note' => 'Percentage of assessed amount'];
                    @endphp
                    <div class="rounded-xl border border-slate-200 overflow-hidden">
                        @foreach ($ministryRows as $row)
                            <div class="px-4 py-3 border-b border-slate-100 bg-white bill-row"
                                data-fee="{{ $row['key'] }}" data-default="{{ number_format((float) $row['default'], 2, '.', '') }}">
                                <div class="flex items-center justify-between gap-3">
                                    <div>
                                        @if (!empty($row['rate_key']))
                                            <div class="text-sm font-medium text-slate-700 flex items-center gap-1">
                                                <span>{{ $row['name'] }} (</span>
                                                <input type="text" inputmode="decimal" data-rate-input="{{ $row['rate_key'] }}"
                                                    id="{{ $row['id'] }}_rate" value="{{ $row['rate'] }}"
                                                    class="w-16 px-1.5 py-0.5 rounded-md border border-slate-200 bg-white text-xs font-bold font-mono text-right focus:border-blue-500 focus:ring-1 focus:ring-blue-100"
                                                    title="Edit the percentage if this consent is charged at a different rate">
                                                <span>%)</span>
                                            </div>
                                        @else
                                            <div class="text-sm font-medium text-slate-700" id="{{ $row['id'] }}_label">{{ $row['label'] }}</div>
                                        @endif
                                        <div class="text-[11px] text-slate-400 bill-row-note">{{ $row['note'] }}</div>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <div class="text-sm font-bold text-slate-700 font-mono" id="{{ $row['id'] }}">₦{{ number_format((float) $row['default'], 2) }}</div>
                                        <button type="button"
                                            class="bill-edit-btn p-1.5 rounded-lg text-slate-400 hover:text-blue-600 hover:bg-blue-50 transition"
                                            title="Override this amount">
                                            <i data-lucide="pencil" class="h-3.5 w-3.5"></i>
                                        </button>
                                    </div>
                                </div>

                                {{-- Override editor. Hidden until the pencil is used, and the
                                     inputs carry no name until then, so an untouched row posts
                                     nothing and stays on the calculated amount. --}}
                                <div class="bill-override hidden mt-3 pt-3 border-t border-dashed border-slate-200">
                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                        <div>
                                            <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1">
                                                Manual Amount (₦)
                                            </label>
                                            <input type="text" inputmode="decimal" data-override-amount="{{ $row['key'] }}"
                                                class="w-full px-3 py-2 rounded-lg border border-amber-200 bg-amber-50 text-sm font-medium font-mono"
                                                placeholder="0.00">
                                        </div>
                                        <div>
                                            <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1">
                                                Reason <span class="text-red-500">*</span>
                                            </label>
                                            <input type="text" data-override-reason="{{ $row['key'] }}"
                                                class="w-full px-3 py-2 rounded-lg border border-slate-200 bg-white text-sm"
                                                placeholder="Why this amount differs">
                                        </div>
                                    </div>
                                    <button type="button" class="bill-revert-btn mt-2 text-[11px] font-bold text-slate-500 hover:text-red-600">
                                        Revert to calculated amount
                                    </button>
                                </div>
                            </div>
                        @endforeach

                        <div class="flex items-center justify-between px-4 py-3 bg-slate-50">
                            <div class="text-sm font-bold text-slate-800 uppercase tracking-wider">Total</div>
                            <div class="text-base font-bold text-emerald-700 font-mono" id="bill_total">₦{{ number_format((float) config('consent_bill.fixed_fees.processing'), 2) }}</div>
                        </div>
                    </div>

                    {{-- Stamp duty: computed on the same basis but payable to the revenue
                         authority the transaction type names, so it sits outside the
                         Ministry total. The payee line is filled by applyTransactionType(). --}}
                    <div class="mt-3 rounded-xl border border-amber-200 bg-amber-50/60 overflow-hidden" id="stamp-duty-box">
                            <div class="px-4 py-3 bill-row"
                                data-fee="{{ $stampRow['key'] }}" data-default="{{ number_format((float) $stampRow['default'], 2, '.', '') }}">
                                <div class="flex items-center justify-between gap-3">
                                    <div>
                                        @if (!empty($stampRow['rate_key']))
                                            <div class="text-sm font-medium text-slate-700 flex items-center gap-1">
                                                <span>{{ $stampRow['name'] }} (</span>
                                                <input type="text" inputmode="decimal" data-rate-input="{{ $stampRow['rate_key'] }}"
                                                    id="{{ $stampRow['id'] }}_rate" value="{{ $stampRow['rate'] }}"
                                                    class="w-16 px-1.5 py-0.5 rounded-md border border-slate-200 bg-white text-xs font-bold font-mono text-right focus:border-blue-500 focus:ring-1 focus:ring-blue-100"
                                                    title="Edit the percentage if this consent is charged at a different rate">
                                                <span>%)</span>
                                            </div>
                                        @else
                                            <div class="text-sm font-medium text-slate-700" id="{{ $stampRow['id'] }}_label">{{ $stampRow['label'] }}</div>
                                        @endif
                                        <div class="text-[11px] text-slate-400 bill-row-note">{{ $stampRow['note'] }}</div>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <div class="text-sm font-bold text-slate-700 font-mono" id="{{ $stampRow['id'] }}">₦{{ number_format((float) $stampRow['default'], 2) }}</div>
                                        <button type="button"
                                            class="bill-edit-btn p-1.5 rounded-lg text-slate-400 hover:text-blue-600 hover:bg-blue-50 transition"
                                            title="Override this amount">
                                            <i data-lucide="pencil" class="h-3.5 w-3.5"></i>
                                        </button>
                                    </div>
                                </div>

                                {{-- Override editor. Hidden until the pencil is used, and the
                                     inputs carry no name until then, so an untouched row posts
                                     nothing and stays on the calculated amount. --}}
                                <div class="bill-override hidden mt-3 pt-3 border-t border-dashed border-slate-200">
                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                        <div>
                                            <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1">
                                                Manual Amount (₦)
                                            </label>
                                            <input type="text" inputmode="decimal" data-override-amount="{{ $stampRow['key'] }}"
                                                class="w-full px-3 py-2 rounded-lg border border-amber-200 bg-amber-50 text-sm font-medium font-mono"
                                                placeholder="0.00">
                                        </div>
                                        <div>
                                            <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1">
                                                Reason <span class="text-red-500">*</span>
                                            </label>
                                            <input type="text" data-override-reason="{{ $stampRow['key'] }}"
                                                class="w-full px-3 py-2 rounded-lg border border-slate-200 bg-white text-sm"
                                                placeholder="Why this amount differs">
                                        </div>
                                    </div>
                                    <button type="button" class="bill-revert-btn mt-2 text-[11px] font-bold text-slate-500 hover:text-red-600">
                                        Revert to calculated amount
                                    </button>
                                </div>
                            </div>
                        <div class="px-4 py-2 text-[11px] font-semibold text-amber-800 border-t border-amber-100" id="stamp-duty-payee-note">
                            Not included in the total. Select a transfer type to see who it is paid to.
                        </div>
                    </div>
                </div>
                </div>
            </div>
        </form>

        <!-- Footer: Fixed -->
        <div
            class="px-8 py-6 border-t border-slate-100 flex items-center justify-end gap-3 bg-white shrink-0 shadow-inner">
            <button type="button" id="wizard-prev-btn"
                class="hidden px-6 py-3 rounded-xl border border-slate-200 text-slate-600 font-bold hover:bg-slate-50 transition">
                Previous
            </button>
            <button type="button"
                class="close-modal px-6 py-3 rounded-xl border border-slate-200 text-slate-600 font-bold hover:bg-slate-50 transition">
                Cancel
            </button>
            <button type="button" id="wizard-next-btn"
                class="px-6 py-3 rounded-xl border border-blue-200 bg-blue-50 text-blue-700 font-bold hover:bg-blue-100 transition">
                Next
            </button>
            <button type="submit" form="consent-form" id="submit-btn"
                class="hidden px-8 py-3 rounded-xl bg-blue-600 text-white font-bold shadow-lg shadow-blue-100 hover:bg-blue-700 transition flex items-center gap-2">
                <span>Generate Consent</span>
                <i data-lucide="arrow-right" class="h-4 w-4"></i>
            </button>
        </div>
    </div>
</div>

<!-- Template for Additional Right of Occupancy rows (Hidden) -->
<template id="additional-rofo-template">
    <div class="additional-rofo-block mt-4 pt-4 border-t border-slate-100">
        <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">
            Right of Occupancy Number <span class="rofo-block-index"></span>
        </label>
        {{-- readonly (not disabled) so the variant/interactive toggles leave it alone;
             it carries no name, so the value is submitted via additional_file_number[] --}}
        <input type="text" readonly
            class="additional-rofo-display w-full px-4 py-3 rounded-xl border border-slate-200 bg-slate-100 text-slate-500 cursor-not-allowed text-sm font-medium"
            placeholder="Auto-filled from selected file number">
        <input type="hidden" name="additional_property_applicant[]" class="additional-rofo-applicant">
        <div class="additional-rofo-applicant-wrapper mt-2 hidden items-center gap-2">
            <span class="text-[10px] font-bold text-slate-500 uppercase tracking-wider">Applicant:</span>
            <span class="additional-rofo-applicant-name inline-flex items-center px-3 py-1 rounded-full bg-blue-50 text-blue-700 border border-blue-100 text-xs font-semibold"></span>
        </div>
    </div>
</template>

<!-- Template for Additional File Numbers (Hidden) -->
<template id="additional-fileno-template">
    <div class="additional-fileno-block flex items-center gap-2">
        <div class="relative flex-1">
            <input type="text" name="additional_file_number[]" readonly
                class="additional-fileno-input w-full px-4 py-3 rounded-xl border border-slate-200 bg-slate-50 text-sm font-bold font-mono text-slate-700"
                placeholder="Select file...">
            <div class="additional-fileno-indicator hidden absolute right-3 top-1/2 -translate-y-1/2">
                <i data-lucide="check-circle" class="h-5 w-5 text-emerald-500"></i>
            </div>
        </div>
        <button type="button"
            class="additional-fileno-search-btn p-3 rounded-xl bg-blue-50 text-blue-600 font-bold border border-blue-100 hover:bg-blue-100 transition flex items-center justify-center shrink-0"
            title="Select File">
            <i data-lucide="search" class="h-5 w-5"></i>
        </button>
        <button type="button"
            class="remove-fileno-btn p-3 rounded-xl bg-red-50 text-red-500 font-bold border border-red-100 hover:bg-red-100 transition flex items-center justify-center shrink-0"
            title="Remove">
            <i data-lucide="trash-2" class="h-5 w-5"></i>
        </button>
    </div>
</template>

<!-- Template for Additional Property Descriptions (Hidden) -->
<template id="additional-property-template">
    <div class="additional-property-block pt-8 border-t-2 border-indigo-100">
        <div class="flex items-center gap-3 mb-6">
            <div class="w-10 h-10 rounded-2xl bg-indigo-600 flex items-center justify-center text-white shadow-lg shadow-indigo-200">
                <i data-lucide="map-pin" class="h-5 w-5"></i>
            </div>
            <div>
                <h4 class="text-sm font-black text-slate-800 uppercase tracking-widest property-block-header">Property Description</h4>
                <p class="text-[10px] text-slate-500 font-bold uppercase">
                    Location of the subject plot &mdash;
                    <span class="property-block-fileno font-mono text-indigo-600">no file selected</span>
                </p>
            </div>
        </div>
        <div>
            <div class="space-y-3 p-4 bg-slate-50/50 rounded-2xl border border-slate-100">
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">House No</label>
                        <input type="text" name="additional_property_house_no[]"
                            class="address-component-additional-property w-full px-3 py-2 rounded-xl border border-slate-200 bg-white text-sm font-medium focus:border-blue-500 transition"
                            placeholder="e.g. 12">
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">Plot No</label>
                        <input type="text" name="additional_property_plot_no[]"
                            class="address-component-additional-property w-full px-3 py-2 rounded-xl border border-slate-200 bg-white text-sm font-medium focus:border-blue-500 transition"
                            placeholder="e.g. 45">
                    </div>
                </div>
                <div class="grid grid-cols-1 gap-3">
                    <div>
                        <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">Street</label>
                        <select name="additional_property_street[]" data-manual-input="next"
                            class="address-component-additional-property street-dropdown w-full px-3 py-2 rounded-xl border border-slate-200 bg-white text-sm font-medium focus:border-blue-500 transition" data-fill-options="streets">
                            <option value="">Select Street</option>
{{-- options: street list, filled once by the script at the end of this partial --}}
                        </select>
                        <input type="text" name="additional_property_street_manual[]"
                            class="manual-street-input hidden address-component-additional-property w-full mt-2 px-3 py-2 rounded-xl border border-slate-200 bg-white text-sm font-medium focus:border-blue-500 transition"
                            placeholder="Specify street...">
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">District</label>
                        <select name="additional_property_district[]"
                            class="address-component-additional-property dist-select w-full px-3 py-2 rounded-xl border border-slate-200 bg-white text-sm font-medium focus:border-blue-500 transition" data-fill-options="districts">
                            <option value="">Select District</option>
{{-- options: district list, filled once by the script at the end of this partial --}}
                            <option value="Other">Other</option>
                        </select>
                        <input type="text" name="additional_property_district_other[]"
                            class="hidden address-component-additional-property dist-other w-full mt-2 px-3 py-2 rounded-xl border border-slate-200 bg-white text-sm font-medium focus:border-blue-500 transition"
                            placeholder="Specify district...">
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">LGA <span class="text-red-500">*</span></label>
                        <select name="additional_property_lga[]" required
                            class="address-component-additional-property w-full px-3 py-2 rounded-xl border border-slate-200 bg-white text-sm font-medium focus:border-blue-500 transition">
                            <option value="">Select LGA</option>
                            @foreach($lgas as $lga)
                                <option value="{{ $lga->LGAName }}">{{ $lga->LGAName }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div>
                    <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">State <span class="text-red-500">*</span></label>
                    <select name="additional_property_state[]" required
                        class="address-component-additional-property w-full px-3 py-2 rounded-xl border border-slate-200 bg-white text-sm font-medium focus:border-blue-500 transition">
                        <option value="">Select State</option>
                        @foreach($states as $state)
                            <option value="{{ $state->StateName }}" {{ $state->StateName == 'Kano' ? 'selected' : '' }}>{{ $state->StateName }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <input type="hidden" name="additional_property_description[]" class="additional-property-hidden">
            <div class="mt-3 p-3 bg-white rounded-xl border border-dashed border-slate-200 text-xs text-slate-500">
                <span class="font-bold text-slate-400 uppercase text-[10px] block mb-1">Full Description Preview:</span>
                <span class="additional-property-preview italic">No description built yet...</span>
            </div>
        </div>
    </div>
</template>

<!-- Template for Additional Parties (Hidden) -->
<template id="additional-party-template">
    <div class="additional-party-block pt-8 border-t-2 border-slate-100 space-y-4">
        <div class="flex items-center gap-3 mb-4">
            <div class="w-10 h-10 rounded-2xl bg-slate-100 flex items-center justify-center text-slate-500">
                <i data-lucide="users" class="h-5 w-5"></i>
            </div>
            <div class="flex-1">
                <div class="flex items-center justify-between">
                    <h4 class="text-sm font-black text-slate-800 uppercase tracking-widest party-header">Additional Mortgagee</h4>
                    <button type="button" class="remove-party-btn p-1.5 rounded-lg bg-red-50 text-red-500 hover:bg-red-100 transition shadow-sm border border-red-100 flex items-center gap-1 text-[10px] font-bold uppercase">
                        <i data-lucide="trash-2" class="h-3 w-3"></i>
                        <span>Remove</span>
                    </button>
                </div>
                <!-- <p class="text-[10px] text-slate-500 font-bold uppercase">Co-Lender details</p> -->
            </div>
        </div>

        <div>
            <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">Mortgagee Name <span class="text-red-500">*</span></label>
            <input type="text" name="additional_party_name[]" required
                class="w-full px-4 py-3 rounded-xl border border-slate-200 bg-slate-50 focus:border-blue-500 focus:ring-2 focus:ring-blue-100 transition focus:bg-white text-sm font-medium"
                placeholder="Full name of the party">
        </div>
        
        <div>
            <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">Mortgagee Address</label>
            <div class="space-y-3 p-4 bg-slate-50/50 rounded-2xl border border-slate-100">
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">House No</label>
                        <input type="text" name="additional_party_house_no[]"
                            class="address-component-additional w-full px-3 py-2 rounded-xl border border-slate-200 bg-white text-sm font-medium focus:border-blue-500 transition"
                            placeholder="e.g. 12">
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">Street</label>
                        <select name="additional_party_street[]" data-manual-input="next"
                            class="address-component-additional street-dropdown w-full px-3 py-2 rounded-xl border border-slate-200 bg-white text-sm font-medium focus:border-blue-500 transition" data-fill-options="streets">
                            <option value="">Select Street</option>
{{-- options: street list, filled once by the script at the end of this partial --}}
                        </select>
                        <input type="text" name="additional_party_street_manual[]"
                            class="manual-street-input hidden address-component-additional w-full mt-2 px-3 py-2 rounded-xl border border-slate-200 bg-white text-sm font-medium focus:border-blue-500 transition"
                            placeholder="Specify street...">
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">District</label>
                        <select name="additional_party_district[]"
                            class="address-component-additional dist-select w-full px-3 py-2 rounded-xl border border-slate-200 bg-white text-sm font-medium focus:border-blue-500 transition" data-fill-options="districts">
                            <option value="">Select District</option>
{{-- options: district list, filled once by the script at the end of this partial --}}
                            <option value="Other">Other</option>
                        </select>
                        <input type="text" name="additional_party_district_other[]"
                            class="hidden address-component-additional dist-other w-full mt-2 px-3 py-2 rounded-xl border border-slate-200 bg-white text-sm font-medium focus:border-blue-500 transition"
                            placeholder="Specify district...">
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">LGA <span class="text-red-500">*</span></label>
                        <select name="additional_party_lga[]" required
                            class="address-component-additional w-full px-3 py-2 rounded-xl border border-slate-200 bg-white text-sm font-medium focus:border-blue-500 transition">
                            <option value="">Select LGA</option>
                            @foreach($lgas as $lga)
                                <option value="{{ $lga->LGAName }}">{{ $lga->LGAName }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div>
                    <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">State <span class="text-red-500">*</span></label>
                    <select name="additional_party_state[]" required
                        class="address-component-additional w-full px-3 py-2 rounded-xl border border-slate-200 bg-white text-sm font-medium focus:border-blue-500 transition">
                        <option value="">Select State</option>
                        @foreach($states as $state)
                            <option value="{{ $state->StateName }}" {{ $state->StateName == 'Kano' ? 'selected' : '' }}>{{ $state->StateName }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <!-- Built address for additional party will be stored in a hidden field during submission if needed, 
                 but we can also build it in JS and store it in the JSON array. -->
        </div>
    </div>
</template>

<!-- Template for Additional Applicants (Hidden) -->
<template id="additional-applicant-template">
    <div class="additional-applicant-block pt-8 border-t-2 border-slate-100 space-y-4">
        <div class="flex items-center gap-3 mb-4">
            <div class="w-10 h-10 rounded-2xl bg-slate-100 flex items-center justify-center text-slate-500">
                <i data-lucide="user" class="h-5 w-5"></i>
            </div>
            <div class="flex-1">
                <div class="flex items-center justify-between">
                    <h4 class="text-sm font-black text-slate-800 uppercase tracking-widest applicant-header">Additional Applicant</h4>
                    <button type="button" class="remove-applicant-btn p-1.5 rounded-lg bg-red-50 text-red-500 hover:bg-red-100 transition shadow-sm border border-red-100 flex items-center gap-1 text-[10px] font-bold uppercase">
                        <i data-lucide="trash-2" class="h-3 w-3"></i>
                        <span>Remove</span>
                    </button>
                </div>
            </div>
        </div>

        <div>
            <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">Applicant Name <span class="text-red-500">*</span></label>
            <input type="text" name="additional_applicant_name[]" required
                class="w-full px-4 py-3 rounded-xl border border-slate-200 bg-slate-50 focus:border-blue-500 focus:ring-2 focus:ring-blue-100 transition focus:bg-white text-sm font-medium"
                placeholder="Full name of the party">
        </div>
        
        <div>
            <label class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-2">Applicant Address</label>
            <div class="space-y-3 p-4 bg-slate-50/50 rounded-2xl border border-slate-100">
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">House No</label>
                        <input type="text" name="additional_applicant_house_no[]"
                            class="address-component-additional-applicant w-full px-3 py-2 rounded-xl border border-slate-200 bg-white text-sm font-medium focus:border-blue-500 transition"
                            placeholder="e.g. 12">
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">Street</label>
                        <select name="additional_applicant_street[]" data-manual-input="next"
                            class="address-component-additional-applicant street-dropdown w-full px-3 py-2 rounded-xl border border-slate-200 bg-white text-sm font-medium focus:border-blue-500 transition" data-fill-options="streets">
                            <option value="">Select Street</option>
{{-- options: street list, filled once by the script at the end of this partial --}}
                        </select>
                        <input type="text" name="additional_applicant_street_manual[]"
                            class="manual-street-input hidden address-component-additional-applicant w-full mt-2 px-3 py-2 rounded-xl border border-slate-200 bg-white text-sm font-medium focus:border-blue-500 transition"
                            placeholder="Specify street...">
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">District</label>
                        <select name="additional_applicant_district[]"
                            class="address-component-additional-applicant dist-select w-full px-3 py-2 rounded-xl border border-slate-200 bg-white text-sm font-medium focus:border-blue-500 transition" data-fill-options="districts">
                            <option value="">Select District</option>
{{-- options: district list, filled once by the script at the end of this partial --}}
                            <option value="Other">Other</option>
                        </select>
                        <input type="text" name="additional_applicant_district_other[]"
                            class="hidden address-component-additional-applicant dist-other w-full mt-2 px-3 py-2 rounded-xl border border-slate-200 bg-white text-sm font-medium focus:border-blue-500 transition"
                            placeholder="Specify district...">
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">LGA <span class="text-red-500">*</span></label>
                        <select name="additional_applicant_lga[]" required
                            class="address-component-additional-applicant w-full px-3 py-2 rounded-xl border border-slate-200 bg-white text-sm font-medium focus:border-blue-500 transition">
                            <option value="">Select LGA</option>
                            @foreach($lgas as $lga)
                                <option value="{{ $lga->LGAName }}">{{ $lga->LGAName }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div>
                    <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">State <span class="text-red-500">*</span></label>
                    <select name="additional_applicant_state[]" required
                        class="address-component-additional-applicant w-full px-3 py-2 rounded-xl border border-slate-200 bg-white text-sm font-medium focus:border-blue-500 transition">
                        <option value="">Select State</option>
                        @foreach($states as $state)
                            <option value="{{ $state->StateName }}" {{ $state->StateName == 'Kano' ? 'selected' : '' }}>{{ $state->StateName }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>
    </div>
</template>

{{-- The district (1,800+) and street (800+) lists used to be printed out in full
     in every one of the fifteen selects above, templates included, which made up
     2.6 MB of the page. They are sent once here and copied in before
     consent_applications.js runs. A district select keeps its trailing "Other";
     the street list carries "Other" as its last entry, as $streetOptions did. --}}
<script>
  (function () {
    var lists = {
      districts: @json(collect($districts)->pluck('name')->values()),
      streets: @json($streetOptions->pluck('name')->values())
    };
    var fragments = {};
    Object.keys(lists).forEach(function (key) {
      var frag = document.createDocumentFragment();
      lists[key].forEach(function (name) { frag.appendChild(new Option(name, name)); });
      fragments[key] = frag;
    });

    function fill(root) {
      root.querySelectorAll('select[data-fill-options]').forEach(function (select) {
        var frag = fragments[select.getAttribute('data-fill-options')];
        if (!frag) return;
        var other = select.querySelector('option[value="Other"]');
        select.insertBefore(frag.cloneNode(true), other);
      });
    }

    fill(document);
    document.querySelectorAll('template').forEach(function (tpl) { fill(tpl.content); });
  })();
</script>
