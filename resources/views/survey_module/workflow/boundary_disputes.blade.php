@extends('survey_module.layouts.klaes')

@section('page-title', 'Boundary Dispute — KLAES')

@php
  $PageTitle = 'Boundary Dispute';
  $PageDescription = 'Boundary disputes found or reported during verification: capture, investigate, resolve or escalate.';
@endphp

@section('survey-content')
  @include('survey_module.partials.sections._page-boundary-disputes')
@endsection
