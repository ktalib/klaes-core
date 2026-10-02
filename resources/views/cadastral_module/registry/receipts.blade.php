@extends('cadastral_module.layouts.klaes')

@section('page-title', 'Intake Queue — KLAES')

@php
  $PageTitle = 'Intake Queue';
  $PageDescription = 'Files arriving at the Cadastral registry from Land, SLTR, ST, KANGIS and DCIV. Queued, then In Progress, then Correspondence Done.';
@endphp

@section('cadastral-content')
  @include('cadastral_module.partials.sections._page-receipts')
@endsection
