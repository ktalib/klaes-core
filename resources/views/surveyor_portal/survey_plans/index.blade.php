@extends('surveyor_portal.layouts.portal')

@section('title', 'Survey Plan Management — KN-MLPP-SG Portal')
@section('heading', 'Survey Plan Management')

@php
    use App\Models\Survey\SurveyPlanUpload as Plan;
@endphp

@section('main')
    {{-- Survey Plan Management (Recording 5, 2026-11-16): Manual Upload here (1 coordinate system, 2 reference beacon,
         3 plan details with the file; no map) or Digital Plotting on its own page. A table each below. --}}
    <style>
        .spm-pick { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px; margin-bottom: 14px; }
        @media (max-width: 640px) { .spm-pick { grid-template-columns: minmax(0, 1fr); } }
        .spm-pick a { display: flex; gap: 12px; align-items: center; padding: 14px 16px; border: 1.5px solid var(--ks-line); border-radius: 12px; background: #fff; color: var(--ks-ink); text-decoration: none; }
        .spm-pick a:hover { border-color: var(--ks-blue); }
        .spm-pick a.on { border-color: var(--ks-blue); background: #EFF5FF; box-shadow: 0 0 0 3px #DCE8FB; }
        .spm-pick .ic { flex: none; display: grid; place-items: center; width: 40px; height: 40px; border-radius: 10px; background: var(--ks-blue); color: #fff; }
        .spm-pick a.dig .ic { background: #0F766E; }
        .spm-pick b { display: block; font-size: 15px; }
        .spm-pick small { color: var(--ks-soft); font-size: 12.5px; }
        .spm-tabs { display: flex; gap: 4px; border-bottom: 2px solid var(--ks-line); margin: 22px 0 10px; flex-wrap: wrap; }
        .spm-tabs a { display: inline-flex; align-items: center; gap: 8px; padding: 9px 14px; margin-bottom: -2px; border-bottom: 2px solid transparent; font-weight: 700; font-size: 13.5px; color: var(--ks-soft); text-decoration: none; }
        .spm-tabs a.on { color: var(--ks-blue); border-bottom-color: var(--ks-blue); }
        .spm-tabs .c { font-size: 11px; padding: 1px 8px; border-radius: 999px; background: #E2E8F0; color: #334155; }
        .spm-tabs a.on .c { background: var(--ks-blue); color: #fff; }
    </style>
    {{-- Both cards always show (user, 2026-11-16: "bring back the digital tab"); the current one is highlighted.
         The Manual Upload form below still shows only on the Manual side. --}}
    <div class="spm-pick">
        <a href="{{ route('surveyor-portal.survey-plans.index', ['method' => 'manual']) }}#spPortalForm" class="{{ $method !== 'digital' ? 'on' : '' }}">
            <span class="ic"><i data-lucide="file-up" class="w-5 h-5"></i></span>
            <span><b>Manual Upload</b><small>A scanned or hard copy plan: the coordinate system, the reference beacon and the file.</small></span>
        </a>
        <a href="{{ route('surveyor-portal.survey-plans.digital') }}" class="dig {{ $method === 'digital' ? 'on' : '' }}">
            <span class="ic"><i data-lucide="map-pinned" class="w-5 h-5"></i></span>
            <span><b>Digital Plotting</b><small>Plot the plan on the map by X / Y or by bearing and distance. The plan file is optional.</small></span>
        </a>
    </div>

    @if ($method !== 'digital')

    <form method="POST" id="spPortalForm" action="{{ route('surveyor-portal.survey-plans.store') }}" enctype="multipart/form-data" class="ks-card p-6 space-y-4">
        @csrf
        <input type="hidden" name="plan_method" value="manual">
        <p class="ks-help" style="margin:0;"><b>Manual Upload.</b> Send a survey plan for checking: GKN for public plans, Examination/Verification for private.</p>
        @include('survey_module.survey_plans._manual_reference', ['mrSearchUrl' => route('surveyor-portal.survey-plans.beacons')])
        @include('surveyor_portal.survey_plans._plan_details_portal', ['no' => 3, 'withFile' => true])
        <div class="flex justify-end">
            <button class="ks-btn ks-btn-primary"><i data-lucide="send" class="w-4 h-4"></i> Upload and Send</button>
        </div>
    </form>
    @endif

    <nav class="spm-tabs" aria-label="My survey plans">
        @foreach (Plan::METHODS as $m => $label)
            <a href="{{ route('surveyor-portal.survey-plans.index', ['method' => $m]) }}" class="{{ $method === $m ? 'on' : '' }}" @if ($method === $m) aria-current="page" @endif>My {{ $label }}s <span class="c">{{ number_format($methodCounts[$m]) }}</span></a>
        @endforeach
    </nav>
    <div class="ks-card overflow-x-auto">
        <table class="ks-table">
            <thead>
                <tr><th>S/N</th><th>Upload Ref</th><th>Type</th><th>Sent To</th><th>Plot No.</th><th>District</th><th>LGA</th><th>Location</th><th>Drawn By</th><th>Uploaded</th><th>Stage</th><th>Plan</th></tr>
            </thead>
            <tbody>
                @forelse ($rows as $p)
                    <tr>
                        <td>{{ $rows->firstItem() + $loop->index }}</td>
                        <td style="white-space:nowrap;font-weight:600;">{{ $p->upload_ref }}</td>
                        <td>{{ $p->plan_type }}</td>
                        <td>{{ $p->unit }}</td>
                        <td>{{ $p->plot_no ?: '—' }}</td>
                        <td>{{ $p->district ?: '—' }}</td>
                        <td>{{ $p->lga ?: '—' }}</td>
                        <td>{{ $p->location ?: '—' }}</td>
                        <td>{{ $p->drawn_by }}</td>
                        <td style="white-space:nowrap;">{{ optional($p->created_at)->format('d/m/Y') }}</td>
                        <td style="white-space:nowrap;">{{ $p->stage_no }} · {{ $p->stage_label }}<div class="ks-help" style="margin:0;">{{ $p->stage_status }}</div></td>
                        <td style="white-space:nowrap;"><a href="{{ route('surveyor-portal.survey-plans.show', $p->id) }}" style="color:var(--ks-blue);font-weight:600;">Open</a></td>
                    </tr>
                @empty
                    <tr><td colspan="12" class="ks-help" style="text-align:center;padding:24px;">No {{ strtolower(Plan::METHODS[$method]) }} yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $rows->links() }}</div>
@endsection

@push('styles')
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css">
@endpush

@push('scripts')
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>


// Preview of the chosen plan file: PDF and images in place; anything else (TIFF) by name and size only.
(function () {
    var input = document.getElementById('plan_file'), wrap = document.getElementById('spPreview'), body = document.getElementById('spPreviewBody');
    if (!input || !wrap) return; // the Digital tab has no Manual Upload form
    var url = null;
    var clear = function () {
        if (url) { URL.revokeObjectURL(url); url = null; }
        body.innerHTML = ''; wrap.hidden = true;
    };
    input.addEventListener('change', function () {
        clear();
        var f = input.files && input.files[0];
        if (!f) return;
        document.getElementById('spPreviewName').textContent = f.name;
        document.getElementById('spPreviewSize').textContent = '(' + (f.size / 1048576).toFixed(2) + ' MB)';
        var type = (f.type || '').toLowerCase(), name = f.name.toLowerCase();
        url = URL.createObjectURL(f);
        if (type === 'application/pdf' || /\.pdf$/.test(name)) {
            var frame = document.createElement('iframe');
            frame.src = url; frame.title = 'Preview of ' + f.name;
            body.appendChild(frame);
        } else if (/^image\/(jpeg|png)$/.test(type) || /\.(jpe?g|png)$/.test(name)) {
            var img = document.createElement('img');
            img.src = url; img.alt = 'Preview of ' + f.name;
            body.appendChild(img);
        } else {
            URL.revokeObjectURL(url); url = null;
            var note = document.createElement('div');
            note.className = 'sp-preview-note';
            note.textContent = 'A TIFF cannot be shown in the browser. Check the file name and size, then upload.';
            body.appendChild(note);
        }
        if (f.size > 25 * 1048576) {
            var warn = document.createElement('div');
            warn.className = 'sp-preview-note'; warn.style.color = '#b91c1c';
            warn.textContent = 'This file is larger than 25 MB and will be refused. Choose a smaller file.';
            body.prepend(warn);
        }
        wrap.hidden = false;
        if (window.lucide) lucide.createIcons();
    });
    document.getElementById('spPreviewClear').addEventListener('click', function () { input.value = ''; clear(); input.focus(); });
})();
</script>
@endpush
