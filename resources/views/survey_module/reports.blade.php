@extends('survey_module.layouts.klaes')

@section('page-title', 'Reports — KLAES')

@php
  $PageTitle = 'Reports';
  $PageDescription = 'Cross-module reports for Survey Department';
@endphp

@section('survey-content')
  @include('survey_module.partials.sections._page-reports')
@endsection
