@extends('survey_module.layouts.klaes')

@section('page-title', 'Coordinate Register — KLAES')

@php
  $PageTitle = 'Coordinate Register';
  $PageDescription = 'Every coordinate recorded in the Survey module: I to S lists, Beacon Register, Control Points and LPKN layouts.';
@endphp

@section('survey-content')
  @include('survey_module.partials.sections._page-coordinate-register')
@endsection
