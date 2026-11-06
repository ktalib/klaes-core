@extends('survey_module.layouts.klaes')

@section('page-title', 'Capture Existing Farmers List — KLAES')

@php
  $PageTitle = 'Capture Existing Farmers List'; // was Update Farmers List (user, 2026-11-06), Capture Land (Old), Capture Compensation
  $PageDescription = 'Key in a paper compensation or resettlement allocation list that did not come as a spreadsheet. It is saved as a land with its farmers and allocation lines, like the imported lists.';
@endphp

@section('survey-content')
  @include('survey_module.partials.sections._page-compensation-capture-list')
@endsection
