@extends('survey_module.layouts.klaes')

@section('page-title', 'Project Management — KLAES')

@php
  $PageTitle = 'Project Management';
  $PageDescription = 'Projects define the compensation scheme (Monetary or Land-for-Land 50:50). Cases inherit the scheme from the selected project.';
@endphp

@section('survey-content')
  @include('survey_module.partials.sections._page-projects')
@endsection
