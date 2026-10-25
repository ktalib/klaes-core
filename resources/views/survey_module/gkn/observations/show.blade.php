@extends('survey_module.layouts.klaes')

@section('page-title', 'Field Observations ' . $anchor['prefill']['its_number'] . ' — KLAES')

@php
  $PageTitle = 'Field Observations · ' . $anchor['prefill']['its_number'];
  $PageDescription = $anchor['prefill']['file_number'] . ($anchor['prefill']['grantee_name'] ? ' · ' . $anchor['prefill']['grantee_name'] : '') . ' · ' . ($anchor['source'] === 'cadastral' ? 'Cadastral I to S' : 'Survey I to S');
@endphp

@section('survey-content')
  @include('survey_module.partials.sections._gkn-observation-list')
@endsection
