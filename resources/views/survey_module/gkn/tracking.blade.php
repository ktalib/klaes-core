@extends('survey_module.layouts.klaes')

@section('page-title', 'File Tracking — KLAES')

@php
  $PageTitle = 'File Tracking';
  $PageDescription = 'Track movement of GKN and related survey files';
@endphp

@section('survey-content')
  @include('survey_module.partials.sections._page-gkn-tracking')
@endsection
