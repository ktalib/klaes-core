@extends('survey_module.layouts.klaes')

@section('page-title', 'Compensation Cases — KLAES')

@php
  $PageTitle = 'Compensation Cases';
  $PageDescription = 'View and manage all compensation cases';
@endphp

@section('survey-content')
  @include('survey_module.partials.sections._page-compensation-cases')
@endsection
