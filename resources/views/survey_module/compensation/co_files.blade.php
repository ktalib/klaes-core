@extends('survey_module.layouts.klaes')

@section('page-title', 'CO Files — KLAES')

@php
  $PageTitle = 'CO Files';
  $PageDescription = 'Compensation (CO) files. Each CO file holds one or more projects; commission new files or capture existing ones.';
@endphp

@section('survey-content')
  @include('survey_module.partials.sections._page-co-files')
@endsection
