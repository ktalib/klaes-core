@extends('cadastral_module.layouts.klaes')

@section('page-title', 'Chart — KLAES')

@php
  $PageTitle = 'Chart';
  $PageDescription = 'Chart details and the beacon ring. The area is computed from the coordinates.';
@endphp

@section('cadastral-content')
  @include('cadastral_module.partials.sections._page-chart-register')
@endsection
