{{-- ═══════════════════════ Print White Copy ═══════════════════════
     The proofing stage that sits in front of the Print Manager.

     A white copy is a draft of a document, run off on ordinary paper in black and
     white, for an officer to read against the record before anything is committed
     to security stock. Everything about it is deliberately separate from the
     official print:

       * its own route per document, so no official print URL can render a proof
         and no proof URL can render an official copy;
       * its own state — which is to say none. Generating one mints no security
         serial, writes no print_logs row, increments no print count and moves
         nothing onto a Printed tab. The proof can therefore be run again after
         every correction, as many times as the record needs;
       * its own answer to "is this approved" — none. Printing a proof says only
         that paper came out of a printer. Whether it was read, and whether it was
         found correct, is asked at the Print Manager, of the person who read it.

     Two things live here. The card that opens a proof (and, where the document
     prints a date of issue, takes that date), and the gate in front of the Print
     Manager. Both are global rather than per-page because the second document to
     want them was already the second copy of them.

     Opened directly:
         WhiteCopy.open({ recordId, ref, url, issueDate, ownsDate })
     or through the Print Manager, which dispatches `open-white-copy` when a letter
     it has been asked to print has no date of issue yet.

     The gate:
         WhiteCopy.openPrintManager(ref, type, url, options)
     in place of SmartPrintManager.open() wherever a document has a proofing stage.
--}}

