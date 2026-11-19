{{-- Application Plan pop-up (user, 2026-11-17): opened from a row's menu before printing. The entries KLAES already
     holds for the upload come filled in (plot, location, map sheet, drawn by); the rest are typed here.
     Save & Print stores them (survey_application_plans) and opens the printout in a new tab, filled in.
     Each row carries its entries in data-ap (SurveyApplicationPlan::entriesFor); spOpenApplicationPlan() loads them. --}}
@php
    use App\Models\Survey\SurveyApplicationPlan as AP;
    $apPlanNos = AP::planNoChoices();
    // Surveyed by / Checked by / Passed by: the Survey staff list, or Other (user, 2026-11-18).
    $apPeople = AP::peopleChoices();
@endphp
{{-- The file number takes two of the three columns here, not the whole row (user, 2026-11-19). --}}
<style>
    #apPicker { grid-column: span 2 !important; }
    @media (max-width: 640px) { #apPicker { grid-column: 1 / -1 !important; } }
</style>
<form method="POST" id="apForm" action="" target="_blank" class="farmer-entry-form"
      data-sv-modal="Application Plan" data-sv-modal-size="lg" data-sv-modal-static>
    @csrf
    <p class="helper-text" style="margin:0 0 12px;">
        <b id="apRef"></b> · What KLAES already has is filled in. Fill in the rest, then <b>Save &amp; Print</b>:
        the details are kept for this plan and printed on the form. Blank entries print as lines to fill by hand.
    </p>
    <div class="form-grid cols-3">
        <div class="sv-group"><i class="fas fa-user"></i><span>Application</span></div>
        {{-- The land file, from the file number selector (never typed); the QR carries its tracking ID (user, 2026-11-18). --}}
        {{-- Name of Applicant comes from the picked file's title (user, 2026-11-19). --}}
        @include('survey_module.partials._file_picker', ['id' => 'ap', 'name' => 'file_number', 'value' => null, 'label' => 'File Number', 'fill' => ['title' => 'apApplicant']])
        <div class="form-group"><label>Serial No. (Code sur C of B)</label><input type="text" name="serial_no" maxlength="120"></div>
        {{-- Purpose: the four land uses, or Other with the purpose typed (user, 2026-11-17). --}}
        <div class="form-group">
            <label>Purpose (For …)</label>
            <select name="purpose" data-ap-other="apPurposeOther" data-no-search>
                <option value="">— Select —</option>
                @foreach (AP::PURPOSES as $pu)<option value="{{ $pu }}">{{ $pu }}</option>@endforeach
                <option value="{{ AP::OTHER }}">Other</option>
            </select>
        </div>
        <div class="form-group" id="apPurposeOther"><label>Specify purpose <span class="text-red-500">*</span></label><input type="text" name="purpose_other" maxlength="300"></div>
        {{-- Filled from the file and locked; the pen opens it for a correction kept on this plan only. Indexing and the
             other records are not changed from here (user, 2026-11-19). --}}
        <div class="form-group"><label>Name of Applicant</label>
            <div style="display:flex;gap:6px;align-items:stretch;">
                <input type="text" name="applicant" id="apApplicant" maxlength="120" style="flex:1;min-width:0;">
                <button type="button" id="apApplicantEdit" class="btn btn-outline btn-sm" title="Edit the name on this plan" aria-label="Edit the name on this plan"><i class="fas fa-pen"></i></button>
            </div>
        </div>
        <div class="form-group" style="grid-column: span 2;"><label>Address</label><input type="text" name="address" maxlength="300"></div>

        <div class="sv-group"><i class="fas fa-map-marker-alt"></i><span>Plot and plan</span></div>
        <div class="form-group"><label>Plot No.</label><input type="text" name="plot_no" maxlength="120"></div>
        <div class="form-group" style="grid-column: span 2;"><label>Location</label><input type="text" name="location" maxlength="300"></div>
        <div class="form-group"><label>Map Sheet</label><input type="text" name="map_sheet" maxlength="120"></div>
        <div class="form-group">
            <label>Scale</label>
            <select name="scale">
                <option value="">Auto (largest that fits the frame)</option>
                @foreach (AP::SCALES as $sc)<option value="{{ $sc }}">1:{{ number_format($sc) }}</option>@endforeach
            </select>
        </div>
        {{-- Plan No. = the TP No.: the layout register's TP numbers (type to search), or Other with the number typed (user, 2026-11-17). --}}
        <div class="form-group">
            <label>Plan No.</label>
            <select name="plan_no" class="sv-searchable" data-ap-other="apPlanNoOther">
                <option value="">— Select —</option>
                @foreach ($apPlanNos as $pn)<option value="{{ $pn }}">{{ $pn }}</option>@endforeach
                <option value="{{ AP::OTHER }}">Other</option>
            </select>
        </div>
        <div class="form-group" id="apPlanNoOther"><label>Specify Plan No. <span class="text-red-500">*</span></label><input type="text" name="plan_no_other" maxlength="120"></div>
        <div class="form-group"><label>MS No.</label><input type="text" name="ms_no" maxlength="120"></div>
        <div class="form-group" style="grid-column: span 2;"><label>Reference</label><input type="text" name="reference" maxlength="120"></div>

        <div class="sv-group"><i class="fas fa-border-all"></i><span>Ground</span></div>
        <div class="form-group">
            <label>The ground is open</label>
            <select name="ground_open" id="apGround">
                <option value="">— Select —</option>
                <option value="Yes">Yes</option>
                <option value="No">No</option>
            </select>
        </div>
        <div class="form-group ap-lies"><label>(i) Layout Plan No.</label><input type="text" name="layout_plan_no" maxlength="120"></div>
        <div class="form-group ap-lies"><label>(ii) Government Item No.</label><input type="text" name="government_item_no" maxlength="120"></div>
        <div class="form-group ap-lies"><label>(iii) Certificate of Occupancy No.</label><input type="text" name="cofo_no" maxlength="120"></div>
        <div class="form-group ap-lies"><label>(iv) Application Plan No.</label><input type="text" name="application_plan_no" maxlength="120"></div>

        <div class="sv-group"><i class="fas fa-user-check"></i><span>Survey and office use</span></div>
        <div class="form-group"><label>Surveyed by</label>
            <select name="surveyed_by" data-ap-other="apSurveyedByOther" data-placeholder="Search name…">
                <option value="">— Select —</option>
                @foreach ($apPeople as $pn)<option value="{{ $pn }}">{{ $pn }}</option>@endforeach
                <option value="{{ AP::OTHER }}">Other</option>
            </select></div>
        <div class="form-group" id="apSurveyedByOther"><label>Specify Surveyed by <span class="text-red-500">*</span></label><input type="text" name="surveyed_by_other" maxlength="120"></div>
        {{-- Drawn by is the surveyor too, as a rule (user, 2026-11-18): filled from Surveyed by and locked while
             "Same as Surveyed by" is ticked; untick it when someone else drew the plan. --}}
        <div class="form-group"><label>Drawn by</label><input type="text" name="drawn_by" maxlength="120">
            <label style="display:flex;gap:6px;align-items:center;font-size:12.5px;font-weight:500;margin-top:4px;"><input type="checkbox" id="apDrawnSame" checked> Same as Surveyed by</label></div>
        <div class="form-group"><label>Checked by</label>
            <select name="checked_by" data-ap-other="apCheckedByOther" data-placeholder="Search name…">
                <option value="">— Select —</option>
                @foreach ($apPeople as $pn)<option value="{{ $pn }}">{{ $pn }}</option>@endforeach
                <option value="{{ AP::OTHER }}">Other</option>
            </select></div>
        <div class="form-group" id="apCheckedByOther"><label>Specify Checked by <span class="text-red-500">*</span></label><input type="text" name="checked_by_other" maxlength="120"></div>
        <div class="form-group"><label>Amount Paid (₦)</label><input type="number" name="amount_paid" min="0" step="0.01"></div>
        <div class="form-group"><label>Receipt No.</label><input type="text" name="receipt_no" maxlength="120"></div>
        <div class="form-group"><label>Passed by</label>
            <select name="passed_by" data-ap-other="apPassedByOther" data-placeholder="Search name…">
                <option value="">— Select —</option>
                @foreach ($apPeople as $pn)<option value="{{ $pn }}">{{ $pn }}</option>@endforeach
                <option value="{{ AP::OTHER }}">Other</option>
            </select></div>
        <div class="form-group" id="apPassedByOther"><label>Specify Passed by <span class="text-red-500">*</span></label><input type="text" name="passed_by_other" maxlength="120"></div>
        <div class="form-group"><label>Rank</label><input type="text" name="rank" maxlength="120"></div>
        <div class="form-group"><label>Date</label><input type="date" name="date"></div>
    </div>
    <div class="form-actions">
        <div class="left"><button type="button" class="btn btn-secondary" onclick="document.getElementById('apForm').classList.remove('open')"><i class="fas fa-times"></i> Close</button></div>
        <div class="right"><button class="btn btn-success" type="submit"><i class="fas fa-print"></i> Save &amp; Print</button></div>
    </div>
</form>
<script>
(function () {
    var form = document.getElementById('apForm');
    if (!form) return;
    var ground = document.getElementById('apGround'), current = null;
    // The four "if No it lies on" entries show only when the ground is not open.
    var lies = function () {
        var no = ground.value === 'No';
        form.querySelectorAll('.ap-lies').forEach(function (g) {
            g.style.display = no ? '' : 'none';
            g.querySelector('input').disabled = !no;
        });
    };
    ground.addEventListener('change', lies);
    // "Other" reveals its required specify box (development standard 6).
    var OTHER = @json(AP::OTHER);
    var others = function () {
        form.querySelectorAll('[data-ap-other]').forEach(function (sel) {
            var box = document.getElementById(sel.getAttribute('data-ap-other')), inp = box.querySelector('input'), on = sel.value === OTHER;
            box.style.display = on ? '' : 'none';
            inp.disabled = !on; inp.required = on;
        });
    };
    form.querySelectorAll('[data-ap-other]').forEach(function (sel) { sel.addEventListener('change', others); });
    if (window.jQuery) jQuery(form).on('change', '[data-ap-other]', others);
    others();
    // Drawn by follows Surveyed by while "Same as Surveyed by" is ticked (user, 2026-11-18).
    var surveyedName = function () {
        var sel = form.querySelector('[name="surveyed_by"]');
        return sel.value === OTHER ? form.querySelector('[name="surveyed_by_other"]').value.trim() : sel.value;
    };
    var drawnSync = function () {
        var same = document.getElementById('apDrawnSame').checked, drawn = form.querySelector('[name="drawn_by"]');
        drawn.readOnly = same;
        drawn.style.background = same ? '#f8fafc' : '';
        if (same && surveyedName() !== '') drawn.value = surveyedName();
    };
    document.getElementById('apDrawnSame').addEventListener('change', drawnSync);
    form.querySelector('[name="surveyed_by"]').addEventListener('change', drawnSync);
    form.querySelector('[name="surveyed_by_other"]').addEventListener('input', drawnSync);
    if (window.jQuery) jQuery(form).on('change', '[name="surveyed_by"]', drawnSync);
    // The pen: unlock Name of Applicant to correct it. The change is saved on this plan only.
    var apApplicant = document.getElementById('apApplicant');
    var apLockApplicant = function (locked) {
        apApplicant.readOnly = locked;
        apApplicant.style.background = locked ? 'var(--gray-100)' : '';
    };
    document.getElementById('apApplicantEdit').addEventListener('click', function () {
        apLockApplicant(false);
        apApplicant.focus();
        apApplicant.select();
    });
    // Fill the form from a row: its saved entries, or what the upload already holds. Clears the last row's values.
    window.spOpenApplicationPlan = function (btn) {
        current = btn;
        var d = {};
        try { d = JSON.parse(btn.getAttribute('data-ap') || '{}'); } catch (e) {}
        form.action = btn.getAttribute('data-ap-save');
        document.getElementById('apRef').textContent = btn.getAttribute('data-ap-ref') || '';
        form.querySelectorAll('input[name], select[name]').forEach(function (el) {
            if (el.name === '_token' || /_other$/.test(el.name)) return;
            var val = d[el.name] !== undefined && d[el.name] !== null ? String(d[el.name]) : '';
            if (el.hasAttribute('data-ap-other')) {
                // A saved value that is not on the list shows as Other, with it in the specify box.
                // Any case: names are saved in title case, the list holds them as written.
                var hit = val === '' ? null : Array.prototype.find.call(el.options, function (o) { return o.value !== OTHER && o.value.toLowerCase() === val.toLowerCase(); });
                var listed = val === '' || !!hit;
                el.value = hit ? hit.value : (listed ? val : OTHER);
                form.querySelector('[name="' + el.name + '_other"]').value = listed ? '' : val;
            } else {
                el.value = val;
            }
        });
        lies();
        others();
        // The applicant follows the plan's file number: its title from the records, locked (user, 2026-11-19).
        // A name already saved on the plan (perhaps corrected with the pen) is kept, locked; a blank one is looked up.
        if (window.svFilePicker) {
            window.svFilePicker.reset('ap');
            var apNo = (d.file_number || '').trim();
            if (apNo !== '' && (d.applicant || '').trim() === '') window.svFilePicker.apply('ap', apNo);
            else apLockApplicant(apNo !== '');
        }
        // Same as Surveyed by: ticked unless a different name is already saved.
        var dv = form.querySelector('[name="drawn_by"]').value.trim().toLowerCase(), sv = surveyedName().toLowerCase();
        document.getElementById('apDrawnSame').checked = dv === '' || sv === '' || dv === sv;
        drawnSync();
        window.svModal ? window.svModal.open('apForm') : form.classList.add('open');
    };
    // The printout opens in a new tab; keep what was typed on the row, so reopening shows it, and close the pop-up.
    form.addEventListener('submit', function () {
        if (!current) return;
        var d = {};
        form.querySelectorAll('input[name], select[name]').forEach(function (el) {
            if (el.name === '_token' || /_other$/.test(el.name)) return;
            d[el.name] = el.disabled ? '' : el.value;
            if (el.hasAttribute('data-ap-other') && el.value === OTHER) d[el.name] = form.querySelector('[name="' + el.name + '_other"]').value.trim();
        });
        current.setAttribute('data-ap', JSON.stringify(d));
        setTimeout(function () { form.classList.remove('open'); }, 50);
    });
})();
</script>
