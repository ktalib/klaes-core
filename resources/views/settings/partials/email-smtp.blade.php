{{-- Email SMTP Settings --}}
{{ Form::model($settings, ['route' => ['setting.smtp'], 'method' => 'post']) }}
<div class="space-y-6">
    <div class="{{ $twoColumnGrid }}">
        <div class="space-y-2">
            {{ Form::label('sender_name', __('Sender Name'), ['class' => $labelClass]) }}
            {{ Form::text('sender_name', $settings['FROM_NAME'], ['class' => $inputClass, 'placeholder' => __('Enter sender name')]) }}
        </div>
        <div class="space-y-2">
            {{ Form::label('sender_email', __('Sender Email'), ['class' => $labelClass]) }}
            {{ Form::text('sender_email', $settings['FROM_EMAIL'], ['class' => $inputClass, 'placeholder' => __('Enter sender email')]) }}
        </div>
        <div class="space-y-2">
            {{ Form::label('server_driver', __('SMTP Driver'), ['class' => $labelClass]) }}
            {{ Form::text('server_driver', $settings['SERVER_DRIVER'], ['class' => $inputClass, 'placeholder' => __('Enter smtp driver')]) }}
        </div>
        <div class="space-y-2">
            {{ Form::label('server_host', __('SMTP Host'), ['class' => $labelClass]) }}
            {{ Form::text('server_host', $settings['SERVER_HOST'], ['class' => $inputClass, 'placeholder' => __('Enter smtp host')]) }}
        </div>
        <div class="space-y-2">
            {{ Form::label('server_username', __('SMTP Username'), ['class' => $labelClass]) }}
            {{ Form::text('server_username', $settings['SERVER_USERNAME'], ['class' => $inputClass, 'placeholder' => __('Enter smtp username')]) }}
        </div>
        <div class="space-y-2">
            {{ Form::label('server_password', __('SMTP Password'), ['class' => $labelClass]) }}
            {{ Form::text('server_password', $settings['SERVER_PASSWORD'], ['class' => $inputClass, 'placeholder' => __('Enter smtp password')]) }}
        </div>
        <div class="space-y-2">
            {{ Form::label('server_encryption', __('SMTP Encryption'), ['class' => $labelClass]) }}
            {{ Form::text('server_encryption', $settings['SERVER_ENCRYPTION'], ['class' => $inputClass, 'placeholder' => __('Enter smtp encryption')]) }}
        </div>
        <div class="space-y-2">
            {{ Form::label('server_port', __('SMTP Port'), ['class' => $labelClass]) }}
            {{ Form::text('server_port', $settings['SERVER_PORT'], ['class' => $inputClass, 'placeholder' => __('Enter smtp port')]) }}
        </div>
    </div>

    <div class="flex items-center justify-end gap-3 pt-2">
        <a href="#" data-size="md"
            data-url="{{ route('setting.smtp.test') }}"
            data-title="{{ __('Add Email') }}"
            class="customModal {{ $secondaryButton }}">
            {{ __('Test Mail') }}
        </a>
        {{ Form::submit(__('Save'), ['class' => $primaryButton]) }}
    </div>
</div>
{{ Form::close() }}
