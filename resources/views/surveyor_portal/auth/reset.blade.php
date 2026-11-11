@extends('surveyor_portal.layouts.guest')

@section('title', 'Reset password — MLPP-SG Portal')

@section('card')
    <h1 class="text-lg font-bold mb-4">Reset password</h1>
    <form method="POST" action="{{ route('surveyor-portal.password.update') }}" class="space-y-4">
        @csrf
        <div>
            <label class="ks-label" for="code">Code from the SMS</label>
            <input class="ks-input" id="code" name="code" inputmode="numeric" autocomplete="one-time-code" required autofocus>
            @error('code')<p class="ks-error">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="ks-label" for="password">New Password</label>
            <input class="ks-input" id="password" type="password" name="password" minlength="8" required autocomplete="new-password">
            @error('password')<p class="ks-error">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="ks-label" for="password_confirmation">Confirm New Password</label>
            <input class="ks-input" id="password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password">
        </div>
        <button class="ks-btn ks-btn-primary w-full">Change password</button>
    </form>
@endsection
