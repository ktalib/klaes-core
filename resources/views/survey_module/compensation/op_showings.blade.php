@extends('survey_module.layouts.klaes')

@section('page-title', 'Showing History — ' . ($op->op_number ?: 'OP #' . $op->id) . ' — KLAES')

@php
  use App\Http\Controllers\Survey\OpShowingController as C;
  use App\Models\Survey\SurveyOpShowing as Showing;

  $ref = $op->op_number ?: 'OP #' . $op->id;
  $PageTitle = 'Showing History · ' . $ref;
  $PageDescription = 'Compensation Status → Verification (Records): each time the site is shown to the allottee.';
  $nextNo = (int) ($showings->max('showing_no') ?? 0) + 1;
  $nextLabel = Showing::ordinal($nextNo) . ' Showing';
  $panelOpen = $errors->any();
  $svPick = old('surveyor', '');
  $open = $showings->take(C::SHOW_OPEN);
  $folded = $showings->slice(C::SHOW_OPEN);
  $vs = $op->verificationStatus();
@endphp

@section('survey-content')
@include('survey_module.partials._flash')

<style>
    .survey-proto .shw-card { background:#fff; border:1px solid var(--gray-200,#e5e7eb); border-radius:12px; padding:14px 16px; margin-bottom:12px; }
    .survey-proto .shw-card.latest { border-color: var(--primary); box-shadow: 0 0 0 1px var(--primary) inset; }
    .survey-proto .shw-head { display:flex; justify-content:space-between; gap:10px; flex-wrap:wrap; align-items:center; }
    .survey-proto .shw-no { font-weight:700; font-size:15px; }
    .survey-proto .shw-meta { display:grid; grid-template-columns:repeat(auto-fit,minmax(150px,1fr)); gap:8px 16px; margin-top:10px; font-size:13px; }
    .survey-proto .shw-meta span { display:block; font-size:12px; color:var(--gray-500); }
    .survey-proto .shw-photos { display:flex; flex-wrap:wrap; gap:8px; margin-top:10px; }
    .survey-proto .shw-photos img { width:96px; height:72px; object-fit:cover; border-radius:8px; border:1px solid var(--gray-200,#e5e7eb); }
</style>

<div class="page-header">
    <a class="btn btn-secondary btn-sm" href="{{ route('survey-module.compensation.op-verification') }}"><i class="fas fa-arrow-left"></i> Verification (Records)</a>
    <button type="button" class="btn btn-primary" onclick="document.getElementById('showingPanel').classList.toggle('open')">
        <i class="fas fa-plus"></i> Record {{ $nextLabel }}
    </button>
</div>

<div class="kpi-grid">
    <div class="kpi-card"><div class="kpi-label">OP No.</div><div class="kpi-value" style="font-size:18px;">{{ $ref }}</div>
        <div style="font-size:12px;color:var(--gray-500);">Verification: {{ $vs }}</div></div>
    <div class="kpi-card"><div class="kpi-label">Allottee</div><div style="font-size:15px;font-weight:600;">{{ $op->beneficiary?->full_name ?? '—' }}</div>
        <div style="font-size:12px;color:var(--gray-500);">{{ $op->case?->case_ref }}{{ $op->case?->project?->coFile ? ' · ' . $op->case->project->coFile->co_file_no : '' }}</div></div>
    <div class="kpi-card"><div class="kpi-label">Plot / Cash Ref.</div><div style="font-size:15px;font-weight:600;">{{ $op->plot_or_cash_ref ?: '—' }}</div></div>
    <div class="kpi-card"><div class="kpi-label">Showings</div><div class="kpi-value">{{ $showings->count() }}</div>
        <div style="font-size:12px;color:var(--gray-500);">{{ $showings->first() ? 'Last ' . $showings->first()->shown_on->format('d M Y') : 'None yet' }}</div></div>
</div>

<div class="farmer-entry-form {{ $panelOpen ? 'open' : '' }}" id="showingPanel" data-sv-modal="Record {{ $nextLabel }} — {{ $ref }}">
    <form method="POST" action="{{ route('survey-module.compensation.op-verification.showings.store', $op) }}" enctype="multipart/form-data">
        @csrf
        <div class="form-grid">
            <div class="sv-group"><i class="fas fa-user"></i><span>{{ $nextLabel }}</span></div>
            <div class="form-group full">
                <label>Surveyor <span class="required">*</span></label>
                <select name="surveyor" id="shwSurveyor" required>
                    <option value="">— Select —</option>
                    @foreach ($surveyors as $s)
                        <option value="staff:{{ $s->id }}" @selected($svPick === 'staff:' . $s->id)>{{ $s->full_name }}{{ $s->rank ? ' · ' . $s->rank : '' }}</option>
                    @endforeach
                    <option value="{{ C::SURVEYOR_OTHER }}" @selected($svPick === C::SURVEYOR_OTHER)>Other (specify)</option>
                </select>
                @error('surveyor')<div style="color:var(--danger,#dc2626);font-size:12px;">{{ $message }}</div>@enderror
            </div>
            <div class="form-group full" id="shwOtherBox" @style(['display:none' => $svPick !== C::SURVEYOR_OTHER])>
                <label>Surveyor's Name <span class="required">*</span></label>
                <input type="text" name="surveyor_other" id="shwOther" maxlength="255" value="{{ old('surveyor_other') }}" @disabled($svPick !== C::SURVEYOR_OTHER) />
                @error('surveyor_other')<div style="color:var(--danger,#dc2626);font-size:12px;">{{ $message }}</div>@enderror
            </div>
            <div class="form-group">
                <label>Date <span class="required">*</span></label>
                <input type="date" name="shown_on" max="{{ now()->toDateString() }}" value="{{ old('shown_on', now()->toDateString()) }}" required />
                @error('shown_on')<div style="color:var(--danger,#dc2626);font-size:12px;">{{ $message }}</div>@enderror
            </div>
            <div class="form-group">
                <label>Time <span class="required">*</span></label>
                <input type="time" name="shown_at" value="{{ old('shown_at', now()->format('H:i')) }}" required />
                @error('shown_at')<div style="color:var(--danger,#dc2626);font-size:12px;">{{ $message }}</div>@enderror
            </div>
            @if ($nextNo > 1)
                <div class="form-group full">
                    <label>Why is the site being shown again? <span class="required">*</span></label>
                    <select name="reason" required>
                        <option value="">— Select —</option>
                        @foreach (Showing::REASONS as $reason)
                            <option value="{{ $reason }}" @selected(old('reason') === $reason)>{{ $reason }}</option>
                        @endforeach
                    </select>
                    @error('reason')<div style="color:var(--danger,#dc2626);font-size:12px;">{{ $message }}</div>@enderror
                </div>
            @endif
            <div class="form-group full">
                <label>Photographs</label>
                <input type="file" name="photos[]" accept=".jpg,.jpeg,.png" multiple />
                <p class="helper-text">Up to {{ C::MAX_PHOTOS }} JPG or PNG images, 10 MB each.</p>
                @error('photos')<div style="color:var(--danger,#dc2626);font-size:12px;">{{ $message }}</div>@enderror
                @error('photos.*')<div style="color:var(--danger,#dc2626);font-size:12px;">{{ $message }}</div>@enderror
            </div>
            <div class="form-group full">
                <label>Notes</label>
                <textarea name="notes" maxlength="2000">{{ old('notes') }}</textarea>
            </div>
        </div>
        <div class="form-actions">
            <div class="left"><button type="button" class="btn btn-secondary" onclick="document.getElementById('showingPanel').classList.remove('open')"><i class="fas fa-times"></i> Close</button></div>
            <div class="right"><button class="btn btn-success" type="submit"><i class="fas fa-check"></i> Save {{ $nextLabel }}</button></div>
        </div>
    </form>
</div>

@forelse ($open as $s)
    @include('survey_module.compensation._op_showing_card', ['s' => $s, 'latest' => $loop->first])
@empty
    <div class="shw-card" style="text-align:center;color:var(--gray-500);">
        <i class="fas fa-street-view" style="font-size:24px;display:block;margin-bottom:8px;opacity:.4;"></i>
        The site has not been shown yet. Use <strong>Record {{ $nextLabel }}</strong> when the surveyor shows the allottee the site.
    </div>
@endforelse

@if ($folded->isNotEmpty())
    <details class="shw-card" style="padding:10px 16px;">
        <summary style="cursor:pointer;font-weight:600;">{{ $folded->count() }} earlier showing{{ $folded->count() === 1 ? '' : 's' }}</summary>
        <div style="margin-top:12px;">
            @foreach ($folded as $s)
                @include('survey_module.compensation._op_showing_card', ['s' => $s, 'latest' => false])
            @endforeach
        </div>
    </details>
@endif

<script>
    (function () {
        var sel = document.getElementById('shwSurveyor'), box = document.getElementById('shwOtherBox'), other = document.getElementById('shwOther');
        if (!sel) return;
        sel.addEventListener('change', function () {
            var on = sel.value === @json(C::SURVEYOR_OTHER);
            box.style.display = on ? '' : 'none';
            other.disabled = !on; other.required = on;
            if (!on) other.value = '';
        });
        if (sel.value === @json(C::SURVEYOR_OTHER)) other.required = true;
    })();
</script>
@endsection
