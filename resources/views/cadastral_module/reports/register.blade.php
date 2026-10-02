@extends('cadastral_module.layouts.klaes')

@section('page-title', 'Open a Cadastral Report — KLAES')

@php
  $PageTitle = 'Cadastral Report';
  $PageDescription = 'Opening a report seeds its stage chain: eight steps for verification, seven for customary and statutory.';
@endphp

@section('cadastral-content')
  @include('cadastral_module.partials.sections._page-report-register')
@endsection
