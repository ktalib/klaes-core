@extends('survey_module.layouts.klaes')

@section('page-title', "Surveyors' Report — KLAES")

@php
  $PageTitle = "Surveyors' Report";
  $PageDescription = 'Printed reports of the changes required when a submitted plan does not match the records.';
@endphp

@section('survey-content')
  @include('survey_module.workflow.change_reports._list')
@endsection
