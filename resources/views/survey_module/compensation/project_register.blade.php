@extends('survey_module.layouts.klaes')

@section('page-title', ($project->exists ? 'Update Project' : 'Create Project') . ' — KLAES')

@php
  // Edit is called Update (user, 2026-11-03); the records under the project follow the form.
  $PageTitle = $project->exists ? 'Update Project · ' . $project->project_code : 'Create Project';
  $PageDescription = $project->exists
      ? 'Update the project. Its farmers, farms, lands and OPs are listed under the form.'
      : 'Create a new project and set its compensation scheme. This scheme will be locked for all cases registered under the project.';
@endphp

@section('survey-content')
  @include('survey_module.partials.sections._page-project-register')
  @if ($project->exists && isset($records))
    @include('survey_module.partials.sections._project-records')
  @endif
@endsection
