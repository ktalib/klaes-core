@extends('cadastral_module.layouts.klaes')

@section('page-title', 'Charting Register — KLAES')

@php
  $PageTitle = 'Charting Register';
  $PageDescription = 'Charted parcels, versioned. Conversion files are flagged as not requiring charting.';
@endphp

@section('cadastral-content')
  @include('cadastral_module.partials.sections._page-charting')
@endsection
