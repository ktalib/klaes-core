@extends('surveyor_portal.layouts.guest')

{{-- One page per kind of login (user, 2026-11-11): /login/company and /login/surveyor. $as = company | surveyor. --}}
@php $isCompanyLogin = ($as ?? 'surveyor') === 'company'; @endphp

@section('title', ($isCompanyLogin ? 'Company sign in' : 'Surveyor sign in') . ' — MLPP-SG Portal')

@section('card')
    <span class="inline-flex items-center gap-1.5 mb-3" style="padding:3px 10px;border-radius:999px;background:#E8EDF5;color:var(--ks-blue);font-size:12px;font-weight:700;">
        <i data-lucide="{{ $isCompanyLogin ? 'building-2' : 'user-round' }}" class="w-3.5 h-3.5"></i>
        {{ $isCompanyLogin ? 'Company' : 'Surveyor' }}
    </span>
    <h1 class="mb-1">{{ $isCompanyLogin ? 'Sign in as a company' : 'Sign in as a surveyor' }}</h1>
    <p class="ks-help mb-5">Welcome back to the {{ $portalSettings['brand_name'] }} portal.</p>
    <form method="POST" action="{{ route('surveyor-portal.login.submit') }}" class="space-y-4">
        @csrf
        <input type="hidden" name="as" value="{{ $isCompanyLogin ? 'company' : 'surveyor' }}">
        <div>
            <label class="ks-label" for="username">{{ $isCompanyLogin ? 'Company username' : 'Username' }}</label>
            <div class="ks-field">
                <i data-lucide="user-round"></i>
                <input class="ks-input" id="username" name="username" value="{{ old('username') }}" maxlength="150" required autofocus autocomplete="username" autocapitalize="none" spellcheck="false">
            </div>
            @error('username')<p class="ks-error">{{ $message }}</p>@enderror
            @error('as')<p class="ks-error">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="ks-label" for="password">Password</label>
            <div class="ks-field ks-pw">
                <i data-lucide="lock-keyhole"></i>
                <input class="ks-input" id="password" type="password" name="password" required autocomplete="current-password">
            </div>
            @error('password')<p class="ks-error">{{ $message }}</p>@enderror
        </div>
        <div class="flex items-center justify-between text-sm">
            <label class="flex items-center gap-2" style="cursor:pointer;"><input type="checkbox" name="remember" value="1" style="width:16px;height:16px;accent-color:var(--ks-blue);"> Remember me</label>
            <a href="{{ route('surveyor-portal.password.forgot') }}" style="color:var(--ks-blue);font-weight:600;">Forgot password?</a>
        </div>
        <button class="ks-btn ks-btn-primary w-full"><i data-lucide="log-in" class="w-4 h-4"></i> Sign in</button>
    </form>
    <p class="ks-help ks-auth-alt">
        @if ($isCompanyLogin)
            A surveyor? <a href="{{ route('surveyor-portal.login.as', 'surveyor') }}" style="color:var(--ks-blue);font-weight:600;">Sign in as a surveyor</a>
        @else
            Signing in for a company? <a href="{{ route('surveyor-portal.login.as', 'company') }}" style="color:var(--ks-blue);font-weight:600;">Sign in as a company</a>
            <br>New here? <a href="{{ route('surveyor-portal.register') }}" style="color:var(--ks-blue);font-weight:600;" data-register-dialog-trigger>Register as a surveyor</a>
        @endif
    </p>
@endsection
