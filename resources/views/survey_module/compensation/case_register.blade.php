@extends('survey_module.layouts.klaes')

@section('page-title', ($case->exists ? 'Edit Farm Assessment' : 'Capture New Farm') . ' — KLAES')

@php
  // "You are capturing the land, not registering it" (user, 2026-11-03): Capture Land (New).
  // Was Capture Land (New), then Capture Farm (user, 2026-11-06). This view also edits a saved assessment.
  $PageTitle = $case->exists ? 'Edit Farm Assessment' : 'Capture New Farm';
  $PageDescription = 'Select a Project first. The compensation scheme (Monetary Compensation or Land to Land) is inherited from the Project and drives the rest of the form.';
@endphp

@section('survey-content')
  @include('survey_module.partials.sections._page-compensation-register')
@endsection
