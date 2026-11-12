@extends('surveyor_portal.layouts.portal')

@section('title', 'Signed Survey Plans — MLPP-SG Portal')
@section('heading', 'Signed Survey Plans')

{{-- Survey Plan Upload > Signed (user, 2026-11-12): the surveyor's plans the SG/DSG has signed. --}}
@section('main')
    <div class="ks-pl-top">
        <p class="ks-help" style="margin:0;">Your survey plans signed by the SG/DSG. Download the signed copy from the row menu.</p>
        <form method="GET" class="ks-pl-search" role="search">
            <label class="sr-only" for="plSearch">Search signed plans</label>
            <input class="ks-input" id="plSearch" name="q" value="{{ $search }}" placeholder="Upload ref, plot, place…">
            <button class="ks-btn ks-btn-light"><i data-lucide="search" class="w-4 h-4"></i><span class="sr-only">Search</span></button>
        </form>
    </div>
    @include('surveyor_portal.survey_plans._plan_table', [
        'showSurveyor' => false,
        'openUrl' => fn ($p) => route('surveyor-portal.survey-plans.show', $p->id),
        'originalUrl' => fn ($p) => route('surveyor-portal.survey-plans.file', $p->id),
        'signedUrl' => fn ($p, $f) => route('surveyor-portal.survey-plans.stage-file', [$p->id, $f->id]),
        'emptyText' => $search !== '' ? "No signed plan matches “{$search}”." : 'None of your survey plans has been signed yet.',
    ])
@endsection
