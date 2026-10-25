@extends('survey_module.layouts.klaes')

@section('page-title', ($report->exists ? $report->report_ref : 'New Surveyors Report') . ' — KLAES')

@php
  $PageTitle = $report->exists ? "Surveyor's Report {$report->report_ref}" : "Create Surveyor's Report";
  $PageDescription = $report->exists
      ? ($report->its_number . ' · ' . $report->file_number . ' · ' . $report->status)
      : 'Choose the issued Instruction to Survey this report answers; the file details are taken from it.';
@endphp

@section('survey-content')
  @include('survey_module.partials.sections._page-gkn-report-form')
@endsection
