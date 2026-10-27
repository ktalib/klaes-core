@extends('survey_module.layouts.klaes')

@section('page-title', 'Allocation Records — KLAES')

@php
  $PageTitle = 'Allocation Records';
  $PageDescription = ($tab ?? null) === 'direct'
      ? 'Files commissioned as Direct Allocation, as they stand in File Indexing.'
      : 'Portions allocated to each surveyor on each project, and how many have been surveyed on a case.';
@endphp

@section('survey-content')
  @include('survey_module.partials.sections._allocation-tabs')
  @if (($tab ?? null) === 'direct')
    @include('survey_module.partials.sections._page-allocation-direct')
  @else
    @include('survey_module.partials.sections._page-allocation-records')
  @endif
@endsection
