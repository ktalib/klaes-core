<div x-data="printManager" 
     @open-print-manager.window="openModal($event.detail)"
     x-show="isOpen" 
     class="fixed inset-0 z-[999999] overflow-y-auto" 
     x-cloak>
    <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
        <div x-show="isOpen" 
             x-transition:enter="ease-out duration-300" 
             x-transition:enter-start="opacity-0" 
             x-transition:enter-end="opacity-100" 
             x-transition:leave="ease-in duration-200" 
             x-transition:leave-start="opacity-100" 
             x-transition:leave-end="opacity-0" 
             class="fixed inset-0 transition-opacity bg-slate-900/80 backdrop-blur-md" 
             @click="closeModal()"></div>

        <span class="hidden sm:inline-block sm:align-middle sm:h-screen">&#8203;</span>

        <div x-show="isOpen" 
             x-transition:enter="ease-out duration-300" 
             x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" 
             x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100" 
             x-transition:leave="ease-in duration-200" 
             x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100" 
             x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" 
             class="inline-block w-full max-w-lg overflow-hidden text-left align-middle transition-all transform bg-white shadow-3xl rounded-3xl sm:my-8 sm:align-middle border border-white/20">
            
            <!-- Modal Header -->
            <div class="px-6 py-4 bg-gradient-to-r from-slate-900 to-slate-800 flex justify-between items-center relative overflow-hidden">
                <div class="absolute inset-0 bg-blue-500/5 backdrop-blur-3xl"></div>
                <div class="relative z-10">
                    <h3 class="text-xl font-bold text-white flex items-center gap-2">
                        <i data-lucide="printer" class="h-6 w-6 text-blue-400"></i>
                       Print Manager
                       {{-- Makes it unmistakable that this run prints the re-issued letter --}}
                       <span x-show="isReissuanceType" x-cloak
                             class="px-2 py-0.5 rounded-full bg-amber-500 text-white text-[10px] font-black uppercase tracking-widest">
                           Re-issuance
                       </span>
                    </h3>
                    <p class="text-slate-400 text-xs mt-1 uppercase tracking-widest font-semibold flex items-center gap-1">
                        Ref: <span class="text-blue-300 font-mono" x-text="refNumber"></span>
                        <span x-show="isReissuanceType" x-cloak class="text-amber-300"
                              x-text="isLegacyReissuanceType ? '— Re-issued RofO (Pre-KLAES · all 3 copies)' : '— Re-issued RofO (Original only)'"></span>
                    </p>
                </div>
                <button @click="closeModal()" class="relative z-10 p-2 text-slate-400 hover:text-white hover:bg-white/10 rounded-full transition-all">
                    <i data-lucide="x" class="h-6 w-6"></i>
                </button>
            </div>

            <div class="px-6 py-6 space-y-6">
                <div x-show="!isOssMode" class="space-y-6">
                    <!-- Batch Status Overview -->
                    <div class="grid grid-cols-3 gap-3">
                        <template x-for="(step, index) in sequence" :key="index">
                            {{-- Each tile prints the copy it names. The sequence is the
                                 order the copies are normally run off, not a lock: an
                                 operator who has to re-run one sheet — a jam, a spoilt
                                 Duplicate — presses that copy and gets that copy. The
                                 button below still runs the whole set in one go. --}}
                            <button type="button"
                                 @click="printCopy(step.type)"
                                 :disabled="isPrinting"
                                 :title="'Print the ' + step.type + ' on its own'"
                                 class="relative flex flex-col items-center p-3 rounded-2xl border transition-all duration-500 focus:outline-none focus:ring-2 focus:ring-blue-400"
                                 :class="{
                                    'bg-blue-50 border-blue-200 shadow-sm': currentStep === index,
                                    'bg-green-50 border-green-200 scale-95 opacity-70': isCompleted(step.type),
                                    'bg-slate-50 border-slate-100 grayscale opacity-40': !isCompleted(step.type) && currentStep !== index,
                                    'cursor-wait': isPrinting,
                                    'cursor-pointer hover:border-blue-400 hover:shadow-md hover:opacity-100': !isPrinting
                                 }">
                                <div class="absolute -top-2 -right-2 w-6 h-6 rounded-full flex items-center justify-center text-xs font-bold"
                                     :class="isCompleted(step.type) ? 'bg-green-500 text-white' : (currentStep === index ? 'bg-blue-600 text-white' : 'bg-slate-300 text-slate-500')">
                                    <template x-if="isCompleted(step.type)">
                                        <i data-lucide="check" class="h-3 w-3"></i>
                                    </template>
                                    <template x-if="!isCompleted(step.type)">
                                        <span x-text="index + 1"></span>
                                    </template>
                                </div>
                                <span class="text-[10px] font-bold text-slate-400 uppercase mb-2" x-text="step.type"></span>
                                <div class="w-10 h-10 rounded-xl flex items-center justify-center mb-1"
                                     :class="currentStep === index ? 'bg-blue-600 text-white' : 'bg-slate-200 text-slate-500'">
                                    <i :data-lucide="step.icon" class="h-5 w-5"></i>
                                </div>
                            </button>
                        </template>
                    </div>

                    <!-- Controls -->
                    <div x-show="!batchCompleted || isSingleStepType" class="space-y-6">
                        <div class="flex items-center justify-between p-5 bg-slate-50 rounded-2xl border border-slate-100 hidden">
                            <div>
                                <p class="text-sm font-bold text-slate-900">Current Queue</p>
                                <p class="text-xs text-slate-500" x-text="'Processing ' + currentStepName"></p>
                            </div>
                            <div class="flex items-center gap-4">
                                <div class="flex flex-col items-end">
                                    <span class="text-[10px] font-bold text-slate-400 uppercase">Copies</span>
                                    <input type="number" x-model="copies" min="1" max="50"
                                           class="w-16 px-3 py-1.5 bg-white border border-slate-200 rounded-lg text-sm font-bold text-center focus:ring-2 focus:ring-blue-500 outline-none">
                                </div>
                            </div>
                        </div>

                        <div class="bg-blue-50/50 p-4 rounded-xl border border-blue-100/50 flex gap-3">
                            <i data-lucide="info" class="h-5 w-5 text-blue-500 shrink-0"></i>
                             <p class="text-[11px] text-blue-700 leading-relaxed font-semibold">
                                <template x-if="isLegalSearchType">
                                    <span>"Print Original" issues the applicant's copy. "Print File Copy" produces the stamped FILE COPY retained in the file. Each choice sets the watermark on the printed report.</span>
                                </template>
                                <template x-if="isSingleStepType && !isLegalSearchType">
                                    <span>"Print Original" generates the Original document. You can print multiple copies if needed. Status will be marked as Complete after the first print, enabling CTC generation.</span>
                                </template>
                                <template x-if="!isSingleStepType">
                                    <span>"Print All 3 Copies" generates the full set — Original, Duplicate and Triplicate — at once. To run off just one of them, click that copy above. A green tick means that copy has already been printed. After the full set, you can generate "Certified True Copies" as needed.</span>
                                </template>
                            </p>
                        </div>
                    </div>

                    <!-- Finished State -->
                    <div x-show="batchCompleted && !isSingleStepType" class="py-10 text-center space-y-4 animate-in fade-in zoom-in duration-500">
                        <div class="w-16 h-16 bg-green-100 text-green-600 rounded-full flex items-center justify-center mx-auto shadow-inner">
                            <i data-lucide="check-circle" class="h-8 w-8"></i>
                        </div>
                        <h4 class="text-xl font-bold text-slate-900">Originals Printed</h4>
                    </div>
                </div>

                <div x-show="isOssMode" class="grid grid-cols-2 gap-4">
                    <button type="button" @click="ossLaunchVerification()"
                        :disabled="!ossGeneratedState.verification"
                        class="flex items-center gap-4 rounded-xl border border-teal-200 bg-teal-50 p-4 text-left transition hover:bg-teal-100 hover:shadow-sm disabled:cursor-not-allowed disabled:opacity-50">
                        <div class="flex-shrink-0 w-10 h-10 rounded-lg bg-teal-100 flex items-center justify-center">
                            <i data-lucide="clipboard-check" class="w-5 h-5 text-teal-700"></i>
                        </div>
                        <div>
                            <p class="text-sm font-bold text-teal-900">Verification</p>
                            <p class="text-xs text-teal-600 mt-0.5" x-text="ossHints.verification"></p>
                        </div>
                    </button>

                    <button type="button" @click="ossLaunchAcknowledgement()"
                        :disabled="!ossGeneratedState.acknowledgement"
                        class="flex items-center gap-4 rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-left transition hover:bg-emerald-100 hover:shadow-sm disabled:cursor-not-allowed disabled:opacity-50">
                        <div class="flex-shrink-0 w-10 h-10 rounded-lg bg-emerald-100 flex items-center justify-center">
                            <i data-lucide="circle-check-big" class="w-5 h-5 text-emerald-700"></i>
                        </div>
                        <div>
                            <p class="text-sm font-bold text-emerald-900">Acknowledgement</p>
                            <p class="text-xs text-emerald-600 mt-0.5" x-text="ossHints.acknowledgement"></p>
                        </div>
                    </button>

                    <button type="button" @click="ossLaunchRecommendation()"
                        :disabled="!ossGeneratedState.recommendation"
                        class="flex items-center gap-4 rounded-xl border border-blue-200 bg-blue-50 p-4 text-left transition hover:bg-blue-100 hover:shadow-sm disabled:cursor-not-allowed disabled:opacity-50">
                        <div class="flex-shrink-0 w-10 h-10 rounded-lg bg-blue-100 flex items-center justify-center">
                            <i data-lucide="thumbs-up" class="w-5 h-5 text-blue-700"></i>
                        </div>
                        <div>
                            <p class="text-sm font-bold text-blue-900">Recommendation</p>
                            <p class="text-xs text-blue-600 mt-0.5" x-text="ossHints.recommendation"></p>
                        </div>
                    </button>

                    <button type="button" @click="ossLaunchCommissioningSheet()"
                        x-show="!hideCommissioningSheet"
                        class="flex items-center gap-4 rounded-xl border border-violet-200 bg-violet-50 p-4 text-left transition hover:bg-violet-100 hover:shadow-sm">
                        <div class="flex-shrink-0 w-10 h-10 rounded-lg bg-violet-100 flex items-center justify-center">
                            <i data-lucide="printer" class="w-5 h-5 text-violet-700"></i>
                        </div>
                        <div>
                            <p class="text-sm font-bold text-violet-900">Commissioning Sheet</p>
                            <p class="text-xs text-violet-600 mt-0.5">Generate &amp; print directly</p>
                        </div>
                    </button>
                </div>
            </div>

            <!-- Modal Footer -->
            <div class="px-6 py-4 bg-slate-50 border-t border-slate-100 flex justify-between items-center rounded-b-3xl">
                <button @click="closeModal()" class="px-6 py-2.5 text-sm font-bold text-slate-600 hover:bg-slate-200 rounded-xl transition-all">
                    Cancel
                </button>
                <div x-show="!isOssMode" class="flex gap-3">
                    <!-- Legal Search: explicit Original / File Copy choice -->
                    <template x-if="isLegalSearchType">
                        <div class="flex gap-3">
                            <button @click="executeLegalSearchPrint('Original')"
                                    :disabled="isPrinting"
                                    class="px-6 py-3 text-sm font-bold text-white bg-indigo-600 hover:bg-indigo-700 rounded-xl shadow-xl shadow-indigo-200 transition-all flex items-center gap-2 disabled:opacity-50 active:scale-95">
                                <i data-lucide="printer" class="h-4 w-4"></i>
                                <span>Print Original</span>
                            </button>
                            <button @click="executeLegalSearchPrint('Copy')"
                                    :disabled="isPrinting"
                                    class="px-6 py-3 text-sm font-bold text-slate-700 bg-white border border-slate-300 hover:bg-slate-50 rounded-xl shadow-sm transition-all flex items-center gap-2 disabled:opacity-50 active:scale-95">
                                <i data-lucide="copy" class="h-4 w-4"></i>
                                <span>Print File Copy</span>
                            </button>
                        </div>
                    </template>

                    <!-- Batch Print Button -->
                    <button x-show="(!batchCompleted || isSingleStepType) && !isLegalSearchType"
                            @click="executeBatchPrint()"
                            :disabled="isPrinting"
                            class="px-6 py-3 text-sm font-bold text-white bg-indigo-600 hover:bg-indigo-700 rounded-xl shadow-xl shadow-indigo-200 transition-all flex items-center gap-2 disabled:opacity-50 active:scale-95">
                        <template x-if="!isPrinting">
                            <i data-lucide="printer" class="h-4 w-4"></i>
                        </template>
                        <template x-if="isPrinting">
                            <span class="w-4 h-4 border-2 border-white/30 border-t-white rounded-full animate-spin"></span>
                        </template>
                        {{-- It prints all three; calling it "Print Original" was the
                             reason a click here looked like it had done nothing to the
                             Duplicate and Triplicate. --}}
                        <span x-text="isSingleStepType ? 'Print' : 'Print All 3 Copies'"></span>
                    </button>

                    <!-- Certified True Copy Button (Visible after batch) -->
                    <button x-show="batchCompleted && !isSingleStepType" 
                            @click="executeCTCPrint()" 
                            :disabled="isPrinting"
                            class="px-6 py-3 text-sm font-bold text-white bg-emerald-600 hover:bg-emerald-700 rounded-xl shadow-xl shadow-emerald-200 transition-all flex items-center gap-2 disabled:opacity-50 active:scale-95">
                        <template x-if="!isPrinting">
                            <i data-lucide="award" class="h-4 w-4"></i>
                        </template>
                        <template x-if="isPrinting">
                            <span class="w-4 h-4 border-2 border-white/30 border-t-white rounded-full animate-spin"></span>
                        </template>
                        <span>Print</span>
                    </button>

                    <button x-show="!batchCompleted" 
                            @click="executePrint()" 
                            :disabled="isPrinting"
                            class="px-8 py-3 text-sm font-bold text-white bg-blue-600 hover:bg-blue-700 rounded-xl shadow-xl shadow-blue-200 transition-all flex items-center gap-2 disabled:opacity-50 active:scale-95 hidden">
                        <template x-if="!isPrinting">
                            <i data-lucide="printer" class="h-4 w-4"></i>
                        </template>
                        <template x-if="isPrinting">
                            <span class="w-4 h-4 border-2 border-white/30 border-t-white rounded-full animate-spin"></span>
                        </template>
                        <span x-text="'Print ' + currentStepName"></span>
                    </button>

                    <button x-show="batchCompleted" 
                            @click="closeModal()" 
                            class="px-8 py-2.5 text-sm font-bold text-slate-700 bg-white border border-slate-200 hover:bg-slate-50 rounded-xl transition-all">
                        Exit
                    </button>
                </div>
                <div x-show="isOssMode" class="flex gap-3">
                    <button @click="closeModal()" class="px-8 py-2.5 text-sm font-bold text-slate-700 bg-white border border-slate-200 hover:bg-slate-50 rounded-xl transition-all">
                        Close
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('printManager', () => ({
        isOpen: false,
        isPrinting: false,
        refNumber: '',
        docType: '',
        printUrl: '',
        moduleContext: '',
        moduleRecord: null,
        hideCommissioningSheet: false,
        printLogs: [],
        copies: 1,
        currentStep: 0,
        batchCompleted: false,
        ossGeneratedState: {
            verification: false,
            acknowledgement: false,
            recommendation: false
        },
        ossHints: {
            verification: 'Enabled after verification is generated',
            acknowledgement: 'Enabled after acknowledgement is generated',
            recommendation: 'Enabled after recommendation is generated'
        },
        sequence: [
            { type: 'Original', icon: 'award' },
            { type: 'Duplicate', icon: 'copy' },
            { type: 'Triplicate', icon: 'layers' }
        ],

        async openModal(data) {
            this.refNumber = data.ref;
            this.docType = data.type;
            this.printUrl = data.url;
            this.moduleContext = (data && data.module ? String(data.module) : '').toLowerCase();
            this.moduleRecord = data && data.record ? data.record : null;
            this.hideCommissioningSheet = !!(data && data.hideCommissioningSheet);
            this.copies = 1;
            this.currentStep = 0;
            this.batchCompleted = false;

            if (this.isOssMode) {
                this.resetOssStates();
                await this.loadOssGeneratedStates();
                this.isOpen = true;
                this.$nextTick(() => lucide.createIcons());
                return;
            } 
            
            // Set sequence based on document type
            if (this.isSingleStepType) {
                this.sequence = [{ type: 'Original', icon: 'award' }];
            } else {
                this.sequence = [
                    { type: 'Original', icon: 'award' },
                    { type: 'Duplicate', icon: 'copy' },
                    { type: 'Triplicate', icon: 'layers' }
                ];
            }
            
            await this.checkStatus();
            
            this.isOpen = true;
            this.$nextTick(() => lucide.createIcons());
        },

        get isOssMode() {
            return this.moduleContext === 'oss';
        },

        get isReissuanceType() {
            return String(this.docType || '').includes('Re-issuance');
        },

        // A KLAES-generated RofO already had its Original/Duplicate/Triplicate set
        // issued, so its re-issuance is the Original copy only. A pre-KLAES (legacy)
        // one was never issued from here, so it prints the full set.
        get isLegacyReissuanceType() {
            return this.isReissuanceType && String(this.docType).includes('Legacy');
        },

        get isSingleStepType() {
            if (this.isReissuanceType) return !this.isLegacyReissuanceType;
            return ['Recommendation For Grant', 'ST CofO', 'Commissioning Sheet', 'Bill Balance',
                    'Legal Search Pay-Per-Search', 'Legal Search Online', 'Legal Search Official'].includes(this.docType);
        },

        get isSelfLogging() {
            return ['Legal Search Pay-Per-Search', 'Legal Search Online'].includes(this.docType);
        },

        // Legal Search doc types get an explicit Original / File Copy choice
        // instead of the single auto-deciding Print button.
        get isLegalSearchType() {
            return ['Legal Search Pay-Per-Search', 'Legal Search Online', 'Legal Search Official'].includes(this.docType);
        },

        closeModal() {
            if (this.isPrinting) return;
            this._cleanupFocusRefresh();
            this.isOpen = false;
            this.moduleContext = '';
            this.moduleRecord = null;
        },

        _setupFocusRefresh() {
            this._cleanupFocusRefresh();
            this._focusHandler = async () => {
                await this.checkStatus();
            };
            window.addEventListener('focus', this._focusHandler);
        },

        _cleanupFocusRefresh() {
            if (this._focusHandler) {
                window.removeEventListener('focus', this._focusHandler);
                this._focusHandler = null;
            }
        },

        resetOssStates() {
            this.ossGeneratedState = {
                verification: false,
                acknowledgement: false,
                recommendation: false
            };
            this.ossHints = {
                verification: 'Enabled after verification is generated',
                acknowledgement: 'Enabled after acknowledgement is generated',
                recommendation: 'Enabled after recommendation is generated'
            };
        },

        getOssCaptureRecordId() {
            const rec = this.moduleRecord || {};
            return String(rec.source_instrument_capture_id || rec.id || '').trim();
        },

        getOssFileReference() {
            const rec = this.moduleRecord || {};
            return String(rec.file_no || rec.mls_file_no || rec.mlsfNo || '').trim();
        },

        async loadOssGeneratedStates() {
            const recordId = this.getOssCaptureRecordId();
            const fileRef = this.getOssFileReference();

            if (recordId) {
                try {
                    const res = await fetch('/lands-one-stop-shop/applications/verification-status?record_id=' + encodeURIComponent(recordId), {
                        method: 'GET',
                        headers: { 'Accept': 'application/json' }
                    });
                    const result = await res.json();
                    const ready = !!(result && result.success && result.data && result.data.exists);
                    this.ossGeneratedState.verification = ready;
                    this.ossHints.verification = ready ? 'View & print verification' : 'Enabled after verification is generated';
                } catch (_) {
                    this.ossGeneratedState.verification = false;
                    this.ossHints.verification = 'Unable to verify status right now';
                }

                const ackKey = 'oss_ack_generated_' + recordId;
                const ackReady = localStorage.getItem(ackKey) === '1';
                this.ossGeneratedState.acknowledgement = ackReady;
                this.ossHints.acknowledgement = ackReady ? 'View & print acknowledgement' : 'Enabled after acknowledgement is generated';
            }

            if (fileRef) {
                try {
                    const res = await fetch('/lands-one-stop-shop/applications/recommendation-status?file_ref=' + encodeURIComponent(fileRef), {
                        method: 'GET',
                        headers: { 'Accept': 'application/json' }
                    });
                    const result = await res.json();
                    const ready = !!(result && result.success && result.data && result.data.exists);
                    this.ossGeneratedState.recommendation = ready;
                    if (ready && this.moduleRecord) {
                        this.moduleRecord.recommendation_id = result.data.id || null;
                    }
                    this.ossHints.recommendation = ready ? 'View & print recommendation' : 'Enabled after recommendation is generated';
                } catch (_) {
                    this.ossGeneratedState.recommendation = false;
                    this.ossHints.recommendation = 'Unable to verify status right now';
                }
            }
        },

        ossLaunchVerification() {
            if (!this.moduleRecord) return;
            if (!this.ossGeneratedState.verification) {
                Swal.fire({ icon: 'info', title: 'Verification Not Generated', text: 'Generate verification first, then use this print option.' });
                return;
            }

            const captureId = this.getOssCaptureRecordId();
            if (!captureId) {
                Swal.fire({ icon: 'warning', title: 'Missing Record', text: 'Could not resolve verification record.' });
                return;
            }

            this.closeModal();
            window.open('/lands-one-stop-shop/applications/' + captureId + '/print-verification-view', '_blank');
        },

        ossLaunchAcknowledgement() {
            if (!this.moduleRecord) return;
            if (!this.ossGeneratedState.acknowledgement) {
                Swal.fire({ icon: 'info', title: 'Acknowledgement Not Generated', text: 'Generate acknowledgement first, then use this print option.' });
                return;
            }

            const captureId = this.getOssCaptureRecordId();
            if (!captureId) {
                Swal.fire({ icon: 'warning', title: 'Missing Record', text: 'Could not resolve acknowledgement record.' });
                return;
            }

            this.closeModal();
            window.open('/lands-one-stop-shop/applications/' + captureId + '/print-acknowledgement', '_blank');
        },

        ossLaunchRecommendation() {
            if (!this.moduleRecord) return;
            if (!this.ossGeneratedState.recommendation) {
                Swal.fire({ icon: 'info', title: 'Recommendation Not Generated', text: 'Generate recommendation first, then use this print option.' });
                return;
            }

            const recId = String(this.moduleRecord.recommendation_id || '').trim();
            if (!recId) {
                Swal.fire({ icon: 'warning', title: 'Missing Recommendation', text: 'Could not resolve recommendation record.' });
                return;
            }

            this.closeModal();
            window.open('/land-recommendations/' + recId + '/print', '_blank');
        },

        async ossLaunchCommissioningSheet() {
            if (!this.moduleRecord) return;

            const data = this.moduleRecord;
            const fileNo = this.getOssFileReference();
            if (!fileNo) {
                Swal.fire({ icon: 'warning', title: 'Missing File Number', text: 'This record has no file number.' });
                return;
            }

            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
            const payload = {
                file_number: fileNo,
                file_name: String(data.party_2 || data.file_title || '').trim(),
                name_or_allottee: String(data.party_2 || data.file_title || '').trim(),
                plot_number: String(data.plot_no || '').trim(),
                tp_number: String(data.tp_no || data.plan_no || '').trim(),
                location: String(data.location || data.property_description || data.district || '').trim(),
                lga: String(data.lga || '').trim()
            };

            try {
                const res = await fetch('{{ route('commissioning-sheet.generate-print') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify(payload)
                });
                const result = await res.json();
                if (!result || !result.success || !result.data || !result.data.id) {
                    Swal.fire({ icon: 'error', title: 'Error', text: (result && result.message) ? result.message : 'Failed to prepare commissioning sheet.' });
                    return;
                }

                this.closeModal();
                const printUrl = '{{ url('commissioning-sheet/print') }}/' + result.data.id + '?source=oss';
                window.open(printUrl, '_blank');
            } catch (err) {
                console.error('Commissioning sheet error:', err);
                Swal.fire({ icon: 'error', title: 'Error', text: 'Network error. Please try again.' });
            }
        },

        async checkStatus() {
            try {
                const response = await fetch(`{{ route('print-manager.status') }}?reference_number=${this.refNumber}&document_type=${this.docType}`);
                const data = await response.json();
                if (data.success) {
                    this.printLogs = data.status;
                    this.determineCurrentStep();
                    this.batchCompleted = data.status.completed;
                    
                    if (this.batchCompleted && !this.isSingleStepType) {
                        this.currentStep = 3; // Finished for multi-step
                    } else if (this.batchCompleted && this.isSingleStepType) {
                        this.currentStep = 0; // Remain on Original step for single-step types to allow reprint
                    }
                }
            } catch (error) {
                console.error('Failed to fetch print status', error);
            }
        },

        determineCurrentStep() {
            if (this.isSingleStepType) {
                this.currentStep = 0;
            } else {
                if (this.printLogs.original === 0) this.currentStep = 0;
                else if (this.printLogs.duplicate === 0) this.currentStep = 1;
                else if (this.printLogs.triplicate === 0) this.currentStep = 2;
                else this.currentStep = 3;
            }
        },

        isCompleted(type) {
            if (!this.printLogs) return false;
            return this.printLogs[type.toLowerCase()] > 0;
        },

        get currentStepName() {
            if (!this.isSingleStepType && this.currentStep >= 3) return 'Finished';
            return this.sequence[this.currentStep || 0].type;
        },

        // One copy, named. Passed nothing it prints wherever the sequence has
        // got to, which is what the (hidden) stepped button relied on.
        async executePrint(explicitType = null) {
            if (this.isPrinting) return;
            this.isPrinting = true;

            try {
                const stepType = explicitType || this.currentStepName;
                const _sep1 = this.printUrl.includes('?') ? '&' : '?';
                window.open(`${this.printUrl}${_sep1}status=${stepType}&copies=${this.copies}`, '_blank');
                
                for(let i = 0; i < this.copies; i++) {
                    await fetch('{{ route('print-manager.log') }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                        },
                        body: JSON.stringify({
                            reference_number: this.refNumber,
                            document_type: this.docType,
                            status: stepType,
                            print_type: 'Batch'
                        })
                    });
                }

                await this.checkStatus();
                
                Swal.fire({
                    title: 'Printed Successfully',
                    text: `${stepType} version logged.`,
                    icon: 'success',
                    toast: true,
                    position: 'top-end',
                    showConfirmButton: false,
                    timer: 3000
                });

            } catch (error) {
                Swal.fire('Error', 'Failed to log print action', 'error');
            } finally {
                this.isPrinting = false;
                this.$nextTick(() => lucide.createIcons());
            }
        },

        // A tile was clicked. Legal Search keeps its own two-button choice, because
        // there the copy decides the watermark and is not a step in a sequence.
        async printCopy(copyType) {
            if (this.isPrinting) return;

            if (this.isLegalSearchType) {
                await this.executeLegalSearchPrint('Original');
                return;
            }

            await this.executePrint(copyType);
        },

        async executeBatchPrint() {
            if (this.isPrinting) return;
            this.isPrinting = true;
            
            try {
                if (this.isSingleStepType) {
                    // For single step types, just print Original
                    const _sep2 = this.printUrl.includes('?') ? '&' : '?';
                    window.open(`${this.printUrl}${_sep2}status=Original&copies=${this.copies}`, '_blank');
                    
                    if (this.isSelfLogging) {
                        // Self-logging types: template handles print_logs via afterprint
                        // Re-check status when user returns to this window
                        this._setupFocusRefresh();
                    } else {
                        // Log the print action
                        await fetch('{{ route('print-manager.log') }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                            },
                            body: JSON.stringify({
                                reference_number: this.refNumber,
                                document_type: this.docType,
                                status: 'Original',
                                print_type: 'Batch' 
                            })
                        });
                    }

                } else {
                    // 1. Open Batch Print Window for Multi-step
                    const _sep3 = this.printUrl.includes('?') ? '&' : '?';
                    window.open(`${this.printUrl}${_sep3}status=Batch`, '_blank');
                    
                    // 2. Log full batch to Database
                    await fetch('{{ route('print-manager.batch-log') }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                        },
                        body: JSON.stringify({
                            reference_number: this.refNumber,
                            document_type: this.docType,
                            statuses: ['Original', 'Duplicate', 'Triplicate'],
                            print_type: 'Batch'
                        })
                    });
                }

                await this.checkStatus();
                    Swal.fire({
                        title: 'Printed Successfully',
                        text: `Document has been logged.`,
                        icon: 'success',
                        toast: true,
                        position: 'top-end',
                        showConfirmButton: false,
                        timer: 3000
                    });
            } catch (error) {
                Swal.fire('Error', 'Failed to log print', 'error');
            } finally {
                this.isPrinting = false;
                this.$nextTick(() => lucide.createIcons());
            }
        },

        // Explicit Legal Search print: the operator chooses which version to
        // produce. 'Original' = the applicant's copy; 'Copy' = the stamped
        // FILE COPY retained in the file. The chosen type is passed to the
        // template via ?status= so the watermark is forced (no auto-detect).
        async executeLegalSearchPrint(copyType) {
            if (this.isPrinting) return;
            this.isPrinting = true;

            try {
                const _sepLs = this.printUrl.includes('?') ? '&' : '?';
                window.open(`${this.printUrl}${_sepLs}status=${copyType}`, '_blank');

                if (this.isSelfLogging) {
                    // Pay-Per-Search / Online templates log this print via their
                    // own afterprint handler; just refresh status on return.
                    this._setupFocusRefresh();
                } else {
                    await fetch('{{ route('print-manager.log') }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                        },
                        body: JSON.stringify({
                            reference_number: this.refNumber,
                            document_type: this.docType,
                            status: copyType,
                            print_type: 'Individual'
                        })
                    });
                    await this.checkStatus();
                }

                Swal.fire({
                    title: copyType === 'Copy' ? 'File Copy Sent to Print' : 'Original Sent to Print',
                    text: copyType === 'Copy'
                        ? 'The retained FILE COPY is being generated.'
                        : 'The original report is being generated.',
                    icon: 'success',
                    toast: true,
                    position: 'top-end',
                    showConfirmButton: false,
                    timer: 3000
                });
            } catch (error) {
                Swal.fire('Error', 'Failed to start print', 'error');
            } finally {
                this.isPrinting = false;
                this.$nextTick(() => lucide.createIcons());
            }
        },

        executeCTCPrint() {
            // CTC prints are now batch of 3 (Original, Duplicate, Triplicate)
            const _sep4 = this.printUrl.includes('?') ? '&' : '?';
            window.open(`${this.printUrl}${_sep4}status=Batch&isCTC=1`, '_blank');
            
            Swal.fire({
                title: 'Certified True Copy Batch',
                text: `Generating CTC batch...`,
                icon: 'info',
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 3000
            });
        }
    }));
});
</script>
