@extends('survey_module.layouts.klaes')

@php
  use App\Models\Survey\SurveyPlanUpload as Plan;

  // Survey Update 5g-5i (user, 2026-11-10): one view for Survey Plan Upload and each unit's Uploaded Survey Plan.
  // CR 11112026-2: also SG/DSG > Signing and Upload (listScope 'signing') and MLPP-SG Portal Admin > Uploaded Survey ('portal').
  $listScope = $listScope ?? null;
  $scope = $unit === Plan::UNIT_GKN ? 'gkn' : ($unit === Plan::UNIT_EV ? 'ev' : $listScope);
  $listRoute = match ($scope) { 'gkn' => 'survey-module.gkn.uploaded-plans', 'ev' => 'survey-module.workflow.uploaded-plans',
      'signing' => 'survey-module.survey-plans.signing', 'portal' => 'survey-module.survey-plans.portal', default => 'survey-module.survey-plans.index' };
  $isList = $unit || $listScope;
  $PageTitle = match (true) {
      $listScope === 'signing' => 'Signing and Upload (Survey Plan)',
      $listScope === 'portal' => 'Uploaded Survey (MLPP-SG Portal)',
      (bool) $unit => 'Uploaded Survey Plan',
      default => 'Survey Plan Upload',
  };
  $PageDescription = match (true) {
      $listScope === 'signing' => 'Final copies waiting for the SG/DSG to sign (stage 3), and the signed plans (stage 4).',
      $listScope === 'portal' => 'Survey plans private surveyors sent from the MLPP-SG portal, at every stage.',
      (bool) $unit => 'Survey plans sent to ' . $unit . ' for Checking & Passing, from KLAES and the MLPP-SG portal.',
      default => 'Upload a survey plan and send it to GKN (public) or Examination/Verification (private) for checking.',
  };
  $stageTone = [1 => 'pending', 2 => 'rejected', 3 => 'review', 4 => 'approved'];
  $field = 'padding:8px 14px;border:1.5px solid var(--gray-300);border-radius:var(--radius-sm);font-family:var(--font);font-size:14px;background:#fff;';
  $svItem = 'flex items-center gap-2 w-full px-3 py-2 text-sm text-left text-gray-700 hover:bg-gray-50';
  $open = ! $isList && $errors->any();
  $filtered = request()->hasAny(['q', 'type', 'source', 'unit', 'stage']);
  $typeTone = ['Final Survey Plan' => 'completed', 'Application Plan' => 'pending'];
@endphp

@section('page-title', $PageTitle . ($unit ? ' — ' . $unit : '') . ' — KLAES')

@section('survey-content')
@include('survey_module.partials._flash')

<div class="kpi-grid">
    <div class="kpi-card"><div class="kpi-label">{{ $unit ? 'Received' : 'Uploaded' }}</div><div class="kpi-value">{{ number_format($stats['total']) }}</div></div>
    <div class="kpi-card"><div class="kpi-label">Final Survey Plans</div><div class="kpi-value">{{ number_format($stats['final']) }}</div></div>
    <div class="kpi-card"><div class="kpi-label">Application Plans</div><div class="kpi-value">{{ number_format($stats['app']) }}</div></div>
    <div class="kpi-card"><div class="kpi-label">From the MLPP-SG Portal</div><div class="kpi-value">{{ number_format($stats['portal']) }}</div></div>
</div>

<div class="page-header">
    <div style="font-size:13px;color:var(--gray-500);">{{ $listScope ? $PageDescription : ($unit ? 'Sent to ' . $unit . '.' : 'Every upload, with the unit it went to.') }}</div>
    <div style="display:flex;gap:8px;flex-wrap:wrap;">
        @include('survey_module.partials._records_export', ['endpoint' => route('survey-module.survey-plans.export', array_filter(['scope' => $scope])), 'title' => $unit ? 'Uploaded Survey Plan - ' . $unit : 'Survey Plan Uploads', 'dateLabel' => 'Date Uploaded'])
        {{-- A blank check: the coordinate system and the reference coordinate entered by hand (user, 2026-11-12). --}}
        <a class="btn btn-secondary" href="{{ route('survey-module.survey-plans.check') }}"><i class="fas fa-map-location-dot"></i> Check Survey Plan</a>
        @unless ($isList)
            <button type="button" class="btn btn-primary" onclick="document.getElementById('spuForm').classList.add('open')"><i class="fas fa-upload"></i> Upload Survey Plan</button>
        @endunless
    </div>
