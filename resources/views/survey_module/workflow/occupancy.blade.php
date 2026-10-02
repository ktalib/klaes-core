@extends('survey_module.layouts.klaes')

@section('page-title', 'Occupancy Permit Workflow — KLAES')

@php
  $PageTitle = 'Occupancy Permit Workflow';
  $PageDescription = 'End-to-end OP pipeline: Survey → GIS → Commissioner → Deeds → Land/OSS';
@endphp

@section('survey-content')
  @include('survey_module.partials.sections._page-occupancy')
@endsection
