@extends('cadastral_module.layouts.klaes')

@section('page-title', 'Log Incoming File — KLAES')

@php
  $PageTitle = 'Log Incoming File';
  $PageDescription = 'Select the file with the file-number selector. Its source department, owner, type and location come from the file index.';
@endphp

@section('cadastral-content')
  @include('cadastral_module.partials.sections._page-receipt-register')
@endsection
