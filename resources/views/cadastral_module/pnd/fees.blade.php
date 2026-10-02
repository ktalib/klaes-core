@extends('cadastral_module.layouts.klaes')

@section('page-title', 'Fee Calculator — KLAES')

@php
  $PageTitle = 'Fee Calculator';
  $PageDescription = 'The official Cadastral Fees and Area sheet for a file, line by line, and the bills issued from it.';
@endphp

@section('cadastral-content')
  @include('cadastral_module.partials.sections._page-pnd-fees')
@endsection
