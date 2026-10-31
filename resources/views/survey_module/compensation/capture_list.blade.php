@extends('survey_module.layouts.klaes')

@section('page-title', 'Capture Compensation List — KLAES')

@php
  $PageTitle = 'Capture Compensation List';
  $PageDescription = 'Key in a paper compensation or resettlement allocation list that did not come as a spreadsheet. It is saved as a land with its farmers and allocation lines, like the imported lists.';
@endphp

@section('survey-content')
  @include('survey_module.partials.sections._page-compensation-capture-list')
@endsection
