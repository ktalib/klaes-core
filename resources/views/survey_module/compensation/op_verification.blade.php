@extends('survey_module.layouts.klaes')

@section('page-title', 'OP Verification — KLAES')

@php
  $PageTitle = request('via') === 'examination' ? 'Resettlement Plot Verification' : 'Plot Showing Register';
  $PageDescription = 'Check each Occupancy Permit generated in OP Allocation, then mark it sent to Deeds OP Verification.';
@endphp

@section('survey-content')
  @include('survey_module.partials.sections._page-op-verification')
@endsection
