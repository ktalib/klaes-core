@extends('survey_module.layouts.klaes')

@section('page-title', 'Complaints — KLAES')

@php
  $PageTitle = 'Complaints';
  $PageDescription = 'Compensation Complaint Form: Pending → Under Review → Overpaid / Underpaid / Adequately Compensated → Resolved.';
@endphp

@section('survey-content')
  @include('survey_module.partials.sections._page-complaints')
@endsection
