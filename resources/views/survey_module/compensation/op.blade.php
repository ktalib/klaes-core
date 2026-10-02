@extends('survey_module.layouts.klaes')

@section('page-title', 'OP Generation — KLAES')

@php
  $PageTitle = 'OP Generation';
  $PageDescription = 'Occupancy Permit generation queue for approved compensation cases';
@endphp

@section('survey-content')
  @include('survey_module.partials.sections._page-compensation-op')
@endsection
