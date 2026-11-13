{{-- A survey plan's stage tracker, details, reference beacons and file history (CR 11112026-2), shared by the
     KLAES plan page and the MLPP-SG portal. Self-styled. $plan; $originalUrl (the first upload);
     $fileUrl: fn (SurveyPlanFile) => url. --}}
@php
  use App\Models\Survey\SurveyPlanUpload as SpPlan;
  $spNow = $plan->stage_no;
  // KLAES plan page shows Details / Reference Beacons / Files as tabs (user, 2026-11-12); the portal keeps cards.
  $spTabs = $spTabs ?? false;
  $spSize = fn ($b) => $b ? ($b >= 1048576 ? number_format($b / 1048576, 1) . ' MB' : max(1, round($b / 1024)) . ' KB') : '';
@endphp
<style>
  .sp-card { background: #fff; border: 1px solid #e5e7eb; border-radius: 10px; padding: 16px; }
  .sp-track { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 8px; }
  @media (max-width: 700px) { .sp-track { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
  .sp-step { border: 1px solid #e5e7eb; border-radius: 8px; padding: 10px 12px; background: #f9fafb; }
  .sp-step .k { font-size: 11px; text-transform: uppercase; letter-spacing: .06em; color: #6b7280; }
  .sp-step .t { font-weight: 700; margin-top: 2px; color: #374151; }
  .sp-step.done { background: #ecfdf5; border-color: #a7f3d0; }
  .sp-step.done .t { color: #065f46; }
  .sp-step.now { background: #eff6ff; border-color: #3b82f6; box-shadow: 0 0 0 2px #bfdbfe; }
  .sp-step.now .t { color: #1d4ed8; }
  .sp-kv { display: grid; grid-template-columns: repeat(auto-fit, minmax(170px, 1fr)); gap: 10px 16px; }
  .sp-kv div span { display: block; font-size: 11px; text-transform: uppercase; letter-spacing: .05em; color: #6b7280; }
  .sp-kv div strong { font-weight: 600; color: #111827; word-break: break-word; }
  .sp-tbl { width: 100%; border-collapse: collapse; font-size: 13px; }
  .sp-tbl th { text-align: left; font-size: 11px; text-transform: uppercase; letter-spacing: .05em; color: #6b7280; padding: 8px; border-bottom: 1px solid #e5e7eb; white-space: nowrap; }
  .sp-tbl td { padding: 8px; border-bottom: 1px solid #f3f4f6; vertical-align: top; }
  .sp-mono { font-family: ui-monospace, Consolas, monospace; font-size: 12px; }
  .sp-h { font-size: 15px; font-weight: 700; margin: 0 0 10px; color: #111827; }
  .sp-link { color: #1d4ed8; font-weight: 600; text-decoration: none; }
  .sp-link:hover { text-decoration: underline; }
  .sp-tabin { position: absolute; opacity: 0; pointer-events: none; }
  .sp-tabbar { display: flex; gap: 4px; border-bottom: 2px solid #e5e7eb; flex-wrap: wrap; }
  .sp-tabbar label { display: inline-flex; align-items: center; gap: 8px; padding: 10px 16px; margin-bottom: -2px; border-bottom: 2px solid transparent; font-weight: 700; font-size: 13px; color: #64748b; cursor: pointer; border-radius: 8px 8px 0 0; }
  .sp-tabbar label:hover { color: #1d4ed8; background: #f8fafc; }
  .sp-tabbar .c { font-size: 11px; font-weight: 700; padding: 1px 8px; border-radius: 999px; background: #e2e8f0; color: #334155; }
  .sp-pane { display: none; }
  #spTab1:checked ~ .sp-pane-1, #spTab2:checked ~ .sp-pane-2, #spTab3:checked ~ .sp-pane-3 { display: grid; gap: 12px; }
  #spTab1:checked ~ .sp-tabbar label[for=spTab1], #spTab2:checked ~ .sp-tabbar label[for=spTab2], #spTab3:checked ~ .sp-tabbar label[for=spTab3] { color: #1d4ed8; border-bottom-color: #1d4ed8; background: #eff6ff; }
  #spTab1:checked ~ .sp-tabbar label[for=spTab1] .c, #spTab2:checked ~ .sp-tabbar label[for=spTab2] .c, #spTab3:checked ~ .sp-tabbar label[for=spTab3] .c { background: #1d4ed8; color: #fff; }
  #spTab1:focus-visible ~ .sp-tabbar label[for=spTab1], #spTab2:focus-visible ~ .sp-tabbar label[for=spTab2], #spTab3:focus-visible ~ .sp-tabbar label[for=spTab3] { outline: 2px solid #93c5fd; }
</style>

<div class="sp-card" style="display:grid;gap:14px;">
  @if ($spTabs)
    <input type="radio" name="sp_tab" id="spTab1" class="sp-tabin" checked>
    <input type="radio" name="sp_tab" id="spTab2" class="sp-tabin">
    <input type="radio" name="sp_tab" id="spTab3" class="sp-tabin">
  @endif
  <div class="sp-track" aria-label="Stages">
    @foreach (SpPlan::STAGES as $n => $label)
      <div class="sp-step {{ $n < $spNow || $spNow === SpPlan::STAGE_SIGNED ? 'done' : ($n === $spNow ? 'now' : '') }}" @if ($n === $spNow) aria-current="step" @endif>
        <div class="k">Stage {{ $n }}</div>
        <div class="t">{{ $label }}</div>
      </div>
    @endforeach
  </div>
  <div style="font-size:13px;color:#374151;"><strong>Now:</strong> {{ $plan->stage_status }}@if ($plan->signing_method) · signed {{ $plan->signing_method === SpPlan::SIGN_DIGITAL ? 'digitally' : 'by hand' }}@endif</div>
  @if ($spTabs)
    <div class="sp-tabbar" role="tablist">
      <label for="spTab1" role="tab"><i class="fas fa-circle-info"></i> Details</label>
      <label for="spTab2" role="tab"><i class="fas fa-location-crosshairs"></i> Reference Coordinate <span class="c">{{ $plan->referenceBeacons->count() }}</span></label>
      <label for="spTab3" role="tab"><i class="fas fa-folder-open"></i> Files <span class="c">{{ $plan->files->count() + 1 }}</span></label>
    </div>
    <div class="sp-pane sp-pane-1">
  @endif
  <div class="sp-kv">
    <div><span>Upload Ref</span><strong class="sp-mono">{{ $plan->upload_ref }}</strong></div>
    <div><span>Type</span><strong>{{ $plan->plan_type }}</strong></div>
    <div><span>Sent To</span><strong>{{ $plan->unit }}</strong></div>
    <div><span>Plot No.</span><strong>{{ $plan->plot_no ?: '—' }}</strong></div>
    <div><span>District</span><strong>{{ $plan->district ?: '—' }}</strong></div>
    <div><span>LGA</span><strong>{{ $plan->lga ?: '—' }}</strong></div>
    <div><span>Location</span><strong>{{ $plan->location ?: '—' }}</strong></div>
    <div><span>Drawn By</span><strong>{{ $plan->drawn_by }}</strong></div>
    <div><span>Coordinate System</span><strong>{{ $plan->coord_system_label ?: '—' }}</strong></div>
    <div><span>Uploaded</span><strong>{{ $plan->created_at?->format('d M Y H:i') }} · {{ $plan->uploaded_by_name }} ({{ $plan->source_label }})</strong></div>
  </div>
  @if ($plan->remarks)<div style="font-size:13px;color:#374151;"><strong>Remarks:</strong> {{ $plan->remarks }}</div>@endif
@if ($spTabs)
  </div>
@else
</div>
@endif

<div class="{{ $spTabs ? 'sp-pane sp-pane-2' : 'sp-card' }}">
  @unless ($spTabs)<h3 class="sp-h">Reference Coordinate</h3>@endunless
  <div style="overflow-x:auto;">
    <table class="sp-tbl">
      <thead><tr><th>S/N</th><th>Beacon No.</th><th>X (Easting)</th><th>Y (Northing)</th><th>From</th></tr></thead>
      <tbody>
        @forelse ($plan->referenceBeacons as $b)
          <tr>
            <td>{{ $loop->iteration }}</td>
            <td class="sp-mono">{{ $b->beacon_no }}</td>
            <td class="sp-mono">{{ $b->coord_x }}</td>
            <td class="sp-mono">{{ $b->coord_y }}</td>
            <td>{{ ['captured' => 'Beacon Register', 'extant' => 'Extant Beacons list', 'typed' => 'Typed'][$b->source] ?? $b->source }}</td>
          </tr>
        @empty
          <tr><td colspan="5" style="color:#6b7280;">No reference beacons: this plan was uploaded before they were asked for.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>

<div class="{{ $spTabs ? 'sp-pane sp-pane-3' : 'sp-card' }}">
  @unless ($spTabs)<h3 class="sp-h">Files</h3>@endunless
  <div style="overflow-x:auto;">
    <table class="sp-tbl">
      <thead><tr><th>S/N</th><th>Stage</th><th>File</th><th>Note</th><th>By</th><th>Date</th><th></th></tr></thead>
      <tbody>
        <tr>
          <td>1</td><td>1 · Upload</td>
          <td>{{ $plan->file_name ?: 'Plan' }} <span style="color:#6b7280;">{{ $spSize($plan->file_size) }}</span></td>
          <td>—</td><td>{{ $plan->uploaded_by_name }}</td><td style="white-space:nowrap;">{{ $plan->created_at?->format('d M Y H:i') }}</td>
          <td><a class="sp-link" href="{{ $originalUrl }}" target="_blank" rel="noopener">Download</a></td>
        </tr>
        @foreach ($plan->files as $f)
          <tr>
            <td>{{ $loop->iteration + 1 }}</td>
            <td style="white-space:nowrap;">{{ $f->stage }} · {{ $f->kind_label }}</td>
            <td>{{ $f->file_name ?: 'File' }} <span style="color:#6b7280;">{{ $spSize($f->file_size) }}</span></td>
            <td style="max-width:360px;">{{ $f->note ?: '—' }}</td>
            <td>{{ $f->by_name }}</td>
            <td style="white-space:nowrap;">{{ $f->created_at?->format('d M Y H:i') }}</td>
            <td><a class="sp-link" href="{{ $fileUrl($f) }}" target="_blank" rel="noopener">Download</a></td>
          </tr>
        @endforeach
      </tbody>
    </table>
  </div>
</div>
@if ($spTabs)
</div>
@endif
