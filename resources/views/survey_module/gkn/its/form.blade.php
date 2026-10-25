@extends('survey_module.layouts.klaes')

@section('page-title', ($its->exists ? $its->job_ref : 'New Survey Job') . ' — KLAES')

@php
  $PageTitle = $its->exists ? ($its->its_number ?: $its->job_ref) : 'Create Instruction to Survey';
  $PageDescription = $its->exists
      ? 'Survey job ' . $its->job_ref . ' for ' . $its->file_number . ($its->its_number ? ' · issued ' . optional($its->its_issued_at)->format('d M Y') : ' · draft')
      : 'Register a survey job for a GKN, LPKN or Misc KN file, then issue its instruction to a Survey Department surveyor.';
@endphp

@section('survey-content')
  @include('survey_module.partials.sections._page-gkn-its-form')
@endsection
