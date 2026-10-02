@extends('survey_module.layouts.klaes')

@section('page-title', 'Plot Allocation — KLAES')

@php
  $PageTitle = 'Plot Allocation';
  $PageDescription = 'Land-for-Land compensation only · Manage plot allocations with 50:50 split (Farmer : Government). Not used for Monetary (cash for trees) schemes.';
@endphp

@section('survey-content')
  @include('survey_module.partials.sections._page-farm-plot-allocation')
@endsection
