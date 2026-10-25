@extends('survey_module.layouts.klaes')

@section('page-title', 'Instruction to Survey — KLAES')

@php
  $PageTitle = 'Instruction to Survey';
  $PageDescription = 'Survey jobs and their instructions: issued here for GKN, LPKN and Misc KN files, and received from Cadastral.';
@endphp

@section('survey-content')
  @include('survey_module.partials.sections._page-gkn-its')
@endsection
