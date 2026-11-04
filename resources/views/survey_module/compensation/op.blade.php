@extends('survey_module.layouts.klaes')

@section('page-title', 'OP — KLAES')

@php
  // Existing / New tabs (user, 2026-11-03): existing OPs are captured as issued; the generator is for new ones only.
  $PageTitle = 'OP';
  $PageDescription = $tab === 'existing'
      ? 'OPs already issued before KLAES: capture each one as it is on the OP. Nothing is generated here.'
      : 'Occupancy Permit generation queue for approved compensation cases';
@endphp

@section('survey-content')
  <div style="display:flex;flex-wrap:wrap;gap:8px;margin-bottom:16px;" role="tablist">
    @foreach (['existing' => ['Existing', 'fa-archive'], 'new' => ['New', 'fa-file-signature']] as $k => [$label, $icon])
      <a href="{{ route('survey-module.compensation.op', ['tab' => $k]) }}" role="tab" aria-selected="{{ $tab === $k ? 'true' : 'false' }}"
         class="btn {{ $tab === $k ? 'btn-primary' : 'btn-outline' }} btn-sm">
        <i class="fas {{ $icon }}"></i> {{ $label }} <span style="opacity:.8;">({{ number_format($counts[$k] ?? 0) }})</span>
      </a>
    @endforeach
  </div>
  @if ($tab === 'existing')
    @include('survey_module.partials.sections._page-compensation-op-existing')
  @else
    @include('survey_module.partials.sections._page-compensation-op')
  @endif
@endsection
