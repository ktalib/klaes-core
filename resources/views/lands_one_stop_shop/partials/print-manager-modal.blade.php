{{-- ══════════════════════════════════════════════════════════════════
     Print Manager Modal — OSS OP Module
     Reusable partial for launching document prints for a single record.
     Included by: resources/views/lands_one_stop_shop/applications.blade.php
     Future modules: include this partial and wire openPrintManagerModal().
     ══════════════════════════════════════════════════════════════════ --}}

<div id="opPrintManagerModal" class="fixed inset-0 z-[1000070] hidden bg-slate-900/60 p-4">
    <div class="mx-auto mt-12 w-full max-w-2xl rounded-2xl border border-slate-200 bg-white shadow-2xl">

        {{-- Header --}}
        <div class="flex items-start justify-between gap-4 border-b border-slate-200 p-5">
            <div>
                <p class="text-[10px] font-black uppercase tracking-[0.3em] text-slate-400">Documents</p>
                <h3 class="mt-1 text-xl font-extrabold text-slate-900">Print Manager</h3>
                <p id="pmFileRef" class="mt-1 text-sm font-mono font-semibold text-slate-500"></p>
            </div>
            <button type="button" onclick="closePrintManagerModal()"
                class="rounded-lg p-1.5 text-slate-400 transition hover:bg-slate-100 hover:text-slate-700">
                <i data-lucide="x" class="h-5 w-5"></i>
            </button>
        </div>

        {{-- Print Options --}}
        <div class="p-5 grid grid-cols-2 gap-4">

            {{-- Verification --}}
            <button id="pmBtnVerification" type="button" onclick="pmLaunchVerification()"
                class="flex items-center gap-4 rounded-xl border border-teal-200 bg-teal-50 p-4 text-left transition hover:bg-teal-100 hover:shadow-sm disabled:cursor-not-allowed disabled:opacity-50">
                <div class="flex-shrink-0 w-10 h-10 rounded-lg bg-teal-100 flex items-center justify-center">
                    <i data-lucide="clipboard-check" class="w-5 h-5 text-teal-700"></i>
                </div>
                <div>
                    <p class="text-sm font-bold text-teal-900">Verification</p>
                    <p id="pmHintVerification" class="text-xs text-teal-600 mt-0.5">View &amp; print verification</p>
                </div>
            </button>

            {{-- Acknowledgement --}}
            <button id="pmBtnAcknowledgement" type="button" onclick="pmLaunchAcknowledgement()"
                class="flex items-center gap-4 rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-left transition hover:bg-emerald-100 hover:shadow-sm disabled:cursor-not-allowed disabled:opacity-50">
                <div class="flex-shrink-0 w-10 h-10 rounded-lg bg-emerald-100 flex items-center justify-center">
                    <i data-lucide="circle-check-big" class="w-5 h-5 text-emerald-700"></i>
                </div>
                <div>
                    <p class="text-sm font-bold text-emerald-900">Acknowledgement</p>
                    <p id="pmHintAcknowledgement" class="text-xs text-emerald-600 mt-0.5">View &amp; print acknowledgement</p>
                </div>
            </button>

            {{-- Recommendation --}}
            <button id="pmBtnRecommendation" type="button" onclick="pmLaunchRecommendation()"
                class="flex items-center gap-4 rounded-xl border border-blue-200 bg-blue-50 p-4 text-left transition hover:bg-blue-100 hover:shadow-sm disabled:cursor-not-allowed disabled:opacity-50">
                <div class="flex-shrink-0 w-10 h-10 rounded-lg bg-blue-100 flex items-center justify-center">
                    <i data-lucide="thumbs-up" class="w-5 h-5 text-blue-700"></i>
                </div>
                <div>
                    <p class="text-sm font-bold text-blue-900">Recommendation</p>
                    <p id="pmHintRecommendation" class="text-xs text-blue-600 mt-0.5">View &amp; print recommendation</p>
                </div>
            </button>

        </div>

        <div class="border-t border-slate-100 px-5 py-3 flex justify-end">
            <button type="button" onclick="closePrintManagerModal()"
                class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                Close
            </button>
        </div>
    </div>
</div>

<script>
var _pmCurrentRecord = null;
var _pmGeneratedState = {
    verification: false,
    acknowledgement: false,
    recommendation: false
};

function pmGetCaptureRecordId(data) {
    if (!data) return '';
    return (data.source_instrument_capture_id || data.id || '').toString().trim();
}

function pmGetFileReference(data) {
    if (!data) return '';
    return (data.file_no || data.mls_file_no || data.mlsfNo || '').toString().trim();
}

