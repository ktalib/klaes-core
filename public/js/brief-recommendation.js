/**
 * Brief Recommendation — the Permanent Secretary memo for a MOTHER file.
 *
 * A subdivision / merger / regrant / reinstatement ... raises a brief for the
 * mother file, and its children inherit that brief (not whatever recommendation
 * the mother was granted before). The officer:
 *
 *   1. downloads the memo here, as a Word document filled with the mother's
 *      particulars (template chosen from three);
 *   2. edits, prints and signs it outside KLAES;
 *   3. uploads the signed copy from the record / batch menu — the existing
 *      mother-recommendation upload, which files it into the mother's EDMS folder.
 *
 * Approval of the children waits for step 3 (motherRecommendationGate), short of a
 * Supper Admin override.
 *
 * The Word file is filled in the browser (JSZip, loaded by the layout), so going
 * off to Word loses nothing: the record, or the open form, is where it was, and
 * the upload is a menu item on the saved record whenever the officer is back.
 *
 * Used by land_recommendations/index (row + batch menus) and land_recommendations/
 * form (the section under the Mother File No). Configure once per page:
 *
 *   BriefRec.configure({ detailsUrl, downloadedUrl, csrf });
 *   BriefRec.open({ motherFileNo, subject, uploadKey, onUpload, uploadNote });
 */
