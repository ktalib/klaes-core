@extends('survey_module.layouts.klaes')

@section('page-title', 'Capture Farm — KLAES')

@php
  // "You are capturing the land, not registering it" (user, 2026-11-03): Capture Land (New).
  $PageTitle = 'Capture Farm'; // was Capture Land (New) (user, 2026-11-03)
  $PageDescription = 'Select a Project first. The compensation scheme (Monetary Compensation or Land to Land) is inherited from the Project and drives the rest of the form.';
@endphp

@section('survey-content')
  @include('survey_module.partials.sections._page-compensation-register')
@endsection
