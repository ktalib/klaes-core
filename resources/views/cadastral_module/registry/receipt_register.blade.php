@extends('cadastral_module.layouts.klaes')

@section('page-title', 'Log Incoming File — KLAES')

@php
  $PageTitle = 'Log Incoming File';
  $PageDescription = 'Choose the source department, then pick the file from its index. Owner, type and location come from the source file.';
@endphp

@section('cadastral-content')
  @include('cadastral_module.partials.sections._page-receipt-register')
@endsection
