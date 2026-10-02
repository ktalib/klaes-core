@extends('cadastral_module.layouts.klaes')

@section('page-title', 'Duplicate Check — KLAES')

@php
  $PageTitle = 'Duplicate Check';
  $PageDescription = 'Rule-based checking against the duplicate register and against other files sharing a plot number.';
@endphp

@section('cadastral-content')
  @include('cadastral_module.partials.sections._page-duplicates')
@endsection
