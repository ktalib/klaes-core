@extends('survey_module.layouts.klaes')

@php
  // Digital Plotting (user, Recording 5, 2026-11-16): the plan is plotted on the map, not uploaded. Its own page for the
  // map's room. 1 coordinate system, 2 the beacons in traverse order (X / Y or bearing and distance; the first, X1, is
  // the reference, so there is no separate reference section), 3 map, 4 plan details (plan file optional), 5 export.
  $PageTitle = 'Digital Plotting';
  $PageDescription = 'Plot the survey plan on the map by X / Y or by bearing and distance, then send it to GKN or Examination/Verification.';
@endphp

@section('page-title', $PageTitle . ' — KLAES')

@section('survey-content')
@include('survey_module.partials._flash')
<div style="display:flex;justify-content:space-between;align-items:center;gap:8px;flex-wrap:wrap;margin-bottom:12px;">
  <a class="btn btn-secondary" href="{{ route('survey-module.survey-plans.index', ['method' => 'digital']) }}"><i class="fas fa-arrow-left"></i> Digital Plotting list</a>
</div>
{{-- The card shows the beacon / system errors in place; the rest are listed here. --}}
@php $spdErrors = collect($errors->getMessages())->reject(fn ($m, $k) => str_starts_with($k, 'beacons') || $k === 'coord_system')->flatten(); @endphp
@if ($spdErrors->isNotEmpty())
  <div style="margin-bottom:12px;padding:10px 14px;border:1px solid #fecaca;background:#fef2f2;color:#991b1b;border-radius:8px;font-size:13px;">
    @foreach ($spdErrors as $e)<div>{{ $e }}</div>@endforeach
  </div>
@endif
<form method="POST" id="spdForm" action="{{ route('survey-module.survey-plans.store') }}" enctype="multipart/form-data"
      style="background:#fff;border:1px solid #e5e7eb;border-radius:14px;padding:20px 22px;display:grid;gap:18px;">
  @csrf
  <input type="hidden" name="plan_method" value="digital">
  @include('survey_module.survey_plans._plan_check', ['spcMode' => 'digital', 'spcSearchUrl' => route('survey-module.survey-plans.beacons')])
  @include('survey_module.survey_plans._plan_details_klaes', ['no' => 4, 'withFile' => true, 'fileOptional' => true])
  @include('survey_module.survey_plans._plan_check_export', ['spcExportNo' => 5, 'spcExportGate' => true])
  <div class="form-actions">
    <div class="left"><a class="btn btn-secondary" href="{{ route('survey-module.survey-plans.index', ['method' => 'digital']) }}"><i class="fas fa-times"></i> Close</a></div>
    <div class="right"><button class="btn btn-success" type="submit"><i class="fas fa-paper-plane"></i> Plot and Send</button></div>
  </div>
</form>
@endsection
