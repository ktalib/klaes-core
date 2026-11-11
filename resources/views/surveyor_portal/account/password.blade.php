@extends('surveyor_portal.layouts.portal')

@section('title', 'Change Password — MLPP-SG Portal')
@section('heading', 'Change Password')

{{-- Change password (user, 2026-11-10). A company login is held here until it replaces the password sent by SMS and email. --}}
@section('main')
    <div class="ks-card" style="max-width:520px;padding:22px;">
        @if ($forced)
            <p class="ks-help" style="margin:0 0 16px;">The password that was sent to you was temporary. Choose your own password to continue.</p>
        @endif
        <form method="POST" action="{{ route('surveyor-portal.password.change') }}" class="space-y-4">
            @csrf
            <div>
                <label class="ks-label" for="current_password">{{ $forced ? 'Password that was sent to you' : 'Current password' }}</label>
                <input class="ks-input" type="password" id="current_password" name="current_password" required autocomplete="current-password" autofocus>
                @error('current_password')<p class="ks-error">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="ks-label" for="password">New password</label>
                <input class="ks-input" type="password" id="password" name="password" required minlength="8" autocomplete="new-password">
                <p class="ks-help" style="margin:4px 0 0;">At least 8 characters.</p>
                @error('password')<p class="ks-error">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="ks-label" for="password_confirmation">Confirm new password</label>
                <input class="ks-input" type="password" id="password_confirmation" name="password_confirmation" required minlength="8" autocomplete="new-password">
            </div>
            <button class="ks-btn ks-btn-primary"><i data-lucide="key-round" class="w-4 h-4"></i> Save new password</button>
        </form>
    </div>
@endsection
