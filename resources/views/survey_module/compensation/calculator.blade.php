@extends('survey_module.layouts.klaes')

@section('page-title', 'Compensation Calculator — KLAES')

@php
  $PageTitle = 'Compensation Calculator';
  $PageDescription = 'Estimate scheme totals. Choose Monetary or Land-for-Land — never both for one case.';
@endphp

@section('survey-content')
  @include('survey_module.partials.sections._page-compensation-calculator')
@endsection
