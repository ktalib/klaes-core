@extends('surveyor_portal.layouts.guest')

@section('title', 'Register — MLPP-SG Portal')
@section('width', '520px')

@section('guest-banner')
    <img src="{{ asset($portalSettings['logo_registration_banner']) }}"
         alt="{{ $portalSettings['brand_name'] }} — {{ $portalSettings['office_name'] }} Portal"
         width="1600" height="666" class="relative"
         style="width:100%;max-width:620px;height:auto;margin-left:32px;mix-blend-mode:multiply;">
@endsection

@section('guest-mobile-banner')
    <img src="{{ asset($portalSettings['logo_registration_banner']) }}"
         alt="{{ $portalSettings['brand_name'] }} — {{ $portalSettings['office_name'] }} Portal"
         width="1600" height="666" class="mx-auto mb-5 lg:hidden"
         style="width:100%;max-width:460px;height:auto;mix-blend-mode:multiply;">
@endsection

@section('card')
    <h1 class="mb-1" id="ksHeading">Register as a surveyor</h1>
    <p class="ks-help mb-5">The Office of the Surveyor General confirms each registration. We will text you when you can sign in.</p>
    @include('surveyor_portal.auth._surveyor_form', ['sfAction' => route('surveyor-portal.register.submit'), 'sfSubmit' => 'Register'])
    <p class="ks-help ks-auth-alt">Already registered? <a href="{{ route('surveyor-portal.login') }}" data-signin-dialog-trigger style="color:var(--ks-blue);font-weight:600;">Sign in</a></p>
@endsection
