@extends('survey_module.layouts.klaes')

@section('page-title', 'Land Allocation — KLAES')

@php
  $PageTitle = 'Land Allocation';
  $PageDescription = 'Land-for-Land compensation cases · 50:50 plot split. For detailed plot rows use Plot Allocation under Tools.';
@endphp

@section('survey-content')
  @include('survey_module.partials.sections._page-compensation-land')
@endsection
