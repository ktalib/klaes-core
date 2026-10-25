@extends('survey_module.layouts.klaes')

@section('page-title', 'Applications — KLAES')

@php
  $PageTitle = 'Applications';
  $PageDescription = 'GKN, LPKN, LPPL and Misc KN files in one register. Commission new numbers with Commission Application Files.';
@endphp

@section('survey-content')
  @include('survey_module.partials.sections._page-gkn-applications')
@endsection
