{{-- Password Settings --}}
{{ Form::model($loginUser, ['route' => ['setting.password'], 'method' => 'post']) }}
<div class="space-y-6">
    <div class="space-y-5">
        <div class="space-y-2">
            {{ Form::label('current_password', __('Current Password'), ['class' => $labelClass]) }}
            {{ Form::password('current_password', ['class' => $inputClass, 'placeholder' => __('Enter your current password'), 'required' => 'required']) }}
        </div>
        <div class="space-y-2">
            {{ Form::label('new_password', __('New Password'), ['class' => $labelClass]) }}
            {{ Form::password('new_password', ['class' => $inputClass, 'placeholder' => __('Enter your new password'), 'required' => 'required']) }}
        </div>
        <div class="space-y-2">
            {{ Form::label('confirm_password', __('Confirm New Password'), ['class' => $labelClass]) }}
            {{ Form::password('confirm_password', ['class' => $inputClass, 'placeholder' => __('Enter your confirm new password'), 'required' => 'required']) }}
        </div>
    </div>

    <div class="flex justify-end pt-2">
        {{ Form::submit(__('Save'), ['class' => $primaryButton]) }}
    </div>
</div>
{{ Form::close() }}
