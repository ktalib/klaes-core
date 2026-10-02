@extends('cadastral_module.layouts.klaes')

@section('page-title', 'File Movements — KLAES')

@php
  $PageTitle = 'File Movements';
  $PageDescription = 'Movement history read live from the KLAES file tracker.';
@endphp

@section('cadastral-content')
  @include('cadastral_module.partials.sections._page-movements')
@endsection
