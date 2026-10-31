@extends('survey_module.layouts.klaes')

@section('page-title', 'Lands Assessment — KLAES')

@php
  $PageTitle = 'Lands Assessment';
  $PageDescription = "Each farmer's land (formerly compensation cases)";
@endphp

@section('survey-content')
  @include('survey_module.partials.sections._page-compensation-cases')
@endsection