</div>

@unless ($isList)
{{-- The very short form (Survey Update 5h): where the plan is, who drew it, its type and the unit it goes to. --}}
<form method="POST" id="spuForm" action="{{ route('survey-module.survey-plans.store') }}" enctype="multipart/form-data"
      class="farmer-entry-form {{ $open ? 'open' : '' }}" data-sv-modal="Upload Survey Plan" data-sv-modal-size="xl">
    @csrf
    {{-- One card, no tabs (user, 2026-11-12, Survey Plan Check): 1 coordinate system, 2 reference coordinate,
         3 map, 4 other beacons, 5 plan details, 6 export. --}}
    @include('survey_module.survey_plans._plan_check', ['spcMode' => 'upload', 'spcSearchUrl' => route('survey-module.survey-plans.beacons')])
    <div class="spc" style="margin:18px 0 12px;"><div class="spc-group"><span class="n">5</span><span>Plan details</span></div></div>
    <div class="form-grid cols-3">
        <div class="sv-group"><i class="fas fa-file-upload"></i><span>Survey Plan</span></div>
        <div class="form-group">
            <label>Type <span class="required">*</span></label>
            <select name="plan_type" required>
                <option value="">— Select —</option>
                @foreach (Plan::TYPES as $t)<option value="{{ $t }}" @selected(old('plan_type') === $t)>{{ $t }}</option>@endforeach
            </select>
        </div>
        <div class="form-group">
            <label>Send To <span class="required">*</span></label>
            <select name="unit" required>
                <option value="">— Select unit —</option>
                @foreach (Plan::UNITS as $u => $label)<option value="{{ $u }}" @selected(old('unit') === $u)>{{ $label }}</option>@endforeach
            </select>
            <p class="helper-text">GKN for public plans, Examination/Verification for private.</p>
        </div>
        <div class="form-group">
            <label>Plan File <span class="required">*</span></label>
            <input type="file" name="plan_file" id="spuFile" accept=".pdf,.jpg,.jpeg,.png,.tif,.tiff" required>
            <p class="helper-text">PDF, JPG, PNG or TIFF, up to 25 MB.</p>
        </div>
        {{-- Preview of the chosen plan, before it is saved (user, 2026-11-10). Read from the browser only. --}}
        <div id="spuPreview" hidden style="grid-column:1 / -1;border:1px solid #e5e7eb;border-radius:8px;background:#f9fafb;padding:10px 12px;">
            <div style="display:flex;align-items:center;justify-content:space-between;gap:8px;margin-bottom:8px;">
                <span style="font-size:13px;font-weight:600;color:#374151;"><i class="fas fa-eye" style="margin-right:6px;color:#6b7280;"></i>Preview: <span id="spuPreviewName"></span></span>
                <button type="button" id="spuPreviewClear" class="btn btn-secondary" style="padding:4px 10px;font-size:12px;"><i class="fas fa-times"></i> Remove file</button>
            </div>
            <div id="spuPreviewBody"></div>
        </div>

        <div class="sv-group"><i class="fas fa-map-marker-alt"></i><span>Location of the Plan</span></div>
        <div class="form-group">
            <label>Plot No.</label>
            <input type="text" name="plot_no" maxlength="100" value="{{ old('plot_no') }}">
        </div>
        <div class="form-group">
            <label>District <span class="required">*</span></label>
            <select name="district" id="spuDistrict" required>
                <option value="">— Select district —</option>
                @foreach ($districts as $d)<option value="{{ $d }}" @selected(old('district') === $d)>{{ $d }}</option>@endforeach
                <option value="other" @selected(old('district') === 'other')>Other (specify)</option>
            </select>
            <input type="text" name="district_other" id="spuDistrictOther" maxlength="150" placeholder="Specify the district *" value="{{ old('district_other') }}"
                   style="margin-top:6px;{{ old('district') === 'other' ? '' : 'display:none;' }}" @disabled(old('district') !== 'other') @required(old('district') === 'other')>
        </div>
        <div class="form-group">
            <label>LGA <span class="required">*</span></label>
            <select name="lga" required>
                <option value="">— Select LGA —</option>
                @foreach ($lgas as $l)<option value="{{ $l }}" @selected(old('lga') === $l)>{{ $l }}</option>@endforeach
            </select>
        </div>

        <div class="sv-group"><i class="fas fa-user-edit"></i><span>Drawn By</span></div>
        <div class="form-group">
            <label>Who drew the plan <span class="required">*</span></label>
            <select name="drawn_by" id="spuDrawn" required>
                <option value="">— Select surveyor —</option>
                @foreach ($surveyors as $s)<option value="{{ $s->id }}" @selected(old('drawn_by') === (string) $s->id)>{{ $s->full_name }}{{ $s->rank ? ' · ' . $s->rank : '' }}</option>@endforeach
                <option value="other" @selected(old('drawn_by') === 'other')>Other (specify)</option>
            </select>
            <input type="text" name="drawn_by_other" id="spuDrawnOther" maxlength="255" placeholder="Type the name *" value="{{ old('drawn_by_other') }}"
                   style="margin-top:6px;{{ old('drawn_by') === 'other' ? '' : 'display:none;' }}" @disabled(old('drawn_by') !== 'other') @required(old('drawn_by') === 'other')>
        </div>
        <div class="form-group" style="grid-column:span 2;">
            <label>Remarks</label>
            <textarea name="remarks" rows="2" maxlength="1000">{{ old('remarks') }}</textarea>
        </div>
    </div>
    <div style="margin-top:18px;">@include('survey_module.survey_plans._plan_check_export', ['spcExportNo' => 6])</div>
    <div class="form-actions">
        <div class="left"><a class="btn btn-secondary" href="{{ route('survey-module.survey-plans.index') }}"><i class="fas fa-times"></i> Close</a></div>
        <div class="right"><button class="btn btn-success" type="submit"><i class="fas fa-paper-plane"></i> Upload and Send</button></div>
    </div>
