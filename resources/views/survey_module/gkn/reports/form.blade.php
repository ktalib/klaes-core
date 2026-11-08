@extends('survey_module.layouts.klaes')

@section('page-title', ($report->exists ? $report->report_ref : 'New Surveyors Report') . ' — KLAES')

@php
  $PageTitle = $report->exists ? "Surveyor's Report {$report->report_ref}" : "Create Surveyor's Report";
  $PageDescription = $report->exists
      ? ($report->its_number . ' · ' . $report->file_number . ' · ' . $report->status)
      : 'Complete the report below. Select the issued Instruction to Survey to fill its file and property details.';
@endphp

@section('survey-content')
  @include('survey_module.partials.sections._page-gkn-report-form')
@endsection
