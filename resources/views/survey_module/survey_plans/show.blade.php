@extends('survey_module.layouts.klaes')

@php
  use App\Models\Survey\SurveyPlanUpload as Plan;
  use App\Models\Survey\SurveyPlanFile as PlanFile;

  // A survey plan's page (CR 11112026-2, item 5): its four stages and the action open at each.
  $PageTitle = 'Survey Plan ' . $plan->upload_ref;
  $PageDescription = $plan->plan_type . ' · ' . $plan->unit . ' · ' . $plan->stage_status;
  $stage = $plan->stage_no;
  $original = route('survey-module.survey-plans.file', $plan);
  $fileUrl = fn ($f) => route('survey-module.survey-plans.stage-file', [$plan, $f]);
  $final = $plan->latestFile(PlanFile::KIND_FINAL);
  $signed = $plan->latestFile(PlanFile::KIND_SIGNED);
  $corrections = $plan->files->where('kind', PlanFile::KIND_CORRECTION);
  $finalUrl = $final ? $fileUrl($final) : null;
  $ext = strtolower(pathinfo((string) $plan->file_name, PATHINFO_EXTENSION));
  $drawable = in_array($ext, ['pdf', 'jpg', 'jpeg', 'png'], true);
@endphp

@section('page-title', $PageTitle . ' — KLAES')

@section('survey-content')
@include('survey_module.partials._flash')
@if ($errors->any())
  <div style="margin-bottom:12px;padding:10px 14px;border:1px solid #fecaca;background:#fef2f2;color:#991b1b;border-radius:8px;font-size:13px;">
    @foreach ($errors->all() as $e)<div>{{ $e }}</div>@endforeach
  </div>
@endif

