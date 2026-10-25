@extends('survey_module.layouts.klaes')

@section('page-title', 'Surveyors Report — KLAES')

@php
  $PageTitle = 'Surveyors Report';
  $PageDescription = "The Survey Department's Surveyor's Report, raised against an issued Instruction to Survey.";
@endphp

@section('survey-content')
  @include('survey_module.partials.sections._page-gkn-surveyor-reports')
@endsection
