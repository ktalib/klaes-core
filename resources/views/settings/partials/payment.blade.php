{{-- Payment Settings --}}
{{ Form::model($settings, ['route' => ['setting.payment'], 'method' => 'post']) }}
<div class="space-y-6">
    <div class="{{ $twoColumnGrid }}">
        <div class="space-y-2">
            {{ Form::label('CURRENCY_SYMBOL', __('Currency Icon'), ['class' => $labelClass]) }}
            {{ Form::text('CURRENCY_SYMBOL', $settings['CURRENCY_SYMBOL'], ['class' => $inputClass, 'placeholder' => __('Enter currency icon'), 'required']) }}
        </div>
        <div class="space-y-2">
            {{ Form::label('CURRENCY', __('Currency Code'), ['class' => $labelClass]) }}
            {{ Form::text('CURRENCY', $settings['CURRENCY'], ['class' => $inputClass, 'placeholder' => __('Enter currency code'), 'required']) }}
        </div>
    </div>

    <div class="border-t border-slate-100"></div>

    {{-- Stripe --}}
    <div class="space-y-4">
        <div class="flex items-center justify-between">
            {{ Form::label('stripe_payment', __('Stripe Payment'), ['class' => $labelClass]) }}
            <label class="relative inline-flex cursor-pointer items-center">
                <input type="checkbox" name="stripe_payment" id="stripe_payment"
                    class="peer sr-only"
                    {{ $settings['STRIPE_PAYMENT'] == 'on' ? 'checked' : '' }}>
                <div class="h-6 w-11 rounded-full bg-slate-200 after:absolute after:left-[2px] after:top-[2px] after:h-5 after:w-5 after:rounded-full after:border after:border-slate-300 after:bg-white after:transition-all after:content-[''] peer-checked:bg-blue-600 peer-checked:after:translate-x-full peer-checked:after:border-white peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-100"></div>
            </label>
        </div>
        <div class="{{ $twoColumnGrid }}">
            <div class="space-y-2">
                {{ Form::label('stripe_key', __('Account Key'), ['class' => $labelClass]) }}
                {{ Form::text('stripe_key', $settings['STRIPE_KEY'], ['class' => $inputClass, 'placeholder' => __('Enter stripe key')]) }}
            </div>
            <div class="space-y-2">
                {{ Form::label('stripe_secret', __('Account Secret Key'), ['class' => $labelClass]) }}
                {{ Form::text('stripe_secret', $settings['STRIPE_SECRET'], ['class' => $inputClass, 'placeholder' => __('Enter stripe secret')]) }}
            </div>
        </div>
    </div>

    <div class="border-t border-slate-100"></div>

    {{-- PayPal --}}
    <div class="space-y-4">
        <div class="flex items-center justify-between">
            {{ Form::label('paypal_payment', __('Paypal Payment'), ['class' => $labelClass]) }}
            <label class="relative inline-flex cursor-pointer items-center">
                <input type="checkbox" name="paypal_payment" id="paypal_payment"
                    class="peer sr-only"
                    {{ $settings['paypal_payment'] == 'on' ? 'checked' : '' }}>
                <div class="h-6 w-11 rounded-full bg-slate-200 after:absolute after:left-[2px] after:top-[2px] after:h-5 after:w-5 after:rounded-full after:border after:border-slate-300 after:bg-white after:transition-all after:content-[''] peer-checked:bg-blue-600 peer-checked:after:translate-x-full peer-checked:after:border-white peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-100"></div>
            </label>
        </div>
        <div class="space-y-4">
            <div class="space-y-2">
                {{ Form::label('paypal_mode', __('Account Mode'), ['class' => $labelClass]) }}
                <div class="flex items-center gap-6 pt-1">
                    <label for="sandbox" class="flex cursor-pointer items-center gap-2">
                        <input type="radio" value="sandbox" id="sandbox" name="paypal_mode"
                            class="h-4 w-4 border-slate-300 text-blue-600 focus:ring-blue-500"
                            {{ $settings['paypal_mode'] == 'sandbox' ? 'checked' : '' }}>
                        <span class="text-sm font-medium text-slate-700">{{ __('Sandbox') }}</span>
                    </label>
                    <label for="live" class="flex cursor-pointer items-center gap-2">
                        <input type="radio" value="live" id="live" name="paypal_mode"
                            class="h-4 w-4 border-slate-300 text-blue-600 focus:ring-blue-500"
                            {{ $settings['paypal_mode'] == 'live' ? 'checked' : '' }}>
                        <span class="text-sm font-medium text-slate-700">{{ __('Live') }}</span>
                    </label>
                </div>
            </div>
            <div class="{{ $twoColumnGrid }}">
                <div class="space-y-2">
                    {{ Form::label('paypal_client_id', __('Account Client ID'), ['class' => $labelClass]) }}
                    {{ Form::text('paypal_client_id', $settings['paypal_client_id'], ['class' => $inputClass, 'placeholder' => __('Enter client id')]) }}
                </div>
                <div class="space-y-2">
                    {{ Form::label('paypal_secret_key', __('Account Secret Key'), ['class' => $labelClass]) }}
                    {{ Form::text('paypal_secret_key', $settings['paypal_secret_key'], ['class' => $inputClass, 'placeholder' => __('Enter secret key')]) }}
                </div>
            </div>
        </div>
    </div>

    <div class="border-t border-slate-100"></div>

    {{-- Bank Transfer --}}
    <div class="space-y-4">
        <div class="flex items-center justify-between">
            {{ Form::label('bank_transfer_payment', __('Bank Transfer Payment'), ['class' => $labelClass]) }}
            <label class="relative inline-flex cursor-pointer items-center">
                <input type="checkbox" name="bank_transfer_payment" id="bank_transfer_payment"
                    class="peer sr-only"
                    {{ $settings['bank_transfer_payment'] == 'on' ? 'checked' : '' }}>
                <div class="h-6 w-11 rounded-full bg-slate-200 after:absolute after:left-[2px] after:top-[2px] after:h-5 after:w-5 after:rounded-full after:border after:border-slate-300 after:bg-white after:transition-all after:content-[''] peer-checked:bg-blue-600 peer-checked:after:translate-x-full peer-checked:after:border-white peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-100"></div>
            </label>
        </div>
        <div class="{{ $twoColumnGrid }}">
            <div class="space-y-2">
                {{ Form::label('bank_name', __('Bank Name'), ['class' => $labelClass]) }}
                {{ Form::text('bank_name', $settings['bank_name'], ['class' => $inputClass, 'placeholder' => __('Enter bank name')]) }}
            </div>
            <div class="space-y-2">
                {{ Form::label('bank_holder_name', __('Bank Holder Name'), ['class' => $labelClass]) }}
                {{ Form::text('bank_holder_name', $settings['bank_holder_name'], ['class' => $inputClass, 'placeholder' => __('Enter bank holder name')]) }}
            </div>
            <div class="space-y-2">
                {{ Form::label('bank_account_number', __('Bank Account Number'), ['class' => $labelClass]) }}
                {{ Form::text('bank_account_number', $settings['bank_account_number'], ['class' => $inputClass, 'placeholder' => __('Enter bank account number')]) }}
            </div>
            <div class="space-y-2">
                {{ Form::label('bank_ifsc_code', __('Bank IFSC'), ['class' => $labelClass]) }}
                {{ Form::text('bank_ifsc_code', $settings['bank_ifsc_code'], ['class' => $inputClass, 'placeholder' => __('Enter bank ifsc code')]) }}
            </div>
            <div class="md:col-span-2 space-y-2">
                {{ Form::label('bank_other_details', __('Other Details'), ['class' => $labelClass]) }}
                {{ Form::textarea('bank_other_details', $settings['bank_other_details'], ['class' => $textareaClass, 'rows' => 1, 'placeholder' => __('Enter bank other details')]) }}
            </div>
        </div>
    </div>

    <div class="border-t border-slate-100"></div>

    {{-- Flutterwave --}}
    <div class="space-y-4">
        <div class="flex items-center justify-between">
            {{ Form::label('flutterwave_payment', __('Flutterwave Payment'), ['class' => $labelClass]) }}
            <label class="relative inline-flex cursor-pointer items-center">
                <input type="checkbox" name="flutterwave_payment" id="flutterwave_payment"
                    class="peer sr-only"
                    {{ $settings['flutterwave_payment'] == 'on' ? 'checked' : '' }}>
                <div class="h-6 w-11 rounded-full bg-slate-200 after:absolute after:left-[2px] after:top-[2px] after:h-5 after:w-5 after:rounded-full after:border after:border-slate-300 after:bg-white after:transition-all after:content-[''] peer-checked:bg-blue-600 peer-checked:after:translate-x-full peer-checked:after:border-white peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-100"></div>
            </label>
        </div>
        <div class="{{ $twoColumnGrid }}">
            <div class="space-y-2">
                {{ Form::label('flutterwave_public_key', __('Public Key'), ['class' => $labelClass]) }}
                {{ Form::text('flutterwave_public_key', $settings['flutterwave_public_key'], ['class' => $inputClass, 'placeholder' => __('Enter flutterwave public key')]) }}
            </div>
            <div class="space-y-2">
                {{ Form::label('flutterwave_secret_key', __('Secret Key'), ['class' => $labelClass]) }}
                {{ Form::text('flutterwave_secret_key', $settings['flutterwave_secret_key'], ['class' => $inputClass, 'placeholder' => __('Enter flutterwave secret key')]) }}
            </div>
        </div>
    </div>

    <div class="flex justify-end pt-2">
        {{ Form::submit(__('Save'), ['class' => $primaryButton]) }}
    </div>
</div>
{{ Form::close() }}