function pmSetOptionState(key, enabled, hint) {
    var buttonMap = {
        verification: 'pmBtnVerification',
        acknowledgement: 'pmBtnAcknowledgement',
        recommendation: 'pmBtnRecommendation'
    };
    var hintMap = {
        verification: 'pmHintVerification',
        acknowledgement: 'pmHintAcknowledgement',
        recommendation: 'pmHintRecommendation'
    };

    var btn = document.getElementById(buttonMap[key]);
    var hintEl = document.getElementById(hintMap[key]);
    if (btn) btn.disabled = !enabled;
    if (hintEl && hint) hintEl.textContent = hint;
}

function pmSetDefaultOptionStates() {
    _pmGeneratedState.verification = false;
    _pmGeneratedState.acknowledgement = false;
    _pmGeneratedState.recommendation = false;

    pmSetOptionState('verification', false, 'Enabled after verification is generated');
    pmSetOptionState('acknowledgement', false, 'Enabled after acknowledgement is generated');
    pmSetOptionState('recommendation', false, 'Enabled after recommendation is generated');
}

function pmLoadGeneratedStates(data) {
    if (!data) return;

    var recordId = pmGetCaptureRecordId(data);
    var fileRef = pmGetFileReference(data);

    if (recordId) {
        fetch('/lands-one-stop-shop/applications/verification-status?record_id=' + encodeURIComponent(recordId), {
            method: 'GET',
            headers: { 'Accept': 'application/json' }
        })
        .then(function (res) { return res.json(); })
        .then(function (result) {
            var ready = !!(result && result.success && result.data && result.data.exists);
            _pmGeneratedState.verification = ready;
            pmSetOptionState('verification', ready, ready ? 'View & print verification' : 'Enabled after verification is generated');
        })
        .catch(function () {
            _pmGeneratedState.verification = false;
            pmSetOptionState('verification', false, 'Unable to verify status right now');
        });
    }

    if (recordId) {
        var ackKey = 'oss_ack_generated_' + recordId;
        var ackReady = localStorage.getItem(ackKey) === '1';
        _pmGeneratedState.acknowledgement = ackReady;
        pmSetOptionState('acknowledgement', ackReady, ackReady ? 'View & print acknowledgement' : 'Enabled after acknowledgement is generated');
    }

    if (fileRef) {
        fetch('/lands-one-stop-shop/applications/recommendation-status?file_ref=' + encodeURIComponent(fileRef), {
            method: 'GET',
            headers: { 'Accept': 'application/json' }
        })
        .then(function (res) { return res.json(); })
        .then(function (result) {
            var ready = !!(result && result.success && result.data && result.data.exists);
            _pmGeneratedState.recommendation = ready;
            if (ready && _pmCurrentRecord) {
                _pmCurrentRecord.recommendation_id = result.data.id || null;
                _pmCurrentRecord._recData = result.data;
            }
            pmSetOptionState('recommendation', ready, ready ? 'View & print recommendation' : 'Enabled after recommendation is generated');
        })
        .catch(function () {
            _pmGeneratedState.recommendation = false;
            pmSetOptionState('recommendation', false, 'Unable to verify status right now');
        });
    }
}

/**
 * Open the Print Manager modal for a given record.
 * Accepts either a DOM button (inside a <tr data-record>) or a plain data object.
 */
function openPrintManagerModal(btn) {
    var data = _getRecordFromButton(btn);
    if (!data) {
        Swal.fire({ icon: 'warning', title: 'Missing Data', text: 'Could not read record details.' });
        return;
    }
    _pmCurrentRecord = data;
    var refEl = document.getElementById('pmFileRef');
    if (refEl) refEl.textContent = data.mls_file_no || data.file_no || '—';
    var modal = document.getElementById('opPrintManagerModal');
    if (modal) modal.classList.remove('hidden');

    pmSetDefaultOptionStates();
    pmLoadGeneratedStates(data);

    if (window.lucide) window.lucide.createIcons();
}

function closePrintManagerModal() {
    var modal = document.getElementById('opPrintManagerModal');
    if (modal) modal.classList.add('hidden');
    _pmCurrentRecord = null;
}

function pmLaunchVerification() {
    if (!_pmCurrentRecord) return;
    if (!_pmGeneratedState.verification) {
        Swal.fire({ icon: 'info', title: 'Verification Not Generated', text: 'Generate verification first, then use this print option.' });
        return;
    }

    var captureId = pmGetCaptureRecordId(_pmCurrentRecord);
    if (!captureId) {
        Swal.fire({ icon: 'warning', title: 'Missing Record', text: 'Could not resolve verification record.' });
        return;
    }

    closePrintManagerModal();
    window.open('/lands-one-stop-shop/applications/' + captureId + '/print-verification-view', '_blank');
}

