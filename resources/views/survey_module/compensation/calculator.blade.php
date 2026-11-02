@extends('survey_module.layouts.klaes')

@section('page-title', 'Compensation Calculator — KLAES')

@php
  $PageTitle = 'Compensation Calculator';
  $PageDescription = 'Pick a project to see what each beneficiary is owed: Monetary Compensation (count × rate) or Land to Land (split by the project’s sharing ratio, 50:50 by default). A project is one or the other, never both.';
@endphp

@section('survey-content')
  @include('survey_module.partials.sections._page-compensation-calculator')
@endsection
