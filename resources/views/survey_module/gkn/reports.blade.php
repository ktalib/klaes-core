@extends('survey_module.layouts.klaes')

@section('page-title', 'GKN Reports — KLAES')

@php
  $PageTitle = 'GKN Reports';
  $PageDescription = 'Government lands reports and exports';
@endphp

@section('survey-content')
  @include('survey_module.partials.sections._page-gkn-reports')
@endsection
