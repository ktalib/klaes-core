@extends('survey_module.layouts.klaes')

@section('page-title', 'Compensation Dashboard — KLAES')

@php
  $PageTitle = 'Compensation Dashboard';
  $PageDescription = 'Overview of all compensation activities and metrics';
@endphp

@section('survey-content')
  @include('survey_module.partials.sections._page-compensation-dashboard')
@endsection