(function (window, document) {
    'use strict';

    var cfg = { detailsUrl: '', downloadedUrl: '', csrf: '', templateBase: '/templates/brief-recommendation-' };
    var current = null;   // the options the open modal was called with
    var downloaded = {};  // motherFileNo|key -> true, for this page load

    var VARIANTS = [
        { value: 'access-road',   label: 'Accessible with recommended and adequate access road' },
        { value: 'compensated',   label: 'Compensated land (report at page 2)' },
        { value: 'reinstatement', label: 'Re-instatement of revoked title' }
    ];

    function esc(v) {
        return String(v == null ? '' : v).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }

    function alertMsg(icon, title, text) {
        if (window.Swal) window.Swal.fire({ icon: icon, title: title, text: text });
        else window.alert(title + '\n\n' + text);
    }

    function el(id) { return document.getElementById(id); }

    function build() {
        if (el('briefRecModal')) return;

        var radios = VARIANTS.map(function (v, i) {
            return '<label class="flex items-start gap-2 text-xs font-semibold text-slate-700 cursor-pointer">'
                + '<input type="radio" name="briefRecVariant" value="' + v.value + '"' + (i === 0 ? ' checked' : '') + ' class="mt-0.5">'
                + '<span>' + esc(v.label) + '</span></label>'
                + (v.value === 'access-road'
                    ? '<div id="briefRecMeasureWrap" class="pl-6">'
                        + '<input type="text" id="briefRecMeasurement" placeholder="Recommended measurement, e.g. 143.38m X 102.6m X 45.77m"'
                        + ' class="w-full px-3 py-2 border border-slate-200 rounded-lg text-xs focus:outline-none focus:border-blue-500">'
                        + '<p class="mt-1 text-[10px] text-slate-400">Leave empty to fill it in on the Word document.</p></div>'
                    : '')
                + (v.value === 'reinstatement'
                    // File numbers come from the global selector, never typed.
                    ? '<div id="briefRecPortionWrap" class="pl-6 hidden"><div class="flex items-center gap-2">'
                        + '<input type="text" id="briefRecPortionTitle" readonly placeholder="Revoked title file no."'
                        + ' class="flex-1 px-3 py-2 border border-slate-200 rounded-lg text-xs bg-slate-50 font-mono text-slate-800 cursor-default">'
                        + '<button type="button" id="briefRecPortionPick" class="shrink-0 px-3 py-2 rounded-lg bg-blue-600 text-white text-xs font-bold hover:bg-blue-700 transition">Select</button>'
                        + '<button type="button" id="briefRecPortionClear" title="Clear" class="hidden shrink-0 px-2 py-2 rounded-lg text-slate-400 hover:text-slate-900 hover:bg-slate-100 transition">&times;</button>'
                        + '</div><p class="mt-1 text-[10px] text-slate-400">The revoked title being reinstated. Leave empty to fill it in on the Word document.</p></div>'
                    : '');
        }).join('');

        var html = ''
            + '<div id="briefRecModal" class="fixed inset-0 hidden items-center justify-center bg-slate-900/70 p-4" style="z-index:100000;" role="dialog" aria-modal="true" aria-labelledby="briefRecTitle">'
            + '<div class="w-full max-w-lg rounded-2xl bg-white shadow-2xl overflow-hidden">'
            +   '<div class="px-6 py-4 border-b border-slate-200 flex items-start gap-3">'
            +     '<div class="h-10 w-10 shrink-0 rounded-xl bg-sky-100 flex items-center justify-center"><i data-lucide="file-text" class="h-5 w-5 text-sky-700"></i></div>'
            +     '<div class="min-w-0 flex-1">'
            +       '<h3 id="briefRecTitle" class="text-sm font-black text-slate-900">Brief Recommendation (mother)</h3>'
            +       '<p id="briefRecSub" class="text-[11px] font-semibold text-slate-500 truncate"></p>'
            +     '</div>'
            +     '<button type="button" id="briefRecClose" aria-label="Close" class="shrink-0 p-2 rounded-lg text-slate-400 hover:text-slate-900 hover:bg-slate-100 transition">&times;</button>'
            +   '</div>'
            +   '<div class="px-6 py-5 space-y-3">'
            +     '<div id="briefRecStatus" class="hidden"></div>'
            +     '<div id="briefRecOptions" class="rounded-xl border border-slate-200 px-4 py-3 space-y-2">'
            +       '<p class="text-[11px] font-black uppercase tracking-wider text-slate-500">Memo type</p>' + radios
            +     '</div>'
            +     '<button type="button" id="briefRecDownload" class="w-full flex items-center gap-3 px-4 py-3 rounded-xl border border-slate-200 text-left hover:border-slate-300 transition">'
            +       '<span class="h-9 w-9 shrink-0 rounded-lg bg-blue-100 flex items-center justify-center"><i data-lucide="download" class="h-4 w-4 text-blue-700"></i></span>'
            +       '<span class="flex-1"><span class="block text-[10px] font-black uppercase tracking-wider text-blue-600">Step 1</span>'
            +       '<span class="block text-sm font-bold text-slate-800">Download Brief Recommendation</span>'
            +       '<span class="block text-[11px] text-slate-500">Word document filled with the mother file\'s details. Edit, print and sign it.</span></span>'
            +       '<span id="briefRecStep1Done" class="hidden shrink-0 px-2 py-0.5 rounded-full bg-emerald-100 text-[10px] font-black text-emerald-700">Done</span>'
            +     '</button>'
            +     '<button type="button" id="briefRecUpload" class="w-full flex items-center gap-3 px-4 py-3 rounded-xl border border-slate-200 text-left hover:border-slate-300 transition">'
            +       '<span class="h-9 w-9 shrink-0 rounded-lg bg-violet-100 flex items-center justify-center"><i data-lucide="upload" class="h-4 w-4 text-violet-700"></i></span>'
            +       '<span class="flex-1"><span class="block text-[10px] font-black uppercase tracking-wider text-violet-600">Step 2</span>'
            +       '<span id="briefRecUploadLabel" class="block text-sm font-bold text-slate-800">Upload the signed Brief Recommendation</span>'
            +       '<span class="block text-[11px] text-slate-500">PDF or image of the signed copy. Approval waits for it.</span></span>'
            +     '</button>'
            +     '<p id="briefRecNote" class="hidden rounded-xl border border-violet-200 bg-violet-50 px-4 py-3 text-[11px] font-semibold text-violet-900"></p>'
            +   '</div>'
            +   '<div class="px-6 py-4 bg-slate-50 border-t border-slate-200 flex justify-end">'
            +     '<button type="button" id="briefRecDone" class="px-4 py-2 rounded-lg bg-slate-800 text-white text-xs font-bold hover:bg-slate-900 transition">Close</button>'
            +   '</div>'
            + '</div></div>';

        var wrap = document.createElement('div');
        wrap.innerHTML = html;
        document.body.appendChild(wrap.firstChild);

        el('briefRecClose').addEventListener('click', close);
        el('briefRecDone').addEventListener('click', close);
        el('briefRecDownload').addEventListener('click', download);
        el('briefRecUpload').addEventListener('click', function () {
            if (!current || typeof current.onUpload !== 'function') return;
            var cb = current.onUpload;
            close();
            cb();
        });
        el('briefRecPortionPick').addEventListener('click', pickPortion);
        el('briefRecPortionClear').addEventListener('click', function () { setPortion(''); });
        Array.prototype.forEach.call(document.querySelectorAll('input[name="briefRecVariant"]'), function (r) {
            r.addEventListener('change', render);
        });
    }

    function variant() {
        var checked = document.querySelector('input[name="briefRecVariant"]:checked');
        return checked ? checked.value : 'access-road';
    }

    function stateKey(o) { return String(o.motherFileNo || '') + '|' + String(o.uploadKey || ''); }

    function render() {
        if (!current) return;
        el('briefRecMeasureWrap').classList.toggle('hidden', variant() !== 'access-road');
        el('briefRecPortionWrap').classList.toggle('hidden', variant() !== 'reinstatement');

        var done = !!downloaded[stateKey(current)];
        el('briefRecStep1Done').classList.toggle('hidden', !done);

        var canUpload = typeof current.onUpload === 'function';
        el('briefRecUpload').classList.toggle('hidden', !canUpload);
        el('briefRecUploadLabel').textContent = current.hasUpload
            ? 'Replace signed brief Recommendation (mother)'
            : 'Upload signed brief Recommendation (mother)';

        // One step at a time: Upload opens once the brief has been downloaded,
        // here or earlier (current.downloaded comes from the saved record).
        var uploadOpen = done || !!current.downloaded || !!current.hasUpload;
        var up = el('briefRecUpload');
        up.disabled = !uploadOpen;
        up.style.opacity = uploadOpen ? '' : '0.4';
        up.style.cursor = uploadOpen ? '' : 'not-allowed';
        up.title = uploadOpen ? '' : 'Download the brief first (Step 1).';

        var note = el('briefRecNote');
        var text = canUpload ? (current.hasUpload ? 'A signed copy is already on file. Uploading again replaces it.' : '') : (current.uploadNote || '');
        note.textContent = text;
        note.classList.toggle('hidden', !text);
    }

    function setPortion(fileNumber) {
        fileNumber = String(fileNumber || '').trim();
        el('briefRecPortionTitle').value = fileNumber;
        el('briefRecPortionClear').classList.toggle('hidden', !fileNumber);
    }

    function pickPortion() {
        if (!window.GlobalFileNoModal) {
            alertMsg('info', 'File number selector unavailable', 'Leave the field empty and fill the revoked title in on the Word document.');
            return;
        }
        window.GlobalFileNoModal.open({
            // Write nowhere but this picker: the page's own file_number inputs must not change.
            autoPopulateGenericFields: false,
            targetFields: [],
            callback: function (data) { setPortion(data && data.fileNumber ? data.fileNumber : ''); }
        });
    }

    function open(options) {
        if (!options || !String(options.motherFileNo || '').trim()) {
            alertMsg('warning', 'No mother file', 'Select the mother file number first.');
            return;
        }
        build();
        current = options;
        el('briefRecSub').textContent = 'Mother file ' + options.motherFileNo + (options.subject ? ' · ' + options.subject : '');
        el('briefRecMeasurement').value = '';
        setPortion('');
        document.querySelector('input[name="briefRecVariant"][value="access-road"]').checked = true;
        var modal = el('briefRecModal');
        modal.classList.remove('hidden');
        modal.classList.add('flex');
        render();
        if (window.lucide) window.lucide.createIcons();
    }

    function close() {
        var modal = el('briefRecModal');
        if (modal) { modal.classList.add('hidden'); modal.classList.remove('flex'); }
        current = null;
    }

    function xml(v) {
        v = String(v == null ? '' : v).trim();
        if (!v) return '____________________';
        return v.replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; });
    }

    // The memo writes file numbers with slashes: SIT/2026/74, RES/2022/7658.
    function slashed(v) { return String(v || '').replace(/-/g, '/'); }

    function fetchDetails(fileNo) {
        return fetch(cfg.detailsUrl + '?file_no=' + encodeURIComponent(fileNo), {
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
        }).then(function (r) {
            if (!r.ok) throw new Error('Could not read the mother file\'s details (' + r.status + ').');
            return r.json();
        }).then(function (data) {
            return (data && data.details) || { fileNumber: fileNo };
        });
    }

    function logDownload(o, v) {
        if (!cfg.downloadedUrl) return;
        var body = new FormData();
        body.append('file_no', o.motherFileNo);
        body.append('variant', v);
        if (o.uploadKey) body.append('key', o.uploadKey);
        fetch(cfg.downloadedUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'X-CSRF-TOKEN': cfg.csrf, 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            body: body
        }).catch(function () { /* the download itself already happened */ });
    }

    function download() {
        if (!current) return;
        if (typeof window.JSZip === 'undefined') {
            alertMsg('info', 'Still loading', 'The Word generator is still loading. Please try again in a moment.');
            return;
        }
        var o = current;
        var v = variant();
        var measurement = el('briefRecMeasurement').value;
        var portion = el('briefRecPortionTitle').value;
        var btn = el('briefRecDownload');
        btn.disabled = true;
        btn.style.opacity = '0.6';

        fetchDetails(o.motherFileNo)
            .then(function (d) {
                var district = String(d.district || '').trim();
                var fields = {
                    APPLICANT: xml(d.applicant),
                    DISTRICT: xml(district),
                    DISTRICT_UPPER: xml(district.toUpperCase()),
                    LGA: xml(d.lga),
                    LAYOUT: xml(d.layout),
                    PURPOSE: xml(String(d.purpose || '').toLowerCase()),
                    MEASUREMENT: xml(measurement),
                    PORTION_TITLE: xml(slashed(portion)),
                    FILE_NUMBER: xml(slashed(o.motherFileNo))
                };
                return fetch(cfg.templateBase + v + '.docx', { cache: 'no-store' })
                    .then(function (res) {
                        if (!res.ok) throw new Error('Template not found (' + res.status + ').');
                        return res.arrayBuffer();
                    })
                    .then(function (buf) { return window.JSZip.loadAsync(buf); })
                    .then(function (zip) {
                        return zip.file('word/document.xml').async('string').then(function (doc) {
                            doc = doc.replace(/\$\{([A-Z_]+)\}/g, function (m, key) {
                                return Object.prototype.hasOwnProperty.call(fields, key) ? fields[key] : m;
                            });
                            zip.file('word/document.xml', doc);
                            return zip.generateAsync({
                                type: 'blob',
                                mimeType: 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
                            });
                        });
                    });
            })
            .then(function (blob) {
                var link = document.createElement('a');
                link.href = URL.createObjectURL(blob);
                link.download = 'Brief-Recommendation-' + String(o.motherFileNo).replace(/[^A-Za-z0-9_-]+/g, '-') + '.docx';
                document.body.appendChild(link);
                link.click();
                link.remove();
                setTimeout(function () { URL.revokeObjectURL(link.href); }, 1000);

                downloaded[stateKey(o)] = true;
                logDownload(o, v);
                if (current === o) render();
            })
            .catch(function (err) {
                if (window.console) console.error('Brief Recommendation download failed', err);
                alertMsg('error', 'Download failed', (err && err.message) || 'Could not build the Word document.');
            })
            .then(function () {
                btn.disabled = false;
                btn.style.opacity = '';
            });
    }

    function configure(options) {
        for (var k in options) {
            if (Object.prototype.hasOwnProperty.call(options, k)) cfg[k] = options[k];
        }
    }

    // ── Approval override ────────────────────────────────────────────────
    // The server refuses approval while the signed brief is missing, and says
    // can_override:true when the user is a Supper Admin. This asks for the reason
    // and re-sends the same approval with it: approveUrl is the batch endpoint (ids
    // posted) or, with single = true, one record's own /approve (no ids).
    function overrideApprove(ids, message, approveUrl, single) {
        if (!window.Swal) { window.alert(message); return; }
        window.Swal.fire({
            icon: 'warning',
            title: 'Approve without the signed brief Recommendation?',
            html: '<p style="font-size:13px;text-align:left;margin-bottom:10px">' + esc(message) + '</p>'
                + '<p style="font-size:12px;text-align:left;color:#b45309;font-weight:600">As Supper Admin you can override this. The override and its reason are recorded on each record approved.</p>',
            input: 'textarea',
            inputPlaceholder: 'Reason for approving without the signed Brief Recommendation (at least 10 characters)',
            showCancelButton: true,
            confirmButtonText: 'Override and approve',
            confirmButtonColor: '#b45309',
            showLoaderOnConfirm: true,
            inputValidator: function (v) {
                if (!v || v.trim().length < 10) return 'Give a reason of at least 10 characters.';
            },
            preConfirm: function (reason) {
                return fetch(approveUrl, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': cfg.csrf },
                    body: JSON.stringify(single ? { override_reason: reason.trim() } : { ids: ids, override_reason: reason.trim() })
                })
                    .then(function (r) { return r.json().catch(function () { return {}; }); })
                    .then(function (data) {
                        if (!data.success) throw new Error(data.message || 'The approval failed.');
                        return data;
                    })
                    .catch(function (err) { window.Swal.showValidationMessage(err.message || 'Request failed.'); return false; });
            }
        }).then(function (result) {
            if (!result.isConfirmed || !result.value) return;
            window.Swal.fire({ icon: 'success', title: 'Approved', text: (result.value.approved != null ? result.value.approved : ids.length) + ' record(s) approved with an override.', timer: 1800, showConfirmButton: false })
                .then(function () { window.location.reload(); });
        });
    }

    // ── Signature check (ink detection) ──────────────────────────────────
    // A heuristic, not a verifier: it asks whether the signature part of the page
    // carries handwriting-like ink. On the signed memos on file the signatures,
    // dates and names are in blue or red pen over black print, and the letterhead's
    // colour sits at the top (crest) and down the left edge (the vertical band). So:
    //
    //   - only the lower part of the page is read (SIG_TOP down), minus the left band;
    //   - "coloured ink" is a saturated blue / violet / red pixel that is not pale;
    //     a cluster of it big enough to be a stroke counts as a mark;
    //   - for black-pen signatures, a dark cluster counts only if it is TALL and
    //     LOOSE (sparse) - printed letters are short and dense, rules are flat.
    //
    // It can be fooled (a coloured stamp passes, a very faint pencil fails), which
    // is why the upload warns rather than refuses.
    var SIG = {
        WIDTH: 1000,        // pages are scaled to this width before reading
        TOP: 0.40,          // read from 40% of the page height down
        LEFT: 0.15,         // skip the vertical letterhead band
        RIGHT: 0.95,        // and the page edge (scans often show the next sheet)
        MIN_INK_PIXELS: 60, // a coloured cluster smaller than this is a speck
        INK_LEVEL: 0.92,    // pen ink is darker than this share of the paper brightness
        BLUE_LEAD: 18,      // blue pen: blue channel above red and green by this much
        RED_LEAD: 35,       // red pen: red channel above green and blue by this much
        DARK_LEVEL: 0.62,   // black ink / print is darker than this share of the paper
        DARK_MAX_CHROMA: 50,// ... and near-grey (a faint blue pen in a dim photo still is)
        MIN_INK_W: 0.03,    // ... and its box must be this wide or tall (of page width)
        DARK_MIN_H: 0.035,  // a black cluster must be this tall (of page height)
        DARK_MIN_W: 0.05,
        DARK_MAX_FILL: 0.35 // ... and this loose (ink / box area)
    };

    function analyseInk(data, w, h) {
        var x0 = Math.floor(w * SIG.LEFT), x1 = Math.floor(w * SIG.RIGHT);
        var y0 = Math.floor(h * SIG.TOP), y1 = h;
        var rw = x1 - x0, rh = y1 - y0;
        if (rw <= 0 || rh <= 0) return { detected: false, marks: 0, colourMarks: 0, darkMarks: 0 };

        // The paper's own brightness, so a dim photo and a bright scan are read
        // alike: the 90th percentile of luminance across the region.
        var hist = new Uint32Array(256), total = 0, x, y, i;
        for (y = y0; y < y1; y += 2) {
            for (x = x0; x < x1; x += 2) {
                i = (y * w + x) * 4;
                hist[(data[i] * 3 + data[i + 1] * 6 + data[i + 2]) / 10 | 0]++;
                total++;
            }
        }
        var paper = 255, acc = 0;
        for (var v = 0; v < 256; v++) { acc += hist[v]; if (acc >= total * 0.9) { paper = v; break; } }
        var darkCut = paper * SIG.DARK_LEVEL, inkCut = paper * SIG.INK_LEVEL;

        // 1 = coloured (blue / violet / red pen), 2 = dark (black pen or print).
        var mask = new Uint8Array(rw * rh);
        for (y = 0; y < rh; y++) {
            for (x = 0; x < rw; x++) {
                i = ((y + y0) * w + (x + x0)) * 4;
                var r = data[i], g = data[i + 1], b = data[i + 2];
                var lum = (r * 3 + g * 6 + b) / 10;
                var k = 0;
                if (lum < inkCut && (b - Math.max(r, g) >= SIG.BLUE_LEAD || r - Math.max(g, b) >= SIG.RED_LEAD)) {
                    k = 1;   // green (letterhead) and yellow never qualify
                } else if (lum < darkCut && Math.max(r, g, b) - Math.min(r, g, b) < SIG.DARK_MAX_CHROMA) {
                    k = 2;   // near-grey only: dark green letterhead is not black pen
                }
                mask[y * rw + x] = k;
            }
        }

        // Photocopied and phone-photo strokes break into dots; one pixel of
        // growth rejoins them before they are measured. Applied to dark ink only,
        // and too little to merge two lines of print.
        var dark = new Uint8Array(rw * rh);
        for (y = 0; y < rh; y++) {
            for (x = 0; x < rw; x++) {
                if (mask[y * rw + x] !== 2) continue;
                for (var gy = Math.max(0, y - 1); gy <= Math.min(rh - 1, y + 1); gy++) {
                    for (var gx = Math.max(0, x - 1); gx <= Math.min(rw - 1, x + 1); gx++) dark[gy * rw + gx] = 1;
                }
            }
        }

        var seen = new Uint8Array(rw * rh), stack = new Int32Array(rw * rh);
        // Components of `kind` in `grid`: returns how many pass `accept(count, bw, bh)`.
        function components(grid, kind, accept) {
            var found = 0;
            seen.fill(0);
            for (var start = 0; start < grid.length; start++) {
                if (grid[start] !== kind || seen[start]) continue;
                var top = 0, count = 0, minX = rw, maxX = 0, minY = rh, maxY = 0;
                stack[top++] = start; seen[start] = 1;
                while (top) {
                    var p = stack[--top], px = p % rw, py = (p - px) / rw;
                    count++;
                    if (px < minX) minX = px; if (px > maxX) maxX = px;
                    if (py < minY) minY = py; if (py > maxY) maxY = py;
                    for (var dy = -1; dy <= 1; dy++) {
                        var ny = py + dy;
                        if (ny < 0 || ny >= rh) continue;
                        for (var dx = -1; dx <= 1; dx++) {
                            var nx = px + dx;
                            if (nx < 0 || nx >= rw) continue;
                            var q = ny * rw + nx;
                            if (!seen[q] && grid[q] === kind) { seen[q] = 1; stack[top++] = q; }
                        }
                    }
                }
                if (accept(count, maxX - minX + 1, maxY - minY + 1)) found++;
            }
            return found;
        }

        var colourMarks = components(mask, 1, function (count, bw, bh) {
            return count >= SIG.MIN_INK_PIXELS && Math.max(bw, bh) >= w * SIG.MIN_INK_W;
        });
        // Black pen: tall AND loose. Printed letters (even bold, underlined
        // headings) are short; rules and dotted lines are flat.
        var darkMarks = components(dark, 1, function (count, bw, bh) {
            return bh >= h * SIG.DARK_MIN_H && bw >= w * SIG.DARK_MIN_W && count / (bw * bh) <= SIG.DARK_MAX_FILL;
        });

        return { detected: colourMarks + darkMarks > 0, marks: colourMarks + darkMarks, colourMarks: colourMarks, darkMarks: darkMarks, paper: paper };
    }

    function canvasFor(width, height) {
        var c = document.createElement('canvas');
        c.width = width; c.height = height;
        return c;
    }

    function pixelsOf(source, sw, sh) {
        var scale = SIG.WIDTH / sw;
        var c = canvasFor(SIG.WIDTH, Math.max(1, Math.round(sh * scale)));
        var ctx = c.getContext('2d');
        ctx.fillStyle = '#fff';
        ctx.fillRect(0, 0, c.width, c.height);
        ctx.drawImage(source, 0, 0, c.width, c.height);
        return { data: ctx.getImageData(0, 0, c.width, c.height).data, w: c.width, h: c.height };
    }

    function imagePages(file) {
        return new Promise(function (resolve, reject) {
            var url = URL.createObjectURL(file);
            var img = new Image();
            img.onload = function () {
                try { resolve([pixelsOf(img, img.naturalWidth, img.naturalHeight)]); }
                catch (e) { reject(e); }
                finally { URL.revokeObjectURL(url); }
            };
            img.onerror = function () { URL.revokeObjectURL(url); reject(new Error('This image could not be read (HEIC photos cannot be checked in the browser).')); };
            img.src = url;
        });
    }

    var PDFJS = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/';
    var pdfjsReady = null;
    function loadPdfJs() {
        if (window.pdfjsLib) return Promise.resolve(window.pdfjsLib);
        if (pdfjsReady) return pdfjsReady;
        pdfjsReady = new Promise(function (resolve, reject) {
            var s = document.createElement('script');
            s.src = PDFJS + 'pdf.min.js';
            s.onload = function () {
                if (!window.pdfjsLib) { reject(new Error('PDF reader did not load.')); return; }
                window.pdfjsLib.GlobalWorkerOptions.workerSrc = PDFJS + 'pdf.worker.min.js';
                resolve(window.pdfjsLib);
            };
            s.onerror = function () { pdfjsReady = null; reject(new Error('PDF reader could not be loaded.')); };
            document.head.appendChild(s);
        });
        return pdfjsReady;
    }

    // The memo is one page; a scan may carry a cover or attachment, so the first
    // three pages are read and a mark on any of them counts.
    function pdfPages(file) {
        return Promise.all([loadPdfJs(), file.arrayBuffer()]).then(function (r) {
            return r[0].getDocument({ data: r[1] }).promise;
        }).then(function (pdf) {
            var jobs = [];
            for (var n = 1; n <= Math.min(pdf.numPages, 3); n++) {
                jobs.push(pdf.getPage(n).then(function (page) {
                    var vp1 = page.getViewport({ scale: 1 });
                    var vp = page.getViewport({ scale: SIG.WIDTH / vp1.width });
                    var c = canvasFor(Math.round(vp.width), Math.round(vp.height));
                    var ctx = c.getContext('2d');
                    ctx.fillStyle = '#fff';
                    ctx.fillRect(0, 0, c.width, c.height);
                    return page.render({ canvasContext: ctx, viewport: vp }).promise.then(function () {
                        return { data: ctx.getImageData(0, 0, c.width, c.height).data, w: c.width, h: c.height };
                    });
                }));
            }
            return Promise.all(jobs);
        });
    }

    /**
     * Check a chosen file. Resolves (never rejects) with
     *   { status: 'detected' | 'not_detected' | 'unchecked', marks, pages, message }
     * 'unchecked' means the check could not run (unreadable file, PDF reader
     * unavailable); the upload treats that as nothing to warn about.
     */
    function checkSignature(file) {
        var isPdf = /pdf$/i.test(file.type) || /\.pdf$/i.test(file.name);
        return (isPdf ? pdfPages(file) : imagePages(file))
            .then(function (pages) {
                var marks = 0;
                pages.forEach(function (p) { marks += analyseInk(p.data, p.w, p.h).marks; });
                return {
                    status: marks > 0 ? 'detected' : 'not_detected',
                    marks: marks,
                    pages: pages.length,
                    message: marks > 0
                        ? 'Signature detected (' + marks + ' handwritten mark' + (marks === 1 ? '' : 's') + ' in the signature area).'
                        : 'No signature detected in the signature area of this document.'
                };
            })
            .catch(function (err) {
                return { status: 'unchecked', marks: null, pages: 0, message: 'The signature check could not run: ' + ((err && err.message) || 'unknown error') };
            });
    }

    window.BriefRec = {
        configure: configure, open: open, close: close, overrideApprove: overrideApprove,
        checkSignature: checkSignature, analyseInk: analyseInk, SIGNATURE_SETTINGS: SIG
    };
})(window, document);
