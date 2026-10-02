@extends('cadastral_module.layouts.klaes')

@section('page-title', 'Land Descriptions — KLAES')

@php
  $PageTitle = 'Descriptions';
  $PageDescription = 'The land description for a file, generated from a template and edited before it is saved.';
@endphp

@section('cadastral-content')
  @include('cadastral_module.partials.sections._page-pnd-descriptions')
@endsection
