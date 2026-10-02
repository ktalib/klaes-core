@extends('cadastral_module.layouts.klaes')

@php
  $scopeLabel = ($scopeType ?? null) ? \App\Models\Cadastral\CadastralReport::TYPES[$scopeType] : null;
  $PageTitle = $scopeLabel ? $scopeLabel . ' Reports' : 'Cadastral Reports';
  $PageDescription = match ($scopeType ?? null) {
      'verification' => 'Verification reports: eight stages, with a field inspection before the chart.',
      'customary'    => 'Customary reports: seven stages from registration to dispatch.',
      'statutory'    => 'Statutory reports: seven stages from registration to dispatch.',
      default        => 'All verification, customary and statutory reports.',
  };
@endphp

@section('page-title', $PageTitle . ' — KLAES')

@section('cadastral-content')
  @include('cadastral_module.partials.sections._page-reports')
@endsection
