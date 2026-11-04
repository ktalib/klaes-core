@extends('survey_module.layouts.klaes')

@php
  // Opened as Surveyor's Report (Private) > Computation Field Observations: titled "Computation Field Observation" (user, 2026-11-03).
  $isObs = request('records') === 'observations';
  $PageTitle = $isObs ? 'Computation Field Observation' : "Surveyor's Report (Private)";
@endphp
@section('page-title', $PageTitle . ' — KLAES')

@php
  $PageDescription = 'Printed reports of the changes required when a submitted plan does not match the records.';
@endphp

@section('survey-content')
  @include('survey_module.workflow.change_reports._list')
@endsection
