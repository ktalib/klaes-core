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
      $listScope === 'portal' => 'Uploaded Survey (KN-MLPP-SG Portal)',
      (bool) $unit => 'Uploaded Survey Plan',
      default => 'Survey Plan Management',
  };
  $PageDescription = match (true) {
      $listScope === 'signing' => 'Final copies waiting for the SG/DSG to sign (stage 3), and the signed plans (stage 4).',
      $listScope === 'portal' => 'Survey plans private surveyors sent from the KN-MLPP-SG portal, at every stage.',
      (bool) $unit => 'Survey plans sent to ' . $unit . ' for Checking & Passing, from KLAES and the KN-MLPP-SG portal.',
      default => 'Manual Upload (a scanned or hard copy plan) or Digital Plotting (the plan plotted on the map), sent to GKN (public) or Examination/Verification (private) for checking.',
  };
  // A table each for Manual Upload and Digital Plotting (Recording 5, 2026-11-16).
  $methodTab = fn ($m) => route($listRoute, array_merge(request()->except(['method', 'page']), ['method' => $m]));
  $stageTone = [1 => 'pending', 2 => 'rejected', 3 => 'review', 4 => 'approved'];
  $field = 'padding:8px 14px;border:1.5px solid var(--gray-300);border-radius:var(--radius-sm);font-family:var(--font);font-size:14px;background:#fff;';
  $svItem = 'flex items-center gap-2 w-full px-3 py-2 text-sm text-left text-gray-700 hover:bg-gray-50';
  $open = ! $isList && $errors->any() && old('plan_method') !== Plan::METHOD_DIGITAL;
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
    <div class="kpi-card"><div class="kpi-label">From the KN-MLPP-SG Portal</div><div class="kpi-value">{{ number_format($stats['portal']) }}</div></div>
</div>

