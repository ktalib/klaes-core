@extends('survey_module.layouts.klaes')

@section('page-title', 'Beacon Register — KLAES')

@php
  $PageTitle = 'Beacon Register';
  $PageDescription = 'Examination/Verification · beacon numbers issued to surveyors, with payment, receipt and review';
@endphp

@section('survey-content')
  @include('survey_module.partials.sections._page-beacon-register')
@endsection