</form>
@endunless

<form method="GET" class="table-toolbar">
    <div class="left" style="flex-wrap:wrap;">
        <input type="text" name="q" value="{{ $term }}" placeholder="Ref, plot no., district, LGA, drawn by…" style="{{ $field }}width:300px;" />
        <select name="type" style="{{ $field }}">
            <option value="">All types</option>
            @foreach (Plan::TYPES as $t)<option value="{{ $t }}" @selected(request('type') === $t)>{{ $t }}</option>@endforeach
        </select>
        @unless ($unit)
            <select name="unit" style="{{ $field }}">
                <option value="">All units</option>
                @foreach (Plan::UNITS as $u => $label)<option value="{{ $u }}" @selected(request('unit') === $u)>{{ $u }}</option>@endforeach
            </select>
        @endunless
        <select name="stage" style="{{ $field }}">
            <option value="">All stages</option>
            @foreach (Plan::STAGES as $n => $label)<option value="{{ $n }}" @selected(request('stage') === (string) $n)>{{ $n }} · {{ $label }}</option>@endforeach
        </select>
        <select name="source" style="{{ $field }}">
            <option value="">KLAES and portal</option>
            <option value="klaes" @selected(request('source') === 'klaes')>KLAES</option>
            <option value="portal" @selected(request('source') === 'portal')>MLPP-SG Portal</option>
        </select>
        <button class="btn btn-secondary" type="submit"><i class="fas fa-search"></i> Search</button>
        @if ($filtered)<a class="btn btn-outline btn-sm" href="{{ route($listRoute) }}">Clear</a>@endif
    </div>
</form>

