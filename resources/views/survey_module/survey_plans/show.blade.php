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
  .sp-grid2 { display: grid; grid-template-columns: minmax(0, 1.25fr) minmax(0, 1fr); grid-template-rows: auto auto; gap: 12px; }
  .sp-grid2 .sp-download { grid-row: span 2; }
  .sp-grid4 { display: grid; grid-template-columns: minmax(0, 1fr) minmax(0, 1fr); gap: 12px; }
  @media (max-width: 900px) { .sp-grid2, .sp-grid4 { grid-template-columns: minmax(0, 1fr); } .sp-grid2 .sp-download { grid-row: auto; } }
  .sp-box { border: 1.5px solid #d1d5db; border-radius: 10px; padding: 14px; background: #fff; display: grid; gap: 10px; align-content: start; min-width: 0; }
  .sp-box > .h { font-weight: 800; letter-spacing: .06em; text-transform: uppercase; font-size: 13px; color: #111827; display: flex; align-items: center; gap: 8px; }
  .sp-box > .h .n { display: inline-grid; place-items: center; width: 22px; height: 22px; border-radius: 50%; background: #1d4ed8; color: #fff; font-size: 12px; }
  .sp-box.done { border-color: #10b981; background: #f0fdf4; }
  .sp-box.done > .h .n { background: #10b981; }
  .sp-box.off { opacity: .55; }
  .sp-box p { margin: 0; font-size: 13px; color: #4b5563; }
  .sp-box textarea, .sp-box input[type=file], .sp-box select { width: 100%; font-size: 13px; }
  .sp-box textarea { padding: 8px; border: 1.5px solid #d1d5db; border-radius: 6px; min-height: 70px; }
  .sp-tools { display: flex; flex-wrap: wrap; gap: 6px; align-items: center; }
  .sp-tools button, .sp-tools select { padding: 5px 10px; border: 1px solid #d1d5db; border-radius: 6px; background: #fff; font-size: 12px; cursor: pointer; }
  .sp-tools button.on { background: #1d4ed8; color: #fff; border-color: #1d4ed8; }
  .sp-stage { position: relative; border: 1px solid #e5e7eb; border-radius: 8px; overflow: auto; max-height: 640px; background: #f3f4f6; }
  .sp-stage canvas { display: block; max-width: none; }
  .sp-stage #spInk { position: absolute; left: 0; top: 0; cursor: crosshair; touch-action: none; }
  .sp-thumbs { display: flex; flex-wrap: wrap; gap: 8px; }
  .sp-thumbs figure { margin: 0; border: 1px solid #d1d5db; border-radius: 6px; padding: 4px; background: #fff; font-size: 11px; text-align: center; }
  .sp-thumbs img { display: block; width: 110px; height: 80px; object-fit: contain; }
  .sp-method { display: inline-flex; border: 1px solid #d1d5db; border-radius: 8px; overflow: hidden; }
  .sp-method label { padding: 8px 16px; cursor: pointer; font-weight: 600; font-size: 13px; }
  .sp-method input { position: absolute; opacity: 0; }
  .sp-method input:checked + span { color: #fff; }
  .sp-method label:has(input:checked) { background: #1d4ed8; color: #fff; }
  .sp-section { display: grid; gap: 12px; margin-top: 16px; }
  .sp-section > h3 { margin: 0; font-size: 16px; font-weight: 800; }
</style>

<div style="display:flex;justify-content:space-between;align-items:center;gap:8px;flex-wrap:wrap;margin-bottom:12px;">
  <a class="btn btn-secondary" href="{{ url()->previous() !== url()->current() ? url()->previous() : route('survey-module.survey-plans.index') }}"><i class="fas fa-arrow-left"></i> Back</a>
</div>

<div style="display:grid;gap:12px;">
  @include('survey_module.survey_plans._plan_summary', ['originalUrl' => $original])

  {{-- ============ Stage 1 -> 2 / 3: the checking unit ============ --}}
  @if ($canCheck && in_array($stage, [Plan::STAGE_UPLOADED, Plan::STAGE_CORRECTION], true))
    <div class="sp-section">
      <h3>Check the plan ({{ $plan->unit }})</h3>
      <form method="POST" action="{{ route('survey-module.survey-plans.corrections', $plan) }}" enctype="multipart/form-data" id="spCorrForm">
        @csrf
        <div class="sp-grid2">
          <div class="sp-box sp-download">
            <div class="h"><span class="n">1</span> Download</div>
            <p>Download the plan as uploaded, or mark it up on screen under Correction.</p>
            <div><a class="btn btn-secondary" href="{{ $original }}" target="_blank" rel="noopener"><i class="fas fa-download"></i> Download plan</a></div>
            @if ($drawable)
              <div class="sp-tools" id="spTools">
                <button type="button" data-tool="pen" class="on">Pen</button>
                <button type="button" data-tool="box">Box</button>
                <button type="button" data-tool="text">Text</button>
                <select id="spColor" aria-label="Colour"><option value="#dc2626">Red</option><option value="#2563eb">Blue</option><option value="#111827">Black</option></select>
                <button type="button" id="spUndo">Undo</button>
                <button type="button" id="spClearInk">Clear marks</button>
                <span id="spPageNav" hidden>
                  <button type="button" id="spPrev">‹</button> Page <span id="spPageNo">1</span> / <span id="spPages">1</span> <button type="button" id="spNext">›</button>
                </span>
              </div>
              <div class="sp-stage" id="spStage">
                <canvas id="spBase"></canvas><canvas id="spInk"></canvas>
                <p id="spLoading" style="padding:20px;">Loading the plan…</p>
              </div>
            @else
              <p>This file type cannot be drawn on in the browser. Download it, mark it, and upload the marked copy under Upload.</p>
            @endif
          </div>

          <div class="sp-box">
            <div class="h"><span class="n">2</span> Correction</div>
            <p>Mark the corrections on the plan, then add the page. Add as many pages as needed.</p>
            @if ($drawable)<div><button type="button" class="btn btn-primary" id="spAddPage"><i class="fas fa-plus"></i> Add this marked page</button></div>@endif
            <div class="sp-thumbs" id="spThumbs"></div>
            <label style="font-size:13px;font-weight:600;">What has to be corrected <span class="required">*</span>
              <textarea name="note" required maxlength="2000" placeholder="e.g. Bearing on line 3-4 does not close; check beacon A0879A">{{ old('note') }}</textarea>
            </label>
          </div>

          <div class="sp-box">
            <div class="h"><span class="n">3</span> Upload</div>
            <p>Or upload a copy you marked outside KLAES (PDF, JPG, PNG or TIFF, up to 25 MB). Then send it back to {{ $plan->uploaded_by_name }}.</p>
            <input type="file" name="correction_file" accept=".pdf,.jpg,.jpeg,.png,.tif,.tiff">
            <div id="spMarkupInputs"></div>
            <div><button class="btn btn-success" type="submit"><i class="fas fa-reply"></i> Send corrections to the uploader</button></div>
          </div>
        </div>
      </form>

      @if ($stage === Plan::STAGE_UPLOADED)
        <form method="POST" action="{{ route('survey-module.survey-plans.pass', $plan) }}" class="sp-box" style="border-style:dashed;">
          @csrf
          <div class="h">No corrections</div>
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
 */
(function () {
  var src = @json($original), isPdf = @json($ext === 'pdf');
  var base = document.getElementById('spBase'), ink = document.getElementById('spInk'), stage = document.getElementById('spStage');
  var bctx = base.getContext('2d'), ictx = ink.getContext('2d');
  var tool = 'pen', strokes = [], cur = null, pdf = null, page = 1, pageMarks = {};
  var MAXW = 1600;

  function sizeTo(w, h) { [base, ink].forEach(function (c) { c.width = w; c.height = h; c.style.width = w + 'px'; c.style.height = h + 'px'; }); }
  function loaded() { var l = document.getElementById('spLoading'); if (l) l.remove(); redraw(); }

  function renderPdfPage(n) {
    pdf.getPage(n).then(function (p) {
      var v = p.getViewport({ scale: 1 }); var s = Math.min(2.5, MAXW / v.width); v = p.getViewport({ scale: s });
      sizeTo(Math.floor(v.width), Math.floor(v.height));
      p.render({ canvasContext: bctx, viewport: v }).promise.then(function () {
        document.getElementById('spPageNo').textContent = n;
        strokes = pageMarks[n] || []; loaded();
      });
    });
  }
  if (isPdf) {
    pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';
    pdfjsLib.getDocument({ url: src, withCredentials: true }).promise.then(function (d) {
      pdf = d; document.getElementById('spPages').textContent = d.numPages;
      if (d.numPages > 1) document.getElementById('spPageNav').hidden = false;
      renderPdfPage(1);
    }).catch(function () { document.getElementById('spLoading').textContent = 'The plan could not be opened here. Download it and upload a marked copy instead.'; });
    var go = function (d) { if (!pdf) return; pageMarks[page] = strokes; page = Math.max(1, Math.min(pdf.numPages, page + d)); renderPdfPage(page); };
    document.getElementById('spPrev').addEventListener('click', function () { go(-1); });
    document.getElementById('spNext').addEventListener('click', function () { go(1); });
  } else {
    var img = new Image();
    img.onload = function () { var s = Math.min(1, MAXW / img.naturalWidth); sizeTo(Math.round(img.naturalWidth * s), Math.round(img.naturalHeight * s)); bctx.drawImage(img, 0, 0, base.width, base.height); loaded(); };
    img.onerror = function () { document.getElementById('spLoading').textContent = 'The plan could not be opened here. Download it and upload a marked copy instead.'; };
    img.src = src;
  }

  function color() { return document.getElementById('spColor').value; }
  function redraw() {
    ictx.clearRect(0, 0, ink.width, ink.height);
    strokes.concat(cur ? [cur] : []).forEach(function (s) {
      ictx.strokeStyle = s.c; ictx.fillStyle = s.c; ictx.lineWidth = 4; ictx.lineCap = 'round'; ictx.lineJoin = 'round';
      if (s.t === 'pen') { ictx.beginPath(); s.p.forEach(function (q, i) { i ? ictx.lineTo(q[0], q[1]) : ictx.moveTo(q[0], q[1]); }); ictx.stroke(); }
      else if (s.t === 'box') { ictx.strokeRect(s.x, s.y, s.w, s.h); }
      else if (s.t === 'text') { ictx.font = 'bold 28px Arial, sans-serif'; ictx.fillText(s.s, s.x, s.y); }
    });
  }
  function pos(e) { var r = ink.getBoundingClientRect(); return [(e.clientX - r.left) * ink.width / r.width, (e.clientY - r.top) * ink.height / r.height]; }
  ink.addEventListener('pointerdown', function (e) {
    var p = pos(e);
    if (tool === 'text') {
      var t = document.createElement('input'); t.type = 'text'; t.placeholder = 'Type, then Enter';
      t.style.cssText = 'position:absolute;z-index:5;padding:4px 6px;border:2px solid ' + color() + ';border-radius:4px;font:14px Arial;';
      var r = ink.getBoundingClientRect(), sr = stage.getBoundingClientRect();
      t.style.left = (e.clientX - sr.left + stage.scrollLeft) + 'px'; t.style.top = (e.clientY - sr.top + stage.scrollTop) + 'px';
      stage.appendChild(t); setTimeout(function () { t.focus(); }, 0);
      var done = function (keep) { if (keep && t.value.trim()) { strokes.push({ t: 'text', c: color(), s: t.value.trim(), x: p[0], y: p[1] }); redraw(); } t.remove(); };
      t.addEventListener('keydown', function (k) { if (k.key === 'Enter') done(true); if (k.key === 'Escape') done(false); });
      t.addEventListener('blur', function () { done(true); });
      return;
    }
    ink.setPointerCapture(e.pointerId);
    cur = tool === 'pen' ? { t: 'pen', c: color(), p: [p] } : { t: 'box', c: color(), x: p[0], y: p[1], w: 0, h: 0, ox: p[0], oy: p[1] };
  });
  ink.addEventListener('pointermove', function (e) {
    if (!cur) return; var p = pos(e);
    if (cur.t === 'pen') cur.p.push(p); else { cur.x = Math.min(cur.ox, p[0]); cur.y = Math.min(cur.oy, p[1]); cur.w = Math.abs(p[0] - cur.ox); cur.h = Math.abs(p[1] - cur.oy); }
    redraw();
  });
  ink.addEventListener('pointerup', function () { if (cur) { strokes.push(cur); cur = null; redraw(); } });
  document.querySelectorAll('#spTools [data-tool]').forEach(function (b) {
    b.addEventListener('click', function () { tool = b.dataset.tool; document.querySelectorAll('#spTools [data-tool]').forEach(function (x) { x.classList.toggle('on', x === b); }); });
  });
  document.getElementById('spUndo').addEventListener('click', function () { strokes.pop(); redraw(); });
  document.getElementById('spClearInk').addEventListener('click', function () { strokes = []; redraw(); });

  var thumbs = document.getElementById('spThumbs'), inputs = document.getElementById('spMarkupInputs'), count = 0;
  document.getElementById('spAddPage').addEventListener('click', function () {
    if (!strokes.length) { alert('Mark the corrections on the plan first.'); return; }
    var out = document.createElement('canvas'); out.width = base.width; out.height = base.height;
    var o = out.getContext('2d'); o.fillStyle = '#fff'; o.fillRect(0, 0, out.width, out.height); o.drawImage(base, 0, 0); o.drawImage(ink, 0, 0);
    var data = out.toDataURL('image/jpeg', 0.85), id = ++count;
    var h = document.createElement('input'); h.type = 'hidden'; h.name = 'markups[]'; h.value = data; h.dataset.id = id; inputs.appendChild(h);
    var fig = document.createElement('figure'); var im = document.createElement('img'); im.src = data; im.alt = 'Marked page';
    var cap = document.createElement('figcaption'); cap.textContent = (isPdf ? 'Page ' + page : 'Marked plan') + ' ';
    var rm = document.createElement('button'); rm.type = 'button'; rm.textContent = 'Remove'; rm.style.cssText = 'border:0;background:none;color:#b91c1c;cursor:pointer;font-size:11px;';
    rm.addEventListener('click', function () { fig.remove(); h.remove(); });
    cap.appendChild(rm); fig.appendChild(im); fig.appendChild(cap); thumbs.appendChild(fig);
  });
})();
</script>
@endif
@endpush
