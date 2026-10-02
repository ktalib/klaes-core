@extends('cadastral_module.layouts.klaes')

@section('page-title', 'Cadastral Information — KLAES')

@php
  $PageTitle = 'Cadastral Information';
  $PageDescription = 'Charting, index cards, survey job numbers and file status.';
@endphp

@section('cadastral-content')
  @include('cadastral_module.partials.sections._page-information-dashboard')
@endsection
