@extends('survey_module.layouts.klaes')

@php
  // Opened from the sidebar's Plot Showing Register (?view=showing, user 2026-11-02): same page, that title.
  $isShowingRegister = request('view') === 'showing';
  $PageTitle = $isShowingRegister ? 'Plot Showing Register' : 'Lands Assessment';
  $PageDescription = "Each farmer's land (formerly compensation cases)";
@endphp

@section('page-title', $PageTitle . ' — KLAES')

@section('survey-content')
  @if (!empty($blank))
    {{-- Lands Assessment and the Plot Showing Register are blank for now (user, 2026-11-02); CaseController::index. --}}
    @include('survey_module.partials._flash')
    {{-- Buttons while the table is blank (user, 2026-11-02): Lands Assessment has Register and Capture
         Compensation (moved here from the Plot Showing Register, which now opens Plot Confirmation). --}}
    <div class="page-header">
      <div></div>
      <div style="display:flex;gap:8px;flex-wrap:wrap;">
        <a href="{{ route('survey-module.compensation.capture-list') }}" class="btn btn-secondary">
          <i class="fas fa-clipboard-list"></i> Capture Compensation
        </a>
        <a href="{{ route('survey-module.compensation.cases.register') }}" class="btn btn-primary">
          <i class="fas fa-plus"></i> Register
        </a>
      </div>
    </div>
    <div class="dash-card" style="text-align:center;padding:48px 16px;color:var(--gray-500);">
      <i class="fas fa-map" style="font-size:28px;color:var(--gray-300);"></i>
      <div style="margin-top:10px;font-size:14px;">Nothing here yet.</div>
    </div>
  @else
    @include('survey_module.partials.sections._page-compensation-cases')
  @endif
@endsection