<div id="whiteCopyModal" class="fixed inset-0 z-[1000095] hidden bg-slate-900/70 p-4 overflow-y-auto">
    <div class="min-h-full flex items-center justify-center">
        <div class="bg-white w-full max-w-lg rounded-2xl shadow-2xl overflow-hidden">
            <div class="px-6 py-4 bg-gradient-to-r from-slate-800 to-slate-700 flex items-center gap-3">
                <i data-lucide="file-search" class="h-5 w-5 text-slate-300"></i>
                <div class="min-w-0">
                    <h3 class="text-white font-bold text-lg leading-tight">Generate White Copy</h3>
                    <p class="text-slate-300 text-[11px] font-semibold uppercase tracking-widest truncate">
                        <span id="whiteCopyFile" class="font-mono"></span>
                        <span class="text-slate-400"> · proof for vetting</span>
                    </p>
                </div>
                <button type="button" onclick="closeWhiteCopyModal()"
                        class="ml-auto p-2 text-slate-300 hover:text-white hover:bg-white/10 rounded-full transition">
                    <i data-lucide="x" class="h-5 w-5"></i>
                </button>
            </div>

            <div class="p-6 space-y-5">
                <div class="flex gap-2.5 p-3.5 rounded-xl bg-slate-50 border border-slate-200">
                    <i data-lucide="info" class="h-4 w-4 text-slate-400 shrink-0 mt-0.5"></i>
                    <p class="text-[12px] text-slate-600 font-medium leading-relaxed">
                        A draft of the document on ordinary white paper, in black and white. It carries no
                        coat of arms, no QR, no serial number and no signature block, and it is marked
                        <b>WHITE COPY</b> — it is not an issued copy and cannot stand in for one.
                    </p>
                </div>

                {{-- Shown only for the documents that actually print a date of issue —
                     the RofO does, the recommendation form does not. It is asked for
                     here rather than at the Print Manager because it prints on the
                     letter: it is part of what is being proofread, and asking for it
                     at the printer left the one field still open to change as the one
                     field nobody had read on paper.

                     Blank until someone enters it. date_issued has no fallback
                     anywhere, so a letter that has never been issued has no date, and
                     a proof that invents one proofreads as correct and prints as
                     wrong. --}}
                <div id="whiteCopyDateSection" class="space-y-2">
                    <div class="flex items-center justify-between gap-3">
                        <label class="text-[11px] font-black uppercase tracking-widest text-slate-500">
                            Date Issued <span id="whiteCopyDateRequired" class="text-red-500">*</span>
                        </label>
                        <span id="whiteCopyDateOnRecord"
                              class="hidden text-[10px] font-bold text-slate-400 uppercase tracking-wider">On record</span>
                    </div>
                    {{-- Entered here only when the record has none. Once a date is on
                         record it is shown but not edited: changing it belongs to the
                         Print Manager, which is the last thing looked at before paper
                         is spent. Two places to change one field is how a date gets
                         corrected on the proof and quietly changed back afterwards. --}}
                    <input type="date" id="whiteCopyDate"
                           class="w-full px-3 py-2 bg-white rounded-lg text-sm font-semibold text-slate-800 outline-none focus:ring-2 focus:ring-slate-500 border border-slate-300">
                    <div id="whiteCopyDateFixed" class="hidden">
                        <p id="whiteCopyDateFixedValue" class="text-[15px] font-black text-slate-800 font-mono"></p>
                        <p id="whiteCopyDateFixedNote" class="hidden text-[10px] font-black uppercase tracking-wider text-slate-400 mt-0.5"></p>
                    </div>
                    <p id="whiteCopyDateHelp" class="text-[11px] text-slate-500 leading-relaxed">
                        Saved to the record and printed on the white copy as <b>DATE OF ISSUE</b>. The official
                        print then carries the same date.
                    </p>
                    <p id="whiteCopyDateFixedHelp" class="hidden text-[11px] text-slate-500 leading-relaxed">
                        Already on record, and printed on the white copy as <b>DATE OF ISSUE</b>. To change it,
                        use <b>Edit</b> on the Print Manager.
                    </p>
                    {{-- A batch fills the blanks only. A letter already carrying a date
                         keeps it: that is the date on the copy that went out, and one
                         answer given for many files must never overwrite it. --}}
                    <p id="whiteCopyDateBatchHelp" class="hidden text-[11px] text-slate-500 leading-relaxed">
                        <b><span id="whiteCopyMissingCount"></span></b> of
                        <b><span id="whiteCopyBatchCount"></span></b> letters have no date of issue on record.
                        This date is written to <b>those only</b> — the rest keep the date they already carry —
                        and prints on each white copy as <b>DATE OF ISSUE</b>.
                    </p>
                </div>

                <p id="whiteCopyError" class="hidden text-[11.5px] font-bold text-red-600"></p>

                <div class="flex gap-2.5 p-3.5 rounded-xl bg-amber-50 border border-amber-200">
                    <i data-lucide="alert-triangle" class="h-4 w-4 text-amber-600 shrink-0 mt-0.5"></i>
                    <p class="text-[12px] text-amber-900 font-semibold leading-relaxed">
                        Printing a white copy does not mark this record as printed and does not approve it.
                        Correct the record and print another as many times as you need — then confirm the
                        proofreading at the Print Manager.
                    </p>
                </div>
            </div>

            <div class="px-6 py-4 bg-slate-50 border-t border-slate-100 flex items-center gap-3">
                <button type="button" onclick="closeWhiteCopyModal()"
                        class="px-4 py-2 text-sm font-bold text-slate-600 hover:text-slate-900 transition">Cancel</button>
                <button type="button" id="whiteCopyGenerate" onclick="generateWhiteCopy()"
                        class="ml-auto px-5 py-2.5 rounded-xl bg-slate-800 text-white text-sm font-bold hover:bg-slate-900 disabled:opacity-40 disabled:cursor-not-allowed transition inline-flex items-center gap-2">
                    <i data-lucide="printer" class="h-4 w-4"></i>
                    Generate White Copy
                </button>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    // `onGenerate` is what makes this card work for a batch as well as a row. A
    // batch keeps its own print pipeline — a form post carrying a list of ids — so
    // the card only collects the date and hands it back; it does not learn how to
    // print anything.
    // `issueDateUrl` is which module's records the date is written to. It defaults
    // to the Land RofO endpoint because that is where this card started, but the
    // card is shared and the endpoint is not: SLTR keeps its own table, so a date
    // entered on an SLTR proof and posted to the land route would be written to a
    // land record — silently, and to whichever ids happened to match.
    var state = { id: null, url: '', ownsDate: false, download: false, onGenerate: null, count: 0,
                  missing: 0, dateFixed: false, issueDateUrl: '', dates: [] };
    var DEFAULT_ISSUE_DATE_URL = '{{ route('land-rofos.issue-date') }}';

    function csrf() {
        var el = document.querySelector('meta[name="csrf-token"]');
        return el ? el.getAttribute('content') : '';
    }

    function escHtml(v) {
        return String(v == null ? '' : v)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    // opts.ownsDate — this document prints a DATE OF ISSUE, so the date is taken
    // here and written before the proof opens. Without it the card is only
    // "generate the proof", which is all a document with no printed date needs.
    function open(opts) {
        opts = opts || {};
        state.id         = opts.recordId || null;
        state.url        = opts.url || '';
        state.ownsDate   = !!opts.ownsDate;
        state.download   = !!opts.download;
        state.onGenerate = (typeof opts.onGenerate === 'function') ? opts.onGenerate : null;
        state.issueDateUrl = opts.issueDateUrl || DEFAULT_ISSUE_DATE_URL;
        state.count      = opts.count || 0;
        state.missing    = opts.missingDates || 0;
        // The distinct dates a batch's letters already carry, earliest first. The
        // caller may not know them (the lookup can fail), which is why the line
        // below still has a count-only form to fall back to.
        state.dates      = Array.isArray(opts.dates) ? opts.dates : [];

        var isBatch = state.count > 0;

        document.getElementById('whiteCopyFile').textContent = opts.ref || '';
        document.getElementById('whiteCopyError').classList.add('hidden');
        document.getElementById('whiteCopyDateSection').classList.toggle('hidden', !state.ownsDate);

        var issueDate = opts.issueDate || '';

        // Whether there is still a date to ask for. A row that already carries one,
        // or a batch whose letters all do, has nothing to enter here — the date is
        // shown and the Print Manager's Edit is where it changes.
        var onRecord = isBatch ? (state.missing === 0) : !!issueDate;
        state.dateFixed = state.ownsDate && onRecord;

        document.getElementById('whiteCopyDate').classList.toggle('hidden', state.dateFixed);
        document.getElementById('whiteCopyDateFixed').classList.toggle('hidden', !state.dateFixed);
        // What the letters will print. A batch is normally issued on one day, so
        // there is usually a single date to show — and showing it is the point of
        // the proof: the date is one of the things being read back on paper.
        // Letters issued on different days show the spread instead.
        document.getElementById('whiteCopyDateFixedValue').textContent =
            !isBatch
                ? issueDate
                : (state.dates.length === 1
                    ? state.dates[0]
                    : (state.dates.length > 1
                        ? state.dates[0] + ' — ' + state.dates[state.dates.length - 1]
                        : 'All ' + state.count + ' letters are dated.'));

        // Named under the date, so a single date does not read as one letter's and
        // a spread does not read as a typo.
        var fixedNote = document.getElementById('whiteCopyDateFixedNote');
        fixedNote.classList.toggle('hidden', !isBatch || state.dates.length === 0);
        fixedNote.textContent = state.dates.length === 1
            ? 'All ' + state.count + ' letters carry this date.'
            : state.dates.length + ' different dates across ' + state.count + ' letters.';

        document.getElementById('whiteCopyDateHelp').classList.toggle('hidden', isBatch || state.dateFixed);
        document.getElementById('whiteCopyDateBatchHelp').classList.toggle('hidden', !isBatch || state.dateFixed);
        document.getElementById('whiteCopyDateFixedHelp').classList.toggle('hidden', !state.dateFixed);
        if (isBatch) {
            document.getElementById('whiteCopyBatchCount').textContent = state.count;
            document.getElementById('whiteCopyMissingCount').textContent = state.missing;
        }

        document.getElementById('whiteCopyDateOnRecord').classList.toggle('hidden', !onRecord);
        document.getElementById('whiteCopyDateRequired').classList.toggle('hidden', onRecord);

        // Every native date input is wrapped by flatpickr (see
        // admin/header.blade.php), which draws a DD/MM/YYYY field of its own in
        // front of it. Assigning .value updates the hidden original and leaves the
        // visible field showing the last date — so the picker is told, not the input.
        var field = document.getElementById('whiteCopyDate');
        if (field._flatpickr) {
            if (issueDate) { field._flatpickr.setDate(issueDate, false); } else { field._flatpickr.clear(); }
        } else {
            field.value = issueDate;
        }

        // After the value is in the field, so an existing date enables the button and
        // a blank one leaves it dead. Late enough to catch flatpickr's own input,
        // which is created on the first open of a page.
        syncGenerateEnabled();
        var field2 = document.getElementById('whiteCopyDate');
        if (field2 && field2._flatpickr && field2._flatpickr.config
            && field2._flatpickr.config.onChange.indexOf(syncGenerateEnabled) === -1) {
            field2._flatpickr.config.onChange.push(syncGenerateEnabled);
        }

        document.getElementById('whiteCopyModal').classList.remove('hidden');
        if (window.lucide) window.lucide.createIcons();
    }

    // A synthesised anchor click rather than window.open(). Browsers treat this as
    // navigation; a window.open() fired from a promise callback — after a dialog has
    // been answered — is far enough from the original click to read as a pop-up and
    // be blocked. Every "open the proof" path that runs after a dialog uses this.
    function openTab(url) {
        if (!url) return;
        var a = document.createElement('a');
        a.href = url;
        a.target = '_blank';
        a.rel = 'noopener';
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
    }

    // Whether there is anything to generate yet. A document that prints a DATE OF
    // ISSUE cannot be proofread without one — the date is part of what is being
    // read — so the button is dead until the field is filled rather than letting an
    // undated proof out and failing on the far side of a click.
    //
    // A batch whose letters all carry a date has nothing left to ask for, so it is
    // ready with the field empty; a batch still missing some is not.
    function dateReady() {
        if (!state.ownsDate) return true;
        if (state.dateFixed) return true;
        if (state.count > 0 && state.missing === 0) return true;

        var field = document.getElementById('whiteCopyDate');
        return !!(field && (field.value || '').trim());
    }

    function syncGenerateEnabled() {
        var btn = document.getElementById('whiteCopyGenerate');
        if (btn) btn.disabled = !dateReady();
    }

    // The field is wrapped by flatpickr, which draws its own visible input in front
    // of the real one. Picking a date there fires `change` on the original, but not
    // `input` — so both are listened for, and flatpickr's own hook is registered as
    // well where the picker exists. Any one of the three is enough; together they
    // cover a typed date, a picked date and a cleared one.
    (function watchDateField() {
        var field = document.getElementById('whiteCopyDate');
        if (!field) return;
        ['change', 'input', 'blur'].forEach(function (evt) {
            field.addEventListener(evt, syncGenerateEnabled);
        });
        if (field._flatpickr && field._flatpickr.config) {
            field._flatpickr.config.onChange.push(syncGenerateEnabled);
        }
    })();

    function close() {
        document.getElementById('whiteCopyModal').classList.add('hidden');
        state.id  = null;
        state.url = '';
    }

    function fail(message) {
        var err = document.getElementById('whiteCopyError');
        err.textContent = message;
        err.classList.remove('hidden');
    }

    // The row menus are drawn on the server from the print log: once a proof is
    // logged, Generate White Copy greys out and the Print Manager opens. The page
    // on screen was drawn before this proof, so it is reloaded to pick that up —
    // otherwise the menu goes on offering a proof that has already been run off.
    // Delayed so the download (or the new tab) is under way first.
    function refreshAfterProof() {
        if (window.Swal) {
            Swal.fire({
                icon: 'success',
                title: 'White copy generated',
                text: 'Updating the register…',
                timer: 1500,
                showConfirmButton: false,
                allowOutsideClick: false
            });
        }
        setTimeout(function () { window.location.reload(); }, 1500);
    }

    // Hands the proof over. Pages whose white copy route returns a PDF (opts.download)
    // fetch it and save it as a file, keeping the card open with a busy button while
    // it is generated. Anything else — or a server that answered with the print page
    // instead of a PDF — opens in a new tab as before.
    function deliver(url) {
        if (!state.download) {
            close();
            window.open(url, '_blank');
            refreshAfterProof();
            return;
        }

        var btn = document.getElementById('whiteCopyGenerate');
        var label = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<svg class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none">'
            + '<circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" opacity=".25"></circle>'
            + '<path d="M22 12a10 10 0 0 1-10 10" stroke="currentColor" stroke-width="3"></path></svg> Generating PDF…';

        function restore() {
            btn.innerHTML = label;
            syncGenerateEnabled();
            if (window.lucide) window.lucide.createIcons();
        }

        fetch(url, { credentials: 'same-origin' })
            .then(function (r) {
                var type = r.headers.get('Content-Type') || '';
                if (!r.ok) throw new Error('The white copy could not be generated (' + r.status + ').');
                if (type.indexOf('application/pdf') === -1) return null;

                var disposition = r.headers.get('Content-Disposition') || '';
                var match = disposition.match(/filename="?([^";]+)"?/i);
                return r.blob().then(function (blob) {
                    return { blob: blob, name: match ? match[1] : 'White-Copy.pdf' };
                });
            })
            .then(function (file) {
                restore();
                close();
                if (!file) { openTab(url); return; }

                var href = URL.createObjectURL(file.blob);
                var a = document.createElement('a');
                a.href = href;
                a.download = file.name;
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);
                setTimeout(function () { URL.revokeObjectURL(href); }, 60000);
                refreshAfterProof();
            })
            .catch(function (e) {
                restore();
                fail(e.message || 'The white copy could not be generated. Try again.');
            });
    }

    // Saves the date, then opens the proof. The save comes first and the proof only
    // opens if it succeeded: a white copy printed with a date the record does not
    // hold is a proof of a document that will not print that way.
    function generate() {
        if (!state.url && !state.onGenerate) return;

        var btn = document.getElementById('whiteCopyGenerate');
        document.getElementById('whiteCopyError').classList.add('hidden');

        var isBatch = state.count > 0;
        // Nothing is read off the field when the date is fixed: the record already
        // holds it, and this card is not the place it changes.
        var date = (state.ownsDate && !state.dateFixed)
            ? (document.getElementById('whiteCopyDate').value || '').trim()
            : '';

        if (state.ownsDate && !state.dateFixed && !date) {
            // A batch whose letters all carry a date has nothing to ask for, so an
            // empty field there is not an omission — it is the ordinary case.
            if (!isBatch || state.missing > 0) {
                fail('Enter the date of issue — it prints on the document as DATE OF ISSUE '
                    + 'and nothing else supplies it.');
                return;
            }
        }

        // A batch hands the date back to its own pipeline, which carries it on the
        // print request; the server writes it to the letters that have none as it
        // renders them. Nothing is posted from here.
        if (state.onGenerate) {
            var run = state.onGenerate;
            close();
            run(date);
            return;
        }

        // Nothing to save — either the document carries no date of issue at all, or
        // the record already holds the one it will print.
        if (!state.ownsDate || state.dateFixed) {
            deliver(state.url);
            return;
        }

        btn.disabled = true;

        // 'all' on purpose: this card IS the place the date is decided, so an
        // operator correcting a date between two proofs has to be able to change one
        // already on record. Nothing has been issued yet — that is what a proof is for.
        fetch(state.issueDateUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf(), 'Accept': 'application/json' },
            body: JSON.stringify({ ids: [state.id], issue_date: date, issue_date_apply: 'all' })
        })
        .then(function (r) { return r.json().then(function (d) { return { ok: r.ok, data: d }; }); })
        .then(function (res) {
            if (!res.ok || !res.data.success) {
                throw new Error((res.data && res.data.message) || 'The date of issue could not be saved.');
            }
            deliver(state.url);
        })
        .catch(function (e) {
            syncGenerateEnabled();
            fail(e.message || 'Could not save the date of issue. Check your connection and try again.');
        });
    }

    // ── The gate in front of the Print Manager ─────────────────────────────
    // Official printing is the point of no return: the Original goes onto a sheet
    // of security paper, the record is stamped printed and, for a RofO, the
    // applicant is told it is ready. So the manager is not opened straight from a
    // menu any more — it is opened after somebody says the proof has been read and
    // is correct.
    //
    // This is asked of a person rather than derived from the record on purpose.
    // Nothing the system can see distinguishes "a white copy was generated" from
    // "a white copy was read, checked against the file and found right", and only
    // the second is a reason to spend security paper. A stored flag would have
    // turned the first into the second the moment a proof was opened.
    // The gate itself, so anything that leads to official paper can stand behind
    // it — one row's Print Manager, a whole batch, a hand-picked selection.
    //
    //   subject     what is about to be printed, for the question to name
    //   onYes       run the official print
    //   onWhiteCopy offered when the answer is no; omit and only the explanation
    //               is shown
    function confirmProofread(opts) {
        opts = opts || {};
        var subject = opts.subject || 'this document';

        Swal.fire({
            icon: 'question',
            title: 'Has the White Copy been proofread and approved?',
            html: '<div style="font-size:13px;color:#475569;line-height:1.6">'
                + 'The next step prints official copies of <b>' + escHtml(subject) + '</b>'
                + ' — the Original goes onto security paper.'
                + '</div>',
            showCancelButton: true,
            confirmButtonText: 'Yes, it has',
            cancelButtonText: 'No',
            confirmButtonColor: '#2563eb',
            cancelButtonColor: '#64748b',
            reverseButtons: true,
            focusCancel: true
        }).then(function (r) {
            if (r.isConfirmed) {
                if (typeof opts.onYes === 'function') opts.onYes();
                return;
            }

            // Not a dead end: the answer to "not proofread yet" is a white copy, so
            // the way to one is offered here rather than left for the operator to
            // find their own way back to a menu.
            Swal.fire({
                icon: 'info',
                title: 'Generate the White Copy first',
                html: '<div style="font-size:13px;color:#475569;line-height:1.6">'
                    + 'The white copy has to be printed, proofread and approved before official copies are '
                    + 'run off. Correct the record if you find anything, print another white copy, and come '
                    + 'back here.'
                    + '</div>',
                showCancelButton: !!opts.onWhiteCopy,
                confirmButtonText: opts.onWhiteCopy ? 'Generate White Copy' : 'Close',
                cancelButtonText: 'Close',
                confirmButtonColor: '#1e293b',
                cancelButtonColor: '#94a3b8',
                reverseButtons: true
            }).then(function (r2) {
                if (r2.isConfirmed && typeof opts.onWhiteCopy === 'function') opts.onWhiteCopy();
            });
        });
    }

    function openPrintManager(ref, type, url, options) {
        var opts = options || {};

        // No proofing stage for this document — nothing to have proofread.
        if (!opts.whiteCopyUrl) {
            window.SmartPrintManager.open(ref, type, url, opts);
            return;
        }

        confirmProofread({
            subject: ref,
            onYes: function () {
                window.SmartPrintManager.open(ref, type, url, opts);
            },
            onWhiteCopy: function () {
                open({
                    recordId:  opts.recordId,
                    ref:       ref,
                    url:       opts.whiteCopyUrl,
                    issueDate: opts.issueDate || '',
                    ownsDate:  !!opts.whiteCopyOwnsDate,
                    download:  !!opts.whiteCopyDownload,
                    issueDateUrl: opts.issueDateUrl || ''
                });
            }
        });
    }

    // The Print Manager asks for a record's white copy card — a letter with no date
    // of issue cannot be printed, and that is where the date is entered.
    window.addEventListener('open-white-copy', function (e) {
        var d = e.detail || {};
        open({
            recordId: d.recordId, ref: d.ref, url: d.url || '',
            issueDate: d.issueDate || '', ownsDate: true,
            issueDateUrl: d.issueDateUrl || ''
        });
    });

    window.WhiteCopy = {
        open: open,
        close: close,
        openPrintManager: openPrintManager,
        openTab: openTab,
        // Exposed so a batch run — which has its own pipeline and its own proof
        // route — can stand behind the same question a single row does.
        confirmProofread: confirmProofread
    };

    // The inline handlers in the markup above, and the shorthand the row menus use.
    window.openWhiteCopyModal  = function (id, ref, issueDate, url, opts) {
        opts = opts || {};
        open({
            recordId: id, ref: ref, issueDate: issueDate, url: url,
            ownsDate: !!opts.ownsDate,
            download: !!opts.download,
            issueDateUrl: opts.issueDateUrl || ''
        });
    };
    window.closeWhiteCopyModal = close;
    window.generateWhiteCopy   = generate;
})();
</script>
