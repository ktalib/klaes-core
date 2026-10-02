@php
    $pvcOtp = app(\App\Services\PhoneOtpService::class);
    $pvcUser = auth()->user();

    $pvcPhone = $pvcOtp->localFormat($pvcUser->phone_number ?? '');

    /* Blank when the address on file is one of the seeded placeholders at
       example.org and the like. A prefilled box reads as a value somebody has
       checked, and pressing Send on abdul@example.org only produces a refusal. */
    $pvcEmail = $pvcOtp->emailForCard($pvcUser);
    $pvcEmailIsPlaceholder = $pvcEmail === '' && trim((string) ($pvcUser->email ?? '')) !== '';

    $pvcChannels = $pvcOtp->enabledChannels();
    $pvcHasEmail = in_array(\App\Services\PhoneOtpService::CHANNEL_EMAIL, $pvcChannels, true);
    $pvcHasSms = in_array(\App\Services\PhoneOtpService::CHANNEL_SMS, $pvcChannels, true);
    $pvcCooldown = $pvcOtp->cooldownRemaining($pvcUser);

    $pvcChannel = \App\Services\PhoneOtpService::CHANNEL_BOTH;
    $pvcCodeLength = (int) config('phone_verification.code_length', 6);

    $pvcPrimaryLogo = asset('assets/logo/logo.png');
    $pvcSecondaryLogo = asset('assets/logo/Left_Logo.png');

    if (class_exists(\Illuminate\Support\Facades\Storage::class)) {
        if (\Illuminate\Support\Facades\Storage::disk('public')->exists('upload/logo/logo.png')) {
            $pvcPrimaryLogo = asset('storage/upload/logo/logo.png');
        }
        if (\Illuminate\Support\Facades\Storage::disk('public')->exists('uploads/logo.jpeg')) {
            $pvcSecondaryLogo = asset('storage/uploads/logo.jpeg');
        }
    }
@endphp

<link rel="stylesheet" href="{{ asset('css/phone-verification-card.css') }}">

<div id="phoneVerificationCard" class="pvc-overlay"
    data-send-url="{{ route('phone.verification.send') }}"
    data-verify-url="{{ route('phone.verification.verify') }}"
    data-channel="{{ $pvcChannel }}"
    data-has-email="{{ $pvcHasEmail ? '1' : '0' }}"
    data-has-sms="{{ $pvcHasSms ? '1' : '0' }}"
    data-email="{{ $pvcEmail }}"
    data-phone="{{ $pvcPhone }}"
    data-code-length="{{ $pvcCodeLength }}"
    data-cooldown="{{ $pvcCooldown }}"
    role="dialog" aria-modal="true" aria-labelledby="pvcTitle" aria-hidden="true">

    <div class="pvc-card">
        <div class="pvc-masthead">
            <div class="pvc-logos">
                <img src="{{ $pvcPrimaryLogo }}" alt="KLAES">
                <span class="pvc-logo-rule" aria-hidden="true"></span>
                <img src="{{ $pvcSecondaryLogo }}" alt="Land Admin Enterprise System">
            </div>
            <p class="pvc-eyebrow">{{ __('Account setup') }}</p>
        </div>

        <div class="pvc-body">

            <div id="pvcStepTarget">
                <h2 class="pvc-title" id="pvcTitle">{{ __('Confirm Your Phone & Email') }}</h2>
                <p class="pvc-lede">
                    {{ __('Hello') }} <strong>{{ $pvcUser->first_name ?? $pvcUser->name }}</strong>,
                    {{ __('enter your mobile number and email address. We will send the same verification code to both. You can use the code from either message.') }}
                </p>
                <div class="pvc-field">
                    <label class="pvc-label" for="pvcPhoneInput">{{ __('Mobile number') }}</label>
                    {{-- No `pattern` attribute on purpose: reportValidity() runs before
                         our own check and would replace a sentence the user can act on
                         with the browser's "Please match the requested format." --}}
                    <input type="tel" id="pvcPhoneInput" class="pvc-input" inputmode="numeric"
                        autocomplete="tel" value="{{ $pvcPhone }}" placeholder="08012345678"
                        maxlength="11" required>
                    <p class="pvc-hint">{{ __('11 digits, starting with 0.') }}</p>
                </div>
                <div class="pvc-field">
                    <label class="pvc-label" for="pvcEmailInput">{{ __('Email address') }}</label>
                    <input type="email" id="pvcEmailInput" class="pvc-input" inputmode="email"
                        autocomplete="email" autocapitalize="off" spellcheck="false"
                        value="{{ $pvcEmail }}" placeholder="musa@gmail.com" required>
                    <p class="pvc-hint">{{ __('Your own mailbox — gmail, yahoo, or your work address. Check both contacts and correct them before sending the code.') }}</p>
                </div>
                <p id="pvcTargetMessage" class="pvc-error pvc-hidden" role="alert"></p>
                <button type="button" id="pvcSendBtn" class="pvc-submit">{{ __('Send Verification Code') }}</button>
            </div>

            {{-- Step 2: the code. Shared by both routes — only the wording around it
                 changes, because only the delivery differs. --}}
            <div id="pvcStepCode" class="pvc-hidden">
                <span class="pvc-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"
                        stroke-linecap="round" stroke-linejoin="round">
                        <path d="M4 4h16v12H5.17L4 17.17z" />
                        <line x1="8" y1="10" x2="8" y2="10" />
                        <line x1="12" y1="10" x2="12" y2="10" />
                        <line x1="16" y1="10" x2="16" y2="10" />
                    </svg>
                </span>

                <h2 class="pvc-title">{{ __('Enter The Code') }}</h2>

                <p class="pvc-lede">
                    {{ __('Verification contacts:') }} <strong id="pvcMaskedTarget"></strong>.
                    {{ __('Enter the code from either message to finish setting up your account.') }}
                </p>

                <div class="pvc-field">
                    <label class="pvc-label" for="pvcCodeInput">{{ __('Verification code') }}</label>
                    <input type="text" id="pvcCodeInput" class="pvc-input pvc-input-code"
                        inputmode="numeric" autocomplete="one-time-code"
                        maxlength="{{ $pvcCodeLength }}"
                        placeholder="{{ str_repeat('0', $pvcCodeLength) }}">
                    <p class="pvc-hint">
                        {{ __('The code expires in :minutes minutes.', ['minutes' => $pvcOtp->ttlMinutes()]) }}
                    </p>
                </div>

                <p id="pvcCodeMessage" class="pvc-error pvc-hidden" role="alert"></p>

                <button type="button" id="pvcVerifyBtn" class="pvc-submit">
                    {{ __('Verify My Account') }}
                </button>

                <div class="pvc-actions">
                    <button type="button" id="pvcBackBtn" class="pvc-link">{{ __('Edit phone or email') }}</button>
                    <button type="button" id="pvcResendBtn" class="pvc-link">{{ __('Resend code') }}</button>
                </div>

                <p class="pvc-note">{{ __('Check your email spam or junk folder if the message is missing. SMS delivery may be delayed by your network.') }}</p>
            </div>

            <p class="pvc-footnote">
                {{ __('The system stays locked until your account is confirmed.') }}
            </p>
        </div>
    </div>
</div>

<script src="{{ asset('js/phone-verification-card.js') }}?v={{ filemtime(public_path('js/phone-verification-card.js')) }}" defer></script>
