@extends('cadastral_module.layouts.klaes')

@section('page-title', 'Cadastral Analytics — KLAES')

@php
  $PageTitle = 'Reports / Analytics';
  $PageDescription = 'Cadastral Department figures for a chosen period, beside their all-time totals.';
@endphp

@section('cadastral-content')
  @include('cadastral_module.partials.sections._page-analytics')
@endsection