<div class="table-wrapper">
    <div class="table-scroll">
        <table>
            <thead><tr>
                <th>S/N</th><th>Upload Ref</th><th>Type</th>@unless ($unit)<th>Sent To</th>@endunless
                <th>Plot No.</th><th>District</th><th>LGA</th><th>Location</th><th>Drawn By</th><th>From</th><th>Uploaded By</th><th>Uploaded</th><th>Stage</th><th></th>
            </tr></thead>
            <tbody>
                @forelse ($rows as $row)
                    <tr>
                        <td>{{ $rows->firstItem() + $loop->index }}</td>
                        <td><strong style="font-family:monospace;">{{ $row->upload_ref }}</strong></td>
                        <td><span class="status-badge {{ $typeTone[$row->plan_type] ?? 'pending' }}"><span class="dot"></span>{{ $row->plan_type }}</span></td>
                        @unless ($unit)<td>{{ $row->unit }}</td>@endunless
                        <td>{{ $row->plot_no ?: '—' }}</td>
                        <td>{{ $row->district ?: '—' }}</td>
                        <td>{{ $row->lga ?: '—' }}</td>
                        <td>{{ $row->location ?: '—' }}</td>
                        <td>{{ $row->drawn_by }}</td>
                        <td>{{ $row->source_label }}</td>
                        <td>{{ $row->uploaded_by_name ?: '—' }}</td>
                        <td style="white-space:nowrap;">{{ $row->created_at?->format('d M Y') }}</td>
                        <td style="white-space:nowrap;"><span class="status-badge {{ $stageTone[$row->stage_no] ?? 'pending' }}"><span class="dot"></span>{{ $row->stage_no }} · {{ $row->stage_label }}</span>
                            <div style="font-size:12px;color:var(--gray-500);">{{ $row->stage_status }}</div></td>
                        <td>
                            <div class="inline-block text-left" x-data="{ open: false, top: 0, left: 0 }" @keydown.escape.window="open = false">
                                <button type="button" x-ref="btn" title="Actions" aria-label="Actions"
                                        @click.stop="const r = $refs.btn.getBoundingClientRect(); top = r.bottom + 4; left = Math.max(8, r.right - 224); open = !open"
                                        class="inline-flex items-center justify-center w-8 h-8 rounded-full text-gray-600 hover:bg-gray-100">
                                    <i data-lucide="more-vertical" class="w-5 h-5"></i>
                                </button>
                                <div x-show="open" x-cloak @click.outside="open = false" @scroll.window="open = false"
                                     :style="'top:' + top + 'px; left:' + left + 'px'"
                                     class="fixed z-50 w-56 rounded-lg border border-gray-200 bg-white shadow-lg py-1">
                                    <a href="{{ route('survey-module.survey-plans.show', $row) }}" class="{{ $svItem }}" @click="open = false">
                                        <i data-lucide="layers" class="w-4 h-4 text-gray-500"></i> Open (stages)
                                    </a>
                                    <a href="{{ route('survey-module.survey-plans.check-plan', $row) }}" class="{{ $svItem }}" @click="open = false">
                                        <i data-lucide="map-pinned" class="w-4 h-4 text-gray-500"></i> Check survey plan
                                    </a>
                                    <a href="{{ route('survey-module.survey-plans.file', $row) }}" target="_blank" rel="noopener" class="{{ $svItem }}" @click="open = false">
                                        <i data-lucide="file-text" class="w-4 h-4 text-gray-500"></i> Open Plan
                                    </a>
                                </div>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="{{ $unit ? 13 : 14 }}" style="text-align:center;padding:28px;color:var(--gray-500);">
                        <i class="fas fa-folder-open" style="font-size:24px;display:block;margin-bottom:8px;opacity:.4;"></i>
                        {{ $filtered ? 'No upload matches the search.' : ($unit ? 'No survey plan has been sent to ' . $unit . ' yet.' : 'No survey plans yet. Use Upload Survey Plan.') }}
                    </td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="table-footer">
        <span>Showing {{ $rows->firstItem() ?? 0 }}–{{ $rows->lastItem() ?? 0 }} of {{ number_format($rows->total()) }}</span>
        <div class="pagination">{{ $rows->links() }}</div>
    </div>
</div>
@endsection

