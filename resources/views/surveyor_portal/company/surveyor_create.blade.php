@extends('surveyor_portal.layouts.portal')

@section('title', 'Add Surveyor — MLPP-SG Portal')
@section('heading', 'Add Surveyor')

{{-- Our Surveyors > Add Surveyor (user, 2026-11-13): the registration form, under this company. The account waits for
     the Surveyor General's approval in KLAES (MLPP-SG Portal Admin > Registrations), like any registration. --}}
@push('styles')
<style>
    @media (min-width: 1100px) {
        /* Two balanced columns: Company and Surveyor details, then Address and Username / Password. */
        .ks-addsv #ksRegisterForm { display: block; column-count: 2; column-gap: 20px; }
        .ks-addsv #ksRegisterForm > * { break-inside: avoid; margin: 0 0 20px !important; }
        .ks-addsv #ksRegisterForm > .address-builder { break-before: column; }
        .ks-addsv #ksRegisterForm > button { column-span: all; margin: 4px 0 0 !important; }
    }
</style>
@endpush

@section('main')
    <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:14px;">
        <p class="ks-help" style="margin:0;">Register a surveyor under {{ $company->name }}. The Surveyor General approves the account; the surveyor is texted when they can sign in.</p>
        <a class="ks-btn ks-btn-light" href="{{ route('surveyor-portal.company.surveyors') }}"><i data-lucide="arrow-left" class="w-4 h-4"></i> Our Surveyors</a>
    </div>
    {{-- Full width (user, 2026-11-13): two columns on wide screens; one on phones. --}}
    <div class="ks-card p-6 ks-addsv">
        @include('surveyor_portal.auth._surveyor_form', ['sfAction' => route('surveyor-portal.company.surveyors.store'), 'sfCompany' => $company, 'sfSubmit' => 'Register surveyor'])
    </div>
@endsection
