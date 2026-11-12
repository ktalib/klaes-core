@extends('surveyor_portal.layouts.portal')

@section('title', $plan->upload_ref . ' — Survey Plan — MLPP-SG Portal')
@section('heading', 'Survey Plan ' . $plan->upload_ref)

@php
    use App\Models\Survey\SurveyPlanUpload as Plan;
    use App\Models\Survey\SurveyPlanFile as PlanFile;
    // CR 11112026-2 (item 6): the surveyor follows their plan through the four stages here.
    $fileUrl = fn ($f) => route('surveyor-portal.survey-plans.stage-file', [$plan->id, $f->id]);
    $corrections = $plan->files->where('kind', PlanFile::KIND_CORRECTION);
    $signed = $plan->latestFile(PlanFile::KIND_SIGNED);
@endphp

@section('main')
    <div style="display:grid;gap:14px;">
        <div><a class="ks-btn" href="{{ route('surveyor-portal.survey-plans.index') }}"><i data-lucide="arrow-left" class="w-4 h-4"></i> My survey plans</a></div>

        @if ($errors->any())
            <div class="ks-card p-4" style="border-color:#fecaca;background:#fef2f2;color:#991b1b;">
                @foreach ($errors->all() as $e)<div>{{ $e }}</div>@endforeach
            </div>
        @endif

        @include('survey_module.survey_plans._plan_summary', ['originalUrl' => route('surveyor-portal.survey-plans.file', $plan->id)])

        @if ($plan->stage_no === Plan::STAGE_CORRECTION)
            <form method="POST" action="{{ route('surveyor-portal.survey-plans.final', $plan->id) }}" enctype="multipart/form-data" class="ks-card p-6" style="display:grid;gap:14px;">
                @csrf
                <h2 style="margin:0;font-size:16px;font-weight:800;">Corrections from {{ $plan->unit }}</h2>
                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:14px;">
                    <div style="border:1.5px solid #d1d5db;border-radius:10px;padding:14px;display:grid;gap:8px;align-content:start;">
                        <strong>1. Download the corrections</strong>
                        @foreach ($corrections as $c)
                            <a class="ks-btn" href="{{ $fileUrl($c) }}" target="_blank" rel="noopener"><i data-lucide="download" class="w-4 h-4"></i> {{ $c->file_name }}</a>
                        @endforeach
                        <p class="ks-help" style="margin:0;"><strong>What to correct:</strong> {{ optional($corrections->last())->note }}</p>
                    </div>
                    <div style="border:1.5px solid #d1d5db;border-radius:10px;padding:14px;display:grid;gap:8px;align-content:start;">
                        <strong>2. Upload the final copy</strong>
                        <p class="ks-help" style="margin:0;">Upload the corrected plan. It goes straight to the SG/DSG for signing.</p>
                        <input class="ks-input" type="file" name="final_file" accept=".pdf,.jpg,.jpeg,.png,.tif,.tiff" required>
                        <input class="ks-input" type="text" name="note" maxlength="2000" placeholder="Remark (optional)">
                        <button class="ks-btn ks-btn-primary"><i data-lucide="send" class="w-4 h-4"></i> Send final copy</button>
                    </div>
                </div>
            </form>
        @endif

        @if ($plan->stage_no === Plan::STAGE_SIGNED && $signed)
            <div class="ks-card p-6" style="display:grid;gap:8px;border-color:#a7f3d0;background:#f0fdf4;">
                <strong>Signed copy</strong>
                <p class="ks-help" style="margin:0;">Signed {{ $plan->signing_method === Plan::SIGN_DIGITAL ? 'digitally' : 'by hand' }} on {{ $signed->created_at?->format('d M Y') }}.</p>
                <div><a class="ks-btn ks-btn-primary" href="{{ $fileUrl($signed) }}" target="_blank" rel="noopener"><i data-lucide="download" class="w-4 h-4"></i> Download signed copy</a></div>
            </div>
        @endif
    </div>
@endsection
