@extends('survey_module.layouts.klaes')

@php
  // Survey Plan Check (CR 11112026-2 item 8; user, 2026-11-12): blank, or filled in from an uploaded plan.
  // Nothing on this page is saved; the other beacons, polygon and drawings can be exported.
  $PageTitle = 'Check Survey Plan' . ($plan ? ' ' . $plan->upload_ref : '');
  $PageDescription = $plan
      ? $plan->plan_type . ' · ' . $plan->unit . ' · ' . $plan->stage_status
      : 'Choose the coordinate system, enter the reference coordinate, and plot the plan on the map.';
@endphp

@section('page-title', $PageTitle . ' — KLAES')

@section('survey-content')
<div style="display:flex;justify-content:space-between;align-items:center;gap:8px;flex-wrap:wrap;margin-bottom:12px;">
  <a class="btn btn-secondary" href="{{ url()->previous() !== url()->current() ? url()->previous() : route('survey-module.survey-plans.index') }}"><i class="fas fa-arrow-left"></i> Back</a>
  @if ($plan)
    <div style="display:flex;gap:8px;flex-wrap:wrap;">
      <a class="btn btn-secondary" href="{{ route('survey-module.survey-plans.show', $plan) }}"><i class="fas fa-layer-group"></i> Open Stages</a>
      @if ($plan->file_path)
      <a class="btn btn-secondary" href="{{ route('survey-module.survey-plans.file', $plan) }}" target="_blank" rel="noopener"><i class="fas fa-file-lines"></i> Open Plan</a>
      @endif
    </div>
  @endif
</div>
<div style="background:#fff;border:1px solid #e5e7eb;border-radius:14px;padding:20px 22px;display:grid;gap:18px;">
  {{-- A plan opened for checking is a review (Kunle, 2026-11-18): the checker is not plotting, so the system and the
       reference come from the upload and show on the map; the entry, drawing tools, charts and exports are hidden.
       Export (and its script and styles) is still included, hidden, as the card's script needs it. --}}
  @include('survey_module.survey_plans._plan_check', ['spcMode' => 'check', 'spcReview' => (bool) $plan, 'spcSearchUrl' => route('survey-module.survey-plans.beacons'), 'spcPrefill' => $prefill])
  <div @if ($plan) hidden @endif>
  @include('survey_module.survey_plans._plan_check_export', ['spcExportNo' => 5])
  </div>
</div>
@endsection
