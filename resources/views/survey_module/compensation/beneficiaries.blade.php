@extends('survey_module.layouts.klaes')

@section('page-title', 'Farmers — KLAES')

@php
  $PageTitle = 'Farmers';
  $PageDescription = 'Farmers and landowners registered across compensation cases';
@endphp

@section('survey-content')
  @include('survey_module.partials.sections._page-compensation-beneficiaries')
@endsection
