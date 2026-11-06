@extends('surveyor_portal.layouts.guest')

@section('title', 'Sign in — KSSGOF Portal')

@section('card')
    <h1 class="text-lg font-bold mb-4">Sign in</h1>
    <form method="POST" action="{{ route('surveyor-portal.login.submit') }}" class="space-y-4">
        @csrf
        <div>
            <label class="ks-label" for="login">Phone, email or Surveyor ID</label>
            <input class="ks-input" id="login" name="login" value="{{ old('login') }}" required autofocus autocomplete="username">
            @error('login')<p class="ks-error">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="ks-label" for="password">Password</label>
            <input class="ks-input" id="password" type="password" name="password" required autocomplete="current-password">
            @error('password')<p class="ks-error">{{ $message }}</p>@enderror
        </div>
        <div class="flex items-center justify-between text-sm">
            <label class="flex items-center gap-2"><input type="checkbox" name="remember" value="1"> Remember me</label>
            <a href="{{ route('surveyor-portal.password.forgot') }}" style="color:var(--ks-blue);font-weight:600;">Forgot password?</a>
        </div>
        <button class="ks-btn ks-btn-primary w-full">Sign in</button>
    </form>
    <p class="ks-help text-center mt-4">New here? <a href="{{ route('surveyor-portal.register') }}" style="color:var(--ks-blue);font-weight:600;" data-register-dialog-trigger>Register as a surveyor</a></p>
@endsection
