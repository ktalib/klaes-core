@extends('cadastral_module.layouts.klaes')

@section('page-title', 'Index Card — KLAES')

@php
  $PageTitle = 'Index Card';
  $PageDescription = 'The card, with its movement record read live from the file tracker.';
@endphp

@section('cadastral-content')
  @include('cadastral_module.partials.sections._page-index-card-show')
@endsection
