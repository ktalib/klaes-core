@extends('cadastral_module.layouts.klaes')

@section('page-title', 'Survey Job — KLAES')

@php
  $PageTitle = 'Survey Job';
  $PageDescription = 'Job details and the Instruction to Surveyor.';
@endphp

@section('cadastral-content')
  @include('cadastral_module.partials.sections._page-survey-job-register')
@endsection
