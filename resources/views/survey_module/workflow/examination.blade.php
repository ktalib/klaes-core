@extends('survey_module.layouts.klaes')

@section('page-title', 'Examination — KLAES')

@php
  $PageTitle = 'Examination';
  $PageDescription = 'QA/QC verification queue for survey and compensation deliverables';
@endphp

@section('survey-content')
  @include('survey_module.partials.sections._page-examination')
@endsection
