@extends('survey_module.layouts.klaes')

@section('page-title', 'Register Land — KLAES')

@php
  $PageTitle = 'Register Land';
  $PageDescription = 'Select a Project first. The compensation scheme (Monetary Compensation or Land to Land) is inherited from the Project and drives the rest of the form.';
@endphp

@section('survey-content')
  @include('survey_module.partials.sections._page-compensation-register')
@endsection