<style>
  .sp-grid2 { display: grid; grid-template-columns: minmax(0, 1.7fr) minmax(0, 1fr); grid-template-rows: auto auto; gap: 14px; }
  .sp-grid2 .sp-download { grid-row: span 2; }
  .sp-grid4 { display: grid; grid-template-columns: minmax(0, 1fr) minmax(0, 1fr); gap: 14px; }
  @media (max-width: 1000px) { .sp-grid2, .sp-grid4 { grid-template-columns: minmax(0, 1fr); } .sp-grid2 .sp-download { grid-row: auto; } }
  .sp-box { border: 1px solid #e5e7eb; border-radius: 14px; padding: 16px; background: #fff; display: grid; gap: 12px; align-content: start; min-width: 0;
            box-shadow: 0 1px 2px rgba(16,24,40,.05), 0 4px 14px rgba(16,24,40,.05); position: relative; }
  .sp-box::before { content: ""; position: absolute; left: 0; top: 0; right: 0; height: 4px; border-radius: 14px 14px 0 0; background: linear-gradient(90deg, #1d4ed8, #0ea5e9); }
  .sp-box.tone-green::before { background: linear-gradient(90deg, #059669, #34d399); }
  .sp-box.tone-violet::before { background: linear-gradient(90deg, #7c3aed, #a78bfa); }
  .sp-box > .h { font-weight: 800; letter-spacing: .06em; text-transform: uppercase; font-size: 13px; color: #0f172a; display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
  .sp-box > .h .n { display: inline-grid; place-items: center; width: 26px; height: 26px; border-radius: 8px; background: #1d4ed8; color: #fff; font-size: 12px; box-shadow: 0 2px 6px rgba(29,78,216,.35); }
  .sp-box.tone-green > .h .n { background: #059669; box-shadow: 0 2px 6px rgba(5,150,105,.35); }
  .sp-box.tone-violet > .h .n { background: #7c3aed; box-shadow: 0 2px 6px rgba(124,58,237,.35); }
  .sp-box > .h .sub { margin-left: auto; font-weight: 600; letter-spacing: 0; text-transform: none; font-size: 12px; color: #64748b; }
  .sp-box.done { border-color: #a7f3d0; background: #f0fdf4; }
  .sp-box.done > .h .n { background: #10b981; }
  .sp-box.off { opacity: .55; }
  .sp-box p { margin: 0; font-size: 13px; color: #475569; line-height: 1.5; }
  .sp-box textarea, .sp-box input[type=file], .sp-box select { width: 100%; font-size: 13px; }
  .sp-box textarea { padding: 10px; border: 1.5px solid #d1d5db; border-radius: 10px; min-height: 90px; }
  .sp-box textarea:focus { outline: none; border-color: #1d4ed8; box-shadow: 0 0 0 3px #dbeafe; }
  .sp-drop { border: 2px dashed #cbd5e1; border-radius: 12px; padding: 14px; background: #f8fafc; text-align: center; font-size: 13px; color: #475569; }
  .sp-drop i { font-size: 22px; color: #64748b; display: block; margin-bottom: 6px; }

  /* Markup board (user, 2026-11-12: bigger, full screen, colour swatches, better tools). */
  .sp-work { display: grid; gap: 10px; }
  .sp-bar { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; padding: 8px; border-radius: 12px; background: #0f172a; color: #e2e8f0; }
  .sp-grp { display: inline-flex; align-items: center; gap: 2px; padding: 3px; border-radius: 10px; background: rgba(255,255,255,.08); }
  .sp-grp[hidden] { display: none; }
  .sp-ib { width: 34px; height: 34px; display: inline-grid; place-items: center; border: 0; border-radius: 8px; background: transparent; color: #cbd5e1; cursor: pointer; font-size: 14px; }
  .sp-ib:hover { background: rgba(255,255,255,.14); color: #fff; }
  .sp-ib.on { background: #2563eb; color: #fff; box-shadow: 0 2px 8px rgba(37,99,235,.5); }
  .sp-ib[disabled] { opacity: .35; cursor: default; }
  .sp-ib .dot { display: block; border-radius: 50%; background: currentColor; }
  .sp-sw { width: 24px; height: 24px; border-radius: 50%; border: 2px solid rgba(255,255,255,.3); cursor: pointer; padding: 0; margin: 0 3px; transition: transform .1s; }
  .sp-sw:hover { transform: scale(1.12); }
  .sp-sw.on { border-color: #fff; box-shadow: 0 0 0 2px #60a5fa; transform: scale(1.15); }
  .sp-zoom { min-width: 46px; text-align: center; font-size: 12px; font-weight: 700; color: #e2e8f0; padding: 0 4px; }
  .sp-bar .sp-spacer { flex: 1; }
  .sp-full-btn { display: inline-flex; align-items: center; gap: 6px; height: 34px; padding: 0 12px; border: 0; border-radius: 8px; background: #f8fafc; color: #0f172a; font-weight: 700; font-size: 12px; cursor: pointer; }
  .sp-full-btn:hover { background: #fff; }
  .sp-stage { position: relative; border-radius: 12px; overflow: auto; height: 70vh; min-height: 420px;
              background: #e2e8f0 radial-gradient(#cbd5e1 1px, transparent 1px) 0 0 / 16px 16px; box-shadow: inset 0 0 0 1px #cbd5e1; }
  .sp-stage .sp-canvas-wrap { position: relative; margin: 16px auto; box-shadow: 0 6px 24px rgba(15,23,42,.18); background: #fff; }
  .sp-stage canvas { display: block; max-width: none; }
  .sp-stage #spInk { position: absolute; left: 0; top: 0; cursor: crosshair; touch-action: none; }
  .sp-stage.erase #spInk { cursor: cell; }
  .sp-hint { font-size: 12px; color: #64748b; display: flex; gap: 14px; flex-wrap: wrap; }
  .sp-hint kbd { font: 11px ui-monospace, Consolas, monospace; padding: 1px 5px; border: 1px solid #cbd5e1; border-bottom-width: 2px; border-radius: 4px; background: #fff; color: #334155; }
  .sp-work.full { position: fixed; inset: 0; z-index: 2000; background: #0b1220; padding: 12px; grid-template-rows: auto 1fr auto; }
  .sp-work.full .sp-stage { height: auto; min-height: 0; }
  .sp-work.full .sp-hint { color: #94a3b8; }
  .sp-work .sp-full-only { display: none; }
  .sp-work.full .sp-full-only { display: inline-flex; }
  body.sp-noscroll { overflow: hidden; }

  .sp-thumbs { display: grid; grid-template-columns: repeat(auto-fill, minmax(120px, 1fr)); gap: 10px; }
  .sp-thumbs:empty { display: none; }
  .sp-thumbs figure { margin: 0; border: 1px solid #e2e8f0; border-radius: 10px; padding: 6px; background: #fff; font-size: 11px; text-align: center; box-shadow: 0 1px 3px rgba(0,0,0,.06); }
  .sp-thumbs img { display: block; width: 100%; height: 90px; object-fit: contain; background: #f8fafc; border-radius: 6px; }
  .sp-empty { font-size: 12px; color: #94a3b8; padding: 12px; border: 1px dashed #e2e8f0; border-radius: 10px; text-align: center; }
  .sp-method { display: inline-flex; border: 1px solid #d1d5db; border-radius: 10px; overflow: hidden; }
  .sp-method label { padding: 8px 16px; cursor: pointer; font-weight: 600; font-size: 13px; }
  .sp-method input { position: absolute; opacity: 0; }
  .sp-method input:checked + span { color: #fff; }
  .sp-method label:has(input:checked) { background: #1d4ed8; color: #fff; }
  .sp-section { display: grid; gap: 14px; margin-top: 18px; }
  .sp-section > h3 { margin: 0; font-size: 17px; font-weight: 800; color: #0f172a; display: flex; align-items: center; gap: 10px; }
  .sp-section > h3 .ic { width: 34px; height: 34px; border-radius: 10px; display: inline-grid; place-items: center; background: linear-gradient(135deg, #1d4ed8, #0ea5e9); color: #fff; font-size: 15px; }
  .sp-section > h3 small { font-weight: 500; font-size: 13px; color: #64748b; }
  .sp-pass { border: 1.5px dashed #86efac; background: linear-gradient(135deg, #f0fdf4, #ffffff); }
  .sp-pass::before { display: none; }
</style>

<div style="display:flex;justify-content:space-between;align-items:center;gap:8px;flex-wrap:wrap;margin-bottom:12px;">
  <a class="btn btn-secondary" href="{{ url()->previous() !== url()->current() ? url()->previous() : route('survey-module.survey-plans.index') }}"><i class="fas fa-arrow-left"></i> Back</a>
  <a class="btn btn-primary" href="{{ route('survey-module.survey-plans.check-plan', $plan) }}"><i class="fas fa-map-location-dot"></i> Check survey plan</a>
</div>

<div style="display:grid;gap:12px;">
  @include('survey_module.survey_plans._plan_summary', ['originalUrl' => $original, 'spTabs' => true])

  {{-- ============ Stage 1 -> 2 / 3: the checking unit ============ --}}
  @if ($canCheck && in_array($stage, [Plan::STAGE_UPLOADED, Plan::STAGE_CORRECTION], true))
    <div class="sp-section">
      <h3><span class="ic"><i class="fas fa-ruler-combined"></i></span> Check the plan <small>{{ $plan->unit }}</small></h3>
      <form method="POST" action="{{ route('survey-module.survey-plans.corrections', $plan) }}" enctype="multipart/form-data" id="spCorrForm">
        @csrf
        <div class="sp-grid2">
          <div class="sp-box sp-download">
            <div class="h"><span class="n">1</span> Download
              <span class="sub"><a class="btn btn-secondary btn-sm" href="{{ $original }}" target="_blank" rel="noopener"><i class="fas fa-download"></i> Download plan</a></span>
            </div>
            <p>Download the plan as uploaded, or mark it up on screen below. Use <strong>Full screen</strong> to see the whole plan.</p>
            @if ($drawable)
              <div class="sp-work" id="spWork">
                <div class="sp-bar" id="spTools" role="toolbar" aria-label="Markup tools">
                  <div class="sp-grp" aria-label="Tools">
                    <button type="button" class="sp-ib on" data-tool="pen" title="Pen (P)" aria-label="Pen"><i class="fas fa-pen"></i></button>
                    <button type="button" class="sp-ib" data-tool="hl" title="Highlighter (H)" aria-label="Highlighter"><i class="fas fa-highlighter"></i></button>
                    <button type="button" class="sp-ib" data-tool="line" title="Line (L) · hold Shift for straight angles" aria-label="Line"><i class="fas fa-minus"></i></button>
                    <button type="button" class="sp-ib" data-tool="arrow" title="Arrow (A)" aria-label="Arrow"><i class="fas fa-arrow-right-long"></i></button>
                    <button type="button" class="sp-ib" data-tool="box" title="Box (B)" aria-label="Box"><i class="far fa-square"></i></button>
                    <button type="button" class="sp-ib" data-tool="ellipse" title="Circle (C)" aria-label="Circle"><i class="far fa-circle"></i></button>
                    <button type="button" class="sp-ib" data-tool="text" title="Text (T)" aria-label="Text"><i class="fas fa-font"></i></button>
                    <button type="button" class="sp-ib" data-tool="erase" title="Eraser (E): click a mark to remove it" aria-label="Eraser"><i class="fas fa-eraser"></i></button>
                  </div>
                  <div class="sp-grp" role="radiogroup" aria-label="Colour" id="spColors">
                    @foreach (['#dc2626' => 'Red', '#2563eb' => 'Blue', '#16a34a' => 'Green', '#ea580c' => 'Orange', '#9333ea' => 'Purple', '#111827' => 'Black'] as $hex => $name)
                      <button type="button" class="sp-sw {{ $loop->first ? 'on' : '' }}" data-color="{{ $hex }}" style="background:{{ $hex }}" title="{{ $name }}" aria-label="{{ $name }}" role="radio" aria-checked="{{ $loop->first ? 'true' : 'false' }}"></button>
                    @endforeach
                  </div>
                  <div class="sp-grp" aria-label="Thickness" id="spWidths">
                    <button type="button" class="sp-ib" data-w="2" title="Thin" aria-label="Thin"><span class="dot" style="width:5px;height:5px"></span></button>
                    <button type="button" class="sp-ib on" data-w="4" title="Medium" aria-label="Medium"><span class="dot" style="width:9px;height:9px"></span></button>
                    <button type="button" class="sp-ib" data-w="8" title="Thick" aria-label="Thick"><span class="dot" style="width:14px;height:14px"></span></button>
                  </div>
                  <div class="sp-grp" aria-label="History">
                    <button type="button" class="sp-ib" id="spUndo" title="Undo (Ctrl+Z)" aria-label="Undo"><i class="fas fa-rotate-left"></i></button>
                    <button type="button" class="sp-ib" id="spRedo" title="Redo (Ctrl+Y)" aria-label="Redo"><i class="fas fa-rotate-right"></i></button>
                    <button type="button" class="sp-ib" id="spClearInk" title="Clear all marks on this page" aria-label="Clear marks"><i class="fas fa-trash-can"></i></button>
                  </div>
                  <div class="sp-grp" aria-label="Zoom">
                    <button type="button" class="sp-ib" id="spZoomOut" title="Zoom out (-)" aria-label="Zoom out"><i class="fas fa-magnifying-glass-minus"></i></button>
                    <span class="sp-zoom" id="spZoomPct">100%</span>
                    <button type="button" class="sp-ib" id="spZoomIn" title="Zoom in (+)" aria-label="Zoom in"><i class="fas fa-magnifying-glass-plus"></i></button>
                    <button type="button" class="sp-ib" id="spZoomFit" title="Fit the whole plan (0)" aria-label="Fit"><i class="fas fa-expand"></i></button>
                  </div>
                  <div class="sp-grp" id="spPageNav" hidden aria-label="Pages">
                    <button type="button" class="sp-ib" id="spPrev" aria-label="Previous page"><i class="fas fa-chevron-left"></i></button>
                    <span class="sp-zoom">Page <span id="spPageNo">1</span>/<span id="spPages">1</span></span>
                    <button type="button" class="sp-ib" id="spNext" aria-label="Next page"><i class="fas fa-chevron-right"></i></button>
                  </div>
                  <span class="sp-spacer"></span>
                  <button type="button" class="sp-full-btn sp-full-only" id="spAddPageFull" style="background:#2563eb;color:#fff;"><i class="fas fa-plus"></i> Add this marked page</button>
                  <button type="button" class="sp-full-btn" id="spFull" title="Full screen (F)"><i class="fas fa-up-right-and-down-left-from-center"></i> <span>Full screen</span></button>
                </div>
                <div class="sp-stage" id="spStage">
                  <div class="sp-canvas-wrap" id="spWrap"><canvas id="spBase"></canvas><canvas id="spInk"></canvas></div>
                  <p id="spLoading" style="padding:20px;position:absolute;inset:0;margin:0;">Loading the plan…</p>
                </div>
                <div class="sp-hint"><span><kbd>Ctrl</kbd>+<kbd>Z</kbd> undo</span><span><kbd>+</kbd> <kbd>-</kbd> zoom</span><span>Ctrl + mouse wheel zooms</span><span><kbd>F</kbd> full screen</span><span><kbd>Esc</kbd> close full screen</span></div>
              </div>
            @else
              <div class="sp-drop"><i class="fas fa-file-circle-exclamation"></i>This file type cannot be drawn on in the browser. Download it, mark it, and upload the marked copy under Upload.</div>
            @endif
          </div>

          <div class="sp-box tone-violet">
            <div class="h"><span class="n">2</span> Correction <span class="sub" id="spPageCount">0 pages</span></div>
            <p>Mark the corrections on the plan, then add the page. Add as many pages as needed.</p>
            @if ($drawable)<div><button type="button" class="btn btn-primary" id="spAddPage"><i class="fas fa-plus"></i> Add this marked page</button></div>@endif
            <div class="sp-thumbs" id="spThumbs"></div>
            <div class="sp-empty" id="spThumbsEmpty">No marked pages yet.</div>
            <label style="font-size:13px;font-weight:600;">What has to be corrected <span class="required">*</span>
              <textarea name="note" required maxlength="2000" placeholder="e.g. Bearing on line 3-4 does not close; check beacon A0879A">{{ old('note') }}</textarea>
            </label>
          </div>

          <div class="sp-box tone-green">
            <div class="h"><span class="n">3</span> Upload</div>
            <p>Or upload a copy you marked outside KLAES. Then send it back to {{ $plan->uploaded_by_name }}.</p>
            <label class="sp-drop" style="cursor:pointer;display:block;"><i class="fas fa-cloud-arrow-up"></i>PDF, JPG, PNG or TIFF, up to 25 MB
              <input type="file" name="correction_file" accept=".pdf,.jpg,.jpeg,.png,.tif,.tiff" style="margin-top:8px;">
            </label>
            <div id="spMarkupInputs"></div>
            <div><button class="btn btn-success" type="submit"><i class="fas fa-reply"></i> Send corrections to the uploader</button></div>
          </div>
        </div>
      </form>

      @if ($stage === Plan::STAGE_UPLOADED)
        <form method="POST" action="{{ route('survey-module.survey-plans.pass', $plan) }}" class="sp-box sp-pass">
          @csrf
          <div class="h"><span class="n" style="background:#16a34a;"><i class="fas fa-check"></i></span> No corrections</div>
          <p>The plan is correct as uploaded. It goes straight to the SG/DSG for signing as the final copy.</p>
          <input type="text" name="note" maxlength="2000" placeholder="Remark (optional)" style="padding:8px;border:1.5px solid #d1d5db;border-radius:6px;">
          <div><button class="btn btn-primary" type="submit"><i class="fas fa-check"></i> Pass and send to the SG/DSG</button></div>
        </form>
      @endif
    </div>
  @endif

  {{-- ============ Stage 2 -> 3: the uploader's final copy (KLAES uploads) ============ --}}
  @if ($stage === Plan::STAGE_CORRECTION && $canFinal)
    <div class="sp-section">
      <h3>Upload the final copy</h3>
      <form method="POST" action="{{ route('survey-module.survey-plans.final', $plan) }}" enctype="multipart/form-data" class="sp-grid4">
        @csrf
        <div class="sp-box">
          <div class="h"><span class="n">1</span> Download corrections</div>
          @foreach ($corrections as $c)
            <div><a class="btn btn-secondary" href="{{ $fileUrl($c) }}" target="_blank" rel="noopener"><i class="fas fa-download"></i> {{ $c->file_name }}</a></div>
          @endforeach
          <p><strong>Note:</strong> {{ optional($corrections->last())->note }}</p>
        </div>
        <div class="sp-box">
          <div class="h"><span class="n">2</span> Upload final copy</div>
          <p>The corrected plan goes straight to the SG/DSG for signing.</p>
          <input type="file" name="final_file" accept=".pdf,.jpg,.jpeg,.png,.tif,.tiff" required>
          <input type="text" name="note" maxlength="2000" placeholder="Remark (optional)" style="padding:8px;border:1.5px solid #d1d5db;border-radius:6px;">
          <div><button class="btn btn-success" type="submit"><i class="fas fa-paper-plane"></i> Send to the SG/DSG</button></div>
        </div>
      </form>
    </div>
  @elseif ($stage === Plan::STAGE_CORRECTION && $plan->source === 'portal')
    <div class="sp-box"><p>Waiting for {{ $plan->uploaded_by_name }} to upload the final copy on the MLPP-SG portal.</p></div>
  @endif

  {{-- ============ Stage 3 -> 4: the SG/DSG signs ============ --}}
  @if ($stage === Plan::STAGE_FINAL && $canSign)
    <div class="sp-section" x-data="{ method: '{{ old('signing_method', 'manual') }}', got: false, printed: false, scanned: false }">
      <h3>Sign the plan</h3>
      <form method="POST" action="{{ route('survey-module.survey-plans.sign', $plan) }}" enctype="multipart/form-data" style="display:grid;gap:12px;">
        @csrf
        <div class="sp-method" role="radiogroup" aria-label="How the plan is signed">
          <label><input type="radio" name="signing_method" value="manual" x-model="method"><span>Manual</span></label>
          <label><input type="radio" name="signing_method" value="digital" x-model="method"><span>Digital</span></label>
        </div>
        <p style="margin:0;font-size:13px;color:#4b5563;" x-show="method === 'manual'">Download the final copy, print it, sign it, scan the signed copy, and upload the scan.</p>
        <p style="margin:0;font-size:13px;color:#4b5563;" x-show="method === 'digital'">Download the final copy, sign it digitally, and upload the signed file.</p>

        <div class="sp-grid4">
          <div class="sp-box" :class="got && 'done'">
            <div class="h"><span class="n">1</span> Download</div>
            <p>The final copy{{ $final && $final->note ? ' · ' . $final->note : '' }}.</p>
            <div><a class="btn btn-secondary" href="{{ $finalUrl ?? $original }}" target="_blank" rel="noopener" @click="got = true"><i class="fas fa-download"></i> Download final copy</a></div>
          </div>
          <div class="sp-box" x-show="method === 'manual'" :class="{ done: scanned, off: ! printed }">
            <div class="h"><span class="n">3</span> Scan</div>
            <p>Scan the signed paper copy to PDF or an image.</p>
            <label style="font-size:13px;"><input type="checkbox" x-model="scanned" :disabled="! printed"> The signed copy is scanned</label>
          </div>
          <div class="sp-box" x-show="method === 'manual'" :class="{ done: printed, off: ! got }">
            <div class="h"><span class="n">2</span> Print and sign</div>
            <p>Open the final copy and print it from the viewer, then sign the printed plan.</p>
            <div><a class="btn btn-secondary" href="{{ $finalUrl ?? $original }}" target="_blank" rel="noopener" @click="got = true"><i class="fas fa-print"></i> Open to print</a></div>
            <label style="font-size:13px;"><input type="checkbox" x-model="printed" :disabled="! got"> Printed and signed</label>
          </div>
          <div class="sp-box" :class="{ off: method === 'manual' ? ! scanned : ! got }">
            <div class="h"><span class="n" x-text="method === 'manual' ? 4 : 2">4</span> Upload</div>
            <p x-text="method === 'manual' ? 'Upload the scanned signed copy.' : 'Upload the digitally signed file.'"></p>
            <input type="file" name="signed_file" accept=".pdf,.jpg,.jpeg,.png,.tif,.tiff" required>
            <input type="text" name="note" maxlength="2000" placeholder="Remark (optional)" style="padding:8px;border:1.5px solid #d1d5db;border-radius:6px;">
            <div><button class="btn btn-success" type="submit"><i class="fas fa-signature"></i> Upload signed copy</button></div>
          </div>
        </div>
      </form>
    </div>
  @elseif ($stage === Plan::STAGE_FINAL)
    <div class="sp-box"><p>With the SG/DSG for signing.</p></div>
  @endif

  @if ($stage === Plan::STAGE_SIGNED && $signed)
    <div class="sp-box done">
      <div class="h">Signed copy</div>
      <p>Signed {{ $plan->signing_method === Plan::SIGN_DIGITAL ? 'digitally' : 'by hand' }} by {{ $signed->by_name }} on {{ $signed->created_at?->format('d M Y H:i') }}.</p>
      <div><a class="btn btn-success" href="{{ $fileUrl($signed) }}" target="_blank" rel="noopener"><i class="fas fa-download"></i> Download signed copy</a></div>
    </div>
  @endif
</div>
@endsection

@push('scripts')
@if ($canCheck && in_array($stage, [Plan::STAGE_UPLOADED, Plan::STAGE_CORRECTION], true) && $drawable)
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
<script>
/*
 | Correction markup (CR 11112026-2, item 5, stage 2): the plan drawn on a canvas, the checker's marks on a
 | second one above it. "Add this marked page" flattens both into a JPEG that is posted with the corrections.
 | Tools (user, 2026-11-12): pen, highlighter, line, arrow, box, circle, text, eraser; colour swatches;
 | thickness; undo/redo; zoom; full screen.
 */
(function () {
  var src = @json($original), isPdf = @json($ext === 'pdf');
  var $ = function (id) { return document.getElementById(id); };
  var base = $('spBase'), ink = $('spInk'), stage = $('spStage'), wrap = $('spWrap'), work = $('spWork');
  var bctx = base.getContext('2d'), ictx = ink.getContext('2d');
  var tool = 'pen', color = '#dc2626', width = 4, strokes = [], redo = [], cur = null, pdf = null, page = 1, pageMarks = {};
  var zoom = 1, MAXW = 2200, ready = false;

  function applyZoom() {
    var w = Math.round(base.width * zoom), h = Math.round(base.height * zoom);
    [base, ink].forEach(function (c) { c.style.width = w + 'px'; c.style.height = h + 'px'; });
    wrap.style.width = w + 'px'; wrap.style.height = h + 'px';
    $('spZoomPct').textContent = Math.round(zoom * 100) + '%';
  }
  function sizeTo(w, h) { [base, ink].forEach(function (c) { c.width = w; c.height = h; }); applyZoom(); }
  function fit() {
    if (!base.width) return;
    var r = stage.getBoundingClientRect();
    zoom = Math.max(0.1, Math.min((r.width - 32) / base.width, (r.height - 32) / base.height)); applyZoom();
  }
  function setZoom(z) {
    var old = zoom, cx = stage.scrollLeft + stage.clientWidth / 2, cy = stage.scrollTop + stage.clientHeight / 2;
    zoom = Math.max(0.1, Math.min(4, z)); applyZoom();
    stage.scrollLeft = cx * zoom / old - stage.clientWidth / 2; stage.scrollTop = cy * zoom / old - stage.clientHeight / 2;
  }
  function loaded() { var l = $('spLoading'); if (l) l.remove(); if (!ready) { ready = true; fit(); } redraw(); syncButtons(); }
  function failed() { var l = $('spLoading'); if (l) l.textContent = 'The plan could not be opened here. Download it and upload a marked copy instead.'; }

  function renderPdfPage(n) {
    pdf.getPage(n).then(function (p) {
      var v = p.getViewport({ scale: 1 }); v = p.getViewport({ scale: Math.min(3, MAXW / v.width) });
      sizeTo(Math.floor(v.width), Math.floor(v.height));
      p.render({ canvasContext: bctx, viewport: v }).promise.then(function () {
        $('spPageNo').textContent = n; strokes = pageMarks[n] || []; redo = []; loaded();
      });
    });
  }
  if (isPdf) {
    pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';
    pdfjsLib.getDocument({ url: src, withCredentials: true }).promise.then(function (d) {
      pdf = d; $('spPages').textContent = d.numPages;
      if (d.numPages > 1) $('spPageNav').hidden = false;
      renderPdfPage(1);
    }).catch(failed);
    var go = function (d) { if (!pdf) return; pageMarks[page] = strokes; page = Math.max(1, Math.min(pdf.numPages, page + d)); renderPdfPage(page); };
    $('spPrev').addEventListener('click', function () { go(-1); });
    $('spNext').addEventListener('click', function () { go(1); });
  } else {
    var img = new Image();
    img.onload = function () { var sc = Math.min(1, MAXW / img.naturalWidth); sizeTo(Math.round(img.naturalWidth * sc), Math.round(img.naturalHeight * sc)); bctx.drawImage(img, 0, 0, base.width, base.height); loaded(); };
    img.onerror = failed;
    img.src = src;
  }

  // Widths scale with the plan's size so a mark reads the same on small and large plans.
  function unit() { return Math.max(1, base.width / 1400); }
  function fontPx(st) { return Math.round((14 + st.w * 3) * unit()); }
  function drawOne(c, st) {
    var u = unit(), lw = st.w * u;
    c.save(); c.strokeStyle = st.c; c.fillStyle = st.c; c.lineWidth = lw; c.lineCap = 'round'; c.lineJoin = 'round';
    if (st.t === 'hl') { c.globalAlpha = 0.35; c.lineWidth = lw * 4; c.lineCap = 'butt'; }
    if (st.p) { c.beginPath(); st.p.forEach(function (q, i) { i ? c.lineTo(q[0], q[1]) : c.moveTo(q[0], q[1]); }); c.stroke(); }
    else if (st.t === 'line' || st.t === 'arrow') {
      c.beginPath(); c.moveTo(st.x1, st.y1); c.lineTo(st.x2, st.y2); c.stroke();
      if (st.t === 'arrow') {
        var ang = Math.atan2(st.y2 - st.y1, st.x2 - st.x1), hl = Math.max(14 * u, lw * 4);
        c.beginPath(); c.moveTo(st.x2, st.y2);
        c.lineTo(st.x2 - hl * Math.cos(ang - 0.45), st.y2 - hl * Math.sin(ang - 0.45));
        c.lineTo(st.x2 - hl * Math.cos(ang + 0.45), st.y2 - hl * Math.sin(ang + 0.45)); c.closePath(); c.fill();
      }
    }
    else if (st.t === 'box') c.strokeRect(Math.min(st.x1, st.x2), Math.min(st.y1, st.y2), Math.abs(st.x2 - st.x1), Math.abs(st.y2 - st.y1));
    else if (st.t === 'ellipse') { c.beginPath(); c.ellipse((st.x1 + st.x2) / 2, (st.y1 + st.y2) / 2, Math.abs(st.x2 - st.x1) / 2 || 1, Math.abs(st.y2 - st.y1) / 2 || 1, 0, 0, Math.PI * 2); c.stroke(); }
    else if (st.t === 'text') { c.font = 'bold ' + fontPx(st) + 'px Arial, sans-serif'; c.fillText(st.s, st.x, st.y); }
    c.restore();
  }
  function redraw() { ictx.clearRect(0, 0, ink.width, ink.height); strokes.concat(cur ? [cur] : []).forEach(function (st) { drawOne(ictx, st); }); }
  function bounds(st) {
    var xs, ys;
    if (st.p) { xs = st.p.map(function (q) { return q[0]; }); ys = st.p.map(function (q) { return q[1]; }); }
    else if (st.t === 'text') { var fs = fontPx(st); xs = [st.x, st.x + st.s.length * fs * 0.6]; ys = [st.y - fs, st.y]; }
    else { xs = [st.x1, st.x2]; ys = [st.y1, st.y2]; }
    var pad = (10 + st.w) * unit();
    return [Math.min.apply(null, xs) - pad, Math.min.apply(null, ys) - pad, Math.max.apply(null, xs) + pad, Math.max.apply(null, ys) + pad];
  }
  function syncButtons() { $('spUndo').disabled = !strokes.length; $('spRedo').disabled = !redo.length; $('spClearInk').disabled = !strokes.length; }
  function commit(st) { strokes.push(st); redo = []; redraw(); syncButtons(); }

  function pos(e) { var r = ink.getBoundingClientRect(); return [(e.clientX - r.left) * ink.width / r.width, (e.clientY - r.top) * ink.height / r.height]; }
  ink.addEventListener('pointerdown', function (e) {
    var p = pos(e);
    if (tool === 'erase') {
      for (var i = strokes.length - 1; i >= 0; i--) {
        var bb = bounds(strokes[i]);
        if (p[0] >= bb[0] && p[0] <= bb[2] && p[1] >= bb[1] && p[1] <= bb[3]) { redo = []; strokes.splice(i, 1); redraw(); syncButtons(); break; }
      }
      return;
    }
    if (tool === 'text') {
      e.preventDefault();
      var t = document.createElement('input'); t.type = 'text'; t.placeholder = 'Type, then Enter';
      t.style.cssText = 'position:absolute;z-index:5;padding:6px 8px;border:2px solid ' + color + ';border-radius:6px;font:14px Arial;min-width:200px;box-shadow:0 4px 12px rgba(0,0,0,.2);';
      var wr = wrap.getBoundingClientRect();
      t.style.left = (e.clientX - wr.left) + 'px'; t.style.top = (e.clientY - wr.top - 18) + 'px';
      wrap.appendChild(t); setTimeout(function () { t.focus(); }, 0);
      var c0 = color, w0 = width, closed = false;
      var done = function (keep) { if (closed) return; closed = true; if (keep && t.value.trim()) commit({ t: 'text', c: c0, w: w0, s: t.value.trim(), x: p[0], y: p[1] }); t.remove(); };
      t.addEventListener('keydown', function (k) { k.stopPropagation(); if (k.key === 'Enter') { k.preventDefault(); done(true); } if (k.key === 'Escape') done(false); });
      t.addEventListener('blur', function () { done(true); });
      return;
    }
    ink.setPointerCapture(e.pointerId);
    cur = (tool === 'pen' || tool === 'hl') ? { t: tool, c: color, w: width, p: [p] } : { t: tool, c: color, w: width, x1: p[0], y1: p[1], x2: p[0], y2: p[1] };
  });
  ink.addEventListener('pointermove', function (e) {
    if (!cur) return; var p = pos(e);
    if (cur.p) cur.p.push(p);
    else {
      cur.x2 = p[0]; cur.y2 = p[1];
      if (e.shiftKey && (cur.t === 'line' || cur.t === 'arrow')) { // Shift snaps to 45°
        var dx = cur.x2 - cur.x1, dy = cur.y2 - cur.y1, a = Math.round(Math.atan2(dy, dx) / (Math.PI / 4)) * (Math.PI / 4), d = Math.hypot(dx, dy);
        cur.x2 = cur.x1 + d * Math.cos(a); cur.y2 = cur.y1 + d * Math.sin(a);
      }
    }
    redraw();
  });
  ink.addEventListener('pointerup', function () {
    if (!cur) return; var c = cur; cur = null;
    if (c.p ? c.p.length > 1 : (Math.abs(c.x2 - c.x1) + Math.abs(c.y2 - c.y1) > 3)) commit(c); else redraw();
  });

  function pickTool(name) {
    tool = name;
    document.querySelectorAll('#spTools [data-tool]').forEach(function (x) { x.classList.toggle('on', x.dataset.tool === name); });
    stage.classList.toggle('erase', name === 'erase');
  }
  document.querySelectorAll('#spTools [data-tool]').forEach(function (b) { b.addEventListener('click', function () { pickTool(b.dataset.tool); }); });
  document.querySelectorAll('#spColors [data-color]').forEach(function (b) {
    b.addEventListener('click', function () {
      color = b.dataset.color;
      document.querySelectorAll('#spColors [data-color]').forEach(function (x) { x.classList.toggle('on', x === b); x.setAttribute('aria-checked', x === b ? 'true' : 'false'); });
      if (tool === 'erase') pickTool('pen');
    });
  });
  document.querySelectorAll('#spWidths [data-w]').forEach(function (b) {
    b.addEventListener('click', function () { width = +b.dataset.w; document.querySelectorAll('#spWidths [data-w]').forEach(function (x) { x.classList.toggle('on', x === b); }); });
  });
  function undo() { if (strokes.length) { redo.push(strokes.pop()); redraw(); syncButtons(); } }
  function redoOne() { if (redo.length) { strokes.push(redo.pop()); redraw(); syncButtons(); } }
  $('spUndo').addEventListener('click', undo);
  $('spRedo').addEventListener('click', redoOne);
  $('spClearInk').addEventListener('click', function () { if (strokes.length && confirm('Clear all marks on this page?')) { strokes = []; redo = []; redraw(); syncButtons(); } });
  $('spZoomIn').addEventListener('click', function () { setZoom(zoom * 1.25); });
  $('spZoomOut').addEventListener('click', function () { setZoom(zoom / 1.25); });
  $('spZoomFit').addEventListener('click', fit);
  stage.addEventListener('wheel', function (e) { if (!e.ctrlKey) return; e.preventDefault(); setZoom(zoom * (e.deltaY < 0 ? 1.1 : 1 / 1.1)); }, { passive: false });

  // Full screen: the same board pinned over the whole window, so the marks carry over.
  function setFull(on) {
    work.classList.toggle('full', on); document.body.classList.toggle('sp-noscroll', on);
    var b = $('spFull'); b.querySelector('span').textContent = on ? 'Exit full screen' : 'Full screen';
    b.querySelector('i').className = on ? 'fas fa-down-left-and-up-right-to-center' : 'fas fa-up-right-and-down-left-from-center';
    setTimeout(fit, 30);
  }
  $('spFull').addEventListener('click', function () { setFull(!work.classList.contains('full')); });
  document.addEventListener('keydown', function (e) {
    var tag = (e.target.tagName || '').toLowerCase(); if (tag === 'input' || tag === 'textarea' || tag === 'select') return;
    if (e.key === 'Escape' && work.classList.contains('full')) { setFull(false); return; }
    if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'z') { e.preventDefault(); e.shiftKey ? redoOne() : undo(); return; }
    if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'y') { e.preventDefault(); redoOne(); return; }
    if (e.ctrlKey || e.metaKey || e.altKey) return;
    var keys = { p: 'pen', h: 'hl', l: 'line', a: 'arrow', b: 'box', c: 'ellipse', t: 'text', e: 'erase' }, k = e.key.toLowerCase();
    if (keys[k]) pickTool(keys[k]);
    else if (k === 'f') setFull(!work.classList.contains('full'));
    else if (k === '+' || k === '=') setZoom(zoom * 1.25);
    else if (k === '-') setZoom(zoom / 1.25);
    else if (k === '0') fit();
  });
  window.addEventListener('resize', function () { if (work.classList.contains('full')) fit(); });

  var thumbs = $('spThumbs'), inputs = $('spMarkupInputs'), count = 0;
  function countPages() { var n = thumbs.children.length; $('spPageCount').textContent = n + (n === 1 ? ' page' : ' pages'); $('spThumbsEmpty').hidden = n > 0; }
  function addPage() {
    if (!strokes.length) { alert('Mark the corrections on the plan first.'); return; }
    var out = document.createElement('canvas'); out.width = base.width; out.height = base.height;
    var o = out.getContext('2d'); o.fillStyle = '#fff'; o.fillRect(0, 0, out.width, out.height); o.drawImage(base, 0, 0); o.drawImage(ink, 0, 0);
    var data = out.toDataURL('image/jpeg', 0.85), id = ++count;
    var h = document.createElement('input'); h.type = 'hidden'; h.name = 'markups[]'; h.value = data; h.dataset.id = id; inputs.appendChild(h);
    var fig = document.createElement('figure'); var im = document.createElement('img'); im.src = data; im.alt = 'Marked page';
    var cap = document.createElement('figcaption'); cap.textContent = (isPdf ? 'Page ' + page : 'Marked plan') + ' · ';
    var rm = document.createElement('button'); rm.type = 'button'; rm.textContent = 'Remove'; rm.style.cssText = 'border:0;background:none;color:#b91c1c;cursor:pointer;font-size:11px;font-weight:600;';
    rm.addEventListener('click', function () { fig.remove(); h.remove(); countPages(); });
    cap.appendChild(rm); fig.appendChild(im); fig.appendChild(cap); thumbs.appendChild(fig); countPages();
    var b = $('spAddPageFull'), old = b.innerHTML; b.innerHTML = '<i class="fas fa-check"></i> Page added'; setTimeout(function () { b.innerHTML = old; }, 1400);
  }
  $('spAddPage').addEventListener('click', addPage);
  $('spAddPageFull').addEventListener('click', addPage);
  countPages(); syncButtons();
})();
</script>
@endif
@endpush
