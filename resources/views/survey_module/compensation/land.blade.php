@extends('survey_module.layouts.klaes')

@section('page-title', 'Resettlement Plot Allocation — KLAES')

@php
  $PageTitle = 'Resettlement Plot Allocation';
  $PageDescription = 'Land to Land compensation cases · plots split by the project’s sharing ratio (50:50 by default). For detailed plot rows use Resettlement Plots under Geo-informatics.';
@endphp

@section('survey-content')
  @include('survey_module.partials.sections._page-compensation-land')
@endsection
