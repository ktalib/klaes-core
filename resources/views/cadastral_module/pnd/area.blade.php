@extends('cadastral_module.layouts.klaes')

@section('page-title', 'Area & Pillars — KLAES')

@php
  $PageTitle = 'Area & Pillars';
  $PageDescription = 'The area of a file in square metres, hectares, acres and plots, and its government and private pillars.';
@endphp

@section('cadastral-content')
  @include('cadastral_module.partials.sections._page-pnd-area')
@endsection
