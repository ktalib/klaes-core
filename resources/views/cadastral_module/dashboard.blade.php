@extends('cadastral_module.layouts.klaes')

@section('page-title', 'Cadastral Dashboard — KLAES')

@php
  $PageTitle = 'Cadastral Dashboard';
  $PageDescription = 'Intake, reports, survey jobs, index cards and fees across the Cadastral Department.';
@endphp

@section('cadastral-content')
  @include('cadastral_module.partials.sections._page-module-dashboard')
@endsection
