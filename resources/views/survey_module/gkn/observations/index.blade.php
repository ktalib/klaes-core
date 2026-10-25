@extends('survey_module.layouts.klaes')

@section('page-title', 'Computation Field Observations — KLAES')

@php
  $PageTitle = 'Computation Field Observations';
  $PageDescription = 'Field readings for each issued Instruction to Survey, checked against the coordinate list and closed as a traverse.';
@endphp

@section('survey-content')
  @include('survey_module.partials.sections._gkn-its-survey-overview', ['page' => 'observations'])
@endsection
