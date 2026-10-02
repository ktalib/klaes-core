@extends('survey_module.layouts.klaes')

@section('page-title', 'Register GKN — KLAES')

@php
  $PageTitle = 'Register GKN';
  $PageDescription = 'Register a new government land parcel';
@endphp

@section('survey-content')
  @include('survey_module.partials.sections._page-gkn-register')
@endsection
