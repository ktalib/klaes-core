{{-- Multipurpose Settings: Brand & Work Station Targets --}}
{{ Form::open(['route' => ['setting.multipurpose'], 'method' => 'post', 'enctype' => 'multipart/form-data']) }}
<div class="space-y-8">
    {{-- Brand Identity Section --}}
    <div class="{{ $sectionCard }}">
        <div class="{{ $sectionHeader }}">
            <h5 class="text-base font-bold text-slate-800">{{ __('Brand Identity') }}</h5>
            <p class="mt-1 text-xs text-slate-500">{{ __('Update the logo and labels that appear across the suite.') }}</p>
        </div>
        <div class="{{ $sectionBody }}">
            <div class="{{ $twoColumnGrid }}">
                <div class="space-y-2">
                    {{ Form::label('site_name', __('Site Name'), ['class' => $labelClass]) }}
                    {{ Form::text('site_name', $settings['app_name'], ['class' => $inputClass, 'placeholder' => __('Enter site name'), 'required' => true]) }}
                </div>
                <div class="space-y-2">
                    {{ Form::label('site_tagline', __('Tagline'), ['class' => $labelClass]) }}
                    {{ Form::text('site_tagline', $settings['site_tagline'], ['class' => $inputClass, 'placeholder' => __('Short description (optional)')]) }}
                </div>
            </div>
            <div class="{{ $threeColumnGrid }}">
                <div class="space-y-2">
                    {{ Form::label('brand_logo', __('Primary Logo'), ['class' => $labelClass]) }}
                    {{ Form::file('brand_logo', ['class' => $fileInputClass]) }}
                    <p class="mt-1 text-xs text-slate-400">{{ __('PNG/JPG/SVG up to 4MB') }}</p>
                    @if (!empty($settings['company_logo']))
                        <img src="{{ asset(Storage::url('upload/logo')) . '/' . $settings['company_logo'] }}"
                            class="mt-2 h-16 w-auto rounded-xl border border-slate-100 bg-white p-2 shadow-sm"
                            alt="{{ __('Primary Logo') }}">
                    @endif
                </div>
                <div class="space-y-2">
                    {{ Form::label('brand_light_logo', __('Light Logo'), ['class' => $labelClass]) }}
                    {{ Form::file('brand_light_logo', ['class' => $fileInputClass]) }}
                    <p class="mt-1 text-xs text-slate-400">{{ __('Alternate/white mark (optional)') }}</p>
                    @if (!empty($settings['light_logo']))
                        <img src="{{ asset(Storage::url('upload/logo')) . '/' . $settings['light_logo'] }}"
                            class="mt-2 h-16 w-auto rounded-xl border border-slate-100 bg-white p-2 shadow-sm"
                            alt="{{ __('Light Logo') }}">
                    @endif
                </div>
                <div class="space-y-2">
                    {{ Form::label('brand_favicon', __('Favicon'), ['class' => $labelClass]) }}
                    {{ Form::file('brand_favicon', ['class' => $fileInputClass]) }}
                    <p class="mt-1 text-xs text-slate-400">{{ __('Square icon, max 2MB') }}</p>
                    @if (!empty($settings['company_favicon']))
                        <img src="{{ asset(Storage::url('upload/logo')) . '/' . $settings['company_favicon'] }}"
                            class="mt-2 h-16 w-16 rounded-xl border border-slate-100 bg-white p-2 shadow-sm"
                            alt="{{ __('Favicon') }}">
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- Work Station Targets Section --}}
    <div class="{{ $sectionCard }}">
        <div class="{{ $sectionHeader }}">
            <h5 class="text-base font-bold text-slate-800">{{ __('Work Station Targets') }}</h5>
            <p class="mt-1 text-xs text-slate-500">{{ __('Define expected units per work station so productivity reports reflect accurate goals.') }}</p>
        </div>
        <div class="{{ $sectionBody }}">
            <div class="{{ $twoColumnGrid }}">
                @forelse ($workStationModules as $moduleKey => $moduleLabel)
                    <div class="space-y-2">
                        {{ Form::label('target_' . $moduleKey, $moduleLabel, ['class' => $labelClass]) }}
                        {{ Form::number('target_' . $moduleKey, $settings['activity_target_' . $moduleKey] ?? 0, ['class' => $inputClass, 'min' => 0, 'required' => true]) }}
                    </div>
                @empty
                    <p class="col-span-2 text-sm text-slate-500">{{ __('Configure work stations with module keys to enable target tracking.') }}</p>
                @endforelse
            </div>
        </div>
    </div>

    <div class="flex justify-end pt-2">
        {{ Form::submit(__('Save Changes'), ['class' => $primaryButton]) }}
    </div>
</div>
{{ Form::close() }}
