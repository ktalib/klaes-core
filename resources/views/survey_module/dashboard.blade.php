@extends('survey_module.layouts.klaes')

@section('page-title', 'Dashboard — KLAES')

@php
  $PageTitle = 'Dashboard';
  $PageDescription = 'Survey Department overview';
@endphp

@section('survey-content')
  @include('survey_module.partials.sections._page-dashboard')
@endsection
