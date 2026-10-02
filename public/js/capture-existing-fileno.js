/**
 * Capture Existing File Numbers – DataTable & modal logic.
 *
 * Expects a global object `window.captureExistingConfig` with route URLs:
 *   {
 *     dataUrl:        '/existing-file-numbers/data',
 *     storeUrl:       '/existing-file-numbers/store',
 *     migrateUrl:     '/file-numbers/migrate',
 *     showUrl:        '/file-numbers/:id',          // :id placeholder
 *     updateUrl:      '/file-numbers/:id',           // :id placeholder
 *     destroyUrl:     '/file-numbers/:id',           // :id placeholder
 *     existingUrl:    '/file-numbers/existing',
 *     countUrl:       '/file-numbers/count',
 *   }
 */

(function () {
    'use strict';

    var cfg = window.captureExistingConfig || {};

    var table;

    var BADGE_VARIANTS = {
        mls:       { wrapper: 'border border-sky-200 bg-sky-100 text-sky-700',       dot: 'bg-sky-500' },
        st:        { wrapper: 'border border-emerald-200 bg-emerald-100 text-emerald-700', dot: 'bg-emerald-500' },
        kangis:    { wrapper: 'border border-amber-200 bg-amber-100 text-amber-700', dot: 'bg-amber-500' },
        newkangis: { wrapper: 'border border-fuchsia-200 bg-fuchsia-100 text-fuchsia-700', dot: 'bg-fuchsia-500' },
        muted:     { wrapper: 'border border-slate-200 bg-slate-100 text-slate-500', dot: 'bg-slate-400' }
    };

    /* ── Helpers ─────────────────────────────────────────────── */

    function sanitizeValue(value) {
        return $('<div/>').text(value ?? '').html();
    }

    function renderFileBadge(value, variant) {
        variant = variant || 'muted';
        if (!value || value === 'N/A' || value === '-' || (typeof value === 'string' && value.trim() === '')) {
            var muted = BADGE_VARIANTS.muted;
            return '<span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-semibold ' + muted.wrapper + ' whitespace-nowrap">' +
                        '<span class="w-1.5 h-1.5 rounded-full ' + muted.dot + '"></span>' +
                        '-' +
                    '</span>';
        }
        var sanitized = sanitizeValue(value);
        var config = BADGE_VARIANTS[variant] || BADGE_VARIANTS.muted;
        return '<span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-semibold ' + config.wrapper + ' whitespace-nowrap">' +
                    '<span class="w-1.5 h-1.5 rounded-full ' + config.dot + '"></span>' +
                    sanitized +
                '</span>';
    }

    function renderPlainText(value, fallback) {
        fallback = fallback || '-';
        if (!value || value === 'N/A' || value === '-' || (typeof value === 'string' && value.trim() === '')) {
            return '<span class="inline-flex items-center px-2 py-1 rounded-md bg-slate-100 text-xs font-semibold text-slate-500">' + fallback + '</span>';
        }
        return '<span class="inline-flex items-center px-2 py-1 rounded-md bg-slate-50 text-sm font-medium text-slate-700">' + sanitizeValue(value) + '</span>';
    }

    function csrfToken() {
        var meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.getAttribute('content') : '';
    }

    /* ── Loading utilities ──────────────────────────────────── */

    function showLoadingButton(btn, originalText) {
        if (btn) {
            btn.disabled = true;
            btn.innerHTML = '<i data-lucide="loader" class="w-4 h-4 mr-2 animate-spin"></i>Loading...';
            lucide.createIcons();
        }
    }

    function hideLoadingButton(btn, originalText) {
        if (btn) {
            btn.disabled = false;
            btn.innerHTML = originalText;
            lucide.createIcons();
        }
    }

    function showGlobalLoading(message) {
        Swal.fire({
            title: message || 'Processing...',
            allowOutsideClick: false,
            allowEscapeKey: false,
            showConfirmButton: false,
            didOpen: function () { Swal.showLoading(); }
        });
    }

    function hideGlobalLoading() {
        Swal.close();
    }

    /* ── DataTable initialisation ───────────────────────────── */

    function testDataEndpoint() {
        console.log('Testing data endpoint...');
        fetch(cfg.dataUrl + '?draw=1&start=0&length=5', {
            method: 'GET',
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrfToken()
            }
        })
        .then(function (r) { console.log('Response status:', r.status); return r.json(); })
        .then(function (d) { console.log('Endpoint test result:', d); })
        .catch(function (e) { console.error('Endpoint test error:', e); });
    }

    function initDataTable() {
        table = $('#captureTable').DataTable({
            processing: true,
            serverSide: true,
            ajax: {
                url: cfg.dataUrl,
                type: 'GET',
                data: function (d) { console.log('DataTables request:', d); return d; },
                dataSrc: function (json) {
                    console.log('DataTables response:', json);
                    if (json.error) {
                        console.error('Server error:', json.error);
                        Swal.fire({ icon: 'error', title: 'Server Error', text: json.error, confirmButtonColor: '#ef4444' });
                        return [];
                    }
                    if (!json.data) { console.warn('No data property in response:', json); return []; }
                    console.log('Returning data array with length:', json.data.length);
                    return json.data;
                },
                error: function (xhr, error) {
                    console.error('DataTables AJAX error:', error, 'Status:', xhr.status);
                    var msg = 'Failed to load file numbers. Please check your connection and try again.';
                    if (xhr.status === 500) msg = 'Server error occurred. Please contact the administrator.';
                    else if (xhr.status === 404) msg = 'Data endpoint not found. Please contact the administrator.';
                    else if (xhr.status === 0) msg = 'Network connection error. Please check your internet connection.';
                    Swal.fire({
                        icon: 'error', title: 'Error Loading Data', text: msg, confirmButtonColor: '#ef4444',
                        footer: '<small>Error Code: ' + xhr.status + ' - ' + error + '</small>'
                    });
                }
            },
            columns: [
                {
                    data: 'primaryFileNo', name: 'primaryFileNo', title: 'Primary FileNo', defaultContent: '-',
                    className: 'align-middle whitespace-nowrap font-semibold text-slate-700',
                    render: function (data, type, row) {
                        if (!data || data === 'N/A' || data === '-') return renderFileBadge(null, 'muted');
                        // Determine variant from which underlying field matched
                        var variant = 'muted';
                        var mlsf = (row.mlsfNo || '').trim();
                        var kang = (row.kangisFileNo || '').trim();
                        var nk   = (row.NewKANGISFileNo || '').trim();
                        var st   = (row.stFileNo || '').trim();
                        if (mlsf && mlsf !== 'N/A' && data === mlsf) variant = 'mls';
                        else if (kang && kang !== 'N/A' && data === kang) variant = 'kangis';
                        else if (nk && nk !== 'N/A' && data === nk) variant = 'newkangis';
                        else if (st && st !== 'N/A' && data === st) variant = 'st';
                        return renderFileBadge(data, variant);
                    }
                },
                {
                    data: 'relatedFileNo', name: 'relatedFileNo', title: 'Related FileNo', defaultContent: '-',
                    className: 'align-middle whitespace-nowrap text-slate-600',
                    render: function (data) {
                        if (!data || data === 'N/A' || data === '-') return renderPlainText(null);
                        // Show first value as badge, rest as tooltip
                        var parts = data.split(',').map(function(s) { return s.trim(); }).filter(Boolean);
                        if (parts.length === 0) return renderPlainText(null);
                        var html = renderFileBadge(parts[0], 'muted');
                        if (parts.length > 1) {
                            html += '<span class="ml-1 inline-flex items-center px-1.5 py-0.5 rounded-full text-xs font-semibold bg-slate-200 text-slate-600" title="' + sanitizeValue(data) + '">+' + (parts.length - 1) + '</span>';
                        }
                        return html;
                    }
                },
                {
                    data: 'source', name: 'source', title: 'Source', defaultContent: '-',
                    className: 'align-middle whitespace-nowrap text-slate-600',
                    render: function (data) { return renderPlainText(data, '-'); }
                },
                {
                    data: 'FileName', name: 'FileName', title: 'File Name', defaultContent: '-',
                    className: 'align-middle whitespace-nowrap text-slate-600',
                    render: function (data) { return renderPlainText(data, 'Not Captured'); }
                },
                {
                    data: 'tp_no', name: 'tp_no', title: 'TP No', defaultContent: '-',
                    className: 'align-middle whitespace-nowrap text-slate-600',
                    render: function (data) { return renderPlainText(data); }
                },
                {
                    data: 'plot_no', name: 'plot_no', title: 'Plot No', defaultContent: '-',
                    className: 'align-middle whitespace-nowrap text-slate-600',
                    render: function (data) { return renderPlainText(data); }
                },
                {
                    data: 'lga', name: 'lga', title: 'LGA', defaultContent: '-',
                    className: 'align-middle whitespace-nowrap text-slate-600',
                    render: function (data) { return renderPlainText(data); }
                },
                {
                    data: 'location', name: 'location', title: 'Location', defaultContent: '-',
                    className: 'align-middle whitespace-nowrap text-slate-600',
                    render: function (data) { return renderPlainText(data); }
                },
                {
                    data: 'created_by', name: 'created_by', title: 'Captured By', defaultContent: '-',
                    className: 'align-middle whitespace-nowrap text-slate-600',
                    render: function (data) { return renderPlainText(data, 'System'); }
                },
                {
                    data: 'created_at', name: 'created_at', title: 'Capture Date', defaultContent: '-',
                    className: 'align-middle whitespace-nowrap text-slate-600',
                    render: function (data) {
                        if (data && data !== '-' && data.trim() !== '') {
                            try {
                                var date = new Date(data);
                                if (!isNaN(date)) {
                                    var formatted = date.toLocaleDateString('en-US', {
                                        year: 'numeric', month: 'short', day: 'numeric',
                                        hour: '2-digit', minute: '2-digit', hour12: true
                                    });
                                    return '<span class="inline-flex items-center px-2 py-1 rounded-md bg-emerald-50 text-xs font-semibold text-emerald-700">' + formatted + '</span>';
                                }
                            } catch (e) { console.log('Date parsing error:', e); }
                        }
                        return renderPlainText(null);
                    }
                },
                {
                    data: 'action', name: 'action', title: 'Actions', orderable: false, searchable: false,
                    className: 'align-middle whitespace-nowrap text-center text-slate-500',
                    defaultContent: '<span class="inline-flex items-center px-2 py-1 rounded-md bg-slate-100 text-xs font-semibold text-slate-500">No actions</span>',
                    render: function (data) {
                        if (data && data !== '-' && data.trim() !== '') return data;
                        return '<span class="inline-flex items-center px-2 py-1 rounded-md bg-slate-100 text-xs font-semibold text-slate-500">No actions</span>';
                    }
                }
            ],
            order: [[0, 'desc']],
            pageLength: 25,
            responsive: true,
            language: {
                processing: '<div class="flex items-center justify-center"><i data-lucide="loader" class="w-4 h-4 mr-2 animate-spin"></i>Loading file numbers...</div>',
                emptyTable: '<div class="text-center py-8"><div class="text-gray-400 mb-2"><i data-lucide="database" class="w-12 h-12 mx-auto mb-2"></i></div><h3 class="text-lg font-medium text-gray-900 mb-1">No file numbers found</h3><p class="text-gray-500">Start by capturing your first existing file number using the button above.</p></div>',
                zeroRecords: '<div class="text-center py-8"><div class="text-gray-400 mb-2"><i data-lucide="search" class="w-12 h-12 mx-auto mb-2"></i></div><h3 class="text-lg font-medium text-gray-900 mb-1">No matching records found</h3><p class="text-gray-500">Try adjusting your search criteria.</p></div>',
                info: 'Showing _START_ to _END_ of _TOTAL_ file numbers',
                infoEmpty: 'No file numbers available',
                infoFiltered: '(filtered from _MAX_ total file numbers)',
                lengthMenu: 'Show _MENU_ file numbers per page',
                search: 'Search file numbers:',
                paginate: { first: 'First', last: 'Last', next: 'Next', previous: 'Previous' }
            },
            drawCallback: function (settings) {
                setTimeout(function () { lucide.createIcons(); }, 100);
                console.log('DataTable draw completed:', {
                    recordsTotal: settings.json ? settings.json.recordsTotal : 0,
                    recordsFiltered: settings.json ? settings.json.recordsFiltered : 0,
                    dataLength: settings.json && settings.json.data ? settings.json.data.length : 0
                });
            },
            createdRow: function (row) { $(row).addClass('transition-colors'); },
            initComplete: function (settings, json) {
                console.log('DataTable initialized:', {
                    recordsTotal: json ? json.recordsTotal : 0,
                    recordsFiltered: json ? json.recordsFiltered : 0,
                    dataLength: json && json.data ? json.data.length : 0
                });
                if (json && json.recordsTotal === 0) console.log('No records found in database');
            }
        });
    }

    /* ── Tracking-ID / Grouping lookup ──────────────────────── */

    var trackingLookupTimeout = null;
    var trackingLookupRequestId = 0;

    function setTrackingIdDisplay(text, tone) {
        var display = document.getElementById('trackingIdDisplay');
        if (!display) return;
        display.textContent = text;
        display.classList.remove('text-slate-500', 'text-green-600', 'text-red-600');
        if (tone === 'success') display.classList.add('text-green-600');
        else if (tone === 'error') display.classList.add('text-red-600');
        else display.classList.add('text-slate-500');
    }

    function setTrackingStatus(message) {
        var el = document.getElementById('trackingIdStatus');
        if (el) el.textContent = message || '';
    }

    function resetTrackingIdState() {
        var inp = document.getElementById('trackingId');
        if (inp) inp.value = '';
        setTrackingIdDisplay('Awaiting grouping match');
        setTrackingStatus('');
    }

    function normalizeGroupingLookupTarget(value) {
        return (value || '').toString().trim()
            .replace(/\s+AND\s+EXTENSION$/i, '')
            .replace(/\(T\)\s*$/i, '')
            .replace(/-+$/, '');
    }

    function scheduleGroupingLookup(previewValue) {
        if (trackingLookupTimeout) clearTimeout(trackingLookupTimeout);
        var normalizedValue = normalizeGroupingLookupTarget(previewValue);
        if (!normalizedValue || normalizedValue === '-') { resetTrackingIdState(); return; }
        trackingLookupTimeout = setTimeout(function () { lookupGroupingTrackingId(normalizedValue); }, 400);
    }

    function lookupGroupingTrackingId(previewValue) {
        trackingLookupTimeout = null;
        trackingLookupRequestId += 1;
        var requestId = trackingLookupRequestId;
        var lookupTarget = normalizeGroupingLookupTarget(previewValue);
        var trackingInput = document.getElementById('trackingId');

        setTrackingIdDisplay('Searching...');
        setTrackingStatus('Checking grouping records...');

        var token = csrfToken();

        fetch('/api/grouping/link-mls', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token },
            body: JSON.stringify({ mls_file_number: lookupTarget })
        })
        .then(function (r) { return r.json().then(function (d) { return { ok: r.ok, data: d }; }); })
        .then(function (result) {
            if (requestId !== trackingLookupRequestId) return;
            if (result.ok && result.data.success) {
                var linked = result.data.data || {};
                var tid = (linked.tracking_id || '').trim();
                if (trackingInput) trackingInput.value = tid;
                if (tid) { setTrackingIdDisplay(tid, 'success'); setTrackingStatus('Linked to grouping record.'); }
                else { setTrackingIdDisplay('Grouping record has no tracking ID'); setTrackingStatus('Assign tracking ID manually before saving.'); }
                return;
            }
            // Fallback
            return fetch('/api/grouping/awaiting/' + encodeURIComponent(lookupTarget))
                .then(function (r2) { return r2.json().then(function (d2) { return { ok: r2.ok, data: d2 }; }); })
                .then(function (fb) {
                    if (requestId !== trackingLookupRequestId) return;
                    if (!fb.ok || !fb.data.success) throw new Error((fb.data && fb.data.message) || 'Grouping record not found');
                    var rec = fb.data.data || fb.data;
                    var tid = (rec.tracking_id || '').trim();
                    if (trackingInput) trackingInput.value = tid;
                    if (tid) { setTrackingIdDisplay(tid, 'success'); setTrackingStatus('Linked to grouping record.'); }
                    else { setTrackingIdDisplay('Grouping record has no tracking ID'); setTrackingStatus('Assign tracking ID manually before saving.'); }
                });
        })
        .catch(function (err) {
            if (requestId !== trackingLookupRequestId) return;
            if (trackingInput) trackingInput.value = '';
            setTrackingIdDisplay('Grouping tracking ID not found', 'error');
            setTrackingStatus('A new tracking ID will be generated on save.');
            console.error('Error fetching grouping tracking ID:', err);
        });
    }

    /* ── Capture modal functions ─────────────────────────────── */

    function openCaptureModal() {
        document.getElementById('captureModal').classList.remove('hidden');
        document.getElementById('captureForm').reset();
        document.getElementById('year').value = new Date().getFullYear();

        if (trackingLookupTimeout) { clearTimeout(trackingLookupTimeout); trackingLookupTimeout = null; }
        trackingLookupRequestId += 1;
        resetTrackingIdState();

        document.getElementById('fileOption').value = 'normal';
        updateCaptureForm('normal');
        loadExistingFileNumbers();
        updateCapturePreview();
    }

    function closeCaptureModal() {
        document.getElementById('captureModal').classList.add('hidden');
        if (trackingLookupTimeout) { clearTimeout(trackingLookupTimeout); trackingLookupTimeout = null; }
        trackingLookupRequestId += 1;
    }

    function syncCaptureFileOption(value) {
        var el = document.getElementById('fileOption');
        if (!el || !value) return;
        el.value = value;
        updateCaptureForm(value);
    }

    function syncQuickFileOptionUI(value) {
        var opts = document.querySelectorAll('input[name="quick_file_option_capture"]');
        if (!opts.length) return;
        var matched = false;
        opts.forEach(function (o) { var m = o.value === value; o.checked = m; if (m) matched = true; });
        if (!matched) opts.forEach(function (o) { o.checked = false; });
    }

    function updateCaptureForm(type) {
        var prefixSection = document.getElementById('prefixSection');
        var middlePrefixSection = document.getElementById('middlePrefixSection');
        var yearSection = document.getElementById('yearSection');
        var extensionFileSection = document.getElementById('extensionFileSection');
        var temporaryFileSection = document.getElementById('temporaryFileSection');
        var serialNoField = document.getElementById('serialNo');

        syncQuickFileOptionUI(type);

        if (prefixSection) prefixSection.classList.add('hidden');
        if (middlePrefixSection) middlePrefixSection.classList.add('hidden');
        if (yearSection) yearSection.classList.add('hidden');
        if (extensionFileSection) extensionFileSection.classList.add('hidden');
        if (temporaryFileSection) temporaryFileSection.classList.add('hidden');

        serialNoField.type = 'text';
        serialNoField.removeAttribute('min');
        serialNoField.removeAttribute('max');
        serialNoField.removeAttribute('step');
        serialNoField.removeAttribute('maxlength');
        serialNoField.removeAttribute('pattern');
        serialNoField.removeAttribute('inputmode');
        serialNoField.disabled = false;

        if (type === 'normal') {
            if (prefixSection) prefixSection.classList.remove('hidden');
            if (yearSection) yearSection.classList.remove('hidden');
            serialNoField.type = 'number';
            serialNoField.setAttribute('min', '1');
            serialNoField.setAttribute('max', '9999');
            serialNoField.placeholder = 'Enter serial number (1-9999)';
        } else if (type === 'temporary') {
            if (temporaryFileSection) temporaryFileSection.classList.remove('hidden');
            if (yearSection) yearSection.classList.remove('hidden');
            serialNoField.placeholder = 'Not required for temporary files';
            serialNoField.value = '';
            serialNoField.disabled = true;
            loadExistingFileNumbers();
            setupTemporaryToggle();
        } else if (type === 'extension') {
            if (extensionFileSection) extensionFileSection.classList.remove('hidden');
            if (yearSection) yearSection.classList.remove('hidden');
            serialNoField.placeholder = 'Not required for extensions';
            serialNoField.value = '';
            serialNoField.disabled = true;
            loadExistingFileNumbers();
            setupExtensionToggle();
        } else if (type === 'miscellaneous') {
            if (middlePrefixSection) middlePrefixSection.classList.remove('hidden');
            if (yearSection) yearSection.classList.remove('hidden');
            serialNoField.type = 'text';
            serialNoField.setAttribute('inputmode', 'text');
            serialNoField.placeholder = 'Enter custom serial (e.g., 1, ABC123)';
        } else if (type === 'old_mls' || type === 'sltr' || type === 'sit') {
            if (type === 'sit' && yearSection) yearSection.classList.remove('hidden');
            serialNoField.type = 'text';
            serialNoField.setAttribute('inputmode', 'text');
            if (type === 'sit') serialNoField.placeholder = 'Enter SIT serial (e.g., 1, 2024-1)';
            else if (type === 'old_mls') serialNoField.placeholder = 'Enter Old MLPP number (e.g., 5467, 34874857488758)';
            else if (type === 'sltr') serialNoField.placeholder = 'Enter SLTR serial (e.g., 1, 2024-1)';
        }

        updateCapturePreview();
    }

    function setupExtensionToggle() {
        var dropdownRadio = document.getElementById('extensionDropdown');
        var manualRadio = document.getElementById('extensionManual');
        var dropdownSection = document.getElementById('extensionDropdownSection');
        var manualInput = document.getElementById('extensionManualInput');

        if (document.getElementById('extensionManualFile')) document.getElementById('extensionManualFile').disabled = true;
        if (document.getElementById('existingFileNo')) document.getElementById('existingFileNo').disabled = false;

        if (dropdownRadio && manualRadio) {
            dropdownRadio.addEventListener('change', function () {
                if (this.checked) {
                    dropdownSection.classList.remove('hidden');
                    manualInput.classList.add('hidden');
                    document.getElementById('extensionManualFile').value = '';
                    document.getElementById('extensionManualFile').disabled = true;
                    document.getElementById('existingFileNo').disabled = false;
                    updateCapturePreview();
                }
            });
            manualRadio.addEventListener('change', function () {
                if (this.checked) {
                    dropdownSection.classList.add('hidden');
                    manualInput.classList.remove('hidden');
                    document.getElementById('existingFileNo').value = '';
                    document.getElementById('existingFileNo').disabled = true;
                    document.getElementById('extensionManualFile').disabled = false;
                    updateCapturePreview();
                }
            });
        }
    }

    function setupTemporaryToggle() {
        var dropdownRadio = document.getElementById('temporaryDropdown');
        var manualRadio = document.getElementById('temporaryManual');
        var dropdownSection = document.getElementById('temporaryDropdownSection');
        var manualInput = document.getElementById('temporaryManualInput');

        if (document.getElementById('temporaryManualFile')) document.getElementById('temporaryManualFile').disabled = true;
        if (document.getElementById('temporaryFileNo')) document.getElementById('temporaryFileNo').disabled = false;

        if (dropdownRadio && manualRadio) {
            dropdownRadio.addEventListener('change', function () {
                if (this.checked) {
                    dropdownSection.classList.remove('hidden');
                    manualInput.classList.add('hidden');
                    document.getElementById('temporaryManualFile').value = '';
                    document.getElementById('temporaryManualFile').disabled = true;
                    document.getElementById('temporaryFileNo').disabled = false;
                    updateCapturePreview();
                }
            });
            manualRadio.addEventListener('change', function () {
                if (this.checked) {
                    dropdownSection.classList.add('hidden');
                    manualInput.classList.remove('hidden');
                    document.getElementById('temporaryFileNo').value = '';
                    document.getElementById('temporaryFileNo').disabled = true;
                    document.getElementById('temporaryManualFile').disabled = false;
                    updateCapturePreview();
                }
            });
        }
    }

    /* ── Preview ─────────────────────────────────────────────── */

    function updateCapturePreview() {
        var fileOption = document.getElementById('fileOption').value;
        var prefix = document.getElementById('prefix').value;
        var middlePrefix = document.getElementById('middlePrefix').value;
        var year = document.getElementById('year').value;
        var serialNo = document.getElementById('serialNo').value;
        var preview = document.getElementById('capturePreview');

        var previewText = '-';
        var extensionFileValue = '';
        var temporaryFileValue = '';

        if (fileOption === 'extension') {
            var extDrop = document.getElementById('extensionDropdown');
            if (extDrop && extDrop.checked) extensionFileValue = (document.getElementById('existingFileNo').value || '').trim();
            else extensionFileValue = (document.getElementById('extensionManualFile').value || '').trim();
        }

        if (fileOption === 'temporary') {
            var tmpDrop = document.getElementById('temporaryDropdown');
            if (tmpDrop && tmpDrop.checked) temporaryFileValue = (document.getElementById('temporaryFileNo').value || '').trim();
            else temporaryFileValue = (document.getElementById('temporaryManualFile').value || '').trim();
        }

        if (fileOption === 'extension' && extensionFileValue) {
            previewText = extensionFileValue.trim().replace(/-$/, '') + ' AND EXTENSION';
        } else if (fileOption === 'temporary' && temporaryFileValue) {
            previewText = temporaryFileValue + '(T)';
        } else if (fileOption === 'miscellaneous' && middlePrefix && serialNo && year) {
            previewText = 'MISC-' + middlePrefix + '-' + year + '-' + serialNo;
        } else if (fileOption === 'old_mls' && serialNo) {
            previewText = 'KN ' + serialNo;
        } else if (fileOption === 'sltr' && serialNo) {
            previewText = 'SLTR-' + serialNo;
        } else if (fileOption === 'sit' && serialNo && year) {
            previewText = 'SIT-' + year + '-' + serialNo;
        } else if (fileOption === 'normal' && prefix && year && serialNo) {
            previewText = prefix + '-' + year + '-' + serialNo.toString().trim();
        }

        preview.textContent = previewText;
        if (previewText !== '-') { preview.classList.remove('text-gray-400'); preview.classList.add('text-green-600'); }
        else { preview.classList.remove('text-green-600'); preview.classList.add('text-gray-400'); }

        var groupingTarget = null;
        if (fileOption === 'normal' && previewText !== '-') groupingTarget = previewText;
        else if (fileOption === 'extension' && extensionFileValue) groupingTarget = extensionFileValue;
        else if (fileOption === 'temporary' && temporaryFileValue) groupingTarget = temporaryFileValue;

        if (groupingTarget) scheduleGroupingLookup(groupingTarget);
        else if (fileOption === 'normal') resetTrackingIdState();
        else { resetTrackingIdState(); setTrackingIdDisplay('Will be generated on save'); }
    }

    /* ── Form submissions ────────────────────────────────────── */

    function submitCaptureForm(event) {
        event.preventDefault();
        var submitBtn = event.target.querySelector('button[type="submit"]');
        var originalText = submitBtn.innerHTML;
        showLoadingButton(submitBtn, originalText);
        showGlobalLoading('Capturing existing file number...');

        var formData = new FormData(document.getElementById('captureForm'));

        fetch(cfg.storeUrl, {
            method: 'POST',
            body: formData,
            headers: { 'X-CSRF-TOKEN': csrfToken() }
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            hideGlobalLoading();
            hideLoadingButton(submitBtn, originalText);
            if (data.success) {
                Swal.fire({ icon: 'success', title: 'Success!', text: data.message, confirmButtonColor: '#10b981' });
                closeCaptureModal();
                table.ajax.reload();
                updateTotalCount();
            } else {
                Swal.fire({ icon: 'error', title: 'Error!', text: data.message || 'An error occurred', confirmButtonColor: '#ef4444' });
            }
        })
        .catch(function (err) {
            hideGlobalLoading();
            hideLoadingButton(submitBtn, originalText);
            console.error('Error:', err);
            Swal.fire({ icon: 'error', title: 'Error!', text: 'An error occurred while capturing the file number', confirmButtonColor: '#ef4444' });
        });
    }

    function openMigrationModal() { document.getElementById('migrationModal').classList.remove('hidden'); }
    function closeMigrationModal() { document.getElementById('migrationModal').classList.add('hidden'); }

    function submitMigrationForm(event) {
        event.preventDefault();
        var submitBtn = event.target.querySelector('button[type="submit"]');
        var originalText = submitBtn.innerHTML;
        showLoadingButton(submitBtn, originalText);
        showGlobalLoading('Migrating data... Please wait.');

        var formData = new FormData(document.getElementById('migrationForm'));

        fetch(cfg.migrateUrl, {
            method: 'POST',
            body: formData,
            headers: { 'X-CSRF-TOKEN': csrfToken() }
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            hideGlobalLoading();
            hideLoadingButton(submitBtn, originalText);
            if (data.success) {
                Swal.fire({ icon: 'success', title: 'Success!', text: data.message, confirmButtonColor: '#10b981' });
                closeMigrationModal();
                table.ajax.reload();
                updateTotalCount();
            } else {
                Swal.fire({ icon: 'error', title: 'Error!', text: data.message || 'An error occurred during migration', confirmButtonColor: '#ef4444' });
            }
        })
        .catch(function (err) {
            hideGlobalLoading();
            hideLoadingButton(submitBtn, originalText);
            console.error('Error:', err);
            Swal.fire({ icon: 'error', title: 'Error!', text: 'An error occurred while migrating data', confirmButtonColor: '#ef4444' });
        });
    }

    /* ── Edit / Delete ───────────────────────────────────────── */

    function editRecord(id) {
        showGlobalLoading('Loading record details...');
        fetch(cfg.showUrl.replace(':id', id))
            .then(function (r) { return r.json(); })
            .then(function (data) {
                hideGlobalLoading();
                document.getElementById('editId').value = data.id;
                document.getElementById('editMlsfNo').value = data.mlsfNo || data.kangisFileNo || 'N/A';
                document.getElementById('editKangisFileNo').value = data.kangisFileNo || '';
                document.getElementById('editNewKangisFileNo').value = data.NewKANGISFileNo || '';
                document.getElementById('editFileName').value = data.FileName || '';
                document.getElementById('editModal').classList.remove('hidden');
            })
            .catch(function (err) {
                hideGlobalLoading();
                console.error('Error:', err);
                Swal.fire({ icon: 'error', title: 'Error!', text: 'Failed to load record details', confirmButtonColor: '#ef4444' });
            });
    }

    function closeEditModal() { document.getElementById('editModal').classList.add('hidden'); }

    function submitEditForm(event) {
        event.preventDefault();
        var submitBtn = event.target.querySelector('button[type="submit"]');
        var originalText = submitBtn.innerHTML;
        showLoadingButton(submitBtn, originalText);
        showGlobalLoading('Updating record...');

        var id = document.getElementById('editId').value;
        var formData = new FormData(document.getElementById('editForm'));

        fetch(cfg.updateUrl.replace(':id', id), {
            method: 'POST',
            body: formData,
            headers: { 'X-CSRF-TOKEN': csrfToken() }
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            hideGlobalLoading();
            hideLoadingButton(submitBtn, originalText);
            if (data.success) {
                Swal.fire({ icon: 'success', title: 'Success!', text: data.message, confirmButtonColor: '#10b981' });
                closeEditModal();
                table.ajax.reload();
            } else {
                Swal.fire({ icon: 'error', title: 'Error!', text: data.message || 'An error occurred', confirmButtonColor: '#ef4444' });
            }
        })
        .catch(function (err) {
            hideGlobalLoading();
            hideLoadingButton(submitBtn, originalText);
            console.error('Error:', err);
            Swal.fire({ icon: 'error', title: 'Error!', text: 'An error occurred while updating the record', confirmButtonColor: '#ef4444' });
        });
    }

    function deleteRecord(id) {
        Swal.fire({
            title: 'Are you sure?',
            text: "You won't be able to revert this!",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#ef4444',
            cancelButtonColor: '#6b7280',
            confirmButtonText: 'Yes, delete it!',
            showLoaderOnConfirm: true,
            preConfirm: function () {
                return fetch(cfg.destroyUrl.replace(':id', id), {
                    method: 'DELETE',
                    headers: { 'X-CSRF-TOKEN': csrfToken(), 'Content-Type': 'application/json' }
                })
                .then(function (r) { if (!r.ok) throw new Error(r.statusText); return r.json(); })
                .catch(function (err) { Swal.showValidationMessage('Request failed: ' + err); });
            },
            allowOutsideClick: function () { return !Swal.isLoading(); }
        }).then(function (result) {
            if (result.isConfirmed && result.value) {
                var data = result.value;
                if (data.success) {
                    Swal.fire({ icon: 'success', title: 'Deleted!', text: data.message, confirmButtonColor: '#10b981' });
                    table.ajax.reload();
                    updateTotalCount();
                } else {
                    Swal.fire({ icon: 'error', title: 'Error!', text: data.message || 'An error occurred', confirmButtonColor: '#ef4444' });
                }
            }
        });
    }

    /* ── Load existing file numbers for dropdowns ────────────── */

    function loadExistingFileNumbers() {
        fetch(cfg.existingUrl)
            .then(function (r) { return r.json(); })
            .then(function (data) {
                var extensionSelect = document.getElementById('existingFileNo');
                extensionSelect.innerHTML = '<option value="">Select existing file number...</option>';
                var temporarySelect = document.getElementById('temporaryFileNo');
                temporarySelect.innerHTML = '<option value="">Select existing file number...</option>';

                data.forEach(function (fileNo) {
                    var extOpt = document.createElement('option');
                    extOpt.value = fileNo.mlsfNo;
                    extOpt.textContent = fileNo.mlsfNo;
                    extensionSelect.appendChild(extOpt);

                    var tmpOpt = document.createElement('option');
                    tmpOpt.value = fileNo.mlsfNo;
                    tmpOpt.textContent = fileNo.mlsfNo;
                    temporarySelect.appendChild(tmpOpt);
                });

                updateCapturePreview();
            })
            .catch(function (err) { console.error('Error loading existing file numbers:', err); });
    }

    function updateTotalCount() {
        fetch(cfg.countUrl)
            .then(function (r) { return r.json(); })
            .then(function (data) { document.getElementById('totalCount').textContent = data.count; })
            .catch(function (err) { console.error('Error updating count:', err); });
    }

    /* ── Bootstrap ───────────────────────────────────────────── */

    $(document).ready(function () {
        lucide.createIcons();
        testDataEndpoint();
        initDataTable();
    });

    document.addEventListener('DOMContentLoaded', function () {
        document.getElementById('serialNo').addEventListener('input', updateCapturePreview);
        document.getElementById('year').addEventListener('input', updateCapturePreview);
        document.getElementById('prefix').addEventListener('change', updateCapturePreview);
        document.getElementById('middlePrefix').addEventListener('input', updateCapturePreview);
        document.getElementById('existingFileNo').addEventListener('change', updateCapturePreview);
        document.getElementById('extensionManualFile').addEventListener('input', updateCapturePreview);
        document.getElementById('temporaryFileNo').addEventListener('change', updateCapturePreview);
        document.getElementById('temporaryManualFile').addEventListener('input', updateCapturePreview);
        document.getElementById('fileOption').addEventListener('change', function () { updateCaptureForm(this.value); });

        var fileOption = document.getElementById('fileOption');
        if (fileOption) syncQuickFileOptionUI(fileOption.value || 'normal');

        var query = new URLSearchParams(window.location.search);
        var fromFfr = query.get('from_ffr');
        var sourceFileNo = (query.get('file_no') || '').trim();

        if (fromFfr === '1') {
            openCaptureModal();
            if (sourceFileNo) {
                var foSelect = document.getElementById('fileOption');
                var extManRadio = document.getElementById('extensionManual');
                var extManInput = document.getElementById('extensionManualFile');
                if (foSelect) { foSelect.value = 'extension'; updateCaptureForm('extension'); }
                if (extManRadio) { extManRadio.checked = true; extManRadio.dispatchEvent(new Event('change', { bubbles: true })); }
                if (extManInput) { extManInput.value = sourceFileNo; extManInput.dispatchEvent(new Event('input', { bubbles: true })); }
                updateCapturePreview();
            }
        }
    });

    /* ── Expose globals needed by inline onclick handlers ──── */
    window.openCaptureModal     = openCaptureModal;
    window.closeCaptureModal    = closeCaptureModal;
    window.syncCaptureFileOption = syncCaptureFileOption;
    window.updateCaptureForm    = updateCaptureForm;
    window.updateCapturePreview = updateCapturePreview;
    window.submitCaptureForm    = submitCaptureForm;
    window.openMigrationModal   = openMigrationModal;
    window.closeMigrationModal  = closeMigrationModal;
    window.submitMigrationForm  = submitMigrationForm;
    window.editRecord           = editRecord;
    window.closeEditModal       = closeEditModal;
    window.submitEditForm       = submitEditForm;
    window.deleteRecord         = deleteRecord;
    window.loadExistingFileNumbers = loadExistingFileNumbers;

})();
