@extends('survey_module.layouts.klaes')

@section('page-title', 'Economic Trees — KLAES')

@php
  $PageTitle = 'Economic Trees';
  $PageDescription = 'Valuation catalogue for Monetary (cash) compensation schemes only · Tree × Qty × Unit Price';
@endphp

@section('survey-content')
  @include('survey_module.partials.sections._page-compensation-trees')
@endsection
