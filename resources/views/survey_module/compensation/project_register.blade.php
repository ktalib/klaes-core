@extends('survey_module.layouts.klaes')

@section('page-title', 'Create Project — KLAES')

@php
  $PageTitle = 'Create Project';
  $PageDescription = 'Create a new project and set its compensation scheme. This scheme will be locked for all cases registered under the project.';
@endphp

@section('survey-content')
  @include('survey_module.partials.sections._page-project-register')
@endsection
