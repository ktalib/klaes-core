@extends('survey_module.layouts.klaes')

@section('page-title', ($report->exists ? $report->report_ref : "New Surveyors' Report") . ' — KLAES')

@php
  $PageTitle = $report->exists ? "Surveyors' Report {$report->report_ref}" : "Create Surveyors' Report";
  $PageDescription = $line ? ('From Register File ' . $line->register_ref . ' · ' . $line->file_number) : 'Record what does not match the records and the changes required.';
@endphp

@section('survey-content')
  @include('survey_module.workflow.change_reports._form')
@endsection
