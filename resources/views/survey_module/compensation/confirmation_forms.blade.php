@extends('survey_module.layouts.klaes')

@section('page-title', 'Special Projects — KLAES')

@section('survey-content')
@include('survey_module.partials._flash')

<div class="page-header" style="margin-bottom:14px;">
    <div>@include('survey_module.partials._records_export', [
        'endpoint' => route('survey-module.compensation.confirmation-forms.export'),
        'title' => 'Confirmation Forms',
        'dateLabel' => 'Date Captured',
    ])</div>
</div>

@include('survey_module.compensation.partials._confirmation_forms_panel')
@endsection