function pmLaunchAcknowledgement() {
    if (!_pmCurrentRecord) return;
    if (!_pmGeneratedState.acknowledgement) {
        Swal.fire({ icon: 'info', title: 'Acknowledgement Not Generated', text: 'Generate acknowledgement first, then use this print option.' });
        return;
    }

    var ackId = pmGetCaptureRecordId(_pmCurrentRecord);
    if (!ackId) {
        Swal.fire({ icon: 'warning', title: 'Missing Record', text: 'Could not resolve acknowledgement record.' });
        return;
    }

    closePrintManagerModal();
    window.open('/lands-one-stop-shop/applications/' + ackId + '/print-acknowledgement', '_blank');
}

function pmLaunchRecommendation() {
    if (!_pmCurrentRecord) return;
    if (!_pmGeneratedState.recommendation) {
        Swal.fire({ icon: 'info', title: 'Recommendation Not Generated', text: 'Generate recommendation first, then use this print option.' });
        return;
    }

    var data = _pmCurrentRecord;
    var rd = data._recData || {};
    var fields = {
        applicant_name: (data.party_2 || data.file_title || '').toString().trim(),
        file_ref: (data.file_no || data.mls_file_no || '').toString().trim(),
        purpose: (data.land_use || '').toString().trim(),
        location: (data.property_description || data.location || '').toString().trim(),
        plot_no: (data.plot_no || '').toString().trim(),
        plan_no: (data.plan_no || data.tp_no || '').toString().trim(),
        term: rd.term || '',
        dev_value: rd.dev_value || '',
        completion_time: rd.completion_time || '',
        ground_rent: rd.ground_rent || '',
        dev_charge: rd.dev_charge || 'To Follow',
        survey_charges: rd.survey_charges || '',
        director_reasons: rd.director_reasons || '',
        ps_plot: (data.plot_no || '').toString().trim(),
        ps_location: (data.property_description || data.location || '').toString().trim()
    };

    closePrintManagerModal();

    var form = document.createElement('form');
    form.method = 'POST';
    form.action = '/lands-one-stop-shop/applications/print-recommendation';
    form.target = '_blank';
    var csrf = document.createElement('input');
    csrf.type = 'hidden'; csrf.name = '_token';
    csrf.value = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    form.appendChild(csrf);
    Object.keys(fields).forEach(function (key) {
        var input = document.createElement('input');
        input.type = 'hidden'; input.name = key; input.value = fields[key] || '';
        form.appendChild(input);
    });
    document.body.appendChild(form);
    form.submit();
    document.body.removeChild(form);
}

function pmLaunchCommissioningSheet() {
    if (!_pmCurrentRecord) return;
    var data = _pmCurrentRecord;
    closePrintManagerModal();

    var fileNo = (data.file_no || data.mls_file_no || data.mlsfNo || '').toString().trim();
    if (!fileNo) {
        Swal.fire({ icon: 'warning', title: 'Missing File Number', text: 'This record has no file number.' });
        return;
    }

    var csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    var payload = {
        file_number:      fileNo,
        file_name:        (data.party_2 || data.file_title || '').toString().trim(),
        name_or_allottee: (data.party_2 || data.file_title || '').toString().trim(),
        plot_number:      (data.plot_no || '').toString().trim(),
        tp_number:        (data.tp_no || data.plan_no || '').toString().trim(),
        location:         (data.location || data.property_description || data.district || '').toString().trim(),
        lga:              (data.lga || '').toString().trim()
    };

    fetch('{{ route("commissioning-sheet.generate-print") }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken,
            'Accept': 'application/json'
        },
        body: JSON.stringify(payload)
    })
    .then(function (res) { return res.json(); })
    .then(function (result) {
        if (!result || !result.success || !result.data || !result.data.id) {
            Swal.fire({ icon: 'error', title: 'Error', text: (result && result.message) ? result.message : 'Failed to prepare commissioning sheet.' });
            return;
        }
        var printUrl = '{{ url("commissioning-sheet/print") }}/' + result.data.id + '?source=oss';
        window.open(printUrl, '_blank');
    })
    .catch(function (err) {
        console.error('Commissioning sheet error:', err);
        Swal.fire({ icon: 'error', title: 'Error', text: 'Network error. Please try again.' });
    });
}
</script>
