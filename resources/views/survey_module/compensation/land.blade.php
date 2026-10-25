@extends('survey_module.layouts.klaes')

@section('page-title', 'OP Allocation — KLAES')

@php
  $PageTitle = 'OP Allocation';
  $PageDescription = 'Plot for Plot compensation cases · 50:50 plot split. For detailed plot rows use Plot Allocation under Tools.';
@endphp

@section('survey-content')
  @include('survey_module.partials.sections._page-compensation-land')
@endsection
