<div x-data="printManager" 
     @open-print-manager.window="openModal($event.detail)"
     @white-copy-generated.window="onWhiteCopyGenerated($event.detail)"
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
             {{-- Landscape where there are three passes to lay side by side, so the
                  choice is one row rather than a tall column. A single-copy document
                  has nothing to put in that width, so it keeps the narrow modal. --}}
             :class="showPasses ? 'max-w-3xl' : 'max-w-lg'"
             class="inline-block w-full overflow-hidden text-left align-middle transition-all transform bg-white shadow-3xl rounded-3xl sm:my-8 sm:align-middle border border-white/20">
            
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
                        {{-- A batch run puts many letters on paper at once, so the
                             count is part of knowing what the pass will do. --}}
                        <span x-show="isBatchMode" x-cloak class="text-violet-300"
                              x-text="'— batch of ' + batchCount + ' RofO' + (batchCount === 1 ? '' : 's')"></span>
                    </p>
                </div>
                <button @click="closeModal()" class="relative z-10 p-2 text-slate-400 hover:text-white hover:bg-white/10 rounded-full transition-all">
                    <i data-lucide="x" class="h-6 w-6"></i>
                </button>
            </div>

            <div class="px-6 py-6 space-y-6">
                <div x-show="!isOssMode" class="space-y-6">
                    {{-- ── Where the White Copy stage is in use ───────────────────
                         The date of issue is keyed in on the White Copy, not here.
                         It is part of what the officer proofreads — the letter
                         prints it as DATE OF ISSUE — so asking for it at the
                         printer would mean the proof was read without the one
                         field the printer was still free to change afterwards.

                         What is left here is the answer, read-only, so the operator
                         can see what will print; and, when the record has none, a
                         stop. There is no fallback behind date_issued, so an
                         undated letter is not a letter — the passes stay disabled
                         and the way forward is back to the White Copy. --}}
                    {{-- The panel's colour and its chip both follow issueDateOutstanding,
                         not issueDate. A batch has no single date in that field — each
                         letter carries its own — so reading the field made a fully
                         dated batch announce itself as MISSING in red while the line
                         below it said all its letters were dated. --}}
                    <div x-show="dateOnWhiteCopy" x-cloak
                         class="p-4 rounded-2xl border space-y-2"
                         :class="issueDateOutstanding ? 'border-red-300 bg-red-50/50' : 'border-slate-200 bg-slate-50'">
                        <div class="flex items-center justify-between gap-3">
                            <label class="text-[11px] font-black uppercase tracking-widest text-slate-500">
                                Date Issued
                            </label>
                            <span x-show="issueDateUnlocked" x-cloak
                                  class="text-[10px] font-black text-amber-600 uppercase tracking-wider">Editing</span>
                            <span x-show="!issueDateUnlocked && !issueDateOutstanding" x-cloak
                                  class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Set on the White Copy</span>
                            <span x-show="!issueDateUnlocked && issueDateOutstanding" x-cloak
                                  class="text-[10px] font-black text-red-500 uppercase tracking-wider">Missing</span>
                        </div>

                        {{-- ── Read-only, which is how this panel normally stands ────
                             The date belongs to the White Copy: it prints on the letter,
                             so it is part of what was read on paper. Shown here so the
                             operator can see what will print without leaving. --}}
                        <template x-if="!issueDateUnlocked">
                            <div class="space-y-2">
                                {{-- One letter: the date it carries. --}}
                                <div x-show="!isBatchMode && issueDate" x-cloak class="flex items-center gap-2">
                                    <p class="text-[15px] font-black text-slate-800 font-mono flex-1" x-text="issueDate"></p>
                                    <button type="button" @click="issueDateConfirming = true"
                                            class="px-4 py-2 rounded-lg bg-slate-200 text-slate-700 text-xs font-black hover:bg-slate-300 transition">
                                        Edit
                                    </button>
                                </div>
                                <p x-show="!isBatchMode && issueDate" x-cloak class="text-[11px] text-slate-500 leading-relaxed">
                                    Prints on the letter as <b>DATE OF ISSUE</b>. It is set on the White Copy, where it
                                    is read back on paper — <b>Edit</b> changes it here if the proof was wrong.
                                </p>

                                {{-- A batch: the date its letters carry. Usually one —
                                     a batch is issued on a day — so it is shown exactly
                                     as a single letter's is, because this is the last
                                     screen before security paper goes through the
                                     printer and the operator has to be able to read
                                     what will print on it. Letters issued on different
                                     days keep the count and show the spread instead;
                                     there is no one date to put in front of them. --}}
                                <div x-show="isBatchMode && batchMissingDates === 0" x-cloak class="flex items-center gap-2">
                                    <div class="flex-1 min-w-0">
                                        <p x-show="batchDates.length === 1" x-cloak
                                           class="text-[15px] font-black text-slate-800 font-mono" x-text="batchDates[0]"></p>
                                        <p x-show="batchDates.length > 1" x-cloak
                                           class="text-[15px] font-black text-slate-800 font-mono truncate"
                                           x-text="batchDates[0] + ' — ' + batchDates[batchDates.length - 1]"></p>
                                        <p x-show="batchDates.length > 1" x-cloak
                                           class="text-[10px] font-black uppercase tracking-wider text-amber-600 mt-0.5">
                                            <span x-text="batchDates.length"></span> different dates in this batch
                                        </p>
                                        {{-- The fallback: the dates could not be read (the
                                             lookup failed), but every letter is known to
                                             carry one. --}}
                                        <p x-show="batchDates.length === 0" x-cloak
                                           class="text-[13px] font-bold text-slate-700">
                                            All <span x-text="batchCount"></span> letters carry a date of issue.
                                        </p>
                                    </div>
                                    <button type="button" @click="issueDateConfirming = true"
                                            class="shrink-0 px-4 py-2 rounded-lg bg-slate-200 text-slate-700 text-xs font-black hover:bg-slate-300 transition">
                                        Edit
                                    </button>
                                </div>
                                <p x-show="isBatchMode && batchMissingDates === 0" x-cloak
                                   class="text-[11px] text-slate-500 leading-relaxed">
                                    <span x-show="batchDates.length === 1" x-cloak>All
                                        <b><span x-text="batchCount"></span></b> letters print this as
                                        <b>DATE OF ISSUE</b>.</span>
                                    <span x-show="batchDates.length !== 1" x-cloak>Each prints the date on its own
                                        record as <b>DATE OF ISSUE</b>.</span>
                                    They are set on the White copy — <b>Edit</b> replaces them here if the proof
                                    was wrong.
                                </p>
                            </div>
                        </template>

                        {{-- ── Unlocked ──────────────────────────────────────────────
                             The proof was read and the date on it was wrong. Editing here
                             rather than sending the operator back to the White Copy is a
                             deliberate exception: the date is the one field on the letter
                             whose correctness is settled by reading this dialog's own
                             answer, and a second trip through the proof to change one
                             date is a trip nobody makes.

                             It is behind a confirmation because a date already on record
                             is what an issued copy out in the world carries. --}}
                        <template x-if="issueDateUnlocked">
                            <div class="space-y-2">
                                <input type="date" x-model="issueDate" :max="today" required
                                       class="w-full px-3 py-2 bg-white rounded-lg text-sm font-semibold text-slate-800 outline-none focus:ring-2 focus:ring-amber-500 border"
                                       :class="issueDate ? 'border-amber-300' : 'border-red-400 bg-red-50/40'">
                                <p x-show="!isBatchMode" class="text-[11px] text-slate-500 leading-relaxed">
                                    Saved to the record when the print runs, and printed as <b>DATE OF ISSUE</b>.
                                </p>
                                <p x-show="isBatchMode" x-cloak class="text-[11px] text-amber-800 font-semibold leading-relaxed">
                                    This replaces the date on <b>all <span x-text="batchCount"></span></b> letters in the
                                    batch, not only the blank ones.
                                </p>
                            </div>
                        </template>

                        {{-- The confirmation. Filling a blank date never reaches here —
                             there is nothing to overwrite — so this only guards a change
                             to a date already on record. --}}
                        <div x-show="issueDateConfirming" x-cloak
                             class="p-3 rounded-lg bg-amber-50 border border-amber-300 text-[12px] text-amber-900 space-y-2">
                            <p x-show="!isBatchMode">
                                Change the date of issue? This letter is dated
                                <b x-text="issueDateOnRecord || issueDate"></b>, and any copy already issued carries that date.
                            </p>
                            <p x-show="isBatchMode" x-cloak>
                                Change the date of issue on <b>all <span x-text="batchCount"></span></b> letters in this
                                batch? Any copy already issued carries the date it has now.
                            </p>
                            <div class="flex gap-2">
                                <button type="button" @click="unlockIssueDate()"
                                        class="px-3 py-1.5 rounded-md bg-amber-600 text-white text-[12px] font-bold">Yes, edit it</button>
                                <button type="button" @click="issueDateConfirming = false"
                                        class="px-3 py-1.5 rounded-md bg-slate-200 text-slate-700 text-[12px] font-bold">No</button>
                            </div>
                        </div>

                        <p x-show="issueDateError" x-cloak x-text="issueDateError"
                           class="text-[11.5px] font-bold text-red-600"></p>

                        <template x-if="issueDateOutstanding && !issueDateUnlocked">
                            <div class="space-y-2">
                                <p class="text-[12px] text-red-700 font-semibold leading-relaxed" x-show="!isBatchMode">
                                    This letter has no date of issue, and nothing else supplies one. It is entered
                                    on the White Copy, where it can be read back before anything is printed.
                                </p>
                                <p class="text-[12px] text-red-700 font-semibold leading-relaxed" x-show="isBatchMode" x-cloak>
                                    <b x-text="batchMissingDates"></b> of <b x-text="batchCount"></b> letters have no
                                    date of issue, and nothing else supplies one. It is entered on the White Copy,
                                    where it can be read back before anything is printed.
                                </p>
                                <button type="button" @click="goToWhiteCopy()"
                                        class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-slate-800 text-white text-xs font-black hover:bg-slate-900 transition">
                                    <i data-lucide="file-search" class="h-3.5 w-3.5"></i>
                                    <span x-text="isBatchMode ? 'White copy' : 'Print White Copy'"></span>
                                </button>
                            </div>
                        </template>
                    </div>

                    {{-- ── Date Issued ──────────────────────────────
                         land_recommendations.date_issued, which the letter prints as
                         DATE OF ISSUE. A column of its own: it used to be the
                         recommendation's application_date, which is a different fact
                         about a different thing and is not the printer's to edit.

                         Asked for here only where there is no White Copy stage in
                         front of this dialog — a batch run, and the documents that
                         have no proof copy of their own. Where there is one, the date
                         is keyed in there and this panel is replaced by the read-only
                         one above: see dateOnWhiteCopy.

                         Nothing stands behind date_issued — no fallback, no backfill
                         — so a letter that has never been issued has an empty date
                         until someone keys one in.

                         A date already on record is what an issued letter out in the
                         world carries, so the field stays locked until an edit is
                         confirmed. Filling a blank one never needs confirming. --}}
                    <div x-show="supportsIssueDate && !dateOnWhiteCopy" x-cloak
                         class="p-4 rounded-2xl border border-slate-200 bg-slate-50 space-y-2">
                        <div class="flex items-center justify-between gap-3">
                            <label class="text-[11px] font-black uppercase tracking-widest text-slate-500">
                                Date Issued
                                <span x-show="!issueDate" x-cloak class="text-red-500">*</span>
                            </label>
                            <span x-show="issueDateLocked && !issueDateUnlocked" x-cloak
                                  class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">On record</span>
                            <span x-show="!issueDate" x-cloak
                                  class="text-[10px] font-black text-red-500 uppercase tracking-wider">Required</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <input type="date" x-model="issueDate" :max="today" required
                                   :disabled="issueDateLocked && !issueDateUnlocked"
                                   class="flex-1 px-3 py-2 bg-white rounded-lg text-sm font-semibold text-slate-800 outline-none focus:ring-2 focus:ring-blue-500 disabled:bg-slate-100 disabled:text-slate-500 border"
                                   :class="issueDate ? 'border-slate-300' : 'border-red-400 bg-red-50/40'">
                            <button type="button"
                                    x-show="issueDateLocked && !issueDateUnlocked" x-cloak
                                    @click="issueDateConfirming = true"
                                    class="px-4 py-2 rounded-lg bg-slate-200 text-slate-700 text-xs font-black hover:bg-slate-300 transition">
                                Edit
                            </button>
                        </div>

                        <div x-show="issueDateConfirming" x-cloak
                             class="p-3 rounded-lg bg-amber-50 border border-amber-300 text-[12px] text-amber-900 space-y-2">
                            <p>
                                Change the date of issue? This letter is dated
                                <b x-text="issueDateOnRecord"></b>, and any copy already issued carries that date.
                            </p>
                            <div class="flex gap-2">
                                <button type="button" @click="unlockIssueDate()"
                                        class="px-3 py-1.5 rounded-md bg-amber-600 text-white text-[12px] font-bold">Yes, edit it</button>
                                <button type="button" @click="issueDateConfirming = false"
                                        class="px-3 py-1.5 rounded-md bg-slate-200 text-slate-700 text-[12px] font-bold">No</button>
                            </div>
                        </div>

                        <p x-show="!isBatchMode" class="text-[11px] text-slate-500 leading-relaxed">
                            Prints on the letter as <b>DATE OF ISSUE</b> and is saved to the record, so a reprint
                            comes out carrying the same date.
                        </p>
                        {{-- In a batch this fills the blanks only. A letter already
                             carrying a date keeps it, because that is the date on the
                             copy that went out. --}}
                        <p x-show="isBatchMode" x-cloak class="text-[11px] text-slate-500 leading-relaxed">
                            <b x-text="batchMissingDates"></b> of <b x-text="batchCount"></b> letters have no
                            date of issue on record. This date is written to <b>those only</b> — the rest keep
                            the date they already carry.
                        </p>
                        <p x-show="issueDateError" x-cloak x-text="issueDateError"
                           class="text-[11.5px] font-bold text-red-600"></p>
                    </div>

                    {{-- Only where there is no pass choice to make — a single-copy
                         document, whose one tile is the whole of its print state.
                         Where the three passes are on show they carry the printed
                         ticks themselves, and this row said the same thing twice. --}}
                    <div x-show="!showPasses" x-cloak class="grid grid-cols-3 gap-3">
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

                    {{-- ── The three passes ───────────────────────────────────────
                         The whole of the print choice, on one surface. These used to
                         be separate items in the row's action menu next to a "Print
                         Manager" that did something else again; there is one way in
                         now, and the choice is made here.

                         They are three passes, not three buttons that do the same
                         thing: the Original goes on the colour security stock and the
                         office copies on plain paper, which is two trips through the
                         printer with the tray changed in between. "Duplicate &
                         Triplicate" on its own is how a run stopped after the
                         Originals is picked up. --}}
                    <div x-show="showPasses" x-cloak class="grid grid-cols-3 gap-3">
                        <template x-for="pass in passOptions" :key="pass.key">
                            <button type="button"
                                    @click="runPass(pass.key)"
                                    :disabled="isPrinting || !passEnabled(pass)"
                                    :title="passDisabledReason(pass)"
                                    class="flex flex-col gap-2.5 p-3.5 rounded-xl border text-left transition-all"
                                    :class="passEnabled(pass)
                                        ? pass.tone + ' disabled:opacity-50 disabled:cursor-wait'
                                        : 'border-slate-200 bg-slate-50 opacity-60 cursor-not-allowed grayscale'">
                                <span class="flex items-center justify-between gap-2">
                                    <span class="flex-shrink-0 w-9 h-9 rounded-lg flex items-center justify-center"
                                          :class="pass.iconTone">
                                        <i :data-lucide="pass.icon" class="w-4 h-4"></i>
                                    </span>

                                    {{-- A green tick means every copy this pass covers is
                                         already out — the same print_logs state the copy
                                         tiles used to show, put where the choice is made.
                                         Pressing a ticked pass reprints it.

                                         Wrapped, not a bare <i>: lucide swaps the <i> for
                                         an <svg>, so an x-if rooted on the icon itself
                                         leaves Alpine holding a node that is no longer in
                                         the DOM when the state flips. --}}
                                    <template x-if="passPrinted(pass)">
                                        <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded-full bg-green-100 text-green-700 text-[9px] font-black uppercase tracking-wide">
                                            <i data-lucide="check" class="w-2.5 h-2.5"></i>
                                            Printed
                                        </span>
                                    </template>
                                </span>

                                {{-- One line each. A pass whose name wraps reads as two
                                     choices at a glance, which is the opposite of what
                                     this panel is for — so the names are kept short
                                     enough to survive the column, and the run they
                                     belong to is spelled out underneath. --}}
                                <span class="block">
                                    <span class="block text-[13px] font-bold whitespace-nowrap" :class="pass.titleTone"
                                          x-text="pass.label"></span>
                                    <span class="block text-[10.5px] font-medium mt-0.5 whitespace-nowrap" :class="pass.hintTone"
                                          x-text="pass.hint"></span>
                                </span>
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
                                <template x-if="isBatchMode">
                                    <span>Pick a pass for the whole batch. Every letter in it goes through the printer in that pass — the Originals on security paper, the office copies on plain. A tick means every letter in the batch is past that pass; run 2 prints only the letters that still owe it.</span>
                                </template>
                                <template x-if="!isSingleStepType && !isBatchMode && !splitPassesAllowed">
                                    <span>"All Copies" prints this letter's Original, Duplicate and Triplicate in one run. The two split passes are greyed out on purpose: they exist to send a whole <b>batch</b> through the printer twice — every Original on security paper, then every office copy on plain — so they are chosen from the <b>Batches</b> tab, not for one letter. A green tick means that pass is already out; pressing it again reprints. Once the full set is out, "Certified True Copies" can be generated.</span>
                                </template>
                                <template x-if="!isSingleStepType && !isBatchMode && splitPassesAllowed">
                                    <span>Pick a pass above: the full set in one run, the Original alone (security paper), or the Duplicate &amp; Triplicate alone (plain paper) — which is also how a run stopped after the Originals is picked up. A green tick means that pass is already out; pressing it again reprints. Once the full set is out, "Certified True Copies" can be generated.</span>
                                </template>
                            </p>
                        </div>
                    </div>

                    {{-- Where the batch stands, in a sentence. The ticks say which
                         passes are done; this says how many letters that covers. --}}
                    <div x-show="isBatchMode && batchStatusLine" x-cloak
                         class="flex items-center gap-2.5 p-3 rounded-xl bg-slate-50 border border-slate-200">
                        <i data-lucide="layers" class="h-4 w-4 text-slate-400 shrink-0"></i>
                        <p class="text-[11.5px] font-semibold text-slate-600" x-text="batchStatusLine"></p>
                    </div>

                    {{-- A line, not a full-page celebration: the ticks on the passes
                         already say what is out, so this only has to name what that
                         means for the next step. It also said "Originals Printed"
                         while standing for the whole set being out. --}}
                    <div x-show="batchCompleted && !isSingleStepType" x-cloak
                         class="flex items-center gap-3 p-3 rounded-xl bg-green-50 border border-green-200">
                        <span class="flex-shrink-0 w-8 h-8 rounded-full bg-green-100 text-green-700 flex items-center justify-center">
                            <i data-lucide="check-circle" class="h-4 w-4"></i>
                        </span>
                        <p class="text-[12px] font-bold text-green-900">
                            Full set printed
                            <span class="block text-[11px] font-medium text-green-700">Certified True Copies can now be generated.</span>
                        </p>
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

                    {{-- The proofing stage, and FIRST on the board — it is the step
                         that comes first in the work, and a board that lists the
                         official print above it invites the officer to skip it.

                         The two tiles hand off to each other: the proof is live until
                         it has been run, then it closes and the official print opens.
                         Neither is a mode of the other; one costs nothing and can be
                         repeated, the other puts a serial on paper. --}}
                    <button type="button" @click="ossLaunchRecommendationWhiteCopy()"
                        :disabled="!ossGeneratedState.recommendation || ossRecommendationPrinted || ossProofRun"
                        :title="ossRecommendationPrinted
                                    ? 'Already printed — the white copy is a pre-print proof'
                                    : (ossProofRun ? 'White copy already run off — print the recommendation next' : '')"
                        class="flex items-center gap-4 rounded-xl border border-slate-200 bg-slate-50 p-4 text-left transition hover:bg-slate-100 hover:shadow-sm disabled:cursor-not-allowed disabled:opacity-50">
                        <div class="flex-shrink-0 w-10 h-10 rounded-lg bg-slate-200 flex items-center justify-center">
                            <i data-lucide="file-search" class="w-5 h-5 text-slate-700"></i>
                        </div>
                        <div>
                            <p class="text-sm font-bold text-slate-900">1. Recommendation — White Copy</p>
                            <p class="text-xs text-slate-500 mt-0.5"
                               x-text="ossRecommendationPrinted
                                            ? 'Already printed — nothing left to proof'
                                            : (ossProofRun ? 'Proof already run off' : 'Black & white proof for vetting')"></p>
                        </div>
                    </button>

                    {{-- Opens only once the proof has been run. Nothing else on this
                         board says whether the letter was read, and the whole reason
                         the proof exists is that it should be. --}}
                    <button type="button" @click="ossLaunchRecommendation()"
                        :disabled="!ossGeneratedState.recommendation || !(ossProofRun || ossRecommendationPrinted)"
                        :title="!(ossProofRun || ossRecommendationPrinted) ? 'Print and read the white copy first' : ''"
                        class="flex items-center gap-4 rounded-xl border border-blue-200 bg-blue-50 p-4 text-left transition hover:bg-blue-100 hover:shadow-sm disabled:cursor-not-allowed disabled:opacity-50">
                        <div class="flex-shrink-0 w-10 h-10 rounded-lg bg-blue-100 flex items-center justify-center">
                            <i data-lucide="thumbs-up" class="w-5 h-5 text-blue-700"></i>
                        </div>
                        <div>
                            <p class="text-sm font-bold text-blue-900">2. Recommendation</p>
                            <p class="text-xs text-blue-600 mt-0.5"
                               x-text="(ossProofRun || ossRecommendationPrinted) ? ossHints.recommendation : 'Enabled after the white copy is printed'"></p>
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

                    {{-- Only for the documents that have no pass choice to make —
                         a single-copy type. Where the three passes are on show they
                         are the print action, and a fourth button that runs one of
                         them again is the duplication this redesign removed. --}}
                    <button x-show="(!batchCompleted || isSingleStepType) && !isLegalSearchType && !showPasses && !isBatchMode"
                            @click="executeBatchPrint()"
                            :disabled="isPrinting"
                            class="px-6 py-3 text-sm font-bold text-white bg-indigo-600 hover:bg-indigo-700 rounded-xl shadow-xl shadow-indigo-200 transition-all flex items-center gap-2 disabled:opacity-50 active:scale-95">
                        <template x-if="!isPrinting">
                            <span class="flex items-center">
                                <i data-lucide="printer" class="h-4 w-4"></i>
                            </span>
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
                            <span class="flex items-center">
                                <i data-lucide="award" class="h-4 w-4"></i>
                            </span>
                        </template>
                        <template x-if="isPrinting">
                            <span class="w-4 h-4 border-2 border-white/30 border-t-white rounded-full animate-spin"></span>
                        </template>
                        {{-- Named, because "Print" beside three passes that also
                             print says nothing about which of them this is. --}}
                        <span>Print CTC</span>
                    </button>

                    <button x-show="!batchCompleted" 
                            @click="executePrint()" 
                            :disabled="isPrinting"
                            class="px-8 py-3 text-sm font-bold text-white bg-blue-600 hover:bg-blue-700 rounded-xl shadow-xl shadow-blue-200 transition-all flex items-center gap-2 disabled:opacity-50 active:scale-95 hidden">
                        <template x-if="!isPrinting">
                            <span class="flex items-center">
                                <i data-lucide="printer" class="h-4 w-4"></i>
                            </span>
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
        // The row this print belongs to, and the date it is issued as of. Only the
        // land_recommendations family carries a date the manager can write back
        // (see supportsIssueDate) — everything else opens without the panel.
        recordId: null,
        reissuance: '',
        passesAllowed: true,
        // Whether the Original-only / office-copies passes may be pressed. Asked for
        // by the caller, and only the Batches tab asks: see openModal().
        splitPasses: false,
        // Set when the manager is opened for a whole batch rather than one row.
        // Carries the ids, how the batch stands, and the page's own runner — the
        // batch pipeline stays where it is and this only chooses which pass.
        batch: null,
        // Set by a caller whose document has a White Copy stage in front of the
        // official print. Two things follow from it: the date of issue is shown
        // read-only here instead of being editable (it is keyed in on the White
        // Copy), and this is where the manager sends the operator when the record
        // has no date yet.
        whiteCopyUrl: '',
        // Whether the date of issue belongs to the White Copy card. True for the
        // documents that actually print one — the RofO does, the recommendation
        // form has only blank hand-signed date lines — so a proofing stage on its
        // own is not enough to take the field off this dialog.
        whiteCopyOwnsDate: false,
        // Which module's records the Edit writes to. Defaults to the Land RofO
        // endpoint; SLTR keeps its own table and passes its own.
        issueDateUrl: '',
        issueDate: '',
        issueDateOnRecord: '',
        issueDateLocked: false,
        issueDateUnlocked: false,
        issueDateConfirming: false,
        issueDateError: '',
        ossGeneratedState: {
            verification: false,
            acknowledgement: false,
            recommendation: false
        },
        // Whether the recommendation has already been printed officially. Closes
        // the White Copy tile: a proof is read before a document is issued, so one
        // offered for a recommendation already in the applicant's hand is a proof
        // of the past.
        ossRecommendationPrinted: false,
        // Whether the proof has been run off, as the print log records it. Read
        // when the board opens; by itself it only ever catches up on reopening.
        ossWhiteCopyDone: false,
        // The same thing, as this board has just watched it happen. The proof is
        // generated in another tab, so the print_logs row it writes lands after the
        // board has already read its state — waiting for a reopen to notice is what
        // left the officer looking at "Enabled after the white copy is printed"
        // with the proof open beside them. Set the instant the proof is launched,
        // then confirmed against the log; see ossLaunchRecommendationWhiteCopy().
        ossWhiteCopyJustRun: false,
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

            this.recordId = (data && data.recordId) ? data.recordId : null;
            this.reissuance = (data && data.reissuance) ? String(data.reissuance) : '';
            this.whiteCopyUrl = (data && data.whiteCopyUrl) ? String(data.whiteCopyUrl) : '';
            this.whiteCopyOwnsDate = !!(data && data.whiteCopyOwnsDate);
            this.issueDateUrl = (data && data.issueDateUrl)
                ? String(data.issueDateUrl)
                : '{{ route('land-rofos.issue-date') }}';
            this.passesAllowed = !(data && data.passes === false);
            this.batch = (data && data.batch) ? data.batch : null;

            // Splitting a print into two runs is a BATCH operation: run 1 puts every
            // Original on security paper, the tray is reloaded, run 2 puts every
            // office copy on plain paper. One letter has nothing to reload between —
            // splitting it just means two trips for three sheets, and it is how a
            // file ends up with its Original out and its office copies forgotten.
            // So the caller has to ask for the split passes, and only the Batches
            // tab does. Everywhere else the three are shown, to say what a letter
            // consists of, but only "All Copies" can be pressed.
            this.splitPasses = !!(data && data.splitPasses);

            // A date already on the record locks the field; a blank one is simply
            // filled in, which is the ordinary case — date_issued is empty until a
            // letter is actually issued.
            this.issueDateOnRecord = (data && data.issueDate) ? String(data.issueDate) : '';
            // Empty when the record has none. It used to default to today, which
            // is why an undated letter came out carrying a date nobody chose: the
            // field was never blank, so the "enter a date" check never fired and the
            // print silently wrote today. date_issued has no fallback anywhere, and
            // this panel is not allowed to invent one either — the operator types it
            // or the print does not go ahead.
            this.issueDate = this.issueDateOnRecord || '';

            // A batch carries its date on its own letters rather than in
            // data.issueDate, so when they all agree on one that is what Edit opens
            // on — otherwise the operator is asked to retype a date now printed
            // directly above the button. Nothing is sent unless they actually unlock
            // and change it: runBatchPass only forwards the field once
            // issueDateUnlocked is true or a letter is missing a date.
            if (!this.issueDate && this.batchDates.length === 1) {
                this.issueDate = this.batchDates[0];
            }

            this.issueDateLocked = !!this.issueDateOnRecord;
            this.issueDateUnlocked = false;
            this.issueDateConfirming = false;
            this.issueDateError = '';

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
            
            // A batch's state is the batch-print status the caller already read, not
            // this reference's print_logs — one log line per letter, not per batch.
            if (this.isBatchMode) {
                this.printLogs = [];
                this.isOpen = true;
                this.$nextTick(() => lucide.createIcons());
                return;
            }

            await this.checkStatus();
            
            this.isOpen = true;
            this.$nextTick(() => lucide.createIcons());
        },

        get isBatchMode() {
            return !!this.batch;
        },

        // A batch is never in the White Copy flow: a proof is read one letter at a
        // time, and the date panel a batch shows fills the blanks across many rows
        // at once — a different question from "what date does this letter carry".
        get usesWhiteCopy() {
            return !!this.whiteCopyUrl && !this.isBatchMode;
        },

        // Only then is the editable panel replaced by the read-only one. A batch
        // counts as well as a row: the date belongs to the proof either way, and a
        // field here that a batch could still type into would be a second place to
        // set the one thing the proof was read to check.
        get dateOnWhiteCopy() {
            if (!this.whiteCopyOwnsDate) return false;
            return this.isBatchMode || this.usesWhiteCopy;
        },

        get batchCount() {
            return (this.batch && this.batch.count) || 0;
        },

        // Only the letters with no date on record are asked about. The rest keep the
        // date they carry — nothing in a bulk run overwrites a date already issued.
        get batchMissingDates() {
            return (this.batch && this.batch.missingDates) || 0;
        },

        // The distinct dates already on the batch, earliest first. Normally one: a
        // batch is issued on a day. Empty when the caller could not read them, which
        // is why the panel keeps a count-only line behind this.
        get batchDates() {
            return (this.batch && Array.isArray(this.batch.dates)) ? this.batch.dates : [];
        },

        get isOssMode() {
            return this.moduleContext === 'oss';
        },

        // Whether the proof has been run off for the record on the board. Either
        // answer counts: the print log's, and this board's own — it launched the
        // proof itself, or heard the Recommendation Form launch one. The tiles read
        // this rather than the log alone so the hand-off happens while the officer
        // is still looking at it.
        get ossProofRun() {
            return this.ossWhiteCopyDone || this.ossWhiteCopyJustRun;
        },

        get today() {
            const d = new Date();
            return d.getFullYear() + '-'
                + String(d.getMonth() + 1).padStart(2, '0') + '-'
                + String(d.getDate()).padStart(2, '0');
        },

        // The date is stored on land_recommendations.date_issued, so the panel is
        // offered for the documents that live in that table — every Land and OSS
        // recommendation type, RofO and re-issuance included. SLTR keeps its own
        // table and has no such column, so it opens without the panel rather than
        // with one whose Save would go nowhere.
        get supportsIssueDate() {
            // A batch in the White Copy flow always has something to say about the
            // date — "all of them are dated" is an answer worth showing, and it is
            // what tells the operator the passes are unlocked for a good reason.
            if (this.isBatchMode) return this.dateOnWhiteCopy || this.batchMissingDates > 0;
            if (!this.recordId) return false;
            // SLTR used to be excluded here: its table had no date_issued column, so
            // the panel's Save would have gone nowhere. It has one now
            // (2026_08_26_090000_add_date_issued_to_sltr_recommendations), and its
            // letter prints DATE OF ISSUE like any other, so it is in.
            return String(this.docType).includes('RofO')
                || String(this.docType).includes('Recommendation');
        },

        // Whether the Original-only / office-copies passes can be chosen at all.
        get splitPassesAllowed() {
            return this.splitPasses;
        },

        passEnabled(pass) {
            if (this.issueDateOutstanding) return false;
            return pass.key === 'Batch' || this.splitPassesAllowed;
        },

        passDisabledReason(pass) {
            if (this.issueDateOutstanding) {
                if (!this.dateOnWhiteCopy) {
                    return 'Enter the date issued first — it prints on the letter as '
                         + 'DATE OF ISSUE and nothing else supplies it.';
                }
                return this.isBatchMode
                    ? this.batchMissingDates + ' of these letters have no date of issue. It is '
                      + 'entered on the White copy, so it is read back before anything is printed.'
                    : 'This letter has no date of issue. It is entered on the White '
                      + 'Copy, so it is read back before anything is printed.';
            }
            if (this.passEnabled(pass)) return '';
            return 'Splitting the run into Originals and office copies is available '
                 + 'on the Batches tab, where a whole batch goes through the printer '
                 + 'in one pass. For a single letter, print all three copies.';
        },

        // Three passes only where there are three copies to split.
        get showPasses() {
            if (this.isBatchMode) return true;
            return this.passesAllowed && !this.isSingleStepType && !this.isLegalSearchType;
        },

        get passOptions() {
            return [
                {
                    key: 'Batch',
                    label: 'All Copies',
                    hint: 'Original, Duplicate & Triplicate',
                    copies: ['Original', 'Duplicate', 'Triplicate'],
                    icon: 'printer',
                    tone: 'border-violet-200 bg-white hover:bg-violet-50 hover:border-violet-400 hover:shadow-sm',
                    iconTone: 'bg-violet-600 text-white',
                    titleTone: 'text-violet-900',
                    hintTone: 'text-violet-600'
                },
                {
                    key: 'Original',
                    label: 'Original Only',
                    hint: 'Run 1 — security paper',
                    copies: ['Original'],
                    icon: 'award',
                    tone: 'border-emerald-200 bg-white hover:bg-emerald-50 hover:border-emerald-400 hover:shadow-sm',
                    iconTone: 'bg-emerald-600 text-white',
                    titleTone: 'text-emerald-900',
                    hintTone: 'text-emerald-600'
                },
                {
                    key: 'Office',
                    label: 'Duplicate & Triplicate',
                    hint: 'Run 2 — plain paper',
                    copies: ['Duplicate', 'Triplicate'],
                    icon: 'copy',
                    tone: 'border-teal-200 bg-white hover:bg-teal-50 hover:border-teal-400 hover:shadow-sm',
                    iconTone: 'bg-teal-600 text-white',
                    titleTone: 'text-teal-900',
                    hintTone: 'text-teal-600'
                }
            ];
        },

        // Ticked only when every copy the pass covers has been logged — a pass
        // that is half done still reads as outstanding, which is the state an
        // operator picking up an abandoned run needs to see.
        passPrinted(pass) {
            if (this.isBatchMode) return this.batchPassPrinted(pass);
            if (!this.printLogs || !pass || !pass.copies) return false;
            return pass.copies.every(copy => this.isCompleted(copy));
        },

        // A batch is only ticked when EVERY letter in it is past that pass — one
        // file still owing its office copies keeps run 2 outstanding, which is the
        // whole reason the batch can be resumed.
        batchPassPrinted(pass) {
            const st = this.batch && this.batch.status;
            if (!st || !st.total) return false;

            if (pass.key === 'Original') {
                // Originals are out for every letter once none is still not-started.
                return Number(st.not_started || 0) === 0;
            }
            // 'Batch' and 'Office' both mean the full set is on paper.
            return Number(st.complete || 0) === Number(st.total || 0);
        },

        // What the batch still owes, said plainly under the passes.
        get batchStatusLine() {
            const st = this.batch && this.batch.status;
            if (!st || !st.total) return '';
            const total = Number(st.total || 0);
            const awaiting = Number(st.awaiting_office || 0);
            const complete = Number(st.complete || 0);

            if (complete === total) return 'All ' + total + ' letters fully printed.';
            if (awaiting > 0) {
                return awaiting + ' of ' + total + ' had Originals printed and still owe their Duplicate & Triplicate.';
            }
            return total + ' letters ready — nothing printed yet.';
        },

        // Closes the manager and hands the record back to the White Copy card, which
        // is where the date is entered. Dispatched rather than called directly so
        // the manager stays a component that knows nothing about the page it was
        // opened from.
        goToWhiteCopy() {
            // A batch's proof is a form post over a list of ids, so the page that
            // opened the manager keeps that pipeline and hands it back here.
            if (this.isBatchMode) {
                const run = this.batch && this.batch.onWhiteCopy;
                this.closeModal();
                if (typeof run === 'function') run();
                return;
            }

            const detail = {
                recordId: this.recordId,
                ref: this.refNumber,
                url: this.whiteCopyUrl,
                issueDate: this.issueDate || '',
                issueDateUrl: this.issueDateUrl
            };
            this.closeModal();
            window.dispatchEvent(new CustomEvent('open-white-copy', { detail }));
        },

        unlockIssueDate() {
            this.issueDateUnlocked = true;
            this.issueDateConfirming = false;
        },

        // A pass cannot be pressed while the date is outstanding. The tiles are the
        // only way to print from here, so this is the gate — the checks in
        // persistIssueDate() and runBatchPass() stay as the backstop.
        get issueDateOutstanding() {
            if (!this.supportsIssueDate) return false;
            // A date typed into the unlocked field counts: the operator is correcting
            // it here, which is the one edit this panel allows. Without that, an
            // unlocked field could never satisfy the very rule it exists to satisfy.
            if (this.issueDateUnlocked) return !this.issueDate;
            // Otherwise the plain fact: any letter without a date blocks the run.
            if (this.isBatchMode) return this.batchMissingDates > 0;
            return !this.issueDate;
        },

        // Written to the record before the letter is opened, not carried on the
        // print URL: a reprint has to come out carrying the same date as the copy
        // already in the file.
        //
        // Returns false when the operator has left the field empty — the print does
        // not go ahead, because a letter with no date of issue is not a letter.
        // 'all' is sent only for a confirmed edit, so a date already on record is
        // never replaced by accident.
        async persistIssueDate() {
            this.issueDateError = '';
            if (!this.supportsIssueDate) return true;

            if (!this.issueDate) {
                this.issueDateError = this.dateOnWhiteCopy
                    ? 'This letter has no date of issue — set it on the White Copy first.'
                    : 'Enter the date of issue before printing.';
                return false;
            }

            // In the White Copy flow the date reaching here came off the record and
            // was written by the White Copy card. There is nothing to save, and
            // saving anyway would let the printer rewrite a date nobody proofread —
            // unless the operator has explicitly unlocked the field to correct it,
            // which is the one edit this panel allows and must therefore persist.
            if (this.dateOnWhiteCopy && !this.issueDateUnlocked) return true;

            // Locked and untouched: the record already holds this date, nothing to write.
            if (this.issueDateLocked && !this.issueDateUnlocked) return true;

            try {
                const response = await fetch(this.issueDateUrl, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: JSON.stringify({
                        ids: [this.recordId],
                        issue_date: this.issueDate,
                        issue_date_apply: this.issueDateUnlocked ? 'all' : 'missing'
                    })
                });

                // fetch() resolves for HTTP 4xx/5xx responses. Treating those as a
                // successful save made the manager open the print template with the
                // old date still in the database, while the dialog showed the newly
                // typed one. Do not print until the server has confirmed the write.
                if (!response.ok) {
                    throw new Error('Date of issue save failed with HTTP ' + response.status);
                }

                const result = await response.json();
                if (!result || result.success !== true) {
                    throw new Error('Date of issue was not saved.');
                }

                this.issueDateOnRecord = this.issueDate;
                this.issueDateLocked = true;
                this.issueDateUnlocked = false;
                return true;
            } catch (error) {
                // The date is the point of the panel, so a failed write stops the
                // print rather than letting an undated letter out.
                this.issueDateError = 'Could not save the date of issue. Check your connection and try again.';
                return false;
            }
        },

        // Hands the chosen pass back to the page that opened the manager. The date
        // is not written here: it travels with the print and the server writes it to
        // the letters that have none as it renders them.
        runBatchPass(passKey) {
            const copies = { 'Batch': 'all', 'Original': 'original', 'Office': 'office' }[passKey];
            if (!copies || !this.batch || typeof this.batch.onPass !== 'function') return;

            // Same rule as a single letter: if any letter in this batch has no
            // date of issue, one has to be entered before the run goes ahead. It
            // fills those blanks only — a letter already carrying a date keeps it.
            if (this.batchMissingDates > 0 && !this.issueDate) {
                this.issueDateError = this.dateOnWhiteCopy
                    ? this.batchMissingDates + ' of these letters have no date of issue. '
                      + 'Set it on the White copy first.'
                    : 'Enter the date of issue before printing — '
                      + this.batchMissingDates + ' of these letters have none.';
                return;
            }

            // Only ever sent with a real date behind it. Where the White Copy owns
            // the date and nothing has been unlocked, there is no field here to
            // read: this carries nothing and each letter prints the date on its own
            // record.
            //
            // 'all' for a confirmed edit — the operator was told it replaces every
            // date in the batch — and 'missing' otherwise, so one answer given for
            // many files can never overwrite a date a letter already carries.
            const extras = {};
            if (this.issueDate && (this.issueDateUnlocked || this.batchMissingDates > 0)) {
                extras.issue_date = this.issueDate;
                extras.issue_date_apply = this.issueDateUnlocked ? 'all' : 'missing';
            }

            const run = this.batch.onPass;
            this.closeModal();
            run(copies, extras);
        },

        // One pass onto paper. 'Batch' is the whole set, 'Original' and 'Office' the
        // two halves of a split run; the template reads the same names off ?status=,
        // and the log records exactly the copies that were sent.
        async runPass(passKey) {
            if (this.isPrinting) return;

            // A batch keeps its own pipeline — this only names the pass. Nothing is
            // awaited before it runs, because that pipeline claims the print tab
            // synchronously inside the click and a pop-up blocker stops a tab
            // opened after an await.
            if (this.isBatchMode) {
                this.runBatchPass(passKey);
                return;
            }

            if (!(await this.persistIssueDate())) return;

            this.isPrinting = true;
            const statuses = {
                'Batch':    ['Original', 'Duplicate', 'Triplicate'],
                'Original': ['Original'],
                'Office':   ['Duplicate', 'Triplicate']
            }[passKey];

            try {
                const sep = this.printUrl.includes('?') ? '&' : '?';
                window.open(`${this.printUrl}${sep}status=${passKey}`, '_blank');

                await fetch('{{ route('print-manager.batch-log') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: JSON.stringify({
                        reference_number: this.refNumber,
                        document_type: this.docType,
                        statuses: statuses,
                        print_type: 'Batch'
                    })
                });

                await this.checkStatus();

                Swal.fire({
                    title: 'Sent to Print',
                    text: statuses.join(', ') + ' logged.',
                    icon: 'success',
                    toast: true,
                    position: 'top-end',
                    showConfirmButton: false,
                    timer: 3000
                });
            } catch (error) {
                Swal.fire('Error', 'Failed to log the print', 'error');
            } finally {
                this.isPrinting = false;
                this.$nextTick(() => lucide.createIcons());
            }
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
            // These belong to the record, not to the board. Left standing they are
            // the previous file's answer to this file's question — and the one they
            // gate is the official print.
            this.ossRecommendationPrinted = false;
            this.ossWhiteCopyDone = false;
            this.ossWhiteCopyJustRun = false;
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
                    this.ossRecommendationPrinted = !!(ready && result.data.printed);
                    this.ossWhiteCopyDone = !!(ready && result.data.white_copy_done);
                    if (ready && this.moduleRecord) {
                        this.moduleRecord.recommendation_id = result.data.id || null;
                    }
                    this.ossHints.recommendation = ready ? 'View & print recommendation' : 'Enabled after recommendation is generated';
                } catch (_) {
                    this.ossGeneratedState.recommendation = false;
                    // Unknown is not the same as printed: a status that could not be
                    // read must not silently close off the proof.
                    this.ossRecommendationPrinted = false;
                    this.ossWhiteCopyDone = false;
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

            // Official paper, so it stands behind the same question every other
            // official print does. Answering "no" opens the proof instead.
            //
            // The gate lives in the white-copy component, which sits beside this one
            // in layouts/app. (Written out rather than named as a tag: Blade compiles
            // component tags anywhere in the file, comments and script bodies
            // included, so writing the tag here renders the whole component into the
            // middle of this script.)
            // If a page ever renders one without the other, the print
            // still goes ahead rather than dying on an undefined — a missing question
            // must not become a missing document.
            this.closeModal();
            if (!window.WhiteCopy || typeof window.WhiteCopy.confirmProofread !== 'function') {
                window.open('/land-recommendations/' + recId + '/print', '_blank');
                return;
            }
            window.WhiteCopy.confirmProofread({
                subject: this.refNumber || 'this recommendation',
                onYes:       () => window.WhiteCopy.openTab('/land-recommendations/' + recId + '/print'),
                onWhiteCopy: () => window.WhiteCopy.openTab('/land-recommendations/' + recId + '/white-copy')
            });
        },

        // The proof of the same document: no arms, no QR, no serial, no signature
        // blocks and no acknowledgement sheet, marked WHITE COPY. It mints nothing
        // and logs nothing, so it is not asked to confirm anything first.
        ossLaunchRecommendationWhiteCopy() {
            if (!this.moduleRecord) return;
            if (!this.ossGeneratedState.recommendation) {
                Swal.fire({ icon: 'info', title: 'Recommendation Not Generated', text: 'Generate recommendation first, then use this print option.' });
                return;
            }
            // The tile is already disabled for this; checked again because a
            // disabled button is a hint, not a rule.
            if (this.ossRecommendationPrinted) {
                Swal.fire({
                    icon: 'info',
                    title: 'Already printed',
                    text: 'This recommendation has been printed. The white copy is a proof read before printing, so there is nothing left for it to check.'
                });
                return;
            }
            if (this.ossProofRun) {
                Swal.fire({
                    icon: 'info',
                    title: 'White copy already run off',
                    text: 'The proof for this recommendation has been printed. Read it, and print the recommendation itself when it is right.'
                });
                return;
            }

            const recId = String(this.moduleRecord.recommendation_id || '').trim();
            if (!recId) {
                Swal.fire({ icon: 'warning', title: 'Missing Recommendation', text: 'Could not resolve recommendation record.' });
                return;
            }

            // The board stays open, and the official tile beside this one opens as
            // the proof goes. These two tiles are one hand-off — closing the board
            // to run the proof meant reopening it to reach the step the proof was
            // run for, and a board reopened before the other tab had finished
            // writing its log came back still saying "Enabled after the white copy
            // is printed".
            //
            // Launched through WhiteCopy.openTab: a synthesised anchor click, which
            // browsers treat as navigation rather than as the pop-up a bare
            // window.open() can look like.
            this.ossWhiteCopyJustRun = true;
            if (window.WhiteCopy && typeof window.WhiteCopy.openTab === 'function') {
                window.WhiteCopy.openTab('/land-recommendations/' + recId + '/white-copy');
            } else {
                window.open('/land-recommendations/' + recId + '/white-copy', '_blank');
            }

            // Then the log is asked, so the board is telling the truth rather than
            // only being quick. The proof is written by the other tab's request, so
            // the row is not there the instant this one returns — hence the delay,
            // and hence a confirmation that can only ever agree: a log not yet
            // written must not shut the tile it just opened.
            this.ossConfirmWhiteCopyLogged();
        },

        // Re-read the OSS states until the print log shows the proof, without ever
        // standing the tile back down. Stops as soon as the log agrees.
        ossConfirmWhiteCopyLogged(attempt = 0) {
            if (!this.isOssMode || this.ossWhiteCopyDone || attempt >= 4) return;

            window.setTimeout(async () => {
                // The board may have been closed or moved to another record while
                // this was waiting.
                if (!this.isOpen || !this.isOssMode || !this.ossWhiteCopyJustRun) return;

                await this.loadOssGeneratedStates();
                this.ossConfirmWhiteCopyLogged(attempt + 1);
            }, 1500 * (attempt + 1));
        },

        // A proof run from somewhere else on the page — the Recommendation Form's
        // own "Print White Copy" button posts the card's live fields and logs the
        // same proof this board's tile does. If the board is open on that file, the
        // official tile opens here too rather than waiting for a reopen.
        onWhiteCopyGenerated(detail) {
            if (!this.isOpen || !this.isOssMode) return;

            const fileRef = String((detail && detail.fileRef) || '').trim().toUpperCase();
            if (!fileRef || fileRef !== this.getOssFileReference().toUpperCase()) return;

            this.ossWhiteCopyJustRun = true;
            this.ossConfirmWhiteCopyLogged();
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
            // A single copy run off the tiles is still a print of this letter, so it
            // carries the same date of issue as the passes below it.
            if (!(await this.persistIssueDate())) return;
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
            if (!(await this.persistIssueDate())) return;
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

        async executeCTCPrint() {
            if (this.isPrinting) return;

            // CTC uses the same RofO template as every other print. It must save a
            // confirmed Date Issued first; previously this path skipped
            // persistIssueDate(), so the template read the older date_issued value.
            if (!(await this.persistIssueDate())) return;

            this.isPrinting = true;

            // CTC prints are now batch of 3 (Original, Duplicate, Triplicate)
            try {
                const _sep4 = this.printUrl.includes('?') ? '&' : '?';
                window.open(`${this.printUrl}${_sep4}status=Batch&isCTC=1`, '_blank');

                Swal.fire({
                    title: 'Certified True Copy Batch',
                    text: 'Generating CTC batch...',
                    icon: 'info',
                    toast: true,
                    position: 'top-end',
                    showConfirmButton: false,
                    timer: 3000
                });
            } finally {
                this.isPrinting = false;
            }
        }
    }));
});
</script>
