@extends('survey_module.layouts.klaes')

@section('page-title', 'GKN Dashboard — KLAES')

@php
  $PageTitle = 'GKN Dashboard';
  $PageDescription = 'Government lands overview and recent activity';
@endphp

@section('survey-content')
  @include('survey_module.partials.sections._page-gkn-dashboard')
@endsection
