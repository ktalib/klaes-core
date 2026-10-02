@extends('survey_module.layouts.klaes')

@section('page-title', 'GIS — KLAES')

@php
  $PageTitle = 'GIS';
  $PageDescription = 'Spatial layers, boundaries, and map tools';
@endphp

@section('survey-content')
  @include('survey_module.partials.sections._page-gis')
@endsection
