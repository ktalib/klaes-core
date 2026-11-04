@extends('survey_module.layouts.klaes')

@section('page-title', 'Update Farmers List — KLAES')

@php
  $PageTitle = 'Update Farmers List'; // was Capture Land (Old), before that Capture Compensation (user, 2026-11-03)
  $PageDescription = 'Key in a paper compensation or resettlement allocation list that did not come as a spreadsheet. It is saved as a land with its farmers and allocation lines, like the imported lists.';
@endphp

@section('survey-content')
  @include('survey_module.partials.sections._page-compensation-capture-list')
@endsection
