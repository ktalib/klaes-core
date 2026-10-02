{{-- General Settings --}}
{{ Form::model($settings, ['route' => ['setting.general'], 'method' => 'post', 'enctype' => 'multipart/form-data']) }}
<div class="space-y-6">
    <div class="{{ $twoColumnGrid }}">
        <div class="md:col-span-2 space-y-2">
            {{ Form::label('application_name', __('Application Name'), ['class' => $labelClass]) }}
            {{ Form::text('application_name', !empty($settings['app_name']) ? $settings['app_name'] : env('APP_NAME'), ['class' => $inputClass, 'placeholder' => __('Enter your application name'), 'required' => 'required']) }}
        </div>
        <div class="space-y-2">
            {{ Form::label('logo', __('Logo'), ['class' => $labelClass]) }}
            <a href="{{ asset(Storage::url('upload/logo/')) . '/' . (isset($admin_logo) && !empty($admin_logo) ? $admin_logo : 'logo.png') }}"
                target="_blank" class="ml-2 text-blue-500 hover:text-blue-700"><i class="ti ti-eye text-sm"></i></a>
            {{ Form::file('logo', ['class' => $fileInputClass]) }}
        </div>
        <div class="space-y-2">
            {{ Form::label('favicon', __('Favicon'), ['class' => $labelClass]) }}
            <a href="{{ asset(Storage::url('upload/logo')) . '/' . $settings['company_favicon'] }}"
                target="_blank" class="ml-2 text-blue-500 hover:text-blue-700"><i class="ti ti-eye text-sm"></i></a>
            {{ Form::file('favicon', ['class' => $fileInputClass]) }}
        </div>
        <div class="space-y-2">
            {{ Form::label('light_logo', __('Light Logo'), ['class' => $labelClass]) }}
            <a href="{{ asset(Storage::url('upload/logo')) . '/' . $settings['light_logo'] }}"
                target="_blank" class="ml-2 text-blue-500 hover:text-blue-700"><i class="ti ti-eye text-sm"></i></a>
            {{ Form::file('light_logo', ['class' => $fileInputClass]) }}
        </div>
    </div>

    @if (\Auth::user()->type == 'super admin')
        <div class="{{ $twoColumnGrid }}">
            <div class="space-y-2">
                {{ Form::label('landing_logo', __('Landing Page Logo'), ['class' => $labelClass]) }}
                <a href="{{ asset(Storage::url('upload/logo/landing_logo.png')) }}"
                    target="_blank" class="ml-2 text-blue-500 hover:text-blue-700"><i class="ti ti-eye text-sm"></i></a>
                {{ Form::file('landing_logo', ['class' => $fileInputClass]) }}
            </div>
            <div class="space-y-2">
                {{ Form::label('owner_email_verification_label', __('Owner Email Verification'), ['class' => $labelClass]) }}
                <label class="relative inline-flex cursor-pointer items-center">
                    <input type="checkbox" id="owner_email_verification" name="owner_email_verification"
                        class="peer sr-only"
                        {{ $settings['owner_email_verification'] == 'on' ? 'checked' : '' }}>
                    <div class="h-6 w-11 rounded-full bg-slate-200 after:absolute after:left-[2px] after:top-[2px] after:h-5 after:w-5 after:rounded-full after:border after:border-slate-300 after:bg-white after:transition-all after:content-[''] peer-checked:bg-blue-600 peer-checked:after:translate-x-full peer-checked:after:border-white peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-100"></div>
                </label>
            </div>
            <div class="space-y-2">
                {{ Form::label('register_page_label', __('Registration Page'), ['class' => $labelClass]) }}
                <label class="relative inline-flex cursor-pointer items-center">
                    <input type="checkbox" id="register_page" name="register_page"
                        class="peer sr-only"
                        {{ $settings['register_page'] == 'on' ? 'checked' : '' }}>
                    <div class="h-6 w-11 rounded-full bg-slate-200 after:absolute after:left-[2px] after:top-[2px] after:h-5 after:w-5 after:rounded-full after:border after:border-slate-300 after:bg-white after:transition-all after:content-[''] peer-checked:bg-blue-600 peer-checked:after:translate-x-full peer-checked:after:border-white peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-100"></div>
                </label>
            </div>
            <div class="space-y-2">
                {{ Form::label('landing_page_label', __('Landing Page'), ['class' => $labelClass]) }}
                <label class="relative inline-flex cursor-pointer items-center">
                    <input type="checkbox" id="landing_page" name="landing_page"
                        class="peer sr-only"
                        {{ $settings['landing_page'] == 'on' ? 'checked' : '' }}>
                    <div class="h-6 w-11 rounded-full bg-slate-200 after:absolute after:left-[2px] after:top-[2px] after:h-5 after:w-5 after:rounded-full after:border after:border-slate-300 after:bg-white after:transition-all after:content-[''] peer-checked:bg-blue-600 peer-checked:after:translate-x-full peer-checked:after:border-white peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-100"></div>
                </label>
            </div>
            <div class="space-y-2">
                {{ Form::label('pricing_feature_label', __('Pricing Feature'), ['class' => $labelClass]) }}
                <label class="relative inline-flex cursor-pointer items-center">
                    <input type="hidden" name="pricing_feature" value="off">
                    <input type="checkbox" id="pricing_feature" name="pricing_feature" value="on"
                        class="peer sr-only"
                        {{ $settings['pricing_feature'] == 'on' ? 'checked' : '' }}>
                    <div class="h-6 w-11 rounded-full bg-slate-200 after:absolute after:left-[2px] after:top-[2px] after:h-5 after:w-5 after:rounded-full after:border after:border-slate-300 after:bg-white after:transition-all after:content-[''] peer-checked:bg-blue-600 peer-checked:after:translate-x-full peer-checked:after:border-white peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-100"></div>
                </label>
            </div>
        </div>
    @endif

    <div class="flex justify-end pt-2">
        {{ Form::submit(__('Save'), ['class' => $primaryButton]) }}
    </div>
</div>
{{ Form::close() }}
