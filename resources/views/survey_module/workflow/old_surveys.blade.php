@extends('survey_module.layouts.klaes')

@section('page-title', 'Old Surveys — KLAES')

@php
  $PageTitle = 'Old Surveys';
  $PageDescription = 'Historical surveys checked for authenticity against the records.';
@endphp

@section('survey-content')
  @include('survey_module.partials.sections._page-old-surveys')
@endsection