<div class="page-header">
    <div style="font-size:13px;color:var(--gray-500);">{{ $listScope ? $PageDescription : ($unit ? 'Sent to ' . $unit . '.' : 'Every upload, with the unit it went to.') }}</div>
    <div style="display:flex;gap:8px;flex-wrap:wrap;">
        @include('survey_module.partials._records_export', ['endpoint' => route('survey-module.survey-plans.export', array_filter(['scope' => $scope, 'method' => $method])), 'title' => $unit ? 'Uploaded Survey Plan - ' . $unit : 'Survey Plan Uploads', 'dateLabel' => 'Date Uploaded'])
        {{-- Check Survey Plan is no longer offered here (Recording 5, 2026-11-16: "so it's not confusing"); it stays where
             plans are checked. Two ways in: Manual Upload (this page's pop-up) and Digital Plotting (its own page). --}}
        @unless ($isList)
            {{-- Each tab shows only its own button (user, 2026-11-16). --}}
            @if ($method === Plan::METHOD_DIGITAL)
            <a class="btn btn-primary" style="background:#0f766e;border-color:#0f766e;" href="{{ route('survey-module.survey-plans.digital') }}"><i class="fas fa-map-location-dot"></i> Digital Plotting</a>
            @else
            <button type="button" class="btn btn-primary" onclick="document.getElementById('spuForm').classList.add('open')"><i class="fas fa-upload"></i> Manual Upload</button>
            @endif
        @endunless
    </div>
</div>

@unless ($isList)
{{-- The very short form (Survey Update 5h): where the plan is, who drew it, its type and the unit it goes to. --}}
<form method="POST" id="spuForm" action="{{ route('survey-module.survey-plans.store') }}" enctype="multipart/form-data"
      class="farmer-entry-form {{ $open ? 'open' : '' }}" data-sv-modal="Manual Upload: Survey Plan" data-sv-modal-size="xl" data-sv-modal-static>
    @csrf
    <input type="hidden" name="plan_method" value="manual">
    {{-- Manual Upload (Recording 5, 2026-11-16): 1 coordinate system, 2 reference beacon, 3 plan details with the file.
         No map, other beacons or export: those are on Digital Plotting. --}}
    @include('survey_module.survey_plans._manual_reference', ['mrSearchUrl' => route('survey-module.survey-plans.beacons')])
    @include('survey_module.survey_plans._plan_details_klaes', ['no' => 3, 'withFile' => true])
    <div class="form-actions">
        <div class="left"><a class="btn btn-secondary" href="{{ route('survey-module.survey-plans.index') }}"><i class="fas fa-times"></i> Close</a></div>
        <div class="right"><button class="btn btn-success" type="submit"><i class="fas fa-paper-plane"></i> Upload and Send</button></div>
    </div>
</form>
@endunless

@include('survey_module.survey_plans._application_plan_form')

<style>
    .spm-tabs { display: flex; gap: 4px; border-bottom: 2px solid #e5e7eb; margin: 4px 0 12px; flex-wrap: wrap; }
    .spm-tabs a { display: inline-flex; align-items: center; gap: 8px; padding: 10px 16px; margin-bottom: -2px; border-bottom: 2px solid transparent; font-weight: 700; font-size: 13.5px; color: #64748b; text-decoration: none; border-radius: 8px 8px 0 0; }
    .spm-tabs a:hover { color: #1d4ed8; background: #f8fafc; }
    .spm-tabs a.on { color: #1d4ed8; border-bottom-color: #1d4ed8; background: #eff6ff; }
    .spm-tabs .c { font-size: 11px; padding: 1px 8px; border-radius: 999px; background: #e2e8f0; color: #334155; }
    .spm-tabs a.on .c { background: #1d4ed8; color: #fff; }
</style>
<nav class="spm-tabs" aria-label="Survey plan method">
    @foreach (Plan::METHODS as $m => $label)
        <a href="{{ $methodTab($m) }}" class="{{ $method === $m ? 'on' : '' }}" @if ($method === $m) aria-current="page" @endif>
            <i class="fas {{ $m === Plan::METHOD_DIGITAL ? 'fa-map-location-dot' : 'fa-file-arrow-up' }}"></i> {{ $label }} <span class="c">{{ number_format($methodCounts[$m]) }}</span>
        </a>
    @endforeach
</nav>

<form method="GET" class="table-toolbar">
    <input type="hidden" name="method" value="{{ $method }}">
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
            @foreach (Plan::STAGE_STATUS as $n => $label)<option value="{{ $n }}" @selected(request('stage') === (string) $n)>{{ $label }}</option>@endforeach
        </select>
        <select name="source" style="{{ $field }}">
            <option value="">KLAES and portal</option>
            <option value="klaes" @selected(request('source') === 'klaes')>KLAES</option>
            <option value="portal" @selected(request('source') === 'portal')>KN-MLPP-SG Portal</option>
        </select>
        <button class="btn btn-secondary" type="submit"><i class="fas fa-search"></i> Search</button>
        @if ($filtered)<a class="btn btn-outline btn-sm" href="{{ route($listRoute, ['method' => $method]) }}">Clear</a>@endif
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
                        <td style="white-space:nowrap;"><span class="status-badge {{ $stageTone[$row->stage_no] ?? 'pending' }}"><span class="dot"></span>{{ $row->step_no }} · {{ $row->stage_label }}</span>
                            <div style="font-size:12px;color:var(--gray-500);">{{ $row->stage_status }}</div></td>
                        <td>
                            <div class="inline-block text-left" x-data="{ open: false, top: 0, left: 0 }" @keydown.escape.window="open = false">
                                {{-- Near the foot of the window the menu opens upwards (user, 2026-11-18). --}}
                                <button type="button" x-ref="btn" title="Actions" aria-label="Actions"
                                        @click.stop="const r = $refs.btn.getBoundingClientRect(); top = r.bottom + 4; left = Math.max(8, r.right - 224); open = !open;
                                                     $nextTick(() => { const h = $refs.menu.offsetHeight; if (top + h > window.innerHeight - 8) top = Math.max(8, r.top - h - 4); })"
                                        class="inline-flex items-center justify-center w-8 h-8 rounded-full text-gray-600 hover:bg-gray-100">
                                    <i data-lucide="more-vertical" class="w-5 h-5"></i>
                                </button>
                                <div x-show="open" x-ref="menu" x-cloak @click.outside="open = false" @scroll.window="open = false"
                                     :style="'top:' + top + 'px; left:' + left + 'px'"
                                     class="fixed z-50 w-56 rounded-lg border border-gray-200 bg-white shadow-lg py-1">
                                    {{-- One way in (Kunle, 2026-11-18): Open Stages; the map, the plan file, the plotted data and the
                                         traverse all open from the stages page. Application Plan stays here. --}}
                                    <a href="{{ route('survey-module.survey-plans.show', $row) }}" class="{{ $svItem }}" @click="open = false">
                                        <i data-lucide="layers" class="w-4 h-4 text-gray-500"></i> Open Stages
                                    </a>
                                    @if (\App\Models\Survey\SurveyApplicationPlan::installed())
                                    <button type="button" class="{{ $svItem }} w-full text-left" @click="open = false; spOpenApplicationPlan($el)"
                                            data-ap-ref="{{ $row->upload_ref }}" data-ap-save="{{ route('survey-module.survey-plans.application-plan.save', $row) }}"
                                            data-ap="{{ json_encode(\App\Models\Survey\SurveyApplicationPlan::entriesFor($row)) }}">
                                        <i data-lucide="file-badge" class="w-4 h-4 text-gray-500"></i> Application Plan
                                    </button>
                                    @endif
                                </div>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="{{ $unit ? 13 : 14 }}" style="text-align:center;padding:28px;color:var(--gray-500);">
                        <i class="fas fa-folder-open" style="font-size:24px;display:block;margin-bottom:8px;opacity:.4;"></i>
                        {{ $filtered ? 'No upload matches the search.' : ($unit ? 'No ' . strtolower(Plan::METHODS[$method]) . ' has been sent to ' . $unit . ' yet.' : 'No ' . strtolower(Plan::METHODS[$method]) . ' yet. Use ' . Plan::METHODS[$method] . '.') }}
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
