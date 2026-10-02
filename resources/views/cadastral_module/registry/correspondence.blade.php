@extends('cadastral_module.layouts.klaes')

@section('page-title', 'Correspondence Files — KLAES')

@php
  $PageTitle = 'Correspondence Files';
  $PageDescription = 'Cadastral copies of registered files, matched or created on registration, and files on hold for investigation.';
@endphp

@section('cadastral-content')
  @include('cadastral_module.partials.sections._page-correspondence')
@endsection
