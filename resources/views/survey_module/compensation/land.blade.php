@extends('survey_module.layouts.klaes')

@section('page-title', 'Resettlement Plot Allocation — KLAES')

@php
  $PageTitle = 'Resettlement Plot Allocation';
  $PageDescription = 'Land to Land compensation cases · 50:50 plot split. For detailed plot rows use Resettlement Plots under Geo-informatics.';
@endphp

@section('survey-content')
  @include('survey_module.partials.sections._page-compensation-land')
@endsection
