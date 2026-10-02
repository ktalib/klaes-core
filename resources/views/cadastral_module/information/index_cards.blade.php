@extends('cadastral_module.layouts.klaes')

@section('page-title', 'Index Card Register — KLAES')

@php
  $PageTitle = 'Index Card Register';
  $PageDescription = 'The cadastral index card as data. One live card per file number.';
@endphp

@section('cadastral-content')
  @include('cadastral_module.partials.sections._page-index-cards')
@endsection
