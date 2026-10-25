@extends('survey_module.layouts.klaes')

@section('page-title', 'Complaints — KLAES')

@php
  $PageTitle = 'Complaints';
  $PageDescription = 'Grievances from compensation or OP allocation: Open → Under Investigation → Resolved or Escalated.';
@endphp

@section('survey-content')
  @include('survey_module.partials.sections._page-complaints')
@endsection
