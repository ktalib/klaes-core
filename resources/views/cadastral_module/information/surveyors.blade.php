@extends('cadastral_module.layouts.klaes')

@section('page-title', 'Surveyor Directory — KLAES')

@php
  $PageTitle = 'Surveyor Directory';
  $PageDescription = 'Registered surveyors and firms. Only a current licence may receive an Instruction to Surveyor.';
@endphp

@section('cadastral-content')
  @include('cadastral_module.partials.sections._page-surveyors')
@endsection
