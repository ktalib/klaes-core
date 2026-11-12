@extends('surveyor_portal.layouts.portal')

@php $isSigned = $which === 'signed'; @endphp
@section('title', ($isSigned ? 'Signed Survey Plans' : 'Uploaded Survey Plans') . ' — MLPP-SG Portal')
@section('heading', $isSigned ? 'Signed Survey Plans' : 'Uploaded Survey Plans')

{{-- Uploaded Survey Plans > Original / Signed (user, 2026-11-12): the company's surveyors' plans. Read only. --}}
@section('main')
    <div class="ks-pl-top">
        <p class="ks-help" style="margin:0;">
            @if ($isSigned) Your surveyors' plans signed by the SG/DSG. @else Every survey plan your surveyors uploaded, with its stage. @endif
        </p>
        <form method="GET" class="ks-pl-search" role="search">
            <label class="sr-only" for="plSearch">Search survey plans</label>
            <input class="ks-input" id="plSearch" name="q" value="{{ $search }}" placeholder="Upload ref, surveyor, plot, place…">
            <button class="ks-btn ks-btn-light"><i data-lucide="search" class="w-4 h-4"></i><span class="sr-only">Search</span></button>
        </form>
    </div>
    @include('surveyor_portal.survey_plans._plan_table', [
        'showSurveyor' => true,
        'openUrl' => null,
        'originalUrl' => fn ($p) => route('surveyor-portal.company.plans.file', [$p->id, 'original']),
        'signedUrl' => fn ($p, $f) => route('surveyor-portal.company.plans.file', [$p->id, 'signed']),
        'emptyText' => $search !== '' ? "No survey plan matches “{$search}”." : ($isSigned ? "None of your surveyors' plans has been signed yet." : 'Your surveyors have not uploaded a survey plan yet.'),
    ])
@endsection
