@extends('cadastral_module.layouts.klaes')

@section('page-title', 'File Status History — KLAES')

@php
  $PageTitle = 'File Status History';
  $PageDescription = 'Every status change, who made it and why.';
@endphp

@section('cadastral-content')
  @include('cadastral_module.partials.sections._page-file-status-history')
@endsection
