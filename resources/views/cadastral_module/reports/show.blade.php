@extends('cadastral_module.layouts.klaes')

@section('page-title', 'Cadastral Report — KLAES')

@php
  $PageTitle = 'Cadastral Report';
  $PageDescription = 'The report and its stage chain.';
@endphp

@section('cadastral-content')
  @include('cadastral_module.partials.sections._page-report-show')
@endsection
