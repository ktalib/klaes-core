@extends('survey_module.layouts.klaes')

@section('page-title', 'Compensation Calculator — KLAES')

@php
  $PageTitle = 'Compensation Calculator';
  $PageDescription = 'Pick a project to see what each beneficiary is owed: Cash for Trees (count × rate) or Plot for Plot (50:50). A project is one or the other, never both.';
@endphp

@section('survey-content')
  @include('survey_module.partials.sections._page-compensation-calculator')
@endsection
