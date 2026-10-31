@extends('survey_module.layouts.klaes')

@section('page-title', 'Surveyors — KLAES')

@php
  $PageTitle = 'Surveyors';
  $PageDescription = 'Survey Department staff list';
@endphp

@section('survey-content')
  @include('survey_module.partials._gov_private_tabs', ['route' => 'survey-module.records.surveyors.index', 'tab' => 'government'])
  @include('survey_module.partials.sections._page-surveyors')
@endsection
