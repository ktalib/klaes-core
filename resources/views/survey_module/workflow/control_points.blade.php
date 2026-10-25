@extends('survey_module.layouts.klaes')

@section('page-title', 'Control Points — KLAES')

@php
  $PageTitle = 'Control Points';
  $PageDescription = 'Examination/Verification · survey control point numbers issued to surveyors, with payment, receipt and review';
@endphp

@section('survey-content')
  @include('survey_module.partials.sections._page-control-points')
@endsection
