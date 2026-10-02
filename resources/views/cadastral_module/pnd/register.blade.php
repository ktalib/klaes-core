@extends('cadastral_module.layouts.klaes')

@section('page-title', 'Plan and Description Record — KLAES')

@php
  $PageTitle = 'Plan and Description';
  $PageDescription = 'Area in every unit, the pillar schedule, the consolidated bill and the land description.';
@endphp

@section('cadastral-content')
  @include('cadastral_module.partials.sections._page-pnd-register')
@endsection
