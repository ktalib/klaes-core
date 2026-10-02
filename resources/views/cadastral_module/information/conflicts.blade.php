@extends('cadastral_module.layouts.klaes')

@section('page-title', 'Boundary Conflicts — KLAES')

@php
  $PageTitle = 'Boundary Conflicts';
  $PageDescription = 'Charts that appear to describe the same ground as another.';
@endphp

@section('cadastral-content')
  @include('cadastral_module.partials.sections._page-conflicts')
@endsection
