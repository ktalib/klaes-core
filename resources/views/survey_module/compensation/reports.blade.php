@extends('survey_module.layouts.klaes')

@section('page-title', 'Compensation Reports — KLAES')

@php
  $PageTitle = 'Compensation Reports';
  $PageDescription = 'Filter and export compensation scheme reports';
@endphp

@section('survey-content')
  @include('survey_module.partials.sections._page-compensation-reports')
@endsection
