@extends('survey_module.layouts.klaes')

@section('page-title', ($report->exists ? $report->report_ref : "New Surveyor's Report (Private)") . ' — KLAES')

@php
  $PageTitle = $report->exists ? "Surveyor's Report (Private) {$report->report_ref}" : "Create Surveyor's Report (Private)";
  $PageDescription = $line ? ('From Register File ' . $line->register_ref . ' · ' . $line->file_number) : 'Record what does not match the records and the changes required.';
@endphp

@section('survey-content')
  @include('survey_module.workflow.change_reports._form')
@endsection
