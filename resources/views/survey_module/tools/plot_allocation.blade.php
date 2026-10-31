@extends('survey_module.layouts.klaes')

@section('page-title', 'Plot Allocation — KLAES')

@php
  $PageTitle = 'Resettlement Plots (Production)';
  $PageDescription = 'Land to Land compensation only · Manage plot allocations with 50:50 split (Farmer : Government). Not used for Monetary Compensation schemes.';
@endphp

@section('survey-content')
  @include('survey_module.partials.sections._page-farm-plot-allocation')
@endsection
