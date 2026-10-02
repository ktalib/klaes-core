{{-- User Profile Settings --}}
{{ Form::model($loginUser, ['route' => ['setting.account'], 'method' => 'post', 'enctype' => 'multipart/form-data']) }}
<div class="space-y-6">
    <div class="flex items-center gap-4">
        <img src="{{ $users->profile_url ?? $profile . '/avatar.png' }}"
            alt="user-image"
            class="h-20 w-20 rounded-2xl border-4 border-white object-cover shadow-lg" />
    </div>

    <div class="{{ $twoColumnGrid }}">
        <div class="space-y-2">
            {{ Form::label('name', __('Name'), ['class' => $labelClass]) }}
            {{ Form::text('name', null, ['class' => $inputClass, 'placeholder' => __('Enter your name'), 'required' => 'required']) }}
        </div>
        <div class="space-y-2">
            {{ Form::label('email', __('Email Address'), ['class' => $labelClass]) }}
            {{ Form::text('email', null, ['class' => $inputClass, 'placeholder' => __('Enter your email'), 'required' => 'required']) }}
        </div>
        <div class="space-y-2">
            {{ Form::label('phone_number', __('Phone Number'), ['class' => $labelClass]) }}
            {{ Form::number('phone_number', null, ['class' => $inputClass, 'placeholder' => __('Enter your Phone Number')]) }}
        </div>
        <div class="space-y-2">
            {{ Form::label('profile', __('Profile'), ['class' => $labelClass]) }}
            {{ Form::file('profile', ['class' => $fileInputClass]) }}
        </div>
    </div>

    <div class="flex justify-end pt-2">
        {{ Form::submit(__('Save'), ['class' => $primaryButton]) }}
    </div>
</div>
{{ Form::close() }}
