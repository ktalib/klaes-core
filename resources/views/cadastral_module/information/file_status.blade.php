@extends('cadastral_module.layouts.klaes')

@section('page-title', 'File Status — KLAES')

@php
  $PageTitle = 'File Status';
  $PageDescription = 'Revoked, reinstated, withdrawn, change of purpose, open and close.';
@endphp

@section('cadastral-content')
  @include('cadastral_module.partials.sections._page-file-status')
@endsection
