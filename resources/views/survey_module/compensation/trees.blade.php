@extends('survey_module.layouts.klaes')

@section('page-title', 'Farm Assessment — KLAES')

@php
  // Was Economic Trees Assessment; tabs Economic Trees / Cash Crops (CR 30102026 item 6).
  $PageTitle = 'Farm Assessment';
  $PageDescription = 'Valuation catalogue for Monetary Compensation schemes only · Tree × Qty × Unit Price';
@endphp

@section('survey-content')
  @include('survey_module.partials.sections._page-compensation-trees')
@endsection
