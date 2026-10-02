@extends('survey_module.layouts.klaes')

@section('page-title', 'Government Lands — KLAES')

@php
  $PageTitle = 'Government Lands';
  $PageDescription = 'Indexed government land parcels (GKN register)';
@endphp

@section('survey-content')
  @include('survey_module.partials.sections._page-gkn-lands')
@endsection
