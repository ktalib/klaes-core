@extends('survey_module.layouts.klaes')

@section('page-title', 'Register Compensation Case — KLAES')

@php
  $PageTitle = 'Register Compensation Case';
  $PageDescription = 'Select a Project first. The compensation scheme (Monetary or Land-for-Land) is inherited from the Project and drives the rest of the form.';
@endphp

@section('survey-content')
  @include('survey_module.partials.sections._page-compensation-register')
@endsection
