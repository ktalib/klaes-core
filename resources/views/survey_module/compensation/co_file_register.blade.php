@extends('survey_module.layouts.klaes')

@section('page-title', ($coFile->exists ? 'Edit CO File' : 'Commission CO File') . ' — KLAES')

@php
  $capture = $coFile->source === \App\Models\Survey\SurveyCoFile::SOURCE_CAPTURED;
  $PageTitle = $coFile->exists ? 'Edit CO File ' . $coFile->co_file_no : ($capture ? 'Capture Existing CO File' : 'Commission CO File');
  $PageDescription = $capture
      ? 'Key in an existing paper CO file under its own number.'
      : 'Commission a new CO file. It is numbered PRJ-YYYY-0001 automatically and can hold many projects.';
@endphp

@section('survey-content')
  @include('survey_module.partials.sections._page-co-file-register')
@endsection
