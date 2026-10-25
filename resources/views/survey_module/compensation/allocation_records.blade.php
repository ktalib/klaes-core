@extends('survey_module.layouts.klaes')

@section('page-title', 'Allocation Records — KLAES')

@php
  $PageTitle = 'Allocation Records';
  $PageDescription = 'Portions allocated to each surveyor on each project, and how many have been surveyed on a case.';
@endphp

@section('survey-content')
  @include('survey_module.partials.sections._page-allocation-records')
@endsection
