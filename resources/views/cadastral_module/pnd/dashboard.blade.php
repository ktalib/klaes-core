@extends('cadastral_module.layouts.klaes')

@section('page-title', 'Plan and Description — KLAES')

@php
  $PageTitle = 'Plan and Description';
  $PageDescription = 'Area, pillars, fees and the land description.';
@endphp

@section('cadastral-content')
  @include('cadastral_module.partials.sections._page-pnd-dashboard')
@endsection
