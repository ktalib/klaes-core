@extends('survey_module.layouts.klaes')

@section('page-title', 'Layout Plan — KLAES')

@php
  $PageTitle = 'Layout Plan';
  $PageDescription = 'Layout Plan Knowledge Notes — registrations and approvals';
@endphp

@section('survey-content')
  @include('survey_module.partials.sections._page-lpkn')
@endsection
