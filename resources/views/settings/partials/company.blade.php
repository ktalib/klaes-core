{{-- Company Settings --}}
{{ Form::model($settings, ['route' => ['setting.company'], 'method' => 'post']) }}
<div class="space-y-6">
    <div class="{{ $twoColumnGrid }}">
        <div class="space-y-2">
            {{ Form::label('company_name', __('Name'), ['class' => $labelClass]) }}
            {{ Form::text('company_name', $settings['company_name'], ['class' => $inputClass, 'placeholder' => __('Enter company name')]) }}
        </div>
        <div class="space-y-2">
            {{ Form::label('company_email', __('Email'), ['class' => $labelClass]) }}
            {{ Form::text('company_email', $settings['company_email'], ['class' => $inputClass, 'placeholder' => __('Enter company email')]) }}
        </div>
        <div class="space-y-2">
            {{ Form::label('company_phone', __('Phone Number'), ['class' => $labelClass]) }}
            {{ Form::text('company_phone', $settings['company_phone'], ['class' => $inputClass, 'placeholder' => __('Enter company phone')]) }}
        </div>
        <div class="space-y-2">
            {{ Form::label('company_address', __('Address'), ['class' => $labelClass]) }}
            {{ Form::textarea('company_address', $settings['company_address'], ['class' => $textareaClass, 'rows' => '2']) }}
        </div>
        <div class="space-y-2">
            {{ Form::label('CURRENCY_SYMBOL', __('Currency Icon'), ['class' => $labelClass]) }}
            {{ Form::text('CURRENCY_SYMBOL', $settings['CURRENCY_SYMBOL'], ['class' => $inputClass, 'placeholder' => __('Enter currency symbol')]) }}
        </div>
        <div class="space-y-2">
            {{ Form::label('timezone', __('Timezone'), ['class' => $labelClass]) }}
            <select type="text" name="timezone" id="timezone"
                class="basic-select {{ $inputClass }}">
                <option value="">{{ __('Select Timezone') }}</option>
                @foreach ($timezones as $k => $timezone)
                    <option value="{{ $k }}"
                        {{ $settings['timezone'] == $k ? 'selected' : '' }}>
                        {{ $timezone }}</option>
                @endforeach
            </select>
        </div>
        <div class="space-y-2">
            {{ Form::label('invoice_number_prefix', __('Invoice Number Prefix'), ['class' => $labelClass]) }}
            {{ Form::text('invoice_number_prefix', $settings['invoice_number_prefix'], ['class' => $inputClass, 'placeholder' => __('Enter invoice number prefix')]) }}
        </div>
        <div class="space-y-2">
            {{ Form::label('expense_number_prefix', __('Expense Number Prefix'), ['class' => $labelClass]) }}
            {{ Form::text('expense_number_prefix', $settings['expense_number_prefix'], ['class' => $inputClass, 'placeholder' => __('Enter expense number prefix')]) }}
        </div>
    </div>

    {{-- Date & Time Format --}}
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
        <div class="space-y-2">
            {{ Form::label('company_date_format_label', __('System Date Format'), ['class' => $labelClass]) }}
            <div class="space-y-2 pt-1">
                @foreach ([
                    ['id' => 'company_date_format1', 'value' => 'M j, Y', 'display' => date('M d,Y')],
                    ['id' => 'company_date_format2', 'value' => 'y-m-d', 'display' => date('y-m-d')],
                    ['id' => 'company_date_format3', 'value' => 'd-m-y', 'display' => date('d-m-y')],
                    ['id' => 'company_date_format4', 'value' => 'm-d-y', 'display' => date('m-d-y')],
                ] as $opt)
                    <label for="{{ $opt['id'] }}" class="flex cursor-pointer items-center gap-3 rounded-xl border border-slate-200 px-4 py-2.5 transition hover:border-blue-300 hover:bg-blue-50/50 has-[:checked]:border-blue-500 has-[:checked]:bg-blue-50">
                        <input type="radio" id="{{ $opt['id'] }}" name="company_date_format"
                            value="{{ $opt['value'] }}"
                            class="h-4 w-4 border-slate-300 text-blue-600 focus:ring-blue-500"
                            {{ $settings['company_date_format'] == $opt['value'] ? 'checked' : '' }}>
                        <span class="text-sm font-medium text-slate-700">{{ $opt['display'] }}</span>
                    </label>
                @endforeach
            </div>
        </div>
        <div class="space-y-2">
            {{ Form::label('company_time_format_label', __('System Time Format'), ['class' => $labelClass]) }}
            <div class="space-y-2 pt-1">
                @foreach ([
                    ['id' => 'company_time_format1', 'value' => 'H:i', 'display' => date('H:i')],
                    ['id' => 'company_time_format2', 'value' => 'g:i A', 'display' => date('g:i A')],
                    ['id' => 'company_time_format3', 'value' => 'g:i a', 'display' => date('g:i a')],
                ] as $opt)
                    <label for="{{ $opt['id'] }}" class="flex cursor-pointer items-center gap-3 rounded-xl border border-slate-200 px-4 py-2.5 transition hover:border-blue-300 hover:bg-blue-50/50 has-[:checked]:border-blue-500 has-[:checked]:bg-blue-50">
                        <input type="radio" id="{{ $opt['id'] }}" name="company_time_format"
                            value="{{ $opt['value'] }}"
                            class="h-4 w-4 border-slate-300 text-blue-600 focus:ring-blue-500"
                            {{ $settings['company_time_format'] == $opt['value'] ? 'checked' : '' }}>
                        <span class="text-sm font-medium text-slate-700">{{ $opt['display'] }}</span>
                    </label>
                @endforeach
            </div>
        </div>
    </div>

    <div class="flex justify-end pt-2">
        {{ Form::submit(__('Save'), ['class' => $primaryButton]) }}
    </div>
</div>
{{ Form::close() }}
