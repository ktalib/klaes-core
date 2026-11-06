@extends('surveyor_portal.layouts.guest')

@section('title', 'Register — KSSGOF Portal')
@section('width', '520px')

@section('card')
    <h1 class="text-lg font-bold mb-1">Is your surveying company registered?</h1>
    <p class="ks-help mb-4">If it is registered and approved, continue to choose your company. Otherwise, register the company first.</p>
    <div class="grid sm:grid-cols-2 gap-3">
        <a class="ks-btn ks-btn-primary" href="{{ route('surveyor-portal.register', ['company' => 'yes']) }}">Yes, register</a>
        <a class="ks-btn ks-btn-light" href="{{ route('surveyor-portal.register', ['company' => 'no']) }}">No, register a company</a>
    </div>
    <p class="ks-help text-center mt-4"><a href="{{ route('surveyor-portal.login') }}" style="color:var(--ks-blue);font-weight:600;">Already registered? Sign in</a></p>
@endsection
