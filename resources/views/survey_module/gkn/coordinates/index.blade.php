@extends('survey_module.layouts.klaes')

@section('page-title', 'List of Coordinates — KLAES')

@php
  $PageTitle = 'List of Coordinates';
  $PageDescription = 'The official beacon coordinates for each issued Instruction to Survey.';
@endphp

@section('survey-content')
  @include('survey_module.partials.sections._gkn-its-survey-overview', ['page' => 'coordinates'])
@endsection
