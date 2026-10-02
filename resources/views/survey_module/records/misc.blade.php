@extends('survey_module.layouts.klaes')

@section('page-title', 'Misc KN — KLAES')

@php
  $PageTitle = 'Misc KN';
  $PageDescription = 'Miscellaneous cadastral and knowledge notes';
@endphp

@section('survey-content')
  @include('survey_module.partials.sections._page-misc-kn')
@endsection
