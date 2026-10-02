@extends('cadastral_module.layouts.klaes')

@section('page-title', 'Cadastral Report Desk — KLAES')

@php
  $PageTitle = 'Cadastral Report Desk';
  $PageDescription = 'Verification, customary and statutory reports, and the desks holding them.';
@endphp

@section('cadastral-content')
  @include('cadastral_module.partials.sections._page-report-dashboard')
@endsection
