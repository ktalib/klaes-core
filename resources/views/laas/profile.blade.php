@extends('laas.layouts.portal')

@section('title', 'My profile — LAAS Portal')

@section('content')
@php
    // Each card shows only the errors for its own fields, so a failed password
    // check on the phone form cannot surface under "Change password".
    $pending = $applicant->hasPendingPhoneChange();
@endphp

<div class="mx-auto max-w-3xl">

    <div class="mb-8">
        <h1 class="text-2xl font-bold" style="color: var(--ink);">My profile</h1>
        <p class="mt-1 text-sm" style="color: var(--ink-soft);">
            Your account details, the number your updates are sent to, and your password.
        </p>
    </div>

    {{-- ---------------- Details ---------------- --}}
    <section class="laas-card mb-6 p-6">
        <h2 class="mb-1 flex items-center gap-2 text-sm font-extrabold uppercase tracking-widest"
            style="color: var(--brand);">
            <i data-lucide="user" class="h-4 w-4" aria-hidden="true"></i> Your details
        </h2>
        <p class="mb-6 text-xs" style="color: var(--ink-soft);">
            Changing these updates your account only. Applications you have already submitted keep the
            details you gave at the time.
        </p>

        @if($errors->hasAny(['name', 'email', 'nin', 'address']))
            <div role="alert" class="mb-5 rounded-xl border p-4"
                 style="border-color: var(--danger); background: rgba(159,18,57,.07);">
                @foreach($errors->only(['name', 'email', 'nin', 'address']) as $messages)
                    @foreach((array) $messages as $message)
                        <p class="text-sm font-medium" style="color: var(--danger);">{{ $message }}</p>
                    @endforeach
                @endforeach
            </div>
        @endif

        <form method="POST" action="{{ route('laas.profile.details') }}" class="space-y-5">
            @csrf
            @method('PUT')

            <div>
                <label for="name" class="block text-sm font-bold" style="color: var(--ink);">
                    Full name <span style="color: var(--danger);">*</span>
                </label>
                <input id="name" type="text" name="name" required
                       value="{{ old('name', $applicant->name) }}" class="laas-input mt-2">
            </div>

            <div>
                <label for="email" class="block text-sm font-bold" style="color: var(--ink);">
                    Email address <span style="color: var(--danger);">*</span>
                </label>
                <input id="email" type="email" name="email" required
                       value="{{ old('email', $applicant->email) }}" class="laas-input mt-2">
            </div>

            <div>
                <label for="nin" class="block text-sm font-bold" style="color: var(--ink);">
                    National Identification Number (NIN)
                </label>
                <input id="nin" type="text" name="nin"
                       value="{{ old('nin', $applicant->nin) }}" class="laas-input mt-2">
            </div>

            <div>
                <label for="address" class="block text-sm font-bold" style="color: var(--ink);">Contact address</label>
                <textarea id="address" name="address" rows="2"
                          class="laas-input mt-2">{{ old('address', $applicant->address) }}</textarea>
            </div>

            <button type="submit" class="laas-btn px-6 py-2.5 text-sm">
                <i data-lucide="save" class="h-4 w-4" aria-hidden="true"></i> Save details
            </button>
        </form>
    </section>

    {{-- ---------------- Phone ---------------- --}}
    <section class="laas-card mb-6 p-6">
        <h2 class="mb-1 flex items-center gap-2 text-sm font-extrabold uppercase tracking-widest"
            style="color: var(--brand);">
            <i data-lucide="smartphone" class="h-4 w-4" aria-hidden="true"></i> Phone number
        </h2>
        <p class="mb-6 text-xs" style="color: var(--ink-soft);">
            This is where every update on your applications is sent, and the number you sign in with.
        </p>

        {{-- Current number --}}
        <div class="mb-6 flex flex-wrap items-center justify-between gap-3 rounded-xl border p-4"
             style="border-color: var(--brand-line); background: var(--brand-tint);">
            <div>
                <p class="text-[10px] font-bold uppercase tracking-widest" style="color: var(--brand);">
                    Current number
                </p>
                <p class="mt-0.5 font-mono text-lg font-bold" style="color: var(--ink);">{{ $applicant->phone }}</p>
            </div>
            @if($applicant->phone_verified_at)
                <p class="inline-flex items-center gap-1.5 text-xs font-bold" style="color: var(--brand);">
                    <i data-lucide="badge-check" class="h-4 w-4" aria-hidden="true"></i> Confirmed
                </p>
            @endif
        </div>

        @if($errors->hasAny(['phone', 'code']))
            <div role="alert" class="mb-5 rounded-xl border p-4"
                 style="border-color: var(--danger); background: rgba(159,18,57,.07);">
                @foreach($errors->only(['phone', 'code']) as $messages)
                    @foreach((array) $messages as $message)
                        <p class="text-sm font-medium" style="color: var(--danger);">{{ $message }}</p>
                    @endforeach
                @endforeach
            </div>
        @endif

        @if($pending)
            {{-- Waiting on the code --}}
            <div class="rounded-xl border-2 p-5" style="border-color: var(--brand);">
                <p class="mb-1 flex items-center gap-2 text-sm font-extrabold" style="color: var(--brand);">
                    <i data-lucide="clock" class="h-4 w-4" aria-hidden="true"></i>
                    Waiting for confirmation
                </p>
                <p class="mb-5 text-sm" style="color: var(--ink-soft);">
                    We sent a 6-digit code to <strong class="font-mono" style="color: var(--ink);">{{ $applicant->pending_phone }}</strong>.
                    Enter it below to switch your number over. Until you do, updates keep going to your current number.
                </p>

                <form method="POST" action="{{ route('laas.profile.phone.confirm') }}"
                      class="flex flex-wrap items-end gap-3">
                    @csrf
                    <div class="min-w-[160px]">
                        <label for="code" class="block text-sm font-bold" style="color: var(--ink);">6-digit code</label>
                        <input id="code" type="text" name="code" required inputmode="numeric" autocomplete="one-time-code"
                               pattern="[0-9]*" maxlength="6" placeholder="000000"
                               class="laas-input mt-2 font-mono tracking-[.35em]">
                    </div>
                    <button type="submit" class="laas-btn px-6 py-2.5 text-sm">
                        <i data-lucide="check" class="h-4 w-4" aria-hidden="true"></i> Confirm new number
                    </button>
                </form>

                <div class="mt-5 flex flex-wrap items-center gap-4 border-t pt-4" style="border-color: var(--border);">
                    <form method="POST" action="{{ route('laas.profile.phone.cancel') }}">
                        @csrf
                        <button type="submit" class="text-sm font-semibold underline underline-offset-2"
                                style="color: var(--ink-soft);">Cancel this change</button>
                    </form>
                    <p class="text-xs" style="color: var(--ink-faint);">
                        The code expires {{ $applicant->verification_code_expires_at->diffForHumans() }}.
                    </p>
                </div>
            </div>
        @else
            {{-- Start a change --}}
            <form method="POST" action="{{ route('laas.profile.phone.request') }}" class="space-y-5">
                @csrf

                <div>
                    <label for="phone" class="block text-sm font-bold" style="color: var(--ink);">
                        New phone number <span style="color: var(--danger);">*</span>
                    </label>
                    <input id="phone" type="tel" name="phone" required value="{{ old('phone') }}"
                           placeholder="08031234567" aria-describedby="phone-help" class="laas-input mt-2">
                    <p id="phone-help" class="mt-1.5 text-xs" style="color: var(--ink-soft);">
                        We will text a confirmation code to this number before anything changes.
                    </p>
                </div>

                <div>
                    <label for="phone_password" class="block text-sm font-bold" style="color: var(--ink);">
                        Your password <span style="color: var(--danger);">*</span>
                    </label>
                    <input id="phone_password" type="password" name="password" required
                           autocomplete="current-password" aria-describedby="phone-password-help"
                           class="laas-input mt-2">
                    <p id="phone-password-help" class="mt-1.5 text-xs" style="color: var(--ink-soft);">
                        Confirms it is really you changing where your updates are sent.
                    </p>
                </div>

                <button type="submit" class="laas-btn px-6 py-2.5 text-sm">
                    <i data-lucide="send" class="h-4 w-4" aria-hidden="true"></i> Send confirmation code
                </button>
            </form>
        @endif
    </section>

    {{-- ---------------- Password ---------------- --}}
    <section class="laas-card p-6">
        <h2 class="mb-1 flex items-center gap-2 text-sm font-extrabold uppercase tracking-widest"
            style="color: var(--brand);">
            <i data-lucide="lock" class="h-4 w-4" aria-hidden="true"></i> Change password
        </h2>
        <p class="mb-6 text-xs" style="color: var(--ink-soft);">
            Use at least 8 characters.
        </p>

        @if($errors->has('current_password'))
            <div role="alert" class="mb-5 rounded-xl border p-4"
                 style="border-color: var(--danger); background: rgba(159,18,57,.07);">
                <p class="text-sm font-medium" style="color: var(--danger);">{{ $errors->first('current_password') }}</p>
            </div>
        @endif

        <form method="POST" action="{{ route('laas.profile.password') }}" class="space-y-5">
            @csrf
            @method('PUT')

            <div>
                <label for="current_password" class="block text-sm font-bold" style="color: var(--ink);">
                    Current password <span style="color: var(--danger);">*</span>
                </label>
                <input id="current_password" type="password" name="current_password" required
                       autocomplete="current-password" class="laas-input mt-2">
            </div>

            <div class="grid gap-5 sm:grid-cols-2">
                <div>
                    <label for="new_password" class="block text-sm font-bold" style="color: var(--ink);">
                        New password <span style="color: var(--danger);">*</span>
                    </label>
                    <input id="new_password" type="password" name="password" required
                           autocomplete="new-password" class="laas-input mt-2">
                </div>
                <div>
                    <label for="password_confirmation" class="block text-sm font-bold" style="color: var(--ink);">
                        Confirm new password <span style="color: var(--danger);">*</span>
                    </label>
                    <input id="password_confirmation" type="password" name="password_confirmation" required
                           autocomplete="new-password" class="laas-input mt-2">
                </div>
            </div>

            <button type="submit" class="laas-btn px-6 py-2.5 text-sm">
                <i data-lucide="key-round" class="h-4 w-4" aria-hidden="true"></i> Change password
            </button>
        </form>
    </section>
</div>
@endsection
