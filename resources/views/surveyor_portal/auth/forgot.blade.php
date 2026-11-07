@extends('surveyor_portal.layouts.guest')

@section('title', 'Forgot password — KNSSGOF Portal')

@section('card')
    <h1 class="text-lg font-bold mb-1">Forgot password</h1>
    <p class="ks-help mb-4">We will text a reset code to the phone on your account.</p>
    <form method="POST" action="{{ route('surveyor-portal.password.send') }}" class="space-y-4">
        @csrf
        <div>
            <label class="ks-label" for="login">Phone, email or Surveyor ID</label>
            <input class="ks-input" id="login" name="login" value="{{ old('login') }}" required autofocus>
            @error('login')<p class="ks-error">{{ $message }}</p>@enderror
        </div>
        <button class="ks-btn ks-btn-primary w-full">Send code</button>
    </form>
    <p class="ks-help text-center mt-4"><a href="{{ route('surveyor-portal.login') }}" style="color:var(--ks-blue);font-weight:600;">Back to sign in</a></p>
@endsection
