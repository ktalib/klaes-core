{{-- Google Recaptcha Settings --}}
{{ Form::model($settings, ['route' => ['setting.google.recaptcha'], 'method' => 'post']) }}
<div class="space-y-6">
    <div class="flex items-center justify-between">
        {{ Form::label('google_recaptcha', __('Google ReCaptcha Enable'), ['class' => $labelClass]) }}
        <label class="relative inline-flex cursor-pointer items-center">
            <input type="checkbox" name="google_recaptcha" id="google_recaptcha"
                class="peer sr-only"
                {{ $settings['google_recaptcha'] == 'on' ? 'checked' : '' }}>
            <div class="h-6 w-11 rounded-full bg-slate-200 after:absolute after:left-[2px] after:top-[2px] after:h-5 after:w-5 after:rounded-full after:border after:border-slate-300 after:bg-white after:transition-all after:content-[''] peer-checked:bg-blue-600 peer-checked:after:translate-x-full peer-checked:after:border-white peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-100"></div>
        </label>
    </div>

    <div class="{{ $twoColumnGrid }}">
        <div class="space-y-2">
            {{ Form::label('recaptcha_key', __('Recaptcha Key'), ['class' => $labelClass]) }}
            {{ Form::text('recaptcha_key', $settings['recaptcha_key'], ['class' => $inputClass, 'placeholder' => __('Enter recaptcha key')]) }}
        </div>
        <div class="space-y-2">
            {{ Form::label('recaptcha_secret', __('Recaptcha Secret'), ['class' => $labelClass]) }}
            {{ Form::text('recaptcha_secret', $settings['recaptcha_secret'], ['class' => $inputClass, 'placeholder' => __('Enter recaptcha secret')]) }}
        </div>
    </div>

    <div class="flex justify-end pt-2">
        {{ Form::submit(__('Save'), ['class' => $primaryButton]) }}
    </div>
</div>
{{ Form::close() }}
