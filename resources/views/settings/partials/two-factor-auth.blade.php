{{-- 2 Factors Authentication Settings --}}
{{ Form::model($settings, ['route' => ['setting.twofa.enable'], 'method' => 'post']) }}
<div class="space-y-6">
    <div class="space-y-4">
        @if (empty(\Auth::user()->twofa_secret))
            <p class="text-sm text-slate-600">
                {{ __('2-factors authentication is currently') }}
                <span class="inline-flex items-center rounded-full bg-amber-50 px-2.5 py-0.5 text-xs font-bold text-amber-700 border border-amber-200">{{ __('disabled') }}</span>.
                {{ __('To enable') }}:
            </p>
        @else
            <div class="flex items-center gap-3">
                <p class="text-sm font-medium text-slate-700">
                    {{ __('2-factors authentication is currently enable.') }}
                </p>
                <a href="{{ route('2fa.disable') }}" class="{{ $dangerButton }}">
                    {{ __('Click to disable') }}
                </a>
            </div>
        @endif
    </div>

    @if (empty(\Auth::user()->twofa_secret))
        <div class="space-y-6">
            <div class="space-y-3">
                <p class="text-sm font-medium text-slate-700">
                    1. {!! __('Open your OTP app and <b>scan the following QR-code</b>') !!}
                </p>
                <div class="flex justify-center rounded-2xl border border-slate-200 bg-white p-6">
                    <img src="{!! QrCode2FA() !!}" alt="2FA QR Code">
                </div>
            </div>

            <div class="space-y-3">
                <p class="text-sm font-medium text-slate-700">
                    2. {{ __('Generate a One Time Password (OTP) and enter the value below.') }}
                </p>
                <div class="max-w-sm space-y-2">
                    <input name="otp"
                        class="{{ $inputClass }}{{ $errors->has('otp') ? ' border-rose-500 focus:ring-rose-200' : '' }}"
                        type="number" min="0" max="999999"
                        step="1" required autocomplete="off"
                        placeholder="{{ __('Enter OTP code') }}">
                    @if ($errors->has('otp'))
                        <p class="text-sm text-rose-600 font-medium">{{ $errors->first('otp') }}</p>
                    @endif
                </div>
            </div>
        </div>

        <div class="flex justify-end pt-2">
            {{ Form::submit(__('Verify'), ['class' => $primaryButton]) }}
        </div>
    @endif
</div>
{{ Form::close() }}
