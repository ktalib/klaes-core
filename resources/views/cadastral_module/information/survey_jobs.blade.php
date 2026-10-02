@extends('cadastral_module.layouts.klaes')

@section('page-title', 'Survey Job Numbers — KLAES')

@php
  $PageTitle = 'Survey Job Numbers';
  $PageDescription = 'The survey job register and the Instructions to Surveyor issued against it.';
@endphp

@section('cadastral-content')
  @include('cadastral_module.partials.sections._page-survey-jobs')
@endsection