@push('scripts')
<script>
// The reference coordinate is required: submitting without it opens its Beacon No. list.
(function () {
    var form = document.getElementById('spuForm');
    if (form) form.addEventListener('submit', function (e) {
        var no = form.querySelector('#spc-refRows select.spc-b'), val = form.querySelector('input[name="beacons[0][beacon_no]"]');
        if (no && val && !val.value && no.value !== '__other') {
            e.preventDefault();
            no.closest('.spc-bt').scrollIntoView({ block: 'center' });
            if (window.jQuery && jQuery(no).data('select2')) jQuery(no).select2('open');
        }
    }, true);
})();

// "Other (specify)" reveals a required box; any other choice hides, clears and disables it.
(function () {
    [['spuDistrict', 'spuDistrictOther'], ['spuDrawn', 'spuDrawnOther']].forEach(function (pair) {
        var pick = document.getElementById(pair[0]), box = document.getElementById(pair[1]);
        if (!pick || !box) return;
        var sync = function () {
            var on = pick.value === 'other';
            box.style.display = on ? '' : 'none';
            box.disabled = !on; box.required = on;
            if (!on) box.value = '';
        };
        pick.addEventListener('change', sync);
        if (window.jQuery) window.jQuery(pick).on('change', sync);   // select2 on long lists
    });
})();

// Preview the chosen plan before it is saved: PDF in a viewer, JPG/PNG as an image. Browsers cannot
// draw TIFF, so a TIFF shows its name and size only. Nothing is uploaded until Upload and Send.
(function () {
    var input = document.getElementById('spuFile'), box = document.getElementById('spuPreview');
    if (!input || !box) return;
    var body = document.getElementById('spuPreviewBody'), name = document.getElementById('spuPreviewName');
    var MAX = 25 * 1024 * 1024, url = null;
    var size = function (b) { return b >= 1048576 ? (b / 1048576).toFixed(1) + ' MB' : Math.max(1, Math.round(b / 1024)) + ' KB'; };
    var note = function (text, warn) {
        var p = document.createElement('p');
        p.style.cssText = 'margin:0;font-size:13px;color:' + (warn ? '#b91c1c' : '#4b5563') + ';';
        p.textContent = text;
        return p;
    };
    var reset = function () {
        if (url) { URL.revokeObjectURL(url); url = null; }
        body.innerHTML = ''; name.textContent = ''; box.hidden = true;
    };
    input.addEventListener('change', function () {
        reset();
        var f = input.files && input.files[0];
        if (!f) return;
        var ext = (f.name.split('.').pop() || '').toLowerCase();
        name.textContent = f.name + ' (' + size(f.size) + ')';
        box.hidden = false;
        if (f.size > MAX) { body.appendChild(note('This file is larger than 25 MB and will be refused. Choose a smaller file.', true)); return; }
        url = URL.createObjectURL(f);
        if (ext === 'pdf') {
            var frame = document.createElement('iframe');
            frame.src = url; frame.title = 'Survey plan preview';
            frame.style.cssText = 'width:100%;height:480px;border:1px solid #d1d5db;border-radius:6px;background:#fff;';
            body.appendChild(frame);
        } else if (['jpg', 'jpeg', 'png'].indexOf(ext) !== -1) {
            var img = document.createElement('img');
            img.src = url; img.alt = 'Survey plan preview';
            img.style.cssText = 'display:block;max-width:100%;max-height:480px;margin:0 auto;border:1px solid #d1d5db;border-radius:6px;background:#fff;';
            body.appendChild(img);
        } else if (ext === 'tif' || ext === 'tiff') {
            URL.revokeObjectURL(url); url = null;
            body.appendChild(note('TIFF files cannot be shown in the browser. Check the file name above; the plan is saved as it is.'));
        } else {
            URL.revokeObjectURL(url); url = null;
            body.appendChild(note('This file type is not accepted. Choose a PDF, JPG, PNG or TIFF.', true));
        }
    });
    document.getElementById('spuPreviewClear').addEventListener('click', function () { input.value = ''; reset(); });
})();
</script>
@endpush
