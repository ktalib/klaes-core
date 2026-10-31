@extends('survey_module.layouts.klaes')

@php
  $commissioned = ($only ?? null) === \App\Models\Survey\SurveyCoFile::SOURCE_COMMISSIONED;
  $PageTitle = $commissioned ? 'Commission CO File' : 'CO Files';
  $PageDescription = $commissioned
      ? 'CO files commissioned in KLAES, numbered by the system. Each holds one or more projects.'
      : 'Existing CO files captured from the paper records. Each holds one or more projects.';
@endphp

@section('page-title', $PageTitle . ' — KLAES')

@php
@endphp

@section('survey-content')
  @include('survey_module.partials.sections._page-co-files')
@endsection
