@extends('survey_module.layouts.klaes')

@section('page-title', ($report->exists ? $report->report_ref : "New Surveyor's Report (Private)") . ' — KLAES')

@php
  $PageTitle = $report->exists ? "Surveyor's Report (Private) {$report->report_ref}" : "Create Surveyor's Report (Private)";
  $PageDescription = $line
      ? ('From Register File ' . $line->register_ref . ' · ' . $line->file_number)
      : 'Complete the standard Surveyor’s Report using a selected file number.';
  $privateReport = true;
  // The Instruction to Survey the report answers (user, 2026-11-09), from ChangeReportController.
  $key = $key ?? '';
  $choices = $choices ?? [];
  $fromLine = (bool) $line;
@endphp

@section('survey-content')
  @include('survey_module.partials.sections._page-gkn-report-form')
@endsection
